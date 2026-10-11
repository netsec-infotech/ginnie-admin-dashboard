@extends('layouts.no-head-foot')

@section('title', $paymentContext['title'] ?? 'Rider Cash Settle')
@section('content')
    <div class="container container-tight py-4">
        <div class="text-center mb-4">
            <a href="." class="navbar-brand navbar-brand-autodark">
                <img src="{{ asset('logos/hyper-local-logo.png') }}" alt="Logo" width="150px">
            </a>
        </div>

        <div class="card card-md">
            <div class="card-body">
                <h2>{{ $paymentContext['title'] ?? 'Rider Cash Settle' }}</h2>

                @foreach(($paymentContext['lineItems'] ?? []) as $item)
                    <div class="d-flex align-items-center justify-content-between mb-2 {{ $item['bold'] ?? false ? 'fw-bold' : '' }}">
                        <span>{{ $item['label'] }}:</span>
                        <span>{{ $item['value'] }}</span>
                    </div>
                @endforeach

                <div class="d-flex align-items-center justify-content-between">
                    <span>Settlement Reference:</span>
                    <span>#{{ $transactionId }}</span>
                </div>
                <hr class="my-3"/>
                <div class="d-flex align-items-center justify-content-between h3">
                    <span>{{ __('labels.amount') }}:</span>
                    <span>{{ number_format((float)($amount ?? 0), 2) }} {{ $systemSettings['currency'] ?? ($currency ?? '') }}</span>
                </div>

                <div id="error-message" class="text-center text-danger"></div>
                <button class="btn w-100 mt-2 btn-primary" id="payBtn">{{ $payButtonLabel ?? 'Pay Now' }}</button>
            </div>
        </div>
    </div>
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
                        name: "Rider Cash Settle",
                        description: "Cash Settlement",
                        order_id: "{{ $orderId }}",
                        notes: {
                            user_id: "{{ $settlement->deliveryBoy->user_id }}",
                            type: 'delivery_boy_settlement',
                            settlement_id: "{{ $transactionId }}"
                        },
                        handler: function () {
                            window.location.href = "{{ $statusRoute }}";
                        },
                        theme: {color: "#0ea5e9"}
                    };
                    let rzp1 = new Razorpay(options);
                    rzp1.open();
                });
            })();
        </script>
    @endpush
@endsection
