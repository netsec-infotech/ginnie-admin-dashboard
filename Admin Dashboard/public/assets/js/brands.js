document.addEventListener('show.bs.modal', function (event) {
    if (event.target.id === 'brand-modal') {
        const triggerButton = event.relatedTarget;
        const brandId = triggerButton.getAttribute('data-id');
        let url = `${base_url}/${panel}/brands/${brandId}/edit`;

        const form = document.querySelector('.form-submit');
        const brandUpload = document.querySelector('#logo-upload');
        const modalTitle = document.querySelector('#brand-modal .modal-title');
        const submitButton = document.querySelector('#brand-modal button[type="submit"]');
        const selectCategory = document.getElementById("select-root-category");
        const tomSelectScope = selectCategory && selectCategory.tomselect; // TomSelect instance
        const visibilityInspector = document.querySelector('#brand-modal [data-visibility-inspector]');


        if (brandId) {
            // Fetch the visibility inspection in parallel with the brand data fetch.
            if (visibilityInspector) {
                visibilityInspector.dataset.endpoint = `${base_url}/${panel}/brands/${brandId}/visibility`;
                if (typeof window.loadVisibilityInspector === 'function') {
                    window.loadVisibilityInspector(visibilityInspector);
                }
            }

            // Fetch brand data
            fetch(url, {method: 'GET'})
                .then(response => response.json())
                .then(responseData => {
                    const data = responseData.data;
                    console.log(data)
                    // Fill form fields
                    form.querySelector('input[name="title"]').value = data.title || '';
                    form.querySelector('textarea[name="description"]').value = data.description || '';
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
                        kws.forEach((kw) => keywordsTs.addOption({value: kw, text: kw}));
                        keywordsTs.setValue(kws);
                    }
                    form.querySelector('select[name="scope_type"]').value = data.scope_type ?? 'global';
                    if (data.scope_type === 'category') {
                        form.querySelector('#scopeCategoryField').style.display = 'block';
                        // Handle scope selection
                        tomSelectScope.addOption({
                            value: data.scope_id,
                            text: data.scope_category_title,
                        });
                        tomSelectScope.setValue(data.scope_id);
                    } else {
                        form.querySelector('#scopeCategoryField').style.display = 'none';

                        tomSelectScope.clearOptions();
                        tomSelectScope.clear();
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
            // Hide inspector — there's nothing to inspect until the brand is saved.
            if (visibilityInspector) visibilityInspector.hidden = true;
            // Remove _method input if it exists
            const methodInput = form.querySelector('input[name="_method"]');
            if (methodInput) methodInput.parentNode.removeChild(methodInput);

            // Remove files from FilePond if available
            if (typeof FilePond !== 'undefined') {
                const pond = FilePond.find(brandUpload);
                if (pond) pond.removeFiles();
            }
            form.querySelector('#scopeCategoryField').style.display = 'none';
            tomSelectScope.clearOptions();
            tomSelectScope.clear();
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
    // delete brand
    handleDelete(event, '.delete-brand', `/${panel}/brands/`, 'You are about to delete this Brand.');
});
