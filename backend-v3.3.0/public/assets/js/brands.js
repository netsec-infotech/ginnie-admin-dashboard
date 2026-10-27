document.addEventListener('show.bs.modal', function (event) {
    if (event.target.id === 'brand-modal') {
        const triggerButton = event.relatedTarget;
        const brandId = triggerButton ? triggerButton.getAttribute('data-id') : null;
        let url = `${base_url}/${panel}/brands/${brandId}/edit`;

        const form = document.querySelector('#brand-modal .form-submit');
        const brandUpload = document.querySelector('#logo-upload');
        const modalTitle = document.querySelector('#brand-modal .modal-title');
        const submitButton = document.querySelector('#brand-modal button[type="submit"]');
        const selectCategory = document.getElementById("select-root-category");
        const tomSelectScope = selectCategory && selectCategory.tomselect; // TomSelect instance
        const visibilityInspector = document.querySelector('#brand-modal [data-visibility-inspector]');

        if (brandId) {
            const brandIdInput = form.querySelector('#brand-id');
            if (brandIdInput) brandIdInput.value = brandId;
            // Fetch the visibility inspection in parallel with the brand data fetch.
            if (visibilityInspector) {
                visibilityInspector.dataset.endpoint = `${base_url}/${panel}/brands/${brandId}/visibility`;
                if (typeof window.loadVisibilityInspector === 'function') {
                    window.loadVisibilityInspector(visibilityInspector);
                }
            }

            // Fetch brand data
            fetch(url, { method: 'GET' })
                .then(response => response.json())
                .then(responseData => {
                    const data = responseData.data;
                    const translations = data.translations || {};

                    // Fill localized title inputs
                    const titleInputs = form.querySelectorAll('.brand-title-input');
                    if (titleInputs.length > 0) {
                        titleInputs.forEach(input => {
                            const loc = input.getAttribute('data-locale');
                            if (translations.title && translations.title[loc] !== undefined) {
                                input.value = translations.title[loc] || '';
                            } else {
                                input.value = '';
                            }
                        });
                    } else {
                        const legacyTitle = form.querySelector('input[name="title"]');
                        if (legacyTitle) legacyTitle.value = data.title || '';
                    }

                    // Fill localized description inputs
                    const descInputs = form.querySelectorAll('.brand-desc-input');
                    if (descInputs.length > 0) {
                        descInputs.forEach(textarea => {
                            const loc = textarea.getAttribute('data-locale');
                            if (translations.description && translations.description[loc] !== undefined) {
                                textarea.value = translations.description[loc] || '';
                            } else {
                                textarea.value = '';
                            }
                        });
                    } else {
                        const legacyDesc = form.querySelector('textarea[name="description"]');
                        if (legacyDesc) legacyDesc.value = data.description || '';
                    }

                    form.querySelector('input[name="status"]').checked = data.status === 'active';
                    const seo = data.metadata || {};
                    form.querySelector('input[name="metadata[seo_title]"]').value = seo.seo_title || '';
                    form.querySelector('textarea[name="metadata[seo_description]"]').value = seo.seo_description || '';
                    const keywordsSelect = form.querySelector('select.seo-keywords-select');
                    const keywordsTs = keywordsSelect && keywordsSelect.tomselect;
                    if (keywordsTs) {
                        keywordsTs.clear();
                        keywordsTs.clearOptions();
                        const kws = Array.isArray(seo.seo_keywords)
                            ? seo.seo_keywords
                            : (typeof seo.seo_keywords === 'string' && seo.seo_keywords.length
                                ? seo.seo_keywords.split(',').map(k => k.trim()).filter(Boolean)
                                : []);
                        kws.forEach((kw) => keywordsTs.addOption({ value: kw, text: kw }));
                        keywordsTs.setValue(kws);
                    }
                    form.querySelector('select[name="scope_type"]').value = data.scope_type ?? 'global';
                    if (data.scope_type === 'category') {
                        form.querySelector('#scopeCategoryField').style.display = 'block';
                        // Handle scope selection
                        tomSelectScope?.addOption({
                            value: data.scope_id,
                            text: data.scope_category_title,
                        });
                        tomSelectScope?.setValue(data.scope_id);
                    } else {
                        form.querySelector('#scopeCategoryField').style.display = 'none';

                        tomSelectScope?.clearOptions();
                        tomSelectScope?.clear();
                    }
                    if (typeof FilePond !== 'undefined') {
                        if (data.logo !== null && data.logo !== "" && data.logo !== undefined && brandUpload) {
                            const pond = FilePond.find(brandUpload);
                            if (pond) {
                                pond.removeFiles();
                                pond.addFile(data.logo);
                            }
                        }
                    }

                    // Change form action to update route
                    form.setAttribute('action', `${base_url}/${panel}/brands/${brandId}`);

                    // Update modal title and button
                    modalTitle.textContent = 'Edit Brand';
                    submitButton.textContent = 'Update Brand';
                })
                .catch(error => {
                    console.error('AJAX Error:', error);
                });
        } else {
            // New brand mode
            if (form) form.reset();
            const brandIdInput = form.querySelector('#brand-id');
            if (brandIdInput) brandIdInput.value = '';
            // Hide inspector — there's nothing to inspect until the brand is saved.
            if (visibilityInspector) visibilityInspector.hidden = true;
            // Remove _method input if it exists
            const methodInput = form.querySelector('input[name="_method"]');
            if (methodInput) methodInput.parentNode.removeChild(methodInput);

            // Clear localized inputs
            form.querySelectorAll('.brand-title-input').forEach(i => i.value = '');
            form.querySelectorAll('.brand-desc-input').forEach(t => t.value = '');

            // Activate first language tab
            const firstTabBtn = document.querySelector('#brand-language-tabs .nav-link');
            if (firstTabBtn && typeof bootstrap !== 'undefined' && bootstrap.Tab) {
                const tab = new bootstrap.Tab(firstTabBtn);
                tab.show();
            }

            // Remove files from FilePond if available
            if (typeof FilePond !== 'undefined') {
                const pond = FilePond.find(brandUpload);
                if (pond) pond.removeFiles();
            }
            form.querySelector('#scopeCategoryField').style.display = 'none';
            tomSelectScope?.clearOptions();
            tomSelectScope?.clear();
            const keywordsSelect = form.querySelector('select.seo-keywords-select');
            if (keywordsSelect && keywordsSelect.tomselect) {
                keywordsSelect.tomselect.clear();
                keywordsSelect.tomselect.clearOptions();
            }
            // Set action for create
            form.setAttribute('action', `${base_url}/${panel}/brands`);
            modalTitle.textContent = 'Create Brand';
            submitButton.textContent = 'Create new Brand';
        }
    }
});

document.addEventListener('click', function (event) {
    const aiBtn = event.target.closest('#brand-ai-translate-btn');
    if (!aiBtn) return;

    const modal = document.querySelector('#brand-modal');
    if (!modal) return;

    const sourceTitleInput = modal.querySelector('.brand-title-input[data-locale="en"]');
    const sourceDescInput = modal.querySelector('.brand-desc-input[data-locale="en"]');
    const sourceTitle = (sourceTitleInput ? sourceTitleInput.value : '').trim();
    const sourceDesc = (sourceDescInput ? sourceDescInput.value : '').trim();

    if (!sourceTitle && !sourceDesc) {
        if (typeof toastr !== 'undefined') {
            toastr.warning(aiBtn.dataset.sourceRequired);
        } else {
            alert(aiBtn.dataset.sourceRequired);
        }
        return;
    }

    const btnText = aiBtn.querySelector('.btn-text');
    const spinner = aiBtn.querySelector('.spinner-border');
    aiBtn.disabled = true;
    if (btnText) btnText.textContent = aiBtn.dataset.translating;
    if (spinner) spinner.classList.remove('d-none');

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    fetch(`${base_url}/${panel}/translations/ai-translate`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            brand_id: modal.querySelector('#brand-id')?.value || null,
            fields: {
                title: sourceTitle,
                description: sourceDesc
            }
        })
    })
    .then(res => res.json())
    .then(response => {
        if (response.success && response.data?.translations) {
            const translations = response.data.translations;
            Object.keys(translations).forEach(loc => {
                const targetTitleInput = modal.querySelector(`.brand-title-input[data-locale="${loc}"]`);
                const targetDescInput = modal.querySelector(`.brand-desc-input[data-locale="${loc}"]`);

                if (targetTitleInput && translations[loc].title && !targetTitleInput.value.trim()) {
                    targetTitleInput.value = translations[loc].title;
                }
                if (targetDescInput && translations[loc].description && !targetDescInput.value.trim()) {
                    targetDescInput.value = translations[loc].description;
                }
            });

            if (typeof toastr !== 'undefined') {
                toastr.success(aiBtn.dataset.success);
            }
        } else {
            const msg = response.message || aiBtn.dataset.failed;
            if (typeof toastr !== 'undefined') {
                toastr.error(msg);
            } else {
                alert(msg);
            }
        }
    })
    .catch(err => {
        console.error('AI Translation error:', err);
        if (typeof toastr !== 'undefined') {
            toastr.error(aiBtn.dataset.failed);
        }
    })
    .finally(() => {
        aiBtn.disabled = false;
        if (btnText) btnText.textContent = aiBtn.dataset.idle;
        if (spinner) spinner.classList.add('d-none');
    });
});

document.addEventListener('form-submit:success', function (event) {
    const modal = event.target && event.target.closest('#brand-modal');
    if (!modal) return;

    const data = event.detail?.data;
    const brand = data?.data;

    if (brand && brand.id && brand.title) {
        const selectBrandEl = document.getElementById('select-brand');
        if (selectBrandEl) {
            const brandId = String(brand.id);
            const brandTitle = brand.title;
            const brandLogo = brand.logo || '';

            if (selectBrandEl.tomselect) {
                const ts = selectBrandEl.tomselect;
                const optionData = {
                    value: brandId,
                    text: brandTitle,
                    id: brandId,
                    customProperties: brandLogo ? `<img class="avatar avatar-xs me-2" src="${brandLogo}" alt="${brandTitle}">` : ''
                };
                ts.addOption(optionData);
                ts.setValue(brandId);
                ts.refreshOptions(false);
            } else {
                let existingOption = selectBrandEl.querySelector(`option[value="${brandId}"]`);
                if (!existingOption) {
                    existingOption = new Option(brandTitle, brandId, true, true);
                    selectBrandEl.add(existingOption);
                }
                selectBrandEl.value = brandId;
                selectBrandEl.dispatchEvent(new Event('change', { bubbles: true }));
            }

            const selectedBrandHidden = document.getElementById('selected-brand');
            if (selectedBrandHidden) {
                selectedBrandHidden.value = brandId;
            }
        }
    }
});

document.addEventListener('click', function (event) {
    // delete brand
    handleDelete(event, '.delete-brand', `/${panel}/brands/`, 'You are about to delete this Brand.');
});
