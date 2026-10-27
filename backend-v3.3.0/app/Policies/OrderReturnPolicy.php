<?php

namespace App\Policies;

use App\Enums\AdminPermissionEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\Order\OrderItemReturnStatusEnum;
use App\Enums\SellerPermissionEnum;
use App\Models\OrderItemReturn;
use App\Models\User;
use App\Traits\ChecksPermissions;

class OrderReturnPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        // Branch on the current request's panel — a Super Admin with both an
        // admin role and a seller row must still be treated as admin on
        // /admin/* URLs and as seller on /seller/* URLs.
        if ($this->getPanel() === 'admin') {
            return $this->hasPermission(AdminPermissionEnum::RETURN_VIEW());
        }

        if ($user->seller() === null) {
            return false;
        }

        return $user->hasRole(DefaultSystemRolesEnum::SELLER())
            || $this->hasPermission(SellerPermissionEnum::RETURN_VIEW());
    }

    public function view(User $user, OrderItemReturn $return): bool
    {
        if ($this->getPanel() === 'admin') {
            return $this->hasPermission(AdminPermissionEnum::RETURN_VIEW());
        }

        if ($user->seller() === null || $user->seller()->id !== $return->seller_id) {
            return false;
        }

        return $user->hasRole(DefaultSystemRolesEnum::SELLER())
            || $this->hasPermission(SellerPermissionEnum::RETURN_VIEW());
    }

    public function decide(User $user, OrderItemReturn $return): bool
    {
        try {
            if ($this->getPanel() === 'admin') {
                return $this->hasPermission(AdminPermissionEnum::RETURN_DECIDE());
            }

            if ($user->seller() === null || $user->seller()->id !== $return->seller_id) {
                return false;
            }

            return $user->hasRole(DefaultSystemRolesEnum::SELLER())
                || $this->hasPermission(SellerPermissionEnum::RETURN_DECIDE());
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Manual rider assignment for a return pickup — admin only.
     */
    public function assignRider(User $user, OrderItemReturn $return): bool
    {
        if ($this->getPanel() !== 'admin') {
            return false;
        }

        return $this->hasPermission(AdminPermissionEnum::RETURN_ASSIGN_PICKUP());
    }
}
