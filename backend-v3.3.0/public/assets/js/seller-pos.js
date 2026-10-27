/**
 * Seller-panel POS page — product-centric grid with variant + addon support.
 *
 * IMPORTANT: Tabler v1.2.0 doesn't expose `window.bootstrap`, so we route
 * modal show/hide through a small helper that prefers BS5 native and falls
 * back to jQuery's bootstrap-modal plugin (which IS loaded site-wide).
 *
 * Click a product card:
 *   - 1 variant + no addons → add directly to cart
 *   - >1 variant OR has addons → open the customize modal:
 *       · variant pills (auto-selected if only one)
 *       · addon groups (single = radios, multiple = checkboxes,
 *                       required = must satisfy before "Add to cart" enables)
 *       · live line total
 *
 * Cart lines are keyed by a signature so a second selection with the SAME
 * variant + SAME addon set increments qty rather than duplicating a row.
 */
(function () {
    'use strict';

    const config = window.__POS_CONFIG__ || {};
    const productsUrl          = config.productsUrl          || '/seller/pos/api/products';
    const barcodeLookupUrl     = config.barcodeLookupUrl     || '/seller/pos/api/products/by-barcode';
    const categoriesUrl        = config.categoriesUrl        || '/seller/pos/api/categories';
    const usersSearchUrl       = config.usersSearchUrl       || '/seller/pos/api/users/search';
    const customersRegisterUrl = config.customersRegisterUrl || '/seller/pos/api/customers';
    const ordersCreateUrl      = config.ordersCreateUrl      || '/seller/pos/api/orders';
    const ordersRecentUrl      = config.ordersRecentUrl      || '/seller/pos/api/orders/recent';
    const promoValidateUrl     = config.promoValidateUrl     || '/seller/pos/api/promo/validate';
    const walletLookupUrlT     = config.walletLookupUrlT     || '/seller/pos/api/customers/__ID__/wallet';
    const cfdPushUrl           = config.cfdPushUrl           || '/seller/pos/api/cfd/push';
    const cfdShowUrlT          = config.cfdShowUrlT          || '/pos/display/__TOKEN__';
    const parkedListUrl        = config.parkedListUrl        || '/seller/pos/api/parked';
    const parkedCreateUrl      = config.parkedCreateUrl      || '/seller/pos/api/parked';
    const parkedUpdateUrlT     = config.parkedUpdateUrlT     || '/seller/pos/api/parked/__ID__';
    const parkedDeleteUrlT     = config.parkedDeleteUrlT     || '/seller/pos/api/parked/__ID__';
    const paymentSessionsUrl   = config.paymentSessionsUrl   || '/seller/pos/api/payment-sessions';
    const sessionStatusUrlT    = config.paymentSessionStatusUrlTemplate || '/seller/pos/api/payment-sessions/__TOKEN__';
    const sessionCancelUrlT    = config.paymentSessionCancelUrlTemplate || '/seller/pos/api/payment-sessions/__TOKEN__/cancel';
    const refundPreviewUrlT    = config.refundPreviewUrlT    || '/seller/pos/api/orders/__ID__/refund-preview';
    const refundCreateUrlT     = config.refundCreateUrlT     || '/seller/pos/api/orders/__ID__/refund';
    const refundHistoryUrlT    = config.refundHistoryUrlT    || '/seller/pos/api/orders/__ID__/refunds';
    const receiptUrlTemplate   = config.receiptUrlTemplate   || '/seller/pos/orders/__ID__/receipt';
    const storePaymentConfig   = config.storePaymentConfig   || {};
    const storeDisplayMeta     = config.storeDisplayMeta     || {};
    const initialStoreId = parseInt(config.initialStoreId, 10) || 0;
    const initialCurrencyCode   = String(config.currencyCode || '').toUpperCase();
    const SYMBOL_FALLBACK = { INR: '₹', USD: '$', EUR: '€', GBP: '£', AED: 'د.إ', SAR: 'ر.س', AUD: 'A$', CAD: 'C$', NGN: '₦', BRL: 'R$', JPY: '¥' };
    const initialCurrencySymbol  = config.currencySymbol || SYMBOL_FALLBACK[initialCurrencyCode] || (initialCurrencyCode ? `${initialCurrencyCode} ` : '');

    // ── Modal helper (Tabler doesn't expose window.bootstrap; jQuery's
    // modal plugin is loaded site-wide so we fall back to it).
    function showModal(el) {
        if (!el) return;
        if (window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(el).show();
        } else if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
            window.jQuery(el).modal('show');
        } else {
            el.classList.add('show');
            el.style.display = 'block';
            el.setAttribute('aria-hidden', 'false');
            document.body.classList.add('modal-open');
        }
    }
    function hideModal(el) {
        if (!el) return;
        if (window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getInstance(el)?.hide();
        } else if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
            window.jQuery(el).modal('hide');
        } else {
            el.classList.remove('show');
            el.style.display = 'none';
            el.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
        }
    }

    // Shell elements
    const $store    = document.getElementById('pos-store-select');
    const $search   = document.getElementById('pos-search-input');
    const $barcode  = document.getElementById('pos-barcode-input');
    const $grid     = document.getElementById('pos-product-grid');
    const $empty    = document.getElementById('pos-product-empty');
    const $emptyHint= document.getElementById('pos-product-empty-hint');
    const $catRow   = document.getElementById('pos-category-row');
    const $loadMoreWrap = document.getElementById('pos-loadmore-wrap');
    const $loadMoreBtn  = document.getElementById('pos-loadmore-btn');
    const $loadMoreInfo = document.getElementById('pos-loadmore-info');
    const $cartList = document.getElementById('pos-cart-list');
    const $cartEmpty= document.getElementById('pos-cart-empty');
    const $cartTotal= document.getElementById('pos-cart-total');
    const $cartMeta = document.getElementById('pos-cart-meta');
    const $checkoutBtn = document.getElementById('pos-checkout-btn');
    const $fullscreenBtn = document.getElementById('pos-fullscreen-btn');

    // Customize modal
    const $custModalEl   = document.getElementById('pos-customize-modal');
    const $custModalTitle= document.getElementById('pos-customize-title');
    const $custModalThumb= document.getElementById('pos-customize-thumb');
    const $custModalBody = document.getElementById('pos-customize-body');
    const $custLineTotal = document.getElementById('pos-customize-line-total');
    const $custAddBtn    = document.getElementById('pos-customize-add-btn');

    // Checkout modal
    const $modalEl   = document.getElementById('pos-checkout-modal');
    const $modalTotal= document.getElementById('pos-modal-total');
    const $modalTotalMoney = document.getElementById('pos-modal-total-money');
    const $modalSummary    = document.getElementById('pos-modal-summary');
    const $custTabs  = document.getElementById('pos-customer-tabs');
    const $panes     = $modalEl ? $modalEl.querySelectorAll('.pos-customer-pane') : [];
    const $custSearch= document.getElementById('pos-customer-search');
    const $custSelected= document.getElementById('pos-customer-selected');
    let custTomSelect = null;
    // Restrict mobile/number inputs to digits — strips pasted or typed non-numerics.
    document.querySelectorAll('.pos-numeric-only').forEach(el => {
        el.addEventListener('input', () => {
            const cleaned = el.value.replace(/[^0-9]/g, '');
            if (cleaned !== el.value) el.value = cleaned;
        });
    });

    // Country-code inputs allow digits and a single leading "+" (e.g. +91).
    document.querySelectorAll('.pos-cc-only').forEach(el => {
        el.addEventListener('input', () => {
            const digits = el.value.replace(/[^0-9]/g, '');
            const cleaned = el.value.trim().startsWith('+') ? '+' + digits : digits;
            if (cleaned !== el.value) el.value = cleaned;
        });
    });

    const $newToggleBtn = document.getElementById('pos-new-toggle-btn');
    const $newForm   = document.getElementById('pos-new-form');
    const $newRegBtn = document.getElementById('pos-new-register-btn');
    const $newResult = document.getElementById('pos-new-result');
    const $orderNote = document.getElementById('pos-order-note');
    const $checkoutError = document.getElementById('pos-checkout-error');
    const $completeBtn = document.getElementById('pos-complete-sale-btn');
    const $cashReceived = document.getElementById('pos-cash-received');
    const $changeDue = document.getElementById('pos-change-due');
    const $cashShort = document.getElementById('pos-cash-short');
    const $cashShortAmt = document.getElementById('pos-cash-short-amt');

    // Payment tabs + UPI elements
    const $payTabs       = document.getElementById('pos-payment-tabs');
    const $payTabUpi     = document.getElementById('pos-payment-tab-upi');
    const $payPanes      = $modalEl ? $modalEl.querySelectorAll('.pos-payment-pane') : [];
    const $upiNotConfig  = document.getElementById('pos-upi-not-configured');
    const $upiActive     = document.getElementById('pos-upi-active');
    const $upiQr         = document.getElementById('pos-upi-qr');
    const $upiPayee      = document.getElementById('pos-upi-payee');
    const $upiVpa        = document.getElementById('pos-upi-vpa');
    const $upiAmount     = document.getElementById('pos-upi-amount');

    // Online (Razorpay) elements
    const $onlineIdle      = document.getElementById('pos-online-idle');
    const $onlineActive    = document.getElementById('pos-online-active');
    const $onlineGenerate  = document.getElementById('pos-online-generate-btn');
    const $onlineQr        = document.getElementById('pos-online-qr');
    const $onlineUrl       = document.getElementById('pos-online-url');
    const $onlineAmount    = document.getElementById('pos-online-amount');
    const $onlineCancelBtn = document.getElementById('pos-online-cancel-btn');
    const $onlineStatusTxt = document.getElementById('pos-online-status-text');
    const $onlineCountdown = document.getElementById('pos-online-countdown');
    const $onlineError     = document.getElementById('pos-online-error');

    // Split-tender elements
    const $splitIdle       = document.getElementById('pos-split-idle');
    const $splitActive     = document.getElementById('pos-split-active');
    const $splitCashInput  = document.getElementById('pos-split-cash-input');
    const $splitOnlineInput= document.getElementById('pos-split-online-input');
    const $splitTotalEl    = document.getElementById('pos-split-total');
    const $splitGenBtn     = document.getElementById('pos-split-generate-btn');
    const $splitError      = document.getElementById('pos-split-error');
    const $splitQr         = document.getElementById('pos-split-qr');
    const $splitCountdown  = document.getElementById('pos-split-countdown');
    const $splitStatusTxt  = document.getElementById('pos-split-status-text');
    const $splitCashShown  = document.getElementById('pos-split-cash-shown');
    const $splitOnlineShown= document.getElementById('pos-split-online-shown');
    const $splitCancelBtn  = document.getElementById('pos-split-cancel-btn');

    // Discount / promo / wallet elements (queried once, used in multiple handlers)
    const $discountForm   = document.getElementById('pos-discount-form');
    const $discountInput  = document.getElementById('pos-discount-input');
    const $discountToggle = document.getElementById('pos-discount-toggle-btn');
    const $discountHint   = document.getElementById('pos-discount-hint');
    const $promoForm      = document.getElementById('pos-promo-form');
    const $promoInput     = document.getElementById('pos-promo-input');
    const $promoToggle    = document.getElementById('pos-promo-toggle-btn');
    const $promoHint      = document.getElementById('pos-promo-hint');
    const $walletBlock    = document.getElementById('pos-wallet-block');
    const $walletBalance  = document.getElementById('pos-wallet-balance');
    const $walletInput    = document.getElementById('pos-wallet-input');
    const $walletHint     = document.getElementById('pos-wallet-hint');
    const $confirmModal   = document.getElementById('pos-confirm-modal');
    const $confirmStatus  = document.getElementById('pos-confirm-status');
    const $confirmTitle   = document.getElementById('pos-confirm-title');
    const $confirmMessage = document.getElementById('pos-confirm-message');
    const $confirmOkBtn   = document.getElementById('pos-confirm-ok-btn');
    const $holdLabelModal = document.getElementById('pos-hold-label-modal');
    const $holdLabelInput = document.getElementById('pos-hold-label-input');
    const $holdLabelSaveBtn = document.getElementById('pos-hold-label-save-btn');

    // ── State ──
    // Cart row shape:
    //   { signature, store_product_variant_id, product_id, title, variant_title,
    //     unit_price, qty, stock,
    //     regular_unit_price,        // display (incl-tax) regular price for "you saved"
    //     tax_percent, is_inclusive_tax,
    //     addons: [{ addon_group_id, addon_item_id, group_title, item_title, price }] }
    const cart = new Map();
    const productIndex = new Map();

    // Bill-level cashier discount.
    //   type: 'percent' | 'fixed' | null
    //   value: numeric
    const billDiscount = { type: null, value: 0, formType: 'fixed' };

    // Applied promo code (validated server-side).
    //   code: string
    //   amount: server-resolved discount in displayed-currency
    const promoState = { code: null, amount: 0, discountType: null, discountValue: null, maxDiscountValue: null };
    let promoRevalidateTimer = null;
    let promoValidationToken = 0;

    // Wallet payment portion (set in checkout modal once a customer is picked).
    //   balance: cached from API at customer-pick time (display only)
    //   applied: cashier-entered amount to apply
    const walletState = { balance: 0, applied: 0 };

    const customizeState = {
        product: null,
        variantId: null,
        groupChoices: {},
        // When set, commit replaces the existing cart row instead of adding
        // new — used by the cart row "edit" pencil. We carry the qty over too
        // so editing doesn't silently reset to 1.
        editingSignature: null,
        editingQty: 1,
    };

    const customerState = { mode: 'walkin', customer_id: null };
    const paymentState  = { method: 'cash', customMethodName: null }; // 'cash' | 'upi' | 'online' | 'custom'

    // Custom payment method pane elements
    const $customPane         = document.getElementById('pos-custom-method-pane');
    const $customIcon         = document.getElementById('pos-custom-icon');
    const $customLabel        = document.getElementById('pos-custom-label');
    const $customInstructions = document.getElementById('pos-custom-instructions');
    const $customAmount       = document.getElementById('pos-custom-amount');

    // Active online-payment session (only set while one is in flight). The
    // beforeunload guard checks this — if non-null, we warn before close.
    const onlineSession = {
        token: null,
        expiresAt: null,    // Date
        pollTimer: null,
        countdownTimer: null,
    };

    // Pagination + filter state
    const listState = {
        page: 1,
        lastPage: 1,
        perPage: 24,
        total: 0,
        loading: false,
        categoryId: null,
        q: '',
    };

    const confirmState = { onConfirm: null };

    // ── helpers ──

    const fmtNumber = (n) => Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    function selectedStoreCurrencyCode() {
        const storeOpt = $store?.options[$store.selectedIndex];
        const configuredCode = currentStorePaymentConfig()?.currency_code || storeOpt?.dataset.currency || initialCurrencyCode;

        return String(configuredCode || '').toUpperCase();
    }

    function currentCurrencyLabel() {
        const storeOpt = $store?.options[$store.selectedIndex];
        const code = selectedStoreCurrencyCode();
        const configuredLabel = currentStorePaymentConfig()?.currency_symbol || storeOpt?.dataset.currencySymbol;

        return configuredLabel || SYMBOL_FALLBACK[code] || (code ? `${code} ` : initialCurrencySymbol);
    }

    const money = (n) => `${currentCurrencyLabel()}${fmtNumber(n)}`;
    const savingsMessage = 'Savings shown here come from the item special price and are already included in the total.';

    const escapeHtml = (s) => String(s ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

    const debounce = (fn, ms) => { let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); }; };

    function showPosError(message, title = 'Could not continue') {
        if (typeof Toast !== 'undefined' && Toast?.fire) {
            Toast.fire({ icon: 'warning', title: message });
            return;
        }


        window.alert(message);
    }

    function showConfirmModal({ title = 'Confirm action', message = 'Are you sure?', confirmLabel = 'Confirm', confirmClass = 'btn-primary', onConfirm = null }) {
        if (!$confirmModal || !$confirmOkBtn) {
            if (typeof onConfirm === 'function' && window.confirm(message)) {
                onConfirm();
            }
            return;
        }

        confirmState.onConfirm = onConfirm;
        if ($confirmTitle) {
            $confirmTitle.textContent = title;
        }
        if ($confirmMessage) {
            $confirmMessage.textContent = message;
        }
        $confirmOkBtn.textContent = confirmLabel;
        $confirmOkBtn.className = `btn ${confirmClass}`;
        if ($confirmStatus) {
            $confirmStatus.className = `modal-status ${confirmClass.includes('danger') ? 'bg-danger' : 'bg-primary'}`;
        }
        showModal($confirmModal);
    }

    function noImageMarkup(title, extraClass = '') {
        const cls = extraClass ? ` class="${extraClass}"` : '';
        return `<span${cls} aria-label="No image">No image</span>`;
    }

    function hydratePosImages(scope = document) {
        scope.querySelectorAll('img[data-pos-image]').forEach((img) => {
            if (img.dataset.posImageBound === '1') {
                return;
            }

            img.dataset.posImageBound = '1';
            img.addEventListener('error', () => {
                const fallback = document.createElement('span');
                fallback.textContent = 'No image';
                fallback.setAttribute('aria-label', 'No image');
                if (img.closest('.pos-product-thumb')) {
                    fallback.className = 'pos-product-no-image';
                }
                img.replaceWith(fallback);
            }, { once: true });
        });
    }

    function currentStoreId() {
        return parseInt($store?.value, 10) || 0;
    }

    function posDraftStorageKey(storeId = currentStoreId()) {
        return storeId ? `seller_pos_draft_${storeId}` : null;
    }

    function clearPersistedDraft(storeId = currentStoreId()) {
        const key = posDraftStorageKey(storeId);
        if (!key) {
            return;
        }

        try {
            localStorage.removeItem(key);
        } catch (_) {}
    }

    function persistCurrentDraft() {
        const key = posDraftStorageKey();
        if (!key) {
            return;
        }

        if (cart.size === 0) {
            clearPersistedDraft();
            return;
        }

        const payload = {
            cart: Array.from(cart.values()).map((row) => ({
                store_product_variant_id: row.store_product_variant_id,
                product_id: row.product_id,
                title: row.title,
                variant_title: row.variant_title,
                unit_price: Number(row.unit_price || 0),
                regular_unit_price: Number(row.regular_unit_price || 0),
                tax_percent: Number(row.tax_percent || 0),
                is_inclusive_tax: !!row.is_inclusive_tax,
                qty: Number(row.qty || 0),
                stock: Number(row.stock || 0),
                minimum_order_quantity: normalizedQtyRule(row.minimum_order_quantity, 1),
                quantity_step_size: normalizedQtyRule(row.quantity_step_size, 1),
                total_allowed_quantity: normalizedMaxQty(row.total_allowed_quantity),
                addons: Array.isArray(row.addons) ? row.addons.map((addon) => ({
                    addon_group_id: addon.addon_group_id,
                    addon_item_id: addon.addon_item_id,
                    group_title: addon.group_title,
                    item_title: addon.item_title,
                    price: Number(addon.price || 0),
                })) : [],
            })),
        };

        try {
            localStorage.setItem(key, JSON.stringify(payload));
        } catch (_) {}
    }

    function restorePersistedDraft(storeId = currentStoreId()) {
        cart.clear();
        clearCartAdjustments();

        const key = posDraftStorageKey(storeId);
        if (!key) {
            renderCart();
            return;
        }

        try {
            const raw = localStorage.getItem(key);
            if (!raw) {
                renderCart();
                return;
            }

            const payload = JSON.parse(raw);
            const rows = Array.isArray(payload?.cart) ? payload.cart : [];

            rows.forEach((row) => {
                const normalizedRow = {
                    store_product_variant_id: Number(row.store_product_variant_id || 0),
                    product_id: Number(row.product_id || 0),
                    title: row.title || 'Item',
                    variant_title: row.variant_title || 'Default',
                    unit_price: Number(row.unit_price || 0),
                    regular_unit_price: Number(row.regular_unit_price || 0),
                    tax_percent: Number(row.tax_percent || 0),
                    is_inclusive_tax: !!row.is_inclusive_tax,
                    qty: Math.max(1, Number(row.qty || 1)),
                    stock: Number(row.stock || 0),
                    minimum_order_quantity: normalizedQtyRule(row.minimum_order_quantity, 1),
                    quantity_step_size: normalizedQtyRule(row.quantity_step_size, 1),
                    total_allowed_quantity: normalizedMaxQty(row.total_allowed_quantity),
                    addons: Array.isArray(row.addons) ? row.addons.map((addon) => ({
                        addon_group_id: addon.addon_group_id,
                        addon_item_id: addon.addon_item_id,
                        group_title: addon.group_title,
                        item_title: addon.item_title,
                        price: Number(addon.price || 0),
                    })) : [],
                };

                if (!normalizedRow.store_product_variant_id) {
                    return;
                }

                const signature = cartSignature(normalizedRow.store_product_variant_id, normalizedRow.addons);
                cart.set(signature, { ...normalizedRow, signature });
            });
        } catch (_) {
            clearPersistedDraft(storeId);
        }

        renderCart();
    }

    // Subtotal = tax-inclusive sum (matches what's displayed everywhere).
    const rowLineTotal = (row) => {
        const addonSum = row.addons.reduce((acc, a) => acc + Number(a.price || 0), 0);
        return (Number(row.unit_price) + addonSum) * row.qty;
    };

    // Tax baked into the line (informational; subtotal already includes it).
    const rowLineTax = (row) => {
        const pct = Number(row.tax_percent || 0);
        if (pct <= 0) return 0;
        const lineIncl = rowLineTotal(row);
        return lineIncl - (lineIncl / (1 + pct / 100));
    };

    // Strict savings: only count lines where regular_unit_price > unit_price
    // (i.e. items actually on sale). Cashier discount is shown separately.
    const rowLineSavings = (row) => {
        const reg  = Number(row.regular_unit_price || 0);
        const eff  = Number(row.unit_price || 0);
        if (reg <= eff) return 0;
        return (reg - eff) * row.qty;
    };

    function round2(n) { return Math.round((Number(n) || 0) * 100) / 100; }

    // Render a QR code SVG into a target element via the qrcode library.
    function renderQrInto(el, data) {
        if (!el) return;
        if (!data || !window.qrcode) {
            el.innerHTML = '<div class="text-secondary small">QR unavailable</div>';
            return;
        }
        try {
            const qr = window.qrcode(0, 'M');
            qr.addData(data);
            qr.make();
            el.innerHTML = qr.createSvgTag({ scalable: true, margin: 2 });
        } catch (err) {
            console.error('QR render failed', err);
            el.innerHTML = '<div class="text-secondary small">QR error</div>';
        }
    }

    // Format remaining seconds from an expiry Date into "M:SS".
    function formatCountdown(expiresAt) {
        if (!expiresAt) return null;
        const remaining = Math.max(0, Math.floor((expiresAt.getTime() - Date.now()) / 1000));
        const m = Math.floor(remaining / 60);
        const s = remaining % 60;
        return { text: `${m}:${String(s).padStart(2, '0')}`, remaining };
    }

    // Format an ISO date string into a human-readable date + time.
    function formatTime(iso) {
        if (!iso) return '';
        try { return new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }); }
        catch { return iso; }
    }

    // Map cart rows into the payload shape the backend expects.
    function buildCartItemsPayload() {
        return Array.from(cart.values()).map(r => ({
            store_product_variant_id: r.store_product_variant_id,
            quantity: r.qty,
            addons: r.addons.map(a => ({ addon_group_id: a.addon_group_id, addon_item_id: a.addon_item_id })),
        }));
    }

    // Clear cart, customer, discount, promo, wallet and form fields after a sale.
    function resetSaleState() {
        cart.clear();
        clearBillDiscount();
        clearPromo();
        walletState.applied = 0;
        renderCart();
        customerState.customer_id = null;
        setCustomerMode('walkin');
        ['pos-walkin-name','pos-walkin-cc','pos-walkin-mobile','pos-new-name','pos-new-cc','pos-new-mobile','pos-new-email'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        if ($orderNote) $orderNote.value = '';
        if ($cashReceived) $cashReceived.value = '';
        recomputeChange();
    }

    // The single source of truth for what's shown on cart sidebar, modal
    // summary, and what gets sent on checkout.
    function cartBreakdown() {
        let subtotal = 0, tax = 0, savings = 0;
        // Group tax by rate so the cart / checkout / CFD can show
        // "Tax @ 5%: ₹X" instead of an opaque "incl. tax: ₹Y" line.
        // Same shape as the printable receipt so the cashier sees the same
        // breakdown on every surface.
        const taxByRate = new Map();   // rate-string -> amount
        cart.forEach(r => {
            subtotal += rowLineTotal(r);
            const lineTax = rowLineTax(r);
            tax += lineTax;
            savings += rowLineSavings(r);

            const pct = Number(r.tax_percent || 0);
            if (pct > 0 && lineTax > 0) {
                const key = (pct === Math.trunc(pct)) ? String(pct) : pct.toFixed(2);
                taxByRate.set(key, round2((taxByRate.get(key) || 0) + lineTax));
            }
        });
        subtotal = round2(subtotal);
        tax      = round2(tax);
        savings  = round2(savings);

        if (subtotal <= 0) {
            return {
                subtotal: 0,
                subtotalExTax: 0,
                tax: 0,
                taxByRate: [],
                savings: 0,
                discountAmount: 0,
                promoAmount: 0,
                promoCode: null,
                walletApplied: 0,
                total: 0,
            };
        }

        // Stable sort by numeric rate ascending.
        const taxByRateArr = [...taxByRate.entries()]
            .map(([rate, amount]) => ({ rate, amount }))
            .sort((a, b) => parseFloat(a.rate) - parseFloat(b.rate));
        const subtotalExTax = round2(Math.max(0, subtotal - tax));

        let discountAmount = 0;
        if (billDiscount.type && billDiscount.value > 0 && subtotal > 0) {
            if (billDiscount.type === 'percent') {
                const pct = Math.min(100, Math.max(0, Number(billDiscount.value)));
                discountAmount = round2(subtotal * pct / 100);
            } else {
                discountAmount = round2(Math.min(subtotal, Math.max(0, Number(billDiscount.value))));
            }
        }
        // Promo applies on top of cashier discount.
        const afterDiscount = Math.max(0, subtotal - discountAmount);
        const promoAmount = round2(Math.min(afterDiscount, Math.max(0, Number(promoState.amount || 0))));
        // Wallet draws against whatever's left.
        const afterPromo = Math.max(0, afterDiscount - promoAmount);
        const walletApplied = round2(Math.min(afterPromo, Math.max(0, Number(walletState.applied || 0))));

        const total = round2(Math.max(0, afterPromo - walletApplied));
        return {
            subtotal, subtotalExTax,
            tax, taxByRate: taxByRateArr,
            savings, discountAmount,
            promoAmount, promoCode: promoState.code,
            walletApplied, total,
        };
    }

    const cartSignature = (svId, addons) => {
        const sortedAddonIds = addons.map(a => a.addon_item_id).sort((a,b) => a - b).join(',');
        return `${svId}::${sortedAddonIds}`;
    };

    // ── Bill-level discount controls ──

    function applyBillDiscount(type, value) {
        const num = Number(value);
        if (!type || isNaN(num) || num <= 0) return;
        const subtotal = cartBreakdown().subtotal;
        if (type === 'fixed' && num > subtotal) {
            showPosError('Discount amount cannot be greater than the cart total.', 'Invalid discount');
            $discountInput?.focus();
            return;
        }
        billDiscount.type  = type;
        billDiscount.value = num;
        if ($discountInput) $discountInput.value = '';
        renderCart(); // recomputes summary + total
    }

    function clearBillDiscount() {
        billDiscount.type  = null;
        billDiscount.value = 0;
        $discountForm?.classList.add('d-none');
        $discountToggle?.classList.remove('d-none');
        if ($discountInput) $discountInput.value = '';
    }

    function clearCartAdjustments({ render = false } = {}) {
        clearBillDiscount();
        clearPromo({ render: false });
        walletState.applied = 0;
        if ($walletInput) {
            $walletInput.value = '';
        }

        if (render) {
            renderCart({ revalidatePromo: false });
        }
    }

    function promoEligibleCartAmount() {
        const cartAmount = cartBreakdown().subtotal - cartBreakdown().discountAmount;
        return round2(Math.max(0, cartAmount));
    }

    function syncPromoState(data, fallbackCode) {
        const promo = data || {};
        promoState.code = promo.promo_code || fallbackCode;
        promoState.amount = Number(promo.discount || 0);
        promoState.discountType = promo.discount_type || null;
        promoState.discountValue = promo.discount_amount !== null && promo.discount_amount !== undefined
            ? Number(promo.discount_amount)
            : null;
        promoState.maxDiscountValue = promo.max_discount_value !== null && promo.max_discount_value !== undefined
            ? Number(promo.max_discount_value)
            : null;
    }

    async function validatePromoForCurrentCart(code, { showErrors = false } = {}) {
        const cartAmount = promoEligibleCartAmount();
        if (cartAmount <= 0) {
            throw new Error('Cart is empty.');
        }

        const token = ++promoValidationToken;
        const res = await axios.post(promoValidateUrl, {
            promo_code: code,
            cart_amount: cartAmount,
            customer_id: customerState.customer_id || null,
        });
        if (token !== promoValidationToken) {
            return false;
        }

        const ok = res?.data?.success;
        const msg = res?.data?.message;
        if (!ok) {
            throw new Error(msg || 'Invalid promo code.');
        }

        syncPromoState(res?.data?.data || {}, code);

        if ($promoHint) {
            $promoHint.textContent = 'Re-validated server-side at checkout.';
            $promoHint.classList.remove('text-danger');
        }

        if (showErrors) {
            renderCart({ revalidatePromo: false });
        }

        return true;
    }

    async function applyPromoCode() {
        const code = ($promoInput?.value || '').trim();
        if (!code) { $promoInput?.focus(); return; }

        try {
            await validatePromoForCurrentCart(code, { showErrors: true });
            $promoForm?.classList.add('d-none');
            $promoToggle?.classList.remove('d-none');
            if ($promoInput) $promoInput.value = '';
        } catch (err) {
            const msg = err?.response?.data?.message || err?.message || 'Could not apply promo.';
            if ($promoHint) { $promoHint.textContent = msg; $promoHint.classList.add('text-danger'); }
            showPosError(msg, 'Invalid promo code');
        }
    }

    function clearPromo({ render = true } = {}) {
        promoState.code = null;
        promoState.amount = 0;
        promoState.discountType = null;
        promoState.discountValue = null;
        promoState.maxDiscountValue = null;
        if (promoRevalidateTimer) {
            clearTimeout(promoRevalidateTimer);
            promoRevalidateTimer = null;
        }
        $promoForm?.classList.add('d-none');
        $promoToggle?.classList.remove('d-none');
        if ($promoInput) $promoInput.value = '';
        if (render) {
            renderCart({ revalidatePromo: false });
        }
    }

    async function revalidateAppliedPromo({ showRemoval = true } = {}) {
        if (!promoState.code) {
            return true;
        }

        const code = promoState.code;

        try {
            await validatePromoForCurrentCart(code);
            renderCart({ revalidatePromo: false });
            return true;
        } catch (err) {
            const msg = err?.response?.data?.message || err?.message || 'Promo no longer applies to this cart.';
            clearPromo({ render: false });
            renderCart({ revalidatePromo: false });

            if ($promoHint) {
                $promoHint.textContent = msg;
                $promoHint.classList.add('text-danger');
            }

            if (showRemoval) {
                showPosError(`Promo removed: ${msg}`, 'Promo no longer applies');
            }

            return false;
        }
    }

    function schedulePromoRevalidation() {
        if (!promoState.code) {
            return;
        }

        if (promoRevalidateTimer) {
            clearTimeout(promoRevalidateTimer);
        }

        promoRevalidateTimer = setTimeout(() => {
            promoRevalidateTimer = null;
            revalidateAppliedPromo();
        }, 300);
    }

    function promoBreakdownLabel() {
        const parts = [];

        if (promoState.code) {
            parts.push(promoState.code);
        }

        if (promoState.discountType === 'percent' && promoState.discountValue !== null) {
            parts.push(`${fmtNumber(promoState.discountValue).replace(/\.00$/, '')}%`);
        } else if (promoState.discountType === 'flat' && promoState.discountValue !== null) {
            parts.push(`flat ${money(promoState.discountValue)}`);
        } else if (promoState.discountType === 'free_shipping') {
            parts.push('free shipping');
        }

        if (promoState.maxDiscountValue !== null && promoState.maxDiscountValue > 0) {
            parts.push(`max ${money(promoState.maxDiscountValue)}`);
        }

        return parts.length ? `(${parts.map(escapeHtml).join(' · ')})` : '';
    }

    function checkoutErrorMessage(err) {
        const response = err?.response?.data || {};
        const message = response?.message || err?.message || 'Could not complete sale.';
        const data = response?.data || {};

        if (data?.error_type === 'promo' || (promoState.code && String(message).toLowerCase().includes('promo'))) {
            const code = data?.promo_code || promoState.code;

            return code
                ? `${message} The cart was kept as-is. Remove or re-apply promo code "${code}", then try again.`
                : `${message} The cart was kept as-is. Remove or re-apply the promo code, then try again.`;
        }

        return message;
    }

    function clearInvalidPromoAfterCheckout(errorData = {}) {
        if (errorData?.error_type !== 'promo') {
            return false;
        }
        if (String(errorData?.promo_error || '').startsWith('cashback_')) {
            return false;
        }

        clearPromo({ render: false });
        if ($promoHint) {
            $promoHint.textContent = 'Re-validated server-side at checkout.';
            $promoHint.classList.remove('text-danger');
        }
        renderCart({ revalidatePromo: false });

        return true;
    }

    function removeStaleAddonCartRows(errorData = {}) {
        if (errorData?.error_type !== 'addon_changed') {
            return 0;
        }

        const staleAddonId = parseInt(errorData.addon_item_id, 10) || null;
        let removed = 0;

        cart.forEach((row, signature) => {
            const addons = Array.isArray(row.addons) ? row.addons : [];
            const shouldRemove = staleAddonId
                ? addons.some((addon) => parseInt(addon.addon_item_id, 10) === staleAddonId)
                : addons.length > 0;

            if (shouldRemove) {
                cart.delete(signature);
                removed++;
            }
        });

        if (removed > 0) {
            clearPromo({ render: false });
            walletState.applied = 0;
            if ($walletInput) {
                $walletInput.value = '';
            }
            renderCart({ revalidatePromo: false });
            fetchProducts({ append: false });
        }

        return removed;
    }

    function removeStaleProductCartRows(errorData = {}) {
        if (errorData?.error_type !== 'product_changed') {
            return 0;
        }

        const staleVariantId = parseInt(errorData.store_product_variant_id, 10) || null;
        let removed = 0;

        cart.forEach((row, signature) => {
            const shouldRemove = staleVariantId
                ? parseInt(row.store_product_variant_id, 10) === staleVariantId
                : false;

            if (shouldRemove) {
                cart.delete(signature);
                removed++;
            }
        });

        if (removed > 0) {
            clearPromo({ render: false });
            walletState.applied = 0;
            if ($walletInput) {
                $walletInput.value = '';
            }
            renderCart({ revalidatePromo: false });
            fetchProducts({ append: false });
        }

        return removed;
    }

    // A cart row is "editable" only when the product actually offers a
    // choice — multiple variants OR addon groups. For plain single-variant,
    // no-addon products (like Toned Milk 1L), the modal would just show
    // "Toned Milk 1L" with no controls, so there's nothing to edit.
    function rowIsEditable(row) {
        if (!row?.product_id) return false;
        const p = productIndex.get(row.product_id);
        if (!p) return false;
        const hasAddons = (p.variants || []).some(v => (v.addon_groups || []).length > 0);
        return !!(p.has_variants || hasAddons);
    }

    // Initials-based "logo" for missing product images. Cleaner than a generic
    // placeholder icon repeated 24 times across the grid.
    function initialsFor(title) {
        const words = String(title || '').trim().split(/\s+/).slice(0, 2);
        return (words.map(w => w.charAt(0).toUpperCase()).join('') || '·').slice(0, 2);
    }

    // ── product fetch + render ──

    let lastReqToken = 0;

    async function fetchProducts({ append = false } = {}) {
        const storeId = parseInt($store.value, 10);
        if (!storeId) return renderProducts([], { append: false });

        if (!append) {
            listState.page = 1;
            listState.q = ($search.value || '').trim();
        }

        listState.loading = true;
        if ($loadMoreBtn) {
            $loadMoreBtn.disabled = true;
            $loadMoreBtn.textContent = 'Loading…';
        }
        // Skeleton placeholders only on a fresh load — append keeps the
        // existing grid intact and lets the Load-more button show progress.
        if (!append) renderProductSkeleton();

        const myToken = ++lastReqToken;
        try {
            const res = await axios.get(productsUrl, {
                params: {
                    store_id: storeId,
                    q: listState.q,
                    page: listState.page,
                    per_page: listState.perPage,
                    include_out_of_stock: true,
                    category_id: listState.categoryId || undefined,
                },
            });
            if (myToken !== lastReqToken) return;
            const data = res?.data?.data || {};
            listState.lastPage = parseInt(data.last_page, 10) || 1;
            listState.total    = parseInt(data.total, 10) || 0;
            renderProducts(data.products || [], { append });
        } catch (err) {
            if (myToken !== lastReqToken) return;
            console.error('POS product search failed', err);
            renderProducts([], { append: false });
            $emptyHint.textContent = 'Could not load products. Try again.';
        } finally {
            listState.loading = false;
            if ($loadMoreBtn) {
                $loadMoreBtn.disabled = false;
                $loadMoreBtn.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="icon me-1"><path d="M6 9l6 6l6 -6"/></svg>
                    Load more`;
            }
        }
    }

    // Shows shimmer placeholders while a fresh fetch is in flight, so the
    // grid never goes blank between submit and render.
    function renderProductSkeleton() {
        const cols = 8;
        const cell = `
            <div class="col-6 col-sm-4 col-md-3 col-xxl-3">
                <div class="pos-skeleton-card" aria-hidden="true">
                    <div class="pos-skeleton-thumb"></div>
                    <div class="pos-skeleton-line"></div>
                    <div class="pos-skeleton-line short"></div>
                </div>
            </div>`;
        $grid.innerHTML = cell.repeat(cols);
        $empty.classList.add('d-none');
        if ($loadMoreWrap) $loadMoreWrap.classList.add('d-none');
    }

    function renderProducts(items, { append }) {
        if (!append) {
            $grid.innerHTML = '';
            productIndex.clear();
        }

        if (!items.length && !append) {
            $empty.classList.remove('d-none');
            $loadMoreWrap.classList.add('d-none');
            return;
        }
        $empty.classList.add('d-none');

        const frag = document.createDocumentFragment();
        for (const p of items) {
            productIndex.set(p.product_id, p);
            const def = p.default_variant || (p.variants && p.variants[0]) || null;
            if (!def) continue;
            const allOOS  = (p.variants || []).every(v => v.stock <= 0);
            const showSpecial = def.special_price > 0 && def.special_price < def.price;
            const fromPrefix = (p.variants && p.variants.length > 1) ? '<span class="pos-product-price-from">from</span>' : '';
            const defaultStock = Math.max(0, Number(def.stock || 0));
            const stockSum = defaultStock;

            // Stock-level emphasis (low at <10, critical at <=3) so the
            // cashier sees runs-out-soon items without reading numbers.
            let stockClass, stockLabel;
            if (allOOS) {
                stockClass = 'is-out';
                stockLabel = 'Out of stock';
            } else if (defaultStock <= 3) {
                stockClass = 'is-critical';
                stockLabel = `Only ${defaultStock} left`;
            } else if (defaultStock < 10) {
                stockClass = 'is-low';
                stockLabel = `Low · ${stockSum}`;
            } else {
                stockClass = 'is-good';
                stockLabel = `${defaultStock} in stock`;
            }
            if (stockClass === 'is-low') {
                stockLabel = `Low ${defaultStock}`;
            }
            const defaultStockLabel = `Default variant stock: ${defaultStock}`;

            const tags = [];
            if (p.has_variants) tags.push('<span class="badge bg-azure-lt">Sizes</span>');
            if (p.has_addons)   tags.push('<span class="badge bg-purple-lt">Add-ons</span>');

            const col = document.createElement('div');
            col.className = 'col-6 col-md-3 col-xl-3';
            const thumbInner = p.image
                ? `<img src="${escapeHtml(p.image)}" alt="${escapeHtml(p.title)}" loading="lazy" data-pos-image="1">`
                : noImageMarkup(p.title, 'pos-product-no-image');
            col.innerHTML = `
                <button type="button" class="pos-product-card"
                        data-product-id="${p.product_id}"
                        ${allOOS ? 'disabled' : ''}>
                    <div class="pos-product-thumb">
                        ${thumbInner}
                        <div class="pos-product-flag">${tags.join('')}</div>
                        <div class="pos-product-stock ${stockClass}">${stockLabel}</div>
                    </div>
                    <div class="pos-product-body">
                        <div class="pos-product-title" title="${escapeHtml(p.title)}">${escapeHtml(p.title)}</div>
                        <div class="text-secondary small">${escapeHtml(defaultStockLabel)}</div>
                        <div class="pos-product-priceline">
                            ${fromPrefix}<span class="pos-product-price">${money(def.effective_price)}</span>
                            ${showSpecial ? `<span class="pos-product-price-strike">${money(def.price)}</span>` : ''}
                        </div>
                    </div>
                </button>
            `;
            frag.appendChild(col);
        }
        $grid.appendChild(frag);
        hydratePosImages($grid);

        // Load-more visibility + status text
        const shown = $grid.children.length;
        if (listState.page < listState.lastPage) {
            $loadMoreWrap.classList.remove('d-none');
            $loadMoreInfo.textContent = `Showing ${shown} of ${listState.total}`;
        } else {
            $loadMoreWrap.classList.add('d-none');
        }
    }

    function loadMore() {
        if (listState.loading) return;
        if (listState.page >= listState.lastPage) return;
        listState.page += 1;
        fetchProducts({ append: true });
    }

    // ── categories ──

    async function refreshCategories() {
        const storeId = parseInt($store.value, 10);
        if (!storeId || !$catRow) return;
        try {
            const res = await axios.get(categoriesUrl, { params: { store_id: storeId } });
            const cats = res?.data?.data?.categories || [];
            const all = `<button type="button" class="pos-chip ${listState.categoryId ? '' : 'active'}" data-category-id="">All</button>`;
            const rest = cats.map(c => `<button type="button" class="pos-chip ${listState.categoryId === c.id ? 'active' : ''}" data-category-id="${c.id}">${escapeHtml(c.title)}</button>`).join('');
            $catRow.innerHTML = all + rest;
        } catch (err) {
            console.error('POS category fetch failed', err);
        }
    }

    function setCategory(id) {
        listState.categoryId = id || null;
        $catRow.querySelectorAll('.pos-chip').forEach(b => {
            const bid = b.dataset.categoryId ? parseInt(b.dataset.categoryId, 10) : null;
            b.classList.toggle('active', (bid || null) === (listState.categoryId || null));
        });
        fetchProducts({ append: false });
    }

    // ── cart ops ──

    function normalizedQtyRule(value, fallback = 1) {
        const parsed = Number(value);
        if (!Number.isFinite(parsed) || parsed <= 0) {
            return fallback;
        }

        return Math.max(1, Math.floor(parsed));
    }

    function normalizedMaxQty(value) {
        const parsed = Number(value);
        if (!Number.isFinite(parsed) || parsed <= 0) {
            return 0;
        }

        return Math.max(0, Math.floor(parsed));
    }

    function rowQtyLimits(row) {
        const minQty = normalizedQtyRule(row.minimum_order_quantity, 1);
        const stepQty = normalizedQtyRule(row.quantity_step_size, 1);
        const stockQty = Math.max(0, Math.floor(Number(row.stock || 0)));
        const allowedQty = normalizedMaxQty(row.total_allowed_quantity);
        const maxQty = allowedQty > 0 ? Math.min(stockQty, allowedQty) : stockQty;

        return { minQty, stepQty, maxQty, stockQty, allowedQty };
    }

    function quantityConstraintMessage(title, limits) {
        if (limits.maxQty > 0 && limits.maxQty < limits.minQty) {
            return `${title} does not have enough stock for its minimum quantity.`;
        }

        if (limits.allowedQty > 0 && limits.maxQty === limits.allowedQty && limits.allowedQty < limits.stockQty) {
            return `You can add up to ${limits.allowedQty} for ${title}.`;
        }

        if (limits.maxQty > 0) {
            return `You can add up to ${limits.maxQty} for ${title}.`;
        }

        return `Only ${limits.stockQty} in stock for ${title}.`;
    }

    function nextRowQuantity(row, direction) {
        const limits = rowQtyLimits(row);
        const currentQty = Math.max(0, Math.floor(Number(row.qty || 0)));
        const nextQty = currentQty + (limits.stepQty * direction);

        if (direction > 0) {
            if (limits.maxQty > 0 && nextQty > limits.maxQty) {
                return { ok: false, reason: quantityConstraintMessage(row.title, limits) };
            }

            return { ok: true, qty: nextQty };
        }

        if (nextQty <= 0 || nextQty < limits.minQty) {
            return { ok: true, remove: true };
        }

        return { ok: true, qty: nextQty };
    }

    function normalizeCartPayload(payload) {
        const minimum_order_quantity = normalizedQtyRule(payload.minimum_order_quantity, 1);
        const quantity_step_size = normalizedQtyRule(payload.quantity_step_size, 1);
        const total_allowed_quantity = normalizedMaxQty(payload.total_allowed_quantity);
        const stock = Math.max(0, Math.floor(Number(payload.stock || 0)));
        const normalizedPayload = {
            ...payload,
            minimum_order_quantity,
            quantity_step_size,
            total_allowed_quantity,
            stock,
            qty: Math.max(0, Math.floor(Number(payload.qty || minimum_order_quantity))),
        };
        const limits = rowQtyLimits(normalizedPayload);

        if (stock <= 0 || (limits.maxQty > 0 && limits.maxQty < limits.minQty)) {
            return { ok: false, reason: quantityConstraintMessage(payload.title || 'Item', limits) };
        }

        if (normalizedPayload.qty < limits.minQty) {
            normalizedPayload.qty = limits.minQty;
        }

        const extraQty = normalizedPayload.qty - limits.minQty;
        if (extraQty > 0) {
            normalizedPayload.qty = limits.minQty + (Math.ceil(extraQty / limits.stepQty) * limits.stepQty);
        }

        if (limits.maxQty > 0 && normalizedPayload.qty > limits.maxQty) {
            return { ok: false, reason: quantityConstraintMessage(payload.title || 'Item', limits) };
        }

        return { ok: true, payload: normalizedPayload };
    }

    function addCartRow(payload) {
        const normalized = normalizeCartPayload(payload);
        if (!normalized.ok) {
            flashStockWarning(payload.title, normalized.reason);
            return false;
        }

        const nextPayload = normalized.payload;
        const sig = cartSignature(nextPayload.store_product_variant_id, nextPayload.addons);
        const existing = cart.get(sig);
        if (existing) {
            const mergedRow = {
                ...existing,
                stock: Math.max(existing.stock, nextPayload.stock),
                minimum_order_quantity: nextPayload.minimum_order_quantity,
                quantity_step_size: nextPayload.quantity_step_size,
                total_allowed_quantity: nextPayload.total_allowed_quantity,
                qty: existing.qty,
            };
            const mergedLimits = rowQtyLimits(mergedRow);
            const nextQty = existing.qty + nextPayload.qty;
            if (mergedLimits.maxQty > 0 && nextQty > mergedLimits.maxQty) {
                flashStockWarning(existing.title, quantityConstraintMessage(existing.title, mergedLimits));
                return false;
            }

            existing.stock = mergedRow.stock;
            existing.minimum_order_quantity = mergedRow.minimum_order_quantity;
            existing.quantity_step_size = mergedRow.quantity_step_size;
            existing.total_allowed_quantity = mergedRow.total_allowed_quantity;
            existing.qty = nextQty;
        } else {
            cart.set(sig, { ...nextPayload, signature: sig });
        }
        renderCart();
        return true;
    }

    function changeQty(sig, delta) {
        const row = cart.get(sig);
        if (!row) return;
        const next = nextRowQuantity(row, delta);
        if (!next.ok) {
            return flashStockWarning(row.title, next.reason);
        }
        if (next.remove) cart.delete(sig);
        else row.qty = next.qty;
        if (cart.size === 0) {
            clearCartAdjustments();
        }
        renderCart();
    }

    function removeFromCart(sig) {
        cart.delete(sig);
        if (cart.size === 0) {
            clearCartAdjustments();
        }
        renderCart();
    }

    function flashStockWarning(title, detail) {
        const msg = typeof detail === 'string' && detail.trim()
            ? detail
            : `Only ${detail} in stock for ${title}.`;
        if (typeof Toast !== 'undefined' && Toast?.fire) {
            Toast.fire({ icon: 'warning', title: msg });
        } else {
            console.warn(msg);
        }
    }

    function renderCart({ revalidatePromo = true } = {}) {
        $cartList.innerHTML = '';
        let total = 0, count = 0;
        cart.forEach((row) => {
            const lineTotal = rowLineTotal(row);
            const addonUnitTotal = row.addons.reduce((sum, addon) => sum + Number(addon.price || 0), 0);
            const productUnitPrice = Number(row.unit_price || 0);
            const combinedUnitPrice = productUnitPrice + addonUnitTotal;
            total += lineTotal;
            count += row.qty;
            const addonLabel = row.addons.length
                ? `<div class="pos-cart-line-meta">${row.addons.map(a => {
                    const addonPrice = Number(a.price || 0);
                    const addonPriceText = addonPrice > 0 ? ` <span class="text-success">(${money(addonPrice)})</span>` : ' <span class="text-success">(free)</span>';
                    return `${escapeHtml(a.item_title)}${addonPriceText}`;
                }).join(', ')}</div>`
                : '';
            const variantLabel = row.variant_title && row.variant_title.toLowerCase() !== 'default'
                ? `<div class="pos-cart-line-meta">${escapeHtml(row.variant_title)}</div>`
                : '';
            const priceBreakdown = addonUnitTotal > 0
                ? `<div class="pos-cart-line-meta mt-1">Product ${money(productUnitPrice)} + Add-ons ${money(addonUnitTotal)} = ${money(combinedUnitPrice)}</div>
                   <div class="pos-cart-line-meta">${money(combinedUnitPrice)} × ${row.qty} = <strong class="text-body">${money(lineTotal)}</strong></div>`
                : `<div class="pos-cart-line-meta mt-1">${money(productUnitPrice)} × ${row.qty} = <strong class="text-body">${money(lineTotal)}</strong></div>`;
            const li = document.createElement('div');
            li.className = 'list-group-item d-flex align-items-start gap-2';
            li.innerHTML = `
                <div class="flex-fill min-width-0">
                    <div class="fw-medium text-truncate" style="max-width: 400px;" title="${escapeHtml(row.title)}">${escapeHtml(row.title)}</div>
                    ${variantLabel}
                    ${addonLabel}
                    ${priceBreakdown}
                </div>
                <div class="d-flex flex-column align-items-end gap-2">
                    <div class="d-flex align-items-center gap-1">
                        ${rowIsEditable(row) ? `
                        <button type="button" class="pos-cart-edit pos-edit" data-sig="${row.signature}" aria-label="Edit" title="Edit variant / add-ons">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1"/><path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z"/><path d="M16 5l3 3"/></svg>
                        </button>` : ''}
                        <button type="button" class="pos-cart-remove pos-remove" data-sig="${row.signature}" aria-label="Remove">×</button>
                    </div>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-secondary pos-qty-dec" data-sig="${row.signature}">−</button>
                        <span class="btn btn-outline-secondary disabled" style="min-width:2.5rem">${row.qty}</span>
                        <button type="button" class="btn btn-outline-secondary pos-qty-inc" data-sig="${row.signature}">+</button>
                    </div>
                </div>
            `;
            $cartList.appendChild(li);
        });

        $cartEmpty.classList.toggle('d-none', cart.size > 0);
        if ($cartMeta) $cartMeta.textContent = count === 0 ? 'empty' : `${count} ${count === 1 ? 'item' : 'items'}`;
        if ($checkoutBtn) $checkoutBtn.disabled = (cart.size === 0);

        const $clearBtn = document.getElementById('pos-cart-clear-btn');
        if ($clearBtn) $clearBtn.classList.toggle('d-none', cart.size === 0);
        const $holdBtn = document.getElementById('pos-hold-btn');
        if ($holdBtn) $holdBtn.disabled = (cart.size === 0);

        renderCartBreakdown();
        renderModalSummary();
        recomputeChange();
        // Keep the UPI deeplink amount in sync if the cashier edits the cart
        // while the checkout modal is already open.
        if (paymentState.method === 'upi' && $modalEl?.classList.contains('show')) renderUpiPanel();
        if (paymentState.method === 'split' && $modalEl?.classList.contains('show')) refreshSplitTotals();

        // Broadcast live cart state to the customer-facing display.
        cfdSchedulePush();
        persistCurrentDraft();

        if (revalidatePromo) {
            schedulePromoRevalidation();
        }
    }

    function renderCartBreakdown() {
        const $summary       = document.getElementById('pos-cart-summary');
        const $subtotalEl    = document.getElementById('pos-cart-subtotal');
        const $taxRows       = document.getElementById('pos-cart-tax-rows');
        const $savingsRow    = document.getElementById('pos-cart-savings-row');
        const $savingsEl     = document.getElementById('pos-cart-savings');
        const $savingsNote   = document.getElementById('pos-cart-savings-note');
        const $discRow       = document.getElementById('pos-cart-discount-row');
        const $discAmt       = document.getElementById('pos-cart-discount-amt');
        const $discLabel     = document.getElementById('pos-cart-discount-label');

        if (cart.size === 0) {
            clearCartAdjustments();
            $summary?.classList.add('d-none');
            $cartTotal.textContent = money(0);
            if ($modalTotal) $modalTotal.textContent = money(0);
            if ($modalTotalMoney) $modalTotalMoney.textContent = money(0);
            return;
        }
        $summary?.classList.remove('d-none');

        const b = cartBreakdown();
        // Subtotal shown EX-TAX so "Tax @ 18%" reads as a true 18% of the
        // subtotal line. Hyperlocal stores prices tax-inclusive, so the user
        // would otherwise see ₹100 + Tax 18% = ₹15.25 (not 18) which is
        // confusing. Receipt + checkout summary + CFD use the same shape.
        if ($subtotalEl) $subtotalEl.textContent = money(b.subtotalExTax);
        if ($cartTotal)  $cartTotal.textContent  = money(b.total);
        if ($modalTotal) $modalTotal.textContent = money(b.total);
        if ($modalTotalMoney) $modalTotalMoney.textContent = money(b.total);

        // Tax — one row per rate, showing "Tax @ X%: ₹Y" so the cashier
        // (and customer) can see exactly what tax is baked into the subtotal.
        if ($taxRows) {
            if (b.taxByRate && b.taxByRate.length) {
                $taxRows.innerHTML = b.taxByRate.map(t => `
                    <div class="pos-summary-line">
                        <span class="text-secondary small">Tax @ ${t.rate}%</span>
                        <span class="text-secondary small">${money(t.amount)}</span>
                    </div>`).join('');
            } else {
                $taxRows.innerHTML = '';
            }
        }

        if (b.savings > 0) {
            $savingsRow?.classList.remove('d-none');
            $savingsNote?.classList.remove('d-none');
            if ($savingsEl) {
                $savingsEl.textContent = `−${money(b.savings)}`;
            }
            if ($savingsNote) {
                $savingsNote.textContent = savingsMessage;
            }
        } else {
            $savingsRow?.classList.add('d-none');
            $savingsNote?.classList.add('d-none');
        }

        if (b.discountAmount > 0) {
            $discRow?.classList.remove('d-none');
            if ($discAmt)   $discAmt.textContent   = `−${money(b.discountAmount)}`;
            if ($discLabel) $discLabel.textContent = billDiscount.type === 'percent'
                ? `(${billDiscount.value}%)`
                : '(fixed)';
        } else {
            $discRow?.classList.add('d-none');
        }

        // Promo row
        const $promoRow  = document.getElementById('pos-cart-promo-row');
        const $promoAmt  = document.getElementById('pos-cart-promo-amt');
        const $promoCode = document.getElementById('pos-cart-promo-code');
        if (b.promoAmount > 0) {
            $promoRow?.classList.remove('d-none');
            if ($promoAmt) $promoAmt.textContent = `−${money(b.promoAmount)}`;
            if ($promoCode) $promoCode.textContent = b.promoCode ? `(${b.promoCode})` : '';
        } else {
            $promoRow?.classList.add('d-none');
        }

        // Wallet row
        const $walletRow = document.getElementById('pos-cart-wallet-row');
        const $walletAmt = document.getElementById('pos-cart-wallet-amt');
        if (b.walletApplied > 0) {
            $walletRow?.classList.remove('d-none');
            if ($walletAmt) $walletAmt.textContent = `−${money(b.walletApplied)}`;
        } else {
            $walletRow?.classList.add('d-none');
        }

        // Keep custom payment pane amount in sync
        if ($customAmount && paymentState.method === 'custom') {
            $customAmount.textContent = money(b.total);
        }
    }

    // Compact order preview inside the Complete-Sale modal so the cashier
    // can verify the cart contents without scrolling back to the side panel.
    function renderModalSummary() {
        if (!$modalSummary) return;
        if (cart.size === 0) {
            $modalSummary.innerHTML = '<div class="text-secondary small">Cart is empty.</div>';
            return;
        }
        const rows = [];
        cart.forEach((row) => {
            const lineTotal = rowLineTotal(row);
            const variantBit = row.variant_title && row.variant_title.toLowerCase() !== 'default'
                ? ` · ${escapeHtml(row.variant_title)}` : '';
            const addonBit = row.addons.length
                ? ` · ${row.addons.map(a => escapeHtml(a.item_title)).join(', ')}`
                : '';
            const addonUnitTotal = row.addons.reduce((sum, addon) => sum + Number(addon.price || 0), 0);
            const productUnitPrice = Number(row.unit_price || 0);
            rows.push(`
                <div class="pos-summary-row">
                    <div class="pos-summary-title">
                        <div class="text-truncate">${escapeHtml(row.title)} <span class="text-secondary small">× ${row.qty}</span></div>
                        ${(variantBit || addonBit) ? `<div class="pos-summary-meta text-truncate">${variantBit + addonBit}</div>` : ''}
                        <div class="pos-summary-meta text-truncate">
                            ${addonUnitTotal > 0
                                ? `Product ${money(productUnitPrice)} + Add-ons ${money(addonUnitTotal)}`
                                : `Product ${money(productUnitPrice)}`}
                        </div>
                    </div>
                    <div class="pos-summary-amt">${money(lineTotal)}</div>
                </div>
            `);
        });

        // Breakdown footer — same shape as the cart sidebar so the cashier
        // sees a consistent picture before clicking Complete.
        const b = cartBreakdown();
        const breakdownRows = [];
        // Match cart sidebar + receipt: ex-tax subtotal so Tax @ X% reads as
        // a true percentage of the subtotal line. (subtotalExTax + tax = subtotal)
        breakdownRows.push(`<div class="pos-summary-row"><div class="pos-summary-title text-secondary small">Subtotal</div><div class="pos-summary-amt">${money(b.subtotalExTax)}</div></div>`);
        if (b.taxByRate && b.taxByRate.length) {
            for (const t of b.taxByRate) {
                breakdownRows.push(`<div class="pos-summary-row"><div class="pos-summary-title text-secondary small">Tax @ ${t.rate}%</div><div class="pos-summary-amt text-secondary small">${money(t.amount)}</div></div>`);
            }
        }
        if (b.savings > 0) {
            breakdownRows.push(`<div class="pos-summary-row"><div class="pos-summary-title text-success small">You saved</div><div class="pos-summary-amt text-success small">−${money(b.savings)}</div></div>`);
            breakdownRows.push(`<div class="pos-summary-row"><div class="pos-summary-title text-secondary small">${escapeHtml(savingsMessage)}</div><div class="pos-summary-amt"></div></div>`);
        }
        if (b.discountAmount > 0) {
            const label = billDiscount.type === 'percent' ? `(${billDiscount.value}%)` : '(fixed)';
            breakdownRows.push(`<div class="pos-summary-row"><div class="pos-summary-title text-secondary small">Discount <span class="text-secondary small">${label}</span></div><div class="pos-summary-amt text-danger">−${money(b.discountAmount)}</div></div>`);
        }

        if (b.promoAmount > 0) {
            breakdownRows.push(`<div class="pos-summary-row"><div class="pos-summary-title text-secondary small">Promo <span class="text-secondary small">${promoBreakdownLabel()}</span></div><div class="pos-summary-amt text-danger">-${money(b.promoAmount)}</div></div>`);
        }

        $modalSummary.innerHTML = rows.join('') + '<div class="pos-summary-breakdown">' + breakdownRows.join('') + '</div>';
    }

    // ── customize modal: variant + addon picker ──

    function openCustomizeFor(productId) {
        const p = productIndex.get(productId);
        if (!p) return;
        customizeState.product = p;
        const defaultVariant = p.default_variant || p.variants[0];
        customizeState.variantId = defaultVariant?.store_product_variant_id ?? null;
        customizeState.groupChoices = {};
        customizeState.editingSignature = null;
        customizeState.editingQty = 1;

        const hasAddons = (p.variants || []).some(v => (v.addon_groups || []).length > 0);
        if (!p.has_variants && !hasAddons) {
            const v = defaultVariant;
            if (!v || v.stock <= 0) return;
            addCartRow({
                store_product_variant_id: v.store_product_variant_id,
                product_id: p.product_id,
                title: p.title,
                variant_title: v.title,
                unit_price: v.effective_price,
                regular_unit_price: v.price,
                tax_percent: p.tax_percent || 0,
                is_inclusive_tax: !!p.is_inclusive_tax,
                minimum_order_quantity: p.minimum_order_quantity,
                quantity_step_size: p.quantity_step_size,
                total_allowed_quantity: p.total_allowed_quantity,
                qty: p.minimum_order_quantity || 1,
                stock: v.stock,
                addons: [],
            });
            return;
        }

        renderCustomize();
        showModal($custModalEl);
    }

    function renderCustomize() {
        const p = customizeState.product;
        $custModalTitle.textContent = p.title;

        // Small thumbnail in the modal header — swaps when the cashier picks
        // a variant that has its own image, else falls back to product image,
        // else shows initials so the chip doesn't render empty.
        const selectedForHero = p.variants.find(v => v.store_product_variant_id === customizeState.variantId);
        const thumbSrc = selectedForHero?.image || p.image || null;
        if ($custModalThumb) {
            $custModalThumb.innerHTML = thumbSrc
                ? `<img src="${escapeHtml(thumbSrc)}" alt="${escapeHtml(p.title)}" loading="lazy" data-pos-image="1">`
                : noImageMarkup(p.title);
            hydratePosImages($custModalThumb);
        }

        let html = '';
        if (p.has_variants) {
            html += `<h4 class="mb-2">Choose size</h4>
                     <div class="form-selectgroup form-selectgroup-pills mb-3" id="pos-variant-group">`;
            for (const v of p.variants) {
                const oos = v.stock <= 0;
                html += `
                    <label class="form-selectgroup-item">
                        <input type="radio" name="pos-variant" value="${v.store_product_variant_id}"
                               class="form-selectgroup-input"
                               ${v.store_product_variant_id === customizeState.variantId ? 'checked' : ''}
                               ${oos ? 'disabled' : ''}>
                        <span class="form-selectgroup-label pos-variant-pill">
                            ${escapeHtml(v.title)}
                            <span class="text-secondary small ms-1">${money(v.effective_price)}</span>
                            ${oos
                                ? '<span class="text-danger small ms-1">(out)</span>'
                                : `<span class="text-secondary small ms-1">(${Math.max(0, Number(v.stock || 0))} in stock)</span>`}
                        </span>
                    </label>`;
            }
            html += `</div>`;
        }

        const selectedVariant = p.variants.find(v => v.store_product_variant_id === customizeState.variantId);
        const groups = selectedVariant?.addon_groups || [];
        if (groups.length) {
            html += `<h4 class="mb-2">Add-ons</h4>`;
            for (const g of groups) {
                const isSingle = g.selection_type === 'single';
                const inputType = isSingle ? 'radio' : 'checkbox';
                html += `
                    <div class="card mb-2">
                        <div class="card-header py-2 d-flex">
                            <strong>${escapeHtml(g.title)}</strong>
                            <span class="ms-2 text-secondary small">
                                ${isSingle ? 'Choose one' : 'Choose any'}
                                ${g.is_required ? ' · <span class="text-danger">Required</span>' : ''}
                            </span>
                        </div>
                        <div class="card-body py-2">
                `;
                for (const it of g.items) {
                    const checked = (customizeState.groupChoices[g.addon_group_id] || new Set()).has(it.addon_item_id);
                    const disabled = !it.is_available;
                    const priceLabel = Number(it.price) > 0 ? `+${money(it.price)}` : 'Free';
                    const priceClass = Number(it.price) > 0 ? '' : 'is-free';
                    html += `
                        <label class="pos-addon-row ${disabled ? 'is-disabled' : ''}">
                            <input class="form-check-input pos-addon-input"
                                   type="${inputType}"
                                   name="pos-addon-${g.addon_group_id}"
                                   value="${it.addon_item_id}"
                                   data-group-id="${g.addon_group_id}"
                                   data-price="${it.price}"
                                   ${checked ? 'checked' : ''}
                                   ${disabled ? 'disabled' : ''}>
                            <span class="pos-addon-label">${escapeHtml(it.title)}
                                ${disabled ? '<span class="text-secondary small ms-1">unavailable</span>' : ''}
                            </span>
                            <span class="pos-addon-price ${priceClass}">${priceLabel}</span>
                        </label>
                    `;
                }
                html += `</div></div>`;
            }
        }

        $custModalBody.innerHTML = html;
        recomputeCustomizeTotal();
    }

    function recomputeCustomizeTotal() {
        const p = customizeState.product;
        const selectedVariant = p.variants.find(v => v.store_product_variant_id === customizeState.variantId);
        let total = Number(selectedVariant?.effective_price || 0);

        let valid = true;
        for (const g of (selectedVariant?.addon_groups || [])) {
            const chosen = customizeState.groupChoices[g.addon_group_id] || new Set();
            if (g.is_required && chosen.size === 0) valid = false;
            if (g.selection_type === 'single' && chosen.size > 1) valid = false;
            chosen.forEach((itemId) => {
                const it = g.items.find(i => i.addon_item_id === itemId);
                if (it) total += Number(it.price || 0);
            });
        }

        if (selectedVariant && selectedVariant.stock <= 0) valid = false;

        $custLineTotal.textContent = money(total);
        $custAddBtn.disabled = !valid;
    }

    /**
     * Open the customize modal pre-populated with an existing cart row's
     * selections — used by the cart row edit pencil. On Save, the original
     * row is removed and the new row added (or merged if its signature
     * collides with another row, e.g. editing one of two duplicated lines
     * to match the other).
     */
    function editCartRow(signature) {
        const row = cart.get(signature);
        if (!row) return;
        const p = productIndex.get(row.product_id);
        if (!p) {
            // Product no longer in the loaded grid — bail to avoid an empty modal.
            // (Cashier can still adjust qty + remove from the row controls.)
            console.warn('Cannot edit row: product not in current view.');
            return;
        }

        customizeState.product = p;
        customizeState.variantId = row.store_product_variant_id;
        customizeState.editingSignature = signature;
        customizeState.editingQty = row.qty;

        // Rebuild groupChoices Sets from the row's addons.
        customizeState.groupChoices = {};
        for (const a of (row.addons || [])) {
            const gid = a.addon_group_id;
            if (!customizeState.groupChoices[gid]) customizeState.groupChoices[gid] = new Set();
            customizeState.groupChoices[gid].add(a.addon_item_id);
        }

        renderCustomize();
        // Re-label the primary action so the cashier knows they're editing.
        if ($custAddBtn) $custAddBtn.textContent = 'Save changes';
        showModal($custModalEl);
    }

    function commitCustomize() {
        const p = customizeState.product;
        const v = p.variants.find(v => v.store_product_variant_id === customizeState.variantId);
        if (!v) return;

        const addons = [];
        for (const g of (v.addon_groups || [])) {
            const chosen = customizeState.groupChoices[g.addon_group_id] || new Set();
            chosen.forEach((itemId) => {
                const it = g.items.find(i => i.addon_item_id === itemId);
                if (it) addons.push({
                    addon_group_id: g.addon_group_id,
                    addon_item_id: itemId,
                    group_title: g.title,
                    item_title: it.title,
                    price: Number(it.price || 0),
                });
            });
        }

        // If we're editing an existing row, drop it first so the resulting
        // signature can either replace it cleanly OR merge with another row.
        const isEditing = !!customizeState.editingSignature;
        const carryQty  = isEditing ? customizeState.editingQty : 1;
        if (isEditing) cart.delete(customizeState.editingSignature);

        addCartRow({
            store_product_variant_id: v.store_product_variant_id,
            product_id: p.product_id,
            title: p.title,
            variant_title: v.title,
            unit_price: Number(v.effective_price),
            regular_unit_price: Number(v.price),
            tax_percent: p.tax_percent || 0,
            is_inclusive_tax: !!p.is_inclusive_tax,
            minimum_order_quantity: p.minimum_order_quantity,
            quantity_step_size: p.quantity_step_size,
            total_allowed_quantity: p.total_allowed_quantity,
            qty: carryQty,
            stock: v.stock,
            addons,
        });

        // Reset editing state + restore the default button label.
        customizeState.editingSignature = null;
        customizeState.editingQty = 1;
        if ($custAddBtn) $custAddBtn.textContent = 'Add to cart';
        hideModal($custModalEl);
    }

    // ── customer step ──

    function setCustomerMode(mode) {
        customerState.mode = mode;
        customerState.customer_id = null;
        customerState.attached_name = null;
        $custSelected?.classList.add('d-none');
        if (custTomSelect) { custTomSelect.clear(); custTomSelect.clearOptions(); }
        if ($newForm) $newForm.classList.add('d-none');
        if ($newResult) $newResult.innerHTML = '';
        $custTabs?.querySelectorAll('.nav-link').forEach(b => b.classList.toggle('active', b.dataset.mode === mode));
        $panes.forEach(p => p.classList.toggle('d-none', p.dataset.pane !== mode));

        // Wallet block is only meaningful for the existing-customer pane.
        $walletBlock?.classList.add('d-none');
        walletState.balance = 0;
        walletState.applied = 0;
        if ($walletInput) $walletInput.value = '';
        renderCart();
        if (typeof renderCustomerChip === 'function') renderCustomerChip();
    }

    // Tom Select for existing-customer search (uses UserApiController@search)
    if ($custSearch && window.TomSelect) {
        custTomSelect = new TomSelect($custSearch, {
            valueField: 'id',
            labelField: 'text',
            searchField: 'text',
            copyClassesToDropdown: false,
            dropdownParent: 'body',
            load: function (query, callback) {
                if (query.length < 2) return callback();
                axios.get(usersSearchUrl, { params: { search: query, type: 'customer' } })
                    .then(res => {
                        // Drop options from previous searches (keep the selected one) so
                        // overlapping result sets don't accumulate into duplicate rows.
                        this.clearOptions(item => item === this.items[0]);
                        callback(res.data || []);
                    })
                    .catch(() => callback());
            },
            render: {
                option: function (data, escape) {
                    return '<div><div class="fw-medium">' + escape(data.text) + '</div></div>';
                },
                item: function (data, escape) {
                    return '<div>' + escape(data.text) + '</div>';
                }
            },
            onChange: function (value) {
                if (!value) return;
                const opt = custTomSelect.options[value];
                if (opt) pickExistingCustomer(parseInt(opt.id, 10), opt.text);
            }
        });
    }

    function pickExistingCustomer(id, name) {
        customerState.customer_id = id;
        customerState.attached_name = name;
        $custSelected.classList.remove('d-none');
        $custSelected.textContent = `Selected: ${name} (id ${id})`;
        schedulePromoRevalidation();
        loadWalletForCustomer(id).finally(() => {
            if (typeof renderCustomerChip === 'function') renderCustomerChip();
        });
        if (typeof renderCustomerChip === 'function') renderCustomerChip();
    }

    async function loadWalletForCustomer(customerId) {
        if (!$walletBlock) return;
        try {
            const url = walletLookupUrlT.replace('__ID__', encodeURIComponent(customerId));
            const res = await axios.get(url);
            walletState.balance = Number(res?.data?.data?.balance || 0);
            walletState.applied = 0;

            // Backfill the name if the caller didn't have it (e.g. resuming a
            // held sale snapshotted before names were stored) so the chip and
            // selected banner never fall back to a bare customer id.
            const fetchedName = res?.data?.data?.name;
            if (fetchedName && !customerState.attached_name && customerState.customer_id === (parseInt(customerId, 10) || null)) {
                customerState.attached_name = fetchedName;
                if ($custSelected && !$custSelected.classList.contains('d-none')) {
                    $custSelected.textContent = `Selected: ${fetchedName} (id ${customerId})`;
                }
                if (typeof renderCustomerChip === 'function') renderCustomerChip();
            }
            if ($walletBalance) $walletBalance.textContent = money(walletState.balance);
            $walletBlock.classList.remove('d-none');
            if ($walletInput) $walletInput.value = '';
            if ($walletHint) { $walletHint.textContent = 'Capped at customer\'s balance and the remaining bill total.'; $walletHint.classList.remove('text-danger'); }
            renderCart();
        } catch (_) {
            $walletBlock.classList.add('d-none');
            walletState.balance = 0;
            walletState.applied = 0;
        }
    }

    function clearWallet() {
        walletState.applied = 0;
        if ($walletInput) $walletInput.value = '';
        renderCart();
    }

    async function registerNewCustomer() {
        $newResult.innerHTML = '';
        const name   = (document.getElementById('pos-new-name').value || '').trim();
        const cc     = (document.getElementById('pos-new-cc').value || '').trim();
        const mobile = (document.getElementById('pos-new-mobile').value || '').trim();
        const email  = (document.getElementById('pos-new-email').value || '').trim();
        if (!name || !cc || !mobile) {
            $newResult.innerHTML = '<div class="alert alert-warning mb-0">Name, country code and mobile are required.</div>';
            return;
        }
        try {
            $newRegBtn.disabled = true;
            const res = await axios.post(customersRegisterUrl, { name, country_code: cc, mobile, email: email || null });
            const c = res?.data?.data?.customer;
            if (c?.id) {
                pickExistingCustomer(c.id, c.name || name);
                collapseRegisterForm();
            }
        } catch (err) {
            const status = err?.response?.status;
            const data = err?.response?.data;
            if (status === 409 && data?.data?.customer) {
                const e = data.data.customer;
                pickExistingCustomer(e.id, e.name || name);
                collapseRegisterForm();
            } else {
                $newResult.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(data?.message || 'Registration failed.')}</div>`;
            }
        } finally {
            $newRegBtn.disabled = false;
        }
    }

    function collapseRegisterForm() {
        if ($newForm) $newForm.classList.add('d-none');
        ['pos-new-name','pos-new-cc','pos-new-mobile','pos-new-email'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        if ($newResult) $newResult.innerHTML = '';
        if (custTomSelect) { custTomSelect.clear(); custTomSelect.clearOptions(); }
    }

    // ── Customer chip in cart header + quick-attach modal ──
    // The chip is a single source of truth for the cashier on whether a
    // customer is attached to the in-progress sale. It writes to the same
    // customerState the checkout modal's Customer pane uses, so the two
    // surfaces always agree.

    const $custChipBtn   = document.getElementById('pos-cart-customer-btn');
    const $custChipName  = document.getElementById('pos-cart-customer-name');
    const $custChipMeta  = document.getElementById('pos-cart-customer-meta');
    const $custChipX     = document.getElementById('pos-cart-customer-detach');
    const $attachModal   = document.getElementById('pos-attach-customer-modal');
    const $attachSearch  = document.getElementById('pos-attach-search');
    const $attachResults = document.getElementById('pos-attach-results');
    const $attachNewToggle = document.getElementById('pos-attach-new-toggle');
    const $attachNewForm = document.getElementById('pos-attach-new-form');
    const $attachNewSubmit = document.getElementById('pos-attach-new-submit');
    const $attachNewResult = document.getElementById('pos-attach-new-result');

    function renderCustomerChip() {
        if (!$custChipBtn) return;
        const id = customerState.customer_id;
        if (id) {
            $custChipBtn.classList.add('is-attached');
            const name = customerState.attached_name || `Customer #${id}`;
            $custChipName.textContent = name;
            const balance = walletState.balance > 0 ? `${money(walletState.balance)} wallet` : '';
            if (balance) {
                $custChipMeta.textContent = balance;
                $custChipMeta.classList.remove('d-none');
            } else {
                $custChipMeta.classList.add('d-none');
            }
            $custChipX.classList.remove('d-none');
        } else {
            $custChipBtn.classList.remove('is-attached');
            $custChipName.textContent = 'Walk-in customer';
            $custChipMeta.classList.add('d-none');
            $custChipX.classList.add('d-none');
        }
    }

    /**
     * Attach a customer from any path (toolbar chip, register-new flow).
     * Routes through the existing customerState + checkout pane so the
     * checkout modal will already have the right state when it opens.
     */
    function attachCustomer(id, name) {
        customerState.customer_id   = parseInt(id, 10) || null;
        customerState.attached_name = name || null;
        customerState.mode          = 'existing';
        // Sync the checkout customer pane so its tabs + selected-banner reflect
        // the attached customer next time it opens. setCustomerMode resets the
        // id, so we set state again afterwards.
        try { setCustomerMode('existing'); } catch (_) {}
        customerState.customer_id   = parseInt(id, 10) || null;
        customerState.attached_name = name || null;
        if ($custSelected) {
            $custSelected.textContent = `Selected: ${name || 'Customer'} (id ${id})`;
            $custSelected.classList.remove('d-none');
        }
        schedulePromoRevalidation();
        loadWalletForCustomer(customerState.customer_id).finally(renderCustomerChip);
        renderCustomerChip();
    }

    function detachCustomer() {
        customerState.attached_name = null;
        // setCustomerMode('walkin') already nulls customer_id and resets wallet.
        setCustomerMode('walkin');
        renderCustomerChip();
    }

    const attachSearchDebounced = debounce(async () => {
        if (!$attachResults) return;
        const q = ($attachSearch?.value || '').trim();
        $attachResults.innerHTML = '';
        if (q.length < 2) {
            $attachResults.innerHTML = '<div class="list-group-item text-secondary small">Type at least 2 characters to search.</div>';
            return;
        }
        try {
            const res = await axios.get(usersSearchUrl, { params: { search: q, type: 'customer' } });
            const list = res?.data || [];
            if (!list.length) {
                $attachResults.innerHTML = '<div class="list-group-item text-secondary small">No matches. Register them below.</div>';
                return;
            }
            const frag = document.createDocumentFragment();
            list.forEach(c => {
                const a = document.createElement('button');
                a.type = 'button';
                a.className = 'list-group-item list-group-item-action pos-attach-pick';
                a.dataset.customerId = c.id;
                a.dataset.customerName = c.text || '';
                a.innerHTML = `<div class="fw-medium">${escapeHtml(c.text || 'Customer')}</div>`;
                frag.appendChild(a);
            });
            $attachResults.appendChild(frag);
        } catch {
            $attachResults.innerHTML = '<div class="list-group-item text-danger small">Search failed.</div>';
        }
    }, 200);

    $custChipBtn?.addEventListener('click', (e) => {
        // Detach × takes precedence — bubbles up from the inner span.
        if (e.target.closest('#pos-cart-customer-detach') && customerState.customer_id) {
            detachCustomer();
            return;
        }
        // Open the attach modal. Pre-clear last search so it's a fresh field.
        if ($attachSearch) $attachSearch.value = '';
        if ($attachResults) $attachResults.innerHTML = '';
        if ($attachNewForm) $attachNewForm.classList.add('d-none');
        if ($attachNewResult) $attachNewResult.innerHTML = '';
        showModal($attachModal);
        setTimeout(() => $attachSearch?.focus(), 200);
    });

    $attachSearch?.addEventListener('input', attachSearchDebounced);

    $attachResults?.addEventListener('click', (e) => {
        const btn = e.target.closest('.pos-attach-pick');
        if (!btn) return;
        const id   = parseInt(btn.dataset.customerId, 10) || 0;
        const name = btn.dataset.customerName || '';
        if (!id) return;
        attachCustomer(id, name);
        hideModal($attachModal);
    });

    $attachNewToggle?.addEventListener('click', () => {
        $attachNewForm?.classList.toggle('d-none');
        if (!$attachNewForm?.classList.contains('d-none')) {
            // Carry over any digits the cashier already typed in the search.
            const q = ($attachSearch?.value || '').trim();
            if (/^[0-9 +\-]+$/.test(q)) {
                document.getElementById('pos-attach-new-mobile').value = q.replace(/[^0-9]/g, '');
            }
        }
    });

    $attachNewSubmit?.addEventListener('click', async () => {
        if (!$attachNewResult) return;
        $attachNewResult.innerHTML = '';
        const name   = (document.getElementById('pos-attach-new-name').value || '').trim();
        const cc     = (document.getElementById('pos-attach-new-cc').value || '').trim();
        const mobile = (document.getElementById('pos-attach-new-mobile').value || '').trim();
        const email  = (document.getElementById('pos-attach-new-email').value || '').trim();
        if (!name || !cc || !mobile) {
            $attachNewResult.innerHTML = '<div class="alert alert-warning mb-0">Name, country code and mobile are required.</div>';
            return;
        }
        $attachNewSubmit.disabled = true;
        try {
            const res = await axios.post(customersRegisterUrl, { name, country_code: cc, mobile, email: email || null });
            const c = res?.data?.data?.customer;
            if (c?.id) {
                attachCustomer(c.id, c.name);
                hideModal($attachModal);
            }
        } catch (err) {
            const data   = err?.response?.data;
            const status = err?.response?.status;
            if (status === 409 && data?.data?.customer) {
                const e = data.data.customer;
                attachCustomer(e.id, e.name);
                hideModal($attachModal);
            } else {
                $attachNewResult.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(data?.message || 'Registration failed.')}</div>`;
            }
        } finally {
            $attachNewSubmit.disabled = false;
        }
    });

    // Initial paint of the chip (before any cart activity).
    renderCustomerChip();

    // ── cash calculator ──

    function recomputeChange() {
        const total = cartBreakdown().total;
        const received = parseFloat($cashReceived?.value || '0') || 0;
        const diff = received - total;
        if (received <= 0) {
            $changeDue.textContent = money(0);
            $changeDue.classList.remove('pos-change-positive');
            $cashShort.classList.add('d-none');
        } else if (diff < 0) {
            $changeDue.textContent = money(0);
            $changeDue.classList.remove('pos-change-positive');
            $cashShort.classList.remove('d-none');
            $cashShortAmt.textContent = money(-diff);
        } else {
            $changeDue.textContent = money(diff);
            $changeDue.classList.add('pos-change-positive');
            $cashShort.classList.add('d-none');
        }
    }

    // ── UPI / Online QR ──

    function currentStorePaymentConfig() {
        const id = parseInt($store?.value, 10);
        return storePaymentConfig[id] || {};
    }

    // Refresh the UPI tab availability whenever the store changes. If the
    // active store has no VPA configured, the tab is greyed out and any
    // previous selection of UPI flips back to Cash.
    function refreshPaymentMethodAvailability() {
        const cfg = currentStorePaymentConfig();
        const upiOk = !!(cfg && cfg.upi_vpa && cfg.upi);
        const builtIn = { cash: cfg.cash !== false, upi: upiOk, online: !!cfg.online_qr, split: !!cfg.split };

        // Show/hide built-in tabs
        $payTabs?.querySelectorAll('[data-pm]').forEach(function (li) {
            const key = li.dataset.pm;
            if (key in builtIn) li.classList.toggle('d-none', !builtIn[key]);
        });
        if ($payTabUpi) {
            $payTabUpi.classList.toggle('is-disabled', !upiOk);
            $payTabUpi.title = upiOk ? '' : 'Configure UPI VPA in store settings to enable.';
        }

        // Remove old custom tabs
        $payTabs?.querySelectorAll('[data-pm^="custom_"]').forEach(function (li) { li.remove(); });

        // Add custom method tabs
        const customs = cfg.custom_methods || [];
        customs.forEach(function (cm, i) {
            const li = document.createElement('li');
            li.className = 'nav-item';
            li.dataset.pm = 'custom_' + i;
            const btn = document.createElement('button');
            btn.className = 'nav-link';
            btn.type = 'button';
            btn.dataset.method = 'custom';
            btn.dataset.customIndex = String(i);
            btn.textContent = (cm.icon ? cm.icon + ' ' : '') + cm.name;
            li.appendChild(btn);
            $payTabs.appendChild(li);
        });

        // If active method is now hidden, fall back to first visible
        const activeKey = paymentState.method === 'custom' ? 'custom_' + (paymentState._customIdx || 0) : paymentState.method;
        const activeLi = $payTabs?.querySelector('[data-pm="' + activeKey + '"]');
        if (!activeLi || activeLi.classList.contains('d-none')) {
            const first = $payTabs?.querySelector('[data-pm]:not(.d-none)');
            if (first) {
                const fbBtn = first.querySelector('[data-method]');
                if (fbBtn) {
                    if (fbBtn.dataset.method === 'custom') {
                        activateCustomMethod(parseInt(fbBtn.dataset.customIndex, 10));
                    } else {
                        setPaymentMethod(fbBtn.dataset.method);
                    }
                }
            }
        }
    }

    function setPaymentMethod(method) {
        const cfg = currentStorePaymentConfig();
        if (method === 'upi' && !cfg.upi_vpa) method = 'cash';

        // Switching AWAY from an active online session implicitly cancels it
        if (paymentState.method === 'online' && method !== 'online' && onlineSession.token) {
            cancelOnlineSession({ silent: true });
        }

        paymentState.method = method;
        if (method !== 'custom') paymentState.customMethodName = null;

        // Highlight the active tab
        $payTabs?.querySelectorAll('.nav-link').forEach(function (b) {
            if (method === 'custom') {
                b.classList.remove('active');
            } else {
                b.classList.toggle('active', b.dataset.method === method);
            }
        });

        // Show the correct pane (custom methods share the 'custom' pane)
        const paneKey = method;
        $payPanes.forEach(p => p.classList.toggle('d-none', p.dataset.pane !== paneKey));
        if ($customPane) $customPane.classList.toggle('d-none', method !== 'custom');

        if (method === 'upi')    renderUpiPanel();
        if (method === 'online') resetOnlineIdle();
        if (method === 'split')  resetSplitIdle();
        updateCompleteButtonLabel();
    }

    function activateCustomMethod(idx) {
        const cfg = currentStorePaymentConfig();
        const customs = cfg.custom_methods || [];
        const cm = customs[idx];
        if (!cm) return;

        setPaymentMethod('custom');
        paymentState.customMethodName = cm.name;
        paymentState._customIdx = idx;

        // Highlight the correct custom tab
        $payTabs?.querySelectorAll('.nav-link').forEach(function (b) {
            if (b.dataset.method === 'custom') {
                b.classList.toggle('active', parseInt(b.dataset.customIndex, 10) === idx);
            }
        });

        // Fill the custom pane
        if ($customIcon) $customIcon.textContent = cm.icon || '💳';
        if ($customLabel) $customLabel.textContent = cm.name;
        if ($customInstructions) {
            $customInstructions.textContent = cm.instructions || '';
            $customInstructions.classList.toggle('d-none', !cm.instructions);
        }
        if ($customAmount) $customAmount.textContent = money(cartBreakdown().total);
    }

    // UPI deeplink format per NPCI spec — works in every UPI app on Android/iOS.
    function buildUpiDeeplink(amount) {
        const cfg = currentStorePaymentConfig();
        if (!cfg.upi_vpa) return null;
        const params = new URLSearchParams();
        params.set('pa', cfg.upi_vpa);
        params.set('pn', cfg.upi_payee_name || cfg.upi_vpa);
        params.set('am', Number(amount || 0).toFixed(2));
        params.set('cu', (cfg.currency_code || 'INR').toUpperCase());
        params.set('tn', `POS sale`);
        return `upi://pay?${params.toString()}`;
    }

    function renderUpiPanel() {
        const cfg = currentStorePaymentConfig();
        if (!cfg.upi_vpa) {
            $upiNotConfig?.classList.remove('d-none');
            $upiActive?.classList.add('d-none');
            return;
        }
        $upiNotConfig?.classList.add('d-none');
        $upiActive?.classList.remove('d-none');

        $upiPayee.textContent  = cfg.upi_payee_name || cfg.upi_vpa;
        $upiVpa.textContent    = cfg.upi_vpa;
        const total = cartBreakdown().total;
        $upiAmount.textContent = money(total);

        const deeplink = buildUpiDeeplink(total);
        if (!deeplink || !window.qrcode) {
            $upiQr.innerHTML = '<div class="text-secondary small">QR unavailable</div>';
            return;
        }
        renderQrInto($upiQr, deeplink);
    }

    function updateCompleteButtonLabel() {
        if (!$completeBtn) return;
        if (paymentState.method === 'online' || paymentState.method === 'split') {
            $completeBtn.classList.add('d-none');
            return;
        }
        $completeBtn.classList.remove('d-none');
        let label = 'Complete sale';
        if (paymentState.method === 'upi' || paymentState.method === 'custom') label = 'Mark as paid';
        $completeBtn.textContent = label;
    }

    // ── Online (Razorpay) session ──

    function resetOnlineIdle() {
        clearOnlineTimers();
        onlineSession.token = null;
        onlineSession.expiresAt = null;
        $onlineIdle?.classList.remove('d-none');
        $onlineActive?.classList.add('d-none');
        $onlineError?.classList.add('d-none');
        if ($onlineError) $onlineError.textContent = '';
    }

    function clearOnlineTimers() {
        if (onlineSession.pollTimer)      { clearInterval(onlineSession.pollTimer);      onlineSession.pollTimer = null; }
        if (onlineSession.countdownTimer) { clearInterval(onlineSession.countdownTimer); onlineSession.countdownTimer = null; }
    }

    function buildOnlineSessionPayload() {
        const total = cartBreakdown().total;
        const payload = {
            store_id: parseInt($store.value, 10),
            amount:   total,
            items: buildCartItemsPayload(),
            order_note: ($orderNote?.value || '').trim() || null,
        };

        if (customerState.mode === 'walkin') {
            const wn = (document.getElementById('pos-walkin-name').value || '').trim();
            const wm = (document.getElementById('pos-walkin-mobile').value || '').trim();
            const wc = (document.getElementById('pos-walkin-cc')?.value || '').trim();
            if (wn) payload.walkin_customer_name = wn;
            if (wm) payload.walkin_customer_mobile = wm;
            if (wc) payload.walkin_customer_country_code = wc;
        } else if (customerState.customer_id) {
            payload.customer_id = customerState.customer_id;
        }

        if (billDiscount.type && billDiscount.value > 0) {
            payload.discount_type  = billDiscount.type;
            payload.discount_value = Number(billDiscount.value);
        }
        return payload;
    }

    async function generateOnlineSession() {
        if (cart.size === 0) return;
        if (customerState.mode !== 'walkin' && !customerState.customer_id) {
            $onlineError.textContent = customerState.mode === 'existing'
                ? 'Pick a customer first or use Walk-in.'
                : 'Register the new customer first.';
            $onlineError.classList.remove('d-none');
            return;
        }

        $onlineError?.classList.add('d-none');
        $onlineGenerate.disabled = true;
        $onlineGenerate.textContent = 'Creating QR…';

        try {
            const res = await axios.post(paymentSessionsUrl, buildOnlineSessionPayload());
            const s = res?.data?.data?.session;
            if (!s?.token) throw new Error('Unexpected response');

            onlineSession.token     = s.token;
            onlineSession.expiresAt = s.expires_at ? new Date(s.expires_at) : null;

            $onlineIdle.classList.add('d-none');
            $onlineActive.classList.remove('d-none');

            // QR encodes the absolute payment URL; phone camera scanners open
            // it directly in the customer's browser.
            $onlineUrl.value = s.payment_url || '';
            $onlineAmount.textContent = money(s.amount);
            renderOnlineQr(s.payment_url);
            tickCountdown();
            onlineSession.countdownTimer = setInterval(tickCountdown, 1000);
            startOnlinePolling();
        } catch (err) {
            const msg = err?.response?.data?.message || err?.message || 'Could not create payment session.';
            $onlineError.textContent = msg;
            $onlineError.classList.remove('d-none');
        } finally {
            $onlineGenerate.disabled = false;
            $onlineGenerate.textContent = 'Generate payment QR';
        }
    }

    function renderOnlineQr(payUrl) { renderQrInto($onlineQr, payUrl); }

    function tickCountdown() {
        const cd = formatCountdown(onlineSession.expiresAt);
        if (!cd) return;
        if ($onlineCountdown) $onlineCountdown.textContent = cd.text;
        if (cd.remaining <= 0) {
            clearOnlineTimers();
            $onlineStatusTxt.textContent = 'Session expired — generate a new QR.';
            const dot = $onlineActive?.querySelector('.status-dot');
            dot?.classList.remove('pulse');
            dot?.classList.add('is-failed');
        }
    }

    function startOnlinePolling() {
        clearInterval(onlineSession.pollTimer);
        onlineSession.pollTimer = setInterval(pollOnlineStatus, 3000);
        // Also do a quick first poll so the UI reacts fast on a fast scan.
        setTimeout(pollOnlineStatus, 800);
    }

    async function pollOnlineStatus() {
        if (!onlineSession.token) return;
        try {
            const url = sessionStatusUrlT.replace('__TOKEN__', encodeURIComponent(onlineSession.token));
            const res = await axios.get(url);
            const s = res?.data?.data?.session;
            if (!s) return;

            if (s.status === 'paid') {
                clearOnlineTimers();
                onPaymentReceived(s);
            } else if (s.status === 'failed' || s.status === 'expired' || s.status === 'cancelled') {
                clearOnlineTimers();
                $onlineStatusTxt.textContent = `Session ${s.status}` + (s.failure_reason ? ` — ${s.failure_reason}` : '');
                const dot = $onlineActive?.querySelector('.status-dot');
                dot?.classList.remove('pulse');
                dot?.classList.add('is-failed');
            }
        } catch (_) {
            // Network blip — keep polling.
        }
    }

    function onPaymentReceived(session) {
        $onlineStatusTxt.textContent = 'Payment received!';
        const dot = $onlineActive?.querySelector('.status-dot');
        dot?.classList.remove('pulse');
        dot?.classList.add('is-paid');

        const orderId = session.order_id;
        const receiptUrl = orderId ? receiptUrlTemplate.replace('__ID__', String(orderId)) : null;
        if (receiptUrl) try { window.open(receiptUrl, '_blank', 'noopener'); } catch (_) {}

        resetSaleState();

        const total = Number(session.amount || 0);
        // Reset session bookkeeping BEFORE hiding modal so the beforeunload
        // guard doesn't trigger when the modal close animation fires.
        onlineSession.token = null;
        onlineSession.expiresAt = null;

        setTimeout(() => {
            hideModal($modalEl);
            setPaymentMethod('cash');
            showSaleSuccess(orderId, total, 0, receiptUrl);
            fetchProducts({ append: false });
        }, 700); // brief pause so cashier sees the success state
    }

    async function cancelOnlineSession({ silent = false } = {}) {
        const token = onlineSession.token;
        clearOnlineTimers();
        onlineSession.token = null;
        onlineSession.expiresAt = null;
        resetOnlineIdle();
        if (!token) return;
        try {
            const url = sessionCancelUrlT.replace('__TOKEN__', encodeURIComponent(token));
            await axios.post(url);
        } catch (_) {
            if (!silent) console.warn('Failed to cancel session server-side.');
        }
    }

    // Tab-close warning — user explicitly asked for this in the spec.
    window.addEventListener('beforeunload', (e) => {
        if (onlineSession.token) {
            e.preventDefault();
            e.returnValue = ''; // required for Chrome to show the prompt
            return '';
        }
    });

    // Reset the customize modal back to "add to cart" mode whenever it's
    // dismissed — so the next open from the product grid is fresh.
    if ($custModalEl) {
        $custModalEl.addEventListener('hidden.bs.modal', () => {
            customizeState.editingSignature = null;
            customizeState.editingQty = 1;
            if ($custAddBtn) $custAddBtn.textContent = 'Add to cart';
        });
    }

    // If the cashier dismisses the checkout modal while a session is active
    // (Cancel button, X, etc.) cancel server-side so we don't leak rows.
    if ($modalEl) {
        $modalEl.addEventListener('hidden.bs.modal', () => {
            if (onlineSession.token) cancelOnlineSession({ silent: true });
        });
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
            // Tabler/BS5 events fire either way, but jQuery namespace is what's
            // actually loaded here so attach via jQuery too as a belt-and-braces.
            window.jQuery($modalEl).on('hidden.bs.modal', () => {
                if (onlineSession.token) cancelOnlineSession({ silent: true });
            });
        }
    }

    // ── Split tender (Cash + Online QR) ──

    const splitState = { cash: 0 };

    function refreshSplitTotals() {
        const total = cartBreakdown().total;
        if ($splitTotalEl) $splitTotalEl.textContent = money(total);
        const cash = Math.max(0, parseFloat($splitCashInput?.value || '0') || 0);
        splitState.cash = cash;
        const online = Math.max(0, total - cash);
        if ($splitOnlineInput) $splitOnlineInput.value = online > 0 ? online.toFixed(2) : '';
        if ($splitGenBtn) {
            $splitGenBtn.disabled = !(cash >= 0 && online > 0 && total > 0 && cash <= total);
        }
        // Inline guidance.
        if ($splitError) {
            if (cash > total) {
                $splitError.textContent = 'Cash portion is greater than the bill total.';
                $splitError.classList.remove('d-none');
            } else if (online <= 0 && total > 0) {
                $splitError.textContent = 'Online portion must be greater than zero — use the Cash tab if customer is paying entirely in cash.';
                $splitError.classList.remove('d-none');
            } else {
                $splitError.classList.add('d-none');
                $splitError.textContent = '';
            }
        }
    }

    function resetSplitIdle() {
        clearOnlineTimers();
        onlineSession.token = null;
        onlineSession.expiresAt = null;
        $splitIdle?.classList.remove('d-none');
        $splitActive?.classList.add('d-none');
        if ($splitCashInput) $splitCashInput.value = '';
        if ($splitOnlineInput) $splitOnlineInput.value = '';
        splitState.cash = 0;
        refreshSplitTotals();
    }

    async function generateSplitSession() {
        if (cart.size === 0) return;
        const total = cartBreakdown().total;
        const cash = splitState.cash;
        const online = total - cash;
        if (online <= 0 || cash < 0 || cash > total) return;

        if (customerState.mode !== 'walkin' && !customerState.customer_id) {
            $splitError.textContent = 'Pick a customer first or use Walk-in.';
            $splitError.classList.remove('d-none');
            return;
        }
        $splitError?.classList.add('d-none');
        $splitGenBtn.disabled = true;
        $splitGenBtn.textContent = 'Creating QR…';

        const payload = {
            ...buildOnlineSessionPayload(),
            // Override: amount becomes ONLINE portion only — Razorpay charges this.
            amount: round2(online),
            cash_portion: round2(cash),
        };

        try {
            const res = await axios.post(paymentSessionsUrl, payload);
            const s = res?.data?.data?.session;
            if (!s?.token) throw new Error('Unexpected response');

            onlineSession.token     = s.token;
            onlineSession.expiresAt = s.expires_at ? new Date(s.expires_at) : null;

            $splitIdle.classList.add('d-none');
            $splitActive.classList.remove('d-none');
            $splitCashShown.textContent   = money(cash);
            $splitOnlineShown.textContent = money(online);

            // QR encodes the public payment URL — same as online tab.
            renderSplitQr(s.payment_url);
            tickSplitCountdown();
            onlineSession.countdownTimer = setInterval(tickSplitCountdown, 1000);
            startOnlinePolling(); // reuses online pollOnlineStatus → fires onPaymentReceived which clears state
        } catch (err) {
            const msg = err?.response?.data?.message || err?.message || 'Could not create payment session.';
            $splitError.textContent = msg;
            $splitError.classList.remove('d-none');
        } finally {
            $splitGenBtn.disabled = false;
            $splitGenBtn.textContent = 'Collect cash & generate QR for online';
        }
    }

    function renderSplitQr(payUrl) { renderQrInto($splitQr, payUrl); }

    function tickSplitCountdown() {
        const cd = formatCountdown(onlineSession.expiresAt);
        if (!cd) return;
        if ($splitCountdown) $splitCountdown.textContent = cd.text;
        if (cd.remaining <= 0) {
            clearOnlineTimers();
            $splitStatusTxt.textContent = 'Session expired — generate a new QR.';
            $splitActive?.querySelector('.status-dot')?.classList.add('is-failed');
        }
    }

    $splitCashInput?.addEventListener('input', refreshSplitTotals);
    $splitGenBtn?.addEventListener('click', generateSplitSession);
    $splitCancelBtn?.addEventListener('click', () => {
        cancelOnlineSession();
        setPaymentMethod('cash');
    });

    function applyQuickTender(kind) {
        const total = cartBreakdown().total;
        if (total <= 0) return;
        let v = parseFloat($cashReceived?.value || '0') || 0;
        if (kind === 'exact')         v = total;
        else if (kind === 'round-up') v = Math.ceil(total / 100) * 100;
        else                          v = (v < total ? total : v) + parseFloat(kind);
        $cashReceived.value = v.toFixed(2);
        recomputeChange();
    }

    async function completeSale() {
        if (cart.size === 0) return;
        $checkoutError.classList.add('d-none');
        $checkoutError.textContent = '';

        if (promoState.code) {
            const promoStillValid = await revalidateAppliedPromo({ showRemoval: false });
            if (!promoStillValid) {
                $checkoutError.textContent = 'The promo code no longer matches this sale and was removed. The cart items were kept. Review the total, then complete the sale again.';
                $checkoutError.classList.remove('d-none');
                return;
            }
        }

        const total = cartBreakdown().total;

        let received = 0;
        if (paymentState.method === 'cash') {
            received = parseFloat($cashReceived?.value || '0') || 0;
            if (received > 0 && received < total) {
                $checkoutError.textContent = `Cash received (${money(received)}) is less than total (${money(total)}).`;
                $checkoutError.classList.remove('d-none');
                return;
            }
        }

        const payload = {
            store_id: parseInt($store.value, 10),
            payment_method: paymentState.method === 'custom' ? 'custom' : paymentState.method,
            items: buildCartItemsPayload(),
            order_note: ($orderNote?.value || '').trim() || null,
        };

        if (billDiscount.type && billDiscount.value > 0) {
            payload.discount_type  = billDiscount.type;
            payload.discount_value = Number(billDiscount.value);
        }
        if (promoState.code) payload.promo_code = promoState.code;
        if (walletState.applied > 0 && customerState.customer_id) {
            payload.wallet_amount = Number(walletState.applied);
        }
        if (paymentState.method === 'custom' && paymentState.customMethodName) {
            payload.custom_payment_method_name = paymentState.customMethodName;
        }

        if (customerState.mode === 'walkin') {
            const wn = (document.getElementById('pos-walkin-name').value || '').trim();
            const wm = (document.getElementById('pos-walkin-mobile').value || '').trim();
            const wc = (document.getElementById('pos-walkin-cc')?.value || '').trim();
            if (wn) payload.walkin_customer_name = wn;
            if (wm) payload.walkin_customer_mobile = wm;
            if (wc) payload.walkin_customer_country_code = wc;
        } else {
            if (!customerState.customer_id) {
                $checkoutError.textContent = 'Please search and select a customer, or register a new one.';
                $checkoutError.classList.remove('d-none');
                return;
            }
            payload.customer_id = customerState.customer_id;
        }

        try {
            $completeBtn.disabled = true;
            $completeBtn.textContent = (paymentState.method === 'upi' || paymentState.method === 'custom') ? 'Confirming…' : 'Processing…';
            const res = await axios.post(ordersCreateUrl, payload);
            const order = res?.data?.data?.order;
            if (!order?.id) throw new Error('Unexpected response');

            const receiptUrl = receiptUrlTemplate.replace('__ID__', String(order.id));
            try { window.open(receiptUrl, '_blank', 'noopener'); } catch (_) {}

            const change = (paymentState.method === 'cash' && received > 0) ? Math.max(0, received - total) : 0;

            resetSaleState();
            setPaymentMethod('cash');
            hideModal($modalEl);
            showSaleSuccess(order.id, total, change, receiptUrl);
            fetchProducts({ append: false });
        } catch (err) {
            const errorData = err?.response?.data?.data || {};
            const promoRemoved = clearInvalidPromoAfterCheckout(errorData);
            const removedRows = promoRemoved ? 0 : (removeStaleAddonCartRows(errorData) || removeStaleProductCartRows(errorData));
            const msg = checkoutErrorMessage(err);
            let checkoutMessage = msg;
            if (promoRemoved) {
                checkoutMessage = `${msg} The promo was removed; cart items were not removed.`;
            } else if (removedRows > 0) {
                checkoutMessage = `${msg} The affected cart item was removed. Review the cart and continue.`;
            }
            $checkoutError.textContent = checkoutMessage;
            $checkoutError.classList.remove('d-none');
        } finally {
            $completeBtn.disabled = false;
            updateCompleteButtonLabel();
        }
    }

    function showSaleSuccess(orderId, total, change, receiptUrl) {
        const toastInstance = typeof Toast !== 'undefined' ? Toast : (typeof window.Toast !== 'undefined' ? window.Toast : null);
        if (toastInstance && toastInstance.fire) {
            const changeRow = change > 0 ? `<div class="small mt-1 text-muted">Change due: <strong>${money(change)}</strong></div>` : '';
            toastInstance.fire({
                icon: 'success',
                title: 'Sale complete',
                html: `
                    <div class="small text-muted">Order #${orderId} · Total ${money(total)}</div>
                    ${changeRow}
                    <div class="mt-2"><a href="${escapeHtml(receiptUrl)}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success">Open receipt</a></div>
                `,
                timer: 10000,
                timerProgressBar: true
            });
        } else {
            let host = document.getElementById('pos-toast-host');
            if (!host) {
                host = document.createElement('div');
                host.id = 'pos-toast-host';
                document.body.appendChild(host);
            }
            const card = document.createElement('div');
            card.className = 'alert alert-success alert-dismissible mb-2';
            card.setAttribute('role', 'alert');
            const changeRow = change > 0 ? `<div class="small">Change due: <strong>${money(change)}</strong></div>` : '';
            card.innerHTML = `
                <div class="fw-medium">Sale complete</div>
                <div class="small">Order #${orderId} · Total ${money(total)}</div>
                ${changeRow}
                <div class="mt-1"><a href="${escapeHtml(receiptUrl)}" target="_blank" rel="noopener" class="text-success">Open receipt</a></div>
                <button type="button" class="btn-close" aria-label="Close"></button>
            `;
            card.querySelector('.btn-close').addEventListener('click', () => card.remove());
            host.appendChild(card);
            setTimeout(() => card.remove(), 10000);
        }
    }

    function toggleFullscreen() {
        document.body.classList.toggle('pos-fullscreen');
        const isOn = document.body.classList.contains('pos-fullscreen');
        $fullscreenBtn.querySelectorAll('.pos-fullscreen-on').forEach(el => el.classList.toggle('d-none', isOn));
        $fullscreenBtn.querySelectorAll('.pos-fullscreen-off').forEach(el => el.classList.toggle('d-none', !isOn));
    }

    // ── wiring ──

    // ── Barcode scanner ──
    // Barcode input now auto-submits after a short idle window so manual
    // typing and scanner bursts both add the matched product without
    // requiring Enter. Scanners that still append Enter are deduped so the
    // same code is not added twice.
    let barcodeLookupTimer = null;
    let barcodeLookupInFlight = false;
    let barcodeLastSubmittedCode = '';
    let barcodeLastSubmittedAt = 0;
    const barcodeLookupDelayMs = 1400;

    function clearBarcodeLookupTimer() {
        if (!barcodeLookupTimer) {
            return;
        }

        clearTimeout(barcodeLookupTimer);
        barcodeLookupTimer = null;
    }

    function shouldSkipDuplicateBarcode(code) {
        const now = Date.now();
        if (barcodeLastSubmittedCode === code && (now - barcodeLastSubmittedAt) < 800) {
            return true;
        }

        barcodeLastSubmittedCode = code;
        barcodeLastSubmittedAt = now;

        return false;
    }

    function queueBarcodeLookup(code, delay = barcodeLookupDelayMs) {
        const normalizedCode = String(code || '').trim();
        clearBarcodeLookupTimer();
        if (!normalizedCode) {
            return;
        }

        barcodeLookupTimer = setTimeout(() => {
            barcodeLookupTimer = null;
            handleBarcode(normalizedCode);
        }, delay);
    }

    async function handleBarcode(code) {
        const c = String(code || '').trim();
        clearBarcodeLookupTimer();
        if (!c || !$store?.value || barcodeLookupInFlight || shouldSkipDuplicateBarcode(c)) return;
        barcodeLookupInFlight = true;
        $barcode.disabled = true;
        try {
            const res = await axios.get(barcodeLookupUrl, { params: { store_id: parseInt($store.value, 10), code: c } });
            const product = res?.data?.data?.product;
            if (!product) {
                flashBarcodeError('No match found.');
                return;
            }
            // Cache it in productIndex so the customize modal / cart edit
            // path can resolve it without a re-fetch.
            productIndex.set(product.product_id, product);

            // If the product has neither variants nor addons, drop it
            // straight into the cart. Otherwise pop the customize modal so
            // the cashier can pick the right variant + addons.
            const def = product.default_variant || product.variants?.[0];
            const hasAddons = (product.variants || []).some(v => (v.addon_groups || []).length > 0);
            if (!product.has_variants && !hasAddons && def && def.stock > 0) {
                addCartRow({
                    store_product_variant_id: def.store_product_variant_id,
                    product_id: product.product_id,
                    title: product.title,
                    variant_title: def.title,
                    unit_price: def.effective_price,
                    regular_unit_price: def.price,
                    tax_percent: product.tax_percent || 0,
                    is_inclusive_tax: !!product.is_inclusive_tax,
                    minimum_order_quantity: product.minimum_order_quantity,
                    quantity_step_size: product.quantity_step_size,
                    total_allowed_quantity: product.total_allowed_quantity,
                    qty: product.minimum_order_quantity || 1,
                    stock: def.stock,
                    addons: [],
                });
            } else {
                openCustomizeFor(product.product_id);
            }
        } catch (err) {
            const msg = err?.response?.data?.message || 'Lookup failed.';
            flashBarcodeError(msg);
        } finally {
            $barcode.value = '';
            $barcode.disabled = false;
            barcodeLookupInFlight = false;
            $barcode.focus();
        }
    }

    function flashBarcodeError(msg) {
        if (window.toastr?.warning) window.toastr.warning(msg);
        else console.warn('Barcode:', msg);
        if ($barcode) {
            $barcode.classList.add('is-invalid');
            setTimeout(() => $barcode.classList.remove('is-invalid'), 1500);
        }
    }

    $barcode?.addEventListener('input', () => {
        queueBarcodeLookup($barcode.value);
    });

    $barcode?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearBarcodeLookupTimer();
            handleBarcode($barcode.value);
        }
    });

    if ($search) $search.addEventListener('input', debounce(() => fetchProducts({ append: false }), 300));
    if ($store)  $store.addEventListener('change', () => {
        listState.categoryId = null;
        refreshCategories();
        fetchProducts({ append: false });
        refreshPaymentMethodAvailability();
        restorePersistedDraft();
    });

    $payTabs?.addEventListener('click', (e) => {
        const btn = e.target.closest('.nav-link[data-method]');
        if (!btn || btn.classList.contains('is-disabled')) return;
        if (btn.dataset.method === 'custom' && btn.dataset.customIndex !== undefined) {
            activateCustomMethod(parseInt(btn.dataset.customIndex, 10));
        } else {
            setPaymentMethod(btn.dataset.method);
        }
    });

    // Discount controls — toggle the form open, switch type pills, apply, clear.
    function showDiscountForm() {
        $discountForm?.classList.remove('d-none');
        $discountToggle?.classList.add('d-none');
        $discountInput?.focus();
    }
    function hideDiscountForm() {
        $discountForm?.classList.add('d-none');
        $discountToggle?.classList.remove('d-none');
        if ($discountInput) $discountInput.value = '';
    }
    $discountToggle?.addEventListener('click', showDiscountForm);
    document.getElementById('pos-discount-cancel-btn')?.addEventListener('click', hideDiscountForm);

    $promoToggle?.addEventListener('click', () => {
        $promoForm?.classList.remove('d-none');
        $promoToggle?.classList.add('d-none');
        $promoInput?.focus();
    });
    function hidePromoForm() {
        $promoForm?.classList.add('d-none');
        $promoToggle?.classList.remove('d-none');
        if ($promoInput) {
            $promoInput.value = '';
        }
        if ($promoHint) {
            $promoHint.textContent = 'Re-validated server-side at checkout.';
            $promoHint.classList.remove('text-danger');
        }
    }
    document.getElementById('pos-promo-cancel-btn')?.addEventListener('click', hidePromoForm);
    document.getElementById('pos-promo-apply-btn')?.addEventListener('click', applyPromoCode);
    $promoInput?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); applyPromoCode(); }
    });
    document.getElementById('pos-cart-promo-clear')?.addEventListener('click', clearPromo);

    // Wallet apply / max / clear
    // Cap on every keystroke at min(customer balance, remaining bill) so the
    // cashier physically cannot enter a value that would over-apply. Backend
    // also enforces this with a SELECT … FOR UPDATE on the wallet row, but
    // the UI must agree or the cashier sees a confusing post-submit error.
    $walletInput?.addEventListener('input', (e) => {
        const raw = parseFloat(e.target.value || '0') || 0;
        const b = cartBreakdown();
        const remaining = Math.max(0, b.subtotal - b.discountAmount - b.promoAmount);
        const cap = Math.max(0, Math.min(Number(walletState.balance || 0), remaining));
        let val = Math.max(0, raw);
        if (val > cap) {
            val = cap;
            // Snap the field to the cap and flag it briefly so the cashier
            // knows their input was clamped (silent clamping is confusing).
            e.target.value = cap.toFixed(2);
            if ($walletHint) {
                $walletHint.textContent = `Capped at ${money(cap)} — customer's wallet balance is ${money(walletState.balance)}.`;
                $walletHint.classList.add('text-danger');
                clearTimeout(window.__posWalletHintTimer);
                window.__posWalletHintTimer = setTimeout(() => {
                    $walletHint.textContent = "Capped at customer's balance and the remaining bill total.";
                    $walletHint.classList.remove('text-danger');
                }, 3000);
            }
        }
        walletState.applied = val;
        renderCart();
    });
    document.getElementById('pos-wallet-max-btn')?.addEventListener('click', () => {
        const b = cartBreakdown();
        const remaining = b.subtotal - b.discountAmount - b.promoAmount;
        const max = Math.max(0, Math.min(walletState.balance, remaining));
        walletState.applied = max;
        if ($walletInput) $walletInput.value = max.toFixed(2);
        renderCart();
    });
    document.getElementById('pos-wallet-clear-btn')?.addEventListener('click', clearWallet);
    document.querySelectorAll('.pos-discount-type').forEach(btn => btn.addEventListener('click', () => {
        billDiscount.formType = btn.dataset.type;
        document.querySelectorAll('.pos-discount-type').forEach(b => b.classList.toggle('active', b === btn));
        if ($discountInput) {
            if (btn.dataset.type === 'percent') $discountInput.setAttribute('max', '100');
            else $discountInput.removeAttribute('max');
        }
        if ($discountHint) $discountHint.textContent = btn.dataset.type === 'percent'
            ? 'Cashier discount, 0–100%.'
            : 'Cashier discount, capped at the subtotal.';
    }));
    document.getElementById('pos-discount-apply-btn')?.addEventListener('click', function () {
        const v = parseFloat($discountInput?.value || '0') || 0;
        if (v <= 0) { $discountInput?.focus(); return; }
        const formType = billDiscount.formType || 'fixed';
        if (formType === 'percent' && v > 100) {
            showPosError('Percentage discount cannot be greater than 100.', 'Invalid discount');
            $discountInput?.focus();
            return;
        }
        if (formType === 'fixed' && v > cartBreakdown().subtotal) {
            showPosError('Discount amount cannot be greater than the cart total.', 'Invalid discount');
            $discountInput?.focus();
            return;
        }
        applyBillDiscount(formType, v);
        hideDiscountForm();
    });
    $discountInput?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); document.getElementById('pos-discount-apply-btn')?.click(); }
    });
    document.getElementById('pos-cart-discount-clear')?.addEventListener('click', () => {
        clearBillDiscount();
        renderCart();
    });

    document.getElementById('pos-cart-clear-btn')?.addEventListener('click', () => {
        if (cart.size === 0) return;
        showConfirmModal({
            title: 'Clear cart',
            message: 'Clear all items from the cart?',
            confirmLabel: 'Clear cart',
            confirmClass: 'btn-danger',
            onConfirm: () => {
                cart.clear();
                clearCartAdjustments();
                renderCart();
                hideModal($confirmModal);
            },
        });
    });

    // ── Customer-Facing Display (CFD) ──
    //
    // Cashier opens a 2nd screen at /pos/display/{token}. The POS broadcasts
    // the live cart to the cache (cfdPush), the display polls (cfdPull). We
    // persist the token in localStorage so reopening the POS reconnects to
    // the same display without rotating tokens (and confusing the customer
    // with a now-blank screen).
    function cfdToken() {
        try {
            let t = localStorage.getItem('pos_cfd_token');
            if (!t || t.length !== 36) {
                // RFC4122 v4 with crypto.getRandomValues — same shape Laravel
                // uses for Str::uuid().
                t = ([1e7]+-1e3+-4e3+-8e3+-1e11).replace(/[018]/g, c =>
                    (c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16));
                localStorage.setItem('pos_cfd_token', t);
            }
            return t;
        } catch { return null; }
    }

    function cfdSnapshotState() {
        const b = cartBreakdown();
        const items = [];
        cart.forEach((row) => {
            const variant = row.variant_title && row.variant_title.toLowerCase() !== 'default' ? row.variant_title : null;
            const addonUnitTotal = row.addons.reduce((sum, addon) => sum + Number(addon.price || 0), 0);
            items.push({
                title: row.title,
                qty: row.qty,
                variant,
                product_unit_price: Number(row.unit_price || 0),
                addon_unit_total: addonUnitTotal,
                tax_percent: Number(row.tax_percent || 0),
                tax_amount: round2(rowLineTax(row)),
                addons: row.addons.map(a => ({
                    title: a.item_title,
                    price: Number(a.price || 0),
                })),
                line_total: rowLineTotal(row),
            });
        });

        const storeOpt = $store?.options[$store.selectedIndex];
        const storeId = currentStoreId();
        return {
            store_name: storeOpt?.text || '',
            store_logo: storeDisplayMeta?.[storeId]?.logo || null,
            currency_code: selectedStoreCurrencyCode(),
            currency_symbol: currentCurrencyLabel(),
            items,
            breakdown: {
                subtotal:       b.subtotal,
                subtotalExTax:  b.subtotalExTax,
                tax:            b.tax,
                taxByRate:      b.taxByRate || [],
                savings:        b.savings,
                savingsMessage,
                discountAmount: b.discountAmount,
                promoAmount:    b.promoAmount,
                promoCode:      b.promoCode,
                walletApplied:  b.walletApplied,
                total:          b.total,
            },
        };
    }

    let cfdPushTimer = null;
    function cfdSchedulePush() {
        const token = cfdToken();
        if (!token) return;
        clearTimeout(cfdPushTimer);
        // Coalesce rapid-fire cart edits into one push.
        cfdPushTimer = setTimeout(async () => {
            try {
                await axios.post(cfdPushUrl, { token, state: cfdSnapshotState() });
            } catch (_) { /* benign — display will go offline-dot */ }
        }, 250);
    }

    document.getElementById('pos-cfd-btn')?.addEventListener('click', () => {
        const token = cfdToken();
        if (!token) {
            window.alert('Could not generate a display token. Browser storage blocked?');
            return;
        }
        // Push current state immediately so the display has something to show
        // when it loads, even if the cart is empty.
        cfdSchedulePush();
        const url = cfdShowUrlT.replace('__TOKEN__', encodeURIComponent(token));
        window.open(url, '_blank', 'noopener');
    });

    // ── Recent orders ──
    const $recentModal      = document.getElementById('pos-recent-modal');
    const $recentLoading    = document.getElementById('pos-recent-loading');
    const $recentEmpty      = document.getElementById('pos-recent-empty');
    const $recentTableWrap  = document.getElementById('pos-recent-table-wrap');
    const $recentRows       = document.getElementById('pos-recent-rows');

    // ── Recent sales: search + sort + inline actions + count footer ──
    const $recentSearch = document.getElementById('pos-recent-search');
    const $recentLimit  = document.getElementById('pos-recent-limit');
    const $recentCount  = document.getElementById('pos-recent-count');
    const recentState = { q: '', sort: 'id', direction: 'desc', limit: 50, total: 0 };

    function recentRenderSortHeaders() {
        if (!$recentTableWrap) return;
        $recentTableWrap.querySelectorAll('th.pos-sortable').forEach(th => {
            const key = th.dataset.sort;
            th.classList.remove('is-asc', 'is-desc');
            if (key === recentState.sort) th.classList.add(recentState.direction === 'asc' ? 'is-asc' : 'is-desc');
        });
    }

    async function loadRecentOrders() {
        if (!$recentRows) return;
        $recentLoading?.classList.remove('d-none');
        $recentEmpty?.classList.add('d-none');
        $recentTableWrap?.classList.add('d-none');
        if ($recentCount) $recentCount.textContent = '';
        try {
            const res = await axios.get(ordersRecentUrl, {
                params: {
                    limit:     recentState.limit,
                    q:         recentState.q || undefined,
                    sort:      recentState.sort,
                    direction: recentState.direction,
                },
            });
            const data   = res?.data?.data || {};
            const orders = data.orders || [];
            recentState.total = parseInt(data.total, 10) || 0;
            if ($recentCount) {
                if (recentState.total === 0) {
                    $recentCount.textContent = recentState.q ? `No matches for "${recentState.q}".` : '';
                } else {
                    const shown = orders.length;
                    const cap   = parseInt(data.limit, 10) || recentState.limit;
                    $recentCount.textContent = recentState.total > cap
                        ? `Showing ${shown} of ${recentState.total} sales (raise the per-page selector to see more).`
                        : `Showing all ${recentState.total} sale${recentState.total === 1 ? '' : 's'}.`;
                }
            }
            if (!orders.length) {
                $recentEmpty?.classList.remove('d-none');
                recentRenderSortHeaders();
                return;
            }
            const fmtTime = formatTime;
            const eyeIcon  = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="2"/><path d="M22 12c-2.667 4.667 -6 7 -10 7s-7.333 -2.333 -10 -7c2.667 -4.667 6 -7 10 -7s7.333 2.333 10 7"/></svg>`;
            const printIcon= `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M17 17h2a2 2 0 0 0 2 -2v-4a2 2 0 0 0 -2 -2h-14a2 2 0 0 0 -2 2v4a2 2 0 0 0 2 2h2"/><path d="M17 9v-4a2 2 0 0 0 -2 -2h-6a2 2 0 0 0 -2 2v4"/><rect x="7" y="13" width="10" height="8" rx="2"/></svg>`;
            const undoIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14l-4 -4l4 -4"/><path d="M5 10h11a4 4 0 1 1 0 8h-1"/></svg>`;
            const histIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3 -6.7l-3 2.7"/><path d="M3 4v4h4"/><path d="M12 8v4l3 2"/></svg>`;

            $recentRows.innerHTML = orders.map(o => {
                const view    = o.detail_url ? `<a href="${escapeHtml(o.detail_url)}" target="_blank" rel="noopener" class="pos-recent-action" title="Open order details" aria-label="View">${eyeIcon}</a>` : '';
                const reprint = `<a href="${escapeHtml(o.receipt_url)}" target="_blank" rel="noopener" class="pos-recent-action" title="Reprint receipt" aria-label="Reprint">${printIcon}</a>`;
                const refund  = o.refundable
                    ? `<button type="button" class="pos-recent-action is-refund pos-recent-refund" data-order-id="${o.id}" title="Refund items" aria-label="Refund">${undoIcon}</button>`
                    : '';
                const history = o.has_refunds
                    ? `<button type="button" class="pos-recent-action is-history pos-recent-history" data-order-id="${o.id}" title="View refund history" aria-label="Refund history">${histIcon}</button>`
                    : '';
                const fullyRefunded = o.has_refunds && !o.refundable
                    ? `<span class="badge bg-secondary-lt ms-1" title="All items refunded">Refunded</span>`
                    : '';
                return `
                <tr>
                    <td><strong>#${o.id}</strong>${fullyRefunded}</td>
                    <td class="text-secondary small">${escapeHtml(fmtTime(o.created_at))}</td>
                    <td class="text-truncate" style="max-width: 160px">${escapeHtml(o.customer || '—')}</td>
                    <td><span class="badge bg-secondary-lt">${escapeHtml(o.payment_label)}</span></td>
                    <td class="text-end fw-medium">${money(o.final_total)}</td>
                    <td class="text-end">
                        <div class="pos-recent-actions">
                            ${view}${reprint}${refund}${history}
                        </div>
                    </td>
                </tr>`;
            }).join('');
            $recentTableWrap?.classList.remove('d-none');
            recentRenderSortHeaders();
        } catch (err) {
            $recentLoading.textContent = err?.response?.data?.message || 'Could not load recent sales.';
        } finally {
            $recentLoading?.classList.add('d-none');
        }
    }

    // Search input: debounce so we don't hammer the endpoint on every keystroke.
    $recentSearch?.addEventListener('input', debounce(() => {
        recentState.q = ($recentSearch.value || '').trim();
        loadRecentOrders();
    }, 250));

    // Sortable column headers — click to toggle direction; click a different
    // column to switch sort key (default desc).
    $recentTableWrap?.addEventListener('click', (e) => {
        const th = e.target.closest('th.pos-sortable');
        if (!th) return;
        const key = th.dataset.sort;
        if (recentState.sort === key) {
            recentState.direction = recentState.direction === 'asc' ? 'desc' : 'asc';
        } else {
            recentState.sort = key;
            recentState.direction = 'desc';
        }
        loadRecentOrders();
    });

    $recentLimit?.addEventListener('change', () => {
        recentState.limit = parseInt($recentLimit.value, 10) || 50;
        loadRecentOrders();
    });

    document.getElementById('pos-recent-btn')?.addEventListener('click', () => {
        loadRecentOrders();
        showModal($recentModal);
    });
    document.getElementById('pos-recent-refresh-btn')?.addEventListener('click', loadRecentOrders);

    // ── Refund flow ──
    // Triggered from a row in the Recent modal. The order_id is on the
    // button data attribute. We hide the recent modal while the refund
    // modal is open so the cashier has a single decision in front of them.
    const $refundModal      = document.getElementById('pos-refund-modal');
    const $refundLoading    = document.getElementById('pos-refund-loading');
    const $refundEmpty      = document.getElementById('pos-refund-empty');
    const $refundFormWrap   = document.getElementById('pos-refund-form-wrap');
    const $refundRows       = document.getElementById('pos-refund-rows');
    const $refundAlready    = document.getElementById('pos-refund-already');
    const $refundReason     = document.getElementById('pos-refund-reason');
    const $refundOrderLabel = document.getElementById('pos-refund-order-label');
    const $refundTotal      = document.getElementById('pos-refund-total');
    const $refundSubmit     = document.getElementById('pos-refund-submit');

    let refundCtx = { orderId: null, lines: [] };

    function refundComputeTotal() {
        let total = 0;
        for (const ln of refundCtx.lines) {
            const qty = Math.max(0, Math.min(ln.max_refundable, parseInt(ln.input?.value || '0', 10) || 0));
            total += qty * ln.per_unit_amount;
        }
        $refundTotal.textContent = money(total);
        $refundSubmit.disabled = total <= 0;
        return total;
    }

    function renderRefundLines(preview) {
        refundCtx.lines = (preview.refundable || []).map(r => ({ ...r, input: null }));
        if (!refundCtx.lines.length) {
            $refundFormWrap.classList.add('d-none');
            $refundEmpty.classList.remove('d-none');
            $refundSubmit.disabled = true;
            return;
        }
        $refundEmpty.classList.add('d-none');
        $refundFormWrap.classList.remove('d-none');

        $refundRows.innerHTML = refundCtx.lines.map((ln, i) => {
            const meta = [ln.variant_title, ln.addon_summary].filter(Boolean).join(' · ');
            return `
                <tr data-i="${i}">
                    <td>
                        <div class="fw-medium">${escapeHtml(ln.title)}</div>
                        ${meta ? `<div class="text-secondary small">${escapeHtml(meta)}</div>` : ''}
                        ${ln.already_refunded > 0 ? `<div class="text-secondary small">${ln.already_refunded} of ${ln.quantity} already refunded</div>` : ''}
                    </td>
                    <td class="text-center text-secondary">${ln.quantity}</td>
                    <td class="text-center">
                        <input type="number" min="0" max="${ln.max_refundable}" value="0"
                               class="form-control form-control-sm pos-refund-qty"
                               data-i="${i}" inputmode="numeric" style="max-width: 90px; margin: 0 auto;">
                        <div class="text-secondary small mt-1">max ${ln.max_refundable}</div>
                    </td>
                    <td class="text-end">
                        <span class="fw-medium pos-refund-line-amt" data-i="${i}">${money(0)}</span>
                        <div class="text-secondary small">${money(ln.per_unit_amount)} each</div>
                    </td>
                </tr>`;
        }).join('');

        // Wire up qty inputs after they're in the DOM
        refundCtx.lines.forEach((ln, i) => {
            ln.input = $refundRows.querySelector(`input.pos-refund-qty[data-i="${i}"]`);
            const $amt = $refundRows.querySelector(`.pos-refund-line-amt[data-i="${i}"]`);
            ln.input?.addEventListener('input', () => {
                let q = parseInt(ln.input.value || '0', 10) || 0;
                if (q < 0) q = 0;
                if (q > ln.max_refundable) q = ln.max_refundable;
                ln.input.value = String(q);
                if ($amt) $amt.textContent = money(q * ln.per_unit_amount);
                refundComputeTotal();
            });
        });
        refundComputeTotal();
    }

    async function openRefundModal(orderId) {
        refundCtx = { orderId, lines: [] };
        $refundOrderLabel.textContent = `#${orderId}`;
        $refundReason.value = '';
        $refundTotal.textContent = money(0);
        $refundSubmit.disabled = true;
        $refundLoading.classList.remove('d-none');
        $refundEmpty.classList.add('d-none');
        $refundFormWrap.classList.add('d-none');
        $refundAlready.classList.add('d-none');
        $refundRows.innerHTML = '';

        // Hand-off: hide the Recent modal so the cashier is focused on
        // the refund decision (Bootstrap's modal stack handles this poorly).
        hideModal($recentModal);
        showModal($refundModal);

        try {
            const url = refundPreviewUrlT.replace('__ID__', encodeURIComponent(orderId));
            const res = await axios.get(url);
            const preview = res?.data?.data || {};
            if (preview.already_refunded_total > 0) {
                $refundAlready.classList.remove('d-none');
                $refundAlready.textContent = `${money(preview.already_refunded_total)} already refunded against this order.`;
            }
            renderRefundLines(preview);
        } catch (err) {
            $refundLoading.textContent = err?.response?.data?.message || 'Could not load refund details.';
        } finally {
            $refundLoading.classList.add('d-none');
        }
    }

    async function submitRefund() {
        if (!refundCtx.orderId) return;
        const lines = refundCtx.lines
            .map(ln => ({ order_item_id: ln.order_item_id, quantity: parseInt(ln.input?.value || '0', 10) || 0 }))
            .filter(l => l.quantity > 0);
        if (!lines.length) return;

        const method = document.querySelector('input[name="pos-refund-method"]:checked')?.value || 'cash';
        const note   = (document.getElementById('pos-refund-method-note')?.value || '').trim();

        $refundSubmit.disabled = true;
        $refundSubmit.textContent = 'Refunding…';
        try {
            const url = refundCreateUrlT.replace('__ID__', encodeURIComponent(refundCtx.orderId));
            const res = await axios.post(url, {
                lines,
                reason: ($refundReason.value || '').trim() || null,
                method,
                method_note: note || null,
            });
            const data = res?.data?.data || {};
            hideModal($refundModal);
            // Bring the cashier back to the recent list with the row updated.
            await loadRecentOrders();
            showModal($recentModal);
            if (typeof Toast !== 'undefined' && Toast?.fire) {
                Toast.fire({ icon: 'success', title: `Refund recorded · ${money(data.total_amount || 0)}` });
            } else {
                window.alert(`Refund recorded · ${money(data.total_amount || 0)}`);
            }
        } catch (err) {
            const msg = err?.response?.data?.message || 'Could not process refund.';
            if (typeof Toast !== 'undefined' && Toast?.fire) {
                Toast.fire({ icon: 'error', title: msg });
            } else {
                window.alert(msg);
            }
        } finally {
            $refundSubmit.disabled = false;
            $refundSubmit.textContent = 'Refund';
        }
    }

    // ── Refund history view (read-only audit trail per order) ──
    const $refundHistoryModal   = document.getElementById('pos-refund-history-modal');
    const $refundHistoryLabel   = document.getElementById('pos-refund-history-order-label');
    const $refundHistoryLoading = document.getElementById('pos-refund-history-loading');
    const $refundHistoryEmpty   = document.getElementById('pos-refund-history-empty');
    const $refundHistorySummary = document.getElementById('pos-refund-history-summary');
    const $refundHistoryList    = document.getElementById('pos-refund-history-list');

    // Alias — uses the shared formatTime helper.
    const refundHistoryFmtTime = formatTime;

    async function openRefundHistory(orderId) {
        if (!$refundHistoryModal) return;
        $refundHistoryLabel.textContent = `#${orderId}`;
        $refundHistoryLoading.classList.remove('d-none');
        $refundHistoryEmpty.classList.add('d-none');
        $refundHistorySummary.classList.add('d-none');
        $refundHistoryList.innerHTML = '';
        // Hand-off from Recent modal — same pattern as the refund flow.
        hideModal($recentModal);
        showModal($refundHistoryModal);
        try {
            const url = refundHistoryUrlT.replace('__ID__', encodeURIComponent(orderId));
            const res = await axios.get(url);
            const d   = res?.data?.data || {};
            const refunds = d.refunds || [];
            if (!refunds.length) {
                $refundHistoryEmpty.classList.remove('d-none');
                return;
            }
            $refundHistorySummary.classList.remove('d-none');
            $refundHistorySummary.textContent =
                `${money(d.total_refunded || 0)} refunded across ${refunds.length} event${refunds.length === 1 ? '' : 's'} (order total ${money(d.order_total || 0)}).`;

            $refundHistoryList.innerHTML = refunds.map(r => {
                const lines = (r.lines || []).map(l => {
                    const meta = l.variant_title ? ` <span class="text-secondary small">· ${escapeHtml(l.variant_title)}</span>` : '';
                    return `
                        <div class="d-flex align-items-baseline">
                            <div class="flex-fill">
                                <span class="fw-medium">${escapeHtml(l.title || ('Item #' + l.order_item_id))}</span>${meta}
                            </div>
                            <div class="text-secondary small me-3">× ${l.quantity}</div>
                            <div class="text-end" style="min-width: 90px;">${money(l.amount)}</div>
                        </div>`;
                }).join('');
                const noteHtml = r.method_note ? `<div class="text-secondary small">Ref: ${escapeHtml(r.method_note)}</div>` : '';
                const reasonHtml = r.reason ? `<div class="text-secondary small mt-1"><em>"${escapeHtml(r.reason)}"</em></div>` : '';
                const byHtml = r.refunded_by ? ` · by ${escapeHtml(r.refunded_by)}` : '';
                return `
                    <div class="card card-borderless" style="background: var(--tblr-bg-surface-secondary, #f6f8fb);">
                        <div class="card-body py-2 px-3">
                            <div class="d-flex align-items-center mb-2">
                                <div>
                                    <span class="fw-medium">Refund #${r.id}</span>
                                    <span class="text-secondary small ms-2">${escapeHtml(refundHistoryFmtTime(r.created_at))}${byHtml}</span>
                                </div>
                                <div class="ms-auto">
                                    <span class="badge bg-secondary-lt me-2">${escapeHtml(r.method_label || r.method)}</span>
                                    <span class="fw-medium">${money(r.total_amount)}</span>
                                </div>
                            </div>
                            ${noteHtml}
                            <div class="mt-1">${lines}</div>
                            ${reasonHtml}
                        </div>
                    </div>`;
            }).join('');
        } catch (err) {
            $refundHistoryLoading.textContent = err?.response?.data?.message || 'Could not load refund history.';
        } finally {
            $refundHistoryLoading.classList.add('d-none');
        }
    }

    // Event delegation: click on Refund or History buttons in the recent list.
    $recentRows?.addEventListener('click', (e) => {
        const refundBtn  = e.target.closest('.pos-recent-refund');
        const historyBtn = e.target.closest('.pos-recent-history');
        if (refundBtn) {
            const id = parseInt(refundBtn.dataset.orderId, 10);
            if (id) openRefundModal(id);
            return;
        }
        if (historyBtn) {
            const id = parseInt(historyBtn.dataset.orderId, 10);
            if (id) openRefundHistory(id);
        }
    });
    $refundSubmit?.addEventListener('click', submitRefund);

    // ── Held / parked sales ──
    const $heldBtn      = document.getElementById('pos-held-btn');
    const $heldCount    = document.getElementById('pos-held-count');
    const $holdBtn      = document.getElementById('pos-hold-btn');
    const $heldModal    = document.getElementById('pos-held-modal');
    const $heldLoading  = document.getElementById('pos-held-loading');
    const $heldEmpty    = document.getElementById('pos-held-empty');
    const $heldList     = document.getElementById('pos-held-list');

    function setHeldCount(n) {
        if (!$heldCount) return;
        $heldCount.textContent = String(n);
        $heldCount.classList.toggle('d-none', n === 0);
    }

    async function refreshHeldCount() {
        try {
            const res = await axios.get(parkedListUrl);
            const list = res?.data?.data?.parked || [];
            setHeldCount(list.length);
        } catch (_) {}
    }


    async function holdCurrentCart() {
        if (cart.size === 0) {
            showPosError('Cart is empty.', 'Nothing to hold');
            return;
        }
        const label = ($holdLabelInput?.value || '').trim();

        const items = Array.from(cart.values()).map(r => ({
            store_product_variant_id: r.store_product_variant_id,
            quantity: r.qty,
            // We snapshot the JS row's display fields too so resume can repopulate
            // the cart sidebar without re-fetching the catalog. PosOrderService
            // re-derives prices/tax authoritatively at actual checkout time.
            row_snapshot: {
                product_id: r.product_id,
                title: r.title,
                variant_title: r.variant_title,
                unit_price: r.unit_price,
                regular_unit_price: r.regular_unit_price,
                tax_percent: r.tax_percent,
                is_inclusive_tax: r.is_inclusive_tax,
                stock: r.stock,
                minimum_order_quantity: r.minimum_order_quantity,
                quantity_step_size: r.quantity_step_size,
                total_allowed_quantity: r.total_allowed_quantity,
                addons: r.addons,
            },
        }));

        const payload = {
            store_id: parseInt($store.value, 10),
            label: label || null,
            amount: cartBreakdown().total,
            items,
            customer: customerState.customer_id ? { id: customerState.customer_id, name: customerState.attached_name || null } : null,
            walkin: {
                name:   (document.getElementById('pos-walkin-name')?.value || '').trim() || null,
                mobile: (document.getElementById('pos-walkin-mobile')?.value || '').trim() || null,
                country_code: (document.getElementById('pos-walkin-cc')?.value || '').trim() || null,
            },
            order_note: ($orderNote?.value || '').trim() || null,
            discount: billDiscount.type ? { type: billDiscount.type, value: billDiscount.value } : null,
            promo: promoState.code ? {
                code: promoState.code,
                amount: promoState.amount,
                discount_type: promoState.discountType,
                discount_value: promoState.discountValue,
                max_discount_value: promoState.maxDiscountValue,
            } : null,
        };

        try {
            await axios.post(parkedCreateUrl, payload);
            if ($holdLabelInput) $holdLabelInput.value = '';
            hideModal($holdLabelModal);
            resetSaleState();
            refreshHeldCount();
        } catch (err) {
            showPosError(err?.response?.data?.message || 'Could not hold sale.');
        }
    }

    async function loadHeldList() {
        if (!$heldList) return;
        $heldLoading?.classList.remove('d-none');
        $heldEmpty?.classList.add('d-none');
        $heldList.classList.add('d-none');
        try {
            const res = await axios.get(parkedListUrl);
            const list = res?.data?.data?.parked || [];
            setHeldCount(list.length);
            if (!list.length) {
                $heldEmpty?.classList.remove('d-none');
                return list;
            }
            $heldList.innerHTML = list.map(p => {
                const when = p.created_at ? new Date(p.created_at).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : '';
                return `
                <div class="list-group-item d-flex align-items-center gap-2">
                    <div class="flex-fill">
                        <div class="fw-medium">${escapeHtml(p.label || 'Held sale')} <span class="text-secondary small">Â· ${money(p.amount)} Â· ${p.item_count} item${p.item_count === 1 ? '' : 's'}</span></div>
                        <div class="text-secondary small">${escapeHtml(when)}</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger pos-held-del" data-id="${p.id}">Discard</button>
                    <button type="button" class="btn btn-sm btn-primary pos-held-resume" data-id="${p.id}" data-payload='${escapeHtml(JSON.stringify(p.payload))}'>Resume</button>
                </div>`;
            }).join('');
            $heldList.innerHTML = list.map(renderHeldSaleCard).join('');
            $heldList.classList.remove('d-none');
            return list;
        } catch (err) {
            $heldLoading.textContent = err?.response?.data?.message || 'Could not load held sales.';
        } finally {
            $heldLoading?.classList.add('d-none');
        }
    }

    function heldPayloadItems(payload) {
        return Array.isArray(payload?.items) ? payload.items : [];
    }

    function heldRowFromItem(item) {
        const snap = item?.row_snapshot || {};
        return {
            store_product_variant_id: Number(item?.store_product_variant_id || 0),
            product_id: Number(snap.product_id || 0),
            title: snap.title || 'Item',
            variant_title: snap.variant_title || 'Default',
            unit_price: Number(snap.unit_price || 0),
            regular_unit_price: Number(snap.regular_unit_price || 0),
            tax_percent: Number(snap.tax_percent || 0),
            is_inclusive_tax: !!snap.is_inclusive_tax,
            qty: Number(item?.quantity || 1),
            stock: Number(snap.stock || 0),
            minimum_order_quantity: normalizedQtyRule(snap.minimum_order_quantity, 1),
            quantity_step_size: normalizedQtyRule(snap.quantity_step_size, 1),
            total_allowed_quantity: normalizedMaxQty(snap.total_allowed_quantity),
            addons: Array.isArray(snap.addons) ? snap.addons : [],
        };
    }

    function heldItemUnitAddonTotal(item) {
        return heldRowFromItem(item).addons.reduce((sum, addon) => sum + Number(addon.price || 0), 0);
    }

    function heldItemLineTotal(item) {
        const row = heldRowFromItem(item);
        return (Number(row.unit_price || 0) + heldItemUnitAddonTotal(item)) * Number(row.qty || 0);
    }

    function heldPayloadSubtotal(payload) {
        return heldPayloadItems(payload).reduce((sum, item) => sum + heldItemLineTotal(item), 0);
    }

    function heldPayloadDiscountAmount(payload, subtotal) {
        const discount = payload?.discount;
        if (!discount?.type) {
            return 0;
        }

        const value = Number(discount.value || 0);
        if (value <= 0 || subtotal <= 0) {
            return 0;
        }

        if (discount.type === 'percent') {
            return Math.min(subtotal, round2(subtotal * Math.min(100, Math.max(0, value)) / 100));
        }

        if (discount.type === 'fixed') {
            return Math.min(subtotal, Math.max(0, value));
        }

        return 0;
    }

    function heldPayloadTotal(payload) {
        const subtotal = heldPayloadSubtotal(payload);
        const discountAmount = heldPayloadDiscountAmount(payload, subtotal);
        return Math.max(0, round2(subtotal - discountAmount));
    }

    function heldPayloadClone(payload) {
        return JSON.parse(JSON.stringify(payload || {}));
    }

    function renderHeldSaleCard(parkedSale) {
        const payload = parkedSale.payload || {};
        const payloadJson = escapeHtml(JSON.stringify(payload));
        const items = heldPayloadItems(payload);
        const when = parkedSale.created_at ? new Date(parkedSale.created_at).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : '';
        const summary = `${money(Number(parkedSale.amount || 0))} · ${items.length} item${items.length === 1 ? '' : 's'}`;
        const itemRows = items.map((item, index) => {
            const row = heldRowFromItem(item);
            const variantMeta = row.variant_title && row.variant_title !== 'Default'
                ? `<div class="text-secondary small">${escapeHtml(row.variant_title)}</div>`
                : '';
            const addonRows = row.addons.length
                ? `<div class="mt-1">${row.addons.map((addon) => `
                    <div class="text-secondary small">
                        ${addon.group_title ? `${escapeHtml(addon.group_title)}: ` : ''}${escapeHtml(addon.item_title || 'Addon')}
                        ${Number(addon.price || 0) > 0 ? `<span class="ms-1">+${money(addon.price)}</span>` : ''}
                    </div>`).join('')}
                </div>`
                : '';

            return `
                <div class="pos-held-item">
                    <div class="d-flex justify-content-between gap-2">
                        <div class="flex-fill">
                            <div class="fw-medium">${escapeHtml(row.title)}</div>
                            ${variantMeta}
                            ${addonRows}
                            <div class="text-secondary small mt-1">${money(row.unit_price + heldItemUnitAddonTotal(item))} × ${row.qty}</div>
                        </div>
                        <div class="text-end">
                            <div class="fw-medium">${money(heldItemLineTotal(item))}</div>
                            <div class="d-flex gap-1 justify-content-end mt-2">
                                <button type="button" class="btn btn-sm btn-outline-danger pos-held-discard-item" data-id="${parkedSale.id}" data-index="${index}" data-payload='${payloadJson}'>Discard</button>
                                <button type="button" class="btn btn-sm btn-primary pos-held-resume-item" data-id="${parkedSale.id}" data-index="${index}" data-payload='${payloadJson}'>Resume</button>
                            </div>
                        </div>
                    </div>
                </div>`;
        }).join('');

        return `
            <details class="list-group-item pos-held-card">
                <summary class="pos-held-summary">
                    <div class="flex-fill">
                        <div class="fw-medium">${escapeHtml(parkedSale.label || 'Held sale')} <span class="text-secondary small">${escapeHtml(summary)}</span></div>
                        <div class="text-secondary small">${escapeHtml(when)}</div>
                    </div>
                </summary>
                <div class="pos-held-body">
                    <div class="d-flex justify-content-end gap-2 mb-3">
                        <button type="button" class="btn btn-sm btn-outline-danger pos-held-del" data-id="${parkedSale.id}" data-payload='${payloadJson}'>Discard all</button>
                        <button type="button" class="btn btn-sm btn-primary pos-held-resume" data-id="${parkedSale.id}" data-payload='${payloadJson}'>Resume all</button>
                    </div>
                    ${itemRows}
                </div>
            </details>`;
    }

    async function persistHeldPayload(id, payload) {
        const nextPayload = heldPayloadClone(payload);
        nextPayload.amount = heldPayloadTotal(nextPayload);
        const url = parkedUpdateUrlT.replace('__ID__', encodeURIComponent(id));
        await axios.patch(url, {
            ...nextPayload,
            label: nextPayload.label || null,
            amount: nextPayload.amount,
            items: heldPayloadItems(nextPayload),
        });
    }

    function applyHeldPayloadToCart(payload) {
        cart.clear();
        clearCartAdjustments();
        customerState.customer_id = null;
        setCustomerMode('walkin');

        const items = heldPayloadItems(payload);
        for (const item of items) {
            const row = heldRowFromItem(item);
            const sig = cartSignature(row.store_product_variant_id, row.addons);
            cart.set(sig, { ...row, signature: sig });
        }

        if (payload?.customer?.id) {
            attachCustomer(parseInt(payload.customer.id, 10), payload.customer.name || null);
        } else if (payload?.walkin?.name || payload?.walkin?.mobile) {
            const $n = document.getElementById('pos-walkin-name');
            const $m = document.getElementById('pos-walkin-mobile');
            const $c = document.getElementById('pos-walkin-cc');
            if ($n) $n.value = payload.walkin.name || '';
            if ($m) $m.value = payload.walkin.mobile || '';
            if ($c) $c.value = payload.walkin.country_code || '';
        }

        if (payload?.order_note && $orderNote) {
            $orderNote.value = payload.order_note;
        }
        if (payload?.discount?.type) {
            billDiscount.type = payload.discount.type;
            billDiscount.value = Number(payload.discount.value || 0);
            billDiscount.formType = payload.discount.type;
        }

        renderCart();
        hideModal($confirmModal);

        // Re-apply the held promo after the cart is live so it's re-priced
        // server-side against the resumed contents (and its chip reappears).
        if (payload?.promo?.code) {
            restoreHeldPromo(payload.promo);
        }
    }

    async function restoreHeldPromo(promo) {
        const code = (promo?.code || '').trim();
        if (!code) {
            return;
        }
        try {
            await validatePromoForCurrentCart(code, { showErrors: true });
        } catch (_) {
            // The cart changed enough that the promo no longer qualifies — seed
            // the snapshot so the cashier still sees what was applied and can act.
            syncPromoState({
                promo_code: code,
                discount: promo.amount,
                discount_type: promo.discount_type,
                discount_amount: promo.discount_value,
                max_discount_value: promo.max_discount_value,
            }, code);
            if ($promoForm) $promoForm.classList.add('d-none');
            if ($promoToggle) $promoToggle.classList.remove('d-none');
            renderCart({ revalidatePromo: false });
        }
    }

    function resumeHeldPayload(payloadJson, onApplied = null) {
        let p;
        try { p = JSON.parse(payloadJson); } catch { return; }

        const applyResume = () => {
            applyHeldPayloadToCart(p);
            if (typeof onApplied === 'function') {
                onApplied();
            }
        };

        if (cart.size > 0) {
            showConfirmModal({
                title: 'Resume held sale',
                message: 'Replace the current cart with this held sale?',
                confirmLabel: 'Replace cart',
                confirmClass: 'btn-primary',
                onConfirm: applyResume,
            });
            return;
        }

        applyResume();
    }

    function addHeldItemToCart(item) {
        const row = heldRowFromItem(item);
        return addCartRow({
            store_product_variant_id: row.store_product_variant_id,
            product_id: row.product_id,
            title: row.title,
            variant_title: row.variant_title,
            unit_price: row.unit_price,
            regular_unit_price: row.regular_unit_price,
            tax_percent: row.tax_percent || 0,
            is_inclusive_tax: !!row.is_inclusive_tax,
            minimum_order_quantity: row.minimum_order_quantity,
            quantity_step_size: row.quantity_step_size,
            total_allowed_quantity: row.total_allowed_quantity,
            qty: row.qty,
            stock: row.stock,
            addons: row.addons,
        });
    }

    async function resumeHeldItem(id, payloadJson, itemIndex) {
        let payload;
        try { payload = JSON.parse(payloadJson); } catch { return; }

        const items = heldPayloadItems(payload);
        const target = items[itemIndex];
        if (!target) {
            return;
        }

        const added = addHeldItemToCart(target);
        if (!added) {
            return;
        }

        const nextPayload = heldPayloadClone(payload);
        nextPayload.items.splice(itemIndex, 1);

        try {
            if (nextPayload.items.length === 0) {
                await deleteHeld(id, { suppressReload: true });
            } else {
                await persistHeldPayload(id, nextPayload);
            }
            await loadHeldList();
        } catch (err) {
            showPosError(err?.response?.data?.message || 'Could not update held sale.');
        }
    }

    async function discardHeldItem(id, payloadJson, itemIndex) {
        let payload;
        try { payload = JSON.parse(payloadJson); } catch { return; }

        const nextPayload = heldPayloadClone(payload);
        if (!heldPayloadItems(nextPayload)[itemIndex]) {
            return;
        }
        nextPayload.items.splice(itemIndex, 1);

        try {
            if (nextPayload.items.length === 0) {
                await deleteHeld(id, { suppressReload: true });
            } else {
                await persistHeldPayload(id, nextPayload);
            }
            await loadHeldList();
        } catch (err) {
            showPosError(err?.response?.data?.message || 'Could not update held sale.');
        }
    }

    async function deleteHeld(id, { suppressReload = false } = {}) {
        try {
            const url = parkedDeleteUrlT.replace('__ID__', encodeURIComponent(id));
            await axios.delete(url);
            if (!suppressReload) {
                const list = await loadHeldList();
                if (!Array.isArray(list) || list.length === 0) {
                    hideModal($heldModal);
                }
            }
            refreshHeldCount();
        } catch (err) {
            showPosError(err?.response?.data?.message || 'Could not discard.');
        }
    }

    $holdBtn?.addEventListener('click', () => {
        if (cart.size === 0) {
            showPosError('Cart is empty.', 'Nothing to hold');
            return;
        }
        if ($holdLabelInput) $holdLabelInput.value = '';
        if ($holdLabelModal) {
            showModal($holdLabelModal);
            $holdLabelInput?.focus();
            return;
        }
        holdCurrentCart();
    });
    $heldBtn?.addEventListener('click', () => {
        loadHeldList();
        showModal($heldModal);
    });
    $heldList?.addEventListener('click', async (e) => {
        const resume = e.target.closest('.pos-held-resume');
        const del    = e.target.closest('.pos-held-del');
        const resumeItem = e.target.closest('.pos-held-resume-item');
        const discardItem = e.target.closest('.pos-held-discard-item');
        if (resume) {
            const heldId = resume.dataset.id;
            // Only drop the held row once the resume is actually applied. If the
            // cashier cancels the "Replace cart?" prompt, the held sale stays put.
            resumeHeldPayload(resume.dataset.payload, async () => {
                try { await axios.delete(parkedDeleteUrlT.replace('__ID__', encodeURIComponent(heldId))); } catch (_) {}
                refreshHeldCount();
                hideModal($heldModal);
            });
        } else if (resumeItem) {
            await resumeHeldItem(
                resumeItem.dataset.id,
                resumeItem.dataset.payload,
                parseInt(resumeItem.dataset.index, 10),
            );
        } else if (discardItem) {
            showConfirmModal({
                title: 'Discard held item',
                message: 'Discard this item from the held sale?',
                confirmLabel: 'Discard item',
                confirmClass: 'btn-danger',
                onConfirm: async () => {
                    await discardHeldItem(
                        discardItem.dataset.id,
                        discardItem.dataset.payload,
                        parseInt(discardItem.dataset.index, 10),
                    );
                    hideModal($confirmModal);
                },
            });
        } else if (del) {
            showConfirmModal({
                title: 'Discard held sale',
                message: 'Discard this held sale?',
                confirmLabel: 'Discard',
                confirmClass: 'btn-danger',
                onConfirm: async () => {
                    await deleteHeld(del.dataset.id);
                    hideModal($confirmModal);
                },
            });
        }
    });

    $confirmOkBtn?.addEventListener('click', async () => {
        const action = confirmState.onConfirm;
        if (typeof action !== 'function') {
            hideModal($confirmModal);
            return;
        }

        $confirmOkBtn.disabled = true;
        try {
            await action();
        } finally {
            $confirmOkBtn.disabled = false;
            confirmState.onConfirm = null;
        }
    });

    if ($confirmModal) {
        $confirmModal.addEventListener('hidden.bs.modal', () => {
            confirmState.onConfirm = null;
            if ($confirmOkBtn) {
                $confirmOkBtn.disabled = false;
                $confirmOkBtn.textContent = 'Confirm';
                $confirmOkBtn.className = 'btn btn-primary';
            }
            if ($confirmStatus) {
                $confirmStatus.className = 'modal-status bg-primary';
            }
        });
    }

    $holdLabelSaveBtn?.addEventListener('click', holdCurrentCart);
    $holdLabelInput?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            holdCurrentCart();
        }
    });
    $onlineGenerate?.addEventListener('click', generateOnlineSession);
    $onlineCancelBtn?.addEventListener('click', () => {
        cancelOnlineSession();
        setPaymentMethod('cash');
    });
    $onlineUrl?.addEventListener('focus', () => $onlineUrl.select());

    $catRow?.addEventListener('click', (e) => {
        const chip = e.target.closest('.pos-chip');
        if (!chip) return;
        const id = chip.dataset.categoryId ? parseInt(chip.dataset.categoryId, 10) : null;
        setCategory(id);
    });

    $loadMoreBtn?.addEventListener('click', loadMore);

    $grid?.addEventListener('click', (e) => {
        const card = e.target.closest('.pos-product-card');
        if (!card || card.disabled) return;
        const productId = parseInt(card.dataset.productId, 10);
        if (productId) openCustomizeFor(productId);
    });

    $cartList?.addEventListener('click', (e) => {
        const inc = e.target.closest('.pos-qty-inc');
        const dec = e.target.closest('.pos-qty-dec');
        const rm  = e.target.closest('.pos-remove');
        const ed  = e.target.closest('.pos-edit');
        if (inc) changeQty(inc.dataset.sig, +1);
        else if (dec) changeQty(dec.dataset.sig, -1);
        else if (rm) removeFromCart(rm.dataset.sig);
        else if (ed) editCartRow(ed.dataset.sig);
    });

    $custModalBody?.addEventListener('change', (e) => {
        if (e.target.matches('input[name="pos-variant"]')) {
            customizeState.variantId = parseInt(e.target.value, 10);
            customizeState.groupChoices = {};
            renderCustomize();
            return;
        }
        if (e.target.matches('.pos-addon-input')) {
            const groupId = parseInt(e.target.dataset.groupId, 10);
            const itemId  = parseInt(e.target.value, 10);
            const isRadio = e.target.type === 'radio';
            if (!customizeState.groupChoices[groupId]) customizeState.groupChoices[groupId] = new Set();
            if (isRadio) {
                customizeState.groupChoices[groupId] = new Set([itemId]);
            } else {
                if (e.target.checked) customizeState.groupChoices[groupId].add(itemId);
                else customizeState.groupChoices[groupId].delete(itemId);
            }
            recomputeCustomizeTotal();
        }
    });

    $custAddBtn?.addEventListener('click', commitCustomize);

    $checkoutBtn?.addEventListener('click', () => {
        if (cart.size === 0) return;
        $checkoutError?.classList.add('d-none');
        recomputeChange();
        if (paymentState.method === 'upi') renderUpiPanel();
        showModal($modalEl);
    });

    $custTabs?.addEventListener('click', (e) => {
        const btn = e.target.closest('.nav-link[data-mode]');
        if (btn) setCustomerMode(btn.dataset.mode);
    });

    $newToggleBtn?.addEventListener('click', () => {
        if ($newForm) $newForm.classList.toggle('d-none');
    });
    $newRegBtn?.addEventListener('click', registerNewCustomer);
    $completeBtn?.addEventListener('click', completeSale);
    $cashReceived?.addEventListener('input', recomputeChange);
    document.querySelectorAll('.pos-quick-tender').forEach(b => b.addEventListener('click', () => applyQuickTender(b.dataset.tender)));
    $fullscreenBtn?.addEventListener('click', toggleFullscreen);

    // â”€â”€ Global keyboard shortcuts â”€â”€
    //   /     â†’ focus product-title search
    //   F2    â†’ focus barcode scanner field (also Ctrl/Cmd+B as fallback)
    //   Esc   â†’ exit fullscreen / blur the focused field
    // Skipped while the user is already typing in another input or textarea
    // (so "/" works inside search itself), and while a modal is open so we
    // don't yank focus from a checkout / refund flow.
    function isTypingTarget(el) {
        if (!el) return false;
        const tag = (el.tagName || '').toLowerCase();
        if (tag === 'input' || tag === 'textarea' || tag === 'select') return true;
        return !!el.isContentEditable;

    }
    function isAnyModalOpen() {
        return !!document.querySelector('.modal.show');
    }
    document.addEventListener('keydown', (e) => {
        // Esc handlers always run.
        if (e.key === 'Escape') {
            if (document.body.classList.contains('pos-fullscreen')) {
                toggleFullscreen();
                return;
            }
            // Blur a focused POS toolbar input so the cashier can use shortcuts again.
            const ae = document.activeElement;
            if (ae === $search || ae === $barcode) ae.blur();
            return;
        }
        if (isTypingTarget(e.target) || isAnyModalOpen()) return;

        // "/" → focus search. Use code 'Slash' so it works on layouts where
        // shift+/ produces a different character.
        if (e.key === '/' && !e.metaKey && !e.ctrlKey && !e.altKey) {
            if ($search) {
                e.preventDefault();
                $search.focus();
                $search.select();
            }
            return;
        }
        // F2 → focus barcode (POS-industry convention). Also accept
        // Ctrl/Cmd+B as a fallback for laptops without F-keys.
        if (e.key === 'F2' || ((e.ctrlKey || e.metaKey) && (e.key === 'b' || e.key === 'B'))) {
            if ($barcode) {
                e.preventDefault();
                $barcode.focus();
                $barcode.select();
            }
        }
    });

    // Surface the shortcut hint inside the input placeholders so the
    // cashier discovers them without docs.
    if ($search  && !$search.placeholder.includes('/'))   $search.placeholder  = $search.placeholder.replace(/…?\s*$/, '…  ( / )');
    if ($barcode && !$barcode.placeholder.includes('F2')) $barcode.placeholder = $barcode.placeholder.replace(/…?\s*$/, '…  ( F2 )');

    // Inline clear-X buttons on search + barcode inputs. Visible while the
    // field has any value, hidden when empty. Clicking clears the input,
    // refocuses, and fires an "input" event so any debounced search /
    // barcode handler re-runs (resetting the result list).
    document.querySelectorAll('.pos-input-clear').forEach((btn) => {
        const targetId = btn.dataset.target;
        const $input = document.getElementById(targetId);
        if (!$input) return;

        const sync = () => btn.classList.toggle('d-none', !$input.value);
        $input.addEventListener('input', sync);
        $input.addEventListener('change', sync);
        sync();   // initial state — handle pre-populated values

        btn.addEventListener('click', () => {
            $input.value = '';
            $input.dispatchEvent(new Event('input', { bubbles: true }));
            $input.focus();
            btn.classList.add('d-none');
        });
    });

    if (initialStoreId && $store) $store.value = String(initialStoreId);
    refreshPaymentMethodAvailability();
    updateCompleteButtonLabel();
    refreshHeldCount();
    fetchProducts({ append: false });
    restorePersistedDraft();

    window.__POS = {
        getCart: () => Array.from(cart.values()),
        getStoreId: () => parseInt($store?.value, 10) || 0,
        clear: () => {
            cart.clear();
            clearCartAdjustments();
            clearPersistedDraft();
            renderCart();
        },
    };
})();
