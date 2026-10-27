<?php

namespace App\Models;

use App\Enums\SpatieMediaCollectionName;
use App\Enums\Store\StoreFulfillmentTypeEnum;
use App\Enums\Store\StoreStatusEnum;
use App\Enums\Store\StoreVerificationStatusEnum;
use App\Enums\Store\StoreVisibilityStatusEnum;
use App\Services\DeliveryZoneService;
use App\Traits\HasSeoMetadata;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Store extends Model implements HasMedia
{
    use HasFactory, HasSeoMetadata, InteractsWithMedia, SoftDeletes;

    protected function seoFallbackDescription(): ?string
    {
        foreach (['description', 'about_us', 'promotional_text'] as $field) {
            $value = $this->{$field} ?? null;
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return $this->seoFallbackTitle();
    }

    protected $appends = ['distance', 'store_logo', 'store_banner', 'avg_products_rating', 'avg_store_rating', 'total_store_feedback'];

    protected $fillable = [
        'seller_id',
        'name',
        'slug',
        'address',
        'city',
        'landmark',
        'state',
        'zipcode',
        'country',
        'country_code',
        'latitude',
        'longitude',
        'contact_email',
        'contact_number',
        'description',
        'timing',
        'address_proof',
        'voided_check',
        'tax_name',
        'tax_number',
        'bank_name',
        'bank_branch_code',
        'account_holder_name',
        'account_number',
        'routing_number',
        'bank_account_type',
        'currency_code',
        'status',
        'max_delivery_distance',
        'order_preparation_time',
        'promotional_text',
        'about_us',
        'return_replacement_policy',
        'refund_policy',
        'terms_and_conditions',
        'delivery_policy',
        'domestic_shipping_charges',
        'international_shipping_charges',
        'metadata',
        'verification_status',
        'visibility_status',
        'is_recommended',
        'fulfillment_type',
        'pos_upi_vpa',
        'pos_upi_payee_name',
        'pos_payment_config',
        'receipt_template',
        'allows_pickup',
        'is_pickup_cod_enabled',
        'pickup_instructions',
        'is_scheduled_delivery_enabled',
        'scheduled_delivery_buffer_minutes',
        'max_advance_booking_days',
        'scheduled_delivery_slot_duration',
        'scheduled_delivery_slot_capacity',
        'is_temporarily_closed',
        'temporary_closure_reason',
        'prep_concurrency',
        'reschedule_deadline_hours',
    ];

    protected $casts = [
        'metadata' => 'array',
        'receipt_template' => 'array',
        'pos_payment_config' => 'array',
        'max_delivery_distance' => 'double',
        'domestic_shipping_charges' => 'decimal:2',
        'international_shipping_charges' => 'decimal:2',
        'fulfillment_type' => StoreFulfillmentTypeEnum::class,
        //        'status' => StoreStatusEnum::class,
        'verification_status' => StoreVerificationStatusEnum::class,
        'visibility_status' => StoreVisibilityStatusEnum::class,
        'is_recommended' => 'boolean',
        'allows_pickup' => 'boolean',
        'is_pickup_cod_enabled' => 'boolean',
        'is_scheduled_delivery_enabled' => 'boolean',
        'scheduled_delivery_buffer_minutes' => 'integer',
        'max_advance_booking_days' => 'integer',
        'is_temporarily_closed' => 'boolean',
        'scheduled_delivery_slot_duration' => 'integer',
        'scheduled_delivery_slot_capacity' => 'integer',
        'prep_concurrency' => 'integer',
        'reschedule_deadline_hours' => 'integer',
    ];

    public function setNameAttribute($value): void
    {
        $this->attributes['name'] = $value;
        $this->attributes['slug'] = generateUniqueSlug(self::class, $value);
    }

    public function getDistanceAttribute()
    {
        if (! isset($this->user_latitude) || ! isset($this->user_longitude)) {
            return null;
        }

        return DeliveryZoneService::calculateDistance(
            $this->user_latitude,
            $this->user_longitude,
            $this->latitude,
            $this->longitude
        );
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function productVariants(): HasMany
    {
        return $this->hasMany(StoreProductVariant::class);
    }

    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(StoreInventoryLog::class);
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(SellerFeedback::class, 'store_id');
    }

    public function storeVariantAddons(): HasMany
    {
        return $this->hasMany(StoreProductVariantAddon::class, 'store_id');
    }

    /**
     * Store-level inventory / pricing rows for addon items.
     */
    public function addonItems(): HasMany
    {
        return $this->hasMany(StoreAddonItem::class, 'store_id');
    }

    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(DeliveryZone::class, 'store_zone', 'store_id', 'zone_id');
    }

    public function deliveryZones(): BelongsToMany
    {
        return $this->zones();
    }

    public function getProductCountAttribute(): int
    {
        $storeId = $this->id ?? 0;

        return Product::whereHas('variants.storeProductVariants', function ($q) use ($storeId) {
            $q->where('store_id', $storeId);
        })->count();
    }

    // get store all product avg rating
    public function getAvgProductsRatingAttribute()
    {
        return Review::whereHas('product.variants.storeProductVariants', function ($q) {
            $q->where('store_id', $this->id);
        })
            ->avg('rating');
    }

    public function getAvgStoreRatingAttribute()
    {
        return SellerFeedback::where('store_id', $this->id)->avg('rating');
    }

    public function getTotalStoreFeedbackAttribute()
    {
        return SellerFeedback::where('store_id', $this->id)->count();
    }

    public function getStoreLogoAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::STORE_LOGO()) ?? null;
    }

    public function getStoreBannerAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::STORE_BANNER()) ?? null;
    }

    public function getAddressProofAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::ADDRESS_PROOF()) ?? null;
    }

    public function getVoidedCheckAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::VOIDED_CHECK()) ?? null;
    }

    /**
     * Check store status based on status field
     */
    public function checkStoreStatus(): array
    {
        return [
            'is_open' => $this->isAcceptingOrders(),
            'status' => $this->status,
        ];
    }

    public function isAcceptingOrders(): bool
    {
        $verificationStatus = $this->verification_status instanceof StoreVerificationStatusEnum
            ? $this->verification_status->value
            : (string) $this->verification_status;
        $visibilityStatus = $this->visibility_status instanceof StoreVisibilityStatusEnum
            ? $this->visibility_status->value
            : (string) $this->visibility_status;

        return $this->status === StoreStatusEnum::ONLINE()
            && ! $this->is_temporarily_closed
            && $verificationStatus === StoreVerificationStatusEnum::APPROVED()
            && $visibilityStatus === StoreVisibilityStatusEnum::VISIBLE()
            && $this->isWithinOperatingHours();
    }

    /**
     * Check if store is online
     */
    public function isOnline(): bool
    {
        return $this->status === StoreStatusEnum::ONLINE();
    }

    private function isWithinOperatingHours(): bool
    {
        $operatingHours = $this->metadata['operating_hours'] ?? null;

        if (! is_array($operatingHours)) {
            return true;
        }

        $now = Carbon::now(config('app.timezone', 'UTC'));
        $dayConfig = $operatingHours[strtolower($now->format('l'))] ?? null;

        if (! is_array($dayConfig)) {
            return true;
        }

        if (isset($dayConfig['is_open']) && ! filter_var($dayConfig['is_open'], FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        if (empty($dayConfig['open_time']) || empty($dayConfig['close_time'])) {
            return false;
        }

        try {
            $timezone = config('app.timezone', 'UTC');
            $openTime = Carbon::parse($now->format('Y-m-d').' '.$dayConfig['open_time'], $timezone);
            $closeTime = Carbon::parse($now->format('Y-m-d').' '.$dayConfig['close_time'], $timezone);

            if ($closeTime->lessThanOrEqualTo($openTime)) {
                $closeTime->addDay();
            }

            if ($now->lessThan($openTime) || $now->greaterThanOrEqualTo($closeTime)) {
                return false;
            }

            $hasBreak = ! empty($dayConfig['has_break'])
                && filter_var($dayConfig['has_break'], FILTER_VALIDATE_BOOLEAN);

            if (! $hasBreak || empty($dayConfig['break_start_time']) || empty($dayConfig['break_end_time'])) {
                return true;
            }

            $breakStart = Carbon::parse($now->format('Y-m-d').' '.$dayConfig['break_start_time'], $timezone);
            $breakEnd = Carbon::parse($now->format('Y-m-d').' '.$dayConfig['break_end_time'], $timezone);

            if ($breakEnd->lessThanOrEqualTo($breakStart)) {
                $breakEnd->addDay();
            }

            return ! ($now->greaterThanOrEqualTo($breakStart) && $now->lessThan($breakEnd));
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Check if store is offline
     */
    public function isOffline(): bool
    {
        return $this->status === StoreStatusEnum::OFFLINE();
    }

    public function deliveryTimeSlots(): HasMany
    {
        return $this->hasMany(DeliveryTimeSlot::class);
    }

    public function slotOverrides(): HasMany
    {
        return $this->hasMany(StoreSlotOverride::class);
    }

    public function availabilityNotifications(): MorphMany
    {
        return $this->morphMany(AvailabilityNotification::class, 'subscribable');
    }

    public function scopeWithAvailabilityNotificationFor(Builder $query, ?int $userId): Builder
    {
        if ($userId === null) {
            return $query;
        }

        return $query->withExists([
            'availabilityNotifications as availability_notification_exists' => fn (Builder $notificationQuery) => $notificationQuery
                ->where('user_id', $userId)
                ->where('status', 'pending'),
        ]);
    }

    public function isNotifiedByUser(?int $userId = null): bool
    {
        $userId = $userId ?? auth('sanctum')->id() ?? request()->user('sanctum')?->id;
        if (! $userId) {
            return false;
        }

        return $this->availabilityNotifications()
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->exists();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(SpatieMediaCollectionName::STORE_LOGO())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::STORE_BANNER())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::ADDRESS_PROOF())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::VOIDED_CHECK())->singleFile();
    }
}
