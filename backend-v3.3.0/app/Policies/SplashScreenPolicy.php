<?php

namespace App\Policies;

use App\Enums\AdminPermissionEnum;
use App\Models\SplashScreen;
use App\Models\User;
use App\Traits\ChecksPermissions;

class SplashScreenPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission(AdminPermissionEnum::SPLASH_SCREEN_VIEW());
    }

    public function create(User $user): bool
    {
        return $this->hasPermission(AdminPermissionEnum::SPLASH_SCREEN_CREATE());
    }

    public function update(User $user, SplashScreen|string|null $screen = null): bool
    {
        return $this->hasPermission(AdminPermissionEnum::SPLASH_SCREEN_EDIT());
    }

    public function delete(User $user, SplashScreen $screen): bool
    {
        return $this->hasPermission(AdminPermissionEnum::SPLASH_SCREEN_DELETE());
    }
}
