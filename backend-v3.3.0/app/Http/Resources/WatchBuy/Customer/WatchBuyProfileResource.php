<?php

namespace App\Http\Resources\WatchBuy\Customer;

use App\Enums\GuardNameEnum;
use App\Services\WatchBuyService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WatchBuyProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $customer = $request->user('sanctum');
        $customer = $customer?->access_panel === GuardNameEnum::WEB ? $customer : null;
        $config = app(WatchBuyService::class)->config();
        $statusesEnabled = (bool) $config['enabled'] && (bool) $config['statusesEnabled'];
        $hasActiveStatus = $statusesEnabled
            && ($this->status?->value ?? $this->status) === 'active'
            && $this->statusFlag('has_active_status', fn () => $this->resource->statuses()->active()->exists());
        $hasUnseenStatus = $hasActiveStatus && $this->statusFlag(
            'has_unseen_status',
            fn () => ! $customer || $this->resource->statuses()
                ->active()
                ->whereDoesntHave('views', fn ($views) => $views->where('user_id', $customer->id))
                ->exists(),
        );

        $store = null;
        $username = $this->username;
        $photoUrl = $this->photo_url;

        if (($this->profile_type?->value ?? $this->profile_type) !== 'platform') {
            $sellerStores = $this->relationLoaded('seller')
                ? ($this->seller?->relationLoaded('stores') ? $this->seller->stores : $this->seller?->stores()->get())
                : $this->seller?->stores()->get();

            $sellerStore = null;
            if ($sellerStores && $sellerStores->isNotEmpty()) {
                if ($request->filled('store_id')) {
                    $sellerStore = $sellerStores->firstWhere('id', (int) $request->store_id);
                } elseif ($request->filled('store_slug')) {
                    $sellerStore = $sellerStores->firstWhere('slug', (string) $request->store_slug);
                }
                $sellerStore ??= $sellerStores->first();
            }

            if ($sellerStore) {
                $store = [
                    'id' => $sellerStore->id,
                    'name' => $sellerStore->name,
                    'slug' => $sellerStore->slug,
                    'image' => $sellerStore->store_logo,
                ];

                if ($request->routeIs('*statuses.feed*') || $sellerStore->name) {
                    $username = $sellerStore->name ?? $this->username;
                }
                if ($request->routeIs('*statuses.feed*') || $sellerStore->store_logo) {
                    $photoUrl = $sellerStore->store_logo ?? $this->photo_url;
                }
            }
        }

        return [
            'id' => $this->id,
            'type' => $this->profile_type?->value ?? $this->profile_type,
            'username' => $username,
            'bio' => $this->bio,
            'photo_url' => $photoUrl,
            'status' => $this->status?->value ?? $this->status,
            'has_active_status' => $hasActiveStatus,
            'has_unseen_status' => $hasUnseenStatus,
            'store' => $store,
        ];
    }

    private function statusFlag(string $attribute, callable $fallback): bool
    {
        if (array_key_exists($attribute, $this->resource->getAttributes())) {
            return (bool) $this->resource->getAttribute($attribute);
        }

        return (bool) $fallback();
    }
}
