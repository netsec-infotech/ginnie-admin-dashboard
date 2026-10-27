<aside id="panel-sidebar" class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark">
    <div class="container-fluid">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu"
            aria-controls="sidebar-menu" aria-expanded="false" aria-label="{{ __('labels.toggle_navigation') }}">
            <span class="navbar-toggler-icon"></span>
        </button>

        <button type="button" class="btn btn-sm btn-icon sidebar-toggle-btn d-none d-lg-inline-flex"
            id="sidebar-toggle-btn" title="{{ __('labels.toggle_sidebar') }}" aria-label="{{ __('labels.toggle_sidebar') }}">
            <i class="ti ti-chevron-left" id="sidebar-toggle-icon"></i>
        </button>


        <div class="navbar-brand navbar-brand-autodark">
            @php
                use App\Enums\AdminPermissionEnum;
                use App\Enums\SellerPermissionEnum;
                use App\Enums\DefaultSystemRolesEnum;
                use App\Models\Setting;
                use Illuminate\Support\Facades\Auth;
                $panel = request()->segment(1);
                $isImpersonatingForLogo = session('impersonating_as_seller', false);
                $linkPanel = $isImpersonatingForLogo ? 'seller' : $panel;
            @endphp
            <a href="{{ url($linkPanel) }}">
                <img class="brand-img"
                    src="{{ !empty($systemSettings['logo']) ? $systemSettings['logo'] : asset('logos/hyper-local-logo-white.png') }}"
                    alt="{{ $systemSettings['appName'] ?? '' }}" width="120px">
                <img class="brand-img-icon d-none"
                    src="{{ !empty($systemSettings['favicon']) ? $systemSettings['favicon'] : asset('logos/hyper-local-favicon.png') }}"
                    alt="{{ $systemSettings['appName'] ?? '' }}" width="28px">
            </a>
        </div>

        <div class="collapse navbar-collapse overflow-y-auto" id="sidebar-menu" data-sidebar-scroll-container>
            <ul class="navbar-nav pt-lg-3">
                @php
                    // Get the menu items based on the authenticated user's role.
$userRole = Auth::user();
// If impersonating as seller, force the seller panel for the sidebar
$isImpersonating = session('impersonating_as_seller', false);
$currentPanel = $isImpersonating ? 'seller' : $userRole->access_panel->value ?? $panel;
$menuItems = config("menu.{$currentPanel}", []);
$badgeCounts = \App\Helpers\SidebarBadgeHelper::getBadgeCounts($currentPanel);

// Choose the correct permission set per panel
$allPerms =
    $currentPanel === 'admin'
        ? AdminPermissionEnum::values()
        : ($currentPanel === 'seller'
            ? SellerPermissionEnum::values()
            : []);

// Super Admin bypass only applies to admin panel
$isSuperAdmin =
    $currentPanel === 'admin' && $userRole->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN());

// Default Seller role can view the full seller module
// While impersonating as seller, treat the user as default seller for menu visibility
$isDefaultSeller =
    $currentPanel === 'seller' &&
    ($isImpersonating || $userRole->hasRole(DefaultSystemRolesEnum::SELLER()));

$canSee = function ($perm) use ($userRole, $allPerms, $isSuperAdmin, $isDefaultSeller) {
    // If default seller role on seller panel -> always visible
    if ($isDefaultSeller) {
        return true;
    }
    // No permission specified -> visible
    if (empty($perm)) {
        return true;
    }
    // If permission slug does not exist in the panel's enum -> default visible
                        if (!in_array($perm, $allPerms, true)) {
                            return true;
                        }
                        // Super Admin bypass
                        if ($isSuperAdmin) {
                            return true;
                        }
                        try {
                            return $userRole->hasPermissionTo($perm);
                        } catch (\Throwable $e) {
                            return false;
                        }
                    };
                @endphp

                @foreach ($menuItems as $key => $item)
                    {{-- Section divider --}}
                    @if (($item['type'] ?? '') === 'section')
                        <li class="nav-item mt-2 mb-1 px-3">
                            <span class="nav-link-title text-uppercase fw-bold fs-7 text-secondary tracking-wide"
                                style="font-size: 0.65rem; letter-spacing: 0.08em; padding: 0.25rem 0;">
                                {{ __($item['title']) }}
                            </span>
                        </li>
                        @continue
                    @endif

                    @php
                        // Determine if the menu item has sub-routes (dropdown) or a single link.
                        $isDropdown = is_array($item['route']);
                        $isActive = isset($page, $item['active']) && $page === $item['active'];
                        // Hide entire subscription module when system is Single Vendor (both admin and seller panels)
                        if (Setting::isSystemVendorTypeSingle() && $key === 'subscriptions') {
                            continue;
                        }
                        if (
                            !Setting::isSubscriptionEnabled() &&
                            $key === 'subscriptions' &&
                            $currentPanel === 'seller'
                        ) {
                            continue;
                        }
                        // Check permission for single link or any children for dropdown
                        if (!$isDropdown) {
                            if (!$canSee($item['permission'] ?? null)) {
                                continue; // skip this menu item if not permitted
                            }
                        }
                        // For dropdowns, pre-filter sub items; if none remain, skip parent entirely
                        $visibleSubRoutes = null;
                        if ($isDropdown) {
                            $visibleSubRoutes = [];
                            foreach ($item['route'] as $srKey => $subRoute) {
                                // Hide Add Sellers from admin sidebar when system is single-vendor
                                if (
                                    $currentPanel === 'admin' &&
                                    $srKey === 'add_sellers' &&
                                    Setting::isSystemVendorTypeSingle()
                                ) {
                                    continue;
                                }
                                $perm = is_array($subRoute) ? $subRoute['permission'] ?? null : null;
                                if ($canSee($perm)) {
                                    $visibleSubRoutes[$srKey] = $subRoute;
                                }
                            }
                            if (count($visibleSubRoutes) === 0) {
                                continue; // no visible children => hide the whole dropdown
                            }
                        }
                    @endphp

                    <li class="nav-item {{ $isDropdown ? 'dropdown' : '' }} {{ $isActive ? 'active' : '' }}"
                        data-sidebar-label="{{ __($item['title']) }}">
                        @if ($isDropdown)
                            @php
                                $parentBadgeCount = 0;
                                if (!empty($visibleSubRoutes)) {
                                    foreach ($visibleSubRoutes as $srKey => $subRoute) {
                                        if (is_array($subRoute) && !empty($subRoute['badge_key'])) {
                                            $parentBadgeCount += $badgeCounts[$subRoute['badge_key']] ?? 0;
                                        }
                                    }
                                }
                                if (!empty($item['badge_key'])) {
                                    $parentBadgeCount += $badgeCounts[$item['badge_key']] ?? 0;
                                }
                            @endphp
                            <a class="nav-link dropdown-toggle {{ $isActive ? 'active show' : '' }}"
                                href="javascript:void(0)" data-bs-toggle="dropdown" data-bs-auto-close="false"
                                aria-expanded="{{ $isActive ? 'true' : 'false' }}"
                                {{ $key === 'sellers' ? 'data-tour-target="seller-management-menu"' : '' }}>
                                <span class="nav-link-icon d-md-none d-lg-inline-block">
                                    <i class="ti {{ $item['icon'] }} fs-2"></i>
                                    @if ($parentBadgeCount > 0)
                                        <span class="sidebar-collapsed-badge status-dot bg-danger" aria-hidden="true"></span>
                                    @endif
                                </span>
                                <span class="nav-link-title">{{ __($item['title']) }}
                                    @if ($parentBadgeCount > 0)
                                        <span class="status-dot position-absolute bg-danger ms-1">
                                            {{-- {{ $parentBadgeCount }} --}}
                                        </span>
                                    @endif
                                </span>
                            </a>
                            @if (count($visibleSubRoutes) > 0)
                                <div class="dropdown-menu {{ $isActive ? 'show' : '' }}">
                                    <div class="sidebar-flyout-title">{{ __($item['title']) }}</div>
                                    @foreach ($visibleSubRoutes as $srKey => $subRoute)
                                        @php
                                            if (is_array($subRoute)) {
                                                $routeName = $subRoute['route'] ?? array_values($subRoute)[0];
                                                $routeParams =
                                                    $subRoute['params'] ?? (is_array($subRoute) ? $subRoute : []);
                                                $subBadgeKey = $subRoute['badge_key'] ?? null;
                                            } else {
                                                $routeName = $subRoute;
                                                $routeParams = [];
                                                $subBadgeKey = null;
                                            }
                                            $isSubActive =
                                                isset($subRoute) &&
                                                isset($sub_page) &&
                                                $subRoute['sub_active'] === $sub_page;
                                            $subBadgeCount = !empty($subBadgeKey) ? $badgeCounts[$subBadgeKey] ?? 0 : 0;
                                        @endphp
                                        <a class="dropdown-item d-flex align-items-center justify-content-between {{ $isSubActive ? 'active' : '' }}"
                                            href="{{ route($subRoute['sub_route'], $subRoute['route_param'] ?? []) }}"
                                            {{ ($srKey ?? '') === 'add_sellers' || ($subRoute['sub_route'] ?? '') === 'admin.sellers.create' ? 'data-tour-target="add-seller-menu"' : '' }}>
                                            <span><i class="ti ti-point{{ $isSubActive ? '-filled' : '' }} fs-2"></i>
                                                {{ __($subRoute['sub_title']) }}
                                                @if ($subBadgeCount > 0)
                                                    <span class="status-dot position-absolute bg-danger ms-1"></span>
                                                @endif
                                            </span>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        @else
                            @php
                                $singleBadgeKey = $item['badge_key'] ?? $key;
                                $singleBadgeCount = !empty($singleBadgeKey) ? $badgeCounts[$singleBadgeKey] ?? 0 : 0;
                            @endphp
                            <a class="nav-link {{ $isActive ? 'active' : '' }}" href="{{ route($item['route']) }}">
                                <span class="nav-link-icon">
                                    <i class="ti {{ $item['icon'] }} fs-2"></i>
                                    @if ($singleBadgeCount > 0)
                                        <span class="sidebar-collapsed-badge status-dot bg-danger" aria-hidden="true"></span>
                                    @endif
                                </span>
                                <span class="nav-link-title">{{ __($item['title']) }}
                                    @if ($singleBadgeCount > 0)
                                        <span class="status-dot position-absolute bg-danger ms-1"></span>
                                    @endif
                                </span>
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>
            <div class="d-flex d-md-none justify-content-center pt-3 pb-4 fw-medium text-secondary">
                V{{ Setting::getCurrentVersion() }}
            </div>
        </div>
        <div class="sidebar-bottom-version d-none d-md-flex justify-content-center align-items-center py-2 fw-medium text-secondary">
            V{{ Setting::getCurrentVersion() }}
        </div>
    </div>
</aside>
