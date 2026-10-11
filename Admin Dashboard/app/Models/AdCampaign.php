<?php

namespace App\Models;

use App\Enums\Advertisement\AdCampaignStatusEnum;
use App\Enums\Advertisement\AdPlacementEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdCampaign extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'seller_id',
        'product_id',
        'budget',
        'spent',
        'cpc_rate_snapshot',
        'placements',
        'status',
        'approved_by',
        'rejection_reason',
        'force_stopped_by',
        'force_stop_reason',
        'force_stopped_at',
        'wallet_transaction_id',
    ];

    protected $casts = [
        'budget'             => 'decimal:2',
        'spent'              => 'decimal:2',
        'cpc_rate_snapshot'  => 'decimal:4',
        'placements'         => 'array',
        'status'             => AdCampaignStatusEnum::class,
        'force_stopped_at'   => 'datetime',
    ];

    // ---------- Relationships ----------

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function forceStoppedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'force_stopped_by');
    }

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class);
    }

    public function stats(): HasMany
    {
        return $this->hasMany(AdCampaignStat::class, 'campaign_id');
    }

    // ---------- Scopes ----------

    public function scopePending($query)
    {
        return $query->where('status', AdCampaignStatusEnum::PENDING_APPROVAL());
    }

    public function scopeRunning($query)
    {
        return $query->where('status', AdCampaignStatusEnum::RUNNING());
    }

    public function scopeActiveLocked($query)
    {
        return $query->whereIn('status', AdCampaignStatusEnum::activeLocked());
    }

    public function scopeForSeller($query, int $sellerId)
    {
        return $query->where('seller_id', $sellerId);
    }

    // ---------- Accessors ----------

    /** Remaining budget not yet spent. */
    public function getRemainingBudgetAttribute(): string
    {
        return number_format(max(0, $this->budget - $this->spent), 2);
    }

    /** Spend progress as a percentage (0–100). */
    public function getSpendProgressAttribute(): int
    {
        if ($this->budget <= 0) {
            return 0;
        }

        return (int) min(100, round(($this->spent / $this->budget) * 100));
    }

    /** Default placements when not explicitly set. */
    public static function defaultPlacements(): array
    {
        return AdPlacementEnum::all();
    }
}
