<?php

namespace App\Policies;

use App\Enums\AdminPermissionEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Models\GlobalProductAttribute;
use App\Models\User;
use App\Traits\ChecksPermissions;

class GlobalAttributePolicy
{
    use ChecksPermissions;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        try {
            // Allow Admin panel management
            if ($this->getPanel() === 'admin') {
                return $user->hasRole(DefaultSystemRolesEnum::ADMIN()) ||
                    $user->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN()) ||
                    $this->hasPermission(AdminPermissionEnum::PRODUCT_VIEW());
            }

            // Only sellers with a valid seller record can view in seller panel
            if ($user->seller() == null) {
                return false;
            }

            // Must have a seller role or explicit permission
            if (
                $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
                $this->hasPermission(SellerPermissionEnum::ATTRIBUTE_VIEW())
            ) {
                return true;
            }

            return false;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, GlobalProductAttribute $attribute): bool
    {
        if ($this->getPanel() === 'admin') {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        try {
            // Allow Admin panel creation
            if ($this->getPanel() === 'admin') {
                return $user->hasRole(DefaultSystemRolesEnum::ADMIN()) ||
                    $user->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN()) ||
                    $this->hasPermission(AdminPermissionEnum::PRODUCT_CREATE());
            }

            // Only sellers with a valid seller record can create in seller panel
            if ($user->seller() == null) {
                return false;
            }

            // Must have a seller role or explicit permission
            if (
                $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
                $this->hasPermission(SellerPermissionEnum::ATTRIBUTE_CREATE())
            ) {
                return true;
            }

            return false;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, GlobalProductAttribute $attribute): bool
    {
        try {
            // Allow Admin panel update
            if ($this->getPanel() === 'admin') {
                return $user->hasRole(DefaultSystemRolesEnum::ADMIN()) ||
                    $user->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN()) ||
                    $this->hasPermission(AdminPermissionEnum::PRODUCT_EDIT());
            }

            // Only the seller who owns the product can update it
            if ($user->seller() == null) {
                return false;
            }

            // Check if the user is the owner
            if ($user->seller()->id == $attribute->seller_id) {
                // Check role or permission
                if (
                    $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
                    $this->hasPermission(SellerPermissionEnum::ATTRIBUTE_EDIT())
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
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, GlobalProductAttribute $attribute): bool
    {
        try {
            // Allow Admin panel delete
            if ($this->getPanel() === 'admin') {
                return $user->hasRole(DefaultSystemRolesEnum::ADMIN()) ||
                    $user->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN()) ||
                    $this->hasPermission(AdminPermissionEnum::PRODUCT_DELETE());
            }

            // Only the seller who owns the product can delete it
            if ($user->seller() == null) {
                return false;
            }

            // Check if the user is the owner
            if ($user->seller()->id == $attribute->seller_id) {
                // Check role or permission
                if (
                    $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
                    $this->hasPermission(SellerPermissionEnum::ATTRIBUTE_DELETE())
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
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, GlobalProductAttribute $attribute): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, GlobalProductAttribute $attribute): bool
    {
        return false;
    }
}
