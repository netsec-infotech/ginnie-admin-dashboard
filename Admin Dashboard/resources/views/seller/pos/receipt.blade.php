{{-- POS receipt — designed for 80mm thermal printer width.

When the seller clicks "Print receipt" in the POS UI, the browser opens this
view in a new tab and triggers print. The CSS forces a narrow page width so
that on a typical thermal printer driver the receipt comes out cleanly with
no cropping. Auto-print fires once the document is loaded. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt #{{ $order->id }}</title>
    <link href="{{ hyperAsset('assets/seller/css/pos-receipt.css') }}" rel="stylesheet">
</head>
<body onload="window.print();">
<div class="receipt">

    <div class="center bold uppercase receipt-store-title">{{ $store->name }}</div>
    @if(!empty($store->address))
        <div class="center small">{{ $store->address }}</div>
    @endif
    <div class="center small">
        @if(!empty($store->city)){{ $store->city }}@endif{{-- --}}
        @if(!empty($store->state)), {{ $store->state }}@endif{{-- --}}
        @if(!empty($store->zipcode)) — {{ $store->zipcode }}@endif
    </div>
    @if(!empty($store->contact_number))
        <div class="center small">Tel: {{ $store->contact_number }}</div>
    @endif
    @if(!empty($store->tax_name) && !empty($store->tax_number))
        <div class="center small">{{ $store->tax_name }}: {{ $store->tax_number }}</div>
    @endif

    <hr class="divider">

    <div class="row small">
        <span>Order #</span><span>{{ $order->id }}</span>
    </div>
    <div class="row small">
        <span>Date</span><span>{{ $order->created_at->format('Y-m-d H:i') }}</span>
    </div>
    <div class="row small">
        <span>Customer</span>
        <span>{{ $customerName }}</span>
    </div>
    @if(!empty($customerMobile))
        <div class="row small">
            <span>Mobile</span><span>{{ $customerMobile }}</span>
        </div>
    @endif
    @php
        $paymentLabel = match ($order->payment_method) {
            'pos_upi'             => 'UPI',
            'cod'                 => 'Cash',
            'razorpayPayment'     => 'Razorpay',
            'stripePayment'       => 'Stripe',
            'paystackPayment'     => 'Paystack',
            'flutterwavePayment'  => 'Flutterwave',
            default               => strtoupper((string) $order->payment_method),
        };
        $splitCash = (float) ($order->pos_split_cash ?? 0);
        // Split-tender sale: cashier collected $splitCash in cash and rest online.
        $isSplit = $splitCash > 0 && $splitCash < (float) $order->final_total;
    @endphp
    <div class="row small">
        <span>Payment</span><span class="uppercase">{{ $isSplit ? 'Split' : $paymentLabel }} ({{ $order->payment_status }})</span>
    </div>
    @if($isSplit)
        <div class="row small">
            <span>&nbsp;&nbsp;Cash</span><span>{{ number_format($splitCash, 2) }}</span>
        </div>
        <div class="row small">
            <span>&nbsp;&nbsp;{{ $paymentLabel }}</span><span>{{ number_format((float) $order->final_total - $splitCash, 2) }}</span>
        </div>
    @endif

    <hr class="divider">

    <table class="items">
        <thead>
        <tr class="small">
            <th class="item">Item</th>
            <th class="qty">Qty</th>
            <th class="amount">Amount</th>
        </tr>
        </thead>
        <tbody>
        @foreach($order->items as $item)
            @php
                $qty = (float) $item->quantity;
                $addonUnitTotal = (float) $item->addons->sum(fn ($addon) => (float) $addon->price);
                $itemUnitTotal = $qty > 0 ? round((float) $item->subtotal / $qty, 2) : (float) $item->subtotal;
                $productUnitPrice = max(0, round($itemUnitTotal - $addonUnitTotal, 2));
                $pricingLine = $addonUnitTotal > 0
                    ? 'Product '.number_format($productUnitPrice, 2).' + Add-ons '.number_format($addonUnitTotal, 2)
                    : 'Product '.number_format($productUnitPrice, 2);
            @endphp
            <tr>
                <td class="item">
                    {{ $item->title }}
                    @if(!empty($item->variant_title) && strcasecmp($item->variant_title, 'Default') !== 0)
                        <div class="small item-meta">{{ $item->variant_title }}</div>
                    @endif
                    <div class="small item-pricing">{{ $pricingLine }}</div>
                    @if($item->relationLoaded('addons') && $item->addons->isNotEmpty())
                        @foreach($item->addons as $addon)
                            <div class="small item-meta">
                                @if(!empty($addon->addonGroup?->title))
                                    {{ $addon->addonGroup->title }}:
                                @endif
                                {{ $addon->addonItem?->title ?? 'Addon' }}
                                @if((float) $addon->price > 0)
                                    ({{ number_format((float) $addon->price, 2) }})
                                @else
                                    (free)
                                @endif
                            </div>
                        @endforeach
                    @endif
                </td>
                <td class="qty">{{ rtrim(rtrim(number_format((float)$item->quantity, 2, '.', ''), '0'), '.') }}</td>
                <td class="amount">{{ number_format((float)$item->subtotal, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <hr class="divider">

    @php
        // Tax breakdown by rate — items at the same % collapse into one row
        // so the customer sees "Tax @ 5% — ₹X" + "Tax @ 18% — ₹Y" instead
        // of an opaque "incl. tax" total. Skips 0% items.
        $byRate = [];
        foreach ($order->items as $i) {
            $rate = (float) $i->tax_percent;
            if ($rate <= 0) continue;
            $key = number_format($rate, ($rate == (int) $rate) ? 0 : 2, '.', '');
            $byRate[$key] = ($byRate[$key] ?? 0) + (float) $i->tax_amount;
        }
        ksort($byRate, SORT_NUMERIC);
        $taxTotal     = array_sum($byRate);
        $savingsTotal = (float) ($order->pos_savings ?? 0);

        // Subtotal exclusive-of-tax (what the items would have cost without
        // any tax) — clearer than showing the inclusive subtotal + a
        // separate tax row that confuses people.
        $subtotalExTax = max(0.0, (float) $order->subtotal - $taxTotal);
    @endphp
    <div class="totals">
        <div class="row"><span>Subtotal</span><span>{{ number_format($subtotalExTax, 2) }}</span></div>
        @foreach($byRate as $rate => $amount)
            <div class="row small"><span>&nbsp;&nbsp;Tax @ {{ $rate }}%</span><span>{{ number_format($amount, 2) }}</span></div>
        @endforeach
        @if($taxTotal > 0)
            <div class="row small receipt-tax-summary"><span>Total tax</span><span>{{ number_format($taxTotal, 2) }}</span></div>
        @endif
        @if($savingsTotal > 0)
            <div class="row small"><span>You saved (sale price)</span><span>-{{ number_format($savingsTotal, 2) }}</span></div>
            <div class="row small"><span>Savings note</span><span>Already included in total</span></div>
        @endif
        @if((float)$order->promo_discount > 0)
            <div class="row"><span>Discount</span><span>-{{ number_format((float)$order->promo_discount, 2) }}</span></div>
        @endif
        <div class="row grand"><span>TOTAL</span><span>{{ ($currencySymbol ?? '') . number_format((float)$order->final_total, 2) }}</span></div>
    </div>

    <hr class="divider">

    <div class="footer-note">
        {{ $footerNote }}
    </div>

</div>
</body>
</html>
