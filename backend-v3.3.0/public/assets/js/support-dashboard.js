(function () {
    const dashboard = document.getElementById('support-dashboard');
    if (!dashboard) return;

    const content = document.getElementById('support-dashboard-content');
    const alert = document.getElementById('support-dashboard-realtime-alert');
    const connection = document.getElementById('support-dashboard-connection');
    const updated = document.getElementById('support-dashboard-updated');
    const pollInterval = Math.max(15000, Number(dashboard.dataset.pollInterval || 15000));
    const state = {
        live: false,
        loading: false,
        queued: false,
        failures: 0,
        healthTimer: null,
        activityTimer: null,
        updatedAt: new Date(),
        lateMinutes: Math.max(1, Number(dashboard.dataset.lateMinutes || 15)),
    };

    function setConnection(mode) {
        const styles = {
            live: ['text-green', 'bg-green', 'Live'],
            polling: ['text-yellow', 'bg-yellow', 'Polling'],
            offline: ['text-red', 'bg-red', 'Offline'],
            connecting: ['text-secondary', 'bg-secondary', 'Connecting'],
        };
        const [textClass, dotClass, label] = styles[mode] || styles.connecting;
        connection.className = `small fw-medium ${textClass} d-inline-flex align-items-center gap-1`;
        const dot = document.createElement('span');
        dot.className = `status-dot ${mode === 'connecting' ? 'status-dot-animated' : ''} ${dotClass}`;
        connection.replaceChildren(dot, document.createTextNode(label));
    }

    function showAlert(visible) {
        alert.classList.toggle('d-none', !visible);
        alert.classList.toggle('d-flex', visible);
    }

    function scheduleHealthAlert() {
        window.clearTimeout(state.healthTimer);
        if (state.live || !navigator.onLine) return;
        state.healthTimer = window.setTimeout(() => {
            if (!state.live && navigator.onLine) showAlert(true);
        }, 12000);
    }

    function handleRealtime(realtime) {
        state.live = realtime.mode === 'live';
        state.failures = Number(realtime.failures || 0);
        if (state.live) {
            window.clearTimeout(state.healthTimer);
            showAlert(false);
            setConnection('live');
            refreshDashboard();
            return;
        }

        setConnection(navigator.onLine ? 'polling' : 'offline');
        if (realtime.configured === false || state.failures >= 3) showAlert(true);
        scheduleHealthAlert();
        if (navigator.onLine) refreshDashboard();
    }

    async function refreshDashboard() {
        if (state.loading || document.hidden || !navigator.onLine) {
            state.queued = state.loading;
            return;
        }
        state.loading = true;
        content.classList.add('is-refreshing');
        try {
            const url = new URL(dashboard.dataset.refreshUrl, window.location.origin);
            url.searchParams.set('days', dashboard.dataset.days);
            url.searchParams.set('late_minutes', state.lateMinutes);
            const response = await fetch(url, {
                headers: {Accept: 'application/json'},
                credentials: 'same-origin',
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Dashboard refresh failed.');
            content.innerHTML = payload.data.html;
            initQueuePagination();
            reloadDatatables();
            state.updatedAt = new Date(payload.data.generated_at || Date.now());
            updateRelativeTimes();
        } catch (_) {
            if (!state.live) {
                setConnection('offline');
                showAlert(true);
            }
        } finally {
            state.loading = false;
            requestAnimationFrame(() => content.classList.remove('is-refreshing'));
            if (state.queued) {
                state.queued = false;
                refreshDashboard();
            }
        }
    }

    function reloadDatatables() {
        if (!window.jQuery || !window.jQuery.fn?.DataTable) return;

        ['support-team-table', 'support-ratings-table'].forEach(tableId => {
            const selector = `#${tableId}`;
            if (window.jQuery.fn.DataTable.isDataTable(selector)) {
                window.jQuery(selector).DataTable().ajax.reload(null, false);
            }
        });
    }

    async function loadQueuePage(queueElement, reset = false) {
        if (queueElement.dataset.loading === 'true' || !navigator.onLine) return;

        const currentPage = Number(queueElement.dataset.currentPage || 1);
        const lastPage = Number(queueElement.dataset.lastPage || 1);
        if (!reset && currentPage >= lastPage) return;

        queueElement.dataset.loading = 'true';
        const loader = queueElement.querySelector('[data-queue-loading]');
        loader?.classList.remove('d-none');
        try {
            const queue = queueElement.dataset.supportQueue;
            const url = new URL(dashboard.dataset.queueUrl, window.location.origin);
            url.searchParams.set('queue', queue);
            url.searchParams.set('page', reset ? '1' : String(currentPage + 1));
            if (queue === 'late') url.searchParams.set('late_minutes', state.lateMinutes);

            const response = await fetch(url, {
                headers: {Accept: 'application/json'},
                credentials: 'same-origin',
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Queue refresh failed.');

            const items = queueElement.querySelector('[data-queue-items]');
            if (reset) {
                items.innerHTML = payload.data.html;
                queueElement.scrollTop = 0;
            } else {
                items.insertAdjacentHTML('beforeend', payload.data.html);
            }
            queueElement.dataset.currentPage = payload.data.current_page;
            queueElement.dataset.lastPage = payload.data.last_page;
            const total = content.querySelector(`[data-queue-total="${queue}"]`);
            if (total) total.textContent = queue === 'late' ? `${payload.data.total} late` : payload.data.total;
            if (queue === 'late') {
                queueElement.closest('.card')?.classList.toggle('border-danger', payload.data.total > 0);
            }
            updateRelativeTimes();
        } catch (_) {
            if (!state.live) showAlert(true);
        } finally {
            loader?.classList.add('d-none');
            queueElement.dataset.loading = 'false';
        }
    }

    function initQueuePagination() {
        content.querySelectorAll('[data-support-queue]').forEach(queueElement => {
            if (queueElement.dataset.scrollBound === 'true') return;
            queueElement.dataset.scrollBound = 'true';
            queueElement.addEventListener('scroll', () => {
                const remaining = queueElement.scrollHeight - queueElement.scrollTop - queueElement.clientHeight;
                if (remaining < 96) loadQueuePage(queueElement);
            }, {passive: true});
        });

        const lateFilter = content.querySelector('[data-late-reply-filter]');
        if (lateFilter && lateFilter.dataset.changeBound !== 'true') {
            lateFilter.dataset.changeBound = 'true';
            lateFilter.addEventListener('change', () => {
                state.lateMinutes = Math.max(1, Number(lateFilter.value || 15));
                dashboard.dataset.lateMinutes = state.lateMinutes;
                const lateQueue = content.querySelector('[data-support-queue="late"]');
                if (lateQueue) {
                    lateQueue.dataset.lateMinutes = state.lateMinutes;
                    loadQueuePage(lateQueue, true);
                }
            });
        }
    }

    function durationSince(value) {
        const started = new Date(value);
        if (Number.isNaN(started.getTime())) return '';
        const seconds = Math.max(0, Math.floor((Date.now() - started.getTime()) / 1000));
        if (seconds < 60) return `${seconds} sec`;
        if (seconds < 3600) return `${Math.floor(seconds / 60)} min`;
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        return `${hours} hr${minutes ? ` ${minutes} min` : ''}`;
    }

    function updateRelativeTimes() {
        content.querySelectorAll('[data-wait-since]').forEach(element => {
            element.textContent = durationSince(element.dataset.waitSince);
        });
        const seconds = Math.max(0, Math.floor((Date.now() - state.updatedAt.getTime()) / 1000));
        updated.textContent = seconds < 10
            ? 'just now'
            : seconds < 60
                ? `${seconds} sec ago`
                : `${Math.floor(seconds / 60)} min ago`;
    }

    function queueActivityRefresh() {
        window.clearTimeout(state.activityTimer);
        state.activityTimer = window.setTimeout(refreshDashboard, 180);
    }

    window.addEventListener('support:realtime', event => handleRealtime(event.detail || {}));
    window.addEventListener('support:activity', queueActivityRefresh);
    window.addEventListener('online', () => {
        setConnection(state.live ? 'live' : 'polling');
        refreshDashboard();
        scheduleHealthAlert();
    });
    window.addEventListener('offline', () => setConnection('offline'));
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refreshDashboard();
    });

    if (window.SupportAdminRealtime) handleRealtime(window.SupportAdminRealtime);
    else scheduleHealthAlert();
    window.setInterval(() => {
        if (!state.live) refreshDashboard();
    }, pollInterval);
    window.setInterval(updateRelativeTimes, 30000);
    initQueuePagination();
    updateRelativeTimes();
})();
