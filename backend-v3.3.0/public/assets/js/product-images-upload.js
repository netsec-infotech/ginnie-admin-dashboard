(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('images-upload-form');
        var submitBtn = document.getElementById('images-upload-submit');
        if (!form || !submitBtn) return;

        var spinner = submitBtn.querySelector('.spinner-border');
        var uploadText = submitBtn.querySelector('.upload-text');
        var resultWrap = document.getElementById('images-upload-result');
        var successEl = document.getElementById('images-success');
        var failedEl = document.getElementById('images-failed');
        var failedTableWrap = document.getElementById('images-failed-table-wrap');
        var failedTbody = document.getElementById('images-failed-tbody');
        var progressWrap = document.getElementById('images-upload-progress');
        var progressBar = document.getElementById('images-progress-bar');
        var progressText = document.getElementById('images-progress-text');
        var warningWrap = document.getElementById('images-upload-warning');

        var LS_KEY = 'bulk_img_upload_token';
        var LS_PENDING = 'bulk_img_upload_pending';
        var pollTimer = null;
        var inFlight = false;

        function clearResults() {
            if (resultWrap) resultWrap.classList.add('d-none');
            if (failedEl) failedEl.classList.add('d-none');
            if (failedTableWrap) failedTableWrap.classList.add('d-none');
            if (failedTbody) failedTbody.innerHTML = '';
        }

        function updateProgress(total, processed) {
            if (!progressWrap || !progressBar) return;

            var pct = 0;
            if (total > 0) pct = Math.floor((processed / total) * 100);
            progressWrap.classList.remove('d-none');
            progressBar.style.width = pct + '%';
            progressBar.setAttribute('aria-valuenow', pct);
            if (progressText) progressText.textContent = pct + '%';
        }

        function stopPolling() {
            if (pollTimer) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
        }

        function finish(token) {
            try {
                localStorage.removeItem(LS_KEY);
            } catch (e) {}
            stopPolling();
            if (progressWrap) progressWrap.classList.add('d-none');
        }

        function renderFinal(json) {
            if (!json || !json.data) return;

            var d = json.data;
            if (resultWrap) resultWrap.classList.remove('d-none');
            var successMsg =
                (window.BULK_IMG_UPLOAD_CFG.i18n.upload_success_count || 'Successfully processed') +
                ': ' + (d.success_count || 0) + '. ' +
                (window.BULK_IMG_UPLOAD_CFG.i18n.upload_failed_count || 'Failed') +
                ': ' + (d.failed_count || 0) + '.';
            if (successEl) successEl.textContent = successMsg;

            if ((d.failed_rows || []).length) {
                if (failedEl) {
                    failedEl.classList.remove('d-none');
                    failedEl.textContent = window.BULK_IMG_UPLOAD_CFG.i18n.some_rows_failed || 'Some items failed';
                }
                if (failedTableWrap) failedTableWrap.classList.remove('d-none');
                d.failed_rows.forEach(function (r) {
                    if (!failedTbody) return;
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + (r.row || '') + '</td>' +
                        '<td>' + (r.title || '') + '</td>' +
                        '<td>' + (r.error || '') + '</td>';
                    failedTbody.appendChild(tr);
                });
            }
        }

        function pollStatus(token) {
            if (!token) return;

            var url = (window.BULK_IMG_UPLOAD_CFG.statusBaseUrl || '') + token;
            stopPolling();
            pollTimer = setInterval(function () {
                axios.get(url)
                    .then(function (resp) {
                        var json = resp.data;
                        if (!json || !json.success) return;

                        var d = json.data || {};
                        updateProgress(d.total || 0, d.processed || 0);
                        if (d.status === 'completed' || d.status === 'failed') {
                            finish(token);
                            renderFinal(json);
                        }
                    })
                    .catch(function () {});
            }, 2000);
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(form);

            // Keep a short-lived browser-side marker so a refresh can resume polling once a token exists.
            try {
                localStorage.setItem(LS_PENDING, '1');
            } catch (e) {}
            inFlight = true;
            window.onbeforeunload = function () {
                return 'Upload in progress. Are you sure you want to leave?';
            };
            submitBtn.disabled = true;
            if (spinner) spinner.classList.remove('d-none');
            if (uploadText) uploadText.classList.add('d-none');
            clearResults();

            axios.post(window.BULK_IMG_UPLOAD_CFG.uploadUrl, fd)
                .then(function (resp) {
                    var json = resp.data || {};
                    if (json && json.success && json.data && json.data.token) {
                        try {
                            localStorage.setItem(LS_KEY, json.data.token);
                        } catch (e) {}
                        inFlight = false;
                        try {
                            localStorage.removeItem(LS_PENDING);
                        } catch (e) {}
                        window.onbeforeunload = null;
                        pollStatus(json.data.token);
                    } else {
                        if (resultWrap) resultWrap.classList.remove('d-none');
                        if (failedEl) {
                            failedEl.classList.remove('d-none');
                            failedEl.textContent = json?.message || (window.BULK_IMG_UPLOAD_CFG.i18n.upload_failed_generic || 'Upload failed');
                        }
                    }
                })
                .catch(function () {
                    if (resultWrap) resultWrap.classList.remove('d-none');
                    if (failedEl) {
                        failedEl.classList.remove('d-none');
                        failedEl.textContent = window.BULK_IMG_UPLOAD_CFG.i18n.unexpected_error || 'Unexpected error occurred';
                    }
                })
                .finally(function () {
                    submitBtn.disabled = false;
                    if (spinner) spinner.classList.add('d-none');
                    if (uploadText) uploadText.classList.remove('d-none');
                });
        });

        try {
            var existingToken = localStorage.getItem(LS_KEY);
            if (existingToken) {
                pollStatus(existingToken);
            }

            var wasPending = localStorage.getItem(LS_PENDING);
            if (!existingToken && wasPending && warningWrap) {
                warningWrap.classList.remove('d-none');
                localStorage.removeItem(LS_PENDING);
            }
        } catch (e) {}

        window.addEventListener('pageshow', function () {
            if (!inFlight) window.onbeforeunload = null;
        });
    });
})();
