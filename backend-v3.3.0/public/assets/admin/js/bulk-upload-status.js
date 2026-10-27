'use strict';

(function () {
    document.addEventListener('DOMContentLoaded', function () {
        const cfg = window.BULK_UPLOAD_STATUS_CFG || {};
        if (!cfg.statusUrl) {
            return;
        }

        const els = {
            statusText: document.getElementById('bulk-status-text'),
            statusBadge: document.getElementById('bulk-status-badge'),
            startedAt: document.getElementById('bulk-started-at'),
            finishedAt: document.getElementById('bulk-finished-at'),
            processedRows: document.getElementById('bulk-processed-rows'),
            totalRows: document.getElementById('bulk-total-rows'),
            progressPercent: document.getElementById('bulk-progress-percent'),
            progressBar: document.getElementById('bulk-progress-bar'),
            statTotal: document.getElementById('bulk-stat-total'),
            statSuccess: document.getElementById('bulk-stat-success'),
            statUpdated: document.getElementById('bulk-stat-updated'),
            statSkipped: document.getElementById('bulk-stat-skipped'),
            statFailed: document.getElementById('bulk-stat-failed'),
            log: document.getElementById('bulk-log'),
            logCopy: document.getElementById('bulk-log-copy'),
            cancelBtn: document.getElementById('bulk-cancel-btn'),
            downloadErrorsBtn: document.getElementById('bulk-download-errors-btn'),
            downloadErrorsCount: document.getElementById('bulk-download-errors-count'),
        };

        const i18n = cfg.i18n || {};

        const BADGE_CLASSES = {
            pending: 'bg-secondary-lt',
            processing: 'bg-info-lt',
            completed: 'bg-success-lt',
            failed: 'bg-danger-lt',
            cancelled: 'bg-warning-lt',
        };

        let pollTimer = null;
        let stopped = false;

        function updateStatusBadge(value, label) {
            if (!els.statusText) return;
            const cls = BADGE_CLASSES[value] || 'bg-secondary-lt';
            els.statusText.className = 'badge ' + cls;
            els.statusText.textContent = label || value || '';
        }

        function setText(el, value) {
            if (el && value !== null && value !== undefined) {
                el.textContent = value;
            }
        }

        function applyData(data) {
            if (!data) return;
            updateStatusBadge(data.status, data.status_label);
            setText(els.startedAt, data.started_at || '—');
            setText(els.finishedAt, data.finished_at || '—');
            setText(els.processedRows, data.processed_rows);
            setText(els.totalRows, data.total_rows);
            setText(els.progressPercent, data.progress_percent);
            setText(els.statTotal, data.total_rows);
            setText(els.statSuccess, data.success_count);
            setText(els.statUpdated, data.updated_count);
            setText(els.statSkipped, data.skipped_count);
            setText(els.statFailed, data.failed_count);

            if (els.progressBar) {
                const pct = Math.max(0, Math.min(100, parseInt(data.progress_percent || 0, 10)));
                els.progressBar.style.width = pct + '%';
                els.progressBar.setAttribute('aria-valuenow', String(pct));
            }
            if (els.log) {
                const atBottom = (els.log.scrollHeight - els.log.clientHeight - els.log.scrollTop) < 40;
                els.log.textContent = data.error_log || '';
                if (atBottom) {
                    els.log.scrollTop = els.log.scrollHeight;
                }
            }
            if (els.cancelBtn) {
                els.cancelBtn.classList.toggle('d-none', !!data.is_terminal);
            }

            // Reveal the "Download failed rows" button as soon as the importer
            // writes the first error row to disk. Hide it again if the file
            // got cleaned up (data.has_failed_rows_artifact will go false).
            if (els.downloadErrorsBtn) {
                const failedCount = parseInt(data.failed_count || 0, 10);
                const hasArtifact = !!data.has_failed_rows_artifact;
                const shouldShow = failedCount > 0 && hasArtifact;
                els.downloadErrorsBtn.classList.toggle('d-none', !shouldShow);
                if (els.downloadErrorsCount) {
                    els.downloadErrorsCount.textContent = String(failedCount);
                }

                // Keep the expiry tooltip in sync — the label switches from
                // "expires N days after completion" to a concrete date once
                // the job becomes terminal. Bootstrap caches the title at
                // tooltip-instance creation, so we ask the instance to refresh.
                const newLabel = data.failed_rows_expires_label || '';
                if (els.downloadErrorsBtn.getAttribute('data-bs-original-title') !== newLabel
                    && els.downloadErrorsBtn.getAttribute('data-bs-title') !== newLabel) {
                    els.downloadErrorsBtn.setAttribute('data-bs-title', newLabel);
                    if (window.bootstrap && window.bootstrap.Tooltip) {
                        const tip = window.bootstrap.Tooltip.getInstance(els.downloadErrorsBtn);
                        if (tip) {
                            tip.setContent({ '.tooltip-inner': newLabel });
                        }
                    }
                }
            }
        }

        function poll() {
            if (stopped) return;
            axios.get(cfg.statusUrl)
                .then(function (resp) {
                    const json = resp.data || {};
                    if (json && json.success && json.data) {
                        applyData(json.data);
                        if (json.data.is_terminal) {
                            stopped = true;
                            if (pollTimer) {
                                clearInterval(pollTimer);
                                pollTimer = null;
                            }
                        }
                    }
                })
                .catch(function () {
                    // Soft-fail: keep polling on transient errors.
                });
        }

        poll();
        pollTimer = setInterval(poll, parseInt(cfg.pollInterval, 10) || 2000);

        if (els.logCopy) {
            els.logCopy.addEventListener('click', function () {
                const text = els.log ? els.log.textContent : '';
                if (!navigator.clipboard) return;
                navigator.clipboard.writeText(text).then(function () {
                    const old = els.logCopy.textContent;
                    els.logCopy.textContent = '✓';
                    setTimeout(function () { els.logCopy.textContent = old; }, 1200);
                });
            });
        }

        if (els.cancelBtn && cfg.cancelUrl) {
            els.cancelBtn.addEventListener('click', function () {
                if (els.cancelBtn.disabled) return;
                Swal.fire({
                    title: i18n.cancel_title || 'Cancel',
                    text: i18n.cancel_confirm || 'Are you sure?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    confirmButtonText: i18n.cancel_yes || 'Yes, cancel it',
                    cancelButtonText: i18n.cancel_no || 'No',
                }).then(function (result) {
                    if (!result.isConfirmed) return;
                    els.cancelBtn.disabled = true;
                    axios.post(cfg.cancelUrl, {}, {
                        headers: { 'X-CSRF-TOKEN': csrfToken },
                    })
                        .then(function (resp) {
                            const json = resp.data || {};
                            if (json.success) {
                                Toast.fire({ icon: 'success', title: json.message || '' });
                                // Force-stop polling immediately and refresh once
                                // so the badge/CTA flips without waiting 2s.
                                poll();
                            } else {
                                Toast.fire({ icon: 'error', title: json.message || (i18n.unexpected_error || 'Error') });
                                els.cancelBtn.disabled = false;
                            }
                        })
                        .catch(function (err) {
                            const msg = (err && err.response && err.response.data && err.response.data.message)
                                || (i18n.unexpected_error || 'Error');
                            Toast.fire({ icon: 'error', title: msg });
                            els.cancelBtn.disabled = false;
                        });
                });
            });
        }

        // Hide the cancel button on first render for already-terminal jobs.
        if (els.cancelBtn && cfg.initialIsTerminal) {
            els.cancelBtn.classList.add('d-none');
        }
    });
})();
