@push('styles')
    <!-- Include JS Tree CSS -->
    <link rel="stylesheet" href="{{ hyperAsset('assets/vendor/js_tree/main.min.css') }}"/>
@endpush
@push('scripts')
    <script>
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
    <script src="{{hyperAsset('assets/js/product.js')}}" defer></script>
    {{--    <script src="{{hyperAsset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js')}}" defer></script>--}}

    @if(!empty($product) && !empty($productVariants))
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
        document.addEventListener('DOMContentLoaded', function () {
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
                        files: imageUrl ? [{source: imageUrl, options: {type: 'remote'}}] : []
                    });
                } catch (e) {
                }
            }

            function renumber() {
                [...container.querySelectorAll('.section-item')].forEach((secEl, sIdx) => {
                    secEl.querySelector('.section-number').textContent = sIdx + 1;
                    secEl.setAttribute('data-index', sIdx);
                    // rename inputs
                    secEl.querySelectorAll('[name^="custom_sections["]').forEach(inp => {
                        inp.name = inp.name.replace(/custom_sections\[[0-9]+\]/, `custom_sections[${sIdx}]`);
                    });
                    const fieldsContainer = secEl.querySelector('.fields-container');
                    [...fieldsContainer.querySelectorAll('.field-item')].forEach((fEl, fIdx) => {
                        fEl.querySelector('.field-number').textContent = fIdx + 1;
                        fEl.setAttribute('data-index', fIdx);
                        fEl.querySelectorAll('[name*="[fields]"]').forEach(inp => {
                            inp.name = inp.name.replace(/custom_sections\[[0-9]+\]\[fields\]\[[0-9]+\]/, `custom_sections[${sIdx}][fields][${fIdx}]`);
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

            addSectionBtn?.addEventListener('click', function () {
                const card = createSectionElement();
                container.appendChild(card);
                renumber();
            });

            container?.addEventListener('click', function (e) {
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

            // AI Product Description Generator JS
            const generateBtn = document.getElementById('aiGenerateBtn');
            const applyBtn = document.getElementById('aiApplyBtn');
            const errorBox = document.getElementById('aiErrorBox');
            const previewBox = document.getElementById('aiPreviewBox');
            const shortDescPreview = document.getElementById('aiShortDescPreview');
            const fullDescPreview = document.getElementById('aiFullDescPreview');
            const spinner = document.getElementById('aiSpinner');
            let generatedData = null;

            if (generateBtn) {
                generateBtn.addEventListener('click', function () {
                    const title = (document.querySelector('[name="title"]')?.value || '').trim();
                    const categoryId = document.querySelector('#selected_category')?.value || '';
                    const tone = document.getElementById('aiToneSelect')?.value || 'professional';
                    const keywords = (document.getElementById('aiKeywordsInput')?.value || '').trim();

                    if (!title) {
                        errorBox.textContent = 'Please enter a product title first.';
                        errorBox.classList.remove('d-none');
                        return;
                    }

                    errorBox.classList.add('d-none');
                    previewBox.classList.add('d-none');
                    applyBtn.classList.add('d-none');
                    spinner.classList.remove('d-none');
                    generateBtn.disabled = true;

                    const generateUrl = @json(($isAdminMode ?? request()->is('admin/*')) ? route('admin.products.generate-ai-description') : route('seller.products.generate-ai-description'));

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
                            keywords: keywords
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
                        spinner.classList.add('d-none');
                        generateBtn.disabled = false;
                        if (res && res.success && res.data) {
                            generatedData = res.data;
                            shortDescPreview.textContent = res.data.short_description || '';
                            fullDescPreview.innerHTML = res.data.description || '';
                            previewBox.classList.remove('d-none');
                            applyBtn.classList.remove('d-none');
                        } else {
                            errorBox.textContent = (res && res.message) ? res.message : 'Failed to generate product description.';
                            errorBox.classList.remove('d-none');
                        }
                    })
                    .catch(err => {
                        spinner.classList.add('d-none');
                        generateBtn.disabled = false;
                        errorBox.textContent = err.message || 'An error occurred while connecting to AI generator server.';
                        errorBox.classList.remove('d-none');
                    });
                });
            }

            if (applyBtn) {
                applyBtn.addEventListener('click', function () {
                    if (!generatedData) return;

                    const shortDescInput = document.querySelector('[name="short_description"]');
                    if (shortDescInput && generatedData.short_description) {
                        shortDescInput.value = generatedData.short_description;
                    }

                    const descInput = document.querySelector('[name="description"]');
                    if (descInput && generatedData.description) {
                        descInput.value = generatedData.description;
                        if (window.hugerte && window.hugerte.get('productDescriptionField')) {
                            window.hugerte.get('productDescriptionField').setContent(generatedData.description);
                        } else if (window.hugerte && window.hugerte.activeEditor) {
                            window.hugerte.activeEditor.setContent(generatedData.description);
                        }
                    }

                    const modalEl = document.getElementById('aiGeneratorModal');
                    if (modalEl && window.bootstrap) {
                        const modalInstance = window.bootstrap.Modal.getInstance(modalEl);
                        modalInstance?.hide();
                    }
                });
            }
        });
    </script>
@endpush
