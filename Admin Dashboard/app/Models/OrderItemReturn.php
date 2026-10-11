<?php

namespace App\Models;

use App\Enums\Order\OrderItemReturnPickupStatusEnum;
use App\Enums\Order\OrderItemReturnStatusEnum;
use App\Enums\Order\OrderReturnActorEnum;
use App\Enums\Order\ReturnResponsiblePartyEnum;
use App\Enums\Order\AdminLossBearerEnum;
use App\Enums\SpatieMediaCollectionName;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class OrderItemReturn extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'order_item_returns';

    protected $appends = ['images', 'pickup_images', 'rejection_photos', 'rejection_video', 'inspection_photos', 'resolution_photos'];

    protected $fillable = [
        'order_item_id',
        'order_id',
        'user_id',
        'seller_id',
        'store_id',
        'delivery_boy_id',
        'reason',
        'return_reason_id',
        'return_reason_name',
        'snapshot_otp_required',
        'snapshot_proof_required',
        'refund_amount',
        'seller_comment',
        'pickup_status',
        'pickup_otp',
        'pickup_otp_verified',
        'store_rejection_reason',
        'action_on_rejection',
        'rejection_reason_id',
        'rejection_reason_name',
        'responsible_party',
        'deduct_from_rider',
        'rider_deduction_type',
        'deduction_amount',
        'return_status',
        'seller_approved_at',
        'store_confirmed_at',
        'store_rejected_at',
        'admin_decided_at',
        'picked_up_at',
        'received_at',
        'refund_processed_at',
        'decided_by_type',
        'decided_by_id',
        'admin_comment',
        // ==========================================================
        // Return Dispute System
        // Phase 1
        // Add dispute resolution fillable columns
        // ==========================================================
        'pickup_condition',
        'pickup_remarks',
        'dispute_reason',
        'disputed_at',
        'refund_customer',
        'loss_bearer',
        'pay_rider',
        // ==========================================================
        // Return Dispute System
        // Phase 4
        // Add admin dispute resolution columns
        // ==========================================================
        'resolved_by_admin_at',
        'resolved_by',
        'admin_resolution',
    ];

    protected $casts = [
        'refund_amount' => 'decimal:2',
        'deduction_amount' => 'decimal:2',
        'pickup_otp_verified' => 'boolean',
        'snapshot_otp_required' => 'boolean',
        'snapshot_proof_required' => 'boolean',
        'deduct_from_rider' => 'boolean',
        'seller_approved_at' => 'datetime',
        'store_confirmed_at' => 'datetime',
        'store_rejected_at' => 'datetime',
        'admin_decided_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'received_at' => 'datetime',
        'refund_processed_at' => 'datetime',
        'decided_by_type' => OrderReturnActorEnum::class,
        'responsible_party' => ReturnResponsiblePartyEnum::class,
        // ==========================================================
        // Return Dispute System
        // Phase 1
        // Add dispute resolution casts
        // ==========================================================
        'disputed_at' => 'datetime',
        'refund_customer' => 'boolean',
        'pay_rider' => 'boolean',
        // ==========================================================
        // Return Dispute System
        // Phase 4
        // Add admin dispute resolution casts
        // ==========================================================
        'resolved_by_admin_at' => 'datetime',
        'admin_resolution' => AdminLossBearerEnum::class,
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function returnReason(): BelongsTo
    {
        return $this->belongsTo(ReturnReason::class, 'return_reason_id');
    }

    public function rejectionReason(): BelongsTo
    {
        return $this->belongsTo(ReturnRejectionReason::class, 'rejection_reason_id');
    }

    public function getImagesAttribute()
    {
        return $this->getMedia(SpatieMediaCollectionName::ITEM_RETURN_IMAGES())
            ->map(fn($media) => $media->getUrl());
    }

    /**
     * Get pickup verification images uploaded by the delivery boy.
     */
    public function getPickupImagesAttribute()
    {
        return $this->getMedia(SpatieMediaCollectionName::RETURN_PICKUP_IMAGES())
            ->map(fn($media) => $media->getUrl());
    }

    /**
     * Get seller rejection proof photos.
     */
    public function getRejectionPhotosAttribute()
    {
        return $this->getMedia(SpatieMediaCollectionName::SELLER_REJECTION_PHOTOS())
            ->map(fn($media) => $media->getUrl());
    }

    /**
     * Get seller inspection proof photos.
     */
    public function getInspectionPhotosAttribute()
    {
        // ==========================================================
        // Return Dispute System
        // Phase 1
        // Add getInspectionPhotosAttribute relation method
        // ==========================================================
        return $this->getMedia(SpatieMediaCollectionName::SELLER_INSPECTION_PHOTOS())
            ->map(fn($media) => $media->getUrl());
    }

    /**
     * Get seller rejection optional proof video.
     */
    public function getRejectionVideoAttribute()
    {
        $media = $this->getFirstMedia(SpatieMediaCollectionName::SELLER_REJECTION_VIDEO());
        return $media ? $media->getUrl() : null;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function deliveryBoy(): BelongsTo
    {
        return $this->belongsTo(DeliveryBoy::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_id');
    }

    // ==========================================================
    // Return Dispute System
    // Phase 4
    // Add resolvedBy and platformExpense relationships, and getResolutionPhotosAttribute accessor
    // ==========================================================
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function platformExpense(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PlatformExpenseRecord::class, 'order_item_return_id');
    }

    public function getResolutionPhotosAttribute()
    {
        return $this->getMedia(SpatieMediaCollectionName::ADMIN_RESOLUTION_PHOTOS())
            ->map(fn($media) => $media->getUrl());
    }

    protected static function booted(): void
    {
        static::deleted(function ($orderReturn) {
            $orderReturn->clearMediaCollection();
        });
    }
}
