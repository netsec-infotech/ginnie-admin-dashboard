<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdCampaignStat extends Model
{
    protected $fillable = [
        'campaign_id',
        'stat_date',
        'clicks',
        'impressions',
        'spent',
    ];

    protected $casts = [
        'stat_date'   => 'date',
        'clicks'      => 'integer',
        'impressions' => 'integer',
        'spent'       => 'decimal:2',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'campaign_id');
    }
}
