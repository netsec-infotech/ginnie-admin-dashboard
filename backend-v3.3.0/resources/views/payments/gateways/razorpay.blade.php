
<form id="rzpPaymentForm" method="POST" action="#" style="display:none;">
    @csrf
    <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id">
    <input type="hidden" name="razorpay_order_id" id="razorpay_order_id" value="{{ $orderId }}">
    <input type="hidden" name="razorpay_signature" id="razorpay_signature">
    <input type="hidden" name="subscription_transaction_id" value="{{ $transactionId }}">
    <input type="hidden" name="seller_subscription_id" value="{{ $subscriptionId }}">
    <input type="hidden" name="seller_id" value="{{ $sellerId }}">
    <input type="hidden" name="gateway" value="razorpay">
</form>

@php
    // The Razorpay partial is reused by subscription and wallet payments; notes must match the current payment flow.
    $razorpayPaymentType = ($subscriptionId ?? 0) ? 'subscription_payment' : 'wallet_recharge';
@endphp

@push('scripts')
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        (function () {
            let btn = document.getElementById('payBtn');
            if (!btn) return;
            btn.addEventListener('click', function () {
                var options = {
                    key: "{{ $keyId }}",
                    amount: {{ (int)($amountPaise ?? 0) }},
                    currency: "{{ $currency ?? 'INR' }}",
                    name: "Seller Subscription",
                    description: "{{ $planName ?? 'Subscription' }}",
                    order_id: "{{ $orderId }}",
                    notes: {
                        user_id: "{{ $sellerId }}",
                        type: "{{ $razorpayPaymentType }}",
                        transaction_id: "{{ $transactionId }}",
                        subscription_transaction_id: "{{ $transactionId }}",
                        seller_subscription_id: "{{ $subscriptionId }}",
                        seller_id: "{{ $sellerId }}"
                    },
                    handler: function (response) {
                        try { if (window.disableLeaveWarning) window.disableLeaveWarning(); } catch (e) {}
                        const statusUrl = new URL("{{ $statusRoute ?? route('subscription-payment.status', ['transaction' => $transactionUuid]) }}", window.location.origin);
                        statusUrl.searchParams.set('razorpay_payment_id', response.razorpay_payment_id || '');
                        statusUrl.searchParams.set('razorpay_order_id', response.razorpay_order_id || "{{ $orderId }}");
                        statusUrl.searchParams.set('razorpay_signature', response.razorpay_signature || '');
                        window.location.href = statusUrl.toString();
                    },
                    theme: {color: "#0ea5e9"}
                };
                let rzp1 = new Razorpay(options);
                rzp1.open();
            });
        })();
    </script>
@endpush
