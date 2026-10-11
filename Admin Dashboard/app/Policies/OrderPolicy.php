<?php

namespace App\Policies;

use App\Enums\AdminPermissionEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\SellerPermissionEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerOrder;
use App\Models\SellerOrderItem;
use App\Models\User;
use App\Traits\ChecksPermissions;

class OrderPolicy
{
    use ChecksPermissions;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Only the seller who owns the order can view it
        if ($user->seller() == null) {
            return $this->hasPermission(AdminPermissionEnum::ORDER_VIEW());
        }

        // Check role or permission
        if (
            $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
            $this->hasPermission(SellerPermissionEnum::ORDER_VIEW())
        ) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, $order): bool
    {
        try {
            // Only the seller who owns the order can view it
            if ($user->seller() == null) {
                return $this->hasPermission(AdminPermissionEnum::ORDER_VIEW());
            }

            // Check if the user is the owner
            if ($user->seller()->id == $order->seller_id) {
                // Check role or permission
                if (
                    $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
                    $this->hasPermission(SellerPermissionEnum::ORDER_VIEW())
                ) {
                    return true;
                }
            }

            return false;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SellerOrder $order): bool
    {
        try {
            // Admin path — gated by the Phase 3 ORDER_EDIT permission. Previously
            // this returned false outright for non-sellers, locking admins out
            // of order management entirely (BUG-6 in the lifecycle plan).
            if ($user->seller() === null) {
                return $this->hasPermission(AdminPermissionEnum::ORDER_EDIT());
            }

            // Seller path — owner of the order, with role-or-permission bypass.
            if ($user->seller()->id == $order->seller_id) {
                if (
                    $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
                    $this->hasPermission(SellerPermissionEnum::ORDER_EDIT())
                ) {
                    return true;
                }
            }

            return false;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can manage pickup actions (mark ready, verify OTP) for a seller order.
     */
    public function pickupManage(User $user, SellerOrder $order): bool
    {
        try {
            if ($user->seller() === null) {
                return false;
            }

            if ($user->seller()->id == $order->seller_id) {
                if (
                    $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
                    $this->hasPermission(SellerPermissionEnum::PICKUP_MANAGE())
                ) {
                    return true;
                }
            }

            return false;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can update the status of an order item.
     */
    public function updateStatus(User $user, SellerOrderItem $orderItem): bool
    {
        try {
            // Admin path — single ORDER_EDIT permission gates every admin write.
            if ($user->seller() === null) {
                return $this->hasPermission(AdminPermissionEnum::ORDER_EDIT());
            }

            // Seller path — must own the order.
            if ($user->seller()->id == $orderItem->sellerOrder->seller_id) {
                if (
                    $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
                    $this->hasPermission(SellerPermissionEnum::ORDER_UPDATE_STATUS())
                ) {
                    return true;
                }
            }

            return false;
        } catch (\Exception) {
            return false;
        }
    }

    public function viewInvoice(User $user, $orderData): bool
    {
        try {
            if ($this->hasPermission(AdminPermissionEnum::ORDER_VIEW())) {
                return true;
            }
            if ($orderData['user_id'] == $user->id) {
                return true;
            }
            $seller = $user->seller();
            if ($seller !== null) {
                $orderId = $orderData['id'] ?? null;
                if ($orderId) {
                    $hasSellerOrder = SellerOrder::where('order_id', $orderId)
                        ->where('seller_id', $seller->id)
                        ->exists();
                    if ($hasSellerOrder && (
                        $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
                        $this->hasPermission(SellerPermissionEnum::ORDER_VIEW())
                    )) {
                        return true;
                    }
                }
            }

            return false;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Phase 1D — seller cancelling an item they had already accepted.
     * Pre-collect only.
     * Phase 3 — admin path opened up via the unified ORDER_EDIT permission.
     */
    public function cancelItem(User $user, OrderItem $orderItem): bool
    {
        try {
            // Admin path — bypasses pre-collect window. See forceCancel() too.
            if ($user->seller() === null) {
                return $this->hasPermission(AdminPermissionEnum::ORDER_EDIT());
            }

            $belongs = SellerOrderItem::where('order_item_id', $orderItem->id)
                ->whereHas('sellerOrder', fn($q) => $q->where('seller_id', $user->seller()->id))
                ->exists();
            if (!$belongs) {
                return false;
            }

            if (!in_array($orderItem->status, [
                OrderItemStatusEnum::ACCEPTED(),
                OrderItemStatusEnum::PREPARING(),
            ], true)) {
                return false;
            }

            return $user->hasRole(DefaultSystemRolesEnum::SELLER())
                || $this->hasPermission(SellerPermissionEnum::ORDER_CANCEL_POST_ACCEPT());
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Phase 1D — seller confirms physical receipt of returning items.
     */
    public function confirmReturn(User $user, OrderItem $orderItem): bool
    {
        try {
            if ($user->seller() === null) {
                return false;
            }

            $belongs = SellerOrderItem::where('order_item_id', $orderItem->id)
                ->whereHas('sellerOrder', fn($q) => $q->where('seller_id', $user->seller()->id))
                ->exists();
            if (!$belongs) {
                return false;
            }

            if ($orderItem->status !== OrderItemStatusEnum::RETURNING_TO_STORE()) {
                return false;
            }

            return $user->hasRole(DefaultSystemRolesEnum::SELLER())
                || $this->hasPermission(SellerPermissionEnum::ORDER_CONFIRM_RETURN());
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Phase 3 — every admin override action (force-status, force-cancel,
     * force-refund, reassign-rider, add-note) is gated by the single
     * ORDER_EDIT permission. ORDER_VIEW remains read-only.
     */
    public function forceUpdateStatus(User $user, OrderItem|Order $orderItem): bool
    {
        return $this->adminCanEditOrder($user);
    }

    public function forceCancel(User $user, OrderItem $orderItem): bool
    {
        return $this->adminCanEditOrder($user);
    }

    public function forceRefund(User $user, $order): bool
    {
        return $this->adminCanEditOrder($user);
    }

    public function reassignRider(User $user, $order): bool
    {
        return $this->adminCanEditOrder($user);
    }

    public function addNote(User $user, $order): bool
    {
        return $this->adminCanEditOrder($user);
    }

    /**
     * Helper — admin (non-seller) user with the ORDER_EDIT permission.
     */
    private function adminCanEditOrder(User $user): bool
    {
        try {
            return $this->hasPermission(AdminPermissionEnum::ORDER_EDIT());
        } catch (\Exception) {
            return false;
        }
    }
}
