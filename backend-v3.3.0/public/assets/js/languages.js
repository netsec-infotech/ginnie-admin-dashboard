'use strict';

$(document).ready(function () {
    const labels = window.languageLabels || {};
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const detailsForm = document.querySelector('[data-language-details-form]');
    const translationForm = document.querySelector('[data-language-translation-form]');
    const languagePack = document.querySelector('[data-language-pack]');
    const detailFields = () => detailsForm
        ? Array.from(detailsForm.querySelectorAll('input:not([type="hidden"]), select, textarea'))
        : [];
    const translationFields = () => translationForm
        ? Array.from(translationForm.querySelectorAll('[data-translation-key]'))
        : [];
    const detailFieldValue = (field) => ['checkbox', 'radio'].includes(field.type) ? field.checked : field.value;
    let detailValues = new Map(detailFields().map((field) => [field, detailFieldValue(field)]));
    let translationSubmitting = false;
    let allowNextTranslationRequest = false;
    const snapshotDetails = () => {
        detailValues = new Map(detailFields().map((field) => [field, detailFieldValue(field)]));
    };
    const snapshotTranslations = () => {
        translationFields().forEach((field) => {
            field.defaultValue = field.value;
        });
    };
    const hasUnsavedDetails = () => detailFields().some((field) => detailFieldValue(field) !== detailValues.get(field));
    const hasUnsavedTranslations = () => translationFields().some((field) => field.value !== field.defaultValue);
    const hasUnsavedChanges = () => hasUnsavedDetails() || hasUnsavedTranslations();
    const snapshotAll = () => {
        snapshotDetails();
        snapshotTranslations();
    };
    const confirmNavigation = (navigate) => {
        if (typeof Swal === 'undefined') {
            if (typeof Toast !== 'undefined') {
                Toast.fire({icon: 'error', title: labels.unsavedWarning});
            }
            return;
        }

        Swal.fire({
            title: labels.unsavedChanges,
            text: labels.unsavedWarning,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: primaryColor,
            confirmButtonText: labels.leaveWithoutSaving,
            cancelButtonText: labels.stayOnPage,
        }).then((result) => {
            if (result.isConfirmed) {
                snapshotAll();
                navigate();
            }
        });
    };

    if (languagePack && typeof FilePond !== 'undefined' && !FilePond.find(languagePack)) {
        FilePond.create(languagePack, {
            allowImagePreview: false,
            credits: false,
            storeAsFile: true,
            acceptedFileTypes: ['application/json', 'text/plain'],
            maxFileSize: '5MB',
        });
    }

    const countrySelectElement = document.getElementById('language-country-code');
    if (countrySelectElement && typeof TomSelect !== 'undefined' && !countrySelectElement.tomselect) {
        new TomSelect(countrySelectElement, {
            create: false,
            maxItems: 1,
            placeholder: countrySelectElement.getAttribute('placeholder') || 'Select country',
            render: {
                option: function (data, escape) {
                    const code = data.value ? escape(data.value.toLowerCase()) : '';
                    const flagHtml = code ? `<span class="flag flag-xs flag-country-${code} me-2 align-middle"></span>` : '';
                    return `<div class="d-flex align-items-center">${flagHtml}<span>${escape(data.text)}</span></div>`;
                },
                item: function (data, escape) {
                    const code = data.value ? escape(data.value.toLowerCase()) : '';
                    const flagHtml = code ? `<span class="flag flag-xs flag-country-${code} me-2 align-middle"></span>` : '';
                    return `<div class="d-flex align-items-center">${flagHtml}<span>${escape(data.text)}</span></div>`;
                }
            }
        });
    }

    const languageCodeInput = document.getElementById('language-code');
    const languageCountryMap = {
        'en': 'us',
        'en-us': 'us',
        'en-gb': 'gb',
        'es': 'es',
        'es-es': 'es',
        'es-mx': 'mx',
        'fr': 'fr',
        'de': 'de',
        'zh': 'cn',
        'zh-cn': 'cn',
        'zh-tw': 'tw',
        'hi': 'in',
        'ar': 'sa',
        'gu': 'in',
        'te': 'in',
        'ta': 'in',
        'mr': 'in',
        'bn': 'in',
        'pa': 'in',
        'kn': 'in',
        'ml': 'in',
        'ur': 'pk',
        'pt': 'br',
        'pt-br': 'br',
        'pt-pt': 'pt',
        'ja': 'jp',
        'ko': 'kr',
        'ru': 'ru',
        'it': 'it',
        'tr': 'tr',
        'id': 'id',
        'nl': 'nl',
        'vi': 'vn',
        'th': 'th',
        'pl': 'pl',
        'uk': 'ua',
        'el': 'gr',
        'cs': 'cz',
        'sv': 'se',
        'da': 'dk',
        'fi': 'fi',
        'no': 'no',
        'he': 'il',
        'fa': 'ir',
    };

    if (languageCodeInput && countrySelectElement) {
        let manuallyChangedCountry = Boolean(countrySelectElement.value);
        if (countrySelectElement.tomselect) {
            countrySelectElement.tomselect.on('change', function (val) {
                if (val) {
                    manuallyChangedCountry = true;
                }
            });
        } else {
            countrySelectElement.addEventListener('change', function () {
                if (countrySelectElement.value) {
                    manuallyChangedCountry = true;
                }
            });
        }

        const autoDetectCountry = function () {
            const rawCode = (languageCodeInput.value || '').trim().toLowerCase();
            if (!rawCode) {
                return;
            }

            const detectedCountry = languageCountryMap[rawCode] || languageCountryMap[rawCode.split('-')[0]] || null;
            if (detectedCountry && (!countrySelectElement.value || !manuallyChangedCountry)) {
                if (countrySelectElement.tomselect) {
                    countrySelectElement.tomselect.setValue(detectedCountry, false);
                } else {
                    countrySelectElement.value = detectedCountry;
                }
            }
        };

        languageCodeInput.addEventListener('input', autoDetectCountry);
        languageCodeInput.addEventListener('change', autoDetectCountry);
    }

    const languagesTableElement = document.getElementById('languages-table');
    const languageStatusFilter = $('#language-status-filter');
    if (languagesTableElement && $.fn.DataTable.isDataTable(languagesTableElement)) {
        const languagesTable = $('#languages-table').DataTable();
        languageStatusFilter.on('change', function () {
            languagesTable.ajax.reload(null, false);
        });
        $('#languages-table').on('preXhr.dt', function (event, settings, data) {
            data.status = languageStatusFilter.val() || '';
        });
    }

    if (translationForm) {
        const tableElement = document.getElementById('language-translations-table');
        const table = tableElement && $.fn.DataTable.isDataTable(tableElement) ? $('#language-translations-table').DataTable() : null;
        const translationStatusFilter = $('#translation-status');
        const translationKeySearch = $('#translation-key-search');
        const oldValues = {...(window.languageOldTranslations || {})};
        let translationKeySearchTimer;

        if (table) {
            $(tableElement).on('preXhr.dt', function (event, settings, data) {
                data.status = translationStatusFilter.val() || '';
                data.key = translationKeySearch.val() || '';
                if (allowNextTranslationRequest || translationSubmitting || !hasUnsavedTranslations()) {
                    allowNextTranslationRequest = false;
                    return;
                }

                event.preventDefault();
                table.processing(false);
                confirmNavigation(() => {
                    allowNextTranslationRequest = true;
                    table.ajax.reload(null, false);
                });

                return false;
            });

            $(tableElement).on('xhr.dt', function (event, settings, json) {
                if (json) {
                    $('[data-translated-count]').text(json.translatedCount);
                    $('[data-missing-count]').text(json.missingCount);
                    $('[data-source-count]').text(json.sourceCount);
                }
            });

            $(tableElement).on('draw.dt', function () {
                translationFields().forEach((field) => {
                    if (Object.prototype.hasOwnProperty.call(oldValues, field.dataset.translationKey)) {
                        field.value = oldValues[field.dataset.translationKey];
                        delete oldValues[field.dataset.translationKey];
                    }
                });
                snapshotTranslations();
            });

            translationStatusFilter.on('change', function () {
                table.ajax.reload(null, false);
            });

            translationKeySearch.on('input', function () {
                window.clearTimeout(translationKeySearchTimer);
                translationKeySearchTimer = window.setTimeout(function () {
                    table.ajax.reload();
                }, 300);
            });
        }

        translationForm.addEventListener('submit', function () {
            translationSubmitting = true;
        }, true);

        document.addEventListener('form-submit:success', function (event) {
            if (event.target !== translationForm) {
                return;
            }

            snapshotTranslations();
            translationSubmitting = false;
            if (table) {
                allowNextTranslationRequest = true;
                table.ajax.reload(null, false);
            }
        });

        document.addEventListener('form-submit:error', function (event) {
            if (event.target === translationForm) {
                translationSubmitting = false;
            }
        });
    }

    document.addEventListener('form-submit:success', function (event) {
        if (event.target === detailsForm) {
            snapshotDetails();
        }
    });

    document.addEventListener('click', function (event) {
        const link = event.target.closest('a[href]');
        const href = link?.getAttribute('href') || '';
        if (!link || href.startsWith('#') || href.startsWith('javascript:') || link.target === '_blank' || link.hasAttribute('data-language-download') || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || !hasUnsavedChanges()) {
            return;
        }

        event.preventDefault();
        confirmNavigation(() => {
            window.location.href = link.href;
        });
    });

    document.addEventListener('submit', function (event) {
        if (event.target === translationForm || (event.target === detailsForm && !hasUnsavedTranslations()) || !hasUnsavedChanges()) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        confirmNavigation(() => event.target.requestSubmit());
    }, true);

    document.addEventListener('click', function (event) {
        const defaultButton = event.target.closest('.default-language');
        if (defaultButton) {
            const originalContent = defaultButton.innerHTML;
            defaultButton.disabled = true;
            defaultButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

            axios.post(defaultButton.dataset.url, {}, {
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }).then((response) => {
                if (!response.data.success) {
                    throw new Error(response.data.message || labels.requestFailed);
                }

                $('#languages-table').DataTable().ajax.reload(null, false);
                Toast.fire({icon: 'success', title: response.data.message});
            }).catch((error) => {
                Toast.fire({
                    icon: 'error',
                    title: error.response?.data?.message || error.message || labels.requestFailed,
                });
            }).finally(() => {
                defaultButton.disabled = false;
                defaultButton.innerHTML = originalContent;
            });

            return;
        }

        const deleteButton = event.target.closest('.delete-language');
        if (deleteButton) {
            if (typeof Swal === 'undefined') {
                Toast.fire({icon: 'error', title: labels.requestFailed});
                return;
            }

            Swal.fire({
                title: labels.areYouSure,
                html: labels.deleteConfirm,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: primaryColor,
                confirmButtonText: labels.delete,
                cancelButtonText: labels.cancel,
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                deleteButton.disabled = true;
                axios.delete(deleteButton.dataset.url, {
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                }).then((response) => {
                    if (!response.data.success) {
                        throw new Error(response.data.message || labels.requestFailed);
                    }

                    $('#languages-table').DataTable().ajax.reload(null, false);
                    Swal.fire(labels.success, response.data.message, 'success');
                }).catch((error) => {
                    Toast.fire({
                        icon: 'error',
                        title: error.response?.data?.message || error.message || labels.requestFailed,
                    });
                }).finally(() => {
                    deleteButton.disabled = false;
                });
            });

            return;
        }

        const button = event.target.closest('.toggle-language');
        if (!button) {
            return;
        }

        const submit = () => {
            const originalContent = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

            axios.post(button.dataset.url, {}, {
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }).then((response) => {
                if (!response.data.success) {
                    throw new Error(response.data.message || labels.requestFailed);
                }

                $('#languages-table').DataTable().ajax.reload(null, false);
                Toast.fire({icon: 'success', title: response.data.message});
            }).catch((error) => {
                Toast.fire({
                    icon: 'error',
                    title: error.response?.data?.message || error.message || labels.requestFailed,
                });
            }).finally(() => {
                button.disabled = false;
                button.innerHTML = originalContent;
            });
        };

        if (button.dataset.active !== '1') {
            submit();
            return;
        }

        if (typeof Swal === 'undefined') {
            Toast.fire({icon: 'error', title: labels.requestFailed});
            return;
        }

        Swal.fire({
            title: labels.areYouSure,
            text: labels.confirmDeactivate.replace(':language', button.dataset.name),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: primaryColor,
            confirmButtonText: labels.deactivate,
            cancelButtonText: labels.cancel,
        }).then((result) => {
            if (result.isConfirmed) {
                submit();
            }
        });
    });
});

