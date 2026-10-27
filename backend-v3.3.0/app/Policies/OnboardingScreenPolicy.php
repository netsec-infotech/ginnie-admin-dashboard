<?php

namespace App\Policies;

use App\Enums\AdminPermissionEnum;
use App\Models\OnboardingScreen;
use App\Models\User;
use App\Traits\ChecksPermissions;

class OnboardingScreenPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission(AdminPermissionEnum::ONBOARDING_SCREEN_VIEW());
    }

    public function create(User $user): bool
    {
        return $this->hasPermission(AdminPermissionEnum::ONBOARDING_SCREEN_CREATE());
    }

    public function update(User $user, OnboardingScreen|string|null $screen = null): bool
    {
        return $this->hasPermission(AdminPermissionEnum::ONBOARDING_SCREEN_EDIT());
    }

    public function delete(User $user, OnboardingScreen $screen): bool
    {
        return $this->hasPermission(AdminPermissionEnum::ONBOARDING_SCREEN_DELETE());
    }
}
