<!DOCTYPE html>
{{-- Public POS payment page reached via the QR scan from a customer's
     phone. No auth — the {token} in the URL is the secret. Shows a
     gateway picker scoped to whichever gateways the platform admin has
     configured. --}}
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pay {{ $store->name ?? '' }}</title>
    <link rel="icon" href="{{ $systemSettings['favicon'] ?? asset('logos/hyper-local-favicon.png') }}">
    <link href="{{ hyperAsset('assets/css/pos-payment-page.css') }}" rel="stylesheet">
</head>
<body>
<div class="wrap">

    @php
        $isPaid    = $session->status === \App\Enums\Pos\PosPaymentSessionStatusEnum::PAID;
        $isExpired = $session->status === \App\Enums\Pos\PosPaymentSessionStatusEnum::EXPIRED;
        $isCancel  = $session->status === \App\Enums\Pos\PosPaymentSessionStatusEnum::CANCELLED;
        $isFailed  = $session->status === \App\Enums\Pos\PosPaymentSessionStatusEnum::FAILED;
        $isPending = $session->status === \App\Enums\Pos\PosPaymentSessionStatusEnum::PENDING;

        $sessionCurrencyCode = strtoupper((string) ($session->currency_code ?: ''));
        $money = function ($amount) use ($sessionCurrencyCode) {
            return \App\Support\Currency\CurrencySnapshotPayload::formatAmount($amount, $sessionCurrencyCode)
                ?? trim($sessionCurrencyCode.' '.number_format((float) $amount, 2, '.', ','));
        };
    @endphp

    @if($isPaid)
        <div class="card center">
            <div class="icon-tick">✓</div>
            <h2>Payment received</h2>
            <p>Order has been confirmed at the counter.</p>
        </div>
    @elseif($isExpired || $isCancel || $isFailed)
        <div class="card center">
            <h2>Payment {{ $session->status }}</h2>
            <p>
                @if($isExpired) This payment link has expired. Please ask the cashier for a new QR.
                @elseif($isCancel) The cashier cancelled this payment. Please ask for a new QR.
                @else
                    {{ $session->failure_reason ?: 'This payment could not be completed.' }}
                    Please ask the cashier to retry or use cash.
                @endif
            </p>
        </div>
    @else
        {{-- ── Pending: show order summary + gateway picker ── --}}
        <div class="card">
            <div class="brand">
                <div class="brand-logo">{{ strtoupper(substr($store->name ?? 'S', 0, 2)) }}</div>
                <div>
                    <div class="brand-name">{{ $store->name }}</div>
                    <div class="brand-sub">Secure payment</div>
                </div>
            </div>

            <div class="label">You are paying</div>
            <div class="amount">{{ $money($session->amount) }}</div>

            @if(!empty($lineItems))
                <div class="order-totals-divider">
                    @foreach($lineItems as $li)
                        <div class="row">
                            <div class="name">
                                <div>{{ $li['title'] }} <span class="qty">× {{ $li['qty'] }}</span></div>
                                @if(!empty($li['variant']) || !empty($li['addons']))
                                    <div class="meta">
                                        @if(!empty($li['variant'])){{ $li['variant'] }}@endif{{-- --}}
                                        @if(!empty($li['variant']) && !empty($li['addons'])) · @endif{{-- --}}
                                        @if(!empty($li['addons'])){{ implode(', ', $li['addons']) }}@endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if(($breakdown['tax'] ?? 0) > 0 || ($breakdown['savings'] ?? 0) > 0 || ($breakdown['discount'] ?? 0) > 0)
                <div class="order-totals-divider-sm">
                    {{-- Subtotal shown EX-TAX so "Tax @ 18% = ₹X" reads as a true 18% of
                         the subtotal line. Matches the receipt + cart sidebar + CFD. --}}
                    <div class="row"><div class="name text-muted-custom">Subtotal</div><div>{{ $money($breakdown['subtotalExTax'] ?? max(0, ($breakdown['subtotal'] ?? 0) - ($breakdown['tax'] ?? 0))) }}</div></div>
                    @if(!empty($breakdown['taxByRate']))
                        @foreach($breakdown['taxByRate'] as $rate => $amount)
                            <div class="row"><div class="name text-muted-custom font-size-sm">Tax @ {{ $rate }}%</div><div class="text-muted-custom font-size-sm">{{ $money($amount) }}</div></div>
                        @endforeach
                    @elseif(($breakdown['tax'] ?? 0) > 0)
                        <div class="row"><div class="name text-muted-custom font-size-sm">Tax</div><div class="text-muted-custom font-size-sm">{{ $money($breakdown['tax']) }}</div></div>
                    @endif
                    @if(($breakdown['savings'] ?? 0) > 0)
                        <div class="row"><div class="name text-success-custom font-size-sm">You saved</div><div class="text-success-custom font-size-sm">−{{ $money($breakdown['savings']) }}</div></div>
                    @endif
                    @if(($breakdown['discount'] ?? 0) > 0)
                        <div class="row"><div class="name text-muted-custom">Discount</div><div class="text-danger-custom">−{{ $money($breakdown['discount']) }}</div></div>
                    @endif
                </div>
            @endif
        </div>

        <div class="card">
            <div id="payment-banner" class="status-banner is-info"></div>

            @if(empty($gateways))
                <div class="status-banner is-error">
                    No online payment methods are configured. Please ask the cashier to use cash.
                </div>
            @else
                <div class="label payment-gateway-label">Choose a payment method</div>
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
                                    @elseif($gw['name'] === 'flutterwave') Cards · Bank · Mobile money
                                    @endif
                                </div>
                            </span>
                            <span class="gw-arrow">→</span>
                        </button>
                    @endforeach
                </div>
            @endif

            <p class="footer-hint">
                After payment, this page will confirm automatically.
                <br>You may close it once you see the success message.
            </p>
        </div>
    @endif

</div>

@if($isPending && !empty($gateways))
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
    (function () {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const banner    = document.getElementById('payment-banner');
        const buttons   = document.querySelectorAll('.gateway-btn');
        const verifyUrl = @json($verifyUrl);
        const statusUrl = @json($statusUrl);
        const initUrlT  = @json($initUrlT);
        const merchant  = @json($store->name ?? 'POS');
        const amount    = @json((float) $session->amount);

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

        // Razorpay (inline) — same pattern as before, just dispatched from
        // the gateway picker now.
        function openRazorpay(initData) {
            const opts = {
                key: initData.key,
                amount: initData.amount,
                currency: initData.currency,
                name: merchant,
                description: 'In-store purchase',
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
                    setBanner('error', data?.message || 'Could not confirm payment. Please show the cashier this screen.');
                    enableAll();
                }
            } catch (e) {
                setBanner('error', 'Network error. Please show the cashier this screen.');
                enableAll();
            }
        }

        function showSuccessAndStop() {
            document.body.innerHTML = `
                <div class="wrap">
                    <div class="card center">
                        <div class="icon-tick">✓</div>
                        <h2>Payment received</h2>
                        <p>Thank you. Your order has been confirmed at the counter.</p>
                    </div>
                </div>`;
        }

        // Click handler — dispatches per gateway.
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
                    // Stripe Checkout / Paystack auth_url / Flutterwave hosted page
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

        // Background poll: catches webhook backstops in case the customer
        // closed the page after paying.
        setInterval(async () => {
            try {
                const r = await fetch(statusUrl, { credentials: 'same-origin' });
                const j = await r.json();
                const st = j.data?.status;
                if (st && st !== 'pending') {
                    if (st === 'paid') showSuccessAndStop();
                    else location.reload();
                }
            } catch (_) {}
        }, 6000);
    })();
    </script>
@endif

</body>
</html>
