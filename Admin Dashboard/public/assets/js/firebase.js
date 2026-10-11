import {initializeApp} from "https://www.gstatic.com/firebasejs/11.0.1/firebase-app.js";
import {getMessaging, getToken, onMessage} from "https://www.gstatic.com/firebasejs/11.0.1/firebase-messaging.js";

// Best-effort sync of the browser's FCM token to the backend so admin/seller
// panels can be targeted by push notifications. Customer + delivery boy tokens
// continue to flow through the API login (AuthTrait::storeFcmToken) and don't
// need this path.
//
// `firebase.js` is loaded as a module, so the inline non-module globals
// (`base_url`, `panel`, `user_id`, `csrfToken`) aren't visible here — read
// them from the DOM the same way the inline bootstrap does.
function readPanelContext() {
    const panelEl = document.getElementById('panel');
    const baseEl = document.getElementById('base_url');
    const userEl = document.getElementById('user_id');
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const panel = panelEl ? (panelEl.getAttribute('data-panel') || '') : '';

    return {
        panel,
        baseUrl: baseEl ? (baseEl.value || '') : window.location.origin,
        userId: userEl ? (userEl.value || '') : '',
        csrf: csrfMeta ? (csrfMeta.getAttribute('content') || '') : '',
        roleType: panel === 'admin' ? 'admin' : (panel === 'seller' ? 'seller' : ''),
    };
}

function normalizeForegroundMessage(payload) {
    const notification = payload?.notification || {};
    const data = payload?.data || {};

    return {
        title: notification.title || data.title || 'Notification',
        body: notification.body || data.body || data.message || '',
        image: notification.image || notification.imageUrl || data.image || data.imageUrl || '',
    };
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function createForegroundToast({ title, body, image }) {
    const toastEl = document.createElement('div');
    toastEl.className = 'foreground-toast';
    toastEl.setAttribute('role', 'alert');
    toastEl.setAttribute('aria-live', 'assertive');
    toastEl.setAttribute('aria-atomic', 'true');

    const safeTitle = escapeHtml(title || 'Notification');
    const safeBody = escapeHtml(body || '');
    const imageMarkup = image
        ? `<img src="${escapeHtml(image)}" class="foreground-toast__thumb" alt="${safeTitle}">`
        : `<span class="foreground-toast__icon" aria-hidden="true">
                <svg viewBox="0 0 20 20" fill="none">
                    <path d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Z" fill="currentColor" opacity=".14"/>
                    <path d="m6.7 10.1 2.1 2.1 4.5-4.6" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
           </span>`;

    toastEl.innerHTML = `
        <div class="foreground-toast__surface">
            ${imageMarkup}
            <div class="foreground-toast__content">
                <div class="foreground-toast__title">${safeTitle}</div>
                ${safeBody ? `<div class="foreground-toast__body">${safeBody}</div>` : ''}
            </div>
            <button type="button" class="foreground-toast__close" aria-label="Close notification">
                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="m6 6 8 8M14 6l-8 8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                </svg>
            </button>
        </div>
    `;

    const closeButton = toastEl.querySelector('.foreground-toast__close');
    const dismiss = () => {
        toastEl.classList.add('is-hiding');
        window.setTimeout(() => {
            toastEl.remove();
        }, 220);
    };

    closeButton?.addEventListener('click', dismiss);
    window.setTimeout(dismiss, 5200);

    return toastEl;
}

async function ensureMessagingServiceWorker() {
    if (!('serviceWorker' in navigator)) {
        throw new Error('Service workers are not supported in this browser.');
        console.warn('Service workers not supported, skipping Firebase messaging setup.');
    }
    console.log('Registering Firebase messaging service worker...');

    const serviceWorkerUrl = `${window.location.origin}/firebase-messaging-sw.js`;
    const registration = await navigator.serviceWorker.register(serviceWorkerUrl, {scope: '/'});

    // Force the browser to refresh the worker script when it has changed so we
    // don't stay stuck on an older worker that registered push handlers too late.
    await registration.update().catch(() => {});

    if (registration.active) {
        return registration;
    }

    return navigator.serviceWorker.ready;
}

async function syncTokenWithPanel(token, previousToken) {
    try {
        const ctx = readPanelContext();

        if (!ctx.userId || (ctx.panel !== 'admin' && ctx.panel !== 'seller')) {
            return;
        }

        await axios.post(`${ctx.baseUrl}/${ctx.panel}/devices/sync`, {
            fcm_token: token,
            device_type: 'web',
            previous_token: previousToken || undefined,
            role_type: ctx.roleType || undefined,
        }, {
            headers: {
                'X-CSRF-TOKEN': ctx.csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
    } catch (err) {
        // Never break the page if the sync fails — token is already cached locally
        // and the next page load will retry.
        console.warn('Panel device sync failed:', err);
    }
}

async function initFirebase() {
    try {
        let firebaseConfig = JSON.parse(localStorage.getItem('firebase_config'));

        if (!firebaseConfig) {
            const { data } = await axios.get('/api/settings/firebase-config');
            firebaseConfig = data.data;
            localStorage.setItem('firebase_config', JSON.stringify(firebaseConfig));
        }

        const app = initializeApp(firebaseConfig);
        const messaging = getMessaging(app);
        const serviceWorkerRegistration = await ensureMessagingServiceWorker();

        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            console.warn('Notification permission not granted');
            return;
        }

        const vapidKey = firebaseConfig.vapidKey;
        const token = await getToken(messaging, {
            vapidKey,
            serviceWorkerRegistration,
        });

        // Capture the previously-cached token *before* overwriting so the server
        // can drop the stale row in the same write when FCM rotates the token.
        const previousToken = localStorage.getItem('fcm_token');
        localStorage.setItem('fcm_token', token);

        if (token) {
            await syncTokenWithPanel(token, previousToken && previousToken !== token ? previousToken : null);
        }

        // Foreground messages — render an in-page toast.
        onMessage(messaging, (payload) => {
            console.log('Message received in foreground:', payload);

            const { title, body, image } = normalizeForegroundMessage(payload);

            let toastContainer = document.getElementById('toastContainer');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'toastContainer';
                toastContainer.className = 'foreground-toast-stack';
                document.body.appendChild(toastContainer);
            }

            toastContainer.prepend(createForegroundToast({ title, body, image }));

            try {
                const audio = new Audio('/assets/sound/notification.wav');
                audio.volume = 1.0;
                audio.play().catch((err) => {
                    console.warn('Autoplay blocked for notification sound:', err);
                });
            } catch (e) {
                console.warn('Failed to play notification sound:', e);
            }
        });


    } catch (err) {
        console.error('Error initializing Firebase:', err);
    }
}

initFirebase();
