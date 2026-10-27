{{-- Reusable Component: Home Page Builder Upcoming Change & Migration Notice Card --}}
@php
    $canAccessHomeBuilder = auth()->user()?->can('home_builder.view') ?? true;
@endphp

<style>
    .hb-upcoming-card {
        background-color: #FEF9EE;
        border: 1px solid #F6E6C2;
        border-radius: 12px;
        position: relative;
    }

    [data-bs-theme="dark"] .hb-upcoming-card {
        background-color: #231d12;
        border-color: #4a3c1c;
    }

    .hb-upcoming-badge {
        background-color: #F8DC96;
        color: #7D4E00;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        border-radius: 4px;
        padding: 2px 8px;
        display: inline-block;
    }

    [data-bs-theme="dark"] .hb-upcoming-badge {
        background-color: #553e14;
        color: #ffca66;
    }

    .hb-upcoming-icon-circle {
        width: 44px;
        height: 44px;
        min-width: 44px;
        background-color: #F9A825;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
    }

    .hb-prepare-box {
        background-color: #FFFFFF;
        border: 1px solid #F0E8D7;
        border-radius: 8px;
        padding: 6px 14px;
    }

    [data-bs-theme="dark"] .hb-prepare-box {
        background-color: #1a160f;
        border-color: #3b301a;
    }

    .hb-step-num {
        width: 20px;
        height: 20px;
        min-width: 20px;
        background-color: #FCE6B2;
        color: #633C00;
        font-weight: 700;
        font-size: 11px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    [data-bs-theme="dark"] .hb-step-num {
        background-color: #4a3615;
        color: #f7ce74;
    }

    .hb-primary-btn {
        background-color: #F9A825;
        color: #1a1200;
        font-weight: 600;
        font-size: 13px;
        border-radius: 6px;
        padding: 7px 14px;
        border: none;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        white-space: nowrap;
    }

    .hb-primary-btn:hover {
        background-color: #e5971a;
        color: #000000;
    }

    .hb-learn-link {
        color: #596780;
        font-size: 12px;
        text-decoration: underline;
        text-underline-offset: 3px;
        transition: color 0.15s ease;
    }

    .hb-learn-link:hover {
        color: #23272e;
    }

    [data-bs-theme="dark"] .hb-learn-link {
        color: #a0aec0;
    }

    [data-bs-theme="dark"] .hb-learn-link:hover {
        color: #ffffff;
    }

    .hb-prepare-divider {
        width: 1px;
        background-color: #EFE6D5;
        height: 18px;
    }

    [data-bs-theme="dark"] .hb-prepare-divider {
        background-color: #3b301a;
    }

    .hb-close-btn {
        position: absolute;
        top: 10px;
        right: 12px;
        background: transparent;
        border: none;
        padding: 4px;
        color: #8c734b;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        transition: all 0.15s ease;
        z-index: 2;
    }

    .hb-close-btn:hover {
        color: #3a2e18;
        background-color: rgba(0, 0, 0, 0.05);
    }

    [data-bs-theme="dark"] .hb-close-btn {
        color: #bfa36f;
    }

    [data-bs-theme="dark"] .hb-close-btn:hover {
        color: #ffffff;
        background-color: rgba(255, 255, 255, 0.08);
    }
</style>

<div class="card hb-upcoming-card shadow-xs mb-3" id="hb-migration-alert-card">
    <div class="card-body p-3 pe-4">
        {{-- Close Button --}}
        <button type="button" class="hb-close-btn" id="hb-alert-close-btn" aria-label="Close">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path d="M18 6l-12 12" />
                <path d="M6 6l12 12" />
            </svg>
        </button>

        <div class="d-flex align-items-center gap-3 flex-column flex-md-row">
            {{-- Megaphone Yellow Circle Icon --}}
            <div class="hb-upcoming-icon-circle flex-shrink-0 d-none d-sm-flex shadow-xs">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                    <path d="M11.999 3l.001 9h-6l-3 -3l3 -3h6z" />
                    <path d="M6 15h-3a1 1 0 0 0 -1 1v1a2 2 0 0 0 2 2h2a1 1 0 0 0 1 -1v-3" />
                    <path d="M15 8h.01" />
                    <path d="M18 5c1.657 1.591 2 4.148 2 7s-.343 5.409 -2 7" />
                    <path d="M15 11c.77 .74 1 1.93 1 3.5s-.23 2.76 -1 3.5" />
                </svg>
            </div>

            {{-- Main Info Column --}}
            <div class="flex-grow-1 min-w-0 pe-md-2">
                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                    <span class="hb-upcoming-badge">{{ __('labels.upcoming_change') }}</span>
                    <h4 class="fw-bold mb-0" style="font-size: 1.05rem; letter-spacing: -0.01em;">
                        {{ __('labels.home_builder_migration_notice_title') }}
                    </h4>
                </div>

                <div class="text-secondary small mb-2" style="font-size: 12.5px; line-height: 1.4; max-width: 820px;">
                    {{ __('labels.home_builder_migration_notice_desc_1') }}
                    {{ __('labels.home_builder_migration_notice_desc_2') }}
                </div>

                {{-- How to Prepare Box --}}
                <div class="hb-prepare-box shadow-xs">
                    <div class="d-flex align-items-center flex-wrap gap-2 gap-lg-3">
                        <span class="text-uppercase fw-bold text-muted"
                            style="font-size: 10px; letter-spacing: 0.04em;">
                            {{ __('labels.how_to_prepare') }}:
                        </span>

                        {{-- Step 1 --}}
                        <div class="d-flex align-items-center gap-1">
                            <span class="hb-step-num">1</span>
                            <span class="small fw-medium"
                                style="font-size: 12px;">{{ __('labels.prepare_step_1') }}</span>
                        </div>

                        <div class="hb-prepare-divider d-none d-lg-block"></div>

                        {{-- Step 2 --}}
                        <div class="d-flex align-items-center gap-1">
                            <span class="hb-step-num">2</span>
                            <span class="small fw-medium"
                                style="font-size: 12px;">{{ __('labels.prepare_step_2') }}</span>
                        </div>

                        <div class="hb-prepare-divider d-none d-lg-block"></div>

                        {{-- Step 3 --}}
                        <div class="d-flex align-items-center gap-1">
                            <span class="hb-step-num">3</span>
                            <span class="small fw-medium"
                                style="font-size: 12px;">{{ __('labels.prepare_step_3') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right CTA Column --}}
            @if ($canAccessHomeBuilder && Route::has('admin.home-builder.index'))
                <div
                    class="flex-shrink-0 d-flex flex-column align-items-start align-items-md-center justify-content-center ps-md-2 mt-2 mt-md-0">
                    <a href="{{ route('admin.home-builder.index') }}" class="hb-primary-btn shadow-xs mb-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M12 6h-6a2 2 0 0 0 -2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-6" />
                            <path d="M11 13l9 -9" />
                            <path d="M15 4h5v5" />
                        </svg>
                        <span>{{ __('labels.go_to_home_builder') }}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M9 6l6 6l-6 6" />
                        </svg>
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const closeBtn = document.getElementById('hb-alert-close-btn');
        const alertCard = document.getElementById('hb-migration-alert-card');
        if (closeBtn && alertCard) {
            closeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                alertCard.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
                alertCard.style.opacity = '0';
                alertCard.style.transform = 'translateY(-6px)';
                setTimeout(function() {
                    alertCard.remove();
                }, 250);
            });
        }
    });
</script>
