<!DOCTYPE html>
{{-- Public COD doorstep online payment page reached via QR scan or link from delivery boy's phone. No auth required — order UUID token in URL acts as verification. --}}
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pay Order #{{ $order->slug }}</title>
    <link rel="icon" href="{{ asset('logos/hyper-local-favicon.png') }}">
    <link href="{{ hyperAsset('assets/css/pos-payment-page.css') }}" rel="stylesheet">
</head>
<body>
<div class="wrap">

    @php
        $isPaid = (string) $order->payment_status === (string) \App\Enums\PaymentStatusEnum::COMPLETED() || $order->is_cod_paid_online;
        $orderCurrencyCode = strtoupper((string) ($order->currency_code ?: $order->base_currency_code ?: ''));
        $money = fn ($amount) => \App\Support\Currency\CurrencySnapshotPayload::formatAmount($amount, $orderCurrencyCode)
            ?? trim(($orderCurrencyCode ?: '') . ' ' . number_format((float) $amount, 2));
    @endphp

    @if($isPaid)
        <div class="card center">
            <div class="icon-tick">✓</div>
            <h2>Payment Received</h2>
            <p>Thank you! Your online payment of {{ $money($order->total_payable) }} for Order #{{ $order->slug }} has been confirmed.</p>
        </div>
    @else
        {{-- ── Pending: show order summary + gateway picker ── --}}
        <div class="card">
            <div class="brand">
                <div class="brand-logo">COD</div>
                <div>
                    <div class="brand-name">Order #{{ $order->slug }}</div>
                    <div class="brand-sub">Doorstep Online Payment</div>
                </div>
            </div>

            <div class="label">Total Amount Due</div>
            <div class="amount">{{ $money($order->total_payable) }}</div>

            @if($order->items && $order->items->isNotEmpty())
                <div class="order-totals-divider">
                    @foreach($order->items as $item)
                        <div class="row">
                            <div class="name">
                                <div>{{ $item->product_name }} <span class="qty">× {{ $item->quantity }}</span></div>
                                @if($item->variant_name)
                                    <div class="meta">{{ $item->variant_name }}</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="order-totals-divider-sm">
                <div class="row"><div class="name text-muted-custom">Subtotal</div><div>{{ $money($order->subtotal) }}</div></div>
                @if((float) $order->delivery_charge > 0)
                    <div class="row"><div class="name text-muted-custom">Delivery Charge</div><div>{{ $money($order->delivery_charge) }}</div></div>
                @endif
                @if((float) $order->handling_charges > 0)
                    <div class="row"><div class="name text-muted-custom">Handling Charge</div><div>{{ $money($order->handling_charges) }}</div></div>
                @endif
                @if((float) $order->promo_discount > 0)
                    <div class="row"><div class="name text-muted-custom">Promo Discount</div><div class="text-danger-custom">−{{ $money($order->promo_discount) }}</div></div>
                @endif
                <div class="row" style="font-weight: 600; margin-top: 6px;"><div class="name">Total Payable</div><div>{{ $money($order->total_payable) }}</div></div>
            </div>
        </div>

        <div class="card">
            <div id="payment-banner" class="status-banner is-info" style="display: none;"></div>

            @if(empty($gateways))
                <div class="status-banner is-error" style="display: block;">
                    No online payment gateways are currently available. Please pay cash to the delivery executive.
                </div>
            @else
                <div class="label payment-gateway-label">Select Online Payment Method</div>
                <div class="gateways">
                    @foreach($gateways as $gw)
                        <button type="button" class="gateway-btn"
                                data-gateway="{{ $gw['name'] }}"
                                data-mode="{{ $gw['mode'] }}">
                            <span class="gw-mark">{{ strtoupper(substr($gw['label'], 0, 2)) }}</span>
                            <span>
                                <div>{{ $gw['label'] }}</div>
                                <div class="brand-sub">
                                    @if($gw['name'] === 'razorpay') UPI · Cards · Netbanking
                                    @elseif($gw['name'] === 'stripe') Cards · Wallets
                                    @elseif($gw['name'] === 'paystack') Cards · Bank · USSD
                                    @elseif($gw['name'] === 'flutterwave') Cards · Bank · Mobile Money
                                    @endif
                                </div>
                            </span>
                            <span class="gw-arrow">→</span>
                        </button>
                    @endforeach
                </div>
            @endif

            <p class="footer-hint">
                After payment, this screen will confirm automatically.
                <br>You may show the confirmation to your delivery executive.
            </p>
        </div>
    @endif

</div>

@if(!$isPaid && !empty($gateways))
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
    (function () {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const banner    = document.getElementById('payment-banner');
        const buttons   = document.querySelectorAll('.gateway-btn');
        const verifyUrl = @json($verifyUrl);
        const statusUrl = @json($statusUrl);
        const initUrlT  = @json($initUrlT);
        const merchant  = @json('Order #' . $order->slug);
        const symbol    = @json(\App\Support\Currency\CurrencyDisplay::label($orderCurrencyCode));
        const amount    = @json((float) $order->total_payable);

        function setBanner(kind, html) {
            banner.className = 'status-banner is-' + kind;
            banner.style.display = 'block';
            banner.innerHTML = html;
        }
        function clearBanner() { banner.style.display = 'none'; banner.innerHTML = ''; }

        function disableAll(except) {
            buttons.forEach(b => { if (b !== except) b.disabled = true; });
        }
        function enableAll() {
            buttons.forEach(b => { b.disabled = false; });
        }

        async function initGateway(name) {
            const url = initUrlT.replace('__GW__', encodeURIComponent(name));
            const res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: '{}',
            });
            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                throw new Error(err?.message || 'Could not initialise payment.');
            }
            return (await res.json()).data;
        }

        function openRazorpay(initData) {
            const opts = {
                key: initData.key,
                amount: initData.amount,
                currency: initData.currency,
                name: merchant,
                description: 'COD Doorstep Online Payment',
                order_id: initData.order_id,
                handler: function (response) { verifyAndComplete(response); },
                modal: { ondismiss: function () { enableAll(); clearBanner(); } },
                theme: { color: '#2563eb' },
            };
            const rzp = new Razorpay(opts);
            rzp.on('payment.failed', function (resp) {
                setBanner('error', 'Payment failed: ' + (resp.error?.description || 'Unknown error'));
                enableAll();
            });
            rzp.open();
        }

        async function verifyAndComplete(rzpResponse) {
            setBanner('info', 'Confirming your payment…');
            try {
                const res = await fetch(verifyUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        razorpay_payment_id: rzpResponse.razorpay_payment_id,
                        razorpay_order_id:   rzpResponse.razorpay_order_id,
                        razorpay_signature:  rzpResponse.razorpay_signature,
                    }),
                });
                const data = await res.json();
                if (data?.success) {
                    showSuccessAndStop();
                } else {
                    setBanner('error', data?.message || 'Could not confirm payment. Please show this screen to the delivery executive.');
                    enableAll();
                }
            } catch (e) {
                setBanner('error', 'Network error. Please check your internet connection.');
                enableAll();
            }
        }

        function showSuccessAndStop() {
            document.body.innerHTML = `
                <div class="wrap">
                    <div class="card center">
                        <div class="icon-tick">✓</div>
                        <h2>Payment Received</h2>
                        <p>Thank you! Your online payment for Order #${@json($order->slug)} has been confirmed.</p>
                    </div>
                </div>`;
        }

        buttons.forEach(b => b.addEventListener('click', async function () {
            const name = b.dataset.gateway;
            const mode = b.dataset.mode;
            disableAll(b);
            setBanner('info', `Connecting to ${b.querySelector('div')?.textContent || name}…`);

            try {
                const initData = await initGateway(name);
                if (mode === 'inline' && name === 'razorpay') {
                    clearBanner();
                    openRazorpay(initData);
                } else if (mode === 'redirect' && initData?.url) {
                    window.location.href = initData.url;
                } else {
                    setBanner('error', 'Could not start payment for this method.');
                    enableAll();
                }
            } catch (err) {
                setBanner('error', err.message || 'Could not start payment.');
                enableAll();
            }
        }));

        setInterval(async () => {
            try {
                const r = await fetch(statusUrl, { credentials: 'same-origin' });
                const j = await r.json();
                const st = j.data?.status;
                const isPaidOnline = j.data?.is_cod_paid_online;
                if (st === 'completed' || isPaidOnline) {
                    showSuccessAndStop();
                }
            } catch (_) {}
        }, 6000);
    })();
    </script>
@endif

</body>
</html>
