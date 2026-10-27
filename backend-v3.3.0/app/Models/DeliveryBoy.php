<?php

namespace App\Models;

use App\Enums\DeliveryBoy\DeliveryBoyVerificationStatusEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Enums\Wallet\WalletTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class DeliveryBoy extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'delivery_zone_id',
        'status',
        'full_name',
        'address',
        'driver_license',
        'driver_license_number',
        'vehicle_type',
        'vehicle_registration',
        'verification_status',
        'verification_remark',
        'referral_code',
        'friends_code',
        'drop_count',
        'is_flagged',
        'is_blocked',
        'blocked_at',
        'blocked_reason',
        'blocked_by_admin_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'verification_status' => DeliveryBoyVerificationStatusEnum::class,
            'drop_count' => 'integer',
            'is_flagged' => 'boolean',
            'is_blocked' => 'boolean',
            'blocked_at' => 'datetime',
        ];
    }

    /**
     * Admin who blocked this rider, if any.
     */
    public function blockedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by_admin_id');
    }

    public function getDriverLicenseAttribute(): ?array
    {
        return $this->getMedia(SpatieMediaCollectionName::DRIVER_LICENSE())
            ->map(function ($media) {
                return $media->getUrl();
            })->toArray();
    }
    public function getVehicleRegistrationAttribute(): ?array
    {
        return $this->getMedia(SpatieMediaCollectionName::VEHICLE_REGISTRATION())
            ->map(function ($media) {
                return $media->getUrl();
            })->toArray();
    }

    public function getProfileImageAttribute(): ?string
    {
        return $this->user?->getFirstMediaUrl(SpatieMediaCollectionName::PROFILE_IMAGE());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The delivery boy's payout wallet. Keyed on users.id + type=delivery_boy
     * so rider earnings stay isolated from the same user's customer wallet.
     */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class, 'user_id', 'user_id')
            ->where('type', WalletTypeEnum::DELIVERY_BOY->value);
    }

    public function location(): HasOne
    {
        return $this->hasOne(DeliveryBoyLocation::class);
    }

    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DeliveryBoyAssignment::class);
    }

    public function referralAsReferrer(): HasMany
    {
        return $this->hasMany(\App\Models\DeliveryBoyReferral::class, 'referrer_id');
    }

    public function referralAsReferred(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\DeliveryBoyReferral::class, 'referred_id');
    }

    public function referralEarnings(): HasMany
    {
        return $this->hasMany(\App\Models\DeliveryBoyReferralEarning::class, 'beneficiary_id');
    }

    /**
     * Get online settlements for the delivery boy.
     * 
     * [CHANGE] Added relation to track online cash settlements
     */
    public function onlineSettlements(): HasMany
    {
        return $this->hasMany(DeliveryBoyOnlineSettlement::class);
    }

    /**
     * Calculate total unsettled COD cash from completed assignments.
     * 
     * [CHANGE] Added helper method to calculate remaining unsettled cash balance
     */
    public function getUnsettledCashAttribute(): float
    {
        $summary = $this->assignments()
            ->where('status', \App\Enums\DeliveryBoy\DeliveryBoyAssignmentStatusEnum::COMPLETED())
            ->where('cod_cash_collected', '>', 0)
            ->selectRaw('SUM(cod_cash_collected) as collected, SUM(cod_cash_submitted) as submitted')
            ->first();

        $collected = (float) ($summary->collected ?? 0);
        $submitted = (float) ($summary->submitted ?? 0);

        return max(0.0, $collected - $submitted);
    }

    /**
     * Check if the rider's unsettled cash has reached or exceeded the global limit.
     * 
     * [CHANGE] Added helper method to check if the cash collection limit is reached
     */
    public function isCashLimitReached(): bool
    {
        $settingService = app(\App\Services\SettingService::class);
        $settings = $settingService->getSettingByVariable(\App\Enums\SettingTypeEnum::DELIVERY_BOY());

        $status = (bool) ($settings->value['cashCollectionLimitStatus'] ?? false);
        $limit = (float) ($settings->value['cashCollectionLimitAmount'] ?? 0);

        if (!$status || $limit <= 0) {
            return false;
        }

        return $this->unsettled_cash >= $limit;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(SpatieMediaCollectionName::DRIVER_LICENSE());
        $this->addMediaCollection(SpatieMediaCollectionName::VEHICLE_REGISTRATION());
        $this->addMediaCollection(SpatieMediaCollectionName::PROFILE_IMAGE())->singleFile();
    }

    protected static function booted(): void
    {
        static::forceDeleting(function ($deliveryBoy) {
            $deliveryBoy->clearMediaCollection(SpatieMediaCollectionName::DRIVER_LICENSE());
            $deliveryBoy->clearMediaCollection(SpatieMediaCollectionName::VEHICLE_REGISTRATION());
            $deliveryBoy->clearMediaCollection(SpatieMediaCollectionName::PROFILE_IMAGE());
        });
    }
}
