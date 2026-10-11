<?php

/**
 * Phase 1D — seller cancel-post-accept and confirm-store-return flows.
 *
 * Full integration tests for these methods need a multi-vendor order factory
 * chain (Order → OrderItem → SellerOrder → SellerOrderItem with a real Store
 * + ProductVariant + stock rows). That factory work is its own effort beyond
 * the scope of Phase 1D, so this file holds todo() markers for each scenario
 * we want covered. Each one will become a real `it(...)` once the factories
 * land.
 *
 * Service-level coverage: see app/Services/OrderService.php
 *   - cancelOrderItemBySeller()
 *   - confirmStoreReturn()
 *
 * Policy-level coverage: app/Policies/OrderPolicy.php — cancelItem(), confirmReturn()
 */

todo('cancelOrderItemBySeller cancels an ACCEPTED item, refunds the customer wallet, restocks via the listener, increments seller.post_accept_cancel_count');

todo('cancelOrderItemBySeller refuses when the item is COLLECTED or beyond');

todo('cancelOrderItemBySeller unassigns the rider only when no live items remain in the order');

todo('cancelOrderItemBySeller leaves the rider in place when other items are still live');

todo('cancelOrderItemBySeller refuses when the seller does not own the item');

todo('confirmStoreReturn finalises a RETURNING_TO_STORE item to CANCELLED with the carried-forward reason');

todo('confirmStoreReturn refuses when the item is not in RETURNING_TO_STORE');

todo('confirmStoreReturn refuses when the seller does not own the item');

todo('OrderPolicy::cancelItem permits the seller-owner role even without ORDER_CANCEL_POST_ACCEPT permission');

todo('OrderPolicy::cancelItem rejects when the item status is past PREPARING');

todo('OrderPolicy::confirmReturn rejects when the item status is not RETURNING_TO_STORE');
