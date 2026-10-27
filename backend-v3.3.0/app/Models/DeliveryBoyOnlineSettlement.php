<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DeliveryBoyOnlineSettlement extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'delivery_boy_id',
        'amount',
        'payment_gateway',
        'transaction_reference',
        'status',
        'paid_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    /**
     * Get the delivery boy associated with this online settlement.
     * 
     * @return BelongsTo
     */
    public function deliveryBoy(): BelongsTo
    {
        return $this->belongsTo(DeliveryBoy::class);
    }

    /**
     * Get the assignments/orders paid for in this online settlement.
     * 
     * @return BelongsToMany
     */
    public function assignments(): BelongsToMany
    {
        return $this->belongsToMany(
            DeliveryBoyAssignment::class,
            'delivery_boy_online_settlement_assignments',
            'online_settlement_id',
            'delivery_boy_assignment_id'
        )->withPivot('amount')->withTimestamps();
    }
}
