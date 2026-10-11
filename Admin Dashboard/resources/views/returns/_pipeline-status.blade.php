@php
    use App\Enums\Order\OrderItemReturnStatusEnum;

    // Comment: Ordered happy-path statuses for calculating progression index
    $statusOrder = [
        (string) OrderItemReturnStatusEnum::REQUESTED(),
        (string) OrderItemReturnStatusEnum::SELLER_APPROVED(),
        (string) OrderItemReturnStatusEnum::PICKUP_ASSIGNED(),
        (string) OrderItemReturnStatusEnum::PICKED_UP(),
        (string) OrderItemReturnStatusEnum::RECEIVED_BY_SELLER(),
        (string) OrderItemReturnStatusEnum::REFUND_PROCESSED(),
        (string) OrderItemReturnStatusEnum::COMPLETED(),
    ];

    $current = (string) $return->return_status;
    $pickupStatus = (string) $return->pickup_status;
    $orderItemStatus = (string) ($return->orderItem?->status ?? '');

    // Comment: Check for early rejection by seller or late rejection/cancellation at store or during pickup
    $rejectedInitial = $current === (string) OrderItemReturnStatusEnum::SELLER_REJECTED();
    $rejectedStore = in_array($current, [
        (string) OrderItemReturnStatusEnum::REJECTED_AT_STORE(),
        (string) OrderItemReturnStatusEnum::CANCELLED(),
        'rejected',
    ], true) || in_array($pickupStatus, [
            (string) \App\Enums\Order\OrderItemReturnPickupStatusEnum::CANCELLED(),
            'rejected',
            'cancelled',
        ], true) || in_array($orderItemStatus, [
            (string) \App\Enums\Order\OrderItemStatusEnum::REJECTED(),
            (string) \App\Enums\Order\OrderItemStatusEnum::CANCELLED(),
            'rejected',
            'cancelled',
        ], true);
    $rejected = $rejectedInitial || $rejectedStore;

    if ($rejectedInitial) {
        $currentIndex = 1;
    } elseif ($rejectedStore) {
        $currentIndex = 3;
    } else {
        $currentIndex = array_search($current, $statusOrder, true);
    }

    $steps = [
        ['key' => 'requested', 'label' => __('labels.requested')],
        ['key' => 'approved', 'label' => $rejectedInitial ? __('labels.rejected') : __('labels.approved')],
        ['key' => 'picked_up', 'label' => __('labels.picked_up')],
        ['key' => 'received', 'label' => $rejectedStore ? (__('labels.rejected_at_store') ?? 'Rejected at store / Cancelled') : __('labels.received')],
        ['key' => 'refunded', 'label' => $rejected && (float) $return->refund_amount <= 0 ? (__('labels.no_refund_customer') ?? 'No Refund') : __('labels.refunded')],
    ];
@endphp

<div class="d-flex flex-wrap align-items-center gap-2 small p-0">
    @foreach ($steps as $i => $step)
        @php
            // Comment: Step is completed unless it falls after the step at which rejection occurred
            $done = $currentIndex !== false && $i <= $currentIndex && !($rejectedInitial && $i > 1) && !($rejectedStore && $i > 3);
            $isRejectedStep = ($rejectedInitial && $i === 1) || ($rejectedStore && $i === 3);
            $stepClass = $isRejectedStep
                ? 'text-danger fw-medium'
                : ($done ? 'text-success fw-medium' : 'text-muted');
            $dotClass = $isRejectedStep
                ? 'bg-danger'
                : ($done ? 'bg-success' : 'bg-secondary-lt');
        @endphp
        <span class="d-inline-flex align-items-center gap-1 {{ $stepClass }}">
            <span class="status-dot status-dot-animated {{ $dotClass }}"></span>
            {{ $step['label'] }}
        </span>
        @unless ($loop->last)
            <span class="text-muted">|</span>
        @endunless
    @endforeach
</div>