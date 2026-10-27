<?php

namespace App\Policies;

use App\Enums\AdminPermissionEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Models\GlobalProductAttributeValue;
use App\Models\User;
use App\Traits\ChecksPermissions;

class GlobalAttributeValuePolicy
{
    use ChecksPermissions;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if ($this->getPanel() === 'admin') {
            return $user->hasRole(DefaultSystemRolesEnum::ADMIN()) ||
                $user->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN()) ||
                $this->hasPermission(AdminPermissionEnum::PRODUCT_VIEW());
        }

        if ($user->hasRole(DefaultSystemRolesEnum::SELLER())) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, GlobalProductAttributeValue $attributeValue): bool
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
    public function update(User $user, GlobalProductAttributeValue $attributeValue): bool
    {
        try {
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
            if ($user->seller()->id == $attributeValue->attribute->seller_id) {
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
    public function delete(User $user, GlobalProductAttributeValue $attributeValue): bool
    {
        try {
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
            if ($user->seller()->id == $attributeValue->attribute->seller_id) {
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
    public function restore(User $user, GlobalProductAttributeValue $attributeValue): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, GlobalProductAttributeValue $attributeValue): bool
    {
        return false;
    }
}
