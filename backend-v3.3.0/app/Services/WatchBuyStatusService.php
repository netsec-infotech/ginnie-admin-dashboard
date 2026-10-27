<?php

namespace App\Services;

use App\Enums\SpatieMediaCollectionName;
use App\Enums\WatchBuy\WatchBuyProfileStatusEnum;
use App\Enums\WatchBuy\WatchBuySourceEnum;
use App\Enums\WatchBuy\WatchBuyStatusContentTypeEnum;
use App\Models\DeliveryZone;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WatchBuyProfile;
use App\Models\WatchBuyStatus;
use App\Models\WatchBuyStatusView;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WatchBuyStatusService
{
    public function __construct(
        private readonly WatchBuyService $watchBuy,
        private readonly WatchBuyMediaInspector $mediaInspector,
    ) {}

    public function save(WatchBuyProfile $profile, array $data, ?UploadedFile $media, int $actorId): WatchBuyStatus
    {
        abort_if($profile->status !== WatchBuyProfileStatusEnum::ACTIVE, 403, __('labels.watch_buy_profile_is_suspended'));
        abort_unless($this->watchBuy->statusesEnabled(), 404);

        $text = trim((string) ($data['text'] ?? ''));
        if ($text === '' && ! $media) {
            throw ValidationException::withMessages(['text' => __('labels.watch_buy_status_content_required')]);
        }

        $attachmentIds = array_values(array_unique(array_map('intval', $data['attachment_ids'] ?? [])));
        $config = $this->watchBuy->config();
        if (count($attachmentIds) > (int) $config['maxProductsPerStatus']) {
            throw ValidationException::withMessages(['attachment_ids' => __('labels.watch_buy_products_count_invalid')]);
        }

        $source = (string) ($data['source'] ?? WatchBuySourceEnum::SELLER());
        $sellerId = $source === WatchBuySourceEnum::ADMIN_DIRECT() ? null : $profile->seller_id;
        $variants = $attachmentIds === []
            ? collect()
            : $this->watchBuy->eligibleProductVariants($sellerId, $attachmentIds)->keyBy('id');
        if ($variants->count() !== count($attachmentIds)) {
            throw ValidationException::withMessages(['attachment_ids' => __('labels.watch_buy_products_unavailable')]);
        }

        $createdAt = now();
        $attributes = [
            'profile_id' => $profile->id,
            'source' => $source,
            'content_type' => $media ? $this->contentType($media) : WatchBuyStatusContentTypeEnum::TEXT(),
            'text' => $text !== '' ? $text : null,
            'created_by' => $actorId,
            'created_at' => $createdAt,
            'expires_at' => $createdAt->copy()->addDay(),
        ];

        if ($media) {
            $attributes['media_mime'] = $media->getMimeType();
            $attributes['media_size_bytes'] = $media->getSize();
            if ($attributes['content_type'] === WatchBuyStatusContentTypeEnum::VIDEO()) {
                $video = $this->mediaInspector->inspect($media, (int) $config['maxStatusDurationSeconds'], 'status');
                $attributes['duration_ms'] = $video['duration_ms'];
                $attributes['media_width'] = $video['video_width'];
                $attributes['media_height'] = $video['video_height'];
            } else {
                $dimensions = @getimagesize($media->getRealPath());
                if ($dimensions === false) {
                    throw ValidationException::withMessages(['media' => __('labels.watch_buy_status_image_invalid')]);
                }
                $attributes['media_width'] = (int) $dimensions[0];
                $attributes['media_height'] = (int) $dimensions[1];
            }
        }

        return DB::transaction(function () use ($attributes, $attachmentIds, $variants, $sellerId, $media) {
            $status = WatchBuyStatus::create($attributes);
            foreach ($attachmentIds as $sortOrder => $variantId) {
                /** @var ProductVariant $variant */
                $variant = $variants->get($variantId);
                $mapping = $variant->storeProductVariants
                    ->first(fn ($storeVariant) => ! $sellerId || $storeVariant->store?->seller_id === $sellerId);
                $status->products()->create([
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variantId,
                    'store_id' => $mapping?->store_id,
                    'sort_order' => $sortOrder,
                ]);
            }
            if ($media) {
                $status->addMedia($media)->toMediaCollection(SpatieMediaCollectionName::WATCH_BUY_STATUS_MEDIA());
            }

            return $status->refresh();
        });
    }

    public function delete(WatchBuyStatus $status): void
    {
        $status->delete();
    }

    public function deleteExpired(): int
    {
        if (WatchBuyStatus::doesNotExpireInDemoMode()) {
            return 0;
        }

        $deleted = 0;
        WatchBuyStatus::query()
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($statuses) use (&$deleted): void {
                foreach ($statuses as $status) {
                    $status->delete();
                    $deleted++;
                }
            });

        return $deleted;
    }

    /**
     * Story Tray: returns active profiles having at least 1 active status, with unseen-first, official profile priority, and deterministic pagination.
     *
     * @return array{data: Collection, has_more: bool, next_cursor: ?string, session_id: string}
     */
    public function getStatusTray(array $filters, ?User $user = null): array
    {
        if (! $this->watchBuy->enabled() || ! $this->watchBuy->statusesEnabled()) {
            return [
                'data' => collect(),
                'has_more' => false,
                'next_cursor' => null,
                'session_id' => $filters['session_id'] ?? (string) Str::uuid(),
            ];
        }

        $sessionId = ! empty($filters['session_id']) ? (string) $filters['session_id'] : (string) Str::uuid();
        $perPage = min(max((int) ($filters['per_page'] ?? 10), 1), 50);
        $cursor = $filters['cursor'] ?? null;
        $seenProfileIds = [];

        if (! empty($cursor)) {
            try {
                // If URL-decoded spaces were introduced in place of '+', normalize them
                $decodableCursor = str_contains($cursor, ' ') ? str_replace(' ', '+', $cursor) : $cursor;
                $payload = json_decode(Crypt::decryptString($decodableCursor), true, 512, JSON_THROW_ON_ERROR);

                // If session_id was omitted in the query filters, adopt it from the valid decrypted cursor
                if (empty($filters['session_id']) && ! empty($payload['session_id'])) {
                    $sessionId = (string) $payload['session_id'];
                } elseif (($payload['session_id'] ?? null) !== $sessionId) {
                    abort(400, __('labels.invalid_cursor_session'));
                }

                $seenProfileIds = (array) ($payload['seen_profile_ids'] ?? []);
            } catch (\Throwable $e) {
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                    throw $e;
                }
                abort(400, __('labels.invalid_cursor'));
            }
        }

        $storeIdFilter = isset($filters['store_id']) ? (int) $filters['store_id'] : null;
        $storeSlugFilter = isset($filters['store_slug']) ? trim((string) $filters['store_slug']) : null;

        // 1. Fetch active profiles having at least 1 unexpired status (matching store filter if provided)
        $statusCondition = function ($q) use ($storeIdFilter, $storeSlugFilter) {
            $q->active();
            if ($storeIdFilter || $storeSlugFilter) {
                $q->where(function ($sq) use ($storeIdFilter, $storeSlugFilter) {
                    $hasAnyBranch = false;
                    if ($storeIdFilter) {
                        $sq->where(function ($sub) use ($storeIdFilter) {
                            $sub->whereHas('products', fn ($pq) => $pq->where('store_id', $storeIdFilter))
                                ->orWhereHas('profile.seller.stores', fn ($stq) => $stq->where('stores.id', $storeIdFilter));
                        });
                        $hasAnyBranch = true;
                    }
                    if ($storeSlugFilter) {
                        $method = $hasAnyBranch ? 'orWhere' : 'where';
                        $sq->{$method}(function ($sub) use ($storeSlugFilter) {
                            $sub->whereHas('products.store', fn ($pq) => $pq->where('slug', $storeSlugFilter))
                                ->orWhereHas('profile.seller.stores', fn ($stq) => $stq->where('stores.slug', $storeSlugFilter));
                        });
                    }
                });
            }
        };

        $profiles = WatchBuyProfile::query()
            ->where('status', WatchBuyProfileStatusEnum::ACTIVE)
            ->whereHas('statuses', $statusCondition)
            ->with(['media', 'seller.stores.media'])
            ->withCount([
                'statuses as status_count' => $statusCondition,
                'statuses as unseen_status_count' => fn ($query) => $query
                    ->where($statusCondition)
                    ->when($user, fn ($statuses) => $statuses->whereDoesntHave(
                        'views',
                        fn ($views) => $views->where('user_id', $user->id),
                    )),
            ])
            ->get();

        $officialProfile = null;
        $sellerProfiles = collect();

        foreach ($profiles as $profile) {
            $unexpiredCount = (int) $profile->status_count;
            if ($unexpiredCount === 0) {
                continue;
            }

            $allSeen = $user ? (int) $profile->unseen_status_count === 0 : false;
            $profile->setAttribute('has_active_status', true);
            $profile->setAttribute('has_unseen_status', ! $allSeen);

            $groupData = [
                'profile' => $profile,
                'all_seen' => $allSeen,
                'status_count' => $unexpiredCount,
            ];

            if ($profile->profile_type?->value === 'platform' || $profile->profile_type === 'platform') {
                $officialProfile = $groupData;
            } else {
                $sellerProfiles->push($groupData);
            }
        }

        // 2. Separate seller profiles into unseen vs seen
        $unseenSellers = $sellerProfiles->where('all_seen', false)->values();
        $seenSellers = $sellerProfiles->where('all_seen', true)->values();

        // 3. Deterministic randomization within each group
        $sortedUnseenSellers = $unseenSellers->sortBy(fn ($item) => hash('sha256', "{$sessionId}:{$item['profile']->id}"))->values();
        $sortedSeenSellers = $seenSellers->sortBy(fn ($item) => hash('sha256', "{$sessionId}:{$item['profile']->id}"))->values();

        $orderedGroups = collect();

        // 4. Official Profile Priority Rule:
        // Put official profile first when: guest or official has unseen stories
        if ($officialProfile) {
            $isGuest = ($user === null);
            $hasUnseen = ! ($officialProfile['all_seen'] ?? false);

            if ($isGuest || $hasUnseen) {
                $orderedGroups->push($officialProfile);
                foreach ($sortedUnseenSellers as $u) {
                    $orderedGroups->push($u);
                }
                foreach ($sortedSeenSellers as $s) {
                    $orderedGroups->push($s);
                }
            } else {
                // Official is fully seen: include in normal randomized ordering alongside seen sellers
                foreach ($sortedUnseenSellers as $u) {
                    $orderedGroups->push($u);
                }
                $seenAll = $sortedSeenSellers->push($officialProfile)
                    ->sortBy(fn ($item) => hash('sha256', "{$sessionId}:{$item['profile']->id}"))
                    ->values();
                foreach ($seenAll as $s) {
                    $orderedGroups->push($s);
                }
            }
        } else {
            foreach ($sortedUnseenSellers as $u) {
                $orderedGroups->push($u);
            }
            foreach ($sortedSeenSellers as $s) {
                $orderedGroups->push($s);
            }
        }

        // 5. Exclude already seen profile IDs from cursor
        $unseenFromCursor = $orderedGroups->reject(fn ($group) => in_array($group['profile']->id, $seenProfileIds, true))->values();
        $pageSlice = $unseenFromCursor->take($perPage);

        $allSeenProfilesSoFar = array_values(array_unique(array_merge($seenProfileIds, $pageSlice->pluck('profile.id')->all())));
        $hasMore = $unseenFromCursor->count() > $perPage;

        $nextCursor = null;
        if ($hasMore) {
            $nextCursor = Crypt::encryptString(json_encode([
                'session_id' => $sessionId,
                'seen_profile_ids' => $allSeenProfilesSoFar,
            ]));
        }

        return [
            'data' => $pageSlice,
            'has_more' => $hasMore,
            'next_cursor' => $nextCursor,
            'session_id' => $sessionId,
        ];
    }

    /**
     * Get profile stories in chronological playback order (oldest to newest).
     */
    /**
     * @return array{statuses: CursorPaginator, total: int}
     */
    public function getProfileStatuses(WatchBuyProfile $profile, ?float $latitude = null, ?float $longitude = null, ?string $cursor = null, int $perPage = 15, ?User $user = null): array
    {
        if (! $this->watchBuy->enabled() || ! $this->watchBuy->statusesEnabled() || $profile->status !== WatchBuyProfileStatusEnum::ACTIVE) {
            abort(404);
        }

        $zone = $this->watchBuy->resolveZone($latitude, $longitude);
        $hasCoordinates = ($latitude !== null && $longitude !== null);

        $query = WatchBuyStatus::query()
            ->where('profile_id', $profile->id)
            ->active()
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->with([
                'media',
                'products.variant.product.media',
                'products.variant.media',
                'products.variant.storeProductVariants',
                'products.store.deliveryZones',
            ])
            ->when($user, fn ($q) => $q->with(['views' => fn ($vq) => $vq->where('user_id', $user->id)]));

        $total = (clone $query)->count();
        abort_if($total === 0, 404);
        $paginator = $query->cursorPaginate($perPage, ['*'], 'cursor', $cursor ? Cursor::fromEncoded($cursor) : null);

        foreach ($paginator->items() as $status) {
            $eligible = $this->getEligibleAttachmentsForStatus($status, $zone, $hasCoordinates);
            $status->setRelation('products', $eligible);
        }

        return ['statuses' => $paginator, 'total' => $total];
    }

    /**
     * Reusable zone filter for status products.
     */
    public function getEligibleAttachmentsForStatus(WatchBuyStatus $status, ?DeliveryZone $zone, bool $hasCoordinates = false): Collection
    {
        $products = $status->relationLoaded('products') ? $status->products : $status->products()->with([
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

            $productStatus = is_object($product?->status) ? $product->status->value : $product?->status;
            $productVerification = is_object($product?->verification_status) ? $product->verification_status->value : $product?->verification_status;
            if (! $product || $productStatus !== 'active' || $productVerification !== 'approved') {
                continue;
            }

            if (! $variant || ! $variant->availability || $variant->visibility !== 'published') {
                continue;
            }

            $storeVerification = is_object($store?->verification_status) ? $store->verification_status->value : $store?->verification_status;
            $storeVisibility = is_object($store?->visibility_status) ? $store->visibility_status->value : $store?->visibility_status;
            if (! $store || $storeVerification !== 'approved' || $storeVisibility !== 'visible') {
                continue;
            }

            if ($zone !== null) {
                $storeInZone = $store->deliveryZones->contains('id', $zone->id);
                if (! $storeInZone) {
                    continue;
                }
            } elseif ($hasCoordinates) {
                continue;
            }

            $storeProductVariant = $variant->storeProductVariants->firstWhere('store_id', $store->id);

            if (! $storeProductVariant) {
                continue;
            }

            $attachment->setRelation('storeProductVariant', $storeProductVariant);
            $eligible->push($attachment);
        }

        return $eligible->sortBy('sort_order')->values();
    }

    /**
     * Mark status seen state transactionally for authenticated user.
     *
     * @param  array<int, int>  $statusIds
     * @return array<int, array{status_id: int, seen_at: string}>
     */
    public function markSeen(array $statusIds, User $user): array
    {
        if (! $this->watchBuy->enabled() || ! $this->watchBuy->statusesEnabled()) {
            abort(404);
        }

        $statusIds = array_values(array_unique(array_filter(array_map('intval', $statusIds))));
        $validStatuses = WatchBuyStatus::query()
            ->whereIn('id', $statusIds)
            ->active()
            ->whereHas('profile', fn ($q) => $q->where('status', 'active'))
            ->pluck('id')
            ->all();

        abort_unless(count($validStatuses) === count($statusIds), 404);

        $results = [];
        $now = now();

        DB::transaction(function () use ($validStatuses, $user, $now, &$results) {
            foreach ($validStatuses as $statusId) {
                WatchBuyStatusView::updateOrCreate(
                    ['status_id' => $statusId, 'user_id' => $user->id],
                    ['seen_at' => $now]
                );

                $results[] = [
                    'status_id' => $statusId,
                    'seen_at' => $now->toISOString(),
                ];
            }
        });

        return $results;
    }

    private function contentType(UploadedFile $media): string
    {
        return str_starts_with(strtolower((string) $media->getMimeType()), 'video/')
            ? WatchBuyStatusContentTypeEnum::VIDEO()
            : WatchBuyStatusContentTypeEnum::IMAGE();
    }
}
