<!-- STORE REJECT MODAL -->
@php
    $activeRejectionReasons = \App\Models\ReturnRejectionReason::active()->get();
@endphp
<div
    class="modal modal-blur fade"
    id="storeRejectModel"
    tabindex="-1"
    role="dialog"
    aria-hidden="true"
>
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            <div class="modal-status bg-danger"></div>
            <form id="store-reject-form" enctype="multipart/form-data">
                <div class="modal-body py-4">
                    <div class="text-center mb-3">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="24"
                            height="24"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="icon mb-2 text-danger icon-lg"
                        >
                            <path d="M12 9v4"/>
                            <path d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.87l-8.106 -13.536a1.914 1.914 0 0 0 -3.274 0z"/>
                            <path d="M12 16h.01"/>
                        </svg>
                        <h3>{{ __('labels.reject_store_receipt') ?? 'Reject Return Item at Store' }}</h3>
                        <div class="text-secondary small">
                            {{ __('labels.reject_store_receipt_msg') ?? 'Specify why this returned item is unacceptable and select the responsible party.' }}
                        </div>
                    </div>

                    <!-- 1. Select Predefined Rejection Reason -->
                    <div class="form-group mb-3 text-start">
                        <label for="rejection_reason_id" class="form-label required">{{ __('labels.rejection_reason') ?? 'Select Rejection Reason' }} <span class="text-danger">*</span></label>
                        <select class="form-select" id="rejection_reason_id" name="rejection_reason_id" required onchange="toggleCommentRequired(this)">
                            <option value="">-- {{ __('labels.select_reason') ?? 'Select Reason' }} --</option>
                            @foreach($activeRejectionReasons as $rejReason)
                                <option value="{{ $rejReason->id }}" data-comment-required="{{ $rejReason->comment_required ? '1' : '0' }}">
                                    {{ $rejReason->title }}
                                </option>
                            @endforeach
                            <option value="" data-comment-required="1">{{ __('labels.other') ?? 'Other' }}</option>
                        </select>
                    </div>

                    <!-- 2. Rejection Comment / Description -->
                    <div class="form-group mb-3 text-start">
                        <label for="store_rejection_reason" class="form-label" id="comment_label">{{ __('labels.comment') ?? 'Rejection Comment' }}</label>
                        <textarea class="form-control" id="store_rejection_reason" name="rejection_reason" rows="3"
                                  placeholder="{{ __('labels.placeholder_rejection_reason') ?? 'Provide details about why the item was rejected...' }}"></textarea>
                    </div>

                    <!-- 5. Select Responsible Party (Customer vs Rider) -->
                    <div class="form-group mb-3 text-start">
                        <label class="form-label required">{{ __('labels.responsible_party') ?? 'Select Responsible Party' }} <span class="text-danger">*</span></label>
                        <div class="form-selectgroup form-selectgroup-boxes d-flex flex-column gap-2">
                            <label class="form-selectgroup-item">
                                <input type="radio" name="responsible_party" value="customer" class="form-selectgroup-input" checked onchange="toggleResponsiblePartyOptions('customer')">
                                <span class="form-selectgroup-label d-flex align-items-center p-3">
                                    <span class="me-3">
                                        <span class="form-selectgroup-check"></span>
                                    </span>
                                    <span>
                                        <span class="fw-bold d-block text-start">{{ __('labels.responsible_customer') ?? 'Customer (Wrong/Used/Damaged Item Returned)' }}</span>
                                        <span class="text-secondary small d-block text-start">{{ __('labels.responsible_customer_desc') ?? 'Customer sent back an incorrect or damaged item. Customer will NOT receive any refund.' }}</span>
                                    </span>
                                </span>
                            </label>
                            <label class="form-selectgroup-item">
                                <input type="radio" name="responsible_party" value="rider" class="form-selectgroup-input" onchange="toggleResponsiblePartyOptions('rider')">
                                <span class="form-selectgroup-label d-flex align-items-center p-3">
                                    <span class="me-3">
                                        <span class="form-selectgroup-check"></span>
                                    </span>
                                    <span>
                                        <span class="fw-bold d-block text-start">{{ __('labels.responsible_rider') ?? 'Rider (Item Damaged / Lost in Transit)' }}</span>
                                        <span class="text-secondary small d-block text-start">{{ __('labels.responsible_rider_desc') ?? 'Courier damaged or swapped item after pickup. Customer receives FULL refund.' }}</span>
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- 6. Rider Deduction Options (Visible when Responsible Party = Rider) -->
                    <div id="rider_deduction_section" class="card bg-light p-3 mb-3 d-none text-start">
                        <label class="form-label required">{{ __('labels.rider_deduction_option') ?? 'Select Rider Deduction Type' }} <span class="text-danger">*</span></label>
                        <div class="d-flex flex-column gap-2 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="rider_deduction_type" id="deduct_delivery_earning" value="delivery_earning" checked>
                                <label class="form-check-label fw-bold" for="deduct_delivery_earning">
                                    {{ __('labels.deduct_delivery_earning') ?? 'Deduct Delivery Earnings for this Order' }}
                                    @if(isset($return) && isset($return->order) && (float)$return->order->delivery_charge > 0)
                                        ({{ number_format($return->order->delivery_charge, 2) }})
                                    @endif
                                </label>
                                <small class="form-hint text-muted d-block">{{ __('labels.deduct_delivery_earning_help') ?? 'Deducts only the delivery charge / earning associated with this order from the rider\'s wallet.' }}</small>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="rider_deduction_type" id="deduct_product_cost" value="product_cost">
                                <label class="form-check-label fw-bold" for="deduct_product_cost">
                                    {{ __('labels.deduct_product_cost') ?? 'Deduct Full Product Cost' }}
                                    @if(isset($return) && isset($return->refund_amount) && (float)$return->refund_amount > 0)
                                        ({{ number_format($return->refund_amount, 2) }})
                                    @endif
                                </label>
                                <small class="form-hint text-muted d-block">{{ __('labels.deduct_product_cost_help') ?? 'Deducts the full product refund amount from the rider\'s wallet (for damaged or swapped items).' }}</small>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="rider_deduction_type" id="deduct_none" value="none">
                                <label class="form-check-label fw-bold" for="deduct_none">
                                    {{ __('labels.no_deduction_seller_bears_loss') ?? 'No Deduction (Seller Bears Loss)' }}
                                </label>
                                <small class="form-hint text-muted d-block">{{ __('labels.no_deduction_help') ?? 'Rider wallet remains intact; seller absorbs the loss for customer refund.' }}</small>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('labels.cancel') }}</button>
                    <button type="button" class="btn btn-danger ms-auto" id="confirmStoreRejectBtn">{{ __('labels.reject_item') ?? 'Reject Item' }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function toggleCommentRequired(selectEl) {
        var selectedOpt = selectEl.options[selectEl.selectedIndex];
        var isRequired = selectedOpt ? selectedOpt.getAttribute('data-comment-required') === '1' : false;
        var labelEl = document.getElementById('comment_label');
        var textareaEl = document.getElementById('store_rejection_reason');
        if (isRequired) {
            labelEl.innerHTML = '{{ __("labels.comment") ?? "Rejection Comment" }} <span class="text-danger">*</span>';
            textareaEl.required = true;
        } else {
            labelEl.innerHTML = '{{ __("labels.comment") ?? "Rejection Comment" }}';
            textareaEl.required = false;
        }
    }

    function toggleResponsiblePartyOptions(party) {
        var section = document.getElementById('rider_deduction_section');
        if (party === 'rider') {
            section.classList.remove('d-none');
        } else {
            section.classList.add('d-none');
        }
    }
</script>
<!-- END STORE REJECT MODAL -->
