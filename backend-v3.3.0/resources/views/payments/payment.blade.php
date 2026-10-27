@extends('layouts.no-head-foot')

@section('title', $paymentContext['title'] ?? __('labels.payment_summary'))
@section('content')
    <div class="container container-tight py-4">
        <div class="text-center mb-4">
            <a href="." class="navbar-brand navbar-brand-autodark">
                @if(($systemSettings['demoMode'] ?? false))
                    <img
                        src="{{asset('logos/hyper-local-logo.png')}}"
                        alt="{{$systemSettings['appName'] ?? ""}}" width="150px">
                @else
                    <img
                        src="{{!empty($systemSettings['logo'])?$systemSettings['logo'] : asset('logos/hyper-local-logo.png')}}"
                        alt="{{$systemSettings['appName'] ?? ""}}" width="150px">
                @endif
            </a>
        </div>

        <div class="card card-md">
            <div class="card-body">
                <h2>{{ $paymentContext['title'] ?? __('labels.payment_summary') }}</h2>

                @foreach(($paymentContext['lineItems'] ?? []) as $item)
                    <div class="d-flex align-items-center justify-content-between mb-2 {{ $item['bold'] ?? false ? 'fw-bold' : '' }}">
                        <span>{{ $item['label'] }}:</span>
                        <span>{{ $item['value'] }}</span>
                    </div>
                @endforeach

                @isset($transactionId)
                    <div class="d-flex align-items-center justify-content-between">
                        <span>{{ __('labels.system_transaction_id') }}:</span>
                        <span>#{{ $transactionId }}</span>
                    </div>
                @endisset
                <hr class="my-3"/>
                <div class="d-flex align-items-center justify-content-between h3">
                    <span>{{ __('labels.amount') }}:</span>
                    <span>{{ number_format((float)($amount ?? 0), 2) }} {{ $systemSettings['currency'] ?? ($currency ?? '') }}</span>
                </div>

                <div id="payment-element" class="mt-2"></div>
                <div id="error-message" class="text-center text-danger"></div>
                <button class="btn w-100 mt-2 btn-primary" id="payBtn">{{ $payButtonLabel ?? __('labels.pay_now') }}</button>

                @isset($gateway)
                    @includeIf('payments.gateways.' . $gateway)
                @endisset
            </div>
        </div>
    </div>
    @push('scripts')
        <script>
            (function () {
                var shouldWarnOnUnload = true;

                function beforeUnloadHandler(e) {
                    if (!shouldWarnOnUnload) return;
                    e.preventDefault();
                    e.returnValue = '';
                    return '';
                }

                window.addEventListener('beforeunload', beforeUnloadHandler, {capture: true});

                window.disableLeaveWarning = function () {
                    shouldWarnOnUnload = false;
                    window.removeEventListener('beforeunload', beforeUnloadHandler, {capture: true});
                };
                window.enableLeaveWarning = function () {
                    if (!shouldWarnOnUnload) {
                        shouldWarnOnUnload = true;
                        window.addEventListener('beforeunload', beforeUnloadHandler, {capture: true});
                    }
                };
            })();
        </script>
        @isset($gateway)
            @if(($gateway === 'paystack' || $gateway === 'xendit' || $gateway === 'mercado-pago') && !empty($authorizationUrl ?? null))
                <script>
                    (function(){
                        const payBtn = document.getElementById('payBtn');
                        const authUrl = @json($authorizationUrl ?? '');
                        payBtn?.addEventListener('click', function(){
                            try {
                                window.disableLeaveWarning && window.disableLeaveWarning();
                                if (authUrl) {
                                    window.location.href = authUrl;
                                } else {
                                    alert('Authorization URL missing.');
                                    window.enableLeaveWarning && window.enableLeaveWarning();
                                }
                            } catch (e) {
                                window.enableLeaveWarning && window.enableLeaveWarning();
                            }
                        });
                    })();
                </script>
            @elseif($gateway === 'flutterwave' && !empty($authorizationUrl ?? null))
                <script>
                    (function(){
                        const payBtn = document.getElementById('payBtn');
                        const authUrl = @json($authorizationUrl ?? '');
                        payBtn?.addEventListener('click', function(){
                            try {
                                window.disableLeaveWarning && window.disableLeaveWarning();
                                payBtn.disabled = true;
                                if (authUrl) {
                                    window.location.href = authUrl;
                                } else {
                                    alert('Authorization URL missing.');
                                    payBtn.disabled = false;
                                    window.enableLeaveWarning && window.enableLeaveWarning();
                                }
                            } catch (e) {
                                payBtn.disabled = false;
                                window.enableLeaveWarning && window.enableLeaveWarning();
                            }
                        });
                    })();
                </script>
            @endif
        @endisset
    @endpush
@endsection
