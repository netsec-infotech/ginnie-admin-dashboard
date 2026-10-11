'use strict';

(function () {
    function addField(containerId, keyName, valueName, keyPlaceholder, valuePlaceholder) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const fieldDiv = document.createElement('div');
        fieldDiv.className = 'row mb-2 ' + containerId.replace('Fields', '') + '-field';
        fieldDiv.innerHTML = `
            <div class="col-md-5">
                <input type="text" class="form-control" name="${keyName}[]" placeholder="${keyPlaceholder}" />
            </div>
            <div class="col-md-5">
                <input type="text" class="form-control" name="${valueName}[]" placeholder="${valuePlaceholder}" />
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-danger btn-sm remove-field" aria-label="Remove">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                        <path d="M4 7l16 0" />
                        <path d="M10 11l0 6" />
                        <path d="M14 11l0 6" />
                        <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" />
                        <path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" />
                    </svg>
                </button>
            </div>
        `;
        container.appendChild(fieldDiv);
    }

    function bindAddButtons() {
        const addHeader = document.getElementById('addHeaderField');
        const addParams = document.getElementById('addParamsField');
        const addBody = document.getElementById('addBodyField');

        if (addHeader) addHeader.addEventListener('click', () =>
            addField('headerFields', 'customSmsHeaderKey', 'customSmsHeaderValue', 'Header Key', 'Header Value'));

        if (addParams) addParams.addEventListener('click', () =>
            addField('paramsFields', 'customSmsParamsKey', 'customSmsParamsValue', 'Parameter Key', 'Parameter Value'));

        if (addBody) addBody.addEventListener('click', () =>
            addField('bodyFields', 'customSmsBodyKey', 'customSmsBodyValue', 'Body Key', 'Body Value'));

        document.addEventListener('click', (e) => {
            const trigger = e.target.closest('.remove-field');
            if (trigger) trigger.closest('.row')?.remove();
        });
    }

    function bindToggleSections() {
        const customSmsToggle = document.querySelector('input[name="customSms"]');
        const customSmsFields = document.getElementById('customSmsFields');
        const firebaseToggle = document.querySelector('input[name="firebase"]');
        const firebaseFields = document.getElementById('firebaseFields');
        const gatewayBadge = document.getElementById('activeSmsGateway');

        const refreshSections = () => {
            if (customSmsFields && customSmsToggle) {
                customSmsFields.style.display = customSmsToggle.checked ? 'block' : 'none';
            }
            if (firebaseFields && firebaseToggle) {
                firebaseFields.style.display = firebaseToggle.checked ? 'block' : 'none';
            }
        };

        const refreshBadge = () => {
            if (!gatewayBadge) return;
            const customOn = customSmsToggle?.checked;
            const firebaseOn = firebaseToggle?.checked;
            if (customOn) {
                gatewayBadge.textContent = 'Custom (Priority)';
                gatewayBadge.className = 'badge bg-success-lt';
            } else if (firebaseOn) {
                gatewayBadge.textContent = 'Firebase';
                gatewayBadge.className = 'badge bg-success-lt';
            } else {
                gatewayBadge.textContent = 'Not Set';
                gatewayBadge.className = 'badge bg-secondary-lt';
            }
        };

        customSmsToggle?.addEventListener('change', () => { refreshSections(); refreshBadge(); });
        firebaseToggle?.addEventListener('change', () => { refreshSections(); refreshBadge(); });

        refreshSections();
        refreshBadge();
    }

    function escapeHtml(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    /**
     * Pretty-print JSON when the body looks like JSON; otherwise return the raw string.
     */
    function formatGatewayBody(raw) {
        if (raw === null || raw === undefined || raw === '') return '';
        const text = String(raw).trim();
        if ((text.startsWith('{') && text.endsWith('}')) || (text.startsWith('[') && text.endsWith(']'))) {
            try {
                return JSON.stringify(JSON.parse(text), null, 2);
            } catch (_) { /* fall through, render as-is */ }
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

        const codeContent = body ? formatGatewayBody(body) : (error || '');
        if (codeContent !== '') {
            parts.push(
                '<pre class="text-start mb-0" style="max-height:280px;overflow:auto;background:#0f172a;color:#e2e8f0;padding:12px;border-radius:6px;font-size:12px;line-height:1.4;">' +
                `<code>${escapeHtml(codeContent)}</code>` +
                '</pre>'
            );
        }

        return parts.join('');
    }

    function bindTestSms() {
        const button = document.getElementById('testSmsButton');
        if (!button) return;

        const form = document.querySelector('form.form-submit');
        const mobileInput = document.getElementById('testSmsMobile');
        const endpoint = button.dataset.endpoint;
        const successTitle = button.dataset.successTitle || 'SMS sent';
        const failedTitle  = button.dataset.failedTitle  || 'Test failed';

        const setBusy = (busy) => {
            button.disabled = busy;
            button.querySelector('.spinner-border')?.classList.toggle('d-none', !busy);
        };

        button.addEventListener('click', async (e) => {
            e.preventDefault();
            if (!form || !mobileInput) return;

            const mobile = mobileInput.value.trim();
            if (mobile === '') {
                Toast.fire({ icon: 'warning', title: button.dataset.missingMobile || 'Enter a test mobile number.' });
                mobileInput.focus();
                return;
            }

            const formData = new FormData(form);
            // The save endpoint expects `type=authentication`; the test endpoint doesn't, but the extra key is harmless.
            formData.append('test_mobile', mobile);

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

    document.addEventListener('DOMContentLoaded', () => {
        bindAddButtons();
        bindToggleSections();
        bindTestSms();
    });
})();
