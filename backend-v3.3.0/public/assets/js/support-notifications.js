(function () {
    const root = document.getElementById('support-notification-listener');
    if (!root || document.getElementById('support-app')) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const agentId = Number(root.dataset.agentId || 0);
    const notificationDuration = 10000;
    const seenMessageIds = window.SupportSeenMessageIds || new Set();
    window.SupportSeenMessageIds = seenMessageIds;
    const realtimeState = window.SupportAdminRealtime || {
        mode: 'connecting', configured: null, failures: 0,
    };
    window.SupportAdminRealtime = realtimeState;

    function publishRealtime(mode, details = {}) {
        realtimeState.mode = mode;
        realtimeState.configured = details.configured ?? realtimeState.configured;
        realtimeState.failures = details.resetFailures
            ? 0
            : realtimeState.failures + (details.failure ? 1 : 0);
        window.dispatchEvent(new CustomEvent('support:realtime', {detail: {...realtimeState}}));
    }

    function notify(event) {
        window.dispatchEvent(new CustomEvent('support:activity', {detail: event}));
        if (!['message.created', 'session.created'].includes(event.activity) || event.message?.sender_role !== 'user') return;
        const assignedTo = Number(event.assigned_to || 0);
        if (assignedTo && assignedTo !== agentId) return;
        const messageId = String(event.message_id || '');
        if (messageId && seenMessageIds.has(messageId)) return;
        if (messageId) seenMessageIds.add(messageId);
        const title = event.customer?.name ? `New message from ${event.customer.name}` : 'New support message';
        const preview = event.message?.preview || 'Open customer support to reply.';
        const target = new URL(root.dataset.supportUrl, window.location.origin);
        target.searchParams.set('thread', event.thread_uuid);
        target.searchParams.set('scope', 'all');

        const openConversation = toast => {
            toast.classList.add('cursor-pointer');
            toast.tabIndex = 0;
            const open = clickEvent => {
                if (clickEvent.type === 'keydown' && !['Enter', ' '].includes(clickEvent.key)) return;
                if (clickEvent.target.closest?.('.swal2-close')) return;
                window.location.assign(target.toString());
            };
            toast.addEventListener('click', open);
            toast.addEventListener('keydown', open);
        };
        const toast = typeof Toast !== 'undefined'
            ? Toast.fire({icon: 'info', title, text: preview, timer: notificationDuration, didOpen: openConversation})
            : window.Swal?.fire({toast: true, position: 'top-end', icon: 'info', title, text: preview, timer: notificationDuration, showConfirmButton: false, didOpen: openConversation});
        Promise.resolve(toast).catch(() => null);
        window.incrementPanelNotificationCount?.();
        document.dispatchEvent(new CustomEvent('panel:notification-received'));
    }

    fetch(root.dataset.realtimeUrl, {
        headers: {Accept: 'application/json', 'X-CSRF-TOKEN': csrf},
        credentials: 'same-origin',
    }).then(response => response.ok ? response.json() : Promise.reject())
        .then(payload => connect(payload.data))
        .catch(() => publishRealtime('unavailable', {failure: true}));

    function connect(config) {
        const realtime = config?.realtime || {};
        if (!config?.configured || !realtime.key || !['reverb', 'pusher'].includes(realtime.driver)) {
            publishRealtime('unavailable', {configured: false, failure: true});
            return;
        }
        if (!window.Echo || !window.Pusher) {
            publishRealtime('unavailable', {configured: true, failure: true});
            return;
        }

        const authorizationHeaders = {'X-CSRF-TOKEN': csrf, Accept: 'application/json'};
        const EchoClient = window.Echo.default || window.Echo;
        let echo;
        try {
            echo = new EchoClient({
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
                channelAuthorization: {endpoint: realtime.auth_endpoint, transport: 'ajax', headers: authorizationHeaders},
            });
        } catch (_) {
            publishRealtime('unavailable', {configured: true, failure: true});
            return;
        }

        realtimeState.echo = echo;
        const inbox = echo.private('support.admin.inbox');
        inbox.subscribed(() => publishRealtime('live', {configured: true, resetFailures: true}));
        inbox.error(() => publishRealtime('unavailable', {configured: true, failure: true}));
        inbox.listen('.support.activity', notify);

        const connection = echo.connector?.pusher?.connection;
        ['disconnected', 'unavailable'].forEach(status => connection?.bind(status, () => {
            publishRealtime('unavailable', {configured: true, failure: true});
        }));
        connection?.bind('failed', () => publishRealtime('unavailable', {configured: true, failure: true}));
    }
})();
