@php
    $statusValue = $reel->status instanceof \BackedEnum ? $reel->status->value : $reel->status;
@endphp

@if($editPermission || $blockPermission || $deletePermission)
    <div class="dropdown">
        <button type="button" class="btn btn-outline-primary dropdown-toggle text-nowrap" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="ti ti-adjustments-horizontal me-1"></i>{{ __('labels.more_actions') }}
        </button>
        <div class="dropdown-menu dropdown-menu-end">
            @if($editPermission && $statusValue !== 'blocked')
                <a href="{{ route('admin.watch-and-buy.reels.edit', $reel) }}" class="dropdown-item">
                    <i class="ti ti-pencil me-2 text-primary"></i>{{ __('labels.edit') }}
                </a>
                <form method="post" action="{{ route('admin.watch-and-buy.reels.'.($statusValue === 'published' ? 'unpublish' : 'publish'), $reel) }}"
                    class="watch-buy-action-form" data-error-message="{{ __('labels.something_went_wrong') }}">
                    @csrf
                    <button type="submit" class="dropdown-item">
                        <i class="ti {{ $statusValue === 'published' ? 'ti-player-pause text-warning' : 'ti-player-play text-success' }} me-2"></i>
                        {{ $statusValue === 'published' ? __('labels.watch_buy_unpublish') : __('labels.watch_buy_publish') }}
                    </button>
                </form>
            @endif

            @if($blockPermission)
                @if($statusValue === 'blocked')
                    <form method="post" action="{{ route('admin.watch-and-buy.reels.unblock', $reel) }}"
                        class="watch-buy-action-form" data-error-message="{{ __('labels.something_went_wrong') }}">
                        @csrf
                        <button type="submit" class="dropdown-item">
                            <i class="ti ti-lock-open me-2 text-success"></i>{{ __('labels.unblock') }}
                        </button>
                    </form>
                @else
                    <form method="post" action="{{ route('admin.watch-and-buy.reels.block', $reel) }}"
                        class="watch-buy-action-form watch-buy-reason-form" data-error-message="{{ __('labels.something_went_wrong') }}">
                        @csrf
                        <input type="hidden" name="reason">
                        <button type="button" class="dropdown-item watch-buy-reason-action"
                            data-title="{{ __('labels.watch_buy_block_reel_title') }}"
                            data-placeholder="{{ __('labels.watch_buy_block_reason_placeholder') }}"
                            data-confirm-text="{{ __('labels.watch_buy_confirm') }}"
                            data-cancel-text="{{ __('labels.cancel') }}"
                            data-reason-required="{{ __('labels.watch_buy_reason_required') }}">
                            <i class="ti ti-lock me-2 text-warning"></i>{{ __('labels.block') }}
                        </button>
                    </form>
                @endif
            @endif

            @if($deletePermission)
                @if($editPermission || $blockPermission)<div class="dropdown-divider"></div>@endif
                <form method="post" action="{{ route('admin.watch-and-buy.reels.destroy', $reel) }}"
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
