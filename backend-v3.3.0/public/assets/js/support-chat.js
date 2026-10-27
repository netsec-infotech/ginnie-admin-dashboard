(function () {
    const app = document.getElementById('support-app');
    if (!app) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const realtime = JSON.parse(app.dataset.realtime || '{}');
    const pollInterval = Math.max(15000, Number(app.dataset.pollInterval || 15000));
    const pollingEnabled = app.dataset.pollingEnabled === '1';
    const adminRealtime = window.SupportAdminRealtime || {
        mode: 'connecting', configured: Boolean(realtime.key), failures: 0, threadUuid: null,
    };
    window.SupportAdminRealtime = adminRealtime;
    const state = {
        scope: ['unassigned', 'mine', 'all'].includes(app.dataset.initialScope) ? app.dataset.initialScope : 'unassigned',
        query: '',
        status: '',
        topicId: '',
        callback: 'all',
        rating: '',
        page: 1,
        lastPage: 1,
        rows: [],
        thread: null,
        messageCursor: 0,
        inboxLoading: false,
        inboxQueued: false,
        inboxLoaded: false,
        conversationLoading: false,
        polling: false,
        socketLive: false,
        inboxSubscribed: false,
        threadSubscribed: false,
        realtimeFailures: 0,
        realtimeHealthTimer: null,
        echo: null,
        threadChannelUuid: null,
        privateThreadChannel: null,
        presenceThreadChannel: null,
        customerOnline: false,
        typingThreads: new Map(),
        localTypingTimer: null,
        localTyping: false,
        selectedUuid: null,
        attachments: [],
        sending: false,
        clientMessageId: null,
        topics: [],
        selectedTopicId: null,
        initialThreadOpened: false,
        scopeCounts: {unassigned: 0, mine: 0, all: 0},
        historyLoading: false,
        hasOlderMessages: false,
        oldestMessageId: null,
    };
    const attachmentPreviewUrls = new Map();
    const el = id => document.getElementById(id);
    const endpoint = (name, key, value) => app.dataset[name].replace(key, encodeURIComponent(value));
    const initials = name => (name || '?').split(/\s+/).slice(0, 2).map(part => part[0]).join('').toUpperCase();
    const formatTime = value => {
        if (!value) return '';
        const date = new Date(value);
        const sameDay = date.toDateString() === new Date().toDateString();
        return new Intl.DateTimeFormat(undefined, sameDay
            ? {hour: 'numeric', minute: '2-digit', hour12: true}
            : {day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true}
        ).format(date);
    };
    const activeSession = () => state.thread?.sessions?.find(session => session.id === state.thread.active_ticket_id) || null;
    const latestMessageId = () => Math.max(0, ...(state.thread?.sessions || []).flatMap(session => session.messages || []).map(message => message.id));
    const messageList = () => el('support-message-list');

    function node(tag, className, text) {
        const item = document.createElement(tag);
        if (className) item.className = className;
        if (text !== undefined && text !== null) item.textContent = String(text);
        return item;
    }

    function showToast(icon, title) {
        if (typeof Toast !== 'undefined') {
            Toast.fire({icon, title});
            return;
        }
        window.Swal?.fire({toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, icon, title});
    }

    function setConnection(mode) {
        const badge = el('support-connection');
        badge.className = 'support-connection small fw-medium d-inline-flex align-items-center gap-1';
        if (mode === 'live') {
            badge.classList.add('text-green');
        } else if (mode === 'offline') {
            badge.classList.add('text-red');
        } else if (mode === 'socket') {
            badge.classList.add('text-blue');
        } else {
            badge.classList.add('text-yellow');
        }
        const label = mode === 'live' ? 'Live' : mode === 'offline' ? 'Offline' : mode === 'socket' ? 'Socket only' : 'Polling';
        badge.replaceChildren(node('span', 'support-connection-dot'), document.createTextNode(label));
    }

    function setRealtimeAlert(visible) {
        const alert = el('support-realtime-alert');
        alert?.classList.toggle('d-none', !visible);
        alert?.classList.toggle('d-flex', visible);
    }

    function clearRealtimeHealthTimer() {
        if (!state.realtimeHealthTimer) return;
        clearTimeout(state.realtimeHealthTimer);
        state.realtimeHealthTimer = null;
    }

    function markRealtimeHealthy() {
        state.realtimeFailures = 0;
        clearRealtimeHealthTimer();
        setRealtimeAlert(false);
    }

    function scheduleRealtimeHealthCheck() {
        clearRealtimeHealthTimer();
        if (!navigator.onLine) return;
        state.realtimeHealthTimer = setTimeout(() => {
            state.realtimeHealthTimer = null;
            if (!state.socketLive && navigator.onLine) setRealtimeAlert(true);
        }, 12000);
    }

    function recordRealtimeFailure(showImmediately = false) {
        if (!navigator.onLine) return;
        state.realtimeFailures += 1;
        if (showImmediately || state.realtimeFailures >= 3) setRealtimeAlert(true);
        scheduleRealtimeHealthCheck();
    }

    function syncSocketState() {
        state.socketLive = state.inboxSubscribed && (!state.selectedUuid || state.threadSubscribed);
        adminRealtime.mode = state.socketLive ? 'live' : 'unavailable';
        adminRealtime.configured = Boolean(realtime.key && ['reverb', 'pusher'].includes(realtime.driver));
        adminRealtime.failures = state.realtimeFailures;
        adminRealtime.threadUuid = state.selectedUuid || null;
        window.dispatchEvent(new CustomEvent('support:realtime', {detail: {...adminRealtime}}));
        setConnection(state.socketLive ? 'live' : (navigator.onLine && pollingEnabled ? 'polling' : 'offline'));
        if (state.socketLive) markRealtimeHealthy();
        return state.socketLive;
    }

    function setCustomerOnline(isOnline) {
        state.customerOnline = isOnline;
        el('support-customer-presence')?.classList.toggle('d-none', !isOnline);
    }

    function setRemoteTyping(isTyping) {
        const indicator = el('support-typing');
        if (!indicator) return;
        indicator.classList.toggle('d-none', !isTyping);
        if (!isTyping) return;
        const avatar = el('support-typing-avatar');
        const customer = state.thread?.customer;
        avatar.textContent = initials(customer?.name);
        avatar.style.backgroundImage = '';
        if (customer?.avatar) {
            avatar.style.backgroundImage = `url("${String(customer.avatar).replace(/["\\]/g, '')}")`;
            avatar.textContent = '';
        }
        requestAnimationFrame(() => el('support-messages').scrollTo({top: el('support-messages').scrollHeight, behavior: 'smooth'}));
    }

    function syncInboxTyping(button, uuid) {
        const isTyping = state.typingThreads.has(uuid);
        button.querySelector('.support-inbox-preview')?.classList.toggle('d-none', isTyping);
        button.querySelector('.support-inbox-typing')?.classList.toggle('d-none', !isTyping);
    }

    function setThreadTyping(uuid, isTyping) {
        if (!uuid) return;
        window.clearTimeout(state.typingThreads.get(uuid));
        state.typingThreads.delete(uuid);
        if (isTyping) {
            state.typingThreads.set(uuid, window.setTimeout(() => setThreadTyping(uuid, false), 1800));
        }
        const button = [...el('support-inbox').children].find(item => item.dataset.threadUuid === uuid);
        if (button) syncInboxTyping(button, uuid);
        if (uuid === state.selectedUuid) setRemoteTyping(isTyping);
    }

    function clearThreadTyping() {
        [...state.typingThreads.keys()].forEach(uuid => setThreadTyping(uuid, false));
    }

    function whisperTyping(isTyping) {
        window.clearTimeout(state.localTypingTimer);
        if (state.selectedUuid && state.localTyping !== isTyping) {
            state.localTyping = isTyping;
            if (typeof state.privateThreadChannel?.whisper === 'function') {
                state.privateThreadChannel?.whisper('typing', {role: 'admin', typing: isTyping});
            }
            request(endpoint('typingUrl', '__THREAD__', state.selectedUuid), {
                method: 'POST',
                body: JSON.stringify({typing: isTyping}),
            }).catch(() => null);
        }
        if (isTyping) state.localTypingTimer = window.setTimeout(() => whisperTyping(false), 1400);
    }

    function syncUrlState() {
        const url = new URL(window.location.href);
        if (state.selectedUuid) url.searchParams.set('thread', state.selectedUuid);
        else url.searchParams.delete('thread');
        url.searchParams.set('scope', state.scope);
        window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
    }

    function setFullscreen(enabled) {
        app.classList.toggle('support-fullscreen', enabled);
        document.body.classList.toggle('support-fullscreen-open', enabled);
        const toggle = el('support-fullscreen-toggle');
        toggle?.setAttribute('aria-pressed', enabled ? 'true' : 'false');
        toggle?.setAttribute('aria-label', enabled ? 'Exit full screen' : 'Enter full screen');
        if (toggle) toggle.title = enabled ? 'Exit full screen' : 'Full screen';
        const icon = toggle?.querySelector('i');
        if (icon) icon.className = `ti ${enabled ? 'ti-arrows-minimize' : 'ti-arrows-maximize'}`;
    }

    async function request(url, options = {}) {
        const headers = {Accept: 'application/json', 'X-CSRF-TOKEN': csrf, ...(options.headers || {})};
        if (options.body && !(options.body instanceof FormData)) headers['Content-Type'] = 'application/json';
        const response = await fetch(url, {...options, headers, credentials: 'same-origin'});
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            const error = new Error(payload.message || Object.values(payload.errors || {}).flat()[0] || 'Request failed.');
            error.status = response.status;
            throw error;
        }
        return payload.meta ? payload : (payload.data ?? payload);
    }

    function renderInboxSkeleton() {
        const inbox = el('support-inbox');
        inbox.replaceChildren();
        for (let index = 0; index < 6; index += 1) {
            const row = node('div', 'p-3 border-bottom');
            row.innerHTML = '<div class="placeholder-glow d-flex gap-3"><span class="avatar placeholder"></span><div class="flex-fill"><span class="placeholder col-6"></span><span class="placeholder col-10 mt-2"></span></div></div>';
            inbox.append(row);
        }
    }

    function renderInboxError(message) {
        const wrapper = node('div', 'empty py-5');
        wrapper.append(node('p', 'empty-title', 'Could not load conversations'));
        wrapper.append(node('p', 'empty-subtitle text-secondary', message));
        const retry = node('button', 'btn btn-primary', 'Retry');
        retry.type = 'button';
        retry.addEventListener('click', () => loadInbox(true));
        wrapper.append(retry);
        el('support-inbox').replaceChildren(wrapper);
    }

    function inboxRowSignature(row) {
        return JSON.stringify([
            row.customer?.name,
            row.customer?.avatar,
            row.ticket?.slug,
            row.ticket?.status,
            row.ticket?.order_number,
            row.ticket?.assignee,
            row.preview,
            row.latest_at,
            Number(row.unread_count || 0),
            Boolean(row.callback_required),
        ]);
    }

    function syncInboxRowState(button, row) {
        const isSelected = row.uuid === state.selectedUuid;
        button.setAttribute('aria-current', isSelected ? 'true' : 'false');
        button.classList.toggle('is-unread', Boolean(row.unread_count));
        button.classList.toggle('border-primary', isSelected);
        button.classList.toggle('border-transparent', !isSelected && !row.callback_required);
        button.classList.toggle('border-yellow', !isSelected && Boolean(row.callback_required));
        syncInboxTyping(button, row.uuid);
    }

    function createInboxRow(row) {
        const button = node('button', 'list-group-item list-group-item-action text-start w-100 p-3 border-top-0 border-end-0 border-bottom border-start support-inbox-row');
        button.type = 'button';
        button.dataset.threadUuid = row.uuid;
        button.dataset.signature = inboxRowSignature(row);

        const layout = node('div', 'row g-2 align-items-center flex-nowrap');
        const avatarCol = node('div', 'col-auto');
        const avatar = node('span', 'avatar', initials(row.customer.name));
        if (row.customer.avatar) {
            avatar.style.backgroundImage = `url("${String(row.customer.avatar).replace(/["\\]/g, '')}")`;
            avatar.textContent = '';
        }
        avatarCol.append(avatar);

        const body = node('div', 'col min-w-0 text-body');
        const top = node('div', 'd-flex justify-content-between align-items-center gap-2 mb-1');
        top.append(node('span', 'text-truncate text-body support-inbox-name', row.customer.name));
        const activity = node('div', 'd-flex align-items-center gap-1 text-nowrap');
        if (row.unread_count) activity.append(node('span', 'support-count-badge is-unread', row.unread_count));
        activity.append(node('small', 'text-secondary', formatTime(row.latest_at)));
        top.append(activity);
        const preview = node('div', 'small text-truncate text-secondary support-inbox-preview', row.preview);
        const typing = node('div', 'small text-truncate text-primary fw-medium d-none support-inbox-typing', 'Typing…');
        typing.setAttribute('role', 'status');
        const metadata = node('div', 'd-flex align-items-center gap-2 mt-2 small text-secondary');
        if (row.ticket) {
            const status = node('span', 'text-nowrap', String(row.ticket.status || '').replaceAll('_', ' '));
            status.prepend(node('i', 'ti ti-point-filled me-1'));
            metadata.append(status);
            if (row.ticket.order_number) metadata.append(node('span', 'text-truncate', row.ticket.order_number));
            if (row.ticket.assignee) {
                const assignee = node('span', 'text-truncate ms-auto', row.ticket.assignee);
                assignee.prepend(node('i', 'ti ti-user-check me-1'));
                metadata.append(assignee);
            }
        }
        if (row.callback_required) {
            const callback = node('span', 'text-yellow text-nowrap ms-auto', 'Callback');
            callback.prepend(node('i', 'ti ti-phone-calling me-1'));
            metadata.append(callback);
        }
        body.append(top, preview, typing, metadata);
        layout.append(avatarCol, body);
        button.append(layout);
        button.addEventListener('click', () => selectThread(row.uuid));
        syncInboxRowState(button, row);

        return button;
    }

    function renderInbox({animateChanges = false} = {}) {
        const inbox = el('support-inbox');
        const unreadTotal = state.rows.reduce((total, row) => total + Number(row.unread_count || 0), 0);
        const unreadBadge = el('support-inbox-unread-total');
        unreadBadge.textContent = String(unreadTotal);
        unreadBadge.classList.toggle('d-none', unreadTotal === 0);
        if (!state.rows.length) {
            const empty = node('div', 'empty py-5');
            // Keep the inbox empty state neutral so it fits the admin panel tone.
            const emptyIcon = node('div', 'empty-icon');
            emptyIcon.append(node('i', 'ti ti-inbox'));
            empty.append(emptyIcon);
            empty.append(node('p', 'empty-title', 'No conversations found'));
            empty.append(node('p', 'empty-subtitle text-secondary', 'Try another inbox tab or clear the filters.'));
            inbox.replaceChildren(empty);
            return;
        }

        [...inbox.children].filter(item => !item.dataset.threadUuid).forEach(item => item.remove());
        const existingRows = new Map([...inbox.querySelectorAll('[data-thread-uuid]')].map(item => [item.dataset.threadUuid, item]));
        state.rows.forEach(row => {
            const existing = existingRows.get(row.uuid);
            const changed = !existing || existing.dataset.signature !== inboxRowSignature(row);
            const button = changed ? createInboxRow(row) : existing;
            syncInboxRowState(button, row);
            if (existing && changed) existing.replaceWith(button);
            inbox.append(button);
            existingRows.delete(row.uuid);
            if (changed && animateChanges) {
                button.classList.add('support-inbox-row-updated');
                button.addEventListener('animationend', () => button.classList.remove('support-inbox-row-updated'), {once: true});
            }
        });
        existingRows.forEach(item => item.remove());
    }

    function renderScopeCounts(counts = {}) {
        state.scopeCounts = {...state.scopeCounts, ...counts};
        ['unassigned', 'mine', 'all'].forEach(scope => {
            const badge = el(`support-scope-${scope}-count`);
            if (badge) badge.textContent = String(Number(state.scopeCounts[scope] || 0));
        });
    }

    function renderActiveFilters() {
        const container = el('support-active-filters');
        if (!container) return;
        const topicLabel = el('support-topic-filter')?.selectedOptions?.[0]?.textContent;
        const filters = [
            state.query ? {key: 'query', label: `Search: ${state.query}`} : null,
            state.status ? {key: 'status', label: `Status: ${el('support-status-filter').selectedOptions[0].textContent}`} : null,
            state.topicId ? {key: 'topicId', label: `Topic: ${topicLabel}`} : null,
            state.callback === 'required' ? {key: 'callback', label: 'Callback required'} : null,
            state.rating ? {key: 'rating', label: `Rating: ${state.rating} stars`} : null,
        ].filter(Boolean);
        container.replaceChildren();
        container.classList.toggle('d-none', filters.length === 0);
        container.classList.toggle('d-flex', filters.length > 0);
        filters.forEach(filter => {
            const button = node('button', 'support-filter-chip', filter.label);
            button.type = 'button';
            button.append(node('i', 'ti ti-x ms-1'));
            button.addEventListener('click', () => clearFilter(filter.key));
            container.append(button);
        });
        if (filters.length > 1) {
            const clear = node('button', 'support-filter-chip text-secondary', 'Clear all');
            clear.type = 'button';
            clear.addEventListener('click', clearAllFilters);
            container.append(clear);
        }
    }

    function clearFilter(key) {
        const controls = {
            query: ['support-search', ''],
            status: ['support-status-filter', ''],
            topicId: ['support-topic-filter', ''],
            callback: ['support-callback-filter', 'all'],
            rating: ['support-rating-filter', ''],
        };
        const [controlId, value] = controls[key];
        state[key] = value;
        el(controlId).value = value;
        loadInbox(true);
    }

    function clearAllFilters() {
        state.query = '';
        state.status = '';
        state.topicId = '';
        state.callback = 'all';
        state.rating = '';
        el('support-search').value = '';
        el('support-status-filter').value = '';
        el('support-topic-filter').value = '';
        el('support-callback-filter').value = 'all';
        el('support-rating-filter').value = '';
        loadInbox(true);
    }

    // Helper to format snake_case identifiers into title case
    function humanize(str) {
        if (!str) return '';
        return String(str).replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    }

    // Keep status badges quiet and readable inside dense support panels.
    function statusBadge(status) {
        const labels = {
            open: ['Open', 'bg-blue-lt text-blue'],
            in_progress: ['In progress', 'bg-green-lt text-green'],
            waiting_customer: ['Waiting', 'bg-yellow-lt text-yellow'],
            resolved: ['Resolved', 'bg-secondary-lt text-secondary'],
            closed: ['Closed', 'bg-secondary-lt text-secondary'],
        };
        const [label, className] = labels[status] || [humanize(status) || 'Unknown', 'bg-secondary-lt text-secondary'];
        return node('span', `badge ${className} support-status-badge-sm`, label);
    }

    async function loadInbox(reset = false, {silent = false, animateChanges = false} = {}) {
        if (state.inboxLoading) {
            state.inboxQueued = true;
            return;
        }
        state.inboxLoading = true;
        renderActiveFilters();
        if (reset && !state.inboxLoaded) {
            state.page = 1;
            renderInboxSkeleton();
        } else if (reset) {
            state.page = 1;
            if (!silent) el('support-inbox').classList.add('support-inbox-loading');
        }
        const params = new URLSearchParams({
            scope: state.scope,
            page: String(state.page),
            callback: state.callback,
        });
        if (state.query) params.set('q', state.query);
        if (state.status) params.set('status', state.status);
        if (state.topicId) params.set('topic_id', state.topicId);
        if (state.rating) params.set('rating', state.rating);

        try {
            const payload = await request(`${app.dataset.inboxUrl}?${params}`);
            state.rows = reset ? payload.data : [...state.rows, ...payload.data.filter(row => !state.rows.some(existing => existing.uuid === row.uuid))];
            state.lastPage = payload.meta.last_page;
            renderScopeCounts(payload.meta.scope_counts);
            renderInbox({animateChanges});
            state.inboxLoaded = true;
            el('support-load-more').classList.toggle('d-none', state.page >= state.lastPage);
            if (!state.socketLive) setConnection(pollingEnabled ? 'polling' : 'socket');
            if (reset && app.dataset.initialThread && !state.initialThreadOpened) {
                state.initialThreadOpened = true;
                selectThread(app.dataset.initialThread);
            }
        } catch (error) {
            if (reset && !state.inboxLoaded) renderInboxError(error.message);
            else if (!silent) showToast('error', error.message);
            if (!navigator.onLine) setConnection('offline');
        } finally {
            state.inboxLoading = false;
            el('support-inbox').classList.remove('support-inbox-loading');
            if (state.inboxQueued) {
                state.inboxQueued = false;
                void loadInbox(true, {silent: true, animateChanges: true});
            }
        }
    }

    async function selectThread(uuid) {
        if (state.conversationLoading) return;
        const changedThread = state.selectedUuid !== uuid;
        if (state.selectedUuid && changedThread) resetComposerDraft();
        setRemoteTyping(false);
        setCustomerOnline(false);
        state.selectedUuid = uuid;
        if (changedThread) {
            state.threadSubscribed = false;
            syncSocketState();
        }
        syncUrlState();
        state.conversationLoading = true;
        renderInbox();
        app.classList.add('mobile-conversation');
        el('support-empty-conversation').classList.add('d-none');
        el('support-active-conversation').classList.remove('d-none');
        el('support-active-conversation').classList.add('d-flex');
        el('support-active-conversation').classList.add('support-conversation-loading');
        messageList().replaceChildren(node('div', 'placeholder-glow p-4', 'Loading conversation…'));

        try {
            state.thread = await request(endpoint('showUrl', '__THREAD__', uuid));
            state.messageCursor = latestMessageId();
            state.hasOlderMessages = Boolean(state.thread.message_pagination?.has_more);
            state.oldestMessageId = state.thread.message_pagination?.oldest_message_id || null;
            renderConversation();
            setRemoteTyping(state.typingThreads.has(uuid));
            subscribeThread(uuid);
            await markRead();
        } catch (error) {
            messageList().replaceChildren(node('div', 'alert alert-danger m-3', error.message));
        } finally {
            state.conversationLoading = false;
            requestAnimationFrame(() => el('support-active-conversation').classList.remove('support-conversation-loading'));
        }
    }

    function renderConversation() {
        if (!state.thread) return;
        const customer = state.thread.customer;
        const session = activeSession() || state.thread.sessions.at(-1);
        el('support-customer-name').textContent = customer.name;
        const avatar = el('support-customer-avatar');
        avatar.textContent = initials(customer.name);
        avatar.style.backgroundImage = '';
        if (customer.avatar) {
            avatar.style.backgroundImage = `url("${String(customer.avatar).replace(/["\\]/g, '')}")`;
            avatar.textContent = '';
        }
        el('support-ticket-badge').textContent = session?.slug || 'No session';
        const replacementStatus = statusBadge(session?.status || 'closed');
        replacementStatus.id = 'support-status-badge';
        replacementStatus.classList.add('support-header-status');
        el('support-status-badge').replaceWith(replacementStatus);
        const assigneeLabel = el('support-assignee-toggle-label');
        if (assigneeLabel) assigneeLabel.textContent = session?.assignee?.name || 'Unassigned';
        const assignee = el('support-reassign');
        if (assignee) assignee.value = session?.assignee?.id ? String(session.assignee.id) : '';
        el('support-context-line').textContent = [session?.topic?.title, session?.order?.number].filter(Boolean).join(' · ') || customer.email || '';

        renderMessages();
        renderContext();
        renderQuickReplies(session);
        renderComposer();
        const isActive = Boolean(activeSession());
        ['support-claim', 'support-reassign', 'support-waiting', 'support-progress', 'support-call', 'support-resolve', 'support-close'].forEach(id => {
            const action = el(id);
            if (action) action.disabled = !isActive;
        });
        el('support-waiting')?.classList.toggle('d-none', !isActive || session?.status === 'waiting_customer');
        el('support-progress')?.classList.toggle('d-none', !isActive || session?.status !== 'waiting_customer');
    }

    function renderQuickReplies(session) {
        const select = el('support-canned');
        select.replaceChildren();
        const placeholder = node('option', '', 'Quick reply…');
        placeholder.value = '';
        select.append(placeholder);
        const replies = session?.topic?.quick_replies || [];
        replies.forEach(reply => {
            const option = node('option', '', reply);
            option.value = reply;
            select.append(option);
        });
        if (!replies.length) {
            const empty = node('option', '', 'No quick replies configured');
            empty.disabled = true;
            select.append(empty);
        }
    }

    function renderMessages() {
        const scroller = el('support-messages');
        const container = messageList();
        container.replaceChildren();
        el('support-load-older')?.classList.toggle('d-none', !state.hasOlderMessages);
        [...(state.thread.sessions || [])].sort((left, right) => left.id - right.id).forEach(session => {
            const sessionElement = node('section', 'support-session');
            sessionElement.dataset.sessionId = String(session.id);
            const divider = node('div', 'support-session-divider my-4', `${session.slug} · ${session.topic?.title || 'Support'} · ${formatTime(session.created_at)}`);
            sessionElement.append(divider);
            appendSessionMessages(sessionElement, [...(session.messages || [])].sort((left, right) => left.id - right.id));
            if (['resolved', 'closed'].includes(session.status) && !(session.messages || []).some(message => message.type === 'closure')) {
                const actor = session.resolved_by ? ` by ${session.resolved_by}` : '';
                sessionElement.append(renderTimelineEvent(`This conversation has been closed${actor}`, session.closed_at || session.resolved_at, 'ti-check', true));
            }
            container.append(sessionElement);
        });
        requestAnimationFrame(() => {
            scroller.scrollTop = scroller.scrollHeight;
        });
    }

    async function loadOlderMessages() {
        if (!state.selectedUuid || !state.hasOlderMessages || !state.oldestMessageId || state.historyLoading) return;
        const scroller = el('support-messages');
        const threadUuid = state.selectedUuid;
        const button = el('support-load-older');
        state.historyLoading = true;
        button?.classList.add('btn-loading');
        try {
            const data = await request(`${endpoint('historyUrl', '__THREAD__', state.selectedUuid)}?before_message_id=${state.oldestMessageId}&limit=50`);
            if (threadUuid !== state.selectedUuid) return;
            const top = scroller.getBoundingClientRect().top;
            const anchor = [...scroller.querySelectorAll('[data-message-id]')].find(item => item.getBoundingClientRect().bottom > top);
            const anchorTop = anchor?.getBoundingClientRect().top;
            const previousHeight = scroller.scrollHeight;
            const previousOffset = scroller.scrollTop;
            const olderBySession = new Map();
            (data.messages || []).forEach(message => {
                const session = state.thread?.sessions?.find(item => item.id === message.session_id);
                if (session && !session.messages.some(existing => existing.id === message.id)) {
                    session.messages.push(message);
                    session.messages.sort((left, right) => left.id - right.id);
                    if (!olderBySession.has(session.id)) olderBySession.set(session.id, []);
                    olderBySession.get(session.id).push(message);
                }
            });
            state.hasOlderMessages = Boolean(data.has_more);
            state.oldestMessageId = data.oldest_message_id || state.oldestMessageId;
            olderBySession.forEach((messages, sessionId) => {
                const section = messageList().querySelector(`[data-session-id="${sessionId}"]`);
                if (!section) return;
                const fragment = document.createDocumentFragment();
                appendSessionMessages(fragment, messages.sort((left, right) => left.id - right.id));
                section.insertBefore(fragment, section.firstElementChild?.nextSibling || null);
            });
            el('support-load-older')?.classList.toggle('d-none', !state.hasOlderMessages);
            scroller.scrollTop = anchor?.isConnected
                ? scroller.scrollTop + anchor.getBoundingClientRect().top - anchorTop
                : previousOffset + scroller.scrollHeight - previousHeight;
        } catch (error) {
            if (threadUuid === state.selectedUuid) showToast('error', error.message);
        } finally {
            state.historyLoading = false;
            button?.classList.remove('btn-loading');
        }
    }

    function appendLiveMessages(messages) {
        const scroller = el('support-messages');
        const followLatest = !state.historyLoading && scroller.scrollHeight - scroller.scrollTop - scroller.clientHeight < 180;
        let requiresRender = false;
        messages.forEach(message => {
            const session = messageList().querySelector(`[data-session-id="${message.session_id}"]`);
            if (!session) {
                requiresRender = true;
                return;
            }
            const item = renderMessage(message);
            item.classList.add('support-message-enter');
            session.append(item);
            requestAnimationFrame(() => item.classList.add('is-visible'));
        });
        if (requiresRender) {
            renderMessages();
            return;
        }
        if (followLatest) requestAnimationFrame(() => scroller.scrollTo({top: scroller.scrollHeight, behavior: 'smooth'}));
    }

    function appendSessionMessages(container, messages) {
        let activity = [];
        const flushActivity = () => {
            if (!activity.length) return;
            container.append(activity.length === 1 ? renderMessage(activity[0]) : renderTimelineGroup(activity));
            activity = [];
        };

        messages.forEach(message => {
            if (message.sender_role === 'system' && !['closure', 'auto_reply'].includes(message.type)) {
                activity.push(message);
                return;
            }
            flushActivity();
            container.append(renderMessage(message));
        });
        flushActivity();
    }

    function renderTimelineGroup(messages) {
        const group = node('details', 'support-timeline-group');
        group.dataset.messageId = String(messages[0].id);
        const summary = node('summary', 'text-center');
        summary.append(node('i', 'ti ti-history me-1'));
        summary.append(document.createTextNode(`${messages.length} conversation updates · ${formatTime(messages.at(-1)?.created_at)}`));
        const events = node('div');
        messages.forEach(message => {
            const icon = String(message.text || '').toLowerCase().includes('assign') ? 'ti-user-check' : 'ti-info-circle';
            events.append(renderTimelineEvent(message.text, message.created_at, icon));
        });
        group.append(summary, events);
        return group;
    }

    function renderMessage(message) {
        if (message.sender_role === 'system' && message.type !== 'auto_reply') {
            const isClosure = message.type === 'closure';
            const icon = isClosure
                ? 'ti-check'
                : String(message.text || '').toLowerCase().includes('assign') ? 'ti-user-check' : 'ti-info-circle';
            const event = renderTimelineEvent(message.text, message.created_at, icon, isClosure);
            event.dataset.messageId = String(message.id);
            return event;
        }

        const isAdmin = message.sender_role === 'admin' || message.type === 'auto_reply';
        const bubble = node('div', 'chat-item');
        bubble.dataset.messageId = String(message.id);
        const row = node('div', `row align-items-end py-2 ${isAdmin ? 'justify-content-end' : ''}`);
        const avatarColumn = node('div', 'col-auto');
        const avatar = node('span', 'avatar avatar-1', initials(message.sender_name || (isAdmin ? 'Agent' : state.thread?.customer?.name)));
        const senderAvatar = message.sender_avatar || (!isAdmin ? state.thread?.customer?.avatar : null);
        if (senderAvatar) {
            avatar.style.backgroundImage = `url("${String(senderAvatar).replace(/["\\]/g, '')}")`;
            avatar.textContent = '';
        }
        avatarColumn.append(avatar);
        const bubbleColumn = node('div', 'col col-lg-6 support-bubble-column');
        const content = node('div', 'chat-bubble');
        if (isAdmin) content.classList.add('chat-bubble-me');
        const title = node('div', 'chat-bubble-title');
        const titleRow = node('div', 'row align-items-center g-1');
        titleRow.append(node('div', 'col chat-bubble-author', message.sender_name || (isAdmin ? 'Agent' : 'Customer')));
        titleRow.append(node('div', 'col-auto chat-bubble-date', formatTime(message.created_at)));
        if (app.dataset.canManage === '1') {
            const action = node('div', 'col-auto p-0');
            const remove = node('button', 'btn btn-sm btn-icon btn-ghost-danger support-message-delete');
            remove.type = 'button';
            remove.title = 'Delete message';
            remove.setAttribute('aria-label', 'Delete message');
            remove.setAttribute('data-bs-toggle', 'modal');
            remove.setAttribute('data-bs-target', '#support-action-modal');
            remove.append(node('i', 'ti ti-trash'));
            remove.addEventListener('click', () => showSupportAction('delete-message', message.id));
            action.append(remove);
            titleRow.append(action);
        }
        title.append(titleRow);
        const body = node('div', 'chat-bubble-body');
        const text = node('div', 'text-break');
        text.style.whiteSpace = 'pre-wrap';
        text.textContent = message.text || '';
        body.append(text);
        (message.attachments || []).forEach(attachment => {
            const isImage = supportAttachmentIsImage(attachment);
            const previewUrls = supportAttachmentPreviewUrls(attachment);
            const downloadUrl = supportAttachmentDownloadUrl(attachment);

            if (isImage) {
                const link = node('button', 'support-image-button mt-2');
                link.type = 'button';
                link.setAttribute('aria-label', `Preview ${attachment.name || 'attachment'}`);
                link.setAttribute('data-bs-toggle', 'modal');
                link.setAttribute('data-bs-target', '#support-image-modal');
                const image = node('img', 'support-attachment-thumb');
                image.alt = attachment.name || 'Attachment preview';
                image.loading = 'lazy';
                link.append(image);
                link.addEventListener('click', () => openImageLightbox(attachment));
                body.append(link);

                image.onerror = () => {
                    const fallback = node('a', 'btn btn-sm btn-outline-secondary mt-2', `${attachment.name || 'Image'} · ${formatBytes(attachment.size)}`);
                    fallback.href = downloadUrl;
                    fallback.download = attachment.name || 'image';
                    fallback.append(node('i', 'ti ti-download ms-1'));
                    link.replaceWith(fallback);
                };

                loadSupportImage(image, previewUrls);
            } else {
                const link = node('a', 'btn btn-sm btn-outline-secondary mt-2', `${attachment.name} · ${formatBytes(attachment.size)}`);
                link.href = downloadUrl;
                link.download = attachment.name;
                link.append(node('i', 'ti ti-download ms-1'));
                body.append(link);
            }
        });
        content.append(title, body);
        bubbleColumn.append(content);
        if (isAdmin) row.append(bubbleColumn, avatarColumn);
        else row.append(avatarColumn, bubbleColumn);
        bubble.append(row);
        return bubble;
    }

    function renderTimelineEvent(label, createdAt, icon, success = false, detail = null) {
        const event = node('div', `support-timeline-event${success ? ' is-success' : ''}`);
        const eventIcon = node('span', 'support-timeline-event-icon');
        eventIcon.append(node('i', `ti ${icon}`));
        const content = node('span');
        content.append(node('span', 'fw-medium', label || 'Conversation updated'));
        if (detail) content.append(node('span', 'support-timeline-event-detail', ` · ${detail}`));
        const time = node('time', 'text-secondary', formatTime(createdAt));
        if (createdAt) time.dateTime = createdAt;
        event.append(eventIcon, content, time);
        return event;
    }

    function openImageLightbox(attachment) {
        imageZoom = 1;
        const image = el('support-lightbox-image');
        image.removeAttribute('src');
        image.alt = attachment.name || 'Attachment preview';
        el('support-image-modal-title').textContent = attachment.name || 'Attachment preview';
        const download = el('support-lightbox-download');
        download.href = attachmentDownloadUrl(supportAttachmentDownloadUrl(attachment));
        download.download = attachment.name || 'attachment';
        image.onload = () => setImageZoom(1);
        image.onerror = () => {
            image.alt = 'Attachment preview unavailable';
        };
        loadSupportImage(image, supportAttachmentPreviewUrls(attachment));
        if (image.complete) setImageZoom(1);
    }

    function attachmentDownloadUrl(url) {
        if (!url) return '#';
        const normalizedUrl = supportAttachmentUrl(url);
        const separator = normalizedUrl.includes('?') ? '&' : '?';
        return `${normalizedUrl}${separator}download=1`;
    }

    function supportAttachmentUrl(url) {
        if (!url || url.startsWith('#')) return url || '#';

        try {
            const parsed = new URL(url, window.location.href);

            // Support attachment routes are session-protected; keep them on the current admin origin.
            if (parsed.pathname.includes('/support/attachments/')) {
                if (parsed.pathname.includes('/api/user/support/attachments/')) {
                    if (parsed.searchParams.has('signature')) {
                        // Signed customer/API URLs must keep their original domain because the signature belongs to that URL.
                        return parsed.href;
                    }

                    return `${parsed.pathname.replace('/api/user/support/attachments/', '/admin/support/attachments/')}${parsed.search}${parsed.hash}`;
                }

                return `${parsed.pathname}${parsed.search}${parsed.hash}`;
            }
        } catch (error) {
            return url;
        }

        return url;
    }

    function supportAdminAttachmentUrl(attachment, inline = false, fallbackUrl = null) {
        if (!attachment?.id || !app.dataset.attachmentUrl) {
            return supportAttachmentUrl(fallbackUrl);
        }

        const url = app.dataset.attachmentUrl.replace('__MEDIA__', encodeURIComponent(attachment.id));
        const targetUrl = inline ? `${url}${url.includes('?') ? '&' : '?'}inline=1` : url;

        // Admin chat thumbnails should always use the admin media route, normalized to current origin.
        return supportAttachmentUrl(targetUrl);
    }

    function supportAttachmentPreviewUrls(attachment) {
        const urls = [
            attachment?.preview_url,
            attachment?.url,
            attachment?.download_url,
            supportAdminAttachmentUrl(attachment, true, attachment?.admin_preview_url),
            attachment?.admin_preview_url,
            attachment?.customer_preview_url,
        ];

        // Prefer public storage URLs first; route URLs remain as fallback for older private attachments.
        return [...new Set(urls
            .filter(url => url && !String(url).startsWith('data:'))
            .map(url => supportAttachmentUrl(url)))];
    }

    function supportAttachmentDownloadUrl(attachment) {
        const publicUrl = attachment?.download_url || attachment?.url;

        if (publicUrl && !String(publicUrl).includes('/support/attachments/')) {
            return supportAttachmentUrl(publicUrl);
        }

        return supportAdminAttachmentUrl(attachment, false, attachment?.admin_download_url || publicUrl || attachment?.customer_download_url);
    }

    function supportAttachmentIsImage(attachment) {
        if (attachment?.is_image || attachment?.mime_type?.startsWith('image/')) {
            return true;
        }

        // Customer apps can send image files with a generic MIME type, so fall back to the saved filename.
        return /\.(jpe?g|png|webp)$/i.test(attachment?.name || '');
    }

    function loadSupportImage(image, urls) {
        const candidates = (Array.isArray(urls) ? urls : [urls]).filter(url => url && url !== '#');
        if (!candidates.length) return;

        const fallback = image.onerror;
        let index = 0;

        image.onerror = () => {
            index += 1;
            if (index < candidates.length) {
                image.src = supportAttachmentUrl(candidates[index]);
                return;
            }

            if (typeof fallback === 'function') {
                fallback();
            }
        };

        // Use route-backed image sources so previews open with normal domain URLs.
        image.src = supportAttachmentUrl(candidates[index]);
    }

    let imageZoom = 1;
    function setImageZoom(value) {
        imageZoom = Math.max(1, Math.min(5, value));
        const image = el('support-lightbox-image');
        const viewport = el('support-lightbox-viewport');
        const baseWidth = Math.min(Math.max(100, viewport.clientWidth - 16), image.naturalWidth || viewport.clientWidth);
        image.style.width = imageZoom === 1 ? '' : `${baseWidth * imageZoom}px`;
        image.style.maxWidth = imageZoom === 1 ? '' : 'none';
        image.style.maxHeight = imageZoom === 1 ? '' : 'none';
        image.style.cursor = imageZoom === 1 ? 'zoom-in' : 'zoom-out';
        el('support-zoom-reset').textContent = `${Math.round(imageZoom * 100)}%`;
        el('support-zoom-out').disabled = imageZoom <= 1;
        el('support-zoom-in').disabled = imageZoom >= 5;
        if (imageZoom === 1) { viewport.scrollTop = 0; viewport.scrollLeft = 0; }
    }
    el('support-zoom-in').addEventListener('click', () => setImageZoom(imageZoom + .5));
    el('support-zoom-out').addEventListener('click', () => setImageZoom(imageZoom - .5));
    el('support-zoom-reset').addEventListener('click', () => setImageZoom(1));
    el('support-lightbox-image').addEventListener('dblclick', () => setImageZoom(imageZoom === 1 ? 2 : 1));
    el('support-lightbox-viewport').addEventListener('wheel', event => {
        if (!event.ctrlKey) return;
        event.preventDefault();
        setImageZoom(imageZoom + (event.deltaY < 0 ? .25 : -.25));
    }, {passive: false});

    function renderContext() {
        ['support-context-body', 'support-context-panel-body'].map(el).filter(Boolean).forEach(renderContextBody);
    }

    function renderContextBody(body) {
        body.replaceChildren();
        const customer = state.thread.customer;
        const contact = node('div', 'card mb-3');
        const contactBody = node('div', 'card-body');
        contactBody.append(node('h3', 'card-title', customer.name));
        contactBody.append(node('div', 'text-secondary', customer.email || 'No email'));
        contactBody.append(node('div', 'text-secondary', customer.phone || 'No phone'));
        contact.append(contactBody);
        body.append(contact);

        (state.thread.sessions || []).slice().reverse().forEach(session => {
            const card = node('div', 'card mb-3');
            const header = node('div', 'card-header d-flex align-items-center justify-content-between gap-2 py-2');
            header.append(node('h4', 'card-title mb-0', `${session.slug} · ${session.topic?.title || 'Support'}`));
            header.append(statusBadge(session.status));
            const cardBody = node('div', 'card-body');
            if (session.order) {
                const order = node('dl', 'row mt-2 mb-0');
                [
                    ['Order', session.order.number],
                    ['Order status', session.order.status],
                    ['Payment', session.order.payment_status],
                    ['Total', `${session.order.currency_code} ${session.order.total}`]
                ].forEach(([label, value]) => {
                    order.append(node('dt', 'col-5 text-secondary', label));
                    const dd = node('dd', 'col-7 text-end mb-2');
                    if (label === 'Order status') {
                        const isDone = ['delivered', 'completed'].includes(value);
                        dd.append(node('span', `badge ${isDone ? 'bg-success-lt text-success' : 'bg-warning-lt text-warning'} text-capitalize`, humanize(value)));
                    } else if (label === 'Payment') {
                        const isDone = ['completed', 'paid', 'success'].includes(value);
                        dd.append(node('span', `badge ${isDone ? 'bg-success-lt text-success' : 'bg-secondary-lt text-secondary'} text-capitalize`, humanize(value)));
                    } else {
                        dd.textContent = value || '-';
                    }
                    order.append(dd);
                });
                cardBody.append(order);
                const sections = [
                    ['Items', session.order.items, item => `${item.title} × ${item.quantity} · ${humanize(item.fulfillment_status || item.status)}`],
                    ['Shipments', session.order.shipments, shipment => `${shipment.courier || 'Shipment'} · ${humanize(shipment.status)}${shipment.tracking_number ? ` · ${shipment.tracking_number}` : ''}`],
                    ['Returns', session.order.returns, itemReturn => `${humanize(itemReturn.status)}${itemReturn.refund_amount ? ` · ${session.order.currency_code} ${itemReturn.refund_amount}` : ''}`],
                    ['Refunds', session.order.refunds, refund => `${humanize(refund.status)} · ${refund.currency_code} ${refund.amount}`],
                ];
                sections.forEach(([title, entries, formatter]) => {
                    if (!entries?.length) return;
                    cardBody.append(node('h5', 'mt-3 mb-1 fw-bold small text-uppercase text-secondary', title));
                    const list = node('ul', 'list-unstyled small text-secondary mb-0');
                    entries.forEach(entry => list.append(node('li', 'py-1 border-bottom', formatter(entry))));
                    cardBody.append(list);
                });
            }
            if (session.resolution_summary) cardBody.append(node('p', 'mt-3 mb-0', session.resolution_summary));
            if (session.deeplink) {
                const deeplink = node('div', 'card border-azure mt-3');
                const deeplinkHeader = node('div', 'card-header py-2');
                const deeplinkTitle = node('h5', 'card-title mb-0', 'Deeplink details');
                deeplinkTitle.prepend(node('i', 'ti ti-link me-2 text-azure'));
                deeplinkHeader.append(deeplinkTitle);
                const deeplinkBody = node('div', 'card-body py-3');
                const customerScheme = session.deeplink.customer?.scheme;
                deeplinkBody.append(node('div', 'small text-secondary mb-1', 'Customer app scheme'));
                deeplinkBody.append(node('code', 'd-block text-break mb-3', customerScheme ? `${customerScheme}://` : 'Not configured'));
                [
                    ['Customer Play Store', session.deeplink.customer?.play_store_url],
                    ['Customer App Store', session.deeplink.customer?.app_store_url],
                    ['Seller app scheme', session.deeplink.seller?.scheme ? `${session.deeplink.seller.scheme}://` : null],
                ].forEach(([label, value]) => {
                    if (!value) return;
                    deeplinkBody.append(node('div', 'small text-secondary mb-1', label));
                    deeplinkBody.append(node('code', 'd-block text-break mb-3', value));
                });
                if (session.deeplink.reported_links?.length) {
                    deeplinkBody.append(node('div', 'small text-secondary mb-1', 'Links reported by customer'));
                    session.deeplink.reported_links.forEach(link => deeplinkBody.append(node('code', 'd-block text-break mb-1', link)));
                }
                const settings = node('a', 'btn btn-sm btn-outline-azure mt-3', 'Review app settings');
                settings.href = session.deeplink.settings_url;
                settings.append(node('i', 'ti ti-arrow-up-right ms-1'));
                deeplinkBody.append(settings);
                deeplink.append(deeplinkHeader, deeplinkBody);
                cardBody.append(deeplink);
            }
            if (session.rating) {
                const rating = node('div', 'mt-3');
                rating.append(node('div', 'text-yellow', `${'★'.repeat(session.rating.score)}${'☆'.repeat(5 - session.rating.score)}`));
                if (session.rating.agent) rating.append(node('div', 'small text-secondary', `Assigned to ${session.rating.agent.name}`));
                if (session.rating.feedback) rating.append(node('div', 'small mt-1', session.rating.feedback));
                cardBody.append(rating);
            }
            (session.calls || []).filter(call => call.private_note).forEach(call => cardBody.append(node('div', 'alert alert-info py-2 mt-2', `Private note: ${call.private_note}`)));
            card.append(header, cardBody);
            body.append(card);
        });
    }

    function renderComposer() {
        const session = activeSession();
        const form = el('support-message-form');
        const readOnly = el('support-readonly');
        const isAssignedToCurrentAgent = Number(session?.assignee?.id) === Number(app.dataset.agentId);
        if (session && app.dataset.canReply === '1' && isAssignedToCurrentAgent) {
            form.classList.remove('d-none');
            readOnly.classList.add('d-none');
            syncComposerState();
            return;
        }
        form.classList.add('d-none');
        if (session) {
            readOnly.classList.remove('d-none');
            if (app.dataset.canReply !== '1') {
                readOnly.replaceChildren(node('strong', '', 'Read-only conversation. '));
                readOnly.append(document.createTextNode('You do not have permission to reply.'));
                return;
            }
            readOnly.replaceChildren(node('strong', '', 'Assignment required. '));
            readOnly.append(document.createTextNode(session.assignee
                ? `This conversation is assigned to ${session.assignee.name}. Reassign it to yourself before replying.`
                : 'Assign this conversation to yourself before replying.'));
            if (app.dataset.canAssign === '1') {
                const assign = node('button', 'btn btn-sm btn-primary ms-2', 'Assign to me');
                assign.type = 'button';
                assign.addEventListener('click', () => mutate(
                    endpoint('assignUrl', '__TICKET__', session.id),
                    {agent_id: Number(app.dataset.agentId)},
                    'Conversation assigned to you',
                ));
                readOnly.append(assign);
            }
            return;
        }
        const last = state.thread.sessions.at(-1);
        readOnly.classList.remove('d-none');
        readOnly.replaceChildren(node('strong', '', 'Conversation closed. '));
        readOnly.append(document.createTextNode(last?.resolution_summary || 'This session is read-only.'));
        if (last?.rating) readOnly.append(node('div', 'mt-2 text-yellow', `${'★'.repeat(last.rating.score)}${'☆'.repeat(5 - last.rating.score)}`));
    }

    function canReadConversation() {
        return state.selectedUuid && state.thread?.uuid === state.selectedUuid
            && !document.hidden && document.hasFocus()
            && el('support-active-conversation')?.getClientRects().length > 0;
    }

    async function markRead() {
        if (!canReadConversation()) return;
        const uuid = state.selectedUuid;
        const messageId = latestMessageId();
        await request(endpoint('readUrl', '__THREAD__', uuid), {
            method: 'POST',
            body: JSON.stringify({message_id: messageId}),
        }).then(() => {
            if (uuid !== state.selectedUuid || messageId !== latestMessageId()) return;
            const selected = state.rows.find(row => row.uuid === uuid);
            if (selected?.unread_count) {
                selected.unread_count = 0;
                renderInbox({animateChanges: true});
            }
        }).catch(() => null);
    }

    function mergeMessages(messages) {
        const added = [];
        messages.forEach(message => {
            const session = state.thread?.sessions?.find(item => item.id === message.session_id);
            if (session && !session.messages.some(existing => existing.id === message.id)) {
                session.messages.push(message);
                session.messages.sort((left, right) => left.id - right.id);
                added.push(message);
            }
            state.messageCursor = Math.max(state.messageCursor, message.id);
        });
        if (added.length) {
            appendLiveMessages(added);
            const latest = added.filter(message => message.type !== 'auto_reply').sort((left, right) => left.id - right.id).at(-1);
            if (added.some(message => message.sender_role === 'user')) setThreadTyping(state.selectedUuid, false);
            const selected = state.rows.find(row => row.uuid === state.selectedUuid);
            if (selected && latest) {
                selected.preview = latest.text || selected.preview;
                selected.latest_at = latest.created_at;
                state.rows.sort((left, right) => new Date(right.latest_at || 0) - new Date(left.latest_at || 0));
                renderInbox({animateChanges: true});
            }
        }
        markRead();
    }

    async function poll() {
        if (!pollingEnabled || state.polling || state.socketLive || state.conversationLoading || document.hidden || !navigator.onLine) return;
        state.polling = true;
        try {
            await loadInbox(true, {silent: true, animateChanges: true});
            if (state.selectedUuid) {
                const data = await request(`${endpoint('updatesUrl', '__THREAD__', state.selectedUuid)}?after_message_id=${state.messageCursor}`);
                mergeMessages(data.messages || []);
                if (state.thread && data.active_ticket_id !== state.thread.active_ticket_id) await selectThread(state.selectedUuid);
            }
            setConnection('polling');
        } catch (error) {
            setConnection('offline');
        } finally {
            state.polling = false;
        }
    }

    function connectRealtime() {
        if (!window.Echo || !window.Pusher || !realtime.key || !['reverb', 'pusher'].includes(realtime.driver)) {
            state.inboxSubscribed = false;
            state.threadSubscribed = false;
            syncSocketState();
            recordRealtimeFailure(true);
            return;
        }
        window.Pusher = window.Pusher;
        const authorizationHeaders = {'X-CSRF-TOKEN': csrf, Accept: 'application/json'};
        const options = {
            broadcaster: realtime.driver,
            key: realtime.key,
            cluster: realtime.cluster || 'mt1',
            forceTLS: realtime.scheme === 'https',
            wsHost: realtime.host || undefined,
            wsPort: Number(realtime.port || 80),
            wssPort: Number(realtime.port || 443),
            enabledTransports: ['ws', 'wss'],
            authEndpoint: realtime.auth_endpoint,
            auth: {headers: authorizationHeaders},
            channelAuthorization: {
                endpoint: realtime.auth_endpoint,
                transport: 'ajax',
                headers: authorizationHeaders,
            },
        };
        const EchoClient = window.Echo.default || window.Echo;
        try {
            state.echo = new EchoClient(options);
        } catch (_) {
            recordRealtimeFailure(true);
            poll();
            return;
        }
        scheduleRealtimeHealthCheck();
        const inbox = state.echo.private('support.admin.inbox');
        inbox.subscribed(() => {
            state.inboxSubscribed = true;
            syncSocketState();
            loadInbox(true, {silent: true, animateChanges: true});
        });
        inbox.error(() => {
            clearThreadTyping();
            state.inboxSubscribed = false;
            syncSocketState();
            recordRealtimeFailure();
            poll();
        });
        inbox.listen('.support.activity', event => {
            if (event.message?.sender_role === 'user' || ['session.resolved', 'session.closed'].includes(event.activity)) {
                setThreadTyping(event.thread_uuid, false);
            }
            loadInbox(true, {silent: true, animateChanges: true});
        });
        inbox.listen('.support.typing', event => {
            if (event.role === 'customer') setThreadTyping(event.thread_uuid, Boolean(event.typing));
        });
        state.echo.connector.pusher.connection.bind('disconnected', () => {
            clearThreadTyping();
            state.inboxSubscribed = false;
            state.threadSubscribed = false;
            syncSocketState();
            recordRealtimeFailure();
            poll();
        });
        state.echo.connector.pusher.connection.bind('unavailable', () => {
            clearThreadTyping();
            state.inboxSubscribed = false;
            state.threadSubscribed = false;
            syncSocketState();
            recordRealtimeFailure();
            poll();
        });
        state.echo.connector.pusher.connection.bind('failed', () => {
            clearThreadTyping();
            state.inboxSubscribed = false;
            state.threadSubscribed = false;
            syncSocketState();
            recordRealtimeFailure(true);
            poll();
        });
    }

    function subscribeThread(uuid) {
        state.threadSubscribed = false;
        syncSocketState();
        scheduleRealtimeHealthCheck();
        if (!state.echo) return;
        if (state.threadChannelUuid) {
            state.echo.leave(`support.thread.${state.threadChannelUuid}`);
            state.echo.leave(`support.presence.thread.${state.threadChannelUuid}`);
        }
        state.threadChannelUuid = uuid;
        state.privateThreadChannel = state.echo.private(`support.thread.${uuid}`)
            .subscribed(() => {
                state.threadSubscribed = true;
                syncSocketState();
                catchUp();
            })
            .error(() => {
                state.threadSubscribed = false;
                syncSocketState();
                recordRealtimeFailure();
                poll();
            })
            .listen('.support.activity', event => event.activity === 'session.created' || !event.message_id
                ? selectThread(state.selectedUuid)
                : catchUp())
            .listen('.support.typing', event => {
                if (event.role === 'customer') setThreadTyping(uuid, Boolean(event.typing));
            })
            .listenForWhisper('typing', event => {
                if (event.role === 'customer') setThreadTyping(uuid, Boolean(event.typing));
            });
        state.presenceThreadChannel = state.echo.join(`support.presence.thread.${uuid}`)
            .here(members => setCustomerOnline(members.some(member => member.role === 'customer')))
            .joining(member => {
                if (member.role === 'customer') setCustomerOnline(true);
            })
            .leaving(member => {
                if (member.role === 'customer') {
                    setCustomerOnline(false);
                    setThreadTyping(uuid, false);
                }
            });
    }

    async function catchUp() {
        if (!state.selectedUuid) return;
        try {
            const data = await request(`${endpoint('updatesUrl', '__THREAD__', state.selectedUuid)}?after_message_id=${state.messageCursor}`);
            mergeMessages(data.messages || []);
            if (state.thread && data.active_ticket_id !== state.thread.active_ticket_id) await selectThread(state.selectedUuid);
        } catch (_) {
            if (!state.socketLive) setConnection('offline');
        }
    }

    async function mutate(url, body, successMessage) {
        try {
            await request(url, {method: 'POST', body: JSON.stringify(body)});
            if (successMessage) showToast('success', successMessage);
            await selectThread(state.selectedUuid);
            await loadInbox(true, {silent: true, animateChanges: true});
            return true;
        } catch (error) {
            showToast('error', error.message);
            return false;
        }
    }

    function currentTicket() {
        return activeSession();
    }

    function closeAssigneeMenu() {
        const toggle = el('support-assignee-toggle');
        const Dropdown = window.bootstrap?.Dropdown;
        if (toggle && Dropdown) Dropdown.getOrCreateInstance(toggle).hide();
    }

    function hideModal(modalElement) {
        const Modal = window.bootstrap?.Modal;
        if (Modal) {
            Modal.getOrCreateInstance(modalElement).hide();
            return;
        }
        if (window.jQuery?.fn?.modal) window.jQuery(modalElement).modal('hide');
    }

    async function copyTicketNumber() {
        const ticketNumber = el('support-ticket-badge').textContent.trim();
        if (!ticketNumber || ticketNumber === 'No session') return;

        try {
            if (navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(ticketNumber);
            } else {
                const input = node('textarea');
                input.value = ticketNumber;
                input.setAttribute('readonly', '');
                input.style.position = 'fixed';
                input.style.opacity = '0';
                document.body.append(input);
                input.select();
                const copied = document.execCommand('copy');
                input.remove();
                if (!copied) throw new Error('Copy failed.');
            }
            showToast('success', 'Support ticket copied');
        } catch (_) {
            showToast('error', 'Could not copy the support ticket');
        }
    }

    function showSupportAction(type, messageId = null) {
        const ticket = currentTicket() || state.thread?.sessions?.at(-1);
        const modalElement = el('support-action-modal');
        if (!ticket || !modalElement) return;
        const variants = {
            resolve: {
                color: 'success',
                icon: 'ti-circle-check',
                title: 'Resolve conversation',
                description: 'Finish this session and invite the customer to rate the support they received.',
                label: 'Resolve conversation',
                summaryLabel: 'Resolution summary',
                placeholder: 'What was done for the customer?',
            },
            close: {
                color: 'danger',
                icon: 'ti-lock',
                title: 'Close ticket',
                description: 'The customer will no longer be able to send messages in this session.',
                label: 'Close ticket',
                summaryLabel: 'Closure summary',
                placeholder: 'Why is this ticket being closed?',
            },
            call: {
                color: 'primary',
                icon: 'ti-phone',
                title: 'Log customer call',
                description: 'Record the call result and any private follow-up note for the support team.',
                label: 'Save call record',
            },
            'delete-message': {
                color: 'danger',
                icon: 'ti-trash',
                title: 'Delete message',
                description: 'Remove this message and its attachments from the conversation. This cannot be undone from the admin panel.',
                label: 'Delete message',
            },
            clear: {
                color: 'danger',
                icon: 'ti-trash-x',
                title: 'Clear chat history',
                description: 'Remove every message and attachment from this customer conversation while keeping the tickets and audit records.',
                label: 'Clear chat history',
            },
        };
        const variant = variants[type];
        if (!variant) return;
        el('support-action-type').value = type;
        el('support-action-message-id').value = messageId || '';
        el('support-action-status').className = `modal-status bg-${variant.color}`;
        el('support-action-icon').className = `avatar avatar-lg bg-${variant.color}-lt text-${variant.color}`;
        el('support-action-icon').replaceChildren(node('i', `ti ${variant.icon} fs-1`));
        el('support-action-title').textContent = variant.title;
        el('support-action-description').textContent = variant.description;
        el('support-action-submit').className = `btn btn-${variant.color}`;
        el('support-action-submit-icon').className = `ti ${variant.icon} me-1`;
        el('support-action-submit-label').textContent = variant.label;
        el('support-action-summary-group').classList.toggle('d-none', ['call', 'delete-message', 'clear'].includes(type));
        el('support-action-call-fields').classList.toggle('d-none', type !== 'call');
        el('support-action-summary-label').textContent = variant.summaryLabel || 'Summary';
        el('support-action-summary').placeholder = variant.placeholder || '';
        el('support-action-summary').value = '';
        el('support-call-status').value = 'attempted';
        el('support-call-phone').value = state.thread?.customer?.phone || '';
        el('support-call-outcome').value = '';
        el('support-call-note').value = '';
    }

    async function submitSupportAction(event) {
        event.preventDefault();
        const ticket = currentTicket() || state.thread?.sessions?.at(-1);
        const type = el('support-action-type').value;
        if (!ticket || !['resolve', 'close', 'call', 'delete-message', 'clear'].includes(type)) return;
        const actions = {
            resolve: {
                url: endpoint('resolveUrl', '__TICKET__', ticket.id),
                body: {summary: el('support-action-summary').value.trim() || null},
                message: 'Conversation resolved',
            },
            close: {
                url: endpoint('closeUrl', '__TICKET__', ticket.id),
                body: {summary: el('support-action-summary').value.trim() || null},
                message: 'Conversation closed',
            },
            call: {
                url: endpoint('callbackUrl', '__TICKET__', ticket.id),
                body: {
                    phone: el('support-call-phone').value.trim() || null,
                    status: el('support-call-status').value,
                    outcome: el('support-call-outcome').value.trim() || null,
                    private_note: el('support-call-note').value.trim() || null,
                },
                message: 'Call logged',
            },
            'delete-message': {
                url: endpoint('deleteMessageUrl', '__MESSAGE__', el('support-action-message-id').value),
                method: 'DELETE',
                body: null,
                message: 'Message deleted',
            },
            clear: {
                url: endpoint('clearUrl', '__THREAD__', state.selectedUuid),
                method: 'DELETE',
                body: null,
                message: 'Chat history cleared',
            },
        };
        const action = actions[type];
        const submit = el('support-action-submit');
        submit.disabled = true;
        submit.classList.add('btn-loading');
        let succeeded = false;
        if (action.method === 'DELETE') {
            try {
                await request(action.url, {method: 'DELETE'});
                showToast('success', action.message);
                await selectThread(state.selectedUuid);
                await loadInbox(true, {silent: true, animateChanges: true});
                succeeded = true;
            } catch (error) {
                showToast('error', error.message);
            }
        } else {
            succeeded = await mutate(action.url, action.body, action.message);
        }
        submit.disabled = false;
        submit.classList.remove('btn-loading');
        if (succeeded) hideModal(el('support-action-modal'));
    }

    async function loadSupportTopics(selectedId = null) {
        const list = el('support-topic-list');
        if (!list) return;
        list.replaceChildren(node('div', 'p-3 text-secondary', 'Loading topics…'));
        try {
            state.topics = await request(app.dataset.topicsUrl);
            renderSupportTopicList();
            refreshTopicFilter();
            const selected = state.topics.find(topic => topic.id === (selectedId ?? state.selectedTopicId)) || state.topics[0];
            if (selected) selectSupportTopic(selected.id);
            else newSupportTopic();
        } catch (error) {
            const empty = node('div', 'empty py-4');
            empty.append(node('p', 'empty-title', 'Could not load topics'));
            empty.append(node('p', 'empty-subtitle text-secondary', error.message));
            const retry = node('button', 'btn btn-primary btn-sm', 'Retry');
            retry.type = 'button';
            retry.addEventListener('click', () => loadSupportTopics());
            empty.append(retry);
            list.replaceChildren(empty);
        }
    }

    function renderSupportTopicList() {
        const list = el('support-topic-list');
        list.replaceChildren();
        state.topics.forEach(topic => {
            const button = node('button', 'list-group-item list-group-item-action text-start p-3');
            button.type = 'button';
            button.classList.toggle('active', topic.id === state.selectedTopicId);
            const row = node('div', 'd-flex justify-content-between gap-2');
            row.append(node('span', 'text-truncate', topic.title));
            row.append(node('span', `badge ${topic.is_active ? 'bg-green-lt' : 'bg-secondary-lt'}`, topic.is_active ? 'Active' : 'Hidden'));
            button.append(row);
            button.addEventListener('click', () => selectSupportTopic(topic.id));
            list.append(button);
        });
    }

    function initSupportTopicTagInputs() {
        if (typeof window.TomSelect === 'undefined') return;
        document.querySelectorAll('select.support-topic-tags').forEach(input => {
            if (input.tomselect) return;
            const maxItems = Number(input.dataset.maxItems) || 12;
            const maxLength = Number(input.dataset.maxItemLength) || 191;
            new window.TomSelect(input, {
                create(inputValue, callback) {
                    const value = inputValue.trim();
                    if (!value) return callback();
                    if (value.length > maxLength) {
                        showToast('warning', `Each option can contain up to ${maxLength} characters`);
                        return callback();
                    }
                    callback({value, text: value});
                },
                createOnBlur: true,
                persist: false,
                maxItems,
                maxOptions: maxItems,
                delimiter: '\u001f',
                splitOn: /\r?\n/,
                closeAfterSelect: false,
                copyClassesToDropdown: false,
                dropdownParent: 'body',
                placeholder: input.dataset.placeholder || 'Type and press Enter',
            });
        });
    }

    function setSupportTopicTagValues(id, values) {
        initSupportTopicTagInputs();
        const input = el(id);
        const items = [...new Set((values || []).map(value => String(value).trim()).filter(Boolean))];
        if (!input?.tomselect) {
            input?.replaceChildren(...items.map(value => {
                const option = node('option', '', value);
                option.value = value;
                option.selected = true;
                return option;
            }));
            return;
        }
        const select = input.tomselect;
        select.clear(true);
        select.clearOptions();
        items.forEach(value => select.addOption({value, text: value}));
        select.setValue(items, true);
    }

    function supportTopicTagValues(id) {
        const input = el(id);
        const select = input?.tomselect;
        if (select?.control_input?.value?.trim()) select.createItem();
        const value = select
            ? select.getValue()
            : [...(input?.selectedOptions || [])].map(option => option.value);
        return [...new Set((Array.isArray(value) ? value : [value])
            .map(item => String(item).trim())
            .filter(Boolean))];
    }

    function selectSupportTopic(topicId) {
        const topic = state.topics.find(item => item.id === topicId);
        if (!topic) return;
        state.selectedTopicId = topic.id;
        el('support-topic-id').value = topic.id;
        el('support-topic-title').value = topic.title || '';
        el('support-topic-context').value = topic.context || 'both';
        setSupportTopicTagValues('support-topic-guidance', (topic.guidance || '').split(/\r?\n/));
        setSupportTopicTagValues('support-topic-customer-quick-replies', topic.customer_quick_replies || []);
        setSupportTopicTagValues('support-topic-agent-quick-replies', topic.agent_quick_replies || []);
        el('support-topic-sort').value = topic.sort_order ?? 0;
        el('support-topic-active').checked = Boolean(topic.is_active);
        renderSupportTopicList();
    }

    function newSupportTopic() {
        state.selectedTopicId = null;
        el('support-topic-form')?.reset();
        if (!el('support-topic-id')) return;
        el('support-topic-id').value = '';
        el('support-topic-context').value = 'both';
        setSupportTopicTagValues('support-topic-guidance', []);
        setSupportTopicTagValues('support-topic-customer-quick-replies', []);
        setSupportTopicTagValues('support-topic-agent-quick-replies', []);
        el('support-topic-sort').value = state.topics.length
            ? Math.max(...state.topics.map(topic => Number(topic.sort_order) || 0)) + 10
            : 0;
        el('support-topic-active').checked = true;
        renderSupportTopicList();
        el('support-topic-title').focus();
    }

    function refreshTopicFilter() {
        const filter = el('support-topic-filter');
        const currentValue = filter.value;
        filter.replaceChildren();
        const anyTopic = node('option', '', 'Any topic');
        anyTopic.value = '';
        filter.append(anyTopic);
        state.topics.filter(topic => topic.is_active).forEach(topic => {
            const option = node('option', '', topic.title);
            option.value = topic.id;
            filter.append(option);
        });
        filter.value = currentValue;
    }

    async function saveSupportTopic(event) {
        event.preventDefault();
        const guidanceItems = supportTopicTagValues('support-topic-guidance');
        const customerQuickReplies = supportTopicTagValues('support-topic-customer-quick-replies');
        const agentQuickReplies = supportTopicTagValues('support-topic-agent-quick-replies');
        const guidance = guidanceItems.join('\n');
        if (customerQuickReplies.length > 12 || agentQuickReplies.length > 12) {
            showToast('warning', 'Use no more than 12 quick replies for each audience');
            return;
        }
        if (guidance.length > 2000) {
            showToast('warning', 'Pre-chat guidance can contain up to 2,000 characters');
            return;
        }

        const topicId = Number(el('support-topic-id').value) || null;
        const payload = {
            title: el('support-topic-title').value.trim(),
            context: el('support-topic-context').value,
            guidance: guidance || null,
            customer_quick_replies: customerQuickReplies,
            agent_quick_replies: agentQuickReplies,
            sort_order: Number(el('support-topic-sort').value) || 0,
            is_active: el('support-topic-active').checked,
        };
        const save = el('support-topic-save');
        save.disabled = true;
        save.classList.add('btn-loading');
        try {
            const topic = await request(topicId
                ? endpoint('topicUpdateUrl', '__TOPIC__', topicId)
                : app.dataset.topicsUrl, {
                method: topicId ? 'PUT' : 'POST',
                body: JSON.stringify(payload),
            });
            showToast('success', topicId ? 'Support topic updated' : 'Support topic created');
            await loadSupportTopics(topic.id);
            if (state.selectedUuid) await selectThread(state.selectedUuid);
            await loadInbox(true);
        } catch (error) {
            showToast('error', error.message);
        } finally {
            save.disabled = false;
            save.classList.remove('btn-loading');
        }
    }

    function createSupportMessageId() {
        if (typeof window.crypto?.randomUUID === 'function') return window.crypto.randomUUID();
        if (typeof window.crypto?.getRandomValues !== 'function') {
            throw new Error('Your browser cannot generate a message ID. Use a current browser or HTTPS.');
        }
        const bytes = window.crypto.getRandomValues(new Uint8Array(16));
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;
        const hex = Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
        return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
    }

    el('support-message-form').addEventListener('submit', async event => {
        event.preventDefault();
        const ticket = currentTicket();
        const textarea = el('support-message');
        if (state.sending || !ticket || (!textarea.value.trim() && !state.attachments.length)) return;
        const send = el('support-send');
        state.sending = true;
        send.disabled = true;
        send.classList.add('btn-loading');
        try {
            const formData = new FormData();
            formData.append('message', textarea.value.trim());
            state.clientMessageId ||= createSupportMessageId();
            formData.append('client_message_id', state.clientMessageId);
            state.attachments.forEach(file => formData.append('attachments[]', file));
            const message = await request(endpoint('messageUrl', '__TICKET__', ticket.id), {method: 'POST', body: formData});
            mergeMessages([message]);
            whisperTyping(false);
            textarea.value = '';
            textarea.style.height = 'auto';
            state.attachments.forEach(revokeAttachmentPreview);
            state.attachments = [];
            state.clientMessageId = null;
            renderAttachmentPreview();
        } catch (error) {
            if (window.Swal) window.Swal.fire({icon: 'error', title: 'Message not sent', text: error.message, confirmButtonText: 'Retry'});
        } finally {
            state.sending = false;
            send.classList.remove('btn-loading');
            syncComposerState();
            textarea.focus();
        }
    });

    function renderAttachmentPreview() {
        const preview = el('support-attachment-preview');
        preview.replaceChildren();
        state.attachments.forEach((file, index) => {
            const item = node('div', 'card support-attachment-preview-item');
            const body = node('div', 'card-body p-2 d-flex align-items-center gap-2');
            if (file.type.startsWith('image/')) {
                const image = node('img', 'support-attachment-preview-image');
                image.src = attachmentPreviewUrl(file);
                image.alt = '';
                body.append(image);
            } else {
                const icon = node('span', 'support-attachment-preview-icon bg-red-lt text-red');
                icon.append(node('i', 'ti ti-file-type-pdf'));
                body.append(icon);
            }
            const details = node('div', 'flex-fill min-w-0');
            details.append(node('div', 'small fw-medium text-truncate', file.name));
            details.append(node('div', 'small text-secondary', formatBytes(file.size)));
            const remove = node('button', 'btn btn-sm btn-icon btn-ghost-secondary');
            remove.type = 'button';
            remove.setAttribute('aria-label', `Remove ${file.name}`);
            remove.append(node('i', 'ti ti-x'));
            remove.addEventListener('click', () => {
                const [removed] = state.attachments.splice(index, 1);
                revokeAttachmentPreview(removed);
                state.clientMessageId = null;
                renderAttachmentPreview();
            });
            body.append(details, remove);
            item.append(body);
            preview.append(item);
        });
        syncComposerState();
    }

    function attachmentPreviewUrl(file) {
        if (!attachmentPreviewUrls.has(file)) attachmentPreviewUrls.set(file, URL.createObjectURL(file));
        return attachmentPreviewUrls.get(file);
    }

    function revokeAttachmentPreview(file) {
        const url = attachmentPreviewUrls.get(file);
        if (url) URL.revokeObjectURL(url);
        attachmentPreviewUrls.delete(file);
    }

    function resizeMessageInput() {
        const textarea = el('support-message');
        textarea.style.height = 'auto';
        textarea.style.height = `${Math.min(textarea.scrollHeight, 144)}px`;
    }

    function syncComposerState() {
        const hasMessage = Boolean(el('support-message').value.trim());
        el('support-send').disabled = state.sending || !currentTicket() || (!hasMessage && !state.attachments.length);
    }

    function resetComposerDraft() {
        whisperTyping(false);
        state.attachments.forEach(revokeAttachmentPreview);
        state.attachments = [];
        state.clientMessageId = null;
        el('support-message').value = '';
        el('support-message').style.height = 'auto';
        renderAttachmentPreview();
    }

    function formatBytes(bytes) {
        if (!bytes) return '0 B';
        const units = ['B', 'KB', 'MB'];
        const unit = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), 2);
        return `${(bytes / 1024 ** unit).toFixed(unit ? 1 : 0)} ${units[unit]}`;
    }

    function addSupportAttachments(files) {
        const ticket = currentTicket();
        if (state.sending || !ticket || app.dataset.canReply !== '1' || Number(ticket.assignee?.id) !== Number(app.dataset.agentId)) return;
        const allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        const invalid = files.find(file => !allowed.includes(file.type) || file.size > 10 * 1024 * 1024);
        if (invalid || state.attachments.length + files.length > 5) {
            window.Swal?.fire({icon: 'warning', title: 'Attachment rejected', text: 'Choose up to five JPEG, PNG, WebP or PDF files, maximum 10 MB each.'});
        } else {
            state.attachments.push(...files);
            state.clientMessageId = null;
            renderAttachmentPreview();
        }
        syncComposerState();
    }
    el('support-attachments').addEventListener('change', event => {
        addSupportAttachments([...event.target.files]);
        event.target.value = '';
    });

    const dropPanel = el('support-conversation-panel');
    let dropDepth = 0;
    function canDropSupportAttachments() {
        const ticket = currentTicket();
        return !state.sending && !state.conversationLoading && ticket && app.dataset.canReply === '1'
            && Number(ticket.assignee?.id) === Number(app.dataset.agentId)
            && !el('support-message-form').classList.contains('d-none');
    }
    function resetSupportDrop() {
        dropDepth = 0;
        el('support-drop-overlay').classList.add('d-none');
    }
    dropPanel.addEventListener('dragenter', event => {
        if (!event.dataTransfer?.types.includes('Files')) return;
        event.preventDefault();
        dropDepth++;
        if (canDropSupportAttachments()) {
            el('support-drop-overlay').classList.remove('d-none');
        }
    });
    dropPanel.addEventListener('dragover', event => {
        if (!event.dataTransfer?.types.includes('Files')) return;
        event.preventDefault();
        const enabled = canDropSupportAttachments();
        event.dataTransfer.dropEffect = enabled ? 'copy' : 'none';
        if (!enabled) resetSupportDrop();
    });
    dropPanel.addEventListener('dragleave', () => { if (--dropDepth <= 0) resetSupportDrop(); });
    dropPanel.addEventListener('drop', event => {
        if (!event.dataTransfer?.types.includes('Files')) return;
        event.preventDefault();
        resetSupportDrop();
        if (canDropSupportAttachments()) addSupportAttachments([...event.dataTransfer.files]);
    });
    window.addEventListener('dragend', resetSupportDrop);
    window.addEventListener('blur', resetSupportDrop);

    el('support-message').addEventListener('input', () => {
        state.clientMessageId = null;
        resizeMessageInput();
        syncComposerState();
        whisperTyping(Boolean(el('support-message').value.trim()));
    });
    el('support-message').addEventListener('keydown', event => {
        if (event.key !== 'Enter' || event.shiftKey || event.isComposing) return;
        event.preventDefault();
        if (!el('support-send').disabled) el('support-message-form').requestSubmit();
    });
    el('support-canned').addEventListener('change', event => {
        if (event.target.value) el('support-message').value = event.target.value;
        event.target.value = '';
        state.clientMessageId = null;
        resizeMessageInput();
        syncComposerState();
        el('support-message').focus();
    });
    el('support-copy-ticket')?.addEventListener('click', copyTicketNumber);
    const availabilityForm = el('support-availability-form');
    availabilityForm?.addEventListener('schedule:change', event => {
        el('support-away-message-fields').classList.toggle('d-none', !event.detail.enabled);
        el('support-away-message').disabled = !event.detail.enabled;
        el('support-away-message').required = event.detail.enabled;
    });
    el('support-topics-open')?.addEventListener('click', () => loadSupportTopics());
    el('support-quick-replies-manage')?.addEventListener('click', () => loadSupportTopics(activeSession()?.topic?.id));
    el('support-context-toggle')?.addEventListener('click', event => {
        const collapsed = app.classList.toggle('context-collapsed');
        event.currentTarget.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        event.currentTarget.setAttribute('aria-label', collapsed ? 'Show customer details' : 'Hide customer details');
        event.currentTarget.title = collapsed ? 'Show customer details' : 'Hide customer details';
        event.currentTarget.querySelector('i').className = `ti ${collapsed ? 'ti-layout-sidebar-right-expand' : 'ti-layout-sidebar-right-collapse'}`;
    });
    el('support-fullscreen-toggle')?.addEventListener('click', () => setFullscreen(!app.classList.contains('support-fullscreen')));
    el('support-topic-new')?.addEventListener('click', newSupportTopic);
    el('support-topic-reset')?.addEventListener('click', () => {
        if (state.selectedTopicId) selectSupportTopic(state.selectedTopicId);
        else newSupportTopic();
    });
    el('support-topic-form')?.addEventListener('submit', saveSupportTopic);
    el('support-topics-modal')?.addEventListener('shown.bs.modal', initSupportTopicTagInputs);
    el('support-mobile-back').addEventListener('click', () => app.classList.remove('mobile-conversation'));
    el('support-load-more').addEventListener('click', () => {
        if (state.page < state.lastPage) {
            state.page += 1;
            loadInbox(false);
        }
    });

    let searchTimer;
    el('support-search').addEventListener('input', event => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            state.query = event.target.value.trim();
            loadInbox(true);
        }, 300);
    });
    el('support-scopes').addEventListener('click', event => {
        const button = event.target.closest('[data-scope]');
        if (!button) return;
        el('support-scopes').querySelectorAll('.nav-link').forEach(item => {
            const active = item === button;
            item.classList.toggle('active', active);
            item.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        state.scope = button.dataset.scope;
        syncUrlState();
        loadInbox(true);
    });
    [['support-status-filter', 'status'], ['support-topic-filter', 'topicId'], ['support-callback-filter', 'callback'], ['support-rating-filter', 'rating']].forEach(([id, key]) => {
        el(id).addEventListener('change', event => {
            state[key] = event.target.value;
            loadInbox(true);
        });
    });

    el('support-claim')?.addEventListener('click', () => {
        const ticket = currentTicket();
        if (ticket) {
            closeAssigneeMenu();
            mutate(endpoint('assignUrl', '__TICKET__', ticket.id), {agent_id: Number(app.dataset.agentId)}, 'Conversation assigned to you');
        }
    });
    el('support-reassign')?.addEventListener('change', event => {
        const ticket = currentTicket();
        if (ticket) {
            closeAssigneeMenu();
            mutate(endpoint('assignUrl', '__TICKET__', ticket.id), {
                agent_id: event.target.value ? Number(event.target.value) : null,
            }, event.target.value ? 'Conversation assigned' : 'Conversation unassigned');
        }
    });
    el('support-waiting')?.addEventListener('click', () => {
        const ticket = currentTicket();
        if (ticket) mutate(endpoint('statusUrl', '__TICKET__', ticket.id), {status: 'waiting_customer'}, 'Marked waiting for customer');
    });
    el('support-progress')?.addEventListener('click', () => {
        const ticket = currentTicket();
        if (ticket) mutate(endpoint('statusUrl', '__TICKET__', ticket.id), {status: 'in_progress'}, 'Conversation marked in progress');
    });
    el('support-resolve')?.addEventListener('click', () => showSupportAction('resolve'));
    el('support-close')?.addEventListener('click', () => showSupportAction('close'));
    el('support-clear')?.addEventListener('click', () => showSupportAction('clear'));
    el('support-call')?.addEventListener('click', () => showSupportAction('call'));
    el('support-action-form')?.addEventListener('submit', submitSupportAction);

    el('support-image-modal')?.addEventListener('hidden.bs.modal', () => {
        el('support-lightbox-image').removeAttribute('src');
    });
    el('support-load-older')?.addEventListener('click', loadOlderMessages);
    el('support-messages')?.addEventListener('scroll', event => {
        if (event.currentTarget.scrollTop <= 64) loadOlderMessages();
    }, {passive: true});

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) state.socketLive ? catchUp() : pollingEnabled && poll();
        else whisperTyping(false);
    });
    window.addEventListener('focus', () => {
        if (state.socketLive) catchUp();
        else if (pollingEnabled) poll();
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && app.classList.contains('support-fullscreen')) setFullscreen(false);
    });
    window.addEventListener('online', () => {
        if (state.socketLive) catchUp();
        else {
            scheduleRealtimeHealthCheck();
            if (pollingEnabled) poll();
        }
    });
    window.addEventListener('offline', () => setConnection('offline'));
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSupportTopicTagInputs, {once: true});
    } else {
        initSupportTopicTagInputs();
    }
    if (pollingEnabled) setInterval(poll, pollInterval);
    el('support-scopes').querySelectorAll('.nav-link').forEach(item => {
        const active = item.dataset.scope === state.scope;
        item.classList.toggle('active', active);
        item.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    loadInbox(true);
    connectRealtime();
})();
