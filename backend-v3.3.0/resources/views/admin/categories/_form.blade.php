@php
    use App\Models\Setting;
    use App\Enums\SpatieMediaCollectionName;

    $isEdit = isset($category) && $category;
    $hasImage =
        $isEdit &&
        ($category->getFirstMedia(SpatieMediaCollectionName::CATEGORY_IMAGE()) !== null ||
            !empty($category->getFirstMediaUrl('image')));
    $hasBackgroundImage =
        $isEdit &&
        ($category->getFirstMedia(SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE()) !== null ||
            !empty($category->getFirstMediaUrl(SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE())));
    $hasBackgroundAnimation =
        $isEdit &&
        ($category->getFirstMedia(SpatieMediaCollectionName::CATEGORY_BACKGROUND_ANIMATION()) !== null ||
            !empty($category->getFirstMediaUrl(SpatieMediaCollectionName::CATEGORY_BACKGROUND_ANIMATION())));
    $action = $isEdit ? route('admin.categories.update', $category->id) : route('admin.categories.store');
    $pageTitle = $isEdit ? 'Edit Category' : __('labels.create_category');
    $submitLabel = $isEdit ? 'Update Category' : __('labels.create_new_category');
    $selectedParent = old('parent_id', $category->parent_id ?? null);
    $rawBgType = old('background_type', $category->background_type ?? '');
    $backgroundType =
        $rawBgType instanceof \UnitEnum || is_object($rawBgType) ? $rawBgType->value ?? '' : (string) $rawBgType;
    $backgroundColor = old('background_color', $category->background_color ?? '');
    $backgroundColorWeb = old(
        'background_color_web',
        $category->background_color_web ?? ($category->background_color ?? '#ffffff'),
    );
    $backgroundColorApp = old(
        'background_color_app',
        $category->background_color_app ?? ($category->background_color ?? '#0066cc'),
    );
    $fontColor = old('font_color', $category->font_color ?? '');
    $commission = old('commission', $category->commission ?? 0);
    $statusValue = old('status', $category->status ?? \App\Enums\CategoryStatusEnum::ACTIVE());
    $requiresApproval = old('requires_approval', $category->requires_approval ?? true);
    $searchLabels = old('searchLabels', $category->searchLabelSetting?->search_labels ?? []);
    $catalogLanguages = $catalogLanguages ?? collect();
    $titleTranslations = $isEdit ? ($category->getTranslations('title') ?: []) : [];
    $descriptionTranslations = $isEdit ? ($category->getTranslations('description') ?: []) : [];
    $hasTranslationTargets = $catalogLanguages->contains(fn ($language) => $language->code !== 'en');
@endphp

<form class="form-submit" action="{{ $action }}" method="POST" enctype="multipart/form-data"
    data-redirect="{{ route('admin.categories.index') }}">
    @csrf
    <input type="hidden" name="id" id="category-id" value="{{ $category->id ?? '' }}">

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">{{ $pageTitle }}</h3>
                <p class="text-secondary mb-0">{{ __('labels.categories') }}</p>
            </div>
        </div>
        <div class="card-body">
            <div class="mb-4">
                <h4 class="mb-2">{{ __('labels.basic_information') }}</h4>
                <div class="border-top"></div>
            </div>

            @if ($aiTranslationEnabled && $hasTranslationTargets)
                <div class="d-flex justify-content-end mb-3">
                    <button type="button" class="btn btn-outline-primary" id="category-ai-translate-btn"
                        data-category-id="{{ $category->id ?? '' }}"
                        data-source-required="{{ __('labels.ai_translation_source_required') }}"
                        data-translating="{{ __('labels.translating') }}"
                        data-success="{{ __('labels.ai_translations_populated') }}"
                        data-failed="{{ __('labels.ai_translation_request_failed') }}"
                        data-idle="{{ __('labels.translate_with_ai') }}">
                        <span class="spinner-border spinner-border-sm d-none me-1" aria-hidden="true"></span>
                        <span class="btn-text">{{ __('labels.translate_with_ai') }}</span>
                    </button>
                </div>
            @endif

            @if ($catalogLanguages->count() > 1)
                <ul class="nav nav-tabs nav-fill mb-3" id="category-language-tabs" role="tablist">
                    @foreach ($catalogLanguages as $language)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ $language->code === 'en' ? 'active fw-bold' : '' }}"
                                id="category-tab-{{ $language->code }}" data-bs-toggle="tab"
                                data-bs-target="#category-pane-{{ $language->code }}" type="button" role="tab">
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

            <div class="tab-content" id="category-language-tab-content">
                @foreach ($catalogLanguages as $language)
                    <div class="tab-pane fade {{ $language->code === 'en' ? 'show active' : '' }}"
                        id="category-pane-{{ $language->code }}" role="tabpanel">
                        <div class="mb-3">
                            <label class="form-label {{ $language->code === 'en' ? 'required' : '' }}">
                                {{ __('labels.category_name') }} ({{ $language->name }})
                            </label>
                            <input type="text" class="form-control category-title-input"
                                name="title[{{ $language->code }}]" data-locale="{{ $language->code }}"
                                dir="{{ $language->direction ?? 'ltr' }}" maxlength="255"
                                value="{{ old('title.'.$language->code, $titleTranslations[$language->code] ?? '') }}"
                                placeholder="{{ __('labels.enter_category_name') }}"
                                {{ $language->code === 'en' ? 'required' : '' }}>
                            @error('title.'.$language->code)
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('labels.description') }} ({{ $language->name }})</label>
                            <textarea class="form-control category-description-input" name="description[{{ $language->code }}]"
                                data-locale="{{ $language->code }}" dir="{{ $language->direction ?? 'ltr' }}" rows="3"
                                maxlength="10000" placeholder="{{ __('labels.enter_description') }}">{{ old('description.'.$language->code, $descriptionTranslations[$language->code] ?? '') }}</textarea>
                            @error('description.'.$language->code)
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mb-3">
                <label class="form-label">
                    {{ __('labels.parent_category') }}
                    <x-form-info-tooltip :title="__('messages.category_parent_info_message')" />
                </label>
                <select type="text" class="form-select" id="select-parent-category" name="parent_id">
                    @if ($isEdit && $category->parent)
                        <option value="{{ $category->parent->id }}" selected>{{ $category->parent->getTranslation('title', 'en', false) }}</option>
                    @elseif($selectedParent)
                        <option value="{{ $selectedParent }}" selected>{{ $selectedParent }}</option>
                    @endif
                </select>
                @error('parent_id')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div id="category-details">
                <div class="mb-3">
                    <label class="form-label">{{ __('labels.search_labels') }}</label>
                    <select class="form-select" id="category-search-labels" name="searchLabels[]" multiple>
                        @foreach ($searchLabels as $label)
                            <option value="{{ $label }}" selected>{{ $label }}</option>
                        @endforeach
                    </select>
                    <small class="form-hint">{{ __('labels.category_search_labels_help') }}</small>
                    @error('searchLabels')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                    @error('searchLabels.*')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mt-4 mb-4">
                    <h4 class="mb-2">{{ __('labels.media') }}</h4>
                    <div class="border-top"></div>
                </div>

                <div class="mb-4">
                    <div class="row g-3">
                        <div class="col-md-6 col-xl-4">
                            <label
                                class="form-label {{ $hasImage ? '' : 'required' }}">{{ __('labels.image') }}</label>
                            <input type="file" class="form-control" id="image-upload" name="image"
                                data-image-url="{{ $hasImage ? $category->image : '' }}" />
                            @error('image')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 col-xl-4">
                            <label class="form-label">
                                {{ __('labels.icon') }}
                                <x-form-info-tooltip :title="__('messages.category_icon_info_message')" />
                            </label>
                            <input type="file" class="form-control" id="icon-upload" name="icon"
                                data-image-url="{{ $isEdit && !empty($category->icon) ? $category->icon : '' }}" />
                            @error('icon')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 col-xl-4">
                            <label class="form-label">
                                {{ __('labels.active_icon') }}
                                <x-form-info-tooltip :title="__('messages.category_active_icon_info_message')" />
                            </label>
                            <input type="file" class="form-control" id="active-icon-upload" name="active_icon"
                                data-image-url="{{ $isEdit && !empty($category->active_icon) ? $category->active_icon : '' }}" />
                            @error('active_icon')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="mt-4 mb-4">
                    <h4 class="mb-2">Appearance</h4>
                    <div class="border-top"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label">
                        {{ __('labels.background_type') }}
                        <x-form-info-tooltip :title="__('messages.category_background_type_info_message')" />
                    </label>
                    <select class="form-select" name="background_type" id="background-type-select">
                        <option value="">{{ __('labels.select_background_type') }}</option>
                        <option value="color" {{ $backgroundType === 'color' ? 'selected' : '' }}>
                            {{ __('labels.color') }}
                        </option>
                        <option value="image" {{ $backgroundType === 'image' ? 'selected' : '' }}>
                            {{ __('labels.image') }}
                        </option>
                        <option value="animation" {{ $backgroundType === 'animation' ? 'selected' : '' }}>
                            {{ __('labels.animation') ?? 'Animation' }}
                        </option>
                    </select>
                    <small class="form-text text-muted">{{ __('labels.background_home_page_note') }}</small>
                    @error('background_type')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Background Color (Web & App) --}}
                <div class="mb-3" id="background-color-field"
                    style="{{ $backgroundType === 'color' ? '' : 'display: none;' }}">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label
                                class="form-label required">{{ __('labels.background_color_website') ?? 'Background Color' }}
                            </label>
                            <input type="color" class="form-control form-control-color w-100"
                                name="background_color_web" id="background-color-web-input"
                                value="{{ $backgroundColorWeb }}" />
                            <small class="form-text text-muted">Applied to header on Web/Desktop</small>
                            @error('background_color_web')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label
                                class="form-label required">{{ __('labels.background_color_app') ?? 'Background Color' }}
                            </label>
                            <input type="color" class="form-control form-control-color w-100"
                                name="background_color_app" id="background-color-app-input"
                                value="{{ $backgroundColorApp }}" />
                            <small class="form-text text-muted">Applied to header on Mobile App</small>
                            @error('background_color_app')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Background Image (Web & App) --}}
                <div class="mb-3" id="background-image-field"
                    style="{{ $backgroundType === 'image' ? '' : 'display: none;' }}">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label
                                class="form-label">{{ __('labels.background_image_website') ?? 'Background Image for Website' }} (Desktop)</label>
                            <input type="file" class="form-control" id="background-image-web-upload"
                                name="background_image_web"
                                data-image-url="{{ $isEdit && !empty($category->background_image_web) ? $category->background_image_web : '' }}" />
                            <small class="form-text text-muted">Recommended for desktop screens</small>
                            @error('background_image_web')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label
                                class="form-label">{{ __('labels.background_image_website') ?? 'Background Image for Website' }} (Mobile)</label>
                            <input type="file" class="form-control" id="background-image-web-mobile-upload"
                                name="background_image_web_mobile"
                                data-image-url="{{ $isEdit && !empty($category->background_image_web_mobile) ? $category->background_image_web_mobile : '' }}" />
                            <small class="form-text text-muted">Recommended for mobile web screens</small>
                            @error('background_image_web_mobile')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label
                                class="form-label">{{ __('labels.background_image_app') ?? 'Background Image for App' }}</label>
                            <input type="file" class="form-control" id="background-image-app-upload"
                                name="background_image_app"
                                data-image-url="{{ $isEdit && !empty($category->background_image_app) ? $category->background_image_app : '' }}" />
                            <small class="form-text text-muted">Recommended for mobile app screens</small>
                            @error('background_image_app')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Background Animation (Web & App) --}}
                <div class="mb-3" id="background-animation-field"
                    style="{{ $backgroundType === 'animation' ? '' : 'display: none;' }}">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label
                                class="form-label">{{ __('labels.background_animation_website') ?? 'Background Animation (JSON)' }}
                            </label>
                            <input type="file" class="form-control mb-2" id="background-animation-web-upload"
                                name="background_animation_web" accept=".json,application/json"
                                data-image-url="{{ $isEdit && !empty($category->background_animation_web) ? $category->background_animation_web : '' }}" />
                            @php
                                $catWebAnim = $isEdit ? $category->background_animation_web : '';
                            @endphp
                            <div class="mt-2 border rounded p-2 text-center bg-light {{ empty($catWebAnim) ? 'd-none' : '' }}"
                                id="cat-web-lottie-preview-wrap">
                                <div class="d-flex align-items-center justify-content-between mb-2 px-1">
                                    <span class="text-success small"><i class="ti ti-check me-1"></i>Web
                                        Animation:</span>
                                    <a href="{{ $catWebAnim ?: '#' }}" target="_blank" id="cat-web-lottie-filename"
                                        class="small text-decoration-underline {{ empty($catWebAnim) ? 'd-none' : '' }}">{{ $catWebAnim ? basename(parse_url($catWebAnim, PHP_URL_PATH)) : '' }}</a>
                                </div>
                                <div style="height: 180px; width: 100%;"
                                    class="d-flex align-items-center justify-content-center">
                                    <lottie-player id="cat-web-lottie-player" src="{{ $catWebAnim }}"
                                        background="transparent" speed="1"
                                        style="width: 100%; height: 180px; object-fit: contain;" loop
                                        autoplay></lottie-player>
                                </div>
                            </div>
                            <small class="form-text text-muted d-block mt-1">Lottie JSON file for Web.</small>
                            @error('background_animation_web')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label
                                class="form-label">{{ __('labels.background_animation_app') ?? 'Background Animation (JSON)' }}
                            </label>
                            <input type="file" class="form-control mb-2" id="background-animation-app-upload"
                                name="background_animation_app" accept=".json,application/json"
                                data-image-url="{{ $isEdit && !empty($category->background_animation_app) ? $category->background_animation_app : '' }}" />
                            @php
                                $catAppAnim = $isEdit ? $category->background_animation_app : '';
                            @endphp
                            <div class="mt-2 border rounded p-2 text-center bg-light {{ empty($catAppAnim) ? 'd-none' : '' }}"
                                id="cat-app-lottie-preview-wrap">
                                <div class="d-flex align-items-center justify-content-between mb-2 px-1">
                                    <span class="text-success small"><i class="ti ti-check me-1"></i>App
                                        Animation:</span>
                                    <a href="{{ $catAppAnim ?: '#' }}" target="_blank" id="cat-app-lottie-filename"
                                        class="small text-decoration-underline {{ empty($catAppAnim) ? 'd-none' : '' }}">{{ $catAppAnim ? basename(parse_url($catAppAnim, PHP_URL_PATH)) : '' }}</a>
                                </div>
                                <div style="height: 180px; width: 100%;"
                                    class="d-flex align-items-center justify-content-center">
                                    <lottie-player id="cat-app-lottie-player" src="{{ $catAppAnim }}"
                                        background="transparent" speed="1"
                                        style="width: 100%; height: 180px; object-fit: contain;" loop
                                        autoplay></lottie-player>
                                </div>
                            </div>
                            <small class="form-text text-muted d-block mt-1">Lottie JSON file for Mobile App.</small>
                            @error('background_animation_app')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">{{ __('labels.font_color') }}</label>
                    <input type="color" class="form-control form-control-color w-100" name="font_color"
                        id="font-color-input" value="{{ $fontColor ?: '#000000' }}" />
                    @error('font_color')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div id="category-settings">

                <div class="mt-4 mb-4">
                    <h4 class="mb-2">{{ __('labels.settings') }}</h4>
                    <div class="border-top"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label">
                        {{ __('labels.commission') }}
                        <x-form-info-tooltip :title="__('messages.category_commission_info_message')" />
                    </label>
                    @if (Setting::isSubscriptionEnabled() && Setting::isSystemVendorTypeMultiple())
                        <div class="alert alert-info alert-dismissible" role="alert">
                            <div class="alert-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round" class="icon alert-icon icon-2">
                                    <path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"></path>
                                    <path d="M12 9h.01"></path>
                                    <path d="M11 12h1v4h1"></path>
                                </svg>
                            </div>
                            {{ __('labels.subscription_commission_note') }}
                        </div>
                    @endif
                    <input type="number" class="form-control" name="commission" id="commission"
                        placeholder="{{ __('labels.commission_placeholder') }}" step="0.01" min="0"
                        max="100" value="{{ $commission }}" />
                    <small class="form-text text-muted">Enter commission percentage (0-100)</small>
                    @error('commission')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="status" id="status-switch"
                                value="active" {{ $statusValue === 'active' ? 'checked' : '' }}>
                            <label class="form-check-label" for="status-switch">{{ __('labels.status') }}</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="requires_approval"
                                id="approval-switch" value="1" {{ $requiresApproval ? 'checked' : '' }}>
                            <label class="form-check-label" for="approval-switch">
                                {{ __('labels.requires_approval') }}
                                <x-form-info-tooltip :title="__('messages.category_requires_approval_info_message')" />
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mt-4 mb-4">
                    <h4 class="mb-2">{{ __('labels.seo_settings') }}</h4>
                    <div class="border-top"></div>
                </div>

                <x-seo-fields :metadata="$category?->metadata" />
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end align-items-center flex-wrap gap-2">
            <a href="{{ route('admin.categories.index') }}" class="btn btn-outline-secondary">
                {{ __('labels.cancel') }}
            </a>
            <button type="submit" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                    stroke-linejoin="round" class="icon icon-2">
                    <path d="M12 5l0 14" />
                    <path d="M5 12l14 0" />
                </svg>
                {{ $submitLabel }}
            </button>
        </div>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        function bindCatLottiePreview(inputSelector, playerSelector, wrapSelector, filenameSelector) {
            const inputEl = document.querySelector(inputSelector);
            const player = document.querySelector(playerSelector);
            const wrap = document.querySelector(wrapSelector);
            const filenameLink = document.querySelector(filenameSelector);

            function loadFile(file) {
                if (!file || !player || !wrap) return;
                const reader = new FileReader();
                reader.onload = function(e) {
                    try {
                        player.load(e.target.result);
                        wrap.classList.remove('d-none');
                        if (filenameLink) {
                            filenameLink.textContent = file.name || 'New Lottie JSON';
                            filenameLink.classList.remove('d-none');
                            filenameLink.href = '#';
                        }
                    } catch (err) {
                        console.error('Failed to load category lottie JSON into preview:', err);
                    }
                };
                reader.readAsText(file);
            }

            if (inputEl) {
                inputEl.addEventListener('change', function(e) {
                    if (e.target.files && e.target.files[0]) {
                        loadFile(e.target.files[0]);
                    }
                });
            }

            if (typeof FilePond !== 'undefined') {
                const pond = FilePond.find(inputEl);
                if (pond) {
                    pond.on('addfile', (error, fileItem) => {
                        if (!error && fileItem && fileItem.file) {
                            loadFile(fileItem.file);
                        }
                    });
                    pond.on('removefile', () => {
                        if (wrap) wrap.classList.add('d-none');
                    });
                }
            }
        }

        bindCatLottiePreview('#background-animation-web-upload', '#cat-web-lottie-player',
            '#cat-web-lottie-preview-wrap', '#cat-web-lottie-filename');
        bindCatLottiePreview('#background-animation-app-upload', '#cat-app-lottie-player',
            '#cat-app-lottie-preview-wrap', '#cat-app-lottie-filename');

        const aiButton = document.getElementById('category-ai-translate-btn');
        aiButton?.addEventListener('click', async function() {
            const sourceTitle = document.querySelector('.category-title-input[data-locale="en"]')?.value.trim() || '';
            const sourceDescription = document.querySelector('.category-description-input[data-locale="en"]')?.value.trim() || '';
            if (!sourceTitle && !sourceDescription) {
                if (typeof toastr !== 'undefined') {
                    toastr.warning(aiButton.dataset.sourceRequired);
                }
                return;
            }

            const buttonText = aiButton.querySelector('.btn-text');
            const spinner = aiButton.querySelector('.spinner-border');
            aiButton.disabled = true;
            buttonText.textContent = aiButton.dataset.translating;
            spinner.classList.remove('d-none');

            try {
                const response = await fetch(`${base_url}/admin/translations/ai-translate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({
                        module: 'category',
                        category_id: aiButton.dataset.categoryId || null,
                        fields: {
                            title: sourceTitle,
                            description: sourceDescription,
                        },
                    }),
                });
                const payload = await response.json();
                if (!response.ok || !payload.success) {
                    throw new Error(payload.message || aiButton.dataset.failed);
                }

                Object.entries(payload.data?.translations || {}).forEach(([locale, translations]) => {
                    const title = document.querySelector(`.category-title-input[data-locale="${locale}"]`);
                    const description = document.querySelector(`.category-description-input[data-locale="${locale}"]`);
                    if (title && !title.value.trim() && translations.title) {
                        title.value = translations.title;
                    }
                    if (description && !description.value.trim() && translations.description) {
                        description.value = translations.description;
                    }
                });

                if (typeof toastr !== 'undefined') {
                    toastr.success(aiButton.dataset.success);
                }
            } catch (error) {
                console.error('Category AI translation failed:', error);
                if (typeof toastr !== 'undefined') {
                    toastr.error(aiButton.dataset.failed);
                }
            } finally {
                aiButton.disabled = false;
                buttonText.textContent = aiButton.dataset.idle;
                spinner.classList.add('d-none');
            }
        });
    });
</script>
