<?php

namespace App\Services;

use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Enums\SettingTypeEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Enums\Store\StoreVerificationStatusEnum;
use App\Enums\Store\StoreVisibilityStatusEnum;
use App\Enums\WatchBuy\WatchBuyModerationActionEnum;
use App\Enums\WatchBuy\WatchBuyModerationTargetEnum;
use App\Enums\WatchBuy\WatchBuyProfileStatusEnum;
use App\Enums\WatchBuy\WatchBuyProfileTypeEnum;
use App\Enums\WatchBuy\WatchBuyReelStatusEnum;
use App\Enums\WatchBuy\WatchBuySourceEnum;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WatchBuyModerationLog;
use App\Models\WatchBuyProfile;
use App\Models\WatchBuyReel;
use App\Notifications\WatchBuyModerationNotification;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class WatchBuyService
{
    public function __construct(
        private readonly SettingService $settings,
        private readonly WatchBuyMediaInspector $mediaInspector,
    ) {}

    /**
     * @return array{enabled: bool, title: string, maxDurationSeconds: int, maxUploadMb: int, maxProductsPerReel: int, statusesEnabled: bool, maxStatusDurationSeconds: int, maxProductsPerStatus: int, effectiveMaxUploadMb: int}
     */
    public function config(): array
    {
        $config = array_replace([
            'enabled' => true,
            'title' => 'Watch & Buy',
            'maxDurationSeconds' => 60,
            'maxUploadMb' => 50,
            'maxProductsPerReel' => 10,
            'statusesEnabled' => true,
            'maxStatusDurationSeconds' => 15,
            'maxProductsPerStatus' => 5,
        ], $this->settings->getSettingValues(SettingTypeEnum::WATCH_AND_BUY()));
        $config['effectiveMaxUploadMb'] = min((int) $config['maxUploadMb'], $this->serverUploadLimitMb());

        return $config;
    }

    public function enabled(): bool
    {
        return (bool) $this->config()['enabled'];
    }

    public function statusesEnabled(): bool
    {
        $config = $this->config();

        return (bool) $config['enabled'] && (bool) $config['statusesEnabled'];
    }

    public function eligibleProductVariantsQuery(?int $sellerId = null): Builder
    {
        return ProductVariant::query()
            ->with([
                'product.media',
                'media',
                'storeProductVariants' => fn($query) => $query->whereHas('store', fn($store) => $store
                    ->where('verification_status', StoreVerificationStatusEnum::APPROVED())
                    ->where('visibility_status', StoreVisibilityStatusEnum::VISIBLE())
                    ->when($sellerId, fn($match) => $match->where('seller_id', $sellerId))),
                'storeProductVariants.store',
            ])
            ->where('availability', true)
            ->where('visibility', 'published')
            ->whereHas('product', fn($product) => $product
                ->where('status', ProductStatusEnum::ACTIVE())
                ->where('verification_status', ProductVarificationStatusEnum::APPROVED())
                ->when($sellerId, fn($query) => $query->where('seller_id', $sellerId)))
            ->whereHas('storeProductVariants.store', fn($store) => $store
                ->where('verification_status', StoreVerificationStatusEnum::APPROVED())
                ->where('visibility_status', StoreVisibilityStatusEnum::VISIBLE())
                ->when($sellerId, fn($query) => $query->where('seller_id', $sellerId)));
    }

    public function eligibleProductVariants(?int $sellerId = null, array $ids = []): Collection
    {
        return $this->eligibleProductVariantsQuery($sellerId)
            ->when($ids !== [], fn(Builder $query) => $query->whereKey($ids))
            ->get();
    }

    /**
     * @return array<int, array{id: int, text: string, image: ?string}>
     */
    public function eligibleProductVariantOptions(?int $sellerId, string $search = '', array $ids = []): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        return $this->eligibleProductVariantsQuery($sellerId)
            ->when($ids !== [], fn(Builder $query) => $query->whereKey($ids))
            ->when($ids === [] && $search !== '', fn(Builder $query) => $query->where(function (Builder $match) use ($search) {
                $match->where('title', 'like', "%{$search}%")
                    ->orWhereHas('product', fn(Builder $product) => $product->whereEnglishTextLike($search));
            }))
            ->limit(30)
            ->get()
            ->map(fn(ProductVariant $variant) => [
                'id' => $variant->id,
                'text' => trim($variant->product->getTranslation('title', 'en', false).' — '.$variant->title),
                'image' => $variant->image ?: $variant->product->main_image,
            ])->all();
    }

    /**
     * @return array{attachment_ids: array<int, int>, primary_attachment_id: ?int}
     */
    public function reelProductVariantSelection(WatchBuyReel $reel): array
    {
        $products = $reel->exists ? $reel->products()->get() : collect();

        return [
            'attachment_ids' => $products->pluck('product_variant_id')->map(fn($id) => (int) $id)->all(),
            'primary_attachment_id' => $products->firstWhere('is_primary', true)?->product_variant_id,
        ];
    }

    public function saveProfile(WatchBuyProfile $profile, array $data, ?UploadedFile $photo, ?int $actorId, bool $allowSuspended = false): WatchBuyProfile
    {
        abort_if(
            $profile->exists && $profile->status === WatchBuyProfileStatusEnum::SUSPENDED && ! $allowSuspended,
            403,
            __('labels.watch_buy_profile_is_suspended')
        );

        if (! $photo && ! $profile->getFirstMedia(SpatieMediaCollectionName::WATCH_BUY_PROFILE_PHOTO())) {
            throw ValidationException::withMessages(['photo' => __('labels.watch_buy_profile_photo_required')]);
        }

        $data['username'] = Str::lower(trim((string) $data['username']));
        $data['created_by'] ??= $actorId;
        $profile->fill($data)->save();

        if ($photo) {
            $profile->addMedia($photo)->toMediaCollection(SpatieMediaCollectionName::WATCH_BUY_PROFILE_PHOTO());
        }

        return $profile->refresh();
    }

    public function saveReel(WatchBuyReel $reel, WatchBuyProfile $profile, array $data, ?UploadedFile $video, ?UploadedFile $cover, int $actorId): WatchBuyReel
    {
        abort_if($profile->status !== WatchBuyProfileStatusEnum::ACTIVE, 403, __('labels.watch_buy_profile_is_suspended'));
        if (! $profile->getFirstMedia(SpatieMediaCollectionName::WATCH_BUY_PROFILE_PHOTO())) {
            throw ValidationException::withMessages(['profile_id' => __('labels.watch_buy_profile_must_have_photo')]);
        }

        $config = $this->config();
        $attachmentIds = array_values(array_unique(array_map('intval', $data['attachment_ids'] ?? [])));
        $primaryId = (int) ($data['primary_attachment_id'] ?? 0);
        if (count($attachmentIds) < 1 || count($attachmentIds) > (int) $config['maxProductsPerReel']) {
            throw ValidationException::withMessages(['attachment_ids' => __('labels.watch_buy_products_count_invalid')]);
        }
        if (! in_array($primaryId, $attachmentIds, true)) {
            throw ValidationException::withMessages(['primary_attachment_id' => __('labels.watch_buy_primary_must_be_attached')]);
        }

        $source = (string) ($data['source'] ?? WatchBuySourceEnum::SELLER());
        if ($reel->exists && $reel->source === WatchBuySourceEnum::SELLER_BEHALF && $source === WatchBuySourceEnum::SELLER()) {
            $source = WatchBuySourceEnum::SELLER_BEHALF();
        }
        $sellerId = $source === WatchBuySourceEnum::ADMIN_DIRECT() ? null : $profile->seller_id;
        $variants = $this->eligibleProductVariants($sellerId, $attachmentIds)->keyBy('id');
        if ($variants->count() !== count($attachmentIds)) {
            throw ValidationException::withMessages(['attachment_ids' => __('labels.watch_buy_products_unavailable')]);
        }

        $storeMappings = $variants->mapWithKeys(function (ProductVariant $variant) use ($sellerId) {
            $mapping = $variant->storeProductVariants
                ->first(fn($storeVariant) => ! $sellerId || $storeVariant->store?->seller_id === $sellerId);

            return [$variant->id => $mapping?->store_id];
        });

        if (! $video && ! $reel->getFirstMedia(SpatieMediaCollectionName::WATCH_BUY_REEL_VIDEO())) {
            throw ValidationException::withMessages(['video' => __('labels.watch_buy_video_required')]);
        }

        $attributes = [
            'profile_id' => $profile->id,
            'source' => $source,
            'caption' => $data['caption'] ?? null,
            'updated_by' => $actorId,
        ];
        if ($video) {
            $attributes += $this->mediaInspector->inspect($video, (int) $config['maxDurationSeconds']);
        }
        if (! $reel->exists) {
            $attributes['created_by'] = $actorId;
            $attributes['status'] = WatchBuyReelStatusEnum::PUBLISHED();
            $attributes['published_at'] = now();
        }

        return DB::transaction(function () use ($reel, $attributes, $attachmentIds, $primaryId, $variants, $storeMappings, $video, $cover) {
            $reel->fill($attributes)->save();
            $reel->products()->delete();
            foreach ($attachmentIds as $sortOrder => $variantId) {
                $variant = $variants->get($variantId);
                $reel->products()->create([
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variantId,
                    'store_id' => $storeMappings->get($variantId),
                    'sort_order' => $sortOrder,
                    'is_primary' => $variantId === $primaryId,
                ]);
            }
            if ($video) {
                $reel->addMedia($video)->toMediaCollection(SpatieMediaCollectionName::WATCH_BUY_REEL_VIDEO());
            }
            if ($cover) {
                $reel->addMedia($cover)->toMediaCollection(SpatieMediaCollectionName::WATCH_BUY_REEL_COVER());
            }

            return $reel->refresh();
        });
    }

    public function setPublished(WatchBuyReel $reel, bool $published, int $actorId): WatchBuyReel
    {
        $reel->loadMissing('profile');
        abort_if($reel->status === WatchBuyReelStatusEnum::BLOCKED || $reel->profile->status === WatchBuyProfileStatusEnum::SUSPENDED, 403);
        $reel->update([
            'status' => $published ? WatchBuyReelStatusEnum::PUBLISHED() : WatchBuyReelStatusEnum::UNPUBLISHED(),
            'published_at' => $published ? ($reel->published_at ?: now()) : null,
            'updated_by' => $actorId,
        ]);

        return $reel->refresh();
    }

    public function block(WatchBuyReel $reel, string $reason, int $actorId): WatchBuyReel
    {
        if ($reel->status !== WatchBuyReelStatusEnum::BLOCKED) {
            $previous = $reel->status->value;
            $reel->update([
                'status_before_block' => $previous,
                'status' => WatchBuyReelStatusEnum::BLOCKED(),
                'blocked_by' => $actorId,
                'block_reason' => $reason,
                'blocked_at' => now(),
                'updated_by' => $actorId,
            ]);
            $this->log(WatchBuyModerationTargetEnum::REEL(), $reel->id, WatchBuyModerationActionEnum::BLOCK(), $actorId, $previous, WatchBuyReelStatusEnum::BLOCKED(), $reason);
            $this->notifySeller($reel->profile, $reason, $reel);
        }

        return $reel->refresh();
    }

    public function unblock(WatchBuyReel $reel, int $actorId): WatchBuyReel
    {
        if ($reel->status === WatchBuyReelStatusEnum::BLOCKED) {
            $next = $reel->status_before_block?->value ?: WatchBuyReelStatusEnum::UNPUBLISHED();
            $reel->update([
                'status' => $next,
                'status_before_block' => null,
                'blocked_by' => null,
                'block_reason' => null,
                'blocked_at' => null,
                'updated_by' => $actorId,
            ]);
            $this->log(WatchBuyModerationTargetEnum::REEL(), $reel->id, WatchBuyModerationActionEnum::UNBLOCK(), $actorId, WatchBuyReelStatusEnum::BLOCKED(), $next);
        }

        return $reel->refresh();
    }

    public function suspend(WatchBuyProfile $profile, string $reason, int $actorId): WatchBuyProfile
    {
        abort_if($profile->profile_type === WatchBuyProfileTypeEnum::PLATFORM, 422, __('labels.watch_buy_official_profile_cannot_be_suspended'));
        if ($profile->status !== WatchBuyProfileStatusEnum::SUSPENDED) {
            $profile->update([
                'status' => WatchBuyProfileStatusEnum::SUSPENDED(),
                'suspended_by' => $actorId,
                'suspension_reason' => $reason,
                'suspended_at' => now(),
            ]);
            $this->log(WatchBuyModerationTargetEnum::PROFILE(), $profile->id, WatchBuyModerationActionEnum::SUSPEND(), $actorId, WatchBuyProfileStatusEnum::ACTIVE(), WatchBuyProfileStatusEnum::SUSPENDED(), $reason);
            $this->notifySeller($profile, $reason);
        }

        return $profile->refresh();
    }

    public function unsuspend(WatchBuyProfile $profile, int $actorId): WatchBuyProfile
    {
        abort_if($profile->profile_type === WatchBuyProfileTypeEnum::PLATFORM, 422, __('labels.watch_buy_official_profile_cannot_be_suspended'));
        if ($profile->status === WatchBuyProfileStatusEnum::SUSPENDED) {
            $profile->update([
                'status' => WatchBuyProfileStatusEnum::ACTIVE(),
                'suspended_by' => null,
                'suspension_reason' => null,
                'suspended_at' => null,
            ]);
            $this->log(WatchBuyModerationTargetEnum::PROFILE(), $profile->id, WatchBuyModerationActionEnum::UNSUSPEND(), $actorId, WatchBuyProfileStatusEnum::SUSPENDED(), WatchBuyProfileStatusEnum::ACTIVE());
        }

        return $profile->refresh();
    }

    public function deleteReel(WatchBuyReel $reel, int $actorId): void
    {
        DB::transaction(function () use ($reel, $actorId): void {
            $this->log(WatchBuyModerationTargetEnum::REEL(), $reel->id, WatchBuyModerationActionEnum::DELETE(), $actorId, $reel->status->value, null, null, [
                'reel_id' => $reel->id,
                'slug' => $reel->slug,
                'profile_id' => $reel->profile_id,
                'source' => $reel->source->value,
            ]);
            $reel->delete();
        });
    }

    public function resolveZone(?float $latitude, ?float $longitude): ?\App\Models\DeliveryZone
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        $zoneInfo = DeliveryZoneService::getZonesAtPoint($latitude, $longitude);
        if (($zoneInfo['exists'] ?? false) && ! empty($zoneInfo['zone_id'])) {
            return \App\Models\DeliveryZone::find($zoneInfo['zone_id']);
        }

        return null;
    }

    /**
     * Reusable zone-aware product eligibility check.
     */
    public function getEligibleAttachmentsForReel(WatchBuyReel $reel, ?\App\Models\DeliveryZone $zone, bool $hasCoordinates = false): Collection
    {
        $products = $reel->relationLoaded('products') ? $reel->products : $reel->products()->with([
            'variant.product.media',
            'variant.media',
            'variant.storeProductVariants',
            'store.deliveryZones',
        ])->get();

        $eligible = collect();

        foreach ($products as $attachment) {
            $variant = $attachment->variant;
            $product = $variant?->product;
            $store = $attachment->store;

            // Product check
            $productStatus = is_object($product?->status) ? $product->status->value : $product?->status;
            $productVerification = is_object($product?->verification_status) ? $product->verification_status->value : $product?->verification_status;
            if (! $product || $productStatus !== 'active' || $productVerification !== 'approved') {
                continue;
            }

            // Variant check
            if (! $variant || ! $variant->availability || $variant->visibility !== 'published') {
                continue;
            }

            // Store check
            $storeVerification = is_object($store?->verification_status) ? $store->verification_status->value : $store?->verification_status;
            $storeVisibility = is_object($store?->visibility_status) ? $store->visibility_status->value : $store?->visibility_status;
            if (! $store || $storeVerification !== 'approved' || $storeVisibility !== 'visible') {
                continue;
            }

            // Store belongs to resolved zone check
            // If coordinates were passed (indicated by $zone or $zoneRequired), store must belong to the active zone
            if ($zone !== null) {
                $storeInZone = $store->deliveryZones->contains('id', $zone->id);
                if (! $storeInZone) {
                    continue;
                }
            } elseif ($hasCoordinates) {
                // Customer provided coordinates but no zone covers this location -> no attachments eligible
                continue;
            }

            // Store-product-variant check
            $storeProductVariant = $variant->storeProductVariants->firstWhere('store_id', $store->id);

            if (! $storeProductVariant) {
                continue;
            }

            $attachment->setRelation('storeProductVariant', $storeProductVariant);
            $eligible->push($attachment);
        }

        $sorted = $eligible->sortBy('sort_order')->values();

        // Effective primary resolution:
        // 1. Use stored primary if eligible
        $storedPrimary = $sorted->firstWhere('is_primary', true);
        if ($storedPrimary) {
            foreach ($sorted as $item) {
                $item->is_effective_primary = ($item->id === $storedPrimary->id);
            }
        } elseif ($sorted->isNotEmpty()) {
            // 2. Otherwise use the first eligible attachment
            foreach ($sorted as $index => $item) {
                $item->is_effective_primary = ($index === 0);
            }
        }

        return $sorted;
    }

    /**
     * Resolve ordered, customer-safe reels for a Home Builder section.
     *
     * @return Collection<int, WatchBuyReel>
     */
    public function getHomeReels(
        string $source,
        int $limit,
        ?\App\Models\DeliveryZone $zone,
        bool $hasCoordinates = false,
        ?User $user = null,
    ): Collection {
        if (! $this->enabled()) {
            return collect();
        }

        $limit = min(max($limit, 1), 50);

        $query = WatchBuyReel::query()
            ->publiclyVisible()
            ->with([
                'profile' => $this->customerProfileEagerLoad($user),
                'profile.seller.stores.media',
                'media',
                'products.variant.product.media',
                'products.variant.media',
                'products.variant.storeProductVariants',
                'products.store.deliveryZones',
            ])
            ->when($user, fn (Builder $builder) => $builder->with([
                'likes' => fn ($likes) => $likes->where('user_id', $user->id),
            ]));

        if ($source === 'latest') {
            $query->orderByDesc('published_at')->orderByDesc('id');
        } else {
            $query->orderByDesc('like_count')->orderByDesc('published_at')->orderByDesc('id');
        }

        $eligibleReels = collect();

        foreach ($query->get() as $reel) {
            $attachments = $this->getEligibleAttachmentsForReel($reel, $zone, $hasCoordinates);
            if ($attachments->isEmpty()) {
                continue;
            }

            $reel->setRelation('products', $attachments);
            $eligibleReels->push($reel);

            if ($eligibleReels->count() >= $limit) {
                break;
            }
        }

        return $eligibleReels;
    }

    /**
     * Deterministic Reel Feed with Cursor Pagination & Shared Slug Support.
     *
     * @return array{data: Collection<int, WatchBuyReel>, has_more: bool, next_cursor: ?string, session_id: string, slug_not_found: bool}
     */
    public function getReelFeed(array $filters, ?\App\Models\User $user = null): array
    {
        if (! $this->enabled()) {
            return [
                'data' => collect(),
                'has_more' => false,
                'next_cursor' => null,
                'session_id' => $filters['session_id'] ?? (string) Str::uuid(),
                'slug_not_found' => false,
            ];
        }

        $sessionId = ! empty($filters['session_id']) ? (string) $filters['session_id'] : (string) Str::uuid();
        $perPage = min(max((int) ($filters['per_page'] ?? 10), 1), 50);
        $zone = $this->resolveZone(
            isset($filters['latitude']) ? (float) $filters['latitude'] : null,
            isset($filters['longitude']) ? (float) $filters['longitude'] : null
        );

        $cursor = $filters['cursor'] ?? null;
        $seenIds = [];
        $isFirstPage = true;

        if (! empty($cursor)) {
            try {
                $payload = json_decode(\Illuminate\Support\Facades\Crypt::decryptString($cursor), true, 512, JSON_THROW_ON_ERROR);
                if (($payload['session_id'] ?? null) !== $sessionId) {
                    abort(400, __('labels.invalid_cursor_session'));
                }
                $seenIds = (array) ($payload['seen_ids'] ?? []);
                $isFirstPage = false;
            } catch (\Throwable $e) {
                abort(400, __('labels.invalid_cursor'));
            }
        }

        $sharedSlug = $filters['slug'] ?? null;
        $sharedReel = null;
        $slugNotFound = false;

        $hasCoordinates = isset($filters['latitude']) && isset($filters['longitude']);

        if ($isFirstPage && ! empty($sharedSlug)) {
            $candidate = WatchBuyReel::query()
                ->where('slug', $sharedSlug)
                ->publiclyVisible()
                ->with([
                    'profile' => $this->customerProfileEagerLoad($user),
                    'media',
                    'products.variant.product.media',
                    'products.variant.media',
                    'products.variant.storeProductVariants',
                    'products.store.deliveryZones',
                ])
                ->when($user, fn($q) => $q->with(['likes' => fn($lq) => $lq->where('user_id', $user->id)]))
                ->first();

            if ($candidate) {
                $eligibleAttachments = $this->getEligibleAttachmentsForReel($candidate, $zone, $hasCoordinates);
                if ($eligibleAttachments->isNotEmpty()) {
                    $candidate->setRelation('products', $eligibleAttachments);
                    $sharedReel = $candidate;
                } else {
                    $slugNotFound = true;
                }
            } else {
                $slugNotFound = true;
            }
        }

        $storeIdFilter = isset($filters['store_id']) ? (int) $filters['store_id'] : null;
        $storeSlugFilter = isset($filters['store_slug']) ? trim((string) $filters['store_slug']) : null;

        // 1. Fetch candidates (currently published with active profiles)
        $candidatesQuery = WatchBuyReel::query()
            ->publiclyVisible()
            ->with([
                'profile' => $this->customerProfileEagerLoad($user),
                'media',
                'products.variant.product.media',
                'products.variant.media',
                'products.variant.storeProductVariants',
                'products.store.deliveryZones',
            ])
            ->when($user, fn($q) => $q->with(['likes' => fn($lq) => $lq->where('user_id', $user->id)]));

        if ($storeIdFilter) {
            $candidatesQuery->whereHas('products', fn ($q) => $q->where('store_id', $storeIdFilter));
        } elseif ($storeSlugFilter) {
            $candidatesQuery->whereHas('products.store', fn ($q) => $q->where('slug', $storeSlugFilter));
        }

        $candidates = $candidatesQuery->get();

        // 2. Filter zone eligibility and attach resolved products
        $eligibleReels = collect();
        foreach ($candidates as $candidate) {
            $attachments = $this->getEligibleAttachmentsForReel($candidate, $zone, $hasCoordinates);
            if ($attachments->isNotEmpty()) {
                $candidate->setRelation('products', $attachments);
                $eligibleReels->push($candidate);
            }
        }

        // 3. Exclude shared reel from randomization if picked
        if ($sharedReel) {
            $eligibleReels = $eligibleReels->reject(fn($r) => $r->id === $sharedReel->id)->values();
        }

        // 4. Deterministic sort by SHA-256(session_id:reel_id)
        $sorted = $eligibleReels->sortBy(fn($reel) => hash('sha256', "{$sessionId}:{$reel->id}"))->values();

        // 5. Exclude already seen IDs from cursor
        $unseen = $sorted->reject(fn($reel) => in_array($reel->id, $seenIds, true))->values();

        $slotsForRandom = $sharedReel ? max(0, $perPage - 1) : $perPage;
        $pageSlice = $unseen->take($slotsForRandom);

        $resultItems = collect();
        if ($sharedReel) {
            $resultItems->push($sharedReel);
        }
        foreach ($pageSlice as $sliceItem) {
            $resultItems->push($sliceItem);
        }

        $allSeenSoFar = array_values(array_unique(array_merge($seenIds, $resultItems->pluck('id')->all())));
        $hasMore = $unseen->count() > $slotsForRandom;

        $nextCursor = null;
        if ($hasMore) {
            $nextCursor = \Illuminate\Support\Facades\Crypt::encryptString(json_encode([
                'session_id' => $sessionId,
                'seen_ids' => $allSeenSoFar,
            ]));
        }

        return [
            'data' => $resultItems,
            'has_more' => $hasMore,
            'next_cursor' => $nextCursor,
            'session_id' => $sessionId,
            'slug_not_found' => $slugNotFound,
        ];
    }

    /**
     * Get zone-eligible reels for a specific profile (newest-first).
     */
    public function getProfileReels(WatchBuyProfile $profile, float $latitude, float $longitude, int $page = 1, int $perPage = 15, ?\App\Models\User $user = null): array
    {
        if (! $this->enabled() || $profile->status !== WatchBuyProfileStatusEnum::ACTIVE) {
            abort(404);
        }

        $zone = $this->resolveZone($latitude, $longitude);
        $profile->loadCount([
            'statuses as has_active_status' => fn(Builder $statuses) => $statuses->where('expires_at', '>', now()),
            'statuses as has_unseen_status' => fn(Builder $statuses) => $statuses
                ->where('expires_at', '>', now())
                ->when($user, fn(Builder $active) => $active->whereDoesntHave(
                    'views',
                    fn(Builder $views) => $views->where('user_id', $user->id),
                )),
        ]);

        $candidates = WatchBuyReel::query()
            ->where('profile_id', $profile->id)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->with([
                'profile' => $this->customerProfileEagerLoad($user),
                'media',
                'products.variant.product.media',
                'products.variant.media',
                'products.variant.storeProductVariants',
                'products.store.deliveryZones',
            ])
            ->when($user, fn($q) => $q->with(['likes' => fn($lq) => $lq->where('user_id', $user->id)]))
            ->latest('published_at')
            ->latest('id')
            ->get();

        $eligible = collect();
        foreach ($candidates as $reel) {
            $attachments = $this->getEligibleAttachmentsForReel($reel, $zone, true);
            if ($attachments->isNotEmpty()) {
                $reel->setRelation('products', $attachments);
                $eligible->push($reel);
            }
        }

        $total = $eligible->count();
        $offset = ($page - 1) * $perPage;
        $items = $eligible->slice($offset, $perPage)->values();

        return [
            'data' => $items,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => (int) ceil($total / max(1, $perPage)),
        ];
    }

    /**
     * Get a single publicly visible reel by slug or ID with zone-eligible products.
     */
    public function getSingleReel(string|int $identifier, ?float $latitude = null, ?float $longitude = null, ?User $user = null): WatchBuyReel
    {
        if (! $this->enabled()) {
            abort(404);
        }

        $zone = $this->resolveZone($latitude, $longitude);
        $hasCoordinates = ($latitude !== null && $longitude !== null);

        $query = WatchBuyReel::query()
            ->publiclyVisible()
            ->with([
                'profile' => $this->customerProfileEagerLoad($user),
                'media',
                'products.variant.product.media',
                'products.variant.media',
                'products.variant.storeProductVariants',
                'products.store.deliveryZones',
            ])
            ->when($user, fn($q) => $q->with(['likes' => fn($lq) => $lq->where('user_id', $user->id)]));

        if (is_numeric($identifier)) {
            $query->where(fn($q) => $q->where('id', (int) $identifier)->orWhere('slug', (string) $identifier));
        } else {
            $query->where('slug', (string) $identifier);
        }

        $reel = $query->first();

        if (! $reel) {
            abort(404);
        }

        $attachments = $this->getEligibleAttachmentsForReel($reel, $zone, $hasCoordinates);

        if ($attachments->isEmpty()) {
            abort(404);
        }

        $reel->setRelation('products', $attachments);

        return $reel;
    }

    /**
     * Bulk Reel Likes synchronization.
     *
     * @param array<int, array{reel_id: int, liked: bool}> $items
     * @return array<int, array{reel_id: int, like_count: int, liked_by_me: bool}>
     */
    public function syncLikes(array $items, float $latitude, float $longitude, \App\Models\User $user): array
    {
        if (! $this->enabled()) {
            abort(404);
        }

        $zone = $this->resolveZone($latitude, $longitude);
        $states = collect($items)->mapWithKeys(fn(array $item) => [
            (int) $item['reel_id'] => (bool) $item['liked'],
        ]);
        $reels = WatchBuyReel::query()
            ->whereKey($states->keys())
            ->with([
                'profile',
                'products.variant.product',
                'products.variant.storeProductVariants',
                'products.store.deliveryZones',
            ])
            ->get()
            ->keyBy('id');

        abort_unless($reels->count() === $states->count(), 404);

        foreach ($states->filter() as $reelId => $liked) {
            /** @var WatchBuyReel $reel */
            $reel = $reels->get($reelId);
            abort_unless(
                $reel->status === WatchBuyReelStatusEnum::PUBLISHED
                    && $reel->published_at !== null
                    && $reel->profile->status === WatchBuyProfileStatusEnum::ACTIVE
                    && $this->getEligibleAttachmentsForReel($reel, $zone, true)->isNotEmpty(),
                404,
            );
        }

        DB::transaction(function () use ($states, $user): void {
            foreach ($states as $reelId => $liked) {
                if ($liked) {
                    $inserted = DB::table('watch_buy_reel_likes')->insertOrIgnore([
                        'reel_id' => $reelId,
                        'user_id' => $user->id,
                        'created_at' => now(),
                    ]);

                    if ($inserted > 0) {
                        DB::table('watch_buy_reels')->where('id', $reelId)->increment('like_count');
                    }

                    continue;
                }

                $deleted = DB::table('watch_buy_reel_likes')
                    ->where('reel_id', $reelId)
                    ->where('user_id', $user->id)
                    ->delete();

                if ($deleted > 0) {
                    DB::table('watch_buy_reels')
                        ->where('id', $reelId)
                        ->update(['like_count' => DB::raw('CASE WHEN like_count > 0 THEN like_count - 1 ELSE 0 END')]);
                }
            }
        });

        $counts = DB::table('watch_buy_reels')->whereIn('id', $states->keys())->pluck('like_count', 'id');

        return $states->map(fn(bool $liked, int $reelId) => [
            'reel_id' => $reelId,
            'like_count' => (int) $counts[$reelId],
            'liked_by_me' => $liked,
        ])->values()->all();
    }

    private function log(string $targetType, int $targetId, string $action, int $actorId, ?string $previousStatus, ?string $newStatus, ?string $reason = null, ?array $metadata = null): void
    {
        WatchBuyModerationLog::create([
            'target_type' => $targetType,
            'target_id' => $targetId,
            'action' => $action,
            'actor_id' => $actorId,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
            'metadata' => $metadata,
        ]);
    }

    private function customerProfileEagerLoad(?\App\Models\User $user): Closure
    {
        return fn(Builder|BelongsTo $profile) => $profile
            ->with('media')
            ->withCount([
                'statuses as has_active_status' => fn(Builder $statuses) => $statuses->where('expires_at', '>', now()),
                'statuses as has_unseen_status' => fn(Builder $statuses) => $statuses
                    ->where('expires_at', '>', now())
                    ->when($user, fn(Builder $active) => $active->whereDoesntHave(
                        'views',
                        fn(Builder $views) => $views->where('user_id', $user->id),
                    )),
            ]);
    }

    private function notifySeller(WatchBuyProfile $profile, string $reason, ?WatchBuyReel $reel = null): void
    {
        try {
            $profile->loadMissing('seller.user');
            $profile->seller?->user?->notify(new WatchBuyModerationNotification($profile, $reason, $reel));
        } catch (Throwable $exception) {
            Log::error('WatchBuyService::notifySeller failed', ['error' => $exception->getMessage(), 'profile_id' => $profile->id, 'reel_id' => $reel?->id]);
        }
    }

    private function serverUploadLimitMb(): int
    {
        $limits = array_filter([
            $this->iniSizeToMb((string) ini_get('upload_max_filesize')),
            $this->iniSizeToMb((string) ini_get('post_max_size')),
        ], fn(int $limit) => $limit > 0);

        return $limits === [] ? 500 : min($limits);
    }

    private function iniSizeToMb(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        $number = (float) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => (int) floor($number * 1024),
            'k' => (int) floor($number / 1024),
            default => (int) floor($number),
        };
    }
}
