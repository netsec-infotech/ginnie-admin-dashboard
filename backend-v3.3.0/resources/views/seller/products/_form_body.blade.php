@php
    use App\Enums\Order\OrderItemStatusEnum;
    use App\Enums\Product\ProductAttachmentModeEnum;
    use App\Enums\Product\ProductImageFitEnum;
    use App\Enums\Product\ProductTypeEnum;
    use App\Enums\Product\ProductVideoTypeEnum;
    $isAdminMode = $isAdminMode ?? false;
    $formAction =
        $formAction ??
        (empty($product) ? route('seller.products.store') : route('seller.products.update', ['id' => $product->id]));
    $indexUrl = $indexUrl ?? route('seller.products.index');
    $selectedSellerId = $selectedSellerId ?? ($product->seller_id ?? null);
    $selectedSellerName = $selectedSellerName ?? ($product->seller?->user?->name ?? '');
    $systemSettings = \App\Models\Setting::systemSettings();
    $catalogLanguages = $catalogLanguages ?? collect();
    $enableAi = $aiTranslationEnabled ?? false;
    $titleTranslations = !empty($product) ? ($product->getTranslations('title') ?: []) : [];
    $shortDescriptionTranslations = !empty($product) ? ($product->getTranslations('short_description') ?: []) : [];
    $descriptionTranslations = !empty($product) ? ($product->getTranslations('description') ?: []) : [];
    $hasTranslationTargets = $catalogLanguages->contains(fn ($language) => $language->code !== 'en');
    $catalogLanguagePayload = $catalogLanguagePayload ?? $catalogLanguages
        ->map(fn ($language) => [
            'code' => $language->code,
            'name' => $language->name,
            'direction' => $language->direction ?? 'ltr',
            'flag_url' => $language->flag_url ?? null,
        ])
        ->values();
@endphp

@isset($product)
    @php
        $visibilityEndpoint = $isAdminMode
            ? route('admin.products.visibility', $product->id)
            : route('seller.products.visibility', $product->id);
    @endphp
    @include('partials._visibility-inspector', ['endpoint' => $visibilityEndpoint])
@endisset

<form id="product-form-submit" method="POST" action="{{ $formAction }}" enctype="multipart/form-data" novalidate>
    @csrf
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">{{ $title }}</h3>
            <div class="card-actions">
                <a href="{{ $indexUrl }}" class="btn btn-secondary">
                    <i class="ti ti-arrow-left me-1"></i> {{ __('labels.back_to_products') }}
                </a>
            </div>
        </div>

        @if ($isAdminMode)
            <div class="card-body border-bottom" id="adminSellerPickerWrap">
                <div class="row align-items-center g-3">
                    <div class="col-md-6">
                        <label class="form-label required mb-1">{{ __('labels.seller') }}</label>
                        <select id="adminSellerPicker" name="seller_id" class="form-select"
                            placeholder="{{ __('labels.select_seller') }}" autocomplete="off">
                            @if ($selectedSellerId)
                                <option value="{{ $selectedSellerId }}" selected>
                                    {{ $selectedSellerName ?: '#' . $selectedSellerId }}</option>
                            @endif
                        </select>
                        <small class="text-muted d-block mt-1">{{ __('labels.seller_picker_hint') }}</small>
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-warning mb-0 py-2 px-3 d-none" id="adminSellerChangeAlert"
                            role="alert">
                            <i class="ti ti-alert-triangle me-1"></i>{{ __('labels.seller_change_clears_pricing') }}
                        </div>
                    </div>
                </div>
            </div>
        @endif
        <div class="card-header">
            <nav class="nav nav-segmented nav-2 w-100" role="tablist">
                <button type="button" id="product-category" class="nav-link active" data-step="1"
                    aria-selected="true">
                    <!-- List icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-category">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M4 4h6v6h-6z" />
                        <path d="M14 4h6v6h-6z" />
                        <path d="M4 14h6v6h-6z" />
                        <path d="M17 17m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" />
                    </svg>
                    Select Category
                </button>
                <button type="button" id="product-info" class="nav-link" data-step="2" aria-selected="false">
                    <!-- Kanban icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-file-info">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                        <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
                        <path d="M11 14h1v4h1" />
                        <path d="M12 11h.01" />
                    </svg>
                    Product Info
                </button>
                <button type="button" id="product-additional-info" class="nav-link" data-step="3"
                    aria-selected="false">
                    <!-- Kanban icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-feather">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M4 20l10 -10m0 -5v5h5m-9 -1v5h5m-9 -1v5h5m-5 -5l4 -4l4 -4" />
                        <path
                            d="M19 10c.638 -.636 1 -1.515 1 -2.486a3.515 3.515 0 0 0 -3.517 -3.514c-.97 0 -1.847 .367 -2.483 1m-3 13l4 -4l4 -4" />
                    </svg>
                    {{ __('labels.additional_info') }}
                </button>
                <button type="button" id="product-variations" class="nav-link" data-step="4" aria-selected="false">
                    <!-- Kanban icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-versions">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M10 5m0 2a2 2 0 0 1 2 -2h6a2 2 0 0 1 2 2v10a2 2 0 0 1 -2 2h-6a2 2 0 0 1 -2 -2z" />
                        <path d="M7 7l0 10" />
                        <path d="M4 8l0 8" />
                    </svg>
                    {{ __('labels.variations') }}
                </button>
                <button type="button" id="product-images" class="nav-link" data-step="5" aria-selected="false">
                    <!-- Kanban icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round"
                        class="icon icon-tabler icons-tabler-outline icon-tabler-layout-collage">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M4 4m0 2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z" />
                        <path d="M10 4l4 16" />
                        <path d="M12 12l-8 2" />
                    </svg>
                    {{ __('labels.images') }}
                </button>
                <button type="button" id="product-policy-features" class="nav-link" data-step="6"
                    aria-selected="false">
                    <!-- Kanban icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round"
                        class="icon icon-tabler icons-tabler-outline icon-tabler-file-description">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                        <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
                        <path d="M9 17h6" />
                        <path d="M9 13h6" />
                    </svg>
                    {{ __('labels.policies_and_features') }}
                </button>
                <button type="button" id="product-pricing-taxes" class="nav-link" data-step="7"
                    aria-selected="false">
                    <!-- Kanban icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round"
                        class="icon icon-tabler icons-tabler-outline icon-tabler-currency-dollar">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M16.7 8a3 3 0 0 0 -2.7 -2h-4a3 3 0 0 0 0 6h4a3 3 0 0 1 0 6h-4a3 3 0 0 1 -2.7 -2" />
                        <path d="M12 3v3m0 12v3" />
                    </svg>
                    {{ __('labels.pricing_and_taxes') }}
                </button>
            </nav>
        </div>

        <div class="card-body">
            <div class="wizard-step" data-step="1">
                <div class="container">
                    <div class="mb-3">
                        <h4>Search Category</h4>
                        <select class="form-select" id="select-category" type="text">
                            <!-- Category options here, add :selected for $product->category_id -->
                        </select>
                    </div>
                    <div class="mb-3">
                        <h4>Browse</h4>
                        {{-- Keep div for jsTree --}}
                        <div id="categories" data-categories="{{ $categories }}"></div>
                        <input type="hidden" id="selected_category" name="category_id"
                            value="{{ $product->category_id ?? '' }}">
                    </div>
                    <div id="categories-tree"></div>
                </div>
            </div>
            <div class="wizard-step d-none" data-step="2">
                <div class="container">
                    <div class="d-flex justify-content-end gap-2 mb-3">
                        @if ($enableAi)
                            <button type="button" class="btn btn-sm btn-outline-primary" id="openAiGeneratorBtn"
                                data-bs-toggle="modal" data-bs-target="#aiGeneratorModal">
                                <i class="ti ti-wand me-1"></i>{{ __('labels.generate_with_ai') }}
                            </button>
                        @endif
                        @if ($enableAi && $hasTranslationTargets)
                            <button type="button" class="btn btn-sm btn-outline-primary" id="product-ai-translate-btn"
                                data-bs-toggle="modal" data-bs-target="#productAiTranslationLanguagesModal"
                                data-product-id="{{ $product->id ?? '' }}"
                                data-source-required="{{ __('labels.ai_translation_source_required') }}"
                                data-translating="{{ __('labels.translating') }}"
                                data-success="{{ __('labels.ai_translations_populated') }}"
                                data-partial="{{ __('labels.ai_translations_partially_populated') }}"
                                data-failed="{{ __('labels.ai_translation_request_failed') }}"
                                data-idle="{{ __('labels.translate_with_ai') }}">
                                <span class="spinner-border spinner-border-sm d-none me-1" aria-hidden="true"></span>
                                <span class="btn-text">{{ __('labels.translate_with_ai') }}</span>
                            </button>
                        @endif
                    </div>

                    @if ($catalogLanguages->count() > 1)
                        <ul class="nav nav-tabs nav-fill mb-3" id="product-language-tabs" role="tablist">
                            @foreach ($catalogLanguages as $language)
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $language->code === 'en' ? 'active fw-bold' : '' }}"
                                        data-bs-toggle="tab" data-bs-target="#product-pane-{{ $language->code }}"
                                        type="button" role="tab">
                                        @if (!empty($language->flag_url))
                                            <img src="{{ $language->flag_url }}" alt="" class="avatar avatar-xs rounded me-1"
                                                style="width: 16px; height: 12px; object-fit: cover;">
                                        @endif
                                        {{ $language->name }}
                                        @if ($language->code === 'en')
                                            <span class="badge bg-primary-subtle text-primary ms-1">{{ __('labels.default') }}</span>
                                        @endif
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="tab-content" id="product-language-tab-content">
                        @foreach ($catalogLanguages as $language)
                            <div class="tab-pane fade {{ $language->code === 'en' ? 'show active' : '' }}"
                                id="product-pane-{{ $language->code }}" role="tabpanel">
                                <div class="mb-3">
                                    <label class="form-label {{ $language->code === 'en' ? 'required' : '' }}">
                                        {{ __('labels.product_title') }} ({{ $language->name }})
                                    </label>
                                    <input type="text" class="form-control product-title-input"
                                        name="title[{{ $language->code }}]" data-locale="{{ $language->code }}"
                                        dir="{{ $language->direction ?? 'ltr' }}" maxlength="255"
                                        value="{{ old('title.'.$language->code, $titleTranslations[$language->code] ?? '') }}"
                                        {{ $language->code === 'en' ? 'required' : '' }}>
                                    @error('title.'.$language->code)
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="mb-3">
                                    <label class="form-label {{ $language->code === 'en' ? 'required' : '' }}">
                                        {{ __('labels.short_description') }} ({{ $language->name }})
                                    </label>
                                    <textarea class="form-control product-short-description-input" name="short_description[{{ $language->code }}]"
                                        data-locale="{{ $language->code }}" dir="{{ $language->direction ?? 'ltr' }}" rows="3"
                                        maxlength="255" {{ $language->code === 'en' ? 'required' : '' }}>{{ old('short_description.'.$language->code, $shortDescriptionTranslations[$language->code] ?? '') }}</textarea>
                                    @error('short_description.'.$language->code)
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="mb-3">
                                    <label class="form-label {{ $language->code === 'en' ? 'required' : '' }}">
                                        {{ __('labels.description') }} ({{ $language->name }})
                                    </label>
                                    <textarea class="form-control hugerte-mytextarea product-description-input"
                                        name="description[{{ $language->code }}]" data-locale="{{ $language->code }}"
                                        id="{{ $language->code === 'en' ? 'productDescriptionField' : 'productDescriptionField-'.$language->code }}"
                                        dir="{{ $language->direction ?? 'ltr' }}" rows="5"
                                        {{ $language->code === 'en' ? 'required' : '' }}>{{ old('description.'.$language->code, $descriptionTranslations[$language->code] ?? '') }}</textarea>
                                    @error('description.'.$language->code)
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <!-- Custom Product Sections (User-friendly builder) -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0">{{ __('labels.custom_product_sections') }}</label>
                            <button type="button" class="btn btn-outline-primary" id="addSectionBtn">
                                <i class="ti ti-plus me-1"></i>{{ __('labels.add_section') }}
                            </button>
                        </div>
                        <div id="customSectionsContainer" class="vstack gap-3">
                            @php $sectionIndex = 0; @endphp
                            @if (!empty($product) && $product->customProductSections)
                                @foreach ($product->customProductSections as $sec)
                                    <div class="card border section-item" data-index="{{ $sectionIndex }}">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h4 class="mb-0">{{ __('labels.section') }} #<span
                                                        class="section-number">{{ $sectionIndex + 1 }}</span></h4>
                                                <div class="d-flex gap-2">
                                                    <button type="button"
                                                        class="btn btn-outline-danger remove-section">{{ __('labels.remove') }}</button>
                                                </div>
                                            </div>
                                            <input type="hidden" name="custom_sections[{{ $sectionIndex }}][id]"
                                                value="{{ $sec->id }}">
                                            <hr>
                                            <div class="row g-2">
                                                <div class="col-md-4">
                                                    <label
                                                        class="form-label required">{{ __('labels.title') }}</label>
                                                    <input type="text" class="form-control"
                                                        name="custom_sections[{{ $sectionIndex }}][title]"
                                                        value="{{ $sec->title }}">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">{{ __('labels.description') }}</label>
                                                    <input type="text" class="form-control"
                                                        name="custom_sections[{{ $sectionIndex }}][description]"
                                                        value="{{ $sec->description }}">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">{{ __('labels.sort_order') }}</label>
                                                    <input type="number" min="0" class="form-control"
                                                        name="custom_sections[{{ $sectionIndex }}][sort_order]"
                                                        value="{{ (int) ($sec->sort_order ?? 0) }}">
                                                </div>
                                            </div>
                                            <div class="mt-3">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <strong class="mb-0">{{ __('labels.fields') }}</strong>
                                                    <button type="button"
                                                        class="btn btn-outline-secondary add-field">{{ __('labels.add_field') }}</button>
                                                </div>
                                                <div class="vstack gap-2 fields-container">
                                                    @php $fieldIndex = 0; @endphp
                                                    @foreach ($sec->fields ?? [] as $fld)
                                                        <div class="border rounded p-2 field-item"
                                                            data-index="{{ $fieldIndex }}">
                                                            <div
                                                                class="d-flex justify-content-between align-items-center mb-2">
                                                                <span>{{ __('labels.field') }} #<span
                                                                        class="field-number">{{ $fieldIndex + 1 }}</span></span>
                                                                <button type="button"
                                                                    class="btn btn-link text-danger p-0 remove-field">{{ __('labels.remove') }}</button>
                                                            </div>
                                                            <input type="hidden"
                                                                name="custom_sections[{{ $sectionIndex }}][fields][{{ $fieldIndex }}][id]"
                                                                value="{{ $fld->id }}">
                                                            <div class="row g-2 align-items-end">
                                                                <div class="col-md-3">
                                                                    <label
                                                                        class="form-label">{{ __('labels.title') }}</label>
                                                                    <input type="text" class="form-control"
                                                                        name="custom_sections[{{ $sectionIndex }}][fields][{{ $fieldIndex }}][title]"
                                                                        value="{{ $fld->title }}">
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label
                                                                        class="form-label">{{ __('labels.description') }}</label>
                                                                    <input type="text" class="form-control"
                                                                        name="custom_sections[{{ $sectionIndex }}][fields][{{ $fieldIndex }}][description]"
                                                                        value="{{ $fld->description }}">
                                                                </div>
                                                                <div class="col-md-2">
                                                                    <label
                                                                        class="form-label">{{ __('labels.sort_order') }}</label>
                                                                    <input type="number" min="0"
                                                                        class="form-control"
                                                                        name="custom_sections[{{ $sectionIndex }}][fields][{{ $fieldIndex }}][sort_order]"
                                                                        value="{{ (int) ($fld->pivot->sort_order ?? 0) }}">
                                                                </div>
                                                                <div class="col-md-3">
                                                                    <label
                                                                        class="form-label">{{ __('labels.image') }}</label>
                                                                    <input type="file"
                                                                        class="form-control field-image-input"
                                                                        name="custom_sections[{{ $sectionIndex }}][fields][{{ $fieldIndex }}][image]"
                                                                        data-image-url="{{ $fld->image }}">
                                                                </div>
                                                            </div>
                                                        </div>
                                                        @php $fieldIndex++; @endphp
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @php $sectionIndex++; @endphp
                                @endforeach
                            @endif
                        </div>
                        <small class="form-hint">{{ __('labels.custom_sections_hint') }}</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('labels.tags') }}</label>
                        <select class="form-select product-tags" name="tags[]" multiple>
                            <option value="">{{ __('labels.select_tags') }}</option>
                            @php
                                $tags = [];
                                if (!empty($product->tags)) {
                                    $tags = is_string($product->tags)
                                        ? json_decode($product->tags, true)
                                        : $product->tags ?? [];
                                }
                            @endphp

                            @foreach ($tags as $tag)
                                <option value="{{ $tag }}" selected>{{ $tag }}</option>
                            @endforeach
                        </select>
                        <small class="form-hint">{{ __('labels.select_multiple_tags') }}</small>
                    </div>
                    <hr>
                    <x-seo-fields :metadata="$product->metadata ?? null" />
                </div>
            </div>
            <div class="wizard-step d-none" data-step="3">
                <div class="container">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0">{{ __('labels.brand') }}</label>
                            @if($isAdminMode ?? false)
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#brand-modal">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-2" width="18" height="18" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 5l0 14"/>
                                        <path d="M5 12l14 0"/>
                                    </svg>
                                    {{ __('labels.add_brand') }}
                                </button>
                            @endif
                        </div>
                        <input type="hidden" id="selected-brand" value="{{ $product->brand_id ?? '' }}">
                        <select class="form-select" name="brand_id" id="select-brand">
                            @if (!empty($product->brand))
                                <option value="{{ $product->brand_id }}" selected>{{ $product->brand->getTranslation('title', 'en', false) }}
                                </option>
                            @endif
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('labels.made_in') }}</label>
                        <input type="text" class="form-control" name="made_in"
                            value="{{ $product->made_in ?? '' }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            {{ __('labels.hsn_code') }}
                            <x-form-info-tooltip :title="__('messages.product_hsn_code_info_message')" />
                        </label>
                        <input type="text" class="form-control" name="hsn_code"
                            value="{{ $product->hsn_code ?? '' }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            {{ __('labels.indicator') }}
                            <x-form-info-tooltip :title="__('messages.product_indicator_info_message')" />
                        </label>
                        <select class="form-select" name="indicator">
                            <option value="">{{ __('labels.select_indicator') }}</option>
                            @foreach (\App\Enums\Product\ProductIndicatorEnum::values() as $type)
                                <option value="{{ $type }}"
                                    {{ !empty($product->indicator) && $product->indicator == $type ? 'selected' : '' }}>
                                    {{ Str::replace('_', ' ', $type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">
                            {{ __('labels.base_prep_time') }}
                            <x-form-info-tooltip :title="__('messages.product_base_prep_time_info_message')" />
                        </label>
                        <div class="input-group mb-2">
                            <input type="number" min="0" class="form-control" name="base_prep_time"
                                value="{{ $product->base_prep_time ?? '' }}">
                            <span class="input-group-text"> {{ __('labels.minutes') }} </span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Custom Fields</label>
                        <div id="customFieldsContainer" class="vstack gap-2"
                            data-existing='@json($product->custom_fields ?? new \stdClass())'></div>
                        <button type="button" class="btn btn-outline-primary mt-2" id="addCustomFieldBtn">
                            <i class="ti ti-plus me-1"></i> Add Field
                        </button>
                        <small class="form-hint d-block mt-1">Add any additional product information as key/value
                            pairs.</small>
                    </div>
                </div>
            </div>
            <div class="wizard-step d-none" data-step="4">
                <div class="container">
                    <div id="attributes" data-attributes="{{ $attributes }}" data-catalog-languages='@json($catalogLanguagePayload)'></div>
                    <div class="mb-3">
                        <label class="form-label required">{{ __('labels.product_type') }}</label>
                        <select class="form-select text-capitalize" name="type" id="productType"
                            {{ !empty($product->type) ? 'readonly' : '' }}>
                            <option value="">{{ __('labels.select_type') }}</option>
                            @foreach (ProductTypeEnum::values() as $type)
                                <option value="{{ $type }}"
                                    {{ !empty($product->type) && $product->type == $type ? 'selected' : '' }}>
                                    {{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div id="simpleProductSection" class="d-none">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label required">{{ __('labels.barcode') }}</label>
                                    <input type="text" class="form-control" name="barcode"
                                        value="{{ $singleProductVariant->barcode ?? '' }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">{{ __('labels.weight') }}</label>
                                    <div class="input-group">
                                        <input type="number" min="0" class="form-control" name="weight"
                                            value="{{ $singleProductVariant->weight ?? '' }}"><span
                                            class="input-group-text">kg</span>
                                    </div>

                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">{{ __('labels.height') }}</label>
                                    <div class="input-group">
                                        <input type="number" min="0" class="form-control" name="height"
                                            value="{{ $singleProductVariant->height ?? '' }}"><span
                                            class="input-group-text">CM</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">{{ __('labels.length') }}</label>
                                    <div class="input-group">
                                        <input type="number" min="0" class="form-control" name="length"
                                            value="{{ $singleProductVariant->length ?? '' }}"><span
                                            class="input-group-text">CM</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">{{ __('labels.breadth') }}</label>
                                    <div class="input-group">
                                        <input type="number" min="0" class="form-control" name="breadth"
                                            value="{{ $singleProductVariant->breadth ?? '' }}"><span
                                            class="input-group-text">CM</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="variationsSection" class="d-none">
                        {{-- Scope variant-specific dark-mode styles without affecting other product form cards. --}}
                        <div class="card border-0 bg-light product-variants-panel">
                            <div class="card-header bg-transparent border-bottom gap-1">
                                <h4 class="card-title mb-0">
                                    <i class="fas fa-layer-group me-2"></i>Product Variations
                                </h4>
                                <p class="text-muted mb-0 small ms-2">Add attributes and their values to create
                                    product
                                    variants</p>
                            </div>
                            <div class="card-body">
                                <!-- Attributes Management -->
                                <div class="mb-4">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="mb-0">Attributes</h5>
                                        <button type="button" class="btn btn-primary" id="addAttributeBtn">
                                            <i class="ti ti-plus"></i> Add Attribute
                                        </button>
                                    </div>
                                    <div id="attributesContainer">
                                        <!-- Dynamic attributes will be added here -->
                                    </div>
                                </div>

                                <!-- Generate Variants Button -->
                                <div class="mb-4 text-center">
                                    <button type="button" class="btn btn-success" id="generateVariantsBtn" disabled>
                                        <i class="ti ti-wand"></i> Generate Variants
                                    </button>
                                </div>

                                <!-- Variants Table -->
                                <div id="variantsContainer" class="card mb-4">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="card-title mb-0">Product Variants</h5>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-outline-success"
                                                id="addRemovedVariantBtn" data-bs-toggle="modal"
                                                data-bs-target="#addRemovedVariantModal" disabled>
                                                <i class="ti ti-plus me-1"></i>Add Removed Variant
                                            </button>
                                            <button type="button" class="btn btn-outline-danger"
                                                id="removeAllVariantsBtn">
                                                <i class="ti ti-trash me-1"></i>Remove All
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div id="variantsList" class="row g-3">
                                            <!-- Dynamic variants will be added here -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="wizard-step d-none" data-step="5">
                <div class="container">
                    <div class="mb-3">
                        <label class="form-label required">{{ __('labels.main_image') }}</label>
                        <x-filepond_image name="main_image" imageUrl="{{ $product->main_image ?? '' }}" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('labels.additional_images') }}</label>
                        <input type="file" name="additional_images[]" class="form-control"
                            data-images='@json($product->additional_images ?? [])' multiple>
                        <small class="form-hint">You can select multiple images at once</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            {{ __('labels.image_fit') }}
                            <x-form-info-tooltip :title="__('messages.product_image_fit_info_message')" />
                        </label>
                        <select class="form-select text-capitalize" name="image_fit">
                            @foreach (ProductImageFitEnum::values() as $value)
                                <option value="{{ $value }}"
                                    {{ !empty($product->image_fit) && $product->image_fit == $value ? 'selected' : '' }}>
                                    {{ Str::replace('_', ' ', $value) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('labels.video_type') }}</label>
                        <select class="form-select text-capitalize" name="video_type" id="videoType">
                            <option value="">{{ __('labels.select_video_type') }}</option>
                            @foreach (ProductVideoTypeEnum::values() as $type)
                                <option value="{{ $type }}"
                                    {{ !empty($product->video_type) && $product->video_type == $type ? 'selected' : '' }}>
                                    {{ Str::replace('_', ' ', $type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('labels.video_link') }}</label>
                        <input type="url" class="form-control" name="video_link"
                            value="{{ $product->video_link ?? '' }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('labels.video_upload') }}</label>
                        <input type="file" name="product_video" class="form-control"
                            data-image-url="{{ $product->product_video ?? '' }}">
                    </div>
                </div>
            </div>
            <div class="wizard-step d-none" data-step="6">
                <div class="container">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">
                                    {{ __('labels.minimum_order_quantity') }}
                                    <x-form-info-tooltip :title="__('messages.product_minimum_order_quantity_info_message')" />
                                </label>
                                <input type="number" class="form-control" name="minimum_order_quantity"
                                    min="1" value="{{ $product->minimum_order_quantity ?? '' }}">
                                <small class="form-hint">By Default Minimum Quantity is 1</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">
                                    {{ __('labels.quantity_step_size') }}
                                    <x-form-info-tooltip :title="__('messages.product_quantity_step_size_info_message')" />
                                </label>
                                <input type="number" class="form-control" name="quantity_step_size" min="1"
                                    value="{{ $product->quantity_step_size ?? '' }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">
                                    {{ __('labels.total_allowed_quantity') }}
                                    <x-form-info-tooltip :title="__('messages.product_total_allowed_quantity_info_message')" />
                                </label>
                                <input type="number" class="form-control" name="total_allowed_quantity"
                                    min="0" value="{{ $product->total_allowed_quantity ?? '' }}">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">{{ __('labels.is_returnable') }}</label>
                                <select class="form-select" name="is_returnable">
                                    <option value="">{{ __('labels.select_option') }}</option>
                                    <option value="1"
                                        {{ !empty($product->is_returnable) && $product->is_returnable == '1' ? 'selected' : '' }}>
                                        {{ __('labels.yes') }}
                                    </option>
                                    <option value="0" {{ empty($product->is_returnable) ? 'selected' : '' }}>
                                        {{ __('labels.no') }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">{{ __('labels.is_cancelable') }}</label>
                                <select class="form-select" name="is_cancelable">
                                    <option value="">{{ __('labels.select_option') }}</option>
                                    <option value="1"
                                        {{ !empty($product->is_cancelable) && $product->is_cancelable == '1' ? 'selected' : '' }}>
                                        {{ __('labels.yes') }}
                                    </option>
                                    <option value="0" {{ empty($product->is_cancelable) ? 'selected' : '' }}>
                                        {{ __('labels.no') }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">
                                    {{ __('labels.cancelable_till') }}
                                    <x-form-info-tooltip :title="__('messages.product_cancelable_till_info_message')" />
                                </label>
                                <select class="form-select text-capitalize" name="cancelable_till">
                                    <option value="">{{ __('labels.select_option') }}</option>
                                    <option value="{{ OrderItemStatusEnum::PENDING() }}"
                                        {{ !empty($product->cancelable_till) && $product->cancelable_till == OrderItemStatusEnum::PENDING() ? 'selected' : '' }}>
                                        {{ Str::replace('_', ' ', OrderItemStatusEnum::PENDING()) }}
                                    </option>
                                    <option value="{{ OrderItemStatusEnum::AWAITING_STORE_RESPONSE() }}"
                                        {{ !empty($product->cancelable_till) && $product->cancelable_till == OrderItemStatusEnum::AWAITING_STORE_RESPONSE() ? 'selected' : '' }}>
                                        {{ Str::replace('_', ' ', OrderItemStatusEnum::AWAITING_STORE_RESPONSE()) }}
                                    </option>
                                    <option value="{{ OrderItemStatusEnum::ACCEPTED() }}"
                                        {{ !empty($product->cancelable_till) && $product->cancelable_till == OrderItemStatusEnum::ACCEPTED() ? 'selected' : '' }}>
                                        {{ Str::replace('_', ' ', OrderItemStatusEnum::ACCEPTED()) }}
                                    </option>
                                    <option value="{{ OrderItemStatusEnum::PREPARING() }}"
                                        {{ !empty($product->cancelable_till) && $product->cancelable_till == OrderItemStatusEnum::PREPARING() ? 'selected' : '' }}>
                                        {{ Str::replace('_', ' ', OrderItemStatusEnum::PREPARING()) }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">
                                    {{ __('labels.is_attachment_required') }}
                                    <x-form-info-tooltip :title="__('messages.product_is_attachment_required_info_message')" />
                                </label>
                                <select class="form-select" name="is_attachment_required">
                                    <option value="">{{ __('labels.select_option') }}</option>
                                    <option value="1"
                                        {{ !empty($product->is_attachment_required) && $product->is_attachment_required == '1' ? 'selected' : '' }}>
                                        {{ __('labels.yes') }}
                                    </option>
                                    <option value="0"
                                        {{ empty($product->is_attachment_required) ? 'selected' : '' }}>
                                        {{ __('labels.no') }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">
                                    {{ __('labels.attachment_mode') }}
                                    <x-form-info-tooltip :title="__('messages.product_attachment_mode_info_message')" />
                                </label>
                                <select class="form-select text-capitalize" name="attachment_mode">
                                    @foreach (ProductAttachmentModeEnum::values() as $value)
                                        <option value="{{ $value }}"
                                            {{ !empty($product->attachment_mode) && $product->attachment_mode == $value ? 'selected' : '' }}>
                                            {{ $value }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">
                                    {{ __('labels.featured_product') }}
                                    <x-form-info-tooltip :title="__('messages.product_featured_info_message')" />
                                </label>
                                <select class="form-select" name="featured">
                                    <option value="">{{ __('labels.select_option') }}</option>
                                    <option value="1"
                                        {{ !empty($product->featured) && $product->featured == '1' ? 'selected' : '' }}>
                                        {{ __('labels.yes') }}
                                    </option>
                                    <option value="0" {{ empty($product->featured) ? 'selected' : '' }}>
                                        {{ __('labels.no') }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">
                                    {{ __('labels.requires_otp') }}
                                    <x-form-info-tooltip :title="__('messages.require_otp_before_delivery')" />
                                </label>
                                <select class="form-select" name="requires_otp">
                                    <option value="">{{ __('labels.select_option') }}</option>
                                    <option value="1"
                                        {{ !empty($product->requires_otp) && $product->requires_otp == '1' ? 'selected' : '' }}>
                                        {{ __('labels.yes') }}
                                    </option>
                                    <option value="0" {{ empty($product->requires_otp) ? 'selected' : '' }}>
                                        {{ __('labels.no') }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3 returnable-days">
                                <label class="form-label">{{ __('labels.returnable_days') }}</label>
                                <input type="number" class="form-control" name="returnable_days" min="0"
                                    value="{{ $product->returnable_days ?? '' }}">
                                <small class="form-hint">Required if Product is returnable</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">{{ __('labels.warranty_period') }}</label>
                                <input type="text" class="form-control" name="warranty_period"
                                    value="{{ $product->warranty_period ?? '' }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">{{ __('labels.guarantee_period') }}</label>
                                <input type="text" class="form-control" name="guarantee_period"
                                    value="{{ $product->guarantee_period ?? '' }}">
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            <div class="wizard-step d-none" data-step="7">
                <div class="container">
                    <div id="storePricingSection" data-lbl-empty="{{ __('labels.no_store_pricing_added_hint') }}"
                        data-lbl-all-added="{{ __('labels.all_stores_added') }}"
                        data-lbl-no-match="{{ __('labels.no_matching_records_found') }}"
                        data-lbl-loading="{{ __('labels.loading') }}"
                        data-lbl-add-store="{{ __('labels.add_store') }}"
                        data-lbl-remove="{{ __('labels.remove') }}" data-lbl-price="{{ __('labels.price') }}"
                        data-lbl-special-price="{{ __('labels.special_price') }} ({{ __('labels.optional') }})"
                        data-lbl-cost="{{ __('labels.cost') }}" data-lbl-stock="{{ __('labels.stock') }}"
                        data-lbl-sku="{{ __('labels.sku') }}" data-lbl-variant="{{ __('labels.variant') }}"
                        data-lbl-stores-added="{{ __('labels.stores_added_count') }}">
                        <div class="mb-3">
                            <label class="form-label">
                                {{ __('labels.tax_group') }}
                                <x-form-info-tooltip :title="__('messages.product_tax_group_info_message')" />
                            </label>
                            <select class="form-select" name="tax_groups[]" multiple id="select-tax-group">
                                @if (!empty($product))
                                    @foreach ($product->taxClasses as $taxClass)
                                        <option value="{{ $taxClass->id }}" selected>{{ $taxClass->title }}
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                            <small class="form-hint">{{ __('labels.select_multiple_tax_groups') }}</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label d-block">
                                {{ __('labels.is_inclusive_tax') }}
                                <x-form-info-tooltip :title="__('messages.product_is_inclusive_tax_info_message')" />
                            </label>
                            <!-- Hidden fallback so unchecked checkbox submits 0 -->
                            <input type="hidden" name="is_inclusive_tax" value="0">
                            <label class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_inclusive_tax"
                                    value="1"
                                    {{ !empty($product) ? ($product->is_inclusive_tax ? 'checked' : '') : '' }}>
                            </label>
                            <small class="form-hint">{{ __('labels.tax_inclusive_prices_hint') }}</small>
                        </div>
                        <div class="card">
                            <div class="card-header bg-transparent border-bottom gap-2 px-0 flex-wrap">
                                <div class="flex-grow-1">
                                    <h4 class="card-title mb-0 d-flex align-items-center flex-wrap gap-2">
                                        <span class="ms-2">{{ __('labels.store_wise_pricing') }}</span>
                                        <span id="storePricingCount"
                                            class="badge bg-primary-lt text-primary fw-normal"
                                            style="font-size: 0.75rem;"></span>
                                    </h4>
                                    <p class="text-muted mb-0 small ms-2">{{ __('labels.store_wise_pricing_hint') }}
                                    </p>
                                </div>
                                <div class="dropdown me-2" id="addStorePricingWrapper">
                                    <button type="button" class="btn btn-primary dropdown-toggle"
                                        data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
                                        id="addStorePricingBtn">
                                        <i class="ti ti-plus me-1"></i>{{ __('labels.add_store') }}
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end p-2"
                                        style="min-width: 260px; max-height: 320px; overflow-y: auto;">
                                        <input type="text" class="form-control form-control-sm mb-2"
                                            id="addStorePricingSearch" placeholder="{{ __('labels.search') }}">
                                        <div id="addStorePricingList">
                                            <div class="text-muted small text-center py-2">
                                                {{ __('labels.loading') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body px-0">
                                <div id="simplePricingContainer" class="d-none">
                                    <!-- Simple product pricing will be added here -->
                                </div>
                                <div id="variantPricingContainer">
                                    <div class="accordion accordion-flush border m-2 rounded"
                                        id="storePricingAccordion">
                                        <!-- Dynamic store pricing tables will be added here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-footer d-flex justify-content-between">
            <button type="button" class="btn btn-secondary" id="prevStep">Previous</button>
            <button class="btn btn-primary" id="nextStep">Next</button>
        </div>
    </div>
</form>
@if ($enableAi && $hasTranslationTargets)
    <div class="modal fade" id="productAiTranslationLanguagesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('labels.choose_translation_languages') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('labels.close') }}"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">{{ __('labels.choose_translation_languages_help') }}</p>
                    <label class="form-check border rounded p-3 mb-2">
                        <input class="form-check-input" type="checkbox" id="product-ai-translate-all" checked>
                        <span class="form-check-label fw-semibold">{{ __('labels.all_active_languages') }}</span>
                    </label>
                    <div class="vstack gap-2">
                        @foreach ($catalogLanguages->reject(fn ($language) => $language->code === 'en') as $language)
                            <label class="form-check border rounded p-3 mb-0">
                                <input class="form-check-input product-ai-target-locale" type="checkbox"
                                    value="{{ $language->code }}" data-language-name="{{ $language->name }}" checked>
                                <span class="form-check-label">
                                    {{ $language->name }}
                                    <span class="text-muted">({{ $language->native_name }})</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('labels.cancel') }}</button>
                    <button type="button" class="btn btn-primary" id="product-ai-translate-confirm"
                        data-select-required="{{ __('labels.select_translation_language_required') }}">
                        <span class="spinner-border spinner-border-sm d-none me-1" aria-hidden="true"></span>
                        <span class="btn-text">{{ __('labels.start_translation') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
<div class="modal fade" id="addRemovedVariantModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Removed Variant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="removedVariantsList"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@if ($enableAi)
    @include('seller.products._ai_modal')
@endif
