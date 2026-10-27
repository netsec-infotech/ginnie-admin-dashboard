@php
    $demoMode = \App\Models\WatchBuyStatus::doesNotExpireInDemoMode();
@endphp

<div class="watch-buy-status-gallery" role="list">
    @foreach ($statuses as $status)
        @php
            $contentType = $status->content_type->value ?? $status->content_type;
            $contentTypeLabel = __('labels.watch_buy_status_'.($contentType === 'text' ? 'text_only' : $contentType));
            $profileName = '@'.($profile?->username ?? $status->profile?->username ?? __('labels.watch_buy_unknown'));
            $creatorName = $status->creator?->name ?? __('labels.watch_buy_unknown');
            $productCount = $status->products->count();
            $deleteUrl = ($canDelete ?? false) ? route($deleteRoute, $status) : null;
        @endphp

        <article class="watch-buy-status-card" data-status-id="{{ $status->id }}" role="listitem">
            <div class="watch-buy-status-card-media watch-buy-status-theme-{{ $status->id % 4 }}">
                @if ($contentType === 'image' && $status->media_url)
                    <img src="{{ $status->media_url }}" alt="{{ __('labels.watch_buy_status_image') }}" loading="lazy">
                @elseif ($contentType === 'video' && $status->media_url)
                    <video src="{{ $status->media_url }}" muted preload="metadata" playsinline tabindex="-1"></video>
                    <span class="watch-buy-status-play-icon"><i class="ti ti-player-play-filled"></i></span>
                @elseif ($contentType === 'text')
                    <div class="watch-buy-status-text-only">{{ $status->text }}</div>
                @else
                    <div class="watch-buy-status-media-unavailable">
                        <i class="ti ti-photo-off"></i>
                        <span>{{ __('labels.watch_buy_status_media_unavailable') }}</span>
                    </div>
                @endif

                <button type="button" class="watch-buy-status-preview-trigger"
                    data-bs-toggle="modal" data-bs-target="#watch-buy-status-preview-modal"
                    data-status-id="{{ $status->id }}"
                    data-profile="{{ $profileName }}"
                    data-content-type="{{ $contentType }}"
                    data-content-type-label="{{ $contentTypeLabel }}"
                    data-media-url="{{ $status->media_url }}"
                    data-text="{{ $status->text }}"
                    data-created-by="{{ __('labels.watch_buy_status_created_by', ['name' => $creatorName]) }}"
                    data-expires-at="{{ $status->expires_at->toIso8601String() }}"
                    data-expires-label="{{ __('labels.watch_buy_status_expires_in') }}"
                    data-demo-mode="{{ $demoMode ? '1' : '0' }}"
                    data-never-expires-label="{{ __('labels.watch_buy_status_never_expires_demo') }}"
                    data-products-label="{{ trans_choice('labels.watch_buy_status_product_count', $productCount, ['count' => $productCount]) }}"
                    @if ($deleteUrl)
                        data-delete-url="{{ $deleteUrl }}"
                        data-delete-title="{{ __('labels.watch_buy_delete_status') }}"
                        data-delete-text="{{ __('labels.watch_buy_delete_status_confirmation') }}"
                        data-delete-confirm-text="{{ __('labels.watch_buy_delete_permanently') }}"
                        data-delete-cancel-text="{{ __('labels.cancel') }}"
                    @endif
                    aria-label="{{ __('labels.watch_buy_preview_status_by', ['username' => $profileName]) }}">
                    <span><i class="ti ti-eye"></i>{{ __('labels.watch_buy_view_status') }}</span>
                </button>

                <div class="watch-buy-status-card-topbar">
                    <span class="badge bg-dark-lt text-dark">{{ $contentTypeLabel }}</span>
                    @if ($deleteUrl)
                        <button type="button" class="btn btn-icon btn-sm btn-dark watch-buy-status-delete"
                            data-status-id="{{ $status->id }}"
                            data-url="{{ $deleteUrl }}"
                            data-title="{{ __('labels.watch_buy_delete_status') }}"
                            data-text="{{ __('labels.watch_buy_delete_status_confirmation') }}"
                            data-confirm-text="{{ __('labels.watch_buy_delete_permanently') }}"
                            data-cancel-text="{{ __('labels.cancel') }}"
                            title="{{ __('labels.watch_buy_delete_status') }}"
                            aria-label="{{ __('labels.watch_buy_delete_status') }}">
                            <i class="ti ti-trash"></i>
                        </button>
                    @endif
                </div>

                <div class="watch-buy-status-card-expiry">
                    <i class="ti ti-clock"></i>
                    @if ($demoMode)
                        <span>{{ __('labels.watch_buy_status_never_expires_demo') }}</span>
                    @else
                        <span class="watch-buy-status-countdown"
                            data-expires-at="{{ $status->expires_at->toIso8601String() }}"
                            data-expires-label="{{ __('labels.watch_buy_status_expires_in') }}"></span>
                    @endif
                </div>
            </div>

            <div class="watch-buy-status-card-summary">
                @if ($status->text && $contentType !== 'text')
                    <div class="watch-buy-status-card-text">{{ $status->text }}</div>
                @endif
                <div class="text-secondary small text-truncate">
                    {{ __('labels.watch_buy_status_created_by', ['name' => $creatorName]) }}
                </div>
                @if ($status->products->isNotEmpty())
                    <div class="watch-buy-status-products">
                        <div class="avatar-list avatar-list-stacked">
                            @foreach ($status->products->take(3) as $attachment)
                                @php($product = $attachment->variant?->product)
                                @if ($product?->main_image)
                                    <span class="avatar avatar-xs rounded" style="background-image: url('{{ $product->main_image }}')" title="{{ $product->getTranslation('title', 'en', false) }}"></span>
                                @else
                                    <span class="avatar avatar-xs rounded bg-secondary-lt"><i class="ti ti-shopping-bag"></i></span>
                                @endif
                            @endforeach
                        </div>
                        <span>{{ trans_choice('labels.watch_buy_status_product_count', $productCount, ['count' => $productCount]) }}</span>
                    </div>
                @endif
            </div>
        </article>
    @endforeach
</div>

<div class="watch-buy-status-inline-empty d-none">
    <i class="ti ti-clock-hour-4"></i>
    <span>{{ __('labels.watch_buy_no_active_statuses') }}</span>
</div>
