@php
    use App\Enums\Order\OrderItemStatusEnum;

    $isSeller = $panel === 'seller';
    $sellerActions = [];

    // Build the seller dropdown menu items based on the item's current status.
    // Empty list = no dropdown rendered (just the View icon).
    if ($isSeller && $editPermission && !empty($status) && !($isReschedulePending ?? false)) {
        switch ($status) {
            case OrderItemStatusEnum::AWAITING_STORE_RESPONSE():
                $sellerActions = [
                    ['label' => __('labels.accept'), 'icon' => 'ti-check', 'class' => 'text-success', 'modal' => '#acceptModel'],
                    ['label' => __('labels.reject'), 'icon' => 'ti-x', 'class' => 'text-danger', 'modal' => '#rejectModel'],
                ];
                break;

            case OrderItemStatusEnum::ACCEPTED():
                $sellerActions = [];
                if (!($isPickup ?? false)) {
                    $prepItem = ['label' => __('labels.preparing'), 'icon' => 'ti-tools-kitchen-2', 'class' => 'text-primary', 'modal' => '#preparingModel'];
                    if ($isPrepLocked ?? false) {
                        $prepItem['disabled'] = true;
                        $prepItem['disabled_reason'] = __('messages.scheduled_delivery_prep_too_early', ['time' => $prepStartTimeStr ?? '']);
                        $prepItem['class'] = 'text-muted';
                    }
                    $sellerActions[] = $prepItem;
                }
                $sellerActions[] = ['label' => __('labels.cancel_item'), 'icon' => 'ti-ban', 'class' => 'text-danger', 'modal' => '#cancelItemModal'];
                break;

            case OrderItemStatusEnum::PREPARING():
                $sellerActions = [
                    ['label' => __('labels.cancel_item'), 'icon' => 'ti-ban', 'class' => 'text-danger', 'modal' => '#cancelItemModal'],
                ];
                break;

            case OrderItemStatusEnum::RETURNING_TO_STORE():
                $sellerActions = [
                    ['label' => __('labels.confirm_received'), 'icon' => 'ti-package-import', 'class' => 'text-success', 'modal' => '#confirmReturnModal'],
                ];
                break;
        }
    }
@endphp

<div class="btn-list flex-nowrap">
    {{-- View link is always present and always icon-only — keeps the row tidy. --}}
    <a href="{{ $route }}"
       class="btn btn-icon btn-outline-primary"
       title="{{ __('labels.view') }}"
       data-bs-toggle="tooltip">
        <i class="ti ti-eye"></i>
    </a>

    @if($panel === 'admin')
        <a href="{{ url('admin/orders/invoice?id=' . $uuid) }}"
           class="btn btn-icon btn-outline-secondary"
           title="{{ __('labels.invoice') }}"
           data-bs-toggle="tooltip">
            <i class="ti ti-invoice"></i>
        </a>
    @endif

   @if(!empty($sellerActions) || $panel === 'seller' || $panel === 'admin')
        <div class="dropdown">
            <button type="button"
                    class="btn btn-icon btn-ghost-secondary"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                    title="{{ __('labels.actions') }}">
                <i class="ti ti-dots-vertical"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end">
                 @php
                    $isScheduledActions = str_contains(request()->route()?->getName() ?? '', 'scheduled_orders') || request()->boolean('is_scheduled');
                 @endphp
                 @if($panel === 'admin')
                    <a href="{{ route($isScheduledActions ? 'admin.scheduled_orders.invoice' : 'admin.orders.invoice', ['id' => $uuid]) }}"
                       target="_blank"
                       class="dropdown-item">
                        <i class="ti ti-invoice me-2"></i>{{ __('labels.download_invoice') }}
                    </a>
                    <button type="button"
                            class="dropdown-item js-whatsapp-invoice"
                            data-url="{{ route($isScheduledActions ? 'admin.scheduled_orders.invoice.whatsapp' : 'admin.orders.invoice.whatsapp') }}"
                            data-id="{{ $uuid }}">
                        <i class="ti ti-brand-whatsapp me-2"></i>Send invoice on WhatsApp
                    </button>
                @elseif($panel === 'seller')
                    <a href="{{ route($isScheduledActions ? 'seller.scheduled_orders.invoice' : 'seller.orders.invoice', ['id' => $uuid]) }}"
                       target="_blank"
                       class="dropdown-item">
                        <i class="ti ti-invoice me-2"></i>{{ __('labels.download_invoice') }}
                    </a>
                    <button type="button"
                            class="dropdown-item js-whatsapp-invoice"
                            data-url="{{ route($isScheduledActions ? 'seller.scheduled_orders.invoice.whatsapp' : 'seller.orders.invoice.whatsapp') }}"
                            data-id="{{ $uuid }}">
                        <i class="ti ti-brand-whatsapp me-2"></i>Send invoice on WhatsApp
                    </button>
                @endif
                @if(($isReschedulePending ?? false) && $panel === 'seller')
                    <div class="dropdown-divider"></div>
                    <span class="dropdown-item text-warning disabled">
                        <i class="ti ti-calendar-exclamation me-2"></i>{{ __('labels.needs_reschedule') }}
                    </span>
                @endif
                @if(!empty($sellerActions))
                    <div class="dropdown-divider"></div>
                    @foreach($sellerActions as $action)
                        <button type="button"
                                class="dropdown-item {{ $action['class'] }}"
                                @if(!empty($action['disabled'])) disabled title="{{ $action['disabled_reason'] ?? '' }}" style="cursor: not-allowed;" @else data-bs-toggle="modal" data-bs-target="{{ $action['modal'] }}" @endif
                                data-id="{{ $id }}">
                            <i class="ti {{ $action['icon'] }} me-2"></i>{{ $action['label'] }}
                        </button>
                    @endforeach
                @endif
            </div>
        </div>
    @endif
</div>
