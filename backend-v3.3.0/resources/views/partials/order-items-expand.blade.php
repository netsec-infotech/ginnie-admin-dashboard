@php
    use App\Enums\Order\OrderItemStatusEnum;
    /** @var \Illuminate\Support\Collection $items */
    /** @var bool $editPermission */
    /** @var string $detailRoute */
@endphp
<div class="p-3 border-top">
    @if($items->isEmpty())
        <div class="empty py-4">
            <div class="empty-icon"><i class="ti ti-package-off"></i></div>
            <p class="empty-title mb-0">{{ __('labels.no_items_for_this_order') }}</p>
        </div>
    @else
        <div class="d-flex flex-column gap-2">
            @foreach($items as $item)
                <div class="card card-borderless shadow-none border">
                    <div class="card-body p-3">
                        <div class="row align-items-center g-3">
                            <div class="col-auto">
                                @if(!empty($item['image']))
                                    <a href="{{ $item['image'] }}" data-fslightbox="gallery"
                                       class="d-inline-block rounded border overflow-hidden"
                                       style="width: 56px; height: 56px;">
                                        <img src="{{ $item['image'] }}" alt=""
                                             style="width: 100%; height: 100%; object-fit: cover;">
                                    </a>
                                @else
                                    <div class="rounded border d-flex align-items-center justify-content-center text-secondary"
                                         style="width: 56px; height: 56px; background: var(--tblr-bg-surface-secondary, #f8fafc);">
                                        <i class="ti ti-package fs-3"></i>
                                    </div>
                                @endif
                            </div>
                            <div class="col">
                                <div class="fw-semibold">{{ $item['product_title'] }}</div>
                                <div class="text-secondary small d-flex flex-wrap gap-3 mt-1">
                                    @if(!empty($item['variant_title']))
                                        <span><i class="ti ti-versions me-1"></i>{{ $item['variant_title'] }}</span>
                                    @endif
                                    @if(!empty($item['store']))
                                        <span><i class="ti ti-building-store me-1"></i>{{ $item['store'] }}</span>
                                    @endif
                                    @if(!empty($item['sku']))
                                        <span><i class="ti ti-barcode me-1"></i>{{ $item['sku'] }}</span>
                                    @endif
                                </div>
                                @if(!empty($item['addons']) && $item['addons']->count() > 0)
                                    <div class="mt-1 small text-secondary">
                                        <span class="me-1">{{ __('labels.addons') }}:</span>
                                        @foreach($item['addons'] as $addon)
                                            <span class="badge bg-secondary-lt me-1">
                                                {{ $addon->addonGroup?->title }} — {{ $addon->addonItem?->title }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                                @if(!empty($item['attachments']))
                                    @php $gallery = 'order-item-attachments-' . (int) $item['id']; @endphp
                                    <div class="mt-2">
                                        <a href="{{ $item['attachments'][0] }}"
                                           data-fslightbox="{{ $gallery }}"
                                           class="badge bg-orange-lt text-decoration-none"
                                           target="_blank">
                                            <i class="ti ti-paperclip me-1"></i>{{ __('labels.prescription') }} ({{ count($item['attachments']) }})
                                        </a>
                                        @foreach(array_slice($item['attachments'], 1) as $url)
                                            <a href="{{ $url }}" data-fslightbox="{{ $gallery }}" hidden></a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <div class="col-auto text-center">
                                <div class="text-secondary small">{{ __('labels.quantity') }}</div>
                                <div class="fw-semibold">× {{ (int) $item['quantity'] }}</div>
                            </div>
                            <div class="col-auto text-end">
                                <div class="text-secondary small">{{ __('labels.subtotal') }}</div>
                                <div class="fw-semibold">{{ $currencyService->format($item['subtotal']) }}</div>
                            </div>
                            <div class="col-auto">
                                @include('partials.order-status', ['status' => $item['status']])
                            </div>
                            @if($editPermission && $item['id'])
                                @if($isReschedulePending ?? false)
                                    <div class="col-auto">
                                        <span class="badge bg-warning-lt text-warning">
                                            <i class="ti ti-calendar-exclamation me-1"></i>{{ __('labels.needs_reschedule') }}
                                        </span>
                                    </div>
                                @elseif(empty($item['status']) || $item['status'] === OrderItemStatusEnum::AWAITING_STORE_RESPONSE())
                                    <div class="col-auto">
                                        <div class="btn-list">
                                            <button type="button" class="btn btn-sm btn-success"
                                                    data-bs-toggle="modal" data-bs-target="#acceptModel"
                                                    data-id="{{ $item['id'] }}">
                                                <i class="ti ti-check me-1"></i>{{ __('labels.accept') }}
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                    data-bs-toggle="modal" data-bs-target="#rejectModel"
                                                    data-id="{{ $item['id'] }}">
                                                <i class="ti ti-x me-1"></i>{{ __('labels.reject') }}
                                            </button>
                                        </div>
                                    </div>
                                @elseif($item['status'] === OrderItemStatusEnum::ACCEPTED())
                                     <div class="col-auto">
                                         <div class="btn-list">
                                             @if(!($isPickup ?? false))
                                                 <button type="button" class="btn btn-sm btn-primary"
                                                         data-bs-toggle="modal" data-bs-target="#preparingModel"
                                                         data-id="{{ $item['id'] }}">
                                                     <i class="ti ti-package me-1"></i>{{ __('labels.mark_as_preparing') }}
                                                 </button>
                                             @endif
                                             <button type="button" class="btn btn-sm btn-outline-danger"
                                                     data-bs-toggle="modal" data-bs-target="#cancelItemModal"
                                                     data-id="{{ $item['id'] }}">
                                                 <i class="ti ti-ban me-1"></i>{{ __('labels.cancel_item') }}
                                             </button>
                                         </div>
                                     </div>
                                @elseif($item['status'] === OrderItemStatusEnum::PREPARING())
                                    <div class="col-auto">
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                data-bs-toggle="modal" data-bs-target="#cancelItemModal"
                                                data-id="{{ $item['id'] }}">
                                            <i class="ti ti-ban me-1"></i>{{ __('labels.cancel_item') }}
                                        </button>
                                    </div>
                                @elseif($item['status'] === OrderItemStatusEnum::RETURNING_TO_STORE())
                                    <div class="col-auto">
                                        <button type="button" class="btn btn-sm btn-success"
                                                data-bs-toggle="modal" data-bs-target="#confirmReturnModal"
                                                data-id="{{ $item['id'] }}">
                                            <i class="ti ti-package-import me-1"></i>{{ __('labels.confirm_received') }}
                                        </button>
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="text-end mt-3 gap-2 d-flex justify-content-end">
            @if(!empty($orderUuid))
                @php
                    $isScheduledExpand = str_contains(request()->route()?->getName() ?? '', 'scheduled_orders') || request()->boolean('is_scheduled');
                    $invoiceExpandRoute = $isScheduledExpand
                        ? ($panel === 'seller' ? route('seller.scheduled_orders.invoice', ['id' => $orderUuid]) : route('admin.scheduled_orders.invoice', ['id' => $orderUuid]))
                        : ($panel === 'seller' ? route('seller.orders.invoice', ['id' => $orderUuid]) : route('admin.orders.invoice', ['id' => $orderUuid]));
                @endphp
                <a href="{{ $invoiceExpandRoute }}"
                   target="_blank"
                   class="btn btn-sm btn-outline-secondary py-2 px-4">
                    <i class="ti ti-invoice me-1"></i>{{ __('labels.download_invoice') }}
                </a>
            @endif
            <a href="{{ $detailRoute }}" class="btn btn-sm btn-outline-primary py-2 px-4">
                <i class="ti ti-external-link me-1"></i>{{ __('labels.view_full_order') }}
            </a>
        </div>
    @endif
</div>
