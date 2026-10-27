'use strict';

(function () {
    const form = document.getElementById('broadcast-settings');
    if (!form) return;

    const driver = document.getElementById('broadcast-driver');
    const testButton = document.getElementById('broadcast-test');
    const allowedOriginsSelect = document.getElementById('allowed-origins-select');
    const allowedOriginsInput = document.getElementById('allowed-origins');
    const sections = Array.from(document.querySelectorAll('[data-broadcast-section]'));
    let settingsDirty = false;

    function syncAllowedOrigins(values) {
        if (!allowedOriginsInput) return;

        const origins = (Array.isArray(values) ? values : [values])
            .map(value => String(value || '').trim())
            .filter(Boolean);

        allowedOriginsInput.value = [...new Set(origins)].join('\n');
    }

    function initAllowedOrigins(attempt = 0) {
        if (!allowedOriginsSelect || allowedOriginsSelect.tomselect) return;
        if (!window.TomSelect) {
            if (attempt < 100) window.setTimeout(() => initAllowedOrigins(attempt + 1), 50);
            return;
        }

        new window.TomSelect(allowedOriginsSelect, {
            create(inputValue, callback) {
                const value = inputValue.trim();
                if (!value) return callback();
                callback({value, text: value});
            },
            createOnBlur: true,
            persist: false,
            maxItems: null,
            splitOn: /[\n,]+/,
            closeAfterSelect: false,
            copyClassesToDropdown: false,
            dropdownParent: 'body',
            placeholder: allowedOriginsSelect.dataset.placeholder || 'Add a host and press Enter',
            onChange: syncAllowedOrigins,
        });
        syncAllowedOrigins(allowedOriginsSelect.tomselect.getValue());
    }

    function syncDriver() {
        if (!driver) return;

        sections.forEach(section => {
            const selected = section.dataset.broadcastSection === driver.value;

            // Show only the selected chat driver configuration and validate only its visible fields.
            section.hidden = !selected;
            section.setAttribute('aria-hidden', selected ? 'false' : 'true');
            section.querySelectorAll('.reverb-required, .pusher-required').forEach(field => {
                field.required = selected && field.dataset.hasSecret !== '1';
            });
        });
    }

    function testId() {
        if (window.crypto?.randomUUID) return window.crypto.randomUUID();

        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, character => {
            const random = Math.floor(Math.random() * 16);
            const value = character === 'x' ? random : (random & 0x3) | 0x8;
            return value.toString(16);
        });
    }

    async function testConnection() {
        if (!testButton) return;

        if (settingsDirty) {
            await Swal.fire({
                icon: 'warning',
                title: form.dataset.saveBeforeTesting,
            });
            return;
        }

        if (form.dataset.configured !== '1') {
            await Swal.fire({
                icon: 'warning',
                title: form.dataset.configurationRequired,
            });
            return;
        }

        const realtime = JSON.parse(form.dataset.realtime || '{}');
        if (!window.Echo || !window.Pusher || !realtime.key) {
            await Swal.fire({icon: 'error', title: form.dataset.configurationRequired});
            return;
        }

        const id = testId();
        const channelName = `broadcast.test.${form.dataset.adminId}.${id}`;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const EchoClient = window.Echo.default || window.Echo;
        let echo;

        testButton.disabled = true;
        Swal.fire({
            title: form.dataset.connectingMessage,
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading(),
        });

        try {
            await new Promise((resolve, reject) => {
                let finished = false;
                let timeout;
                const finish = callback => value => {
                    if (finished) return;
                    finished = true;
                    clearTimeout(timeout);
                    callback(value);
                };
                const succeed = finish(resolve);
                const fail = finish(reject);
                timeout = setTimeout(() => fail(new Error('The socket did not receive the test event within 15 seconds.')), 15000);

                echo = new EchoClient({
                    broadcaster: realtime.driver,
                    key: realtime.key,
                    cluster: realtime.cluster || 'mt1',
                    forceTLS: realtime.scheme === 'https',
                    wsHost: realtime.host || undefined,
                    wsPort: Number(realtime.port || 80),
                    wssPort: Number(realtime.port || 443),
                    enabledTransports: ['ws', 'wss'],
                });

                echo.channel(channelName)
                    .listen('.broadcast.connection.test', event => {
                        if (event.test_id === id) succeed();
                    })
                    .error(error => fail(new Error(error?.error?.data?.message || 'WebSocket subscription failed.')))
                    .subscribed(async () => {
                        try {
                            const response = await fetch(form.dataset.testUrl, {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {
                                    Accept: 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrf,
                                },
                                body: JSON.stringify({test_id: id}),
                            });
                            const payload = await response.json().catch(() => ({}));
                            if (!response.ok || payload.success === false) {
                                fail(new Error(payload.message || 'The backend could not publish the test event.'));
                            }
                        } catch (error) {
                            fail(error);
                        }
                    });
            });

            await Swal.fire({
                icon: 'success',
                title: form.dataset.successTitle,
                text: form.dataset.successMessage.replace('__DRIVER__', realtime.driver === 'pusher' ? 'Pusher' : 'Reverb'),
            });
        } catch (error) {
            await Swal.fire({
                icon: 'error',
                title: 'Connection test failed',
                text: error.message,
            });
        } finally {
            if (echo) {
                echo.leave(channelName);
                echo.disconnect();
            }
            testButton.disabled = false;
        }
    }

    driver?.addEventListener('change', syncDriver);
    form.addEventListener('input', () => {
        settingsDirty = true;
    });
    form.addEventListener('change', () => {
        settingsDirty = true;
    });
    testButton?.addEventListener('click', testConnection);
    initAllowedOrigins();
    syncDriver();
})();
