@php
    use App\Services\NotificationService;
    use App\Enums\AdminPermissionEnum;
    use App\Enums\SellerPermissionEnum;
    use App\Enums\DefaultSystemRolesEnum;
    use Illuminate\Support\Facades\Auth;
    use App\Models\Setting;
    use App\Models\SellerUser;
    use App\Models\SellerSubscription;
    use App\Enums\Subscription\SellerSubscriptionStatusEnum;
@endphp
<div class="page-header d-print-none mt-0">
    <div class="container-xl navbar align-items-center justify-content-between py-2 px-2">
        <!-- Start of Header: Responsive Global Search Bar -->
        <!-- Desktop Wide Search Bar (≥ 768px) -->
        <div class="nav-item ms-2 d-none d-md-flex">
            <button type="button"
                class="btn btn-outline-secondary btn-sm px-3 shadow-none text-muted d-flex align-items-center justify-content-between p-2"
                style="width: 360px; max-width: 100%; border-color: var(--tblr-border-color, #cbd5e1); background: var(--tblr-bg-surface, #fff);"
                data-bs-toggle="modal" data-bs-target="#globalSearchModal" title="{{ __('labels.global_search_shortcut') }}">
                <div class="d-flex align-items-center gap-2 text-truncate">
                    <i class="ti ti-search text-primary fs-3"></i>
                    <span class="text-secondary small fw-normal">{{ __('labels.search') ?? 'Search' }}...</span>
                </div>
                <kbd class="bg-body-tertiary text-muted border border-secondary-subtle small ms-1">Ctrl K</kbd>
            </button>
        </div>

        <!-- Mobile Compact Search Icon Button (< 768px) -->
        <div class="nav-item ms-2 d-md-none">
            <button type="button" class="btn btn-icon border-0 shadow-none nav-link px-0" data-bs-toggle="modal"
                data-bs-target="#globalSearchModal" title="{{ __('labels.global_search') }}">
                <i class="ti ti-search fs-2 text-primary"></i>
            </button>
        </div>

        <div class="">
            <!-- Page title actions -->
            <div class="col-auto ms-auto d-print-none">
                <div class="btn-list">
                    @php
                        $currentPanel = request()->segment(1);
                        $userAuth = Auth::user();
                        $canImpersonate = Setting::canImpersonate();
                        $isImpersonating = session('impersonating_as_seller', false);
                    @endphp

                    @if ($canImpersonate && $currentPanel === 'admin' && !$isImpersonating)
                        <a href="{{ route('admin.impersonate.to-seller') }}"
                            class="btn btn-outline-primary d-none d-md-inline-flex">
                            <i class="ti ti-switch-horizontal me-1"></i> {{ __('labels.switch_to_seller') }}
                        </a>
                    @endif
                    @if ($currentPanel === 'seller' && $isImpersonating)
                        <a href="{{ route('admin.impersonate.to-admin') }}"
                            class="btn btn-outline-warning d-none d-md-inline-flex">
                            <i class="ti ti-arrow-back-up me-1"></i> {{ __('labels.back_to_admin') }}
                        </a>
                    @endif

                    <!-- Feature Tours Trigger -->
                    <div class="nav-item dropdown d-flex me-3">
                        <a href="#" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1 rounded-pill"
                            data-bs-toggle="offcanvas" data-bs-target="#offcanvasTourDrawer"
                            aria-controls="offcanvasTourDrawer" tabindex="-1"
                            aria-label="{{ __('labels.guides') ?? 'User Guide' }}">
                            <i class="ti ti-compass fs-2"></i>
                            <span class="d-none d-md-inline">{{ __('labels.guides') ?? 'User Guide' }}</span>
                        </a>
                    </div>

                    <!-- Theme switcher -->
                    <div class="nav-item dropdown d-flex me-3">
                        <a href="#" onclick="toggleTheme()" class="nav-link px-0" tabindex="-1"
                            aria-label="{{ __('labels.switch_theme') }}">
                            <i id="theme-icon" class="ti ti-moon fs-2"></i>
                        </a>
                    </div>
                    <!-- Seller active subscription badge (via model helper) -->
                    @if ($currentPanel === 'seller' && $userAuth && !Setting::isSystemVendorTypeSingle() && Setting::isSubscriptionEnabled())
                        @php
                            $sellerEntity = $userAuth?->seller();
                            $activeSub = $sellerEntity
                                ? SellerSubscription::currentForSeller((int) $sellerEntity->id)
                                : null;
                            $canViewSubscriptions = $userAuth->hasRole(DefaultSystemRolesEnum::SELLER());

                            if (!$canViewSubscriptions) {
                                try {
                                    $canViewSubscriptions = $userAuth->hasPermissionTo(
                                        SellerPermissionEnum::SUBSCRIPTION_VIEW(),
                                    );
                                } catch (\Throwable $e) {
                                    $canViewSubscriptions = false;
                                }
                            }
                        @endphp
                        @if ($canViewSubscriptions && $activeSub)
                            <a href="{{ route('seller.subscription-plans.current') }}"
                                class="btn btn-pill me-3 align-items-center btn-sm"
                                title="{{ __('labels.current_subscription') }}">
                                <span
                                    class="badge {{ $activeSub->badgeClass() }} text-uppercase me-2">{{ (string) $activeSub->status }}</span>
                                <span class="text-truncate d-none d-md-inline-flex"
                                    style="max-width: 180px;">{{ $activeSub->plan?->name ?? __('labels.plan') }}</span>
                            </a>
                        @elseif($canViewSubscriptions)
                            <a href="{{ route('seller.subscription-plans.index') }}"
                                class="btn btn-pill me-3 align-items-center btn-sm"
                                title="{{ __('labels.current_subscription') }}">
                                <span
                                    class="badge bg-primary-lt text-uppercase">{{ __('labels.no_active_plan') }}</span>
                            </a>
                        @endif
                    @endif

                    <!-- Language dropdown -->
                    <x-language-selector />

                    <!-- Notification dropdown -->
                    @php
                        $currentPanel = request()->segment(1); // Get current panel (admin/seller)
                        $userAuth = Auth::user();
                        // Determine if the user can view notifications in the header
                        $canViewNotifications = false;
                        if ($currentPanel === 'admin') {
                            // Super Admin bypass on an admin panel
                            if ($userAuth && $userAuth->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN())) {
                                $canViewNotifications = true;
                            } else {
                                try {
                                    $canViewNotifications =
                                        $userAuth?->hasPermissionTo(AdminPermissionEnum::NOTIFICATION_VIEW()) ?? false;
                                } catch (\Throwable $e) {
                                    $canViewNotifications = false;
                                }
                            }
                        } elseif ($currentPanel === 'seller') {
                            // Default SELLER role and seller impersonation both get seller module access
                            if (
                                $isImpersonating ||
                                ($userAuth && $userAuth->hasRole(DefaultSystemRolesEnum::SELLER()))
                            ) {
                                $canViewNotifications = true;
                            } else {
                                try {
                                    $canViewNotifications =
                                        $userAuth?->hasPermissionTo(SellerPermissionEnum::NOTIFICATION_VIEW()) ?? false;
                                } catch (\Throwable $e) {
                                    $canViewNotifications = false;
                                }
                            }
                        } else {
                            $canViewNotifications = false; // other panels: hide by default
                        }
                    @endphp
                    @if ($canViewNotifications)
                        @php
                            $sentTo = $currentPanel === 'admin' ? 'admin' : 'seller';
                            $sellerEntity = $userAuth?->seller();
                            $id = $currentPanel === 'admin' ? $userAuth['id'] : $sellerEntity->user_id;
                            // Fetch latest notifications for current panel via NotificationService
                            $headerData = app(NotificationService::class)->getHeaderNotifications(
                                userId: $id,
                                sentTo: $sentTo,
                            );
                            $notifications = $headerData['notifications'];
                            $unreadCount = $headerData['unread_count'];
                        @endphp
                        <div class="nav-item dropdown me-3">
                            <button type="button" class="btn btn-icon border-0 shadow-none nav-link px-0"
                                data-bs-toggle="dropdown" tabindex="-1" aria-label="{{ __('labels.show_notifications') }}">
                                <i class="ti ti-bell fs-2"></i>
                                @if ($unreadCount > 0)
                                    <span class="badge bg-red badge-sm mt-1 badge-notification text-red-fg">
                                        {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                                        <span class="visually-hidden">{{ __('labels.unread_messages') }}</span>
                                    </span>
                                @endif
                            </button>
                            <div class="dropdown-menu dropdown-menu-end dropdown-menu-card dropdown-menu-demo">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">{{ __('labels.notifications') }}
                                        </h3>
                                    </div>
                                    <div class="list-group list-group-flush list-group-hoverable">
                                        @forelse($notifications as $notification)
                                            <a href="{{ $notification->getRedirectUrlForPanel($currentPanel) }}"
                                                class="list-group-item list-group-item-action header-notification-link"
                                                data-mark-read-url="{{ route($currentPanel . '.notifications.mark-read', $notification->id) }}"
                                                data-redirect-url="{{ $notification->getRedirectUrlForPanel($currentPanel) }}"
                                                data-is-read="{{ $notification->is_read ? '1' : '0' }}">
                                                <div class="row align-items-center">
                                                    <div class="col-auto">
                                                        <span
                                                            class="status-dot {{ $notification->is_read ? 'bg-secondary' : 'status-dot-animated bg-red' }} d-block"></span>
                                                    </div>
                                                    <div class="col text-truncate">
                                                        <span
                                                            class="text-body d-block text-ellipsis-1">{{ $notification->message ?: $notification->title }}</span>
                                                        <div class="d-block text-muted text-truncate mt-n1">
                                                            {{ $notification->created_at->diffForHumans() }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </a>
                                        @empty
                                            <div class="list-group-item">
                                                <div class="row align-items-center">
                                                    <div class="col text-center text-muted">
                                                        <i class="ti ti-bell-off fs-2 mb-2"></i>
                                                        <div>{{ __('labels.no_notifications') }}</div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforelse
                                    </div>
                                    <div class="card-footer">
                                        <a href="{{ route($currentPanel . '.notifications.index') }}"
                                            class="btn btn-outline-primary w-100">{{ __('labels.view_all_notifications') }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                document.querySelectorAll('.header-notification-link').forEach(function(link) {
                                    link.addEventListener('click', function(event) {
                                        event.preventDefault();

                                        const redirectUrl = this.dataset.redirectUrl || this.getAttribute('href');
                                        const markReadUrl = this.dataset.markReadUrl;
                                        const isRead = this.dataset.isRead === '1';
                                        const csrfToken = document.querySelector('meta[name="csrf-token"]')
                                            ?.getAttribute('content') || '';

                                        if (!markReadUrl || isRead) {
                                            window.location.href = redirectUrl;
                                            return;
                                        }

                                        fetch(markReadUrl, {
                                            method: 'POST',
                                            headers: {
                                                'X-CSRF-TOKEN': csrfToken,
                                                'X-Requested-With': 'XMLHttpRequest',
                                                'Accept': 'application/json',
                                            },
                                        }).finally(function() {
                                            window.location.href = redirectUrl;
                                        });
                                    });
                                });
                            });
                        </script>
                    @endif

                    <!-- Admin profile dropdown -->
                    <div class="nav-item dropdown d-flex">
                        <a href="#" class="nav-link d-flex lh-1 text-reset p-0" data-bs-toggle="dropdown"
                            aria-label="{{ __('labels.open_user_menu') }}">
                            @if ($user->profile_image)
                                <span class="avatar avatar-sm" id="profile"
                                    style="background-image: url({{ $user->profile_image }});"></span>
                            @else
                                <span class="avatar avatar-sm bg-primary text-white"
                                    id="profile">{{ strtoupper(substr($user->name, 0, 2)) }}</span>
                            @endif
                            <div class="d-none d-xl-block ps-2">
                                <div>{{ $user->username ?? '' }}</div>
                                <div class="mt-1 small text-muted">
                                    @if ($user->role == 'admin')
                                        {{ __('labels.administrator') }}
                                    @elseif($user->role == 'delivery_boy')
                                        {{ __('labels.delivery_boy') }}
                                    @endif
                                </div>
                            </div>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                            <a href="{{ route(request()->segment(1) . '.profile.index') }}" class="dropdown-item">
                                <i class="ti ti-user me-2 fs-2"></i>{{ __('labels.profile') }}
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="{{ route(request()->segment(1) . '.logout') }}"
                                class="dropdown-item js-panel-logout">
                                <i class="ti ti-logout me-2 fs-2"></i>{{ __('labels.logout') }}
                            </a>
                        </div>
                    </div>
                </div>
                <!-- BEGIN MODAL -->
                <!-- END MODAL -->
            </div>
        </div>
    </div>
    @if (\App\Support\DemoMode::isDemoUser(auth()->user()))
        <div class="alert alert-danger alert-dismissible m-3 text-danger" role="alert">
            <div class="alert-icon">
                <!-- Download SVG icon from http://tabler.io/icons/icon/info-circle -->
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                    stroke-linejoin="round" class="icon alert-icon icon-2">
                    <path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"></path>
                    <path d="M12 9h.01"></path>
                    <path d="M11 12h1v4h1"></path>
                </svg>
            </div>
            @php
                $demoMessage =
                    $user->access_panel->value === 'admin'
                        ? $systemSettings['adminDemoModeMessage'] ?? null
                        : $systemSettings['sellerDemoModeMessage'] ?? null;
            @endphp
            {{ $demoMessage ?: __('labels.demo_mode_message') }}
        </div>
    @endif
</div>

@include('layouts.partials._tour_drawer')
