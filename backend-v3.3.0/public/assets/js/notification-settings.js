'use strict';

(function () {
    function escapeHtml(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function buildResultHtml(message, payload) {
        const parts = [`<div class="mb-2">${escapeHtml(message || '')}</div>`];
        if (payload?.gateway_status) {
            parts.push(`<div class="text-start small text-muted mb-1">HTTP ${escapeHtml(payload.gateway_status)}</div>`);
        }
        if (payload?.gateway_body) {
            parts.push(
                '<pre class="text-start mb-0" style="max-height:280px;overflow:auto;background:#0f172a;color:#e2e8f0;padding:12px;border-radius:6px;font-size:12px;line-height:1.4;">' +
                `<code>${escapeHtml(JSON.stringify(payload.gateway_body, null, 2))}</code>` +
                '</pre>'
            );
        }
        return parts.join('');
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (window.bootstrap?.Tooltip) {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => {
                new window.bootstrap.Tooltip(element);
            });
        }

        const button = document.getElementById('testWhatsAppButton');
        const saveTemplatesButton = document.getElementById('saveWhatsAppTemplatesButton');
        const syncTemplatesButton = document.getElementById('syncWhatsAppTemplatesButton');
        const form = document.querySelector('form.form-submit');
        const templatesForm = document.getElementById('whatsappTemplatesForm');
        const mobileInput = document.getElementById('whatsappTestMobile');
        const moduleInput = document.getElementById('whatsappTestModule');
        if (!form) return;

        const setBusy = (target, busy) => {
            if (!target) return;
            target.disabled = busy;
            target.querySelector('.spinner-border')?.classList.toggle('d-none', !busy);
        };

        async function saveTemplateMappings() {
            const payload = new FormData(templatesForm);

            return axios.post(saveTemplatesButton.dataset.endpoint, payload, {
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            });
        }

        if (saveTemplatesButton && templatesForm) {
            saveTemplatesButton.addEventListener('click', async (event) => {
                event.preventDefault();
                setBusy(saveTemplatesButton, true);
                try {
                    const { data } = await saveTemplateMappings();

                    Toast.fire({ icon: data?.success ? 'success' : 'error', title: data?.message || 'Saved.' });
                } catch (error) {
                    Toast.fire({
                        icon: 'error',
                        title: error?.response?.data?.message || error?.message || 'Template save failed.',
                    });
                } finally {
                    setBusy(saveTemplatesButton, false);
                }
            });
        }

        async function syncTemplates(target, module = null) {
            const endpoint = target.dataset.endpoint;
            const payload = new FormData();
            if (module) {
                payload.append('module', module);
            }

            setBusy(target, true);
            try {
                if (templatesForm && saveTemplatesButton) {
                    await saveTemplateMappings();
                }

                const { data } = await axios.post(endpoint, payload, {
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                });

                Toast.fire({ icon: data?.success ? 'success' : 'error', title: data?.message || 'Sync completed.' });
                if (data?.success) {
                    window.location.reload();
                }
            } catch (error) {
                Toast.fire({
                    icon: 'error',
                    title: error?.response?.data?.message || error?.message || 'Template sync failed.',
                });
            } finally {
                setBusy(target, false);
            }
        }

        if (syncTemplatesButton) {
            syncTemplatesButton.addEventListener('click', (event) => {
                event.preventDefault();
                syncTemplates(syncTemplatesButton);
            });
        }

        document.querySelectorAll('.js-whatsapp-template-sync').forEach((syncButton) => {
            syncButton.addEventListener('click', (event) => {
                event.preventDefault();
                syncTemplates(syncButton, syncButton.dataset.module);
            });
        });

        if (!button || !mobileInput || !moduleInput) return;

        const setTestBusy = (busy) => {
            button.disabled = busy;
            button.querySelector('.spinner-border')?.classList.toggle('d-none', !busy);
        };

        button.addEventListener('click', async (event) => {
            event.preventDefault();

            if (mobileInput.value.trim() === '') {
                Toast.fire({ icon: 'warning', title: 'Enter a test mobile number first.' });
                mobileInput.focus();
                return;
            }

            const payload = new FormData(form);
            payload.append('test_mobile', mobileInput.value.trim());
            payload.append('module', moduleInput.value);

            setTestBusy(true);
            try {
                const { data } = await axios.post(button.dataset.endpoint, payload, {
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                });

                Swal.fire({
                    icon: data?.success ? 'success' : 'error',
                    title: data?.success ? 'WhatsApp sent' : 'WhatsApp test failed',
                    html: buildResultHtml(data?.message, data?.data),
                    width: 600,
                });
            } catch (error) {
                const responseData = error?.response?.data;
                Swal.fire({
                    icon: 'error',
                    title: 'WhatsApp test failed',
                    html: buildResultHtml(responseData?.message || error?.message || 'Request failed.', responseData?.data),
                    width: 600,
                });
            } finally {
                setTestBusy(false);
            }
        });
    });
})();
