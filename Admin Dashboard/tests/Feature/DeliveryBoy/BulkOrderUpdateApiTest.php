<?php

/**
 * Bulk Collect All and Deliver All API coverage for Delivery Boy.
 *
 * Scenarios covered at the service & resource level:
 *   - Status hierarchy transitions and resource mapping (COLLECTED -> OUT_FOR_DELIVERY)
 *   - Idempotency checks when items are already collected or delivered
 *   - Store-scoped bulk pickup vs Order-scoped bulk dropoff
 *
 * Integration scenarios pending Order/OrderItem/Store factories:
 */

todo('PUT /orders/{orderId}/collect-all marks all store items as COLLECTED and promotes order to OUT_FOR_DELIVERY');

todo('PUT /orders/{orderId}/collect-all in multi-store order only collects specified store items and sets order to COLLECTED');

todo('PUT /orders/{orderId}/collect-all returns 200 OK idempotently when items are already collected');

todo('PUT /orders/{orderId}/collect-all fails when store_id is missing or invalid');

todo('PUT /orders/{orderId}/deliver-all marks all collected items across all stores as DELIVERED');

todo('PUT /orders/{orderId}/deliver-all credits seller statement for each delivered item');

todo('PUT /orders/{orderId}/deliver-all completes COD payment status and fires OrderDelivered event');

todo('PUT /orders/{orderId}/deliver-all returns 200 OK idempotently when items are already delivered');

todo('PUT /orders/{orderId}/deliver-all fails when OTP is required but missing or invalid');
