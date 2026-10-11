<?php

namespace App\Services;

use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Seller\SellerSettlementStatusEnum;
use App\Events\Seller\SellerRegistered;
use App\Models\Country;
use App\Models\Notification;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\SellerStatement;
use App\Models\User;
use App\Models\UserFcmToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SellerService
{
    /**
     * Create a new seller
     *
     * @param array $data
     * @param array $files
     * @return Seller
     * @throws \Exception
     */
    public function createSeller(array $data, array $files = []): Seller
    {
        DB::beginTransaction();
        try {
            $user = $this->resolveOrCreateUser($data);

            $sellerData = $this->prepareSellerData($data, $user->id);
            $seller = Seller::create($sellerData);

            $user->assignRole('seller');

            $this->handleMediaUploads($seller, $files);

            DB::commit();

            event(new SellerRegistered($seller, $user));

            return $seller;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Resolve existing user or create new one
     *
     * @param array $data
     * @return User
     * @throws \Exception
     */
    protected function resolveOrCreateUser(array $data): User
    {
        if (isset($data['user_id'])) {
            $user = User::find($data['user_id']);

            if (!$user) {
                throw new \Exception('User not found', 404);
            }

            if (Seller::where('user_id', $user->id)->exists()) {
                throw new \Exception('Seller already exists for this user', 422);
            }

            return $user;
        }

        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'mobile' => $data['mobile'],
            'password' => $data['password'],
            'status' => 'active',
            'access_panel' => 'seller',
        ]);
    }

    /**
     * Prepare seller data for creation
     *
     * @param array $data
     * @param int $userId
     * @return array
     */
    protected function prepareSellerData(array $data, int $userId): array
    {
        $sellerData = collect($data)->except([
            'name', 'email', 'mobile', 'password', 'user_id',
            'business_license', 'articles_of_incorporation',
            'national_identity_card', 'authorized_signature'
        ])->toArray();

        if (isset($sellerData['country'])) {
            $country = Country::where('name', $sellerData['country'])->firstOrFail();
            if (!empty($country->phonecode)) {
                $sellerData['country_code'] = $country->phonecode;
            }
        }

        $sellerData['user_id'] = $userId;

        return $sellerData;
    }

    /**
     * Handle media uploads for seller
     *
     * @param Seller $seller
     * @param array $files
     * @return void
     */
    protected function handleMediaUploads(Seller $seller, array $files): void
    {
        $collections = [
            'business_license',
            'articles_of_incorporation',
            'national_identity_card',
            'authorized_signature'
        ];

        foreach ($collections as $collection) {
            if (isset($files[$collection]) && $files[$collection] instanceof UploadedFile) {
                $seller->addMedia($files[$collection])->toMediaCollection($collection);
            }
        }
    }

    /**
     * @param Seller $seller
     * @return array
     */
    public function getOrderStats(Seller $seller): array
    {
        $stats = SellerOrder::where('seller_orders.seller_id', $seller->id)
            ->join('orders', 'seller_orders.order_id', '=', 'orders.id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN orders.status = ? THEN 1 ELSE 0 END) as delivered", [OrderStatusEnum::DELIVERED()])
            ->selectRaw("SUM(CASE WHEN orders.status = ? THEN 1 ELSE 0 END) as cancelled", [OrderStatusEnum::CANCELLED()])
            ->selectRaw("SUM(CASE WHEN orders.status = ? THEN 1 ELSE 0 END) as pending", [OrderStatusEnum::PENDING()])
            ->selectRaw("SUM(CASE WHEN orders.status NOT IN (?,?,?) THEN 1 ELSE 0 END) as in_progress", [
                OrderStatusEnum::DELIVERED(),
                OrderStatusEnum::CANCELLED(),
                OrderStatusEnum::PENDING(),
            ])
            ->first();

        $total = (int) ($stats->total ?? 0);
        $delivered = (int) ($stats->delivered ?? 0);

        return [
            'total' => $total,
            'delivered' => $delivered,
            'cancelled' => (int) ($stats->cancelled ?? 0),
            'pending' => (int) ($stats->pending ?? 0),
            'in_progress' => (int) ($stats->in_progress ?? 0),
            'completion_rate' => $total > 0 ? round(($delivered / $total) * 100, 1) : 0,
        ];
    }

    /**
     * @param Seller $seller
     * @return array
     */
    public function getEarningsSummary(Seller $seller): array
    {
        $stats = SellerStatement::where('seller_id', $seller->id)
            ->selectRaw('COALESCE(SUM(amount), 0) as total_earnings')
            ->selectRaw('COALESCE(SUM(CASE WHEN settlement_status = ? THEN amount ELSE 0 END), 0) as total_settled', [SellerSettlementStatusEnum::SETTLED()])
            ->selectRaw('COALESCE(SUM(CASE WHEN settlement_status = ? THEN amount ELSE 0 END), 0) as total_pending', [SellerSettlementStatusEnum::PENDING()])
            ->selectRaw('COUNT(*) as total_entries')
            ->first();

        return [
            'total_earnings' => (float) ($stats->total_earnings ?? 0),
            'total_settled' => (float) ($stats->total_settled ?? 0),
            'total_pending' => (float) ($stats->total_pending ?? 0),
            'total_entries' => (int) ($stats->total_entries ?? 0),
        ];
    }

    /**
     * @param Seller $seller
     * @param int $limit
     * @return Collection
     */
    public function getRecentStatements(Seller $seller, int $limit = 10): Collection
    {
        return SellerStatement::where('seller_id', $seller->id)
            ->with('order:id,uuid,slug')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @param Seller $seller
     * @param int $limit
     * @return Collection
     */
    public function getRecentNotifications(Seller $seller, int $limit = 20): Collection
    {
        return Notification::where('notifiable_id', $seller->user_id)
            ->where('notifiable_type', User::class)
            ->roleType(NotificationRoleTypeEnum::SELLER)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @param Seller $seller
     * @return Collection
     */
    public function getFcmTokens(Seller $seller): Collection
    {
        return UserFcmToken::where('user_id', $seller->user_id)
            ->roleType(NotificationRoleTypeEnum::SELLER())
            ->orderByDesc('updated_at')
            ->get();
    }
}
