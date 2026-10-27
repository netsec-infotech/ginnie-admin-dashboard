<?php

namespace App\Services;

use App\Enums\Subscription\SubscriptionPlanKeyEnum;
use App\Models\Seller;
use App\Models\SellerUser;
use App\Models\User;
use App\Traits\SubscriptionLimitGuard;
use Illuminate\Support\Facades\DB;

class AccountDeletionService
{
    use SubscriptionLimitGuard;

    public function __construct(private readonly StoreService $storeService) {}

    public function hasSellerAccount(User $user): bool
    {
        return Seller::query()->where('user_id', $user->id)->exists()
            || SellerUser::query()->where('user_id', $user->id)->exists();
    }

    public function delete(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user = User::query()->whereKey($user->getKey())->lockForUpdate()->first();
            if (! $user) {
                return;
            }

            $ownedSellers = Seller::query()->where('user_id', $user->id)->get();
            $linkedSellerIds = SellerUser::query()
                ->where('user_id', $user->id)
                ->distinct()
                ->pluck('seller_id');

            foreach ($ownedSellers as $seller) {
                if ($this->storeService->hasActiveOrdersForSeller($seller)) {
                    throw new \RuntimeException(__('labels.cannot_delete_seller_with_active_orders'));
                }
            }

            foreach ($ownedSellers as $seller) {
                $seller->delete();
                $seller->media()->each(function ($media): void {
                    $media->delete();
                });
            }

            SellerUser::query()->where('user_id', $user->id)->delete();

            foreach ($linkedSellerIds as $sellerId) {
                $this->reduceUsageIfMultivendor((int) $sellerId, SubscriptionPlanKeyEnum::SYSTEM_USER_LIMIT());
            }

            $this->removePermissionAssignments($user);
            $user->tokens()->delete();
            $user->delete();
        });
    }

    /**
     * Remove assignments from every seller team, regardless of the active permissions team.
     */
    private function removePermissionAssignments(User $user): void
    {
        foreach (['model_has_roles', 'model_has_permissions'] as $tableName) {
            DB::table(config("permission.table_names.{$tableName}"))
                ->where('model_type', $user->getMorphClass())
                ->where(config('permission.column_names.model_morph_key'), $user->getKey())
                ->delete();
        }
    }
}
