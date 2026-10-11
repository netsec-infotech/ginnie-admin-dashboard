@php
    use App\Enums\Order\OrderReturnActorEnum;
    use App\Enums\Order\OrderItemReturnStatusEnum;

    /** @var \App\Models\OrderItemReturn $return */
    /** @var string $panel */
    /** @var array{items:array<int,array{label:string,amount:float}>,total:float} $refundBreakdown */

    $isAdminPanel = ($panel ?? 'seller') === 'admin';
    // Comment: Finalized or rejected statuses where neither confirmation nor rejection buttons should appear
    $orderItemStatus = (string) ($return->orderItem?->status ?? '');
    $isFinalizedStatus = !empty($return->store_rejection_reason) || !empty($return->action_on_rejection) || !empty($return->refund_processed_at) || in_array((string) $return->return_status, [
        (string) OrderItemReturnStatusEnum::COMPLETED(),
        (string) OrderItemReturnStatusEnum::REJECTED_AT_STORE(),
        (string) OrderItemReturnStatusEnum::SELLER_REJECTED(),
        (string) OrderItemReturnStatusEnum::CANCELLED(),
        (string) OrderItemReturnStatusEnum::REFUND_PROCESSED(),
        'rejected',
        'completed',
    ], true) || in_array($orderItemStatus, [
        (string) \App\Enums\Order\OrderItemStatusEnum::REJECTED(),
        (string) \App\Enums\Order\OrderItemStatusEnum::REFUNDED(),
        (string) \App\Enums\Order\OrderItemStatusEnum::CANCELLED(),
        'rejected',
        'refunded',
        'cancelled',
    ], true) || in_array((string) $return->pickup_status, [
        (string) \App\Enums\Order\OrderItemReturnPickupStatusEnum::CANCELLED(),
        'rejected',
        'cancelled',
    ], true);

    $isPending = ! $isFinalizedStatus && (string) $return->return_status === (string) OrderItemReturnStatusEnum::REQUESTED();

    // ==========================================================
    // Return Dispute System
    // Phase 3
    // Seller Inspection Pending Status Evaluation
    // ==========================================================
    $isPendingStoreReceipt = !$isAdminPanel && (string) $return->return_status === (string) OrderItemReturnStatusEnum::RECEIVED_BY_SELLER();
    $product      = $return->orderItem?->product;
    $variant      = $return->orderItem?->variant;
    $addons       = $return->orderItem?->addons ?? collect();
    $customer     = $return->user;
    $order        = $return->order;
    $store        = $return->store;
    $sellerUser   = $return->seller?->user;
    $decidedBy    = $return->decidedBy;
    $decidedAt    = $return->admin_decided_at ?? $return->seller_approved_at;
    $decidedByValue = $return->decided_by_type instanceof OrderReturnActorEnum
        ? $return->decided_by_type->value
        : (string) $return->decided_by_type;
    $isAdminDecided = $decidedByValue === (string) OrderReturnActorEnum::ADMIN();
@endphp

<!-- Return Dispute System -->
<!-- UI Polish -->
<!-- Dedicated Seller SLA Inspection Card -->
@if ($isPendingStoreReceipt && !$isAdminPanel)
    @php
        $slaHours = (int) (app(\App\Services\SettingService::class)->getSettingValues('seller')['return_seller_inspection_sla_hours'] ?? 48);
        $receivedAt = $return->received_at;
        $deadline = $receivedAt ? $receivedAt->copy()->addHours($slaHours) : null;
        $now = \Carbon\Carbon::now();
        $slaExpired = $deadline ? $now->greaterThanOrEqualTo($deadline) : false;

        $remainingText = '';
        $warningClass = 'bg-info-lt text-info border-info';
        $borderClass = 'border-info';
        $btnClass = 'btn-info';
        if ($deadline && !$slaExpired) {
            $diffInMinutes = $now->diffInMinutes($deadline);
            $diffInHours = $now->diffInHours($deadline);
            $diffInDays = $now->diffInDays($deadline);

            if ($diffInDays >= 1) {
                $remHours = $diffInHours % 24;
                $remainingText = "{$diffInDays} " . \Illuminate\Support\Str::plural('Day', $diffInDays) . " {$remHours} " . \Illuminate\Support\Str::plural('Hour', $remHours) . " Remaining";
            } elseif ($diffInHours >= 1) {
                $remainingText = "{$diffInHours} " . \Illuminate\Support\Str::plural('Hour', $diffInHours) . " Remaining";
            } else {
                $remainingText = "{$diffInMinutes} " . \Illuminate\Support\Str::plural('Minute', $diffInMinutes) . " Remaining";
            }

            if ($diffInHours < 12) {
                $warningClass = 'bg-warning-lt text-warning-dark border-warning';
                $borderClass = 'border-warning';
                $btnClass = 'btn-warning';
            }
        } else {
            $warningClass = 'bg-danger-lt text-danger border-danger';
            $borderClass = 'border-danger';
            $btnClass = 'btn-danger';
        }
    @endphp

    <div class="card {{ $borderClass }} mb-3 shadow-sm">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-yellow-lt text-yellow px-2 py-1 fs-5">Awaiting Seller Inspection</span>
                        @if ($deadline)
                            <span class="text-secondary small">Received at: {{ $receivedAt?->format('Y-m-d H:i') }}</span>
                        @endif
                    </div>
                    <h3 class="mb-2">Inspection Deadline</h3>
                    <div class="p-3 mb-3 rounded {{ $warningClass }} border">
                        <div class="d-flex align-items-center">
                            <i class="ti ti-clock me-2 fs-1"></i>
                            <div>
                                @if ($slaExpired)
                                    <div class="fw-bold fs-3">Inspection deadline has expired. This return will be processed automatically during the next scheduled system check.</div>
                                @else
                                    <div class="fw-bold fs-3">{{ $remainingText }}</div>
                                    <div class="text-secondary small mt-1">Must act before: {{ $deadline?->format('Y-m-d H:i') }}</div>
                                @endif
                            </div>
                        </div>
                    </div>
                    <p class="mb-0 text-muted">
                        <strong>Guidance:</strong> Inspect the returned item carefully. If the returned product is correct, accept the return. If there is any issue with the returned product, escalate the dispute to the administrator with supporting evidence before the inspection deadline expires.
                    </p>
                </div>
                <div class="col-lg-4 text-end mt-3 mt-lg-0 d-flex flex-column gap-2">
                    <button type="button" class="btn btn-success py-2.5 fs-3 w-100 d-flex align-items-center justify-content-center gap-2"
                            data-bs-toggle="modal" data-bs-target="#storeConfirmModel"
                            data-id="{{ $return->id }}">
                        <i class="ti ti-circle-check fs-2"></i> Accept Return
                    </button>
                    <button type="button" class="btn btn-outline-danger py-2.5 fs-3 w-100 d-flex align-items-center justify-content-center gap-2"
                            data-bs-toggle="modal" data-bs-target="#storeEscalateModel"
                            data-id="{{ $return->id }}">
                        <i class="ti ti-alert-triangle fs-2"></i> Escalate to Admin
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif

<div class="card mb-3">
    <div class="card-body">

        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h2 class="mb-1 d-flex align-items-center gap-2">
                    {{ __('labels.return_request') }} #{{ $return->id }}
                </h2>
                <div class="text-secondary small">
                    {{ __('labels.submitted') }} {{ $return->created_at?->format('Y-m-d H:i') }}
                    @if ($return->created_at)
                        ({{ $return->created_at->diffForHumans() }})
                    @endif
                </div>
                <div class="mt-3 d-flex align-items-center gap-2">
                    @include('returns._pipeline-status', ['return' => $return])
                    <x-form-info-tooltip :title="__('labels.tooltip_progress')" />
                </div>
            </div>
            <div class="d-flex gap-2">
                @php
                    $acceptTarget = $isAdminPanel ? '#adminAcceptModel' : '#acceptModel';
                    $rejectTarget = $isAdminPanel ? '#adminRejectModel' : '#rejectModel';
                @endphp
                @if ($isPending)
                    <button type="button" class="btn btn-outline-danger"
                            data-bs-toggle="modal" data-bs-target="{{ $rejectTarget }}"
                            data-id="{{ $return->id }}">
                        {{ __('labels.reject') }}
                    </button>
                    <button type="button" class="btn btn-success"
                            data-bs-toggle="modal" data-bs-target="{{ $acceptTarget }}"
                            data-id="{{ $return->id }}">
                        {{ __('labels.approve') }}
                    </button>
                @elseif ($isPendingStoreReceipt && !$isAdminPanel)
                    <!-- Handled by dedicated Seller Inspection card above -->
                @else
                    {{-- Comment: Display clear status badge when actions are finalized or rejected --}}
                    @php
                        $isRejectedOrCancelled = !empty($return->store_rejection_reason) || $return->action_on_rejection === 'no_refund_customer' || (!empty($return->received_at) && (float) $return->refund_amount <= 0) || in_array((string) $return->return_status, [
                            (string) OrderItemReturnStatusEnum::SELLER_REJECTED(),
                            (string) OrderItemReturnStatusEnum::REJECTED_AT_STORE(),
                            (string) OrderItemReturnStatusEnum::CANCELLED(),
                            'rejected',
                        ], true) || in_array((string) ($return->orderItem?->status ?? ''), [
                            (string) \App\Enums\Order\OrderItemStatusEnum::REJECTED(),
                            (string) \App\Enums\Order\OrderItemStatusEnum::CANCELLED(),
                            'rejected',
                            'cancelled',
                        ], true) || in_array((string) $return->pickup_status, [
                            (string) \App\Enums\Order\OrderItemReturnPickupStatusEnum::CANCELLED(),
                            'rejected',
                            'cancelled',
                        ], true);
                        $statusBadgeText = \Illuminate\Support\Str::headline((string) $return->return_status);
                        if (in_array((string) $return->pickup_status, ['cancelled', 'rejected'], true) && !in_array((string) $return->return_status, ['cancelled', 'rejected_at_store', 'seller_rejected'], true)) {
                            $statusBadgeText = \Illuminate\Support\Str::headline((string) $return->pickup_status);
                        }
                        if (!empty($return->store_rejection_reason) || $return->action_on_rejection === 'no_refund_customer' || (string) $return->return_status === (string) OrderItemReturnStatusEnum::REJECTED_AT_STORE()) {
                            $statusBadgeText = __('labels.rejected_at_store') ?? 'Rejected At Store';
                        } elseif ((string) $return->return_status === (string) OrderItemReturnStatusEnum::SELLER_REJECTED() || (string) ($return->orderItem?->status ?? '') === 'rejected') {
                            $statusBadgeText = __('labels.rejected') ?? 'Seller Rejected';
                        } elseif (!empty($return->received_at) && (float) $return->refund_amount <= 0 && $return->action_on_rejection !== 'deduct_from_delivery_boy') {
                            $statusBadgeText = __('labels.completed') ?? 'Completed';
                        }
                    @endphp
                    @if ($isRejectedOrCancelled)
                        <span class="badge bg-danger-lt fs-3 px-3 py-2">
                            {{ $statusBadgeText }} @if ((float) $return->refund_amount <= 0 && !str_contains(strtolower($statusBadgeText), 'no refund')) ({{ __('labels.no_refund_customer') ?? 'No Refund' }}) @endif
                        </span>
                    @else
                        <span class="badge bg-secondary-lt fs-3 px-3 py-2">
                            {{ $statusBadgeText }}
                        </span>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row row-cards">
    {{-- LEFT column --}}
    <div class="col-12 col-lg-8">
        {{-- Product --}}
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">{{ __('labels.product') }}</h3>
            </div>
            <div class="card-body">
                <div class="d-flex gap-3">
                    @if ($product?->main_image)
                        <img src="{{ $product->main_image }}" alt="" class="rounded"
                             style="width: 80px; height: 80px; object-fit: cover;">
                    @endif
                    <div class="flex-fill">
                        <div class="fw-medium">{{ $product?->title ?? __('labels.n_a') }}</div>
                        @if ($variant?->title)
                            <div class="text-secondary small">{{ $variant->title }}</div>
                        @endif
                        <div class="text-secondary small mt-1">
                            {{ __('labels.qty') }}: {{ $return->orderItem?->quantity ?? 1 }}
                        </div>
                        @if ($addons->isNotEmpty())
                            <div class="mt-2">
                                <div class="small fw-medium">{{ __('labels.addons') }}:</div>
                                <ul class="list-unstyled mb-0 small ps-3">
                                    @foreach ($addons as $addon)
                                        <li>
                                            @if ($addon->addonGroup?->title)
                                                {{ $addon->addonGroup->title }} —
                                            @endif
                                            {{ $addon->addonItem?->title ?? '—' }}
                                            <span class="text-secondary">· {{ number_format((float) $addon->price, 2) }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Customer reason + photos --}}
        @if (!($hideEvidenceCards ?? false))
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">
                    {{ __('labels.customer_reason') }}
                    <x-form-info-tooltip :title="__('labels.tooltip_customer_reason')" />
                </h3>
            </div>
            <div class="card-body">
                @if ($return->reason)
                    <p class="mb-3">{{ $return->reason }}</p>
                @else
                    <p class="text-muted mb-3">{{ __('labels.no_reason_provided') }}</p>
                @endif

                @if (! empty($return->images))
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($return->images as $imageUrl)
                            <a href="{{ $imageUrl }}" data-fslightbox="gallery" class="d-inline-block">
                                <img src="{{ $imageUrl }}" alt=""
                                     class="rounded border"
                                     style="width: 120px; height: 120px; object-fit: cover;">
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
        @endif

        {{-- Refund breakdown --}}
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">
                    {{ __('labels.refund_breakdown') }}
                    <x-form-info-tooltip :title="__('labels.tooltip_refund_breakdown')" />
                </h3>
            </div>
            <div class="card-body">
                {{-- Comment: If return request is rejected and customer receives no refund, display clear rejection message --}}
                @if (in_array((string) $return->return_status, [(string) OrderItemReturnStatusEnum::SELLER_REJECTED(), (string) OrderItemReturnStatusEnum::REJECTED_AT_STORE()], true) && (float) $return->refund_amount <= 0)
                    <div class="alert alert-danger mb-0">
                        <i class="ti ti-alert-circle me-1"></i>
                        {{ __('labels.no_refund_customer') ?? 'No Refund to Customer' }} — {{ __('labels.return_request_rejected') ?? 'This return request was rejected and no customer refund will be processed.' }}
                    </div>
                @else
                    <table class="table table-vcenter mb-0">
                        <tbody>
                        @foreach ($refundBreakdown['items'] as $row)
                            <tr>
                                <td>{{ $row['label'] }}</td>
                                <td class="text-end">{{ number_format($row['amount'], 2) }}</td>
                            </tr>
                        @endforeach
                        <tr class="fw-bold">
                            <td>{{ __('labels.refund_total') }}</td>
                            <td class="text-end">{{ number_format($refundBreakdown['total'], 2) }}</td>
                        </tr>
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        {{-- Seller / Admin notes --}}
        @if ($return->seller_comment || $return->admin_comment)
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">{{ __('labels.notes') }}</h3>
                </div>
                <div class="card-body">
                    @if ($return->seller_comment)
                        <div class="mb-2">
                            <div class="small fw-medium">{{ __('labels.seller_note') }}</div>
                            <div class="text-secondary">{{ $return->seller_comment }}</div>
                        </div>
                    @endif
                    @if ($return->admin_comment)
                        <div>
                            <div class="small fw-medium">{{ __('labels.admin_note') }}</div>
                            <div class="text-secondary">{{ $return->admin_comment }}</div>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Timeline --}}
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">
                    {{ __('labels.timeline') }}
                    <x-form-info-tooltip :title="__('labels.tooltip_timeline')" />
                </h3>
            </div>
            <div class="card-body">
                @include('returns._timeline', ['timelineSteps' => $timelineSteps])
            </div>
        </div>
    </div>

    {{-- RIGHT column --}}
    <div class="col-12 col-lg-4">
        {{-- Customer --}}
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">{{ __('labels.customer') }}</h3>
            </div>
            <div class="card-body">
                <div class="fw-medium">{{ $customer?->name ?? __('labels.n_a') }}</div>
                @if ($customer?->email)
                    <div class="text-secondary small">{{ $customer->email }}</div>
                @endif
                @if ($isAdminPanel && $customer?->mobile)
                    <div class="text-secondary small">{{ $customer->mobile }}</div>
                @endif
            </div>
        </div>

        {{-- Order --}}
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">{{ __('labels.order') }}</h3>
            </div>
            <div class="card-body">
                <div class="fw-medium">#{{ $order?->order_id ?? $return->order_id }}</div>
                @if ($order?->created_at)
                    <div class="text-secondary small">{{ $order->created_at->format('Y-m-d H:i') }}</div>
                @endif
                @if ($order)
                    <div class="mt-2 text-secondary small">
                        {{ __('labels.order_item_id') }} #{{ $return->order_item_id }}
                    </div>
                @endif
            </div>
        </div>

        {{-- Store / Seller (admin-only) --}}
        @if ($isAdminPanel)
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">{{ __('labels.store_seller') }}</h3>
                </div>
                <div class="card-body">
                    <div class="fw-medium">{{ $store?->name ?? __('labels.n_a') }}</div>
                    @if ($sellerUser?->name)
                        <div class="text-secondary small">{{ $sellerUser->name }}</div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Pickup --}}
        @php
            use App\Enums\Order\OrderItemReturnPickupStatusEnum;

            $pickupStatus = (string) $return->pickup_status;
            $isApproved   = (string) $return->return_status === (string) OrderItemReturnStatusEnum::SELLER_APPROVED();
            $isAssignable = $isApproved && in_array($pickupStatus, [
                (string) OrderItemReturnPickupStatusEnum::PENDING(),
                (string) OrderItemReturnPickupStatusEnum::ASSIGNED(),
            ], true);
            $rider        = $return->deliveryBoy;
            $canAssign    = ($isAdminPanel ?? false) && ($canAssignRider ?? false);
        @endphp
        @if (!($hideEvidenceCards ?? false))
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">
                    {{ __('labels.pickup') }}
                    <x-form-info-tooltip :title="__('labels.tooltip_pickup')" placement="left" />
                </h3>
            </div>
            <div class="card-body">
                <div class="mb-1">
                    <span class="text-secondary small">{{ __('labels.status') }}:</span>
                    <span class="fw-medium">{{ \Illuminate\Support\Str::headline($pickupStatus) }}</span>
                </div>
                @if ($rider)
                    @php
                        $riderName = $rider->full_name ?? $rider->user?->name ?? '—';
                        $riderPhone = $rider->user?->mobile ?? $rider->user?->phone ?? null;
                    @endphp
                    <div class="text-secondary small">
                        {{ __('labels.rider') }}: {{ $riderName }}
                        @if ($riderPhone)
                            · <a href="tel:{{ $riderPhone }}">{{ $riderPhone }}</a>
                        @endif
                    </div>
                @endif

                @if ($return->pickup_otp || ($return->snapshot_otp_required ?? false))
                    <div class="mt-2 text-secondary small d-flex align-items-center gap-1">
                        <span>{{ __('labels.return_otp') ?? 'Pickup OTP' }}:</span>
                        <span class="fw-medium">{{ $return->pickup_otp ?? '—' }}</span>
                        @if ($return->pickup_otp_verified)
                            <span class="badge bg-success-lt small">{{ __('labels.verified') ?? 'Verified' }}</span>
                        @else
                            <span class="badge bg-warning-lt small">{{ __('labels.pending') ?? 'Pending' }}</span>
                        @endif
                    </div>
                @endif

                @if (! empty($return->pickup_images))
                    <div class="mt-2">
                        <div class="small text-secondary mb-1">{{ __('labels.pickup_images') ?? 'Rider Proof Images' }}:</div>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach ($return->pickup_images as $imageUrl)
                                <a href="{{ $imageUrl }}" data-fslightbox="gallery" class="d-inline-block">
                                    <img src="{{ $imageUrl }}" alt=""
                                         class="rounded border"
                                         style="width: 60px; height: 60px; object-fit: cover;">
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($canAssign && $isAssignable)
                    <button type="button" class="btn btn-outline-primary btn-sm mt-2"
                            data-bs-toggle="modal" data-bs-target="#assignRiderModal">
                        <i class="ti ti-users me-1"></i>
                        {{ $rider ? __('labels.reassign_rider') : __('labels.assign_rider') }}
                    </button>
                @endif
            </div>
        </div>
        @endif

        @if (!($hideEvidenceCards ?? false))
        @if ($return->store_rejection_reason || (string) $return->return_status === (string) OrderItemReturnStatusEnum::REJECTED_AT_STORE())
            <div class="card mb-3 border-danger">
                <div class="card-header bg-danger-lt">
                    <h3 class="card-title text-danger">
                        {{ __('labels.store_rejection_details') ?? 'Store Rejection Details' }}
                    </h3>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <span class="text-secondary small">{{ __('labels.rejection_reason') }}:</span>
                        <div class="fw-medium">{{ $return->store_rejection_reason ?? '—' }}</div>
                    </div>
                    <div>
                        <span class="text-secondary small">{{ __('labels.action_taken') ?? 'Action Taken' }}:</span>
                        <div class="fw-medium mt-1">
                            @php
                                $respParty = $return->responsible_party instanceof \BackedEnum ? $return->responsible_party->value : (string) $return->responsible_party;
                                $deductionType = $return->rider_deduction_type;
                            @endphp
                            @if ($respParty === 'rider' || $return->deduct_from_rider || $return->action_on_rejection === 'deduct_from_delivery_boy')
                                @if ($deductionType === 'delivery_earning')
                                    <span class="badge bg-primary-lt">
                                        {{ \Illuminate\Support\Facades\Lang::has('labels.customer_refunded_deducted_delivery_earning') ? __('labels.customer_refunded_deducted_delivery_earning') : 'Customer Refunded · Deducted Delivery Earning' }}
                                    </span>
                                @elseif ($deductionType === 'product_cost' || $return->deduct_from_rider || $return->action_on_rejection === 'deduct_from_delivery_boy')
                                    <span class="badge bg-warning-lt">
                                        {{ \Illuminate\Support\Facades\Lang::has('labels.customer_refunded_deducted_product_cost') ? __('labels.customer_refunded_deducted_product_cost') : 'Customer Refunded · Deducted Product Cost from Rider' }}
                                    </span>
                                @else
                                    <span class="badge bg-info-lt">
                                        {{ \Illuminate\Support\Facades\Lang::has('labels.customer_refunded_seller_loss') ? __('labels.customer_refunded_seller_loss') : 'Customer Refunded · Seller Absorbed Loss' }}
                                    </span>
                                @endif
                            @else
                                <span class="badge bg-danger-lt">
                                    {{ \Illuminate\Support\Facades\Lang::has('labels.no_refund_customer') ? __('labels.no_refund_customer') : 'No Refund to Customer' }}
                                </span>
                            @endif
                        </div>
                    </div>
                    @if (($respParty === 'rider' || $return->deduct_from_rider || $return->action_on_rejection === 'deduct_from_delivery_boy') && (float) ($return->deduction_amount ?? 0) > 0)
                        <div class="mt-2">
                            <span class="text-secondary small">{{ __('labels.deducted_amount') ?? 'Deducted Amount' }}:</span>
                            <div class="fw-bold text-danger fs-3 mt-1">
                                {{ isset($return->order) && $return->order->currency_code ? $return->order->currency_code : '$' }} {{ number_format($return->deduction_amount, 2) }}
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif
        @endif

        {{-- Decision metadata --}}
        @if (!($hideEvidenceCards ?? false))
        @if ($decidedBy)
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">
                        {{ __('labels.decision') }}
                        <x-form-info-tooltip :title="__('labels.tooltip_decision')" placement="left" />
                    </h3>
                </div>
                <div class="card-body">
                    <div class="fw-medium">
                        {{ $isAdminDecided ? __('labels.by_support') : __('labels.by_seller') }}
                    </div>
                    <div class="text-secondary small">{{ $decidedBy->name }}</div>
                    @if ($decidedAt)
                        <div class="text-secondary small">{{ $decidedAt->format('Y-m-d H:i') }}</div>
                    @endif
                </div>
            </div>
        @endif
        @endif
    </div>
</div>
