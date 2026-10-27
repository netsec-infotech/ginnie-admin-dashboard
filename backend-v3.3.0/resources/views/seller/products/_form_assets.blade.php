@push('styles')
    <!-- Include JS Tree CSS -->
    <link rel="stylesheet" href="{{ hyperAsset('assets/vendor/js_tree/main.min.css') }}" />
    <link rel="stylesheet" href="{{ hyperAsset('assets/css/product-form.css') }}" />
@endpush
@push('scripts')
    @php
        $catalogLanguagePayload = $catalogLanguages
            ->map(fn ($language) => [
                'code' => $language->code,
                'name' => $language->name,
                'direction' => $language->direction ?? 'ltr',
                'flag_url' => $language->flag_url ?? null,
            ])
            ->values();
    @endphp
    <script>
        window.catalogLanguages = @json($catalogLanguagePayload);
        window.visibilityInspectorLabels = {
            live: @json(__('labels.visibility_live')),
            partial: @json(__('labels.visibility_partial')),
            hidden: @json(__('labels.visibility_hidden')),
            no_active_zones: @json(__('labels.visibility_no_active_zones')),
            reachable_in_zones: @json(__('labels.visibility_reachable_in_zones')),
            show_problem_zones: @json(__('labels.visibility_show_problem_zones')),
            problem_truncated: @json(__('labels.visibility_problem_truncated')),
        };
    </script>
    <script src="{{ asset('assets/js/visibility-inspector.js') }}" defer></script>
    <script src="{{ hyperAsset('assets/vendor/js_tree/main.min.js') }}" defer></script>
    <script src="{{ hyperAsset('assets/js/product.js') }}" defer></script>
    @if ($isAdminMode ?? false)
        <script src="{{ hyperAsset('assets/js/brands.js') }}" defer></script>
    @endif

    @if (!empty($product) && !empty($productVariants))
        <script>
            window.productData = {
                product: @json($product),
                variants: @json($productVariants),
                type: "{{ $product->type }}"
            };
        </script>
    @elseif(!empty($product) && !empty($singleProductVariant))
        <script>
            window.productData = {
                product: @json($product),
                variant: @json($singleProductVariant),
                type: "{{ $product->type }}"
            };
        </script>
    @endif
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-load the visibility inspector when present (only on the edit form).
            const inspectorEl = document.querySelector('[data-visibility-inspector]');
            if (inspectorEl && typeof window.loadVisibilityInspector === 'function') {
                window.loadVisibilityInspector(inspectorEl);
            }

            const container = document.getElementById('customSectionsContainer');
            const addSectionBtn = document.getElementById('addSectionBtn');

            function initFilePondFor(input) {
                if (!window.FilePond || !input) return;
                try {
                    const imageUrl = input.getAttribute('data-image-url') || '';
                    FilePond.create(input, {
                        allowImagePreview: true,
                        credits: false,
                        storeAsFile: true,
                        maxFileSize: '2MB',
                        acceptedFileTypes: ['image/*'],
                        files: imageUrl ? [{
                            source: imageUrl,
                            options: {
                                type: 'remote'
                            }
                        }] : []
                    });
                } catch (e) {}
            }

            function renumber() {
                [...container.querySelectorAll('.section-item')].forEach((secEl, sIdx) => {
                    secEl.querySelector('.section-number').textContent = sIdx + 1;
                    secEl.setAttribute('data-index', sIdx);
                    // rename inputs
                    secEl.querySelectorAll('[name^="custom_sections["]').forEach(inp => {
                        inp.name = inp.name.replace(/custom_sections\[[0-9]+\]/,
                            `custom_sections[${sIdx}]`);
                    });
                    const fieldsContainer = secEl.querySelector('.fields-container');
                    [...fieldsContainer.querySelectorAll('.field-item')].forEach((fEl, fIdx) => {
                        fEl.querySelector('.field-number').textContent = fIdx + 1;
                        fEl.setAttribute('data-index', fIdx);
                        fEl.querySelectorAll('[name*="[fields]"]').forEach(inp => {
                            inp.name = inp.name.replace(
                                /custom_sections\[[0-9]+\]\[fields\]\[[0-9]+\]/,
                                `custom_sections[${sIdx}][fields][${fIdx}]`);
                        });
                    });
                });
            }

            function createFieldElement(sectionIndex) {
                const wrapper = document.createElement('div');
                wrapper.className = 'border rounded p-2 field-item';
                wrapper.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>{{ __('labels.field') }} #<span class="field-number"></span></span>
                        <button type="button" class="btn btn-link text-danger p-0 remove-field">{{ __('labels.remove') }}</button>
                    </div>
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">{{ __('labels.title') }}</label>
                            <input type="text" class="form-control" name="custom_sections[${sectionIndex}][fields][0][title]">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('labels.description') }}</label>
                            <input type="text" class="form-control" name="custom_sections[${sectionIndex}][fields][0][description]">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">{{ __('labels.sort_order') }}</label>
                            <input type="number" min="0" class="form-control" name="custom_sections[${sectionIndex}][fields][0][sort_order]" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ __('labels.image') }}</label>
                            <input type="file" class="form-control field-image-input" name="custom_sections[${sectionIndex}][fields][0][image]">
                        </div>
                    </div>`;
                return wrapper;
            }

            function createSectionElement() {
                const sIdx = container.querySelectorAll('.section-item').length;
                const card = document.createElement('div');
                card.className = 'card border section-item';
                card.innerHTML = `
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h4 class="mb-0">{{ __('labels.section') }} #<span class="section-number"></span></h4>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-danger remove-section">{{ __('labels.remove') }}</button>
                            </div>
                        </div>
                                                <hr>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label required">{{ __('labels.title') }}</label>
                                <input type="text" class="form-control" name="custom_sections[${sIdx}][title]">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('labels.description') }}</label>
                                <input type="text" class="form-control" name="custom_sections[${sIdx}][description]">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">{{ __('labels.sort_order') }}</label>
                                <input type="number" min="0" class="form-control" name="custom_sections[${sIdx}][sort_order]" value="0">
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong class="mb-0">{{ __('labels.fields') }}</strong>
                                <button type="button" class="btn btn-outline-secondary add-field">{{ __('labels.add_field') }}</button>
                            </div>
                            <div class="vstack gap-2 fields-container"></div>
                        </div>
                    </div>`;
                return card;
            }

            container?.querySelectorAll('.field-image-input').forEach(initFilePondFor);

            addSectionBtn?.addEventListener('click', function() {
                const card = createSectionElement();
                container.appendChild(card);
                renumber();
            });

            container?.addEventListener('click', function(e) {
                const t = e.target;
                if (t.closest('.remove-section')) {
                    const item = t.closest('.section-item');
                    item?.remove();
                    renumber();
                }
                if (t.closest('.add-field')) {
                    const section = t.closest('.section-item');
                    const sIdx = parseInt(section.getAttribute('data-index') || '0', 10);
                    const fieldsWrap = section.querySelector('.fields-container');
                    const fieldEl = createFieldElement(sIdx);
                    fieldsWrap.appendChild(fieldEl);
                    renumber();
                    initFilePondFor(fieldEl.querySelector('.field-image-input'));
                }
                if (t.closest('.remove-field')) {
                    const fieldItem = t.closest('.field-item');
                    fieldItem?.remove();
                    renumber();
                }
            });

            // AI Product Content & SEO Generator JS
            const aiModalEl = document.getElementById('aiGeneratorModal');
            const generateBtn = document.getElementById('aiGenerateBtn');
            const applyBtn = document.getElementById('aiApplyBtn');
            const errorBox = document.getElementById('aiErrorBox');
            const emptyState = document.getElementById('aiEmptyState');
            const loadingState = document.getElementById('aiLoadingState');
            const resultContainer = document.getElementById('aiResultContainer');
            const spinner = document.getElementById('aiSpinner');
            const copyShortDescBtn = document.getElementById('aiCopyShortDescBtn');
            let generatedData = null;

            function showAiToast(icon, title) {
                if (typeof Toast !== 'undefined' && typeof Toast.fire === 'function') {
                    Toast.fire({ icon: icon, title: title });
                } else if (typeof Swal !== 'undefined' && typeof Swal.fire === 'function') {
                    Swal.fire({
                        icon: icon,
                        title: title,
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true
                    });
                } else if (typeof showToast === 'function') {
                    showToast(icon, title);
                } else {
                    alert(title);
                }
            }

            // Sync values from form when modal opens
            if (aiModalEl) {
                aiModalEl.addEventListener('show.bs.modal', function() {
                    const mainTitle = (document.querySelector('[name="title[en]"]')?.value || '').trim();
                    const mainCat = document.querySelector('#selected_category')?.value || '';
                    
                    const aiTitleInput = document.getElementById('aiProductTitle');
                    if (aiTitleInput && !aiTitleInput.value && mainTitle) {
                        aiTitleInput.value = mainTitle;
                    }

                    const aiCatSelect = document.getElementById('aiProductCategory');
                    if (aiCatSelect && mainCat) {
                        aiCatSelect.value = mainCat;
                    }
                });

                // Prompt Preset Chip Handler
                aiModalEl.querySelectorAll('.ai-prompt-chip').forEach(chip => {
                    chip.addEventListener('click', function() {
                        const targetId = this.getAttribute('data-target');
                        const text = this.getAttribute('data-text');
                        const textarea = document.querySelector(targetId);
                        if (textarea && text) {
                            const cur = textarea.value.trim();
                            if (!cur) {
                                textarea.value = text;
                            } else if (!cur.includes(text)) {
                                textarea.value = cur + ' ' + text;
                            }
                            textarea.focus();

                            chip.classList.remove('btn-outline-secondary');
                            chip.classList.add('btn-primary');
                            setTimeout(() => {
                                chip.classList.remove('btn-primary');
                                chip.classList.add('btn-outline-secondary');
                            }, 350);
                        }
                    });
                });
            }

            // Generate AI Copy
            if (generateBtn) {
                generateBtn.addEventListener('click', function() {
                    const title = (document.getElementById('aiProductTitle')?.value || '').trim();
                    const categoryId = document.getElementById('aiProductCategory')?.value || '';
                    const tone = document.getElementById('aiProductTone')?.value || 'persuasive';
                    const length = document.getElementById('aiProductLength')?.value || 'standard';
                    const keywords = (document.getElementById('aiProductKeywords')?.value || '').trim();
                    const instructions = (document.getElementById('aiProductInstructions')?.value || '').trim();

                    if (!title) {
                        showAiToast('warning', 'Please enter a product title / name first.');
                        return;
                    }

                    if (errorBox) errorBox.classList.add('d-none');
                    if (emptyState) emptyState.classList.add('d-none');
                    if (resultContainer) resultContainer.classList.add('d-none');
                    if (loadingState) loadingState.classList.remove('d-none');
                    if (applyBtn) applyBtn.disabled = true;
                    if (spinner) spinner.classList.remove('d-none');
                    generateBtn.disabled = true;

                    const generateUrl = @json(
                        $isAdminMode ?? request()->is('admin/*')
                            ? route('admin.products.generate-ai-description')
                            : route('seller.products.generate-ai-description'));

                    fetch(generateUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            title: title,
                            category_id: categoryId,
                            tone: tone,
                            length: length,
                            keywords: keywords,
                            instructions: instructions
                        })
                    })
                    .then(async res => {
                        const data = await res.json().catch(() => null);
                        if (!res.ok) {
                            throw new Error((data && data.message) ? data.message : `Server error (${res.status})`);
                        }
                        if (!data) {
                            throw new Error(`Server returned invalid response (${res.status})`);
                        }
                        return data;
                    })
                    .then(res => {
                        if (loadingState) loadingState.classList.add('d-none');
                        if (spinner) spinner.classList.add('d-none');
                        generateBtn.disabled = false;

                        if (res && res.success && res.data) {
                            generatedData = res.data;

                            // Description Tab
                            const previewTitle = document.getElementById('aiPreviewTitle');
                            if (previewTitle) previewTitle.textContent = generatedData.title || title;

                            const previewFullDesc = document.getElementById('aiPreviewFullDesc');
                            if (previewFullDesc) previewFullDesc.innerHTML = generatedData.description || '';

                            const previewWordCount = document.getElementById('aiPreviewWordCount');
                            if (previewWordCount && generatedData.description) {
                                const plainText = previewFullDesc ? previewFullDesc.textContent || '' : '';
                                const wordCount = plainText.trim() ? plainText.trim().split(/\s+/).length : 0;
                                previewWordCount.textContent = `${wordCount} words`;
                            }

                            // Short Description Tab
                            const previewShortDesc = document.getElementById('aiPreviewShortDesc');
                            if (previewShortDesc) previewShortDesc.textContent = generatedData.short_description || '';

                            // Tags Tab
                            const previewTags = document.getElementById('aiPreviewTags');
                            if (previewTags) {
                                previewTags.innerHTML = '';
                                const tagsList = Array.isArray(generatedData.tags) ? generatedData.tags : [];
                                if (tagsList.length > 0) {
                                    tagsList.forEach(tag => {
                                        const badge = document.createElement('span');
                                        badge.className = 'badge bg-blue-lt px-2 py-1 fs-6';
                                        badge.textContent = tag;
                                        previewTags.appendChild(badge);
                                    });
                                } else {
                                    previewTags.innerHTML = '<span class="text-muted small">No tags suggested.</span>';
                                }
                            }

                            // SEO Tab
                            const serpTitle = document.getElementById('aiPreviewSerpTitle');
                            if (serpTitle) serpTitle.textContent = generatedData.seo_title || generatedData.title || title;

                            const serpDesc = document.getElementById('aiPreviewSerpDesc');
                            if (serpDesc) serpDesc.textContent = generatedData.seo_description || generatedData.short_description || '';

                            const previewSeoTitle = document.getElementById('aiPreviewSeoTitle');
                            if (previewSeoTitle) previewSeoTitle.textContent = generatedData.seo_title || generatedData.title || '—';

                            const previewSeoDesc = document.getElementById('aiPreviewSeoDesc');
                            if (previewSeoDesc) previewSeoDesc.textContent = generatedData.seo_description || generatedData.short_description || '—';

                            const previewSeoKw = document.getElementById('aiPreviewSeoKeywords');
                            if (previewSeoKw) {
                                previewSeoKw.innerHTML = '';
                                const kwList = Array.isArray(generatedData.seo_keywords) ? generatedData.seo_keywords : (Array.isArray(generatedData.tags) ? generatedData.tags : []);
                                if (kwList.length > 0) {
                                    kwList.forEach(kw => {
                                        const badge = document.createElement('span');
                                        badge.className = 'badge bg-purple-lt px-2 py-1';
                                        badge.textContent = kw;
                                        previewSeoKw.appendChild(badge);
                                    });
                                } else {
                                    previewSeoKw.innerHTML = '<span class="text-muted small">No keywords suggested.</span>';
                                }
                            }

                            if (resultContainer) resultContainer.classList.remove('d-none');
                            if (applyBtn) applyBtn.disabled = false;
                        } else {
                            showAiToast('error', (res && res.message) ? res.message : 'Failed to generate product copy.');
                            if (emptyState) emptyState.classList.remove('d-none');
                        }
                    })
                    .catch(err => {
                        if (loadingState) loadingState.classList.add('d-none');
                        if (spinner) spinner.classList.add('d-none');
                        generateBtn.disabled = false;
                        showAiToast('error', err.message || 'An error occurred while connecting to AI generator server.');
                        if (emptyState) emptyState.classList.remove('d-none');
                    });
                });
            }

            // Copy Short Description Button
            if (copyShortDescBtn) {
                copyShortDescBtn.addEventListener('click', function() {
                    const text = document.getElementById('aiPreviewShortDesc')?.textContent || '';
                    if (text && text !== '—') {
                        navigator.clipboard.writeText(text).then(() => {
                            showAiToast('success', 'Short description copied to clipboard!');
                        });
                    }
                });
            }

            // Apply Selected Fields to Main Product Form
            if (applyBtn) {
                applyBtn.addEventListener('click', function() {
                    if (!generatedData) return;

                    const applyTitle = document.getElementById('aiApplyTitle')?.checked ?? true;
                    const applyShortDesc = document.getElementById('aiApplyShortDesc')?.checked ?? true;
                    const applyFullDesc = document.getElementById('aiApplyFullDesc')?.checked ?? true;
                    const applyTags = document.getElementById('aiApplyTags')?.checked ?? true;
                    const applySeo = document.getElementById('aiApplySeo')?.checked ?? true;

                    // 1. Title
                    if (applyTitle && generatedData.title) {
                        const titleInput = document.querySelector('[name="title[en]"]');
                        if (titleInput) titleInput.value = generatedData.title;
                    }

                    // 2. Short Description
                    if (applyShortDesc && generatedData.short_description) {
                        const shortDescInput = document.querySelector('[name="short_description[en]"]');
                        if (shortDescInput) shortDescInput.value = generatedData.short_description;
                    }

                    // 3. Full HTML Description
                    if (applyFullDesc && generatedData.description) {
                        const descInput = document.querySelector('[name="description[en]"]');
                        if (descInput) descInput.value = generatedData.description;

                        if (window.hugerte && window.hugerte.get('productDescriptionField')) {
                            window.hugerte.get('productDescriptionField').setContent(generatedData.description);
                        } else if (window.hugerte && window.hugerte.activeEditor) {
                            window.hugerte.activeEditor.setContent(generatedData.description);
                        } else if (window.tinymce && window.tinymce.get('productDescriptionField')) {
                            window.tinymce.get('productDescriptionField').setContent(generatedData.description);
                        }
                    }

                    // 4. Tags
                    if (applyTags && Array.isArray(generatedData.tags) && generatedData.tags.length > 0) {
                        const tagSelect = document.querySelector('.product-tags');
                        if (tagSelect) {
                            const ts = tagSelect.tomselect;
                            if (ts) {
                                generatedData.tags.forEach(t => {
                                    ts.addOption({ value: t, text: t });
                                    ts.addItem(t);
                                });
                            } else {
                                generatedData.tags.forEach(t => {
                                    if (!tagSelect.querySelector(`option[value="${CSS.escape(t)}"]`)) {
                                        const opt = new Option(t, t, true, true);
                                        tagSelect.add(opt);
                                    }
                                });
                                if (typeof $ !== 'undefined') $(tagSelect).trigger('change');
                            }
                        }
                    }

                    // 5. SEO Metadata
                    if (applySeo) {
                        const seoTitleInput = document.querySelector('input[name="metadata[seo_title]"]');
                        if (seoTitleInput && (generatedData.seo_title || generatedData.title)) {
                            seoTitleInput.value = generatedData.seo_title || generatedData.title;
                        }

                        const seoDescInput = document.querySelector('textarea[name="metadata[seo_description]"]');
                        if (seoDescInput && (generatedData.seo_description || generatedData.short_description)) {
                            seoDescInput.value = generatedData.seo_description || generatedData.short_description;
                        }

                        const seoKwSelect = document.querySelector('.seo-keywords-select');
                        if (seoKwSelect) {
                            const ts = seoKwSelect.tomselect;
                            const kwList = Array.isArray(generatedData.seo_keywords) ? generatedData.seo_keywords : (Array.isArray(generatedData.tags) ? generatedData.tags : []);
                            if (ts) {
                                kwList.forEach(k => {
                                    ts.addOption({ value: k, text: k });
                                    ts.addItem(k);
                                });
                            } else {
                                kwList.forEach(k => {
                                    if (!seoKwSelect.querySelector(`option[value="${CSS.escape(k)}"]`)) {
                                        const opt = new Option(k, k, true, true);
                                        seoKwSelect.add(opt);
                                    }
                                });
                                if (typeof $ !== 'undefined') $(seoKwSelect).trigger('change');
                            }
                        }
                    }

                    showAiToast('success', @json(__('labels.applied_successfully') ?? 'AI product copy applied to form!'));

                    // Close Modal
                    if (aiModalEl) {
                        const bsModal = window.bootstrap || (typeof bootstrap !== 'undefined' ? bootstrap : null);
                        if (bsModal && bsModal.Modal) {
                            const modalInstance = bsModal.Modal.getInstance(aiModalEl) || bsModal.Modal.getOrCreateInstance(aiModalEl);
                            modalInstance?.hide();
                        } else if (typeof $ !== 'undefined' && $.fn && $.fn.modal) {
                            $(aiModalEl).modal('hide');
                        } else {
                            const closeBtn = aiModalEl.querySelector('[data-bs-dismiss="modal"]');
                            if (closeBtn) closeBtn.click();
                        }
                    }
                });
            }

            const translateButton = document.getElementById('product-ai-translate-btn');
            const translateConfirmButton = document.getElementById('product-ai-translate-confirm');
            const translateAllCheckbox = document.getElementById('product-ai-translate-all');
            const targetLocaleCheckboxes = Array.from(document.querySelectorAll('.product-ai-target-locale'));

            function syncTranslateAllCheckbox() {
                if (!translateAllCheckbox) return;

                const selectedCount = targetLocaleCheckboxes.filter(checkbox => checkbox.checked).length;
                translateAllCheckbox.checked = selectedCount === targetLocaleCheckboxes.length;
                translateAllCheckbox.indeterminate = selectedCount > 0 && selectedCount < targetLocaleCheckboxes.length;
            }

            translateAllCheckbox?.addEventListener('change', function() {
                targetLocaleCheckboxes.forEach(checkbox => {
                    checkbox.checked = translateAllCheckbox.checked;
                });
                syncTranslateAllCheckbox();
            });
            targetLocaleCheckboxes.forEach(checkbox => checkbox.addEventListener('change', syncTranslateAllCheckbox));

            function applyProductTranslations(translations) {
                Object.entries(translations || {}).forEach(([locale, fields]) => {
                    const title = document.querySelector(`.product-title-input[data-locale="${locale}"]`);
                    const shortDescription = document.querySelector(`.product-short-description-input[data-locale="${locale}"]`);
                    const description = document.querySelector(`.product-description-input[data-locale="${locale}"]`);
                    const editorId = `productDescriptionField-${locale}`;
                    const editor = window.hugerte?.get(editorId) || window.tinymce?.get(editorId);

                    if (title && !title.value.trim() && fields.title) {
                        title.value = fields.title;
                    }
                    if (shortDescription && !shortDescription.value.trim() && fields.short_description) {
                        shortDescription.value = fields.short_description;
                    }
                    if (description && !(editor?.getContent() || description.value).trim() && fields.description) {
                        description.value = fields.description;
                        editor?.setContent(fields.description);
                    }
                });
            }

            translateConfirmButton?.addEventListener('click', async function() {
                const selectedCheckboxes = targetLocaleCheckboxes.filter(checkbox => checkbox.checked);
                if (selectedCheckboxes.length === 0) {
                    showAiToast('warning', translateConfirmButton.dataset.selectRequired);
                    return;
                }

                const sourceTitle = document.querySelector('.product-title-input[data-locale="en"]')?.value.trim() || '';
                const sourceShortDescription = document.querySelector('.product-short-description-input[data-locale="en"]')?.value.trim() || '';
                const englishDescription = document.querySelector('.product-description-input[data-locale="en"]');
                const sourceDescription = window.hugerte?.get('productDescriptionField')?.getContent()
                    || window.tinymce?.get('productDescriptionField')?.getContent()
                    || englishDescription?.value.trim()
                    || '';

                if (!sourceTitle && !sourceShortDescription && !sourceDescription) {
                    showAiToast('warning', translateButton.dataset.sourceRequired);
                    return;
                }

                const buttonText = translateButton.querySelector('.btn-text');
                const translateSpinner = translateButton.querySelector('.spinner-border');
                const confirmButtonText = translateConfirmButton.querySelector('.btn-text');
                const confirmSpinner = translateConfirmButton.querySelector('.spinner-border');
                translateButton.disabled = true;
                buttonText.textContent = translateButton.dataset.translating;
                translateSpinner.classList.remove('d-none');
                translateConfirmButton.disabled = true;
                confirmButtonText.textContent = translateButton.dataset.translating;
                confirmSpinner.classList.remove('d-none');

                try {
                    const translationUrl = @json(
                        $isAdminMode ?? request()->is('admin/*')
                            ? route('admin.translations.ai-translate')
                            : route('seller.translations.ai-translate'));
                    const selectedLocales = selectedCheckboxes.map(checkbox => checkbox.value);
                    const languageNames = Object.fromEntries(selectedCheckboxes.map(checkbox => [
                        checkbox.value,
                        checkbox.dataset.languageName || checkbox.value,
                    ]));
                    const completedLocales = [];
                    const failedLocales = [];
                    let firstErrorMessage = '';

                    for (let index = 0; index < selectedLocales.length; index += 2) {
                        const localeBatch = selectedLocales.slice(index, index + 2);

                        try {
                            const response = await fetch(translationUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                                },
                                body: JSON.stringify({
                                    module: 'product',
                                    product_id: translateButton.dataset.productId || null,
                                    target_locales: localeBatch,
                                    fields: {
                                        title: sourceTitle,
                                        short_description: sourceShortDescription,
                                        description: sourceDescription,
                                    },
                                }),
                            });
                            const payload = await response.json().catch(() => null);
                            if (!response.ok || !payload?.success) {
                                throw new Error(payload?.message || translateButton.dataset.failed);
                            }

                            const translations = payload.data?.translations || {};
                            applyProductTranslations(translations);
                            localeBatch.forEach(locale => {
                                if (translations[locale]) {
                                    completedLocales.push(locale);
                                } else {
                                    failedLocales.push(locale);
                                }
                            });
                        } catch (error) {
                            console.error('Product AI translation batch failed:', error);
                            firstErrorMessage ||= error.message || translateButton.dataset.failed;
                            failedLocales.push(...localeBatch);
                        }
                    }

                    if (completedLocales.length === 0) {
                        throw new Error(firstErrorMessage || translateButton.dataset.failed);
                    }

                    const translationModal = document.getElementById('productAiTranslationLanguagesModal');
                    if (translationModal && typeof bootstrap !== 'undefined') {
                        bootstrap.Modal.getOrCreateInstance(translationModal).hide();
                    }

                    if (failedLocales.length === 0) {
                        showAiToast('success', translateButton.dataset.success);
                    } else {
                        const failedNames = [...new Set(failedLocales)].map(locale => languageNames[locale] || locale).join(', ');
                        const message = translateButton.dataset.partial
                            .replace(':completed', completedLocales.length)
                            .replace(':total', selectedLocales.length)
                            .replace(':failed', failedNames);
                        showAiToast('warning', message);
                    }
                } catch (error) {
                    console.error('Product AI translation failed:', error);
                    showAiToast('error', error.message || translateButton.dataset.failed);
                } finally {
                    translateButton.disabled = false;
                    buttonText.textContent = translateButton.dataset.idle;
                    translateSpinner.classList.add('d-none');
                    translateConfirmButton.disabled = false;
                    confirmButtonText.textContent = @json(__('labels.start_translation'));
                    confirmSpinner.classList.add('d-none');
                }
            });
        });
    </script>
@endpush
