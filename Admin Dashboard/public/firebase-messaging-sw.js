importScripts("https://www.gstatic.com/firebasejs/11.0.1/firebase-app-compat.js");
importScripts("https://www.gstatic.com/firebasejs/11.0.1/firebase-messaging-compat.js");

// Service worker event listeners MUST be registered synchronously during the
// initial evaluation of this script. Registering them inside an async callback
// (e.g. after `await fetch`) makes Chrome/Edge skip them and emit:
//   "Event handler of 'push'/'notificationclick'/'pushsubscriptionchange' must
//    be added on the initial evaluation of worker script."
// The handlers below cover background notifications natively so we no longer
// rely on `messaging.onBackgroundMessage` being attached before the first push.

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
    let payload = {};
    if (event.data) {
        try {
            payload = event.data.json();
        } catch (e) {
            try {
                payload = { notification: { title: 'Notification', body: event.data.text() } };
            } catch (_) {
                payload = {};
            }
        }
    }

    const notification = payload.notification || {};
    const data = payload.data || {};

    const title = notification.title || data.title || 'New Notification';
    const options = {
        body: notification.body || data.body || '',
        icon: notification.icon || data.icon || '/favicon.ico',
        image: notification.image || data.image || undefined,
        badge: '/favicon.ico',
        data: {
            click_action: notification.click_action || data.click_action || data.url || '/',
            ...data,
        },
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const targetUrl = (event.notification.data && event.notification.data.click_action) || '/';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if ('focus' in client) {
                    client.navigate(targetUrl).catch(() => {});
                    return client.focus();
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(targetUrl);
            }
            return null;
        })
    );
});

self.addEventListener('pushsubscriptionchange', (event) => {
    // Re-subscription is handled by the Firebase SDK on the next page load via
    // getToken(). This empty handler exists only to satisfy the requirement that
    // it be registered during initial evaluation.
});

// Firebase init runs after the synchronous handlers above are wired up. It is
// kept so the Firebase SDK's own token housekeeping continues to work, but the
// notifications themselves are rendered by the native `push` handler above.
async function initFirebase() {
    try {
        const response = await fetch('/api/settings/firebase-config');
        const firebaseConfig = await response.json();

        firebase.initializeApp(firebaseConfig.data);
        firebase.messaging();
    } catch (error) {
        console.error('Firebase SW init error:', error);
    }
}

initFirebase();
