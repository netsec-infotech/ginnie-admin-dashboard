@php
    use Illuminate\Support\Str;
    $globalSettings = $homeGeneralSettings ?? [];
    $globalBgType = $globalSettings['backgroundType'] ?? '';
    $globalBgColorWeb = $globalSettings['backgroundColorWeb'] ?? ($globalSettings['backgroundColor'] ?? '#ffffff');
    $globalBgColorApp = $globalSettings['backgroundColorApp'] ?? ($globalSettings['backgroundColor'] ?? '#ffffff');
    $globalBgImgWeb = $globalSettings['backgroundImageWeb'] ?? ($globalSettings['backgroundImage'] ?? '');
    $globalBgImgApp = $globalSettings['backgroundImageApp'] ?? ($globalSettings['backgroundImage'] ?? '');
    $globalBgAnimWeb = $globalSettings['backgroundAnimationWeb'] ?? ($globalSettings['backgroundAnimation'] ?? '');
    $globalBgAnimApp = $globalSettings['backgroundAnimationApp'] ?? '';
    $globalFontColor = $globalSettings['fontColor'] ?? '#000000';
    $globalTitle = $globalSettings['title'] ?? 'Home / Global';
    $globalSearchLabels = $globalSettings['searchLabels'] ?? [];
    $globalIcon = $globalSettings['icon'] ?? '';
    $globalActiveIcon = $globalSettings['activeIcon'] ?? '';
@endphp

<div class="row row-cards g-3">
    {{-- Left Column: Global Header & Search to Add & Drag-and-Drop Sortable Categories (Width: col-lg-4) --}}
    <div class="col-12 col-lg-4">
        <div class="card shadow-sm border h-100">
            <div class="card-header bg-surface d-flex align-items-center justify-content-between pb-2">
                <div>
                    <h3 class="card-title fw-bold m-0" style="font-size: 1.1rem;">Storefront Navigation</h3>
                    <p class="text-muted small mb-0 mt-1">
                        <span id="navbar-cat-count">{{ $parentCategories->count() }}</span> categories · search to add
                    </p>
                </div>
                <div class="card-actions">
                    <button type="button" class="btn btn-sm btn-primary px-3 shadow-sm" id="save-category-order">
                        <i class="ti ti-check me-1"></i>Save sorting
                    </button>
                </div>
            </div>

            <div class="card-body p-3">
                {{-- Global Home Header Settings Item (Permanent Top Item) --}}
                <div class="card border rounded-3 p-2 shadow-xs mb-3 hb-global-home-row border-primary bg-primary-lt"
                    id="hb-global-home-btn" data-title="{{ $globalTitle }}" data-icon="{{ $globalIcon }}"
                    data-active-icon="{{ $globalActiveIcon }}">
                    <div class="d-flex align-items-center justify-content-between gap-2">
                        <div class="d-flex align-items-center gap-2 min-w-0 flex-grow-1">
                            <div class="avatar avatar-sm rounded bg-white shadow-xs border text-primary flex-shrink-0">
                                <i class="ti ti-world fs-2"></i>
                            </div>
                            <div class="min-w-0">
                                <div class="fw-bold text-dark text-truncate" id="hb-global-title-display">
                                    {{ $globalTitle }}
                                </div>
                                <div class="text-muted small" style="font-size: 11px;">
                                    Global header appearance
                                </div>
                            </div>
                        </div>
                        <span class="badge bg-primary text-white flex-shrink-0" style="font-size: 10px;">Global</span>
                    </div>
                </div>

                <div class="hr-text text-muted my-5 small">Categories on Home</div>

                {{-- Quick Autocomplete Search: Add a category... --}}
                <div class="mb-3">
                    <select id="add-category-search-select" class="form-select" placeholder="Add a category..."
                        autocomplete="off">
                        <option value="">Add a category...</option>
                        @foreach ($allParentCategories ?? $parentCategories as $cat)
                            <option value="{{ $cat->id }}" data-title="{{ $cat->title }}"
                                data-icon="{{ $cat->icon ?: $cat->image }}"
                                data-active-icon="{{ $cat->active_icon ?: ($cat->icon ?: $cat->image) }}"
                                data-status="{{ $cat->status }}"
                                data-bg-type="{{ is_object($cat->background_type) ? $cat->background_type->value : $cat->background_type ?? 'none' }}"
                                data-bg-color-web="{{ $cat->background_color_web }}"
                                data-bg-color-app="{{ $cat->background_color_app }}"
                                data-bg-image-web="{{ $cat->background_image_web }}"
                                data-bg-image-app="{{ $cat->background_image_app }}"
                                data-bg-anim-web="{{ $cat->background_animation_web }}"
                                data-bg-anim-app="{{ $cat->background_animation_app }}"
                                data-font-color="{{ $cat->font_color }}"
                                {{ $cat->is_home_category ? 'disabled' : '' }}>
                                {{ $cat->title }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Sortable Category Items List --}}
                <div id="categories-sortable-list" class="sortable-container d-flex flex-column gap-2"
                    data-group="categories">
                    @forelse ($parentCategories as $index => $category)
                        @php
                            $bgType = is_object($category->background_type)
                                ? $category->background_type->value
                                : $category->background_type ?? 'none';
                            $catIcon = $category->icon ?: $category->image;
                            $catActiveIcon = $category->active_icon ?: $catIcon;
                        @endphp
                        <div class="card border rounded-3 p-2 bg-body shadow-xs sortable-item hb-category-item-row"
                            data-id="{{ $category->id }}" data-title="{{ $category->title }}"
                            data-icon="{{ $catIcon }}" data-active-icon="{{ $catActiveIcon }}"
                            data-status="{{ $category->status }}" data-bg-type="{{ $bgType }}"
                            data-bg-color-web="{{ $category->background_color_web }}"
                            data-bg-color-app="{{ $category->background_color_app }}"
                            data-bg-image-web="{{ $category->background_image_web }}"
                            data-bg-image-app="{{ $category->background_image_app }}"
                            data-bg-anim-web="{{ $category->background_animation_web }}"
                            data-bg-anim-app="{{ $category->background_animation_app }}"
                            data-font-color="{{ $category->font_color }}"
                            style="cursor: pointer; transition: all 0.15s ease;">
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <div class="d-flex align-items-center gap-2 min-w-0 flex-grow-1">
                                    <div class="hb-cat-drag-handle text-muted" style="cursor: grab;"
                                        title="Drag to reorder">
                                        <i class="ti ti-grip-vertical fs-2"></i>
                                    </div>
                                    <span class="badge bg-primary-lt text-primary fw-bold px-2 py-1 hb-cat-index"
                                        style="font-size: 11px;">
                                        {{ $index + 1 }}
                                    </span>
                                    <span class="fw-semibold text-body text-truncate hb-cat-title-display">
                                        {{ $category->title }}
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                    <span
                                        class="badge bg-secondary-lt text-secondary hb-cat-bgtype-pill d-inline-flex align-items-center gap-1"
                                        style="font-size: 10px; font-weight: 500;">
                                        @if ($bgType === 'color')
                                            <span class="rounded-circle border d-inline-block hb-cat-color-dot"
                                                style="width: 8px; height: 8px; background: {{ $category->background_color_web ?: '#fff' }};"></span>
                                            <span class="hb-cat-bgtype-text">Color</span>
                                        @elseif ($bgType === 'image')
                                            <i class="ti ti-photo" style="font-size: 11px;"></i>
                                            <span class="hb-cat-bgtype-text">Image</span>
                                        @elseif ($bgType === 'animation')
                                            <i class="ti ti-player-play" style="font-size: 11px;"></i>
                                            <span class="hb-cat-bgtype-text">Animation</span>
                                        @else
                                            <span class="hb-cat-bgtype-text">None</span>
                                        @endif
                                    </span>
                                    <button type="button"
                                        class="btn btn-icon btn-sm btn-ghost-danger hb-remove-cat-btn"
                                        data-id="{{ $category->id }}" title="Remove from top navbar"
                                        style="font-size: 16px; line-height: 1;">
                                        &times;
                                    </button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted hb-no-cat-placeholder">
                            <i class="ti ti-category fs-1 mb-2 d-block text-secondary"></i>
                            <span class="small">No categories on home navbar yet. Search above to add.</span>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Right Column: Header Studio & Appearance Inspector (Width: col-lg-8) --}}
    <div class="col-12 col-lg-8">
        <div class="card position-sticky shadow-sm border" style="top: 1rem;" id="hb-cat-appearance-card">
            <div class="card-header bg-surface d-flex align-items-center justify-content-between">
                <div>
                    <h3 class="card-title m-0 d-flex align-items-center gap-2">
                        <i class="ti ti-palette text-primary"></i>
                        <span id="hb-inspector-heading">Global Header Appearance</span>
                    </h3>
                    <div class="card-subtitle text-muted mt-1" id="hb-cat-selected-title">{{ $globalTitle }}</div>
                </div>
                <button type="button" class="btn btn-primary px-3 shadow-sm" id="hb-save-cat-appearance-top-btn">
                    <i class="ti ti-device-floppy me-1"></i>Save appearance
                </button>
            </div>

            {{-- 1. GLOBAL HOME GENERAL SETTINGS FORM --}}
            <form id="hb-global-settings-form" class="card-body" method="post"
                action="{{ route('admin.settings.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="type" value="home_general_settings">

                {{-- General Details --}}
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label required">{{ __('labels.category_title') ?? 'Bar Title' }}</label>
                        <input type="text" class="form-control" name="title" id="hb-global-title-input"
                            placeholder="{{ __('labels.enter_category_title') }}" value="{{ $globalTitle }}" />
                        <small class="form-text text-muted">{{ __('labels.category_title_help') }}</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('labels.search_labels') }}</label>
                        <div class="input-group">
                            <select class="form-select search-labels" id="hb-global-search-labels"
                                name="searchLabels[]" multiple>
                                @if (!empty($globalSearchLabels))
                                    @foreach ($globalSearchLabels as $label)
                                        <option value="{{ $label }}" selected>
                                            {{ Str::replace('_', ' ', $label) }}</option>
                                    @endforeach
                                @endif
                            </select>
                            <button class="btn btn-outline-secondary generate-search-labels-button" type="button"
                                id="hb-generate-labels-btn" title="Generate search labels">
                                <i class="ti ti-sparkles"></i>
                            </button>
                        </div>
                        <small class="form-text text-muted">{{ __('labels.search_labels_help') }}</small>
                    </div>
                </div>

                {{-- Global Icons (Default & Active) --}}
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">{{ __('labels.icon') }}</label>
                        <input type="file" class="form-control" id="hb-global-icon-upload" name="icon"
                            data-image-url="{{ $globalIcon }}" />
                        <small class="form-text text-muted">Default icon for All Categories pill</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('labels.active_icon') }}</label>
                        <input type="file" class="form-control" id="hb-global-active-icon-upload"
                            name="activeIcon" data-image-url="{{ $globalActiveIcon }}" />
                        <small class="form-text text-muted">Active icon for All Categories pill</small>
                    </div>
                </div>

                <div class="hr-text text-muted my-3 small">Background & Styling</div>

                {{-- Background Type --}}
                <div class="mb-3">
                    <label class="form-label fw-bold">{{ __('labels.background_type') }}</label>
                    <select class="form-select" name="backgroundType" id="hb-global-bg-type-select">
                        <option value="">{{ __('labels.select_background_type') }}</option>
                        <option value="color" {{ $globalBgType === 'color' ? 'selected' : '' }}>
                            {{ __('labels.color') }}</option>
                        <option value="image" {{ $globalBgType === 'image' ? 'selected' : '' }}>
                            {{ __('labels.image') }}</option>
                        <option value="animation" {{ $globalBgType === 'animation' ? 'selected' : '' }}>
                            {{ __('labels.animation') ?? 'Animation' }}</option>
                    </select>
                </div>

                {{-- Global Background Color (Web & App) --}}
                <div class="mb-3 {{ $globalBgType === 'color' ? '' : 'd-none' }}" id="hb-global-bg-color-field">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label
                                class="form-label required">{{ __('labels.background_color') ?? 'Background Color' }}
                                (Web)</label>
                            <input type="color" class="form-control form-control-color w-100"
                                name="backgroundColorWeb" id="hb-global-bg-color-web-input"
                                value="{{ $globalBgColorWeb }}" />
                            <small class="form-text text-muted">Applied to header on Web/Desktop</small>
                        </div>
                        <div class="col-md-6">
                            <label
                                class="form-label required">{{ __('labels.background_color') ?? 'Background Color' }}
                                (App)</label>
                            <input type="color" class="form-control form-control-color w-100"
                                name="backgroundColorApp" id="hb-global-bg-color-app-input"
                                value="{{ $globalBgColorApp }}" />
                            <small class="form-text text-muted">Applied to header on Mobile App</small>
                        </div>
                    </div>
                </div>

                {{-- Global Background Image (Web & App) --}}
                <div class="mb-3 {{ $globalBgType === 'image' ? '' : 'd-none' }}" id="hb-global-bg-image-field">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ __('labels.background_image') ?? 'Background Image' }}
                                (Web)</label>
                            <input type="file" class="form-control" id="hb-global-bg-img-web-upload"
                                name="backgroundImageWeb" data-image-url="{{ $globalBgImgWeb }}" />
                            <small class="form-text text-muted">Recommended for desktop/web screens</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('labels.background_image') ?? 'Background Image' }}
                                (App)</label>
                            <input type="file" class="form-control" id="hb-global-bg-img-app-upload"
                                name="backgroundImageApp" data-image-url="{{ $globalBgImgApp }}" />
                            <small class="form-text text-muted">Recommended for mobile app screens</small>
                        </div>
                    </div>
                </div>

                {{-- Global Background Animation (Web & App) --}}
                <div class="mb-3 {{ $globalBgType === 'animation' ? '' : 'd-none' }}"
                    id="hb-global-bg-animation-field">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ __('labels.background_animation') ?? 'Animation (JSON)' }}
                                (Web)</label>
                            <input type="file" class="form-control mb-1" id="hb-global-bg-anim-web-upload"
                                name="backgroundAnimationWeb" accept=".json,application/json" />
                            <div class="mt-2 border rounded p-2 text-center bg-light {{ !empty($globalBgAnimWeb) ? '' : 'd-none' }}"
                                id="hb-global-web-lottie-wrap" style="height: 140px;">
                                <lottie-player id="hb-global-web-lottie-player" src="{{ $globalBgAnimWeb }}"
                                    background="transparent" speed="1"
                                    style="width: 100%; height: 100%; object-fit: contain;" loop
                                    autoplay></lottie-player>
                            </div>
                            <small class="form-text text-muted">Lottie JSON file for Web</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('labels.background_animation') ?? 'Animation (JSON)' }}
                                (App)</label>
                            <input type="file" class="form-control mb-1" id="hb-global-bg-anim-app-upload"
                                name="backgroundAnimationApp" accept=".json,application/json" />
                            <div class="mt-2 border rounded p-2 text-center bg-light {{ !empty($globalBgAnimApp) ? '' : 'd-none' }}"
                                id="hb-global-app-lottie-wrap" style="height: 140px;">
                                <lottie-player id="hb-global-app-lottie-player" src="{{ $globalBgAnimApp }}"
                                    background="transparent" speed="1"
                                    style="width: 100%; height: 100%; object-fit: contain;" loop
                                    autoplay></lottie-player>
                            </div>
                            <small class="form-text text-muted">Lottie JSON file for Mobile</small>
                        </div>
                    </div>
                </div>

                {{-- Global Font Color --}}
                <div class="mb-3">
                    <label class="form-label fw-bold">{{ __('labels.font_color') }}</label>
                    <input type="color" class="form-control form-control-color w-100" name="fontColor"
                        id="hb-global-font-color-input" value="{{ $globalFontColor }}" />
                    <small class="form-text text-muted">Default header text color</small>
                </div>
            </form>

            {{-- 2. CATEGORY APPEARANCE FORM --}}
            <form id="hb-cat-appearance-form" class="card-body d-none" method="post" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="category_id" id="hb-cat-id-input">

                {{-- Category Icons (Default & Active) --}}
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">{{ __('labels.icon') }}</label>
                        <input type="file" class="form-control" id="hb-cat-icon-upload" name="icon" />
                        <small class="form-text text-muted">Category icon</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('labels.active_icon') }}</label>
                        <input type="file" class="form-control" id="hb-cat-active-icon-upload"
                            name="active_icon" />
                        <small class="form-text text-muted">Active / selected category icon</small>
                    </div>
                </div>

                <div class="hr-text text-muted my-3 small">Background & Styling</div>

                <div class="mb-3">
                    <label class="form-label fw-bold">{{ __('labels.background_type') }}</label>
                    <select class="form-select" name="background_type" id="hb-bg-type-select">
                        <option value="">{{ __('labels.select_background_type') }}</option>
                        <option value="color">{{ __('labels.color') }}</option>
                        <option value="image">{{ __('labels.image') }}</option>
                        <option value="animation">{{ __('labels.animation') ?? 'Animation' }}</option>
                    </select>
                    <small class="form-text text-muted">{{ __('labels.background_home_page_note') }}</small>
                </div>

                {{-- Category Background Color (Web & App) --}}
                <div class="mb-3 d-none" id="hb-bg-color-field">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label
                                class="form-label required">{{ __('labels.background_color') ?? 'Background Color' }}
                                (Web)</label>
                            <input type="color" class="form-control form-control-color w-100"
                                name="background_color_web" id="hb-bg-color-web-input" value="#ffffff" />
                            <small class="form-text text-muted">Applied to header on Web/Desktop</small>
                        </div>
                        <div class="col-md-6">
                            <label
                                class="form-label required">{{ __('labels.background_color') ?? 'Background Color' }}
                                (App)</label>
                            <input type="color" class="form-control form-control-color w-100"
                                name="background_color_app" id="hb-bg-color-app-input" value="#ffffff" />
                            <small class="form-text text-muted">Applied to header on Mobile App</small>
                        </div>
                    </div>
                </div>

                {{-- Category Background Image (Web & App) --}}
                <div class="mb-3 d-none" id="hb-bg-image-field">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ __('labels.background_image') ?? 'Background Image' }}
                                (Web)</label>
                            <input type="file" class="form-control" id="hb-bg-image-web-upload"
                                name="background_image_web" />
                            <small class="form-text text-muted">Recommended for desktop/web screens</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('labels.background_image') ?? 'Background Image' }}
                                (App)</label>
                            <input type="file" class="form-control" id="hb-bg-image-app-upload"
                                name="background_image_app" />
                            <small class="form-text text-muted">Recommended for mobile app screens</small>
                        </div>
                    </div>
                </div>

                {{-- Category Background Animation (Web & App) --}}
                <div class="mb-3 d-none" id="hb-bg-animation-field">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ __('labels.background_animation') ?? 'Animation (JSON)' }}
                                (Web)</label>
                            <input type="file" class="form-control mb-1" id="hb-bg-anim-web-upload"
                                name="background_animation_web" accept=".json,application/json" />
                            <div class="mt-2 border rounded p-2 text-center bg-light d-none" id="hb-web-lottie-wrap"
                                style="height: 140px;">
                                <lottie-player id="hb-web-lottie-player" src="" background="transparent"
                                    speed="1" style="width: 100%; height: 100%; object-fit: contain;" loop
                                    autoplay></lottie-player>
                            </div>
                            <small class="form-text text-muted">Lottie JSON file for Web</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('labels.background_animation') ?? 'Animation (JSON)' }}
                                (App)</label>
                            <input type="file" class="form-control mb-1" id="hb-bg-anim-app-upload"
                                name="background_animation_app" accept=".json,application/json" />
                            <div class="mt-2 border rounded p-2 text-center bg-light d-none" id="hb-app-lottie-wrap"
                                style="height: 140px;">
                                <lottie-player id="hb-app-lottie-player" src="" background="transparent"
                                    speed="1" style="width: 100%; height: 100%; object-fit: contain;" loop
                                    autoplay></lottie-player>
                            </div>
                            <small class="form-text text-muted">Lottie JSON file for Mobile</small>
                        </div>
                    </div>
                </div>

                {{-- Category Font Color --}}
                <div class="mb-4">
                    <label class="form-label fw-bold">{{ __('labels.font_color') }}</label>
                    <input type="color" class="form-control form-control-color w-100" name="font_color"
                        id="hb-font-color-input" value="#000000" />
                    <small class="form-text text-muted">Header text color when this category is active</small>
                </div>
            </form>
        </div>
    </div>
</div>
