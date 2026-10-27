'use strict';

/**
 * System Updates page controller.
 * - Submits the update ZIP via axios and kicks off a queued job on the server.
 * - Polls the update row and mirrors status / step / progress / log into the UI.
 * - Auto-resumes polling on page refresh if a pending row is still active.
 */
(function () {
    const form = document.getElementById('update-form');
    const liveCard = document.getElementById('live-log-card');
    const liveText = document.getElementById('live-log-text');
    const liveStatus = document.getElementById('live-log-status');
    const liveVersion = document.getElementById('live-log-version');
    const liveStep = document.getElementById('live-log-step');
    const liveProgressBar = document.getElementById('live-log-progress-bar');
    const liveStaleBanner = document.getElementById('live-log-stale-banner');
    const copyBtn = document.getElementById('copy-live-log');
    const applyBtn = document.getElementById('apply-update-btn');
    const errorBox = document.getElementById('update-error-box');
    const errorList = document.getElementById('update-error-list');

    const config = document.getElementById('system-update-config');
    const cfg = config ? config.dataset : {};
    const latestUrl = cfg.latestUrl;
    const logUrlTmpl = cfg.logUrl;
    const labels = {
        starting: cfg.labelStarting || 'Starting…',
        processing: cfg.labelProcessing || 'Processing…',
        applied: cfg.labelApplied || 'Applied',
        failed: cfg.labelFailed || 'Failed',
        applying: cfg.labelApplying || 'Applying…',
        apply: cfg.labelApply || 'Apply Update',
        copied: cfg.labelCopied || 'Copied',
        copy: cfg.labelCopy || 'Copy',
        stuck: cfg.labelStuck || 'No progress for several minutes — run may be stuck.',
    };

    let preparedUpdate = form ? JSON.parse(form.dataset.preparedUpdate || 'null') : null;
    const reviewPrepared = document.getElementById('review-prepared-update');
    let recovery = null;
    let pollTimer = null;
    let pollIntervalMs = 2000;
    let pollErrorStreak = 0;
    let currentUpdateId = null;
    let pollStartedAt = null;
    const POLL_MAX_MS = 45 * 60 * 1000; // 45 min safety ceiling

    function setStatus(text, variant) {
        if (!liveStatus) return;
        liveStatus.textContent = text;
        liveStatus.className = 'badge ' + (variant || 'bg-secondary-lt');
    }

    function setStep(stepKey) {
        if (!liveStep) return;
        if (!stepKey) { liveStep.textContent = ''; return; }
        // step comes as snake_case (e.g. "rolling_back"); dataset key is camel-case of the hyphenated data-* attr
        const camel = stepKey.replace(/_/g, '-')
            .split('-').map((s, i) => i === 0 ? s : s.charAt(0).toUpperCase() + s.slice(1)).join('');
        const attr = 'labelStep' + camel.charAt(0).toUpperCase() + camel.slice(1);
        liveStep.textContent = cfg[attr] || stepKey;
    }

    function setProgress(n) {
        if (!liveProgressBar) return;
        const pct = Math.max(0, Math.min(100, Number(n) || 0));
        liveProgressBar.style.width = pct + '%';
        liveProgressBar.setAttribute('aria-valuenow', String(pct));
        liveProgressBar.textContent = pct + '%';
    }

    function showStaleBanner(show) {
        if (!liveStaleBanner) return;
        liveStaleBanner.classList.toggle('d-none', !show);
    }

    function clearErrors() {
        if (errorBox) errorBox.classList.add('d-none');
        if (errorList) errorList.innerHTML = '';
    }

    function requireRequestUrl(url) {
        if (typeof url !== 'string' || !url.trim()) {
            throw new Error('The system updater request URL is unavailable. Refresh this page and try again.');
        }

        return url;
    }

    function renderErrors(messages) {
        if (!errorBox || !errorList) return;
        errorList.innerHTML = '';
        messages.forEach((m) => {
            const li = document.createElement('li');
            li.textContent = m;
            errorList.appendChild(li);
        });
        errorBox.classList.remove('d-none');
    }

    function disableForm() {
        if (form) form.package.disabled = true;
        if (reviewPrepared) reviewPrepared.disabled = true;
        if (applyBtn) {
            applyBtn.disabled = true;
            applyBtn.textContent = labels.applying;
        }
    }

    function enableForm() {
        if (form) form.package.disabled = false;
        if (reviewPrepared) reviewPrepared.disabled = false;
        if (applyBtn) {
            applyBtn.disabled = false;
            applyBtn.textContent = labels.apply;
        }
    }

    function applyPayload(data) {
        if (!data) return;

        if (!currentUpdateId && data.id) currentUpdateId = data.id;

        if (liveText) {
            liveText.textContent = data.log || '';
            liveText.scrollTop = liveText.scrollHeight;
        }
        if (liveVersion) {
            liveVersion.textContent = data.version && data.id
                ? `(v${data.version} #${data.id})`
                : '';
        }

        setStep(data.step);
        setProgress(data.progress);
        showStaleBanner(Boolean(data.is_stale));

        const status = (data.status || '').toLowerCase();
        if (status === 'pending') {
            setStatus(labels.processing, 'bg-warning-lt');
        } else if (status === 'applied') {
            setStatus(labels.applied, 'bg-success-lt');
            setProgress(100);
            stopPolling();
            enableForm();
        } else if (status === 'failed') {
            setStatus(labels.failed, 'bg-danger-lt');
            stopPolling();
            enableForm();
        }
    }

    function selectNewestState(states) {
        return states.sort((left, right) => {
            const leftFinished = ['applied', 'failed'].includes((left.status || '').toLowerCase()) ? 1 : 0;
            const rightFinished = ['applied', 'failed'].includes((right.status || '').toLowerCase()) ? 1 : 0;
            return rightFinished - leftFinished
                || (right.log || '').length - (left.log || '').length
                || new Date(right.heartbeat_at || 0) - new Date(left.heartbeat_at || 0);
        })[0];
    }

    async function pollOnce() {
        try {
            const requests = [];
            if (recovery) requests.push(window.axios.get(recovery.url, {headers: {'X-System-Update-Token': recovery.token}}));
            requests.push(window.axios.get(currentUpdateId ? logUrlTmpl.replace('__ID__', currentUpdateId) : latestUrl));
            const responses = await Promise.allSettled(requests);
            const states = responses.filter(response => response.status === 'fulfilled').map(response => response.value.data).filter(Boolean);
            if (!states.length) throw responses[0]?.reason || new Error('Update status is unavailable.');
            const data = selectNewestState(states);
            pollErrorStreak = 0;
            pollIntervalMs = 2000;
            applyPayload(data);
        } catch (err) {
            pollErrorStreak += 1;
            if (pollErrorStreak >= 3 && pollIntervalMs < 10000) {
                pollIntervalMs = 5000;
                schedulePoll();
            }
        }

        if (pollStartedAt && Date.now() - pollStartedAt > POLL_MAX_MS) {
            stopPolling();
            setStatus(labels.failed, 'bg-danger-lt');
            showStaleBanner(true);
            enableForm();
        }
    }

    function schedulePoll() {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(pollOnce, pollIntervalMs);
    }

    function startPolling() {
        pollStartedAt = pollStartedAt || Date.now();
        pollErrorStreak = 0;
        pollIntervalMs = 2000;
        pollOnce();
        schedulePoll();
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
        pollStartedAt = null;
    }

    function showLiveCard() {
        if (liveCard) liveCard.classList.remove('d-none');
    }

    if (copyBtn && liveText) {
        copyBtn.addEventListener('click', function () {
            navigator.clipboard.writeText(liveText.textContent || '');
            this.textContent = labels.copied;
            setTimeout(() => (this.textContent = labels.copy), 1500);
        });
    }

    function updateModal() {
        const dialog = document.getElementById('confirm-update-modal');
        if (dialog && window.jQuery && typeof window.jQuery.fn.modal === 'function') {
            return {show: () => window.jQuery(dialog).modal('show'), hide: () => window.jQuery(dialog).modal('hide')};
        }
        if (dialog && window.bootstrap?.Modal) return window.bootstrap.Modal.getOrCreateInstance(dialog);
        throw new Error('The update dialog is unavailable. Check that the admin theme scripts loaded, then try again.');
    }

    function confirmUpdate(preview, modal) {
        const dialog = document.getElementById('confirm-update-modal');
        dialog.querySelector('[data-update-version]').textContent = preview.version;
        dialog.querySelector('[data-vendor-warning]').hidden = !preview.vendor_update;
        dialog.querySelector('[data-database-warning]').hidden = !preview.database_update;
        const platformWarning = dialog.querySelector('[data-platform-warning]');
        const platformWarningList = dialog.querySelector('[data-platform-warning-list]');
        const warnings = Array.isArray(preview.platform_warnings) ? preview.platform_warnings : [];
        platformWarning.hidden = warnings.length === 0;
        platformWarningList.replaceChildren(...warnings.map(warning => {
            const item = document.createElement('li');
            item.textContent = warning;
            return item;
        }));
        const confirm = dialog.querySelector('[data-confirm-update]');
        confirm.disabled = true;
        dialog.addEventListener('shown.bs.modal', () => { confirm.disabled = false; }, {once: true});
        return new Promise(resolve => {
            let confirmed = false;
            const approve = () => { confirmed = true; modal.hide(); };
            confirm.addEventListener('click', approve);
            dialog.addEventListener('hidden.bs.modal', () => {
                confirm.removeEventListener('click', approve);
                resolve(confirmed);
            }, {once: true});
            modal.show();
        });
    }

    if (form) {
        form.package.addEventListener('change', () => {
            if (form.package.files.length) form.requestSubmit();
        });
        if (reviewPrepared) reviewPrepared.addEventListener('click', event => submitUpdate(event, true));
        form.addEventListener('submit', event => submitUpdate(event, false));
        async function submitUpdate(e, usePrepared) {
            e.preventDefault();
            if (usePrepared ? !preparedUpdate : !form.package.files.length) return;
            if (applyBtn && applyBtn.disabled) return; // double-submit guard

            clearErrors();
            showLiveCard();
            if (liveText) liveText.textContent = '';
            if (liveVersion) liveVersion.textContent = '';
            currentUpdateId = null;
            recovery = null;
            setStatus(labels.starting, 'bg-secondary-lt');
            setStep('queued');
            setProgress(0);
            showStaleBanner(false);
            const formData = usePrepared ? null : new FormData(form);
            disableForm();

            try {
                setStep('verifying');
                if (!usePrepared) {
                    if (liveText) liveText.textContent = 'Uploading and checking the package. Your website has not changed.';
                    const previewUrl = requireRequestUrl(form.getAttribute('data-preview-url'));
                    const {data} = await window.axios.post(previewUrl, formData);
                    preparedUpdate = data;
                    if (reviewPrepared) reviewPrepared.hidden = false;
                }
                const preview = preparedUpdate;
                if (liveText) liveText.textContent = preview.vendor_update
                    ? (preview.platform_warnings?.length
                        ? 'Package checked with server compatibility warnings. The prepared dependencies can still be installed without Composer. Review the warnings before confirmation.'
                        : 'Package checked. This update includes prepared dependencies; Composer will not run. Waiting for your confirmation.')
                    : 'Package checked. Waiting for your confirmation.';
                if (preview.vendor_update) {
                    const modal = updateModal();
                    if (!await confirmUpdate(preview, modal)) {
                        if (liveText) liveText.textContent = 'Update cancelled. Your website has not changed.';
                        enableForm();
                        return;
                    }
                }
                const submitUrl = requireRequestUrl(form.getAttribute('action'));
                const { data } = await window.axios.post(submitUrl, {token: preview.token, confirmed: true});
                preparedUpdate = null;
                if (reviewPrepared) reviewPrepared.hidden = true;
                recovery = {url: data.recovery_url, token: data.recovery_token};
                const link = document.getElementById('update-recovery-link');
                if (link && recovery.url && recovery.token) {
                    link.href = recovery.url + '#' + recovery.token;
                    link.classList.remove('d-none');
                }
                if (data && data.update_id) currentUpdateId = data.update_id;
                if (data && data.data) applyPayload(data.data);
                startPolling();
            } catch (err) {
                const resp = err && err.response;
                const payload = resp && resp.data;
                if (payload && payload.errors) {
                    const msgs = [];
                    Object.keys(payload.errors).forEach((k) => {
                        (payload.errors[k] || []).forEach((m) => msgs.push(m));
                    });
                    renderErrors(msgs);
                } else if (payload && payload.message) {
                    renderErrors([payload.message]);
                } else {
                    renderErrors([err.message || 'Network error. Please try again.']);
                }
                stopPolling();
                enableForm();
            }
        }
    }

    document.addEventListener('DOMContentLoaded', async function () {
        if (!latestUrl) return;
        try {
            const { data } = await window.axios.get(latestUrl);
            if (data && (data.status || '').toLowerCase() === 'pending') {
                currentUpdateId = data.id;
                showLiveCard();
                disableForm();
                applyPayload(data);
                startPolling();
            }
        } catch (_) { /* ignore — page still usable */ }
    });
})();
