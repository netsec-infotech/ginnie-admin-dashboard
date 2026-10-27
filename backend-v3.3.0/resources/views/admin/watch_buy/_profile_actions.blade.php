@php
    $profileType = $profile->profile_type->value ?? $profile->profile_type;
    $profileStatus = $profile->status->value ?? $profile->status;
@endphp

@if ($editPermission || ($suspendPermission && $profileType === 'seller'))
    <div class="dropdown">
        <button type="button" class="btn btn-outline-primary dropdown-toggle text-nowrap"
            data-bs-toggle="dropdown" aria-expanded="false">
            <i class="ti ti-adjustments-horizontal me-1"></i>{{ __('labels.more_actions') }}
        </button>
        <div class="dropdown-menu dropdown-menu-end">
            @if ($editPermission)
                <a class="dropdown-item"
                    href="{{ $profileType === 'platform' ? route('admin.watch-and-buy.official-profile.edit') : route('admin.watch-and-buy.profiles.edit', $profile) }}">
                    <i class="ti ti-pencil me-2 text-primary"></i>{{ __('labels.edit') }}
                </a>
            @endif

            @if ($suspendPermission && $profileType === 'seller')
                @if ($profileStatus === 'suspended')
                    <form class="watch-buy-action-form" method="post"
                        action="{{ route('admin.watch-and-buy.profiles.unsuspend', $profile) }}">
                        @csrf
                        <button type="submit" class="dropdown-item">
                            <i class="ti ti-user-check me-2 text-success"></i>{{ __('labels.watch_buy_unsuspend') }}
                        </button>
                    </form>
                @else
                    <form class="watch-buy-action-form" method="post"
                        action="{{ route('admin.watch-and-buy.profiles.suspend', $profile) }}">
                        @csrf
                        <input type="hidden" name="reason">
                        <button type="button" class="dropdown-item watch-buy-reason-action"
                            data-title="{{ __('labels.watch_buy_suspend_profile_title') }}"
                            data-placeholder="{{ __('labels.watch_buy_suspension_reason_placeholder') }}"
                            data-confirm-text="{{ __('labels.watch_buy_confirm') }}"
                            data-cancel-text="{{ __('labels.cancel') }}"
                            data-reason-required="{{ __('labels.watch_buy_reason_required') }}">
                            <i class="ti ti-user-pause me-2 text-warning"></i>{{ __('labels.watch_buy_suspend') }}
                        </button>
                    </form>
                @endif
            @endif
        </div>
    </div>
@else
    <span class="text-muted">—</span>
@endif
