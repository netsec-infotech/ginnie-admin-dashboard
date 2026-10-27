@php
    $categories =
        $categories ??
        \App\Models\BlogCategory::query()->active()->orderBy('sort_order', 'asc')->orderBy('name')->get();
@endphp

<!-- AI Blog & SEO Generator Modal -->
<div class="modal fade" id="ai-blog-modal" tabindex="-1" aria-labelledby="aiBlogModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary-lt border-bottom">
                <div class="d-flex align-items-center">
                    <div class="avatar bg-primary text-white me-3 rounded">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" class="icon icon-tabler icon-tabler-sparkles m-0">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path
                                d="M16 18a2 2 0 0 1 2 2a2 2 0 0 1 2 -2a2 2 0 0 1 -2 -2a2 2 0 0 1 -2 2zm0 -12a2 2 0 0 1 2 2a2 2 0 0 1 2 -2a2 2 0 0 1 -2 -2a2 2 0 0 1 -2 2zm-7 12a6 6 0 0 1 6 -6a6 6 0 0 1 -6 -6a6 6 0 0 1 -6 6a6 6 0 0 1 6 6z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="modal-title m-0 fw-bold" id="aiBlogModalLabel">{{ __('labels.ai_blog_generator') }}
                        </h4>
                        <div class="text-muted small">{{ __('labels.ai_blog_generator_desc') }}</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                <div class="row g-4">
                    <!-- Left: Generation Controls Form -->
                    <div class="col-lg-5 border-end-lg pe-lg-4">
                        <div class="card bg-surface-secondary border shadow-none mb-0">
                            <div class="card-body p-3">
                                <h5 class="card-title mb-3 d-flex align-items-center text-primary">
                                    <i class="ti ti-adjustments me-2 fs-4"></i>{{ __('labels.general_information') }}
                                </h5>

                                <div class="mb-3">
                                    <label class="form-label required">{{ __('labels.topic_or_working_title') }}</label>
                                    <input type="text" class="form-control" id="ai-topic"
                                        placeholder="{{ __('labels.topic_placeholder') }}" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">{{ __('labels.category') }}</label>
                                    <select class="form-select" id="ai-category-id">
                                        <option value="">{{ __('labels.select_category') }}</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-sm-6">
                                        <label class="form-label">{{ __('labels.tone_of_voice') }}</label>
                                        <select class="form-select" id="ai-tone">
                                            <option value="informative" selected>{{ __('labels.tone_informative') }}
                                            </option>
                                            <option value="engaging">{{ __('labels.tone_engaging') }}</option>
                                            <option value="professional">{{ __('labels.tone_professional') }}</option>
                                            <option value="storytelling">{{ __('labels.tone_storytelling') }}</option>
                                            <option value="seo_guide">{{ __('labels.tone_seo_guide') }}</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label">{{ __('labels.article_length') }}</label>
                                        <select class="form-select" id="ai-length">
                                            <option value="standard" selected>{{ __('labels.length_standard') }}
                                            </option>
                                            <option value="short">{{ __('labels.length_short') }}</option>
                                            <option value="comprehensive">{{ __('labels.length_comprehensive') }}
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">{{ __('labels.target_keywords') }}</label>
                                    <input type="text" class="form-control" id="ai-keywords"
                                        placeholder="{{ __('labels.target_keywords_placeholder') }}">
                                    <small
                                        class="form-hint text-muted">{{ __('labels.comma_separated_keywords') }}</small>
                                </div>

                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="form-label mb-0 fw-semibold text-primary">
                                            <i
                                                class="ti ti-prompt me-1"></i>{{ __('labels.custom_prompt_and_instructions') }}
                                        </label>
                                        <span class="badge bg-secondary-lt" style="font-size: 0.65rem;">Optional</span>
                                    </div>

                                    <!-- 1-Click Blog Prompt Preset Chips -->
                                    <div class="d-flex flex-wrap gap-1 mb-2">
                                        <button type="button"
                                            class="btn btn-sm btn-outline-secondary py-0 px-2 rounded-pill ai-prompt-chip"
                                            data-target="#ai-instructions"
                                            data-text="Format the main sections as an actionable, numbered step-by-step how-to guide.">
                                            + Step-by-Step Guide
                                        </button>
                                        <button type="button"
                                            class="btn btn-sm btn-outline-secondary py-0 px-2 rounded-pill ai-prompt-chip"
                                            data-target="#ai-instructions"
                                            data-text="Include a balanced Pros & Cons comparison section.">
                                            + Pros & Cons
                                        </button>
                                        <button type="button"
                                            class="btn btn-sm btn-outline-secondary py-0 px-2 rounded-pill ai-prompt-chip"
                                            data-target="#ai-instructions"
                                            data-text="Include a Frequently Asked Questions (FAQ) section with 3-4 common questions and answers.">
                                            + FAQ Section
                                        </button>
                                        <button type="button"
                                            class="btn btn-sm btn-outline-secondary py-0 px-2 rounded-pill ai-prompt-chip"
                                            data-target="#ai-instructions"
                                            data-text="Add compelling Call-to-Action paragraphs encouraging readers to explore our store products.">
                                            + E-Commerce CTA
                                        </button>
                                        <button type="button"
                                            class="btn btn-sm btn-outline-secondary py-0 px-2 rounded-pill ai-prompt-chip"
                                            data-target="#ai-instructions"
                                            data-text="Start with an inspiring, relatable story hook before diving into the concepts.">
                                            + Story Hook
                                        </button>
                                    </div>

                                    <textarea class="form-control" id="ai-instructions" rows="2"
                                        placeholder="{{ __('labels.custom_instructions_placeholder') }}"></textarea>
                                    <small class="form-hint text-muted">Click any preset chip above or type your own
                                        custom directives.</small>
                                </div>

                                <div class="d-grid mt-4">
                                    <button type="button" class="btn btn-primary btn-lg shadow-sm"
                                        id="ai-generate-btn">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            class="icon icon-tabler icon-tabler-wand me-2">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M6 21l15 -15l-3 -3l-15 15l3 3" />
                                            <path d="M15 6l3 3" />
                                            <path
                                                d="M9 3a2 2 0 0 0 2 2a2 2 0 0 0 -2 2a2 2 0 0 0 -2 -2a2 2 0 0 0 2 -2" />
                                            <path
                                                d="M19 13a2 2 0 0 0 2 2a2 2 0 0 0 -2 2a2 2 0 0 0 -2 -2a2 2 0 0 0 2 -2" />
                                        </svg>
                                        <span id="ai-generate-btn-text">{{ __('labels.generate') }}</span>
                                        <span class="spinner-border spinner-border-sm ms-2 d-none"
                                            id="ai-generate-spinner" role="status" aria-hidden="true"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Live Preview & Field Selector -->
                    <div class="col-lg-7 ps-lg-4 d-flex flex-column">
                        <!-- Initial Placeholder -->
                        <div id="ai-empty-state" class="text-center py-5 my-auto">
                            <div class="mb-3 text-muted">
                                <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                                    stroke-linecap="round" stroke-linejoin="round" class="icon text-primary-subtle">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M4 20h4l10.5 -10.5a2.828 2.828 0 1 0 -4 -4l-10.5 10.5v4" />
                                    <path d="M13.5 6.5l4 4" />
                                    <path d="M19 16v6" />
                                    <path d="M22 19l-6 0" />
                                </svg>
                            </div>
                            <h4 class="text-muted fw-semibold mb-1">{{ __('labels.preview_article') }}</h4>
                            <p class="text-muted small max-w-sm mx-auto">
                                Enter a topic on the left and click <strong>Generate Article</strong> to preview
                                structured HTML content, excerpt, suggested tags, and complete SEO meta tags.
                            </p>
                        </div>

                        <!-- Loading State -->
                        <div id="ai-loading-state" class="text-center py-5 my-auto d-none">
                            <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;"
                                role="status"></div>
                            <h4 class="fw-bold text-primary mb-1">{{ __('labels.generating_article') }}</h4>
                            <p class="text-muted small" id="ai-loading-subtext">Crafting engaging copy and optimizing
                                SEO tags...</p>
                        </div>

                        <!-- Result Preview Container -->
                        <div id="ai-result-container" class="d-none d-flex flex-column h-100">
                            <!-- Selection Checklist Bar -->
                            <div class="bg-primary-lt border rounded p-2 mb-3">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                    <span class="fw-semibold small text-primary d-flex align-items-center">
                                        <i class="ti ti-check me-1"></i>Select fields to apply:
                                    </span>
                                    <div class="d-flex flex-wrap align-items-center gap-3">
                                        <label class="form-check form-check-inline m-0 small">
                                            <input class="form-check-input" type="checkbox" id="ai-apply-title"
                                                checked>
                                            <span class="form-check-label">{{ __('labels.apply_title') }}</span>
                                        </label>
                                        <label class="form-check form-check-inline m-0 small">
                                            <input class="form-check-input" type="checkbox" id="ai-apply-excerpt"
                                                checked>
                                            <span
                                                class="form-check-label">{{ __('labels.apply_short_description') }}</span>
                                        </label>
                                        <label class="form-check form-check-inline m-0 small">
                                            <input class="form-check-input" type="checkbox" id="ai-apply-content"
                                                checked>
                                            <span class="form-check-label">{{ __('labels.apply_content') }}</span>
                                        </label>
                                        <label class="form-check form-check-inline m-0 small">
                                            <input class="form-check-input" type="checkbox" id="ai-apply-tags"
                                                checked>
                                            <span class="form-check-label">{{ __('labels.apply_tags') }}</span>
                                        </label>
                                        <label class="form-check form-check-inline m-0 small">
                                            <input class="form-check-input" type="checkbox" id="ai-apply-seo"
                                                checked>
                                            <span class="form-check-label">{{ __('labels.apply_seo') }}</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Preview Tabs Header -->
                            <ul class="nav nav-tabs nav-fill mb-3" id="aiPreviewTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active py-2 small fw-semibold" id="tab-ai-content"
                                        data-bs-toggle="tab" data-bs-target="#pane-ai-content" type="button"
                                        role="tab">
                                        <i class="ti ti-article me-1"></i>{{ __('labels.article_content') }}
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link py-2 small fw-semibold" id="tab-ai-excerpt"
                                        data-bs-toggle="tab" data-bs-target="#pane-ai-excerpt" type="button"
                                        role="tab">
                                        <i class="ti ti-notes me-1"></i>{{ __('labels.excerpt') }}
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link py-2 small fw-semibold" id="tab-ai-tags"
                                        data-bs-toggle="tab" data-bs-target="#pane-ai-tags" type="button"
                                        role="tab">
                                        <i class="ti ti-tags me-1"></i>{{ __('labels.suggested_tags') }}
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link py-2 small fw-semibold" id="tab-ai-seo"
                                        data-bs-toggle="tab" data-bs-target="#pane-ai-seo" type="button"
                                        role="tab">
                                        <i class="ti ti-search me-1"></i>{{ __('labels.seo_metadata') }}
                                    </button>
                                </li>
                            </ul>

                            <!-- Preview Tabs Content -->
                            <div class="tab-content flex-grow-1 border rounded p-3 bg-surface overflow-auto"
                                style="max-height: 400px;" id="aiPreviewTabsContent">
                                <!-- 1. Content Pane -->
                                <div class="tab-pane fade show active" id="pane-ai-content" role="tabpanel">
                                    <div class="mb-2 d-flex justify-content-between align-items-center">
                                        <h5 class="fw-bold mb-0 text-primary" id="ai-preview-title">—</h5>
                                        <span class="badge bg-purple-lt" id="ai-preview-wordcount">0 words</span>
                                    </div>
                                    <hr class="my-2">
                                    <div id="ai-preview-rendered-html" class="prose small" style="line-height: 1.6;">
                                    </div>
                                </div>

                                <!-- 2. Excerpt Pane -->
                                <div class="tab-pane fade" id="pane-ai-excerpt" role="tabpanel">
                                    <label
                                        class="form-label text-muted small fw-bold text-uppercase">{{ __('labels.short_description') }}</label>
                                    <div class="p-3 bg-surface-secondary border rounded" id="ai-preview-excerpt">—
                                    </div>
                                </div>

                                <!-- 3. Tags Pane -->
                                <div class="tab-pane fade" id="pane-ai-tags" role="tabpanel">
                                    <label
                                        class="form-label text-muted small fw-bold text-uppercase">{{ __('labels.suggested_tags') }}</label>
                                    <div class="d-flex flex-wrap gap-2 mt-2" id="ai-preview-tags"></div>
                                </div>

                                <!-- 4. SEO Pane -->
                                <div class="tab-pane fade" id="pane-ai-seo" role="tabpanel">
                                    <div class="mb-3">
                                        <label
                                            class="form-label text-muted small fw-bold text-uppercase">{{ __('labels.seo_title') }}</label>
                                        <div class="p-2 bg-surface-secondary border rounded fw-semibold text-primary"
                                            id="ai-preview-seo-title">—</div>
                                    </div>
                                    <div class="mb-3">
                                        <label
                                            class="form-label text-muted small fw-bold text-uppercase">{{ __('labels.seo_keywords') }}</label>
                                        <div class="d-flex flex-wrap gap-2" id="ai-preview-seo-keywords"></div>
                                    </div>
                                    <div>
                                        <label
                                            class="form-label text-muted small fw-bold text-uppercase">{{ __('labels.seo_description') }}</label>
                                        <div class="p-2 bg-surface-secondary border rounded small"
                                            id="ai-preview-seo-desc">—</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-surface-secondary">
                <button type="button" class="btn btn-secondary"
                    data-bs-dismiss="modal">{{ __('labels.cancel') }}</button>
                <button type="button" class="btn btn-success" id="ai-apply-btn" disabled>
                    <i class="ti ti-check me-1"></i>{{ __('labels.apply_to_form') }}
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const aiModalEl = document.getElementById('ai-blog-modal');
            if (!aiModalEl) return;

            let generatedAiData = null;
            const generateUrl = @json(route('admin.blogs.generate-ai-content'));

            function showAiToast(icon, title) {
                if (typeof Toast !== 'undefined' && typeof Toast.fire === 'function') {
                    Toast.fire({
                        icon: icon,
                        title: title
                    });
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

            // Open Modal: pre-populate fields from main form
            aiModalEl.addEventListener('show.bs.modal', function() {
                const formTitle = document.querySelector('input[name="title"]');
                const formCategories = document.getElementById('blog-category-ids') || document.querySelector('select[name="category_ids[]"]');
                const aiTopic = document.getElementById('ai-topic');
                const aiCategory = document.getElementById('ai-category-id');

                if (aiTopic && formTitle && formTitle.value.trim() && !aiTopic.value.trim()) {
                    aiTopic.value = formTitle.value.trim();
                }
                if (aiCategory && formCategories && !aiCategory.value) {
                    if (formCategories.tomselect && formCategories.tomselect.items.length > 0) {
                        aiCategory.value = formCategories.tomselect.items[0];
                    } else if (formCategories.value) {
                        aiCategory.value = formCategories.value;
                    }
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

            // Generate Button Click
            const generateBtn = document.getElementById('ai-generate-btn');
            const generateBtnText = document.getElementById('ai-generate-btn-text');
            const generateSpinner = document.getElementById('ai-generate-spinner');
            const emptyState = document.getElementById('ai-empty-state');
            const loadingState = document.getElementById('ai-loading-state');
            const resultContainer = document.getElementById('ai-result-container');
            const applyBtn = document.getElementById('ai-apply-btn');

            if (generateBtn) {
                generateBtn.addEventListener('click', function() {
                    const topic = document.getElementById('ai-topic').value.trim();
                    if (!topic) {
                        showAiToast('warning', 'Please enter a topic or title first.');
                        document.getElementById('ai-topic').focus();
                        return;
                    }

                    const payload = {
                        topic: topic,
                        category_id: document.getElementById('ai-category-id').value || null,
                        tone: document.getElementById('ai-tone').value || 'informative',
                        length: document.getElementById('ai-length').value || 'standard',
                        keywords: document.getElementById('ai-keywords').value.trim() || null,
                        instructions: document.getElementById('ai-instructions').value.trim() || null,
                        _token: document.querySelector('meta[name="csrf-token"]').getAttribute(
                            'content')
                    };

                    // Switch to loading UI
                    generateBtn.disabled = true;
                    generateSpinner.classList.remove('d-none');
                    generateBtnText.textContent = @json(__('labels.generating_article'));
                    emptyState.classList.add('d-none');
                    resultContainer.classList.add('d-none');
                    loadingState.classList.remove('d-none');
                    applyBtn.disabled = true;

                    const loadingMessages = [
                        'Analyzing topic and outlining sections...',
                        'Generating rich HTML article content...',
                        'Extracting tag suggestions and SEO keywords...',
                        'Finalizing search-intent meta description...'
                    ];
                    let msgIdx = 0;
                    const subtextEl = document.getElementById('ai-loading-subtext');
                    const msgInterval = setInterval(() => {
                        msgIdx = (msgIdx + 1) % loadingMessages.length;
                        if (subtextEl) subtextEl.textContent = loadingMessages[msgIdx];
                    }, 2500);

                    $.ajax({
                        url: generateUrl,
                        type: 'POST',
                        data: JSON.stringify(payload),
                        contentType: 'application/json',
                        dataType: 'json',
                        success: function(response) {
                            clearInterval(msgInterval);
                            generateBtn.disabled = false;
                            generateSpinner.classList.add('d-none');
                            generateBtnText.textContent = @json(__('labels.generate'));
                            loadingState.classList.add('d-none');

                            if (response.success && response.data) {
                                generatedAiData = response.data;
                                renderPreview(generatedAiData);
                                resultContainer.classList.remove('d-none');
                                applyBtn.disabled = false;
                            } else {
                                emptyState.classList.remove('d-none');
                                const msg = response.message || @json(__('messages.something_went_wrong'));
                                showAiToast('error', msg);
                            }
                        },
                        error: function(xhr) {
                            clearInterval(msgInterval);
                            generateBtn.disabled = false;
                            generateSpinner.classList.add('d-none');
                            generateBtnText.textContent = @json(__('labels.generate'));
                            loadingState.classList.add('d-none');
                            emptyState.classList.remove('d-none');

                            let errorMsg = @json(__('messages.something_went_wrong'));
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMsg = xhr.responseJSON.message;
                            }
                            showAiToast('error', errorMsg);
                        }
                    });
                });
            }

            function renderPreview(data) {
                // Title & Content
                document.getElementById('ai-preview-title').textContent = data.title || 'Untitled Blog';
                const renderedHtmlEl = document.getElementById('ai-preview-rendered-html');
                renderedHtmlEl.innerHTML = data.content || '';

                // Word count
                const textOnly = (data.content || '').replace(/<[^>]*>/g, ' ');
                const wordCount = textOnly.trim().split(/\s+/).filter(Boolean).length;
                document.getElementById('ai-preview-wordcount').textContent = wordCount + ' words';

                // Excerpt
                document.getElementById('ai-preview-excerpt').textContent = data.short_description ||
                    'No excerpt generated.';

                // Tags
                const tagsContainer = document.getElementById('ai-preview-tags');
                tagsContainer.innerHTML = '';
                if (Array.isArray(data.tags) && data.tags.length > 0) {
                    data.tags.forEach(tag => {
                        const badge = document.createElement('span');
                        badge.className = 'badge bg-purple-lt p-2 fs-5';
                        badge.textContent = '#' + tag;
                        tagsContainer.appendChild(badge);
                    });
                } else {
                    tagsContainer.innerHTML = '<span class="text-muted small">No tags suggested.</span>';
                }

                // SEO
                document.getElementById('ai-preview-seo-title').textContent = data.seo_title || data.title || '—';
                document.getElementById('ai-preview-seo-desc').textContent = data.seo_description || data
                    .short_description || '—';

                const seoKwContainer = document.getElementById('ai-preview-seo-keywords');
                seoKwContainer.innerHTML = '';
                const kws = Array.isArray(data.seo_keywords) ? data.seo_keywords : [];
                if (kws.length > 0) {
                    kws.forEach(kw => {
                        const badge = document.createElement('span');
                        badge.className = 'badge bg-blue-lt p-2';
                        badge.textContent = kw;
                        seoKwContainer.appendChild(badge);
                    });
                } else {
                    seoKwContainer.innerHTML = '<span class="text-muted small">—</span>';
                }
            }

            // Apply Button Click
            if (applyBtn) {
                applyBtn.addEventListener('click', function() {
                    if (!generatedAiData) return;

                    const applyTitle = document.getElementById('ai-apply-title')?.checked ?? true;
                    const applyExcerpt = document.getElementById('ai-apply-excerpt')?.checked ?? true;
                    const applyContent = document.getElementById('ai-apply-content')?.checked ?? true;
                    const applyTags = document.getElementById('ai-apply-tags')?.checked ?? true;
                    const applySeo = document.getElementById('ai-apply-seo')?.checked ?? true;

                    // 1. Title
                    if (applyTitle && generatedAiData.title) {
                        const titleInput = document.querySelector('input[name="title"]');
                        if (titleInput) titleInput.value = generatedAiData.title;
                    }

                    // 2. Short Description
                    if (applyExcerpt && generatedAiData.short_description) {
                        const excerptTextarea = document.querySelector(
                            'textarea[name="short_description"]');
                        if (excerptTextarea) excerptTextarea.value = generatedAiData.short_description;
                    }

                    // 3. Category Context (if changed in modal)
                    const aiCatId = document.getElementById('ai-category-id')?.value;
                    if (aiCatId) {
                        const formCat = document.getElementById('blog-category-ids') || document.querySelector('select[name="category_ids[]"]');
                        if (formCat) {
                            if (formCat.tomselect) {
                                if (!formCat.tomselect.items.includes(aiCatId)) {
                                    formCat.tomselect.addItem(aiCatId);
                                }
                            } else if (typeof $ !== 'undefined' && $(formCat).data('select2')) {
                                const cur = $(formCat).val() || [];
                                if (!cur.includes(aiCatId)) {
                                    cur.push(aiCatId);
                                    $(formCat).val(cur).trigger('change');
                                }
                            }
                        }
                    }

                    // 4. Content (WYSIWYG & Textarea)
                    if (applyContent && generatedAiData.content) {
                        const contentTextarea = document.querySelector(
                            'textarea.hugerte-mytextarea, textarea[name="content"]');
                        if (contentTextarea) {
                            contentTextarea.value = generatedAiData.content;
                            if (window.hugeRTE) {
                                try {
                                    const editor = window.hugeRTE.get(contentTextarea.id) || (window.hugeRTE
                                        .editors && window.hugeRTE.editors[0]);
                                    if (editor && typeof editor.setContent === 'function') {
                                        editor.setContent(generatedAiData.content);
                                    }
                                } catch (e) {
                                    console.log(e);
                                }
                            } else if (window.tinymce) {
                                try {
                                    const editor = window.tinymce.get(contentTextarea.id) || window.tinymce
                                        .activeEditor;
                                    if (editor && typeof editor.setContent === 'function') {
                                        editor.setContent(generatedAiData.content);
                                    }
                                } catch (e) {
                                    console.log(e);
                                }
                            }
                        }
                    }

                    // 5. Tags
                    if (applyTags && Array.isArray(generatedAiData.tags) && generatedAiData.tags.length >
                        0) {
                        try {
                            const tagsSelect = document.getElementById('blog-tags');
                            if (tagsSelect) {
                                if (tagsSelect.tomselect) {
                                    tagsSelect.tomselect.clear();
                                    generatedAiData.tags.forEach(tag => {
                                        tagsSelect.tomselect.addOption({
                                            value: tag,
                                            text: tag
                                        });
                                        tagsSelect.tomselect.addItem(tag);
                                    });
                                } else if (typeof $ !== 'undefined' && $(tagsSelect).data('select2')) {
                                    $(tagsSelect).val(generatedAiData.tags).trigger('change');
                                } else {
                                    $(tagsSelect).val(generatedAiData.tags);
                                }
                            }
                        } catch (e) {
                            console.warn('Tags apply error:', e);
                        }
                    }

                    // 6. SEO Metadata
                    if (applySeo) {
                        try {
                            if (generatedAiData.seo_title) {
                                const seoTitleInput = document.querySelector(
                                    'input[name="metadata[seo_title]"]');
                                if (seoTitleInput) seoTitleInput.value = generatedAiData.seo_title;
                            }
                            if (generatedAiData.seo_description) {
                                const seoDescInput = document.querySelector(
                                    'textarea[name="metadata[seo_description]"]');
                                if (seoDescInput) seoDescInput.value = generatedAiData.seo_description;
                            }
                            if (Array.isArray(generatedAiData.seo_keywords)) {
                                const seoKwSelect = document.querySelector('.seo-keywords-select');
                                if (seoKwSelect) {
                                    if (seoKwSelect.tomselect) {
                                        seoKwSelect.tomselect.clear();
                                        generatedAiData.seo_keywords.forEach(kw => {
                                            seoKwSelect.tomselect.addOption({
                                                value: kw,
                                                text: kw
                                            });
                                            seoKwSelect.tomselect.addItem(kw);
                                        });
                                    } else if (typeof $ !== 'undefined' && $(seoKwSelect).data('select2')) {
                                        $(seoKwSelect).val(generatedAiData.seo_keywords).trigger('change');
                                    }
                                }
                            }
                        } catch (e) {
                            console.warn('SEO apply error:', e);
                        }
                    }

                    showAiToast('success', @json(__('labels.applied_successfully') ?? 'AI content applied to blog form successfully!'));

                    // Close Modal
                    if (aiModalEl) {
                        const bsModal = window.bootstrap || (typeof bootstrap !== 'undefined' ? bootstrap :
                            null);
                        let closed = false;
                        if (bsModal && bsModal.Modal) {
                            try {
                                const modalInstance = bsModal.Modal.getInstance(aiModalEl) || bsModal.Modal
                                    .getOrCreateInstance(aiModalEl);
                                if (modalInstance) {
                                    modalInstance.hide();
                                    closed = true;
                                }
                            } catch (e) {
                                console.warn('Bootstrap modal hide error:', e);
                            }
                        }
                        if (!closed && typeof $ !== 'undefined' && $.fn && typeof $.fn.modal ===
                            'function') {
                            try {
                                $(aiModalEl).modal('hide');
                                closed = true;
                            } catch (e) {
                                console.warn('jQuery modal hide error:', e);
                            }
                        }
                        if (!closed) {
                            const closeBtn = aiModalEl.querySelector('[data-bs-dismiss="modal"]');
                            if (closeBtn) closeBtn.click();
                        }
                    }
                });
            }
        });
    </script>
@endpush
