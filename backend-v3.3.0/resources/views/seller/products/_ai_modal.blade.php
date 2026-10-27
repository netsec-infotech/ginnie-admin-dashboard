@php
    $aiCategories = \App\Models\Category::query()
        ->whereNull('deleted_at')
        ->where('status', 1)
        ->orderBy('title')
        ->get(['id', 'title']);
@endphp

<!-- AI Product Content & SEO Generator Modal -->
<div class="modal fade" id="aiGeneratorModal" tabindex="-1" aria-labelledby="aiGeneratorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary-lt border-bottom py-3">
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
                        <h4 class="modal-title m-0 fw-bold" id="aiGeneratorModalLabel">
                            {{ __('labels.ai_product_generator') }}</h4>
                        <div class="text-muted small">{{ __('labels.ai_product_generator_desc') }}</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                <div class="row g-4">
                    <!-- Left: Input & Generation Controls -->
                    <div class="col-lg-5 border-end-lg pe-lg-4">
                        <div class="card bg-surface-secondary border shadow-none mb-0">
                            <div class="card-body p-3">
                                <h5 class="card-title mb-3 d-flex align-items-center text-primary">
                                    <i class="ti ti-adjustments me-2 fs-4"></i>{{ __('labels.general_information') }}
                                </h5>

                                <div class="mb-3">
                                    <label class="form-label required">{{ __('labels.product_title') }}</label>
                                    <input type="text" class="form-control" id="aiProductTitle"
                                        placeholder="e.g. Wireless Noise-Cancelling Headphones Pro" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">{{ __('labels.category') }}</label>
                                    <select class="form-select" id="aiProductCategory">
                                        <option value="">{{ __('labels.select_category') }}</option>
                                        @foreach ($aiCategories as $cat)
                                            <option value="{{ $cat->id }}">{{ $cat->getTranslation('title', 'en', false) }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-sm-6">
                                        <label class="form-label">{{ __('labels.tone_of_voice') }}</label>
                                        <select class="form-select" id="aiProductTone">
                                            <option value="persuasive" selected>{{ __('labels.tone_persuasive') }}
                                            </option>
                                            <option value="luxury">{{ __('labels.tone_luxury') }}</option>
                                            <option value="professional">{{ __('labels.tone_professional') }}</option>
                                            <option value="casual">{{ __('labels.tone_casual') }}</option>
                                            <option value="technical">{{ __('labels.tone_technical') }}</option>
                                            <option value="punchy">{{ __('labels.tone_punchy') }}</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label">{{ __('labels.article_length') }}</label>
                                        <select class="form-select" id="aiProductLength">
                                            <option value="standard" selected>{{ __('labels.length_standard') }}
                                            </option>
                                            <option value="concise">{{ __('labels.length_concise') }}</option>
                                            <option value="comprehensive">{{ __('labels.length_comprehensive') }}
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">{{ __('labels.product_highlights_or_features') }}</label>
                                    <textarea class="form-control" id="aiProductKeywords" rows="3"
                                        placeholder="{{ __('labels.product_highlights_placeholder') }}"></textarea>
                                    <small class="form-hint text-muted">Enter key selling points, dimensions, materials,
                                        or features.</small>
                                </div>

                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="form-label mb-0 fw-semibold text-primary">
                                            <i
                                                class="ti ti-prompt me-1"></i>{{ __('labels.custom_prompt_and_instructions') }}
                                        </label>
                                        <span class="badge bg-secondary-lt" style="font-size: 0.65rem;">Optional</span>
                                    </div>

                                    <!-- 1-Click Prompt Preset Chips -->
                                    <div class="d-flex flex-wrap gap-1 mb-2">
                                        <button type="button"
                                            class="btn btn-sm btn-outline-secondary py-0 px-2 rounded-pill ai-prompt-chip"
                                            data-target="#aiProductInstructions"
                                            data-text="Focus on customer pain points and position this product as the ultimate problem solver.">
                                            + Problem-Solution (PAS)
                                        </button>
                                        <button type="button"
                                            class="btn btn-sm btn-outline-secondary py-0 px-2 rounded-pill ai-prompt-chip"
                                            data-target="#aiProductInstructions"
                                            data-text="Emphasize premium craftsmanship, high durability, and superior material quality.">
                                            + Quality & Durability
                                        </button>
                                        <button type="button"
                                            class="btn btn-sm btn-outline-secondary py-0 px-2 rounded-pill ai-prompt-chip"
                                            data-target="#aiProductInstructions"
                                            data-text="Highlight limited-time seasonal pricing, fast dispatch, and 30-day money-back guarantee.">
                                            + Urgency & Guarantee
                                        </button>
                                        <button type="button"
                                            class="btn btn-sm btn-outline-secondary py-0 px-2 rounded-pill ai-prompt-chip"
                                            data-target="#aiProductInstructions"
                                            data-text="Adopt a luxurious, high-end editorial tone emphasizing prestige and exclusivity.">
                                            + Luxury & Prestige
                                        </button>
                                        <button type="button"
                                            class="btn btn-sm btn-outline-secondary py-0 px-2 rounded-pill ai-prompt-chip"
                                            data-target="#aiProductInstructions"
                                            data-text="Use short, punchy bullet points optimized for fast mobile scanning.">
                                            + Mobile Scannable
                                        </button>
                                    </div>

                                    <textarea class="form-control" id="aiProductInstructions" rows="2"
                                        placeholder="e.g. Write in a conversational tone, mention 2-year warranty, include care tips..."></textarea>
                                    <small class="form-hint text-muted">Click any preset chip above or type your own
                                        custom directives.</small>
                                </div>

                                <div class="d-grid mt-4">
                                    <button type="button" class="btn btn-primary btn-lg shadow-sm"
                                        id="aiGenerateBtn">
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
                                        <span id="aiBtnText">{{ __('labels.generate') }}</span>
                                        <span class="spinner-border spinner-border-sm ms-2 d-none" id="aiSpinner"
                                            role="status" aria-hidden="true"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Live Interactive Preview & Selective Checklist -->
                    <div class="col-lg-7 ps-lg-4 d-flex flex-column">
                        <!-- Initial Empty State -->
                        <div id="aiEmptyState" class="text-center py-5 my-auto">
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
                            <h4 class="text-muted fw-semibold mb-1">{{ __('labels.preview_product_copy') }}</h4>
                            <p class="text-muted small max-w-sm mx-auto">
                                Enter a product title and highlights on the left, then click
                                <strong>{{ __('labels.generate_product_copy') }}</strong> to preview rich HTML copy,
                                short summary, tags, and complete SEO meta tags.
                            </p>
                        </div>

                        <!-- Loading State -->
                        <div id="aiLoadingState" class="text-center py-5 my-auto d-none">
                            <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;"
                                role="status"></div>
                            <h4 class="fw-bold text-primary mb-1">{{ __('labels.generating_product_copy') }}</h4>
                            <p class="text-muted small" id="aiLoadingSubtext">Crafting high-converting product
                                description and SEO metadata...</p>
                        </div>

                        <!-- Error State -->
                        <div id="aiErrorBox" class="alert alert-danger d-none mt-3" role="alert"></div>

                        <!-- Result Preview Container -->
                        <div id="aiResultContainer" class="d-none d-flex flex-column h-100">
                            <!-- Selection Checklist Bar -->
                            <div class="bg-primary-lt border rounded p-2 mb-3">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                    <span class="fw-semibold small text-primary d-flex align-items-center">
                                        <i class="ti ti-check me-1"></i>Select fields to apply:
                                    </span>
                                    <div class="d-flex flex-wrap align-items-center gap-3">
                                        <label class="form-check form-check-inline m-0 small">
                                            <input class="form-check-input" type="checkbox" id="aiApplyTitle"
                                                checked>
                                            <span class="form-check-label">{{ __('labels.apply_title') }}</span>
                                        </label>
                                        <label class="form-check form-check-inline m-0 small">
                                            <input class="form-check-input" type="checkbox" id="aiApplyShortDesc"
                                                checked>
                                            <span
                                                class="form-check-label">{{ __('labels.apply_short_description') }}</span>
                                        </label>
                                        <label class="form-check form-check-inline m-0 small">
                                            <input class="form-check-input" type="checkbox" id="aiApplyFullDesc"
                                                checked>
                                            <span class="form-check-label">{{ __('labels.apply_content') }}</span>
                                        </label>
                                        <label class="form-check form-check-inline m-0 small">
                                            <input class="form-check-input" type="checkbox" id="aiApplyTags" checked>
                                            <span
                                                class="form-check-label">{{ __('labels.apply_product_tags') }}</span>
                                        </label>
                                        <label class="form-check form-check-inline m-0 small">
                                            <input class="form-check-input" type="checkbox" id="aiApplySeo" checked>
                                            <span class="form-check-label">{{ __('labels.apply_seo') }}</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Preview Tabs Header -->
                            <ul class="nav nav-tabs nav-fill mb-3" id="aiProductPreviewTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active py-2 small fw-semibold" id="tab-product-desc"
                                        data-bs-toggle="tab" data-bs-target="#pane-product-desc" type="button"
                                        role="tab">
                                        <i class="ti ti-article me-1"></i>{{ __('labels.description') }}
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link py-2 small fw-semibold" id="tab-product-short-desc"
                                        data-bs-toggle="tab" data-bs-target="#pane-product-short-desc" type="button"
                                        role="tab">
                                        <i class="ti ti-notes me-1"></i>{{ __('labels.short_description') }}
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link py-2 small fw-semibold" id="tab-product-tags"
                                        data-bs-toggle="tab" data-bs-target="#pane-product-tags" type="button"
                                        role="tab">
                                        <i class="ti ti-tags me-1"></i>{{ __('labels.tags') }}
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link py-2 small fw-semibold" id="tab-product-seo"
                                        data-bs-toggle="tab" data-bs-target="#pane-product-seo" type="button"
                                        role="tab">
                                        <i class="ti ti-search me-1"></i>{{ __('labels.seo_metadata') }}
                                    </button>
                                </li>
                            </ul>

                            <!-- Preview Tabs Content -->
                            <div class="tab-content flex-grow-1 border rounded p-3 bg-surface overflow-auto"
                                style="max-height: 400px;" id="aiProductPreviewTabsContent">
                                <!-- 1. Description Pane -->
                                <div class="tab-pane fade show active" id="pane-product-desc" role="tabpanel">
                                    <div class="mb-2 d-flex justify-content-between align-items-center">
                                        <h5 class="fw-bold mb-0 text-primary" id="aiPreviewTitle">—</h5>
                                        <span class="badge bg-purple-lt" id="aiPreviewWordCount">0 words</span>
                                    </div>
                                    <hr class="my-2">
                                    <div id="aiPreviewFullDesc" class="prose small" style="line-height: 1.6;"></div>
                                </div>

                                <!-- 2. Short Description Pane -->
                                <div class="tab-pane fade" id="pane-product-short-desc" role="tabpanel">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label
                                            class="form-label text-muted small fw-bold text-uppercase mb-0">{{ __('labels.short_description') }}</label>
                                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2"
                                            id="aiCopyShortDescBtn">
                                            <i class="ti ti-copy me-1"></i>Copy
                                        </button>
                                    </div>
                                    <div class="p-3 bg-surface-secondary border rounded" id="aiPreviewShortDesc">—
                                    </div>
                                </div>

                                <!-- 3. Tags Pane -->
                                <div class="tab-pane fade" id="pane-product-tags" role="tabpanel">
                                    <label
                                        class="form-label text-muted small fw-bold text-uppercase">{{ __('labels.suggested_tags') }}</label>
                                    <div class="d-flex flex-wrap gap-2 mt-2" id="aiPreviewTags"></div>
                                </div>

                                <!-- 4. SEO Pane -->
                                <div class="tab-pane fade" id="pane-product-seo" role="tabpanel">
                                    <!-- Google Search SERP Simulator -->
                                    <div class="card border mb-3 shadow-none bg-white">
                                        <div class="card-body p-3">
                                            <div class="d-flex align-items-center mb-1">
                                                <div class="avatar avatar-xs bg-light rounded-circle me-2 text-muted">
                                                    <i class="ti ti-world"></i>
                                                </div>
                                                <span class="small text-muted text-truncate"
                                                    style="max-width: 320px;">{{ config('app.url') }} &rsaquo;
                                                    products</span>
                                            </div>
                                            <h6 class="text-primary mb-1 fw-bold cursor-pointer"
                                                id="aiPreviewSerpTitle" style="color: #1a0dab !important;">—</h6>
                                            <p class="text-muted small mb-0" id="aiPreviewSerpDesc"
                                                style="font-size: 0.825rem; line-height: 1.4;">—</p>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label
                                            class="form-label text-muted small fw-bold text-uppercase">{{ __('labels.seo_title') }}</label>
                                        <div class="p-2 bg-surface-secondary border rounded fw-semibold text-primary"
                                            id="aiPreviewSeoTitle">—</div>
                                    </div>
                                    <div class="mb-3">
                                        <label
                                            class="form-label text-muted small fw-bold text-uppercase">{{ __('labels.seo_keywords') }}</label>
                                        <div class="d-flex flex-wrap gap-2" id="aiPreviewSeoKeywords"></div>
                                    </div>
                                    <div>
                                        <label
                                            class="form-label text-muted small fw-bold text-uppercase">{{ __('labels.seo_description') }}</label>
                                        <div class="p-2 bg-surface-secondary border rounded small"
                                            id="aiPreviewSeoDesc">—</div>
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
                <button type="button" class="btn btn-success" id="aiApplyBtn" disabled>
                    <i class="ti ti-check me-1"></i>{{ __('labels.apply_to_form') }}
                </button>
            </div>
        </div>
    </div>
</div>
