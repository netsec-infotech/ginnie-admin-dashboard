<div class="card hb-preview-card" data-role="preview-drawer">
    <div class="card-header border-bottom p-3 d-flex flex-column gap-3">
        {{-- Top Header Row: Title with Icon Avatar & Subtitle on Left, Top Quick Actions on Right --}}
        <div class="d-flex align-items-center justify-content-between w-100">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <div class="hb-preview-title-avatar">
                    <i class="ti ti-{{ ($platform ?? 'app') === 'web' ? 'device-desktop' : 'device-mobile' }}"></i>
                </div>
                <div>
                    <h3 class="card-title m-0 fw-bold fs-3 text-dark lh-sm">{{ __('labels.hb_live_preview') }}</h3>
                    <div class="text-muted small">See how your changes look in real time</div>
                </div>
            </div>
        </div>

        {{-- Filter Rows Container (Side by Side in Same Row) --}}
        <div class="hb-sim-filters-grid">
            {{-- Filter 1: DateTime Simulation --}}
            <div class="hb-sim-filter-item" title="Simulate layout at a specific date and time">
                <div class="hb-sim-filter-icon">
                    <i class="ti ti-calendar"></i>
                </div>
                <div class="hb-sim-filter-label">Select Date</div>
                <div class="hb-sim-filter-input-wrap">
                    <input type="datetime-local" class="form-control" data-role="sim-datetime">
                    <button type="button" class="btn btn-icon btn-ghost-secondary d-none"
                        data-role="reset-sim-datetime" title="Reset to current time">
                        <i class="ti ti-rotate-clockwise fs-4"></i>
                    </button>
                </div>
            </div>

            {{-- Filter 2: Delivery Zone Simulation --}}
            <div class="hb-sim-filter-item" title="Simulate layout for a specific delivery zone">
                <div class="hb-sim-filter-icon">
                    <i class="ti ti-map-pin"></i>
                </div>
                <div class="hb-sim-filter-label">Select Zone</div>
                <div class="hb-sim-filter-input-wrap">
                    <select class="form-select" data-role="sim-zone">
                        <option value="">All Zones (Default)</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Info Alert Pill Text (Placed at top as requested) --}}
        <div class="hb-preview-info-pill">
            <i class="ti ti-info-circle-filled text-primary"></i>
            <span>Changes you make will reflect in the live preview based on the selected date, zone and device.</span>
        </div>

        {{-- Bottom Controls Row: Device Selectors on Left & Zoom/Status/Preview Mode on Right --}}
        <div class="d-flex align-items-center justify-content-between w-100 pt-1 flex-wrap gap-2">
            @if (($platform ?? 'app') === 'app')
                <div class="btn-group btn-group-sm" role="group" data-role="app-device-switcher"
                    title="Switch App Device">
                    <button type="button" class="btn" data-device="android" title="Android preview">
                        <i class="ti ti-brand-android"></i>Android
                    </button>
                    <button type="button" class="btn active" data-device="iphone" title="iPhone preview">
                        <i class="ti ti-brand-apple"></i>iPhone
                    </button>
                    <button type="button" class="btn" data-device="tablet" title="Tablet preview">
                        <i class="ti ti-device-tablet"></i>Tablet
                    </button>
                </div>
            @else
                <div class="btn-group btn-group-sm" role="group" data-role="web-device-switcher"
                    title="Switch Web Viewport">
                    <button type="button" class="btn active" data-device="desktop" title="Desktop view">
                        <i class="ti ti-device-desktop"></i>Desktop
                    </button>
                    <button type="button" class="btn" data-device="tablet" title="Tablet view">
                        <i class="ti ti-device-tablet"></i>Tablet
                    </button>
                    <button type="button" class="btn" data-device="mobile" title="Mobile view">
                        <i class="ti ti-device-mobile"></i>Mobile
                    </button>
                </div>
            @endif

            <div class="d-flex align-items-center gap-1">
                @if (($platform ?? 'app') === 'app')
                    <div class="btn-group btn-group-sm" role="group" title="Zoom device frame scale">
                        <button type="button" class="btn btn-icon" data-role="zoom-out" title="Zoom out">
                            <i class="ti ti-zoom-out"></i>
                        </button>
                        <button type="button" class="btn fw-bold" data-role="zoom-val">
                            68%
                        </button>
                        <button type="button" class="btn btn-icon" data-role="zoom-in" title="Zoom in">
                            <i class="ti ti-zoom-in"></i>
                        </button>
                        <button type="button" class="btn btn-icon" data-role="zoom-reset" title="Reset zoom (68%)">
                            <i class="ti ti-rotate-clockwise"></i>
                        </button>
                    </div>
                @endif

                {{-- Status Indicator (Live / Preview animated pulse dot) --}}
                <div class="hb-preview-status" data-role="preview-status" title="Current Mode">
                    <span class="hb-status-dot"></span>
                    <span class="hb-status-text" data-role="status-text">Live</span>
                </div>

                <div class="d-flex align-items-center gap-1">
                    {{-- Fullscreen Mode Toggle Button --}}
                    <button type="button" class="btn btn-icon" data-role="toggle-fullscreen"
                        title="Toggle Fullscreen Canvas">
                        <i class="ti ti-maximize" data-role="fs-icon-enter"></i>
                        <i class="ti ti-minimize d-none" data-role="fs-icon-exit"></i>
                    </button>

                    {{-- Light / Dark Theme Toggle --}}
                    <button type="button" class="btn btn-icon" data-role="sim-theme-toggle"
                        title="Toggle Light / Dark Preview">
                        <i class="ti ti-sun text-warning" data-role="theme-icon-light"></i>
                        <i class="ti ti-moon text-indigo d-none" data-role="theme-icon-dark"></i>
                    </button>
                </div>

                {{-- Preview / Back to Edit Mode Toggle --}}
                <button type="button" class="btn btn-outline-primary" data-role="toggle-clean-preview"
                    title="Toggle Clean Preview Mode">
                    <i class="ti ti-eye" data-role="mode-icon-preview"></i>
                    <i class="ti ti-edit d-none" data-role="mode-icon-edit"></i>
                    <span data-role="mode-text">Preview</span>
                </button>
            </div>
        </div>
    </div>

    @php
        $homeGeneralSettings =
            app(\App\Services\SettingService::class)
                ->getSettingByVariable(\App\Enums\SettingTypeEnum::HOME_GENERAL_SETTINGS())
                ?->toArray(request())['value'] ?? [];
        $allTitle = !empty($homeGeneralSettings['title']) ? $homeGeneralSettings['title'] : 'All';
        $allIcon = $homeGeneralSettings['icon'] ?? '';
        $allActiveIcon = $homeGeneralSettings['activeIcon'] ?? ($homeGeneralSettings['icon'] ?? '');
        $bgType = $homeGeneralSettings['backgroundType'] ?? '';
        $fontColor = $homeGeneralSettings['fontColor'] ?? '';
        $appBgColor =
            $homeGeneralSettings['backgroundColorApp'] ?? ($homeGeneralSettings['backgroundColor'] ?? '#0066cc');
        $appBgImage = $homeGeneralSettings['backgroundImageApp'] ?? ($homeGeneralSettings['backgroundImage'] ?? '');
        $appBgAnim =
            $homeGeneralSettings['backgroundAnimationApp'] ?? ($homeGeneralSettings['backgroundAnimation'] ?? '');

        $webBgColor =
            $homeGeneralSettings['backgroundColorWeb'] ?? ($homeGeneralSettings['backgroundColor'] ?? '#ffffff');
        $webBgImage = $homeGeneralSettings['backgroundImageWeb'] ?? ($homeGeneralSettings['backgroundImage'] ?? '');
        $webBgAnim =
            $homeGeneralSettings['backgroundAnimationWeb'] ?? ($homeGeneralSettings['backgroundAnimation'] ?? '');
    @endphp

    <div class="card-body p-3 hb-drawer-body">
        @if (($platform ?? 'app') === 'app')
            {{-- Phone Mockup (App Layout using Devices.css v0.2.0) --}}
            <div class="hb-device-wrapper">
                <div class="device device-iphone-14-pro device-black" data-frame="app">
                    <div class="device-frame">
                        <div class="device-screen d-flex flex-column">
                            <div class="hb-phone-header position-relative"
                                style="{{ $bgType === 'color' ? 'background:' . $appBgColor . ';' : ($bgType === 'image' && !empty($appBgImage) ? 'background-image:url(' . $appBgImage . ');background-size:cover;' : 'background:#0066cc;') }} {{ $fontColor ? 'color:' . $fontColor . ';' : '' }}">
                                @if ($bgType === 'animation' && !empty($appBgAnim))
                                    <lottie-player class="hb-header-lottie-bg" src="{{ $appBgAnim }}"
                                        background="transparent" speed="1"
                                        style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; pointer-events: none; z-index: 0;"
                                        loop autoplay></lottie-player>
                                @endif
                                <div class="hb-phone-status-bar">
                                    <span>1:17</span>
                                    <div class="hb-phone-icons">
                                        <i class="ti ti-wifi"></i>
                                        <i class="ti ti-battery-4"></i>
                                    </div>
                                </div>
                                <div class="hb-phone-location-bar">
                                    <div class="hb-phone-location-title">
                                        <i class="ti ti-map-pin-filled me-1"></i>
                                        <span>Selected location</span>
                                        <i class="ti ti-chevron-down ms-1 fs-4"></i>
                                    </div>
                                    <div class="hb-phone-location-sub">
                                        Your current location, ...
                                    </div>
                                </div>
                                <div class="hb-phone-search-bar">
                                    <i class="ti ti-search text-muted me-2"></i>
                                    <span class="text-muted me-auto">Search "tea"</span>
                                    <i class="ti ti-pencil text-muted"></i>
                                </div>
                                <div class="hb-phone-categories-bar">
                                    <div class="hb-phone-cat-item active" data-role="cat-pill" data-cat-id="all"
                                        data-cat-icon="{{ $allIcon }}"
                                        data-cat-active-icon="{{ $allActiveIcon }}">
                                        <div class="hb-phone-cat-icon">
                                            @if (!empty($allActiveIcon))
                                                <img src="{{ $allActiveIcon }}" alt="{{ $allTitle }}">
                                            @elseif (!empty($allIcon))
                                                <img src="{{ $allIcon }}" alt="{{ $allTitle }}">
                                            @else
                                                <i class="ti ti-apps"></i>
                                            @endif
                                        </div>
                                        <span>{{ Str::limit($allTitle, 11) }}</span>
                                    </div>
                                    @if (!empty($categories) && count($categories) > 0)
                                        @foreach ($categories->take(10) as $cat)
                                            @php
                                                $catIcon = $cat->icon ?: null;
                                                $catActiveIcon = $cat->active_icon ?: ($cat->icon ?: null);
                                                $bgType = is_object($cat->background_type)
                                                    ? $cat->background_type->value
                                                    : $cat->background_type ?? 'none';
                                            @endphp
                                            <div class="hb-phone-cat-item" data-role="cat-pill"
                                                data-cat-id="{{ $cat->id }}"
                                                data-cat-title="{{ $cat->title }}"
                                                data-cat-icon="{{ $catIcon }}"
                                                data-cat-active-icon="{{ $catActiveIcon }}"
                                                data-cat-bg-type="{{ $bgType }}"
                                                data-cat-bg-color="{{ $cat->background_color }}"
                                                data-cat-bg-color-web="{{ $cat->background_color_web }}"
                                                data-cat-bg-color-app="{{ $cat->background_color_app }}"
                                                data-cat-bg-image="{{ $cat->background_image }}"
                                                data-cat-bg-image-web="{{ $cat->background_image_web }}"
                                                data-cat-bg-image-app="{{ $cat->background_image_app }}"
                                                data-cat-bg-anim="{{ $cat->background_animation }}"
                                                data-cat-bg-anim-web="{{ $cat->background_animation_web }}"
                                                data-cat-bg-anim-app="{{ $cat->background_animation_app }}"
                                                data-cat-font-color="{{ $cat->font_color }}">
                                                <div class="hb-phone-cat-icon">
                                                    @if (!empty($catIcon))
                                                        <img src="{{ $catIcon }}" alt="{{ $cat->title }}">
                                                    @elseif (!empty($cat->image) && !str_contains($cat->image, 'category-placeholder.png'))
                                                        <img src="{{ $cat->image }}" alt="{{ $cat->title }}">
                                                    @else
                                                        <i class="ti ti-category"></i>
                                                    @endif
                                                </div>
                                                <span>{{ Str::limit($cat->title, 11) }}</span>
                                            </div>
                                        @endforeach
                                    @else
                                        <div class="hb-phone-cat-item" data-role="cat-pill" data-cat-id="all">
                                            <div class="hb-phone-cat-icon"><i class="ti ti-shopping-cart"></i></div>
                                            <span>Grocery</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="hb-preview-screen" data-role="preview-screen-app">
                                <div class="text-center text-muted p-4">
                                    <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                                    <span>{{ __('labels.loading') }}</span>
                                </div>
                            </div>
                            <div class="hb-phone-bottom-nav">
                                <div class="hb-nav-item active"><i class="ti ti-home"></i><span>Home</span></div>
                                <div class="hb-nav-item"><i class="ti ti-category"></i><span>Categories</span></div>
                                <div class="hb-nav-item"><i class="ti ti-shopping-cart"></i><span>Cart</span></div>
                                <div class="hb-nav-item"><i class="ti ti-user"></i><span>Account</span></div>
                            </div>
                        </div>
                    </div>
                    <div class="device-stripe"></div>
                    <div class="device-header"></div>
                    <div class="device-sensors"></div>
                    <div class="device-btns"></div>
                    <div class="device-power"></div>
                    <div class="device-home"></div>
                </div>
            </div>
        @else
            @php
                $webSettingRecord = \App\Models\Setting::find('web');
                $webValues = is_array($webSettingRecord?->value) ? $webSettingRecord->value : [];
                $customerWebUrl = !empty($webValues['customerWebUrl'])
                    ? $webValues['customerWebUrl']
                    : 'https://yourstore.com';
            @endphp
            {{-- Browser Mockup (Web Layout) --}}
            <div class="hb-device-frame hb-web-frame" data-frame="web">
                <div class="hb-web-top-bar">
                    <div class="hb-web-dots">
                        <span class="dot red"></span>
                        <span class="dot yellow"></span>
                        <span class="dot green"></span>
                    </div>
                    <div class="hb-web-address-bar">
                        <i class="ti ti-lock text-success me-1"></i>
                        <span class="hb-web-url">{{ $customerWebUrl }}</span>
                    </div>
                </div>
                <div class="hb-web-header position-relative"
                    style="{{ $bgType === 'color' ? 'background:' . $webBgColor . ';' : ($bgType === 'image' && !empty($webBgImage) ? 'background-image:url(' . $webBgImage . ');background-size:cover;' : 'background:#ffffff;') }} {{ $fontColor ? 'color:' . $fontColor . ';' : '' }}">
                    @if ($bgType === 'animation' && !empty($webBgAnim))
                        <lottie-player class="hb-header-lottie-bg" src="{{ $webBgAnim }}"
                            background="transparent" speed="1"
                            style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; pointer-events: none; z-index: 0;"
                            loop autoplay></lottie-player>
                    @endif
                    <div class="hb-web-categories-bar">
                        <div class="hb-web-cat-pill active" data-role="cat-pill" data-cat-id="all"
                            data-cat-icon="{{ $allIcon }}" data-cat-active-icon="{{ $allActiveIcon }}">
                            <div class="hb-web-cat-pill-icon">
                                @if (!empty($allActiveIcon))
                                    <img src="{{ $allActiveIcon }}" alt="{{ $allTitle }}"
                                        style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
                                @elseif (!empty($allIcon))
                                    <img src="{{ $allIcon }}" alt="{{ $allTitle }}"
                                        style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
                                @else
                                    <i class="ti ti-apps"></i>
                                @endif
                            </div>
                            <span>{{ Str::limit($allTitle, 12) }}</span>
                        </div>
                        @if (!empty($categories) && count($categories) > 0)
                            @foreach ($categories->take(10) as $cat)
                                @php
                                    $catIcon = $cat->icon ?: null;
                                    $catActiveIcon = $cat->active_icon ?: ($cat->icon ?: null);
                                    $bgType = is_object($cat->background_type)
                                        ? $cat->background_type->value
                                        : $cat->background_type ?? 'none';
                                @endphp
                                <div class="hb-web-cat-pill" data-role="cat-pill" data-cat-id="{{ $cat->id }}"
                                    data-cat-title="{{ $cat->title }}" data-cat-icon="{{ $catIcon }}"
                                    data-cat-active-icon="{{ $catActiveIcon }}"
                                    data-cat-bg-type="{{ $bgType }}"
                                    data-cat-bg-color="{{ $cat->background_color }}"
                                    data-cat-bg-color-web="{{ $cat->background_color_web }}"
                                    data-cat-bg-color-app="{{ $cat->background_color_app }}"
                                    data-cat-bg-image="{{ $cat->background_image }}"
                                    data-cat-bg-image-web="{{ $cat->background_image_web }}"
                                    data-cat-bg-image-app="{{ $cat->background_image_app }}"
                                    data-cat-bg-anim="{{ $cat->background_animation }}"
                                    data-cat-bg-anim-web="{{ $cat->background_animation_web }}"
                                    data-cat-bg-anim-app="{{ $cat->background_animation_app }}"
                                    data-cat-font-color="{{ $cat->font_color }}">
                                    <div class="hb-web-cat-pill-icon">
                                        @if (!empty($catIcon))
                                            <img src="{{ $catIcon }}" alt="{{ $cat->title }}"
                                                style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
                                        @elseif (!empty($cat->image) && !str_contains($cat->image, 'category-placeholder.png'))
                                            <img src="{{ $cat->image }}" alt="{{ $cat->title }}"
                                                style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
                                        @else
                                            <i class="ti ti-category"></i>
                                        @endif
                                    </div>
                                    <span>{{ Str::limit($cat->title, 12) }}</span>
                                </div>
                            @endforeach
                        @else
                            <div class="hb-web-cat-pill" data-role="cat-pill" data-cat-id="all">
                                <div class="hb-web-cat-pill-icon"><i class="ti ti-sparkles"></i></div>
                                <span>Beauty</span>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="hb-preview-screen" data-role="preview-screen-web">
                    <div class="text-center text-muted p-4">
                        <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                        <span>{{ __('labels.loading') }}</span>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
