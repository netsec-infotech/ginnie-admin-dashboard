<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HomeLayout extends Model
{
    use HasFactory;

    protected $fillable = [
        'platform',
        'scope_type',
        'scope_id',
        'zone_id',
        'is_active',
    ];

    protected $casts = [
        'scope_id' => 'integer',
        'zone_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function sections(): HasMany
    {
        return $this->hasMany(HomeSection::class)->orderBy('sort_order');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(HomeLayoutRevision::class)->latest();
    }

    /** Category this layout is scoped to (only when scope_type = category). */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'scope_id');
    }

    /** Delivery zone this layout is scoped to (only when scope_type = zone). */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class, 'scope_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Layouts for a given platform (app | web). */
    public function scopeForPlatform(Builder $query, string $platform): Builder
    {
        return $query->where('platform', $platform);
    }
}
