<div class="card seller-watch-buy-statuses mt-4">
    <div class="card-header align-items-center">
        <div class="d-flex align-items-center gap-3">
            <span class="watch-buy-status-profile-ring">
                @if ($profile->photo_url)
                    <span class="avatar avatar-lg rounded-circle" style="background-image: url('{{ $profile->photo_url }}')"></span>
                @else
                    <span class="avatar avatar-lg rounded-circle bg-primary-lt text-primary">{{ strtoupper(substr($profile->username, 0, 1)) }}</span>
                @endif
            </span>
            <div>
                <h3 class="card-title">{{ __('labels.watch_buy_your_statuses') }}</h3>
                <div class="text-secondary small">{{ __('labels.watch_buy_statuses_expire_hint') }}</div>
            </div>
        </div>

        @if ($canCreate && $statusesEnabled && ! $suspended)
            <div class="card-actions">
                <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#{{ $composerId }}">
                    <i class="ti ti-plus me-1"></i>{{ __('labels.watch_buy_add_status') }}
                </button>
            </div>
        @endif
    </div>

    <div class="card-body">
        @unless ($statusesEnabled)
            <div class="alert alert-warning mb-0">{{ __('labels.watch_buy_statuses_disabled') }}</div>
        @else
            @if ($statuses->isEmpty())
                <div class="empty py-4 watch-buy-status-section-empty">
                    <div class="empty-icon"><i class="ti ti-clock-hour-4"></i></div>
                    <p class="empty-title">{{ __('labels.watch_buy_no_active_statuses') }}</p>
                    <p class="empty-subtitle text-secondary">{{ __('labels.watch_buy_no_active_statuses_hint') }}</p>
                </div>
            @else
                @include('watch_buy._status_cards', [
                    'statuses' => $statuses,
                    'profile' => $profile,
                    'deleteRoute' => 'seller.watch-and-buy.statuses.destroy',
                    'canDelete' => $canDelete,
                ])
                <div class="mt-3 watch-buy-status-pagination">{{ $statuses->links() }}</div>
            @endif
        @endunless
    </div>
</div>

@if ($canCreate && $statusesEnabled && ! $suspended)
    @include('watch_buy._status_composer_modal', [
        'modalId' => $composerId,
        'action' => route('seller.watch-and-buy.statuses.store'),
        'endpoint' => route('seller.watch-and-buy.statuses.product-options'),
        'pickerId' => $pickerId,
        'admin' => false,
    ])
@endif

@include('watch_buy._status_preview')
