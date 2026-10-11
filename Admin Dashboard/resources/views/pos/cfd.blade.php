<!DOCTYPE html>
{{-- Customer-Facing Display.

     The cashier opens this in a fresh tab and drags it to the second screen
     facing the customer. It polls the cashier's live cart and renders it
     in customer-friendly type. No auth: the {token} is the secret. --}}
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Customer Display</title>
    <link href="{{ hyperAsset('assets/css/pos-cfd.css') }}" rel="stylesheet">
</head>
<body>
<div class="wrap">
    <header>
        <div class="brand">
            <div class="brand-mark" id="cfd-brand-mark">CS</div>
            <div>
                <div class="brand-name" id="cfd-store-name">Welcome</div>
                <div class="brand-sub" id="cfd-store-sub">Please wait at the counter</div>
            </div>
        </div>
        <div>
            <span class="conn-dot" id="cfd-conn-dot"></span><span class="conn-label" id="cfd-conn-label">connecting...</span>
        </div>
    </header>

    <main id="cfd-main">
        <div class="empty" id="cfd-empty">
            <svg class="icon-large" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/><path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/>
                <path d="M17 17h-11v-14h-2"/><path d="M6 5l14 1l-1 7h-13"/>
            </svg>
            <h2>Ready when you are</h2>
            <p>Your bill will appear here as the cashier rings it up.</p>
        </div>

        <div class="sale-grid" id="cfd-sale" style="display:none">
            <section class="items-panel">
                <div class="panel-head">
                    <div>
                        <div class="panel-title">Items</div>
                        <div class="panel-sub" id="cfd-item-count">0 items</div>
                    </div>
                </div>
                <div id="cfd-rows"></div>
            </section>

            <aside class="totals-panel" id="cfd-footer">
                <div class="panel-title">Bill summary</div>
                <div class="breakdown" id="cfd-breakdown"></div>
                <div class="savings-note" id="cfd-savings-note"></div>
                <div class="total-line">
                    <span class="label">Total</span>
                    <span class="amt" id="cfd-total">-</span>
                </div>
            </aside>
        </div>
    </main>
</div>

<script>
(function () {
    const pullUrl = @json($pullUrl);
    const $empty = document.getElementById('cfd-empty');
    const $sale = document.getElementById('cfd-sale');
    const $rows = document.getElementById('cfd-rows');
    const $footer = document.getElementById('cfd-footer');
    const $breakdown = document.getElementById('cfd-breakdown');
    const $total = document.getElementById('cfd-total');
    const $savingsNote = document.getElementById('cfd-savings-note');
    const $itemCount = document.getElementById('cfd-item-count');
    const $brandMark = document.getElementById('cfd-brand-mark');
    const $storeName = document.getElementById('cfd-store-name');
    const $storeSub = document.getElementById('cfd-store-sub');
    const $connDot = document.getElementById('cfd-conn-dot');
    const $connLabel = document.getElementById('cfd-conn-label');

    function escapeHtml(s) {
        return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function money(n, sym) {
        const s = (sym || '').toString();
        return s + Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function fmtInitials(t) {
        const w = String(t || '').trim().split(/\s+/).slice(0, 2);
        return (w.map(x => x.charAt(0).toUpperCase()).join('') || 'CS').slice(0, 2);
    }

    function renderBrandMark(storeName, logoUrl) {
        if (logoUrl) {
            $brandMark.innerHTML = `<img src="${escapeHtml(logoUrl)}" alt="${escapeHtml(storeName || 'Store logo')}">`;
            return;
        }

        $brandMark.textContent = fmtInitials(storeName);
    }

    function syncStoreHeader(state = {}) {
        if (state.store_name) {
            $storeName.textContent = state.store_name;
        }
        $storeSub.textContent = 'Ready for the next order';
        renderBrandMark(state.store_name || $storeName.textContent, state.store_logo);
    }

    function showEmpty(state = {}) {
        syncStoreHeader(state);
        $empty.style.display = '';
        $sale.style.display = 'none';
        $footer.style.display = 'none';
        if ($savingsNote) {
            $savingsNote.textContent = '';
        }
    }

    function renderItem(it, sym) {
        const addonList = Array.isArray(it.addons) ? it.addons : [];
        const addonNames = addonList.map(addon => typeof addon === 'string' ? addon : addon.title).filter(Boolean);
        const productUnitPrice = Number(it.product_unit_price || 0);
        const addonUnitTotal = Number(it.addon_unit_total || 0);
        const taxRate = Number(it.tax_percent || 0);
        const taxAmount = Number(it.tax_amount || 0);
        const meta = [it.variant].filter(Boolean).join(' - ');
        const unitPriceText = addonUnitTotal > 0
            ? `Product ${money(productUnitPrice, sym)} + Add-ons ${money(addonUnitTotal, sym)}`
            : `Product ${money(productUnitPrice, sym)}`;

        return `
            <div class="row">
                <div class="row-main">
                    <div class="name">${escapeHtml(it.title)}</div>
                    ${meta ? `<div class="meta">${escapeHtml(meta)}</div>` : ''}
                    <div class="price-note">${unitPriceText}</div>
                    ${taxRate > 0 && taxAmount > 0 ? `<div class="tax-note">Includes tax @ ${escapeHtml(taxRate)}%: ${money(taxAmount, sym)}</div>` : ''}
                    ${addonNames.length ? `<div class="addons">${addonList.map(addon => {
                        if (typeof addon === 'string') {
                            return `<span class="addon-chip">${escapeHtml(addon)}</span>`;
                        }

                        const addonPrice = Number(addon.price || 0);
                        return `<span class="addon-chip">${escapeHtml(addon.title || 'Add-on')}${addonPrice > 0 ? ` - ${money(addonPrice, sym)}` : ' - free'}</span>`;
                    }).join('')}</div>` : ''}
                </div>
                <div class="row-side">
                    <div class="qty">Qty ${Number(it.qty || 1)}</div>
                    <div class="amt">${money(it.line_total, sym)}</div>
                </div>
            </div>`;
    }

    function showCart(state) {
        const sym = state.currency_symbol || '';
        const items = Array.isArray(state.items) ? state.items : [];
        if (!items.length) {
            showEmpty(state);
            return;
        }

        $empty.style.display = 'none';
        $sale.style.display = '';
        $footer.style.display = '';

        syncStoreHeader(state);
        $storeSub.textContent = 'Order in progress';

        const itemCount = items.reduce((sum, item) => sum + Number(item.qty || 0), 0);
        if ($itemCount) {
            $itemCount.textContent = `${itemCount} ${itemCount === 1 ? 'item' : 'items'}`;
        }

        $rows.innerHTML = items.map(it => renderItem(it, sym)).join('');

        const b = state.breakdown || {};
        const lines = [];
        const sub = (typeof b.subtotalExTax === 'number') ? b.subtotalExTax : b.subtotal;
        lines.push(`<div class="line"><span>Subtotal</span><span>${money(sub, sym)}</span></div>`);

        if (Array.isArray(b.taxByRate) && b.taxByRate.length) {
            for (const t of b.taxByRate) {
                lines.push(`<div class="line"><span>Tax @ ${escapeHtml(t.rate)}%</span><span>${money(t.amount, sym)}</span></div>`);
            }
        } else if (b.tax > 0) {
            lines.push(`<div class="line"><span>Tax included</span><span>${money(b.tax, sym)}</span></div>`);
        }

        if (b.savings > 0) {
            lines.push(`<div class="line savings"><span>You saved</span><span>-${money(b.savings, sym)}</span></div>`);
        }
        if (b.discountAmount > 0) {
            lines.push(`<div class="line"><span>Discount</span><span>-${money(b.discountAmount, sym)}</span></div>`);
        }
        if (b.promoAmount > 0) {
            lines.push(`<div class="line"><span>Promo${b.promoCode ? ' - ' + escapeHtml(b.promoCode) : ''}</span><span>-${money(b.promoAmount, sym)}</span></div>`);
        }
        if (b.walletApplied > 0) {
            lines.push(`<div class="line"><span>Wallet</span><span>-${money(b.walletApplied, sym)}</span></div>`);
        }

        $breakdown.innerHTML = lines.join('');
        if ($savingsNote) {
            $savingsNote.textContent = b.savings > 0 ? (b.savingsMessage || '') : '';
        }
        $total.textContent = money(b.total ?? 0, sym);
    }

    function setLive(on) {
        $connDot.classList.toggle('live', on);
        $connLabel.textContent = on ? 'live' : 'reconnecting...';
    }

    let lastPushedAt = null;
    let stalenessTicker = null;

    function startStalenessTicker() {
        clearInterval(stalenessTicker);
        stalenessTicker = setInterval(() => {
            if (!lastPushedAt) {
                return;
            }

            const ageSec = (Date.now() - lastPushedAt.getTime()) / 1000;
            if (ageSec > 30) {
                setLive(false);
            }
        }, 5000);
    }

    async function poll() {
        try {
            const res = await fetch(pullUrl, { cache: 'no-store' });
            const response = await res.json();
            const payload = response?.data || {};
            if (payload.active && payload.state) {
                lastPushedAt = payload.state.__pushed_at ? new Date(payload.state.__pushed_at) : new Date();
                setLive(true);
                showCart(payload.state);
            } else {
                setLive(false);
                showEmpty();
            }
        } catch {
            setLive(false);
        }
    }

    poll();
    setInterval(poll, 1500);
    startStalenessTicker();
})();
</script>
</body>
</html>
