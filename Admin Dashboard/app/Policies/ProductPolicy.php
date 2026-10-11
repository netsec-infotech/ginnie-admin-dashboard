<?php

namespace App\Policies;

use App\Enums\AdminPermissionEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Models\Product;
use App\Models\User;
use App\Traits\ChecksPermissions;
use Illuminate\Auth\Access\Response;

class ProductPolicy
{
    use ChecksPermissions;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        try {
            if ($this->getPanel() === 'admin') {
                return $this->hasPermission(AdminPermissionEnum::PRODUCT_VIEW());
            }

            if ($user->hasRole(DefaultSystemRolesEnum::SELLER())) {
                return true;
            }

            return $this->hasPermission(SellerPermissionEnum::PRODUCT_VIEW());
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Product $product): bool
    {
        try {
            if ($this->getPanel() === 'admin') {
                return $this->hasPermission(AdminPermissionEnum::PRODUCT_VIEW());
            }

            if ($user->seller() === null) {
                return false;
            }

            if ((int) $user->seller()->id !== (int) $product->seller_id) {
                return false;
            }

            return $user->hasRole(DefaultSystemRolesEnum::SELLER())
                || $this->hasPermission(SellerPermissionEnum::PRODUCT_VIEW());
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        try {
            if ($this->getPanel() === 'admin') {
                return $this->hasPermission(AdminPermissionEnum::PRODUCT_CREATE());
            }

            if ($user->seller() === null) {
                return false;
            }

            return $user->hasRole(DefaultSystemRolesEnum::SELLER())
                || $this->hasPermission(SellerPermissionEnum::PRODUCT_CREATE());
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Product $product): bool
    {
        try {
            if ($this->getPanel() === 'admin') {
                return $this->hasPermission(AdminPermissionEnum::PRODUCT_EDIT());
            }

            if ($user->seller() === null) {
                return false;
            }

            if ((int) $user->seller()->id !== (int) $product->seller_id) {
                return false;
            }

            return $user->hasRole(DefaultSystemRolesEnum::SELLER())
                || $this->hasPermission(SellerPermissionEnum::PRODUCT_EDIT());
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Product $product): bool
    {
        try {
            if ($this->getPanel() === 'admin') {
                return $this->hasPermission(AdminPermissionEnum::PRODUCT_DELETE());
            }

            if ($user->seller() === null) {
                return false;
            }

            if ((int) $user->seller()->id !== (int) $product->seller_id) {
                return false;
            }

            return $user->hasRole(DefaultSystemRolesEnum::SELLER())
                || $this->hasPermission(SellerPermissionEnum::PRODUCT_DELETE());
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Product $product): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Product $product): bool
    {
        return false;
    }

    public function verifyProduct(User $user): bool
    {
        try {
            return $this->hasPermission(AdminPermissionEnum::PRODUCT_STATUS_UPDATE());
        } catch (\Exception) {
            return false;
        }
    }
}
