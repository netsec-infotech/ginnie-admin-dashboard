@extends('layouts.seller.app', [
    'page' => $menuSeller['pos']['active'] ?? '',
    'sub_page' => $menuSeller['pos']['route']['pos_overview']['sub_active'] ?? '',
])

@section('title', __('labels.pos'))

@section('header_data')
    @php
        $page_title = __('labels.pos');
        $page_pretitle = __('labels.seller') . ' ' . __('labels.pos');
    @endphp
@endsection

@section('seller-content')
    <div class="container-xl pos-shell">

        @if($stores->isEmpty())
            <div class="empty">
                <div class="empty-img">
                    <svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 21l18 0"/><path d="M3 7v1a3 3 0 0 0 6 0v-1m0 1a3 3 0 0 0 6 0v-1m0 1a3 3 0 0 0 6 0v-1h-18l2 -4h14l2 4"/>
                        <path d="M5 21l0 -10.15"/><path d="M19 21l0 -10.15"/>
                    </svg>
                </div>
                <p class="empty-title">No approved store yet</p>
                <p class="empty-subtitle text-secondary">POS will be available once at least one of your stores is approved and visible.</p>
            </div>
        @else
            @php
                $defaultStore = $stores->first();
                $posCurrencyCode = strtoupper((string) ($defaultStore->currency_code ?? ($systemSettings['currency'] ?? '')));
                $posCurrencyLabel = \App\Support\Currency\CurrencyDisplay::label($posCurrencyCode, $systemSettings['currencySymbol'] ?? null);
                $posZero = \App\Support\Currency\CurrencyDisplay::zero($posCurrencyCode, $systemSettings['currencySymbol'] ?? null);
            @endphp

            {{-- ── Sticky toolbar ── --}}
            <div class="pos-toolbar mb-3">
                <div class="row g-2 align-items-center">
                    <div class="col-md-3 col-lg-3">
                        <div class="input-icon">
                            <span class="input-icon-addon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="icon"><path d="M3 21l18 0"/><path d="M3 7v1a3 3 0 0 0 6 0v-1m0 1a3 3 0 0 0 6 0v-1m0 1a3 3 0 0 0 6 0v-1h-18l2 -4h14l2 4"/><path d="M5 21l0 -10.15"/><path d="M19 21l0 -10.15"/><path d="M9 21v-4a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v4"/></svg>
                            </span>
                            <select id="pos-store-select" class="form-select ps-5" aria-label="Store">
                                @foreach($stores as $store)
                                    <option value="{{ $store->id }}" data-currency="{{ $store->currency_code }}" data-currency-symbol="{{ \App\Support\Currency\CurrencyDisplay::label($store->currency_code, $systemSettings['currencySymbol'] ?? null) }}">{{ $store->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4 col-lg-4">
                        <div class="input-icon">
                            <span class="input-icon-addon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="icon"><path d="M21 21l-6 -6"/><path d="M10 17a7 7 0 1 0 0 -14a7 7 0 0 0 0 14"/></svg>
                            </span>
                            <input type="search" id="pos-search-input" class="form-control ps-5 pe-5"
                                   placeholder="Search products by title…" autocomplete="off" aria-label="Search products">
                            <button type="button" class="pos-input-clear d-none" data-target="pos-search-input" title="Clear search" aria-label="Clear search">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6l-12 12"/><path d="M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-3 col-lg-3">
                        <div class="input-icon">
                            <span class="input-icon-addon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="icon"><path d="M4 7v-2a1 1 0 0 1 1 -1h2"/><path d="M4 17v2a1 1 0 0 0 1 1h2"/><path d="M16 4h2a1 1 0 0 1 1 1v2"/><path d="M16 20h2a1 1 0 0 0 1 -1v-2"/><path d="M5 11h1v2h-1z" fill="currentColor"/><path d="M10 11l0 2"/><path d="M14 11h1v2h-1z" fill="currentColor"/><path d="M19 11l0 2"/></svg>
                            </span>
                            <input type="text" id="pos-barcode-input" class="form-control ps-5 pe-5"
                                   placeholder="Scan barcode…" autocomplete="off" aria-label="Scan barcode"
                                   inputmode="numeric">
                            <button type="button" class="pos-input-clear d-none" data-target="pos-barcode-input" title="Clear" aria-label="Clear">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6l-12 12"/><path d="M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-2 col-lg-2 d-flex justify-content-end gap-1 pos-action-bar">
                        {{-- Secondary actions (Held / Recent / Display) live in a single
                             dropdown so 4 buttons don't crowd the toolbar on 13–14" laptops.
                             IDs are preserved so existing JS click handlers keep working. --}}
                        <div class="dropdown">
                            <button type="button" class="btn btn-outline-secondary position-relative" data-bs-toggle="dropdown" aria-expanded="false" title="More actions" aria-label="More actions">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="icon"><circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/></svg>
                                <span class="badge bg-warning text-dark ms-1 d-none" id="pos-held-count">0</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <button type="button" id="pos-held-btn" class="dropdown-item d-flex align-items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="icon"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>
                                        Held sales
                                    </button>
                                </li>
                                <li>
                                    <button type="button" id="pos-recent-btn" class="dropdown-item d-flex align-items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="icon"><path d="M3 12a9 9 0 1 0 3 -6.7l-3 2.7"/><path d="M3 4v4h4"/><path d="M12 8v4l3 2"/></svg>
                                        Recent sales
                                    </button>
                                </li>
                                <li>
                                    <button type="button" id="pos-cfd-btn" class="dropdown-item d-flex align-items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="icon"><rect x="3" y="5" width="18" height="12" rx="1"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                                        Customer display
                                    </button>
                                </li>
                            </ul>
                        </div>
                        <button type="button" id="pos-fullscreen-btn" class="btn btn-outline-secondary" title="Toggle distraction-free mode" aria-label="Focus mode">
                            <svg class="pos-fullscreen-on icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8v-2a2 2 0 0 1 2 -2h2"/><path d="M4 16v2a2 2 0 0 0 2 2h2"/><path d="M16 4h2a2 2 0 0 1 2 2v2"/><path d="M16 20h2a2 2 0 0 0 2 -2v-2"/></svg>
                            <svg class="pos-fullscreen-off icon d-none" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M5 9l4 0l0 -4"/><path d="M3 3l6 6"/><path d="M5 15l4 0l0 4"/><path d="M3 21l6 -6"/><path d="M19 9l-4 0l0 -4"/><path d="M15 9l6 -6"/><path d="M19 15l-4 0l0 4"/><path d="M15 15l6 6"/></svg>
                            <span class="pos-fullscreen-on">Focus</span>
                            <span class="pos-fullscreen-off d-none">Exit</span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- ── Category chips ── --}}
            <div id="pos-category-row" class="pos-chip-row mb-3">
                <button type="button" class="pos-chip active" data-category-id="">All</button>
                @foreach($categories as $cat)
                    <button type="button" class="pos-chip" data-category-id="{{ $cat->id }}">{{ $cat->getTranslation('title', 'en', false) }}</button>
                @endforeach
            </div>

            <div class="row g-3">
                {{-- ── Product grid ── --}}
                <div class="col-12 col-lg-8">
                    <div id="pos-product-grid" class="row g-2"></div>

                    <div id="pos-product-empty" class="empty py-5 d-none">
                        <div class="empty-icon">
                            {{-- Magnifier-with-dots: universal "searched, nothing found".
                                 Reads cleaner than the prior building-store-off icon
                                 which looked like sunglasses on small renders. --}}
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="10" cy="10" r="7"/>
                                <path d="M21 21l-6 -6"/>
                                <circle cx="7"  cy="10" r=".5" fill="currentColor"/>
                                <circle cx="10" cy="10" r=".5" fill="currentColor"/>
                                <circle cx="13" cy="10" r=".5" fill="currentColor"/>
                            </svg>
                        </div>
                        <p class="empty-title">No products to show</p>
                        <p class="empty-subtitle text-secondary" id="pos-product-empty-hint">Try a different search or category.</p>
                    </div>

                    <div id="pos-loadmore-wrap" class="text-center mt-3 d-none">
                        <span class="text-secondary small me-2" id="pos-loadmore-info"></span>
                        <button type="button" id="pos-loadmore-btn" class="btn btn-outline-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="icon me-1"><path d="M6 9l6 6l6 -6"/></svg>
                            Load more
                        </button>
                    </div>
                </div>

                {{-- ── Cart ── --}}
                <div class="col-12 col-lg-4">
                    <div class="card pos-cart sticky-lg-top">
                        <div class="card-header py-2 align-items-center">
                            <span class="pos-cart-icon me-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/><path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/><path d="M17 17h-11v-14h-2"/><path d="M6 5l14 1l-1 7h-13"/></svg>
                            </span>
                            <h3 class="card-title mb-0">Cart</h3>
                            <span class="ms-auto text-secondary small" id="pos-cart-meta">empty</span>
                            <button type="button" id="pos-cart-clear-btn" class="btn btn-link btn-sm text-secondary p-0 ms-2 d-none" title="Clear cart">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7l16 0"/><path d="M10 11l0 6"/><path d="M14 11l0 6"/><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"/><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"/></svg>
                            </button>
                        </div>

                        {{-- Customer chip — visible at all times so the cashier can attach a
                             customer BEFORE ringing up items. Drives the same customerState
                             that the checkout modal's Customer pane reads on open, so the
                             two surfaces stay consistent. --}}
                        <div class="pos-cart-customer">
                            <button type="button" id="pos-cart-customer-btn" class="pos-cart-customer-chip" title="Attach a customer to this sale">
                                <span class="pos-cart-customer-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="7" r="4"/><path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2"/></svg>
                                </span>
                                <span class="pos-cart-customer-label">
                                    <span id="pos-cart-customer-name">Walk-in customer</span>
                                    <span id="pos-cart-customer-meta" class="text-secondary small d-none"></span>
                                </span>
                                <span id="pos-cart-customer-detach" class="pos-cart-customer-detach d-none" title="Detach customer" aria-label="Detach customer">×</span>
                            </button>
                        </div>

                        <div id="pos-cart-empty" class="card-body text-center py-4">
                            <div class="text-secondary mb-1">Your cart is empty</div>
                            <div class="text-secondary small">Tap a product to add it.</div>
                        </div>

                        <div id="pos-cart-list" class="list-group list-group-flush"></div>

                        {{-- Subtotal / tax / savings / discount summary block.
                             Hidden when cart is empty. Discount input is the
                             cashier's bill-level adjustment — % or fixed. --}}
                        <div id="pos-cart-summary" class="card-body py-2 d-none">
                            <div class="pos-summary-line">
                                <span class="text-secondary">Subtotal</span>
                                <span id="pos-cart-subtotal">{{ $posZero }}</span>
                            </div>
                            {{-- Per-rate tax rows (populated by JS). Tax-inclusive prices
                                 are the project default — these rows show what's already
                                 baked into the subtotal so the cashier and customer can see
                                 "Tax @ 5%: ₹X" instead of an opaque combined number. --}}
                            <div id="pos-cart-tax-rows"></div>
                            <div class="pos-summary-line d-none" id="pos-cart-savings-row">
                                <span class="text-success small">You saved</span>
                                <span class="text-success small" id="pos-cart-savings">{{ $posZero }}</span>
                            </div>
                            <div class="small text-secondary d-none" id="pos-cart-savings-note">
                                Savings shown here come from the item's special price and are already included in the total.
                            </div>

                            <div class="pos-summary-line d-none" id="pos-cart-discount-row">
                                <span class="text-secondary">
                                    Discount
                                    <span class="text-secondary small ms-1" id="pos-cart-discount-label"></span>
                                </span>
                                <span>
                                    <span class="text-danger" id="pos-cart-discount-amt">−{{ $posZero }}</span>
                                    <button type="button" class="btn btn-link btn-sm text-secondary p-0 ms-1" id="pos-cart-discount-clear" title="Remove discount">×</button>
                                </span>
                            </div>

                            <div class="pos-summary-line d-none" id="pos-cart-wallet-row">
                                <span class="text-secondary">Wallet</span>
                                <span class="text-primary" id="pos-cart-wallet-amt">−{{ $posZero }}</span>
                            </div>

                            {{-- Promo applied row (separate from cashier discount). --}}
                            <div class="pos-summary-line d-none" id="pos-cart-promo-row">
                                <span class="text-secondary">
                                    Promo
                                    <span class="text-secondary small ms-1" id="pos-cart-promo-code"></span>
                                </span>
                                <span>
                                    <span class="text-danger" id="pos-cart-promo-amt">−{{ $posZero }}</span>
                                    <button type="button" class="btn btn-link btn-sm text-secondary p-0 ms-1" id="pos-cart-promo-clear" title="Remove promo">×</button>
                                </span>
                            </div>

                            <div class="pos-discount-toggle mt-1 d-flex gap-3">
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" id="pos-discount-toggle-btn">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="me-1"><path d="M9 14l6 -6"/><circle cx="9.5" cy="8.5" r=".5"/><circle cx="14.5" cy="13.5" r=".5"/></svg>
                                    Apply discount
                                </button>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" id="pos-promo-toggle-btn">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="me-1"><path d="M5 7h-2a2 2 0 0 0 -2 2v6a2 2 0 0 0 2 2h2"/><path d="M19 7h2a2 2 0 0 1 2 2v6a2 2 0 0 1 -2 2h-2"/><rect x="5" y="5" width="14" height="14" rx="2"/></svg>
                                    Promo code
                                </button>
                            </div>

                            <div class="pos-discount-form d-none mt-2" id="pos-promo-form">
                                <div class="input-group">
                                    <input type="text" class="form-control" id="pos-promo-input" placeholder="Enter code" autocomplete="off" maxlength="64">
                                    <button type="button" class="btn btn-primary" id="pos-promo-apply-btn">Apply</button>
                                    <button type="button" class="btn btn-outline-secondary" id="pos-promo-cancel-btn" title="Hide">×</button>
                                </div>
                                <small class="form-hint d-block mt-1" id="pos-promo-hint">Re-validated server-side at checkout.</small>
                            </div>

                            <div class="pos-discount-form d-none mt-2" id="pos-discount-form">
                                <div class="input-group">
                                    <button type="button" class="btn btn-outline-secondary pos-discount-type" data-type="percent" id="pos-discount-type-pct">%</button>
                                    <button type="button" class="btn btn-outline-secondary pos-discount-type active" data-type="fixed" id="pos-discount-type-fixed">{{ $posCurrencyLabel }} </button>
                                    <input type="number" class="form-control" id="pos-discount-input" inputmode="decimal" step="0.01" min="0" placeholder="0.00">
                                    <button type="button" class="btn btn-primary" id="pos-discount-apply-btn">Apply</button>
                                    <button type="button" class="btn btn-outline-secondary" id="pos-discount-cancel-btn" title="Hide" aria-label="Hide">×</button>
                                </div>
                                <small class="form-hint d-block mt-1" id="pos-discount-hint">Cashier discount, capped at the subtotal.</small>
                            </div>
                        </div>

                        <div class="card-footer py-2 d-flex align-items-baseline">
                            <span class="text-secondary">Total</span>
                            <span class="ms-auto h2 mb-0" id="pos-cart-total">{{ $posZero }}</span>
                        </div>
                        <div class="card-footer py-2 d-flex gap-2">
                            <button type="button" id="pos-hold-btn" class="btn btn-outline-secondary flex-shrink-0" disabled title="Hold this cart and start a new sale">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="icon"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>
                                Hold
                            </button>
                            <button type="button" id="pos-checkout-btn" class="btn btn-primary flex-fill" disabled>
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="icon me-1"><path d="M5 12l5 5l10 -10"/></svg>
                                Checkout
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Variant + Addon picker modal ── --}}
            <div class="modal modal-blur fade" id="pos-customize-modal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header pos-customize-header">
                            <span class="pos-customize-thumb" id="pos-customize-thumb"></span>
                            <h5 class="modal-title flex-fill" id="pos-customize-title">Customize</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" id="pos-customize-body">
                            {{-- Populated by JS — variant picker + addon groups --}}
                        </div>
                        <div class="modal-footer">
                            <span class="me-auto">
                                <span class="text-secondary small d-block">Line total</span>
                                <span class="h3 mb-0" id="pos-customize-line-total">0.00</span>
                            </span>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" id="pos-customize-add-btn" class="btn btn-primary">Add to cart</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Checkout modal ── --}}
            <div class="modal modal-blur fade" id="pos-checkout-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Complete sale</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body pos-checkout-body">

                            {{-- ① Order summary --}}
                            <section class="pos-section">
                                <div class="pos-section-label">Order</div>
                                <div id="pos-modal-summary" class="pos-summary"></div>
                                <div class="pos-summary-total">
                                    <span class="text-secondary">Total</span>
                                    <span class="h2 mb-0" id="pos-modal-total-money">{{ $posZero }}</span>
                                </div>
                            </section>

                            {{-- ② Customer --}}
                            <section class="pos-section">
                                <div class="pos-section-label">Customer</div>
                                <ul class="nav nav-pills mb-2" id="pos-customer-tabs" role="tablist">
                                    <li class="nav-item"><button class="nav-link active" data-mode="walkin"   type="button">Walk-in</button></li>
                                    <li class="nav-item"><button class="nav-link"        data-mode="existing" type="button">Customer</button></li>
                                </ul>

                                <div class="pos-customer-pane" data-pane="walkin">
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <input type="text" class="form-control" id="pos-walkin-name" maxlength="255" placeholder="Name (optional)">
                                        </div>
                                        <div class="col-md-2">
                                            <input type="text" class="form-control pos-cc-only" id="pos-walkin-cc" inputmode="tel" maxlength="10" placeholder="+91">
                                        </div>
                                        <div class="col-md-4">
                                            <input type="text" class="form-control pos-numeric-only" id="pos-walkin-mobile" maxlength="32" inputmode="numeric" pattern="[0-9]*" placeholder="Mobile (optional)">
                                        </div>
                                    </div>
                                </div>

                                <div class="pos-customer-pane d-none" data-pane="existing">
                                    <select id="pos-customer-search" class="form-select" placeholder="Search by name, mobile, or email…" autocomplete="off"></select>
                                    <div id="pos-customer-selected" class="alert alert-success mt-2 mb-0 d-none" role="alert"></div>

                                    {{-- Wallet snapshot --}}
                                    <div id="pos-wallet-block" class="pos-wallet-block d-none mt-3 p-3">
                                        <div class="d-flex align-items-center mb-2">
                                            <strong>Wallet balance</strong>
                                            <span class="ms-auto h4 mb-0 text-primary" id="pos-wallet-balance">—</span>
                                        </div>
                                        <div class="row g-2 align-items-end">
                                            <div class="col-md-7">
                                                <label class="form-label small text-secondary mb-1">Apply to this sale</label>
                                                <input type="number" class="form-control" id="pos-wallet-input" inputmode="decimal" step="0.01" min="0" placeholder="0.00">
                                            </div>
                                            <div class="col-md-5 d-flex gap-1">
                                                <button type="button" class="btn btn-sm btn-outline-secondary flex-fill" id="pos-wallet-max-btn">Use max</button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary flex-fill" id="pos-wallet-clear-btn">Clear</button>
                                            </div>
                                        </div>
                                        <div class="form-hint small mt-1" id="pos-wallet-hint">Capped at customer's balance and the remaining bill total.</div>
                                    </div>

                                    {{-- Inline register (collapsed by default) --}}
                                    <div class="mt-3">
                                        <button type="button" id="pos-new-toggle-btn" class="btn btn-sm btn-outline-primary">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-user-plus" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0"/><path d="M16 19h6"/><path d="M19 16v6"/><path d="M6 21v-2a4 4 0 0 1 4 -4h4"/></svg>
                                            Register new customer
                                        </button>
                                        <div id="pos-new-form" class="d-none mt-2">
                                            <div class="row g-2">
                                                <div class="col-md-6">
                                                    <input type="text" class="form-control" id="pos-new-name" maxlength="255" placeholder="Name *">
                                                </div>
                                                <div class="col-md-3">
                                                    <input type="text" class="form-control pos-cc-only" id="pos-new-cc" inputmode="tel" placeholder="+91 *" maxlength="10">
                                                </div>
                                                <div class="col-md-3">
                                                    <input type="text" class="form-control pos-numeric-only" id="pos-new-mobile" maxlength="32" inputmode="numeric" pattern="[0-9]*" placeholder="Mobile *">
                                                </div>
                                                <div class="col-md-12">
                                                    <input type="email" class="form-control" id="pos-new-email" maxlength="255" placeholder="Email (optional)">
                                                </div>
                                            </div>
                                            <button type="button" id="pos-new-register-btn" class="btn btn-sm btn-primary mt-2">Register & select</button>
                                            <div id="pos-new-result" class="mt-2"></div>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            {{-- ③ Payment --}}
                            <section class="pos-section">
                                <div class="pos-section-label">Payment</div>

                                <ul class="nav nav-pills mb-3 flex-wrap" id="pos-payment-tabs" role="tablist">
                                    <li class="nav-item" data-pm="cash"><button class="nav-link active" data-method="cash" type="button">Cash</button></li>
                                    <li class="nav-item" data-pm="upi"><button class="nav-link" data-method="upi" type="button" id="pos-payment-tab-upi">UPI <small class="text-muted">(India)</small></button></li>
                                    <li class="nav-item" data-pm="online"><button class="nav-link" data-method="online" type="button" id="pos-payment-tab-online">Online QR</button></li>
                                    <li class="nav-item" data-pm="split"><button class="nav-link" data-method="split" type="button" id="pos-payment-tab-split">Split</button></li>
                                </ul>

                                {{-- Cash pane --}}
                                <div class="pos-payment-pane" data-pane="cash">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-5">
                                            <label class="form-label small text-secondary mb-1">Cash received</label>
                                            <div class="input-group">
                                                <span class="input-group-text" id="pos-cash-symbol">{{ $posCurrencyLabel }} </span>
                                                <input type="number" class="form-control" id="pos-cash-received"
                                                       inputmode="decimal" step="0.01" min="0" placeholder="0.00">
                                            </div>
                                        </div>
                                        <div class="col-md-7">
                                            <div class="d-flex flex-wrap gap-1">
                                                <button type="button" class="btn btn-sm btn-outline-secondary pos-quick-tender" data-tender="exact">Exact</button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary pos-quick-tender" data-tender="100">+100</button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary pos-quick-tender" data-tender="500">+500</button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary pos-quick-tender" data-tender="1000">+1000</button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary pos-quick-tender" data-tender="round-up">Round up</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-baseline mt-2">
                                        <span class="text-secondary">Change due</span>
                                        <span class="ms-auto h3 mb-0" id="pos-change-due">{{ $posZero }}</span>
                                    </div>
                                    <div class="small text-danger mt-1 d-none" id="pos-cash-short">Short by <span id="pos-cash-short-amt"></span></div>
                                </div>

                                {{-- Online (Razorpay) pane: cashier creates a session on first
                                     visit to this tab; QR encodes a public URL the customer scans
                                     from any phone. Razorpay's webhook + customer-side verify call
                                     are both wired — whichever fires first promotes the session
                                     into a real Order. --}}
                                <div class="pos-payment-pane d-none" data-pane="online">
                                    <div id="pos-online-idle" class="">
                                        <p class="text-secondary small mb-2">
                                            Customer scans a QR from any phone, pays with Razorpay (UPI / cards / netbanking).
                                            Payment is auto-confirmed — no manual verification.
                                        </p>
                                        <button type="button" id="pos-online-generate-btn" class="btn btn-outline-primary">
                                            Generate payment QR
                                        </button>
                                    </div>

                                    <div id="pos-online-active" class="d-none">
                                        <div class="row g-3 align-items-start">
                                            <div class="col-sm-auto text-center">
                                                <div class="pos-upi-qr" id="pos-online-qr">
                                                    <div class="text-secondary small">QR…</div>
                                                </div>
                                                <div class="text-secondary small mt-1">Expires in <span id="pos-online-countdown">15:00</span></div>
                                            </div>
                                            <div class="col">
                                                <div class="d-flex align-items-center mb-1">
                                                    <span class="status-dot pulse me-2"></span>
                                                    <strong id="pos-online-status-text">Waiting for customer to scan…</strong>
                                                </div>
                                                <div class="text-secondary small mb-2">
                                                    Tab-close is blocked while this session is active.
                                                </div>
                                                <div class="text-secondary small">Amount</div>
                                                <div class="h3 mb-2" id="pos-online-amount">—</div>
                                                <div class="text-secondary small">Or share this link</div>
                                                <input type="text" id="pos-online-url" class="form-control form-control-sm mb-2" readonly>
                                                <button type="button" id="pos-online-cancel-btn" class="btn btn-sm btn-outline-danger">
                                                    Cancel & switch to cash
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="pos-online-error" class="alert alert-danger d-none mb-0" role="alert"></div>
                                </div>

                                {{-- Split tender (cash + online QR) --}}
                                <div class="pos-payment-pane d-none" data-pane="split">
                                    <div id="pos-split-idle">
                                        <p class="text-secondary small mb-2">
                                            Enter how much the customer is paying in cash. The remainder will be charged via Online QR (Razorpay).
                                        </p>
                                        <div class="row g-2 align-items-end">
                                            <div class="col-md-6">
                                                <label class="form-label small text-secondary mb-1">Cash portion</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">{{ $posCurrencyLabel }} </span>
                                                    <input type="number" class="form-control" id="pos-split-cash-input"
                                                           inputmode="decimal" step="0.01" min="0" placeholder="0.00">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small text-secondary mb-1">Online portion</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">{{ $posCurrencyLabel }} </span>
                                                    <input type="number" class="form-control" id="pos-split-online-input" readonly placeholder="0.00">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-baseline mt-2">
                                            <span class="text-secondary small">Total bill</span>
                                            <span class="ms-auto fw-medium" id="pos-split-total">{{ $posZero }}</span>
                                        </div>
                                        <button type="button" id="pos-split-generate-btn" class="btn btn-outline-primary mt-2" disabled>
                                            Collect cash &amp; generate QR for online
                                        </button>
                                        <div id="pos-split-error" class="alert alert-danger mt-2 d-none mb-0" role="alert"></div>
                                    </div>

                                    <div id="pos-split-active" class="d-none">
                                        <div class="row g-3 align-items-start">
                                            <div class="col-sm-auto text-center">
                                                <div class="pos-upi-qr" id="pos-split-qr">
                                                    <div class="text-secondary small">QR…</div>
                                                </div>
                                                <div class="text-secondary small mt-1">Expires in <span id="pos-split-countdown">15:00</span></div>
                                            </div>
                                            <div class="col">
                                                <div class="d-flex align-items-center mb-1">
                                                    <span class="status-dot pulse me-2"></span>
                                                    <strong id="pos-split-status-text">Waiting for online payment…</strong>
                                                </div>
                                                <div class="text-secondary small mb-2">
                                                    Make sure cash is collected before customer pays online.
                                                </div>
                                                <div class="text-secondary small">Cash collected</div>
                                                <div class="h4 mb-2" id="pos-split-cash-shown">—</div>
                                                <div class="text-secondary small">Pay online</div>
                                                <div class="h3 mb-2" id="pos-split-online-shown">—</div>
                                                <button type="button" id="pos-split-cancel-btn" class="btn btn-sm btn-outline-danger">
                                                    Cancel split &amp; switch to cash
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- UPI pane --}}
                                <div class="pos-payment-pane d-none" data-pane="upi">
                                    <div id="pos-upi-not-configured" class="alert alert-warning d-none mb-0" role="alert">
                                        Online QR is not configured for this store. Set the UPI VPA in store settings to enable it.
                                    </div>
                                    <div id="pos-upi-active" class="d-none">
                                        <div class="row g-3 align-items-center">
                                            <div class="col-sm-auto text-center">
                                                <div class="pos-upi-qr" id="pos-upi-qr">
                                                    <div class="text-secondary small">QR…</div>
                                                </div>
                                            </div>
                                            <div class="col">
                                                <div class="text-secondary small">Pay to</div>
                                                <div class="fw-medium" id="pos-upi-payee">—</div>
                                                <div class="text-secondary small mt-1" id="pos-upi-vpa">—</div>
                                                <div class="text-secondary small mt-2">Amount</div>
                                                <div class="h3 mb-0" id="pos-upi-amount">—</div>
                                                <div class="form-hint mt-2">
                                                    Customer scans this QR from any UPI app (GPay / PhonePe / Paytm / BHIM).
                                                    After it lands in your UPI app, tap <strong>Mark as paid</strong>.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Custom payment method pane (dynamically populated by JS) --}}
                                <div class="pos-payment-pane d-none" data-pane="custom" id="pos-custom-method-pane">
                                    <div class="text-center py-2">
                                        <div class="mb-2">
                                            <span id="pos-custom-icon" class="pos-custom-icon"></span>
                                        </div>
                                        <div class="fw-medium mb-1" id="pos-custom-label"></div>
                                        <div class="text-secondary small mb-2" id="pos-custom-instructions"></div>
                                        <div class="text-secondary small mt-2">Amount</div>
                                        <div class="h3 mb-0" id="pos-custom-amount">—</div>
                                    </div>
                                </div>
                            </section>

                            {{-- ④ Note --}}
                            <section class="pos-section pos-section-last">
                                <div class="pos-section-label">Note <span class="text-secondary fw-normal">(optional)</span></div>
                                <textarea class="form-control" id="pos-order-note" rows="2" maxlength="500" placeholder="Any internal note…"></textarea>
                            </section>

                            <div class="alert alert-danger mt-2 d-none" id="pos-checkout-error" role="alert"></div>
                        </div>
                        <div class="modal-footer">
                            <span class="me-auto">
                                <span class="text-secondary small d-block">Total</span>
                                <span class="h3 mb-0" id="pos-modal-total">{{ $posZero }}</span>
                            </span>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="pos-complete-sale-btn">Complete sale</button>
                        </div>
                    </div>
                </div>
            </div>
            {{-- ── Held sales modal ── --}}
            <div class="modal modal-blur fade" id="pos-held-modal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Held sales</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-secondary small mb-2">
                                Carts you set aside. Resume a sale to load it back; stock is re-checked at checkout.
                            </p>
                            <div id="pos-held-loading" class="text-secondary small py-3 text-center">Loading…</div>
                            <div id="pos-held-empty" class="empty py-4 d-none">
                                <p class="empty-title">Nothing on hold</p>
                                <p class="empty-subtitle text-secondary">Click <strong>Hold</strong> on a cart to set it aside.</p>
                            </div>
                            <div class="list-group d-none" id="pos-held-list"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- POS confirmation modal --}}
            <div class="modal modal-blur fade" id="pos-confirm-modal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-sm modal-dialog-centered">
                    <div class="modal-content">
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        <div class="modal-status bg-primary" id="pos-confirm-status"></div>
                        <div class="modal-body text-center py-4">
                            <h3 class="mb-2" id="pos-confirm-title">Confirm action</h3>
                            <p class="mb-0 text-secondary" id="pos-confirm-message">Are you sure?</p>
                        </div>
                        <div class="modal-footer">
                            <div class="w-100 d-flex justify-content-center gap-2">
                                <div class="row">
                                    <div class="col">
                                        <button type="button" class="btn btn-outline-secondary w-100" data-bs-dismiss="modal" id="pos-confirm-cancel-btn">Cancel</button>
                                    </div>
                                    <div class="col">
                                        <button type="button" class="btn btn-sm btn-primary w-100" id="pos-confirm-ok-btn">Confirm</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- POS hold label modal --}}
            <div class="modal modal-blur fade" id="pos-hold-label-modal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-sm modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Hold sale</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <label for="pos-hold-label-input" class="form-label">Label</label>
                            <input type="text" class="form-control" id="pos-hold-label-input" maxlength="120" placeholder="Optional short label">
                            <div class="form-hint mt-2">This helps you recognize the held cart later.</div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-sm btn-primary" id="pos-hold-label-save-btn">Hold cart</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Recent orders modal ── --}}
            <div class="modal modal-blur fade" id="pos-recent-modal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Recent POS sales</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="d-flex align-items-center mb-2 flex-wrap gap-2">
                                <p class="text-secondary small mb-0 me-auto">
                                    Most recent in-store sales for your stores. Use Reprint if a receipt didn't make it to the printer.
                                </p>
                                <a href="{{ route('seller.orders.index', ['type' => 'pos']) }}" target="_blank" rel="noopener" class="btn btn-sm btn-link p-0 ms-2 text-decoration-none">
                                    All orders
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="ms-1"><path d="M11 7h-5a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-5"/><path d="M10 14l10 -10"/><path d="M15 4h5v5"/></svg>
                                </a>
                            </div>

                            <div class="row g-2 align-items-center mb-2">
                                <div class="col-md-7">
                                    <div class="input-icon">
                                        <span class="input-icon-addon">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="icon"><path d="M21 21l-6 -6"/><path d="M10 17a7 7 0 1 0 0 -14a7 7 0 0 0 0 14"/></svg>
                                        </span>
                                        <input type="search" id="pos-recent-search" class="form-control ps-5" placeholder="Search by order #, customer, payment, or total…" autocomplete="off">
                                    </div>
                                </div>
                                <div class="col-md-5 d-flex justify-content-md-end">
                                    <select id="pos-recent-limit" class="form-select form-select-sm">
                                        <option value="25">25 per page</option>
                                        <option value="50" selected>50 per page</option>
                                        <option value="100">100 per page</option>
                                        <option value="200">200 per page</option>
                                    </select>
                                </div>
                            </div>

                            <div id="pos-recent-loading" class="text-secondary small py-3 text-center">Loading…</div>
                            <div id="pos-recent-empty" class="empty py-4 d-none">
                                <p class="empty-title">No POS sales yet</p>
                                <p class="empty-subtitle text-secondary">Once you complete sales, they'll show up here for quick reprinting.</p>
                            </div>
                            <div class="table-responsive d-none" id="pos-recent-table-wrap">
                                <table class="table table-vcenter card-table pos-recent-table">
                                    <thead>
                                        <tr>
                                            <th class="pos-sortable" data-sort="id">Order <span class="pos-sort-icon"></span></th>
                                            <th class="pos-sortable" data-sort="created_at">When <span class="pos-sort-icon"></span></th>
                                            <th>Customer</th>
                                            <th>Payment</th>
                                            <th class="text-end pos-sortable" data-sort="final_total">Total <span class="pos-sort-icon"></span></th>
                                            <th class="text-end pos-recent-actions-col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="pos-recent-rows"></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <span class="text-secondary small me-auto" id="pos-recent-count"></span>
                            <button type="button" id="pos-recent-refresh-btn" class="btn btn-outline-secondary">Refresh</button>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Quick-attach customer modal ── --}}
            <div class="modal modal-blur fade" id="pos-attach-customer-modal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Attach customer</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            {{-- Phone-first lookup: cashier types phone and matching customers
                                 appear instantly. Falls back to name/email match too — the
                                 backend search already covers all three. --}}
                            <label for="pos-attach-search" class="form-label">Phone number</label>
                            <div class="input-icon">
                                <span class="input-icon-addon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="icon"><path d="M5 4h4l2 5l-2.5 1.5a11 11 0 0 0 5 5l1.5 -2.5l5 2v4a2 2 0 0 1 -2 2a16 16 0 0 1 -15 -15a2 2 0 0 1 2 -2"/></svg>
                                </span>
                                <input type="search" id="pos-attach-search" class="form-control ps-5" inputmode="tel" placeholder="Type phone number, name or email…" autocomplete="off" autofocus>
                            </div>
                            <div id="pos-attach-results" class="list-group mt-3"></div>
                            <div class="text-center mt-3">
                                <button type="button" id="pos-attach-new-toggle" class="btn btn-link btn-sm text-decoration-none">
                                    + Register new customer
                                </button>
                            </div>
                            <div id="pos-attach-new-form" class="mt-2 d-none">
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <input type="text" class="form-control" id="pos-attach-new-name" maxlength="255" placeholder="Name *">
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" class="form-control pos-cc-only" id="pos-attach-new-cc" inputmode="tel" placeholder="+91 *" maxlength="10">
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" class="form-control pos-numeric-only" id="pos-attach-new-mobile" maxlength="32" inputmode="numeric" pattern="[0-9]*" placeholder="Mobile *">
                                    </div>
                                    <div class="col-md-12">
                                        <input type="email" class="form-control" id="pos-attach-new-email" maxlength="255" placeholder="Email (optional)">
                                    </div>
                                </div>
                                <button type="button" id="pos-attach-new-submit" class="btn btn-primary mt-2">Register &amp; attach</button>
                                <div id="pos-attach-new-result" class="mt-2"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Refund modal ── --}}
            <div class="modal modal-blur fade" id="pos-refund-modal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Refund sale <span class="text-secondary fw-normal" id="pos-refund-order-label"></span></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div id="pos-refund-loading" class="text-secondary py-3">Loading…</div>
                            <div id="pos-refund-empty" class="empty py-4 d-none">
                                <p class="empty-title">Nothing to refund</p>
                                <p class="empty-subtitle text-secondary">Every item from this sale has already been refunded.</p>
                            </div>
                            <div id="pos-refund-already" class="alert alert-info d-none mb-3"></div>
                            <div id="pos-refund-form-wrap" class="d-none">
                                <p class="text-secondary small mb-2">
                                    Enter the quantity you want to refund per item. This restores stock and records an audit row, but does not call the payment gateway — refund the customer manually with the configured method.
                                </p>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th class="text-center pos-w-sold-col">Sold</th>
                                                <th class="text-center pos-w-refund-col">Refund qty</th>
                                                <th class="text-end pos-w-amount-col">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody id="pos-refund-rows"></tbody>
                                    </table>
                                </div>
                                <div class="mt-3">
                                    <label class="form-label">{{ __('labels.pos_refund_method_label') }}</label>
                                    <div class="btn-group w-100" role="group" aria-label="Refund method">
                                        <input type="radio" class="btn-check" name="pos-refund-method" id="pos-refund-method-cash" value="cash" checked>
                                        <label class="btn btn-outline-secondary" for="pos-refund-method-cash">{{ __('labels.pos_refund_cash') }}</label>
                                        <input type="radio" class="btn-check" name="pos-refund-method" id="pos-refund-method-other" value="other">
                                        <label class="btn btn-outline-secondary" for="pos-refund-method-other">{{ __('labels.pos_refund_other') }}</label>
                                    </div>
                                    <input type="text" id="pos-refund-method-note" class="form-control form-control-sm mt-2" maxlength="200" placeholder="{{ __('labels.pos_refund_note_placeholder') }}">
                                </div>
                                <div class="mt-3">
                                    <label for="pos-refund-reason" class="form-label">{{ __('labels.pos_refund_reason_label') }} <span class="text-secondary">({{ __('labels.pos_refund_reason_hint') }})</span></label>
                                    <textarea id="pos-refund-reason" class="form-control" rows="2" maxlength="500" placeholder="{{ __('labels.pos_refund_reason_placeholder') }}"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <div class="me-auto">
                                <span class="text-secondary small">Refund total</span>
                                <span class="h4 mb-0 ms-2" id="pos-refund-total">{{ $posZero }}</span>
                            </div>
                            <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" id="pos-refund-submit" class="btn btn-danger" disabled>
                                Refund
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Refund history modal (read-only audit trail per order) ── --}}
            <div class="modal modal-blur fade" id="pos-refund-history-modal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Refund history <span class="text-secondary fw-normal" id="pos-refund-history-order-label"></span></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div id="pos-refund-history-loading" class="text-secondary py-3">Loading…</div>
                            <div id="pos-refund-history-empty" class="empty py-4 d-none">
                                <p class="empty-title">No refunds</p>
                                <p class="empty-subtitle text-secondary">Nothing has been refunded against this order yet.</p>
                            </div>
                            <div id="pos-refund-history-summary" class="alert alert-info d-none mb-3"></div>
                            <div id="pos-refund-history-list" class="d-flex flex-column gap-3"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

    </div>

        <link href="{{ hyperAsset('assets/seller/css/pos-index.css') }}" rel="stylesheet">
    {{-- @endif --}}
@endsection

@if(!$stores->isEmpty())
    @push('scripts')
        @php
            $defaultStore = $stores->first();
            $storePaymentConfig = $stores->mapWithKeys(function ($s) {
                $cfg = $s->pos_payment_config ?? [];
                return [$s->id => [
                    'upi_vpa'        => $s->pos_upi_vpa,
                    'upi_payee_name' => $s->pos_upi_payee_name ?: $s->name,
                    'currency_code'  => $s->currency_code,
                    'currency_symbol'=> \App\Support\Currency\CurrencyDisplay::label($s->currency_code, $systemSettings['currencySymbol'] ?? null),
                    'cash'           => filter_var($cfg['cash'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'upi'            => filter_var($cfg['upi'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'online_qr'      => filter_var($cfg['online_qr'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'split'          => filter_var($cfg['split'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'custom_methods' => collect($cfg['custom_methods'] ?? [])->filter(fn($m) => filter_var($m['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN))->values()->all(),
                ]];
            });
            $storeDisplayMeta = $stores->mapWithKeys(fn ($s) => [$s->id => [
                'logo' => $s->store_logo,
            ]]);
        @endphp
        <script>
            window.__POS_CONFIG__ = {
                productsUrl:           "{{ route('seller.pos.api.products.search') }}",
                barcodeLookupUrl:      "{{ route('seller.pos.api.products.barcode') }}",
                categoriesUrl:         "{{ route('seller.pos.api.categories.index') }}",
                customersSearchUrl:    "{{ route('seller.pos.api.customers.search') }}",
                usersSearchUrl:        "{{ route('seller.pos.api.users.search') }}",
                customersRegisterUrl:  "{{ route('seller.pos.api.customers.register') }}",
                ordersCreateUrl:       "{{ route('seller.pos.api.orders.create') }}",
                ordersRecentUrl:       "{{ route('seller.pos.api.orders.recent') }}",
                promoValidateUrl:      "{{ route('seller.pos.api.promo.validate') }}",
                walletLookupUrlT:      "{{ route('seller.pos.api.customers.wallet', ['id' => '__ID__']) }}",
                cfdPushUrl:            "{{ route('seller.pos.api.cfd.push') }}",
                cfdShowUrlT:           "{{ url('/pos/display') }}/__TOKEN__",
                parkedListUrl:         "{{ route('seller.pos.api.parked.list') }}",
                parkedCreateUrl:       "{{ route('seller.pos.api.parked.create') }}",
                parkedUpdateUrlT:      "{{ route('seller.pos.api.parked.update', ['id' => '__ID__']) }}",
                parkedDeleteUrlT:      "{{ route('seller.pos.api.parked.delete', ['id' => '__ID__']) }}",
                paymentSessionsUrl:    "{{ route('seller.pos.api.payment_sessions.create') }}",
                paymentSessionStatusUrlTemplate: "{{ route('seller.pos.api.payment_sessions.status', ['token' => '__TOKEN__']) }}",
                paymentSessionCancelUrlTemplate: "{{ route('seller.pos.api.payment_sessions.cancel', ['token' => '__TOKEN__']) }}",
                refundPreviewUrlT:     "{{ route('seller.pos.api.refunds.preview', ['id' => '__ID__']) }}",
                refundCreateUrlT:      "{{ route('seller.pos.api.refunds.create', ['id' => '__ID__']) }}",
                refundHistoryUrlT:     "{{ route('seller.pos.api.refunds.history', ['id' => '__ID__']) }}",
                receiptUrlTemplate:    "{{ route('seller.pos.orders.receipt', ['id' => '__ID__']) }}",
                initialStoreId:        {{ (int) $defaultStore->id }},
                currencyCode:          @json($defaultStore->currency_code ?? 'USD'),
                currencySymbol:        @json($posCurrencyLabel),
                storePaymentConfig:    @json($storePaymentConfig),
                storeDisplayMeta:      @json($storeDisplayMeta)
            };
        </script>
        <script src="{{ asset('assets/vendor/qrcode/qrcode.min.js') }}"></script>
        <script src="{{ hyperAsset('assets/js/seller-pos.js') }}" defer></script>
    @endpush
@endif
