'use strict';

(function () {
    document.addEventListener('DOMContentLoaded', function () {
        const cfg = window.BULK_UPLOAD_CFG || {};
        const previewForm = document.getElementById('bulk-upload-preview-form');
        const confirmForm = document.getElementById('bulk-upload-confirm-form');
        if (!previewForm || !confirmForm) {
            return;
        }

        const previewSubmit = document.getElementById('bulk-upload-preview-submit');
        const previewSpinner = previewSubmit ? previewSubmit.querySelector('.spinner-border') : null;
        const previewLabel = previewSubmit ? previewSubmit.querySelector('.preview-text') : null;
        const previewTable = document.getElementById('bulk-upload-preview-table');
        const previewSummary = document.getElementById('bulk-upload-preview-summary');
        const previewTokenInput = document.getElementById('preview-token');
        const originalFilenameInput = document.getElementById('original-filename');
        const mappingRowsEl = document.getElementById('mapping-rows');
        const step1 = document.getElementById('bulk-upload-step1');
        const step2 = document.getElementById('bulk-upload-step2');
        const confirmSubmit = document.getElementById('bulk-upload-confirm-submit');
        const confirmSpinner = confirmSubmit ? confirmSubmit.querySelector('.spinner-border') : null;
        const confirmLabel = confirmSubmit ? confirmSubmit.querySelector('.confirm-text') : null;
        const resetBtn = document.getElementById('bulk-upload-reset');
        const operationSelect = document.getElementById('bulk-operation-select');
        const matchByRow = document.getElementById('bulk-match-by-row');
        const matchBySelect = document.getElementById('bulk-match-by-select');

        const targetLocaleRow = document.getElementById('bulk-target-locale-row');
        const targetLocaleSelect = document.getElementById('bulk-target-locale-select');

        let csvHeaders = [];

        function escapeHtml(str) {
            return String(str ?? '').replace(/[&<>"']/g, function (c) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
            });
        }

        function toggleBusy(button, spinner, label, busy) {
            if (!button) return;
            button.disabled = busy;
            if (spinner) spinner.classList.toggle('d-none', !busy);
            if (label) label.classList.toggle('d-none', busy);
        }

        function toast(success, message) {
            if (window.Toast && typeof window.Toast.fire === 'function') {
                window.Toast.fire({ icon: success ? 'success' : 'error', title: message });
            } else if (window.Swal) {
                window.Swal.fire({ icon: success ? 'success' : 'error', title: message, timer: 2000, showConfirmButton: false });
            } else {
                alert(message);
            }
        }

        function renderPreview(headers, rows, totalRows, originalFilename) {
            csvHeaders = headers.slice();
            const thead = previewTable.querySelector('thead');
            const tbody = previewTable.querySelector('tbody');
            thead.innerHTML = '<tr>' +
                '<th>#</th>' +
                headers.map(h => '<th>' + escapeHtml(h) + '</th>').join('') +
                '</tr>';
            tbody.innerHTML = rows.map((row, i) => {
                const cells = headers.map((_, idx) => '<td>' + escapeHtml(row[idx] ?? '') + '</td>').join('');
                return '<tr><td class="text-muted">' + (i + 1) + '</td>' + cells + '</tr>';
            }).join('');

            const shown = rows.length;
            const truncated = shown < totalRows;
            const tpl = truncated
                ? (cfg.i18n && cfg.i18n.preview_showing) || 'Showing first :shown of :total rows'
                : (cfg.i18n && cfg.i18n.preview_total) || 'Total rows: :total';
            previewSummary.textContent = tpl
                .replace(':shown', shown)
                .replace(':total', totalRows) + ' — ' + originalFilename;
            originalFilenameInput.value = originalFilename;
        }

        function isUpdateMode() {
            return operationSelect && operationSelect.value === 'update';
        }

        function isTranslationMode() {
            return operationSelect && operationSelect.value === 'translation';
        }

        function isMatchRequiredMode() {
            return isUpdateMode() || isTranslationMode();
        }

        function renderMappingRows() {
            const allFields = Array.isArray(cfg.availableFields) ? cfg.availableFields : [];
            let fields = [];

            if (isTranslationMode()) {
                const targetLocale = (targetLocaleSelect ? targetLocaleSelect.value : '') || '';
                // In translation mode, only show fields belonging to the selected target language
                fields = allFields.filter(function (f) {
                    if (f.locale) {
                        return f.locale === targetLocale;
                    }
                    return String(f.key).endsWith('_' + targetLocale);
                }).map(function (f) {
                    const isTitle = String(f.key).startsWith('title_') || String(f.key).startsWith('name_') || f.key === 'title' || f.key === 'name';
                    return Object.assign({}, f, { required: isTitle });
                });
            } else {
                // In Create / Update mode: show all fields (with title required only in create mode)
                fields = allFields.map(function (f) {
                    if (f.key === 'title' || f.key === 'name') {
                        return Object.assign({}, f, { required: !isUpdateMode() });
                    }
                    return f;
                });
            }

            const optionsHtml = '<option value="">' + escapeHtml((cfg.i18n && cfg.i18n.pick_column) || '--') + '</option>'
                + csvHeaders.map(h => '<option value="' + escapeHtml(h) + '">' + escapeHtml(h) + '</option>').join('');

            mappingRowsEl.innerHTML = fields.map(field => {
                const required = field.required ? '<span class="text-danger">*</span>' : '';
                // Auto-pick a CSV header that matches the field key (case-insensitive) or base name
                const guess = csvHeaders.find(h => String(h).toLowerCase() === String(field.key).toLowerCase())
                    || (field.locale && csvHeaders.find(h => String(h).toLowerCase() === String(field.key.replace('_' + field.locale, '')).toLowerCase()))
                    || '';
                const selectedHtml = optionsHtml.replace(
                    'value="' + escapeHtml(guess) + '"',
                    'value="' + escapeHtml(guess) + '" selected'
                );
                return ''
                    + '<div class="mb-2 row align-items-center">'
                    + '  <label class="col-sm-5 col-form-label">' + escapeHtml(field.label) + ' ' + required + '</label>'
                    + '  <div class="col-sm-7">'
                    + '    <select class="form-select form-select-sm" name="mapping[' + escapeHtml(field.key) + ']" data-required="' + (field.required ? '1' : '0') + '">'
                    +        (guess ? selectedHtml : optionsHtml)
                    + '    </select>'
                    + '  </div>'
                    + '</div>';
            }).join('');
        }

        previewForm.addEventListener('submit', function (e) {
            e.preventDefault();
            toggleBusy(previewSubmit, previewSpinner, previewLabel, true);

            const fd = new FormData(previewForm);
            axios.post(cfg.previewUrl, fd, {
                headers: { 'X-CSRF-TOKEN': cfg.csrf },
            })
                .then(function (resp) {
                    const json = resp.data || {};
                    if (json && json.success && json.data) {
                        renderPreview(json.data.headers || [], json.data.rows || [], json.data.total_rows || 0, json.data.original_filename || '');
                        previewTokenInput.value = json.data.preview_token || '';
                        step2.classList.remove('d-none');
                        refreshModeUI();           // toggles match-by row, re-renders mapping rows & injects match-field row
                        step2.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    } else {
                        const msg = (json && json.message) || (cfg.i18n && cfg.i18n.upload_failed) || 'Upload failed';
                        toast(false, msg);
                    }
                })
                .catch(function (err) {
                    const msg = (err && err.response && err.response.data && err.response.data.message) || (cfg.i18n && cfg.i18n.unexpected_error) || 'Unexpected error';
                    toast(false, msg);
                })
                .finally(function () {
                    toggleBusy(previewSubmit, previewSpinner, previewLabel, false);
                });
        });

        function refreshModeUI() {
            if (matchByRow) {
                matchByRow.classList.toggle('d-none', !isMatchRequiredMode());
            }
            if (targetLocaleRow) {
                targetLocaleRow.classList.toggle('d-none', !isTranslationMode());
            }
            if (isTranslationMode() && matchBySelect) {
                const uuidOpt = matchBySelect.querySelector('option[value="uuid"]');
                if (uuidOpt && matchBySelect.value !== 'uuid') {
                    matchBySelect.value = 'uuid';
                }
            }
            renderMappingRows();
            refreshMatchFieldRow();
        }

        /**
         * In Update/Translation mode, the admin needs to map a CSV column to the chosen
         * match field (uuid, id, title, etc.) — but importer.availableFields() only
         * lists *writable* fields, so there's no mapping row for `id`/`uuid`. We
         * inject one dynamically at the top of #mapping-rows and swap it when
         * the Match-by select changes.
         */
        function refreshMatchFieldRow() {
            const existing = document.getElementById('mapping-match-field-row');
            if (existing) existing.remove();
            if (!isMatchRequiredMode() || !matchBySelect) return;

            const matchKey = matchBySelect.value;
            if (!matchKey) return;

            // In Translation Mode, always ensure the match field row is present at the top
            if (!isTranslationMode()) {
                const inAvailable = (cfg.availableFields || []).some(f => f.key === matchKey);
                if (inAvailable) return;
            }

            const optionsHtml = '<option value="">'
                + escapeHtml((cfg.i18n && cfg.i18n.pick_column) || '--')
                + '</option>'
                + csvHeaders.map(h => '<option value="' + escapeHtml(h) + '">' + escapeHtml(h) + '</option>').join('');

            // Auto-pick a CSV header matching the key (case-insensitive).
            const guess = csvHeaders.find(h => String(h).toLowerCase() === String(matchKey).toLowerCase()) || '';
            const selectHtml = guess
                ? optionsHtml.replace(
                    'value="' + escapeHtml(guess) + '"',
                    'value="' + escapeHtml(guess) + '" selected'
                )
                : optionsHtml;

            const matchFieldMeta = (cfg.availableMatchFields || []).find(f => f.key === matchKey);
            const label = matchFieldMeta ? matchFieldMeta.label : matchKey;

            const row = document.createElement('div');
            row.id = 'mapping-match-field-row';
            row.className = 'mb-2 row align-items-center bg-primary-lt rounded p-2';
            row.innerHTML =
                '<label class="col-sm-5 col-form-label">'
                + escapeHtml(label)
                + ' <span class="text-danger">*</span>'
                + '<div class="small text-muted">' + escapeHtml((cfg.i18n && cfg.i18n.match_key_hint) || 'match key') + '</div>'
                + '</label>'
                + '<div class="col-sm-7">'
                + '<select class="form-select form-select-sm" name="mapping[' + escapeHtml(matchKey) + ']" data-match-field="1">'
                + selectHtml
                + '</select>'
                + '</div>';

            mappingRowsEl.insertBefore(row, mappingRowsEl.firstChild);
        }

        if (operationSelect) {
            operationSelect.addEventListener('change', refreshModeUI);
        }
        if (targetLocaleSelect) {
            targetLocaleSelect.addEventListener('change', function () {
                if (isTranslationMode()) {
                    renderMappingRows();
                    refreshMatchFieldRow();
                }
            });
        }
        if (matchBySelect) {
            matchBySelect.addEventListener('change', refreshMatchFieldRow);
        }

        confirmForm.addEventListener('submit', function (e) {
            e.preventDefault();

            // Client-side validation:
            if (isTranslationMode()) {
                const matchKey = matchBySelect ? matchBySelect.value : 'uuid';
                const matchSelect = confirmForm.querySelector('select[name="mapping[' + matchKey + ']"]');
                if (matchSelect && !matchSelect.value) {
                    toast(false, (cfg.i18n && cfg.i18n.map_required) || 'Map the match field column.');
                    matchSelect.focus();
                    return;
                }

                const targetLocale = (targetLocaleSelect ? targetLocaleSelect.value : '') || '';
                const allFields = Array.isArray(cfg.availableFields) ? cfg.availableFields : [];
                const requiredTargetField = allFields.find(function (f) {
                    const matchesLocale = f.locale ? f.locale === targetLocale : String(f.key).endsWith('_' + targetLocale);
                    const isTitle = String(f.key).startsWith('title_') || String(f.key).startsWith('name_') || f.key === 'title' || f.key === 'name';
                    return matchesLocale && isTitle;
                });

                if (requiredTargetField) {
                    const targetSelect = confirmForm.querySelector('select[name="mapping[' + requiredTargetField.key + ']"]');
                    if (targetSelect && !targetSelect.value) {
                        toast(false, (cfg.i18n && cfg.i18n.map_required) || 'Map the target language title field.');
                        targetSelect.focus();
                        return;
                    }
                }
            } else if (isUpdateMode()) {
                const matchKey = matchBySelect ? matchBySelect.value : 'id';
                const matchSelect = confirmForm.querySelector('select[name="mapping[' + matchKey + ']"]');
                if (matchSelect && !matchSelect.value) {
                    toast(false, (cfg.i18n && cfg.i18n.map_required) || 'Map the match field column.');
                    matchSelect.focus();
                    return;
                }
            } else {
                const titleSelect = confirmForm.querySelector('select[name="mapping[title]"]')
                    || confirmForm.querySelector('select[name="mapping[name]"]');
                if (titleSelect && !titleSelect.value) {
                    toast(false, (cfg.i18n && cfg.i18n.map_required) || 'Map the title field.');
                    titleSelect.focus();
                    return;
                }
            }

            toggleBusy(confirmSubmit, confirmSpinner, confirmLabel, true);

            const fd = new FormData(confirmForm);
            axios.post(cfg.confirmUrl, fd, {
                headers: { 'X-CSRF-TOKEN': cfg.csrf },
            })
                .then(function (resp) {
                    const json = resp.data || {};
                    if (json && json.success) {
                        toast(true, (cfg.i18n && cfg.i18n.queued) || 'Upload queued');
                        const redirect = (json.data && json.data.redirect_url) || cfg.indexUrl;
                        setTimeout(function () {
                            window.location.href = redirect;
                        }, 1500);
                    } else {
                        toast(false, (json && json.message) || (cfg.i18n && cfg.i18n.upload_failed) || 'Upload failed');
                    }
                })
                .catch(function (err) {
                    const msg = (err && err.response && err.response.data && err.response.data.message) || (cfg.i18n && cfg.i18n.unexpected_error) || 'Unexpected error';
                    toast(false, msg);
                })
                .finally(function () {
                    toggleBusy(confirmSubmit, confirmSpinner, confirmLabel, false);
                });
        });

        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                step2.classList.add('d-none');
                previewForm.reset();
                step1.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        }
    });
})();
