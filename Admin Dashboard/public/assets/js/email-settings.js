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

    function formatBody(raw) {
        if (raw === null || raw === undefined || raw === '') return '';
        const text = String(raw).trim();
        if ((text.startsWith('{') && text.endsWith('}')) || (text.startsWith('[') && text.endsWith(']'))) {
            try { return JSON.stringify(JSON.parse(text), null, 2); } catch (_) { /* noop */ }
        }
        return text;
    }

    function buildResultHtml(message, payload) {
        const status = payload?.gateway_status;
        const body   = payload?.gateway_body;
        const error  = payload?.gateway_error;

        const parts = [];
        parts.push(`<div class="mb-2">${escapeHtml(message || '')}</div>`);

        if (status !== null && status !== undefined) {
            parts.push(`<div class="text-start small text-muted mb-1">HTTP ${escapeHtml(status)}</div>`);
        }

        const codeContent = body ? formatBody(body) : (error || '');
        if (codeContent !== '') {
            parts.push(
                '<pre class="text-start mb-0" style="max-height:280px;overflow:auto;background:#0f172a;color:#e2e8f0;padding:12px;border-radius:6px;font-size:12px;line-height:1.4;">' +
                `<code>${escapeHtml(codeContent)}</code>` +
                '</pre>'
            );
        }
        return parts.join('');
    }

    function bindTestEmail() {
        const button = document.getElementById('testEmailButton');
        if (!button) return;

        const form = document.querySelector('form.form-submit');
        const recipientInput = document.getElementById('testEmailRecipient');
        const endpoint = button.dataset.endpoint;
        const successTitle = button.dataset.successTitle || 'Email sent';
        const failedTitle  = button.dataset.failedTitle  || 'Test failed';
        const requiredFields = ['smtpHost', 'smtpPort', 'smtpEmail', 'smtpPassword', 'smtpEncryption'];

        const setBusy = (busy) => {
            button.disabled = busy;
            button.querySelector('.spinner-border')?.classList.toggle('d-none', !busy);
        };

        const missingSmtpFields = () => {
            if (!form) return true;
            return requiredFields.some((name) => {
                const el = form.querySelector(`[name="${name}"]`);
                return !el || String(el.value || '').trim() === '';
            });
        };

        button.addEventListener('click', async (e) => {
            e.preventDefault();
            if (!form || !recipientInput) return;

            const recipient = recipientInput.value.trim();
            if (recipient === '') {
                Toast.fire({ icon: 'warning', title: button.dataset.missingRecipient || 'Enter a recipient email first.' });
                recipientInput.focus();
                return;
            }

            if (missingSmtpFields()) {
                Toast.fire({ icon: 'warning', title: button.dataset.missingSmtp || 'Fill in all SMTP fields first.' });
                return;
            }

            const formData = new FormData(form);
            formData.append('test_email', recipient);

            setBusy(true);
            try {
                const { data } = await axios.post(endpoint, formData, {
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                });

                Swal.fire({
                    icon:  data?.success ? 'success' : 'error',
                    title: data?.success ? successTitle : failedTitle,
                    html:  buildResultHtml(data?.message, data?.data),
                    width: 600,
                });
                recipientInput.value = '';
            } catch (err) {
                const responseData = err?.response?.data;
                Swal.fire({
                    icon:  'error',
                    title: failedTitle,
                    html:  buildResultHtml(
                        responseData?.message || err?.message || 'Request failed.',
                        responseData?.data || { gateway_error: err?.message }
                    ),
                    width: 600,
                });
            } finally {
                setBusy(false);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', bindTestEmail);
})();
