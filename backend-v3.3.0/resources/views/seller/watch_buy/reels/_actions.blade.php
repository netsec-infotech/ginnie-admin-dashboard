@php
    $statusValue = $reel->status instanceof \BackedEnum ? $reel->status->value : $reel->status;
@endphp

<div class="d-flex align-items-center">
    @if(!$enabled)
        <span class="text-danger">{{ __('labels.watch_buy_feature_disabled') }}</span>
    @elseif($profileStatus === 'suspended')
        <div class="d-flex flex-column align-items-start gap-1 text-wrap">
            <span class="badge bg-warning-lt text-warning">
                <i class="ti ti-user-pause me-1"></i>{{ __('labels.watch_buy_profile_suspended') }}
            </span>
            @if($reel->profile?->suspension_reason)
                <span class="small text-secondary"><span class="fw-semibold">{{ __('labels.reason') }}:</span> {{ $reel->profile->suspension_reason }}</span>
            @endif
        </div>
    @elseif($statusValue === 'blocked')
        <div class="d-flex flex-column align-items-start gap-1 text-wrap">
            <span class="badge bg-danger-lt text-danger">
                <i class="ti ti-lock me-1"></i>{{ __('labels.blocked') }}
            </span>
            @if($reel->block_reason)
                <span class="small text-secondary"><span class="fw-semibold">{{ __('labels.reason') }}:</span> {{ $reel->block_reason }}</span>
            @endif
        </div>
    @elseif($editPermission || $publishPermission || $deletePermission)
        <div class="dropdown">
            <button type="button" class="btn btn-outline-primary dropdown-toggle text-nowrap" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="ti ti-adjustments-horizontal me-1"></i>{{ __('labels.more_actions') }}
            </button>
            <div class="dropdown-menu dropdown-menu-end">
                @if($editPermission)
                    <a href="{{ route('seller.watch-and-buy.reels.edit', $reel) }}" class="dropdown-item">
                        <i class="ti ti-pencil me-2 text-primary"></i>{{ __('labels.edit') }}
                    </a>
                @endif
                @if($publishPermission)
                    <form method="post" action="{{ route('seller.watch-and-buy.reels.'.($statusValue === 'published' ? 'unpublish' : 'publish'), $reel) }}"
                        class="watch-buy-action-form" data-error-message="{{ __('labels.something_went_wrong') }}">
                        @csrf
                        <button type="submit" class="dropdown-item">
                            <i class="ti {{ $statusValue === 'published' ? 'ti-player-pause text-warning' : 'ti-player-play text-success' }} me-2"></i>
                            {{ $statusValue === 'published' ? __('labels.watch_buy_unpublish') : __('labels.watch_buy_publish') }}
                        </button>
                    </form>
                @endif
                @if($deletePermission)
                    @if($editPermission || $publishPermission)<div class="dropdown-divider"></div>@endif
                    <form method="post" action="{{ route('seller.watch-and-buy.reels.destroy', $reel) }}"
                        class="watch-buy-action-form watch-buy-delete-form" data-error-message="{{ __('labels.something_went_wrong') }}">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="dropdown-item text-danger watch-buy-delete"
                            data-title="{{ __('labels.watch_buy_delete_reel_title') }}"
                            data-text="{{ __('labels.watch_buy_delete_reel_text') }}"
                            data-confirm-text="{{ __('labels.watch_buy_delete_permanently') }}"
                            data-cancel-text="{{ __('labels.cancel') }}">
                            <i class="ti ti-trash me-2"></i>{{ __('labels.delete') }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @else
        <span class="text-muted">—</span>
    @endif
</div>
