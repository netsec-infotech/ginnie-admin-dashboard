<?php

/**
 * Phase 1C — rider drop + delivery-failed end-to-end coverage. Same factory
 * gap as Phase 1A's BUG-3 todo and Phase 1D's cancel/confirm-return tests:
 * needs a real Order → OrderItem → SellerOrder → DeliveryBoyAssignment chain
 * to drive the service methods. Tracking each scenario as a todo so the
 * implementation stays honest about what's covered.
 *
 * Service-level coverage (executed today by 1A/1B unit tests):
 *   - The status-hierarchy transitions (OrderItem::isValidStatusTransition)
 *   - The premature-READY_FOR_PICKUP fix (BUG-2)
 *
 * Integration scenarios pending factories:
 */

todo('dropOrderByDeliveryBoy pre-collect: assignment → DROPPED, order.delivery_boy_id cleared, order → READY_FOR_PICKUP');

todo('dropOrderByDeliveryBoy pre-collect: rider earnings on the assignment are zeroed');

todo('dropOrderByDeliveryBoy pre-collect: drop_count++ and auto-flag fires past threshold');

todo('dropOrderByDeliveryBoy post-collect: refuses with labels.cannot_drop_order_after_item_collected once any item is COLLECTED');

todo('dropOrderByDeliveryBoy post-collect: assignment, order status, and stock are left untouched on a refused drop');

todo('dropOrderByDeliveryBoy refuses when the order is not assigned to this rider');

todo('markDeliveryFailed: COLLECTED item → DELIVERY_FAILED → RETURNING_TO_STORE in a single call');

todo('markDeliveryFailed: rider earnings are preserved (not zeroed)');

todo('markDeliveryFailed: refuses when item is not COLLECTED');

todo('markDeliveryFailed: refuses when reason_code is outside the allowed enum');

todo('OrderStatusUpdatedNotification: assigned rider receives the seller-cancel notification (Phase 1D + 1C interaction)');
