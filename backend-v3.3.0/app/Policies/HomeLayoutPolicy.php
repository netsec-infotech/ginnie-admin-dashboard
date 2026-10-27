<?php

namespace App\Policies;

use App\Enums\AdminPermissionEnum;
use App\Models\User;
use App\Traits\ChecksPermissions;

class HomeLayoutPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        try {
            return $this->hasPermission(AdminPermissionEnum::HOME_BUILDER_VIEW());
        } catch (\Exception) {
            return false;
        }
    }

    public function update(User $user): bool
    {
        try {
            return $this->hasPermission(AdminPermissionEnum::HOME_BUILDER_EDIT());
        } catch (\Exception) {
            return false;
        }
    }
}
