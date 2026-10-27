<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Badge extends Model
{
    protected $fillable = [
        'name',
        'label',
        'bg_color',
        'text_color',
        'border_color',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'badge_id');
    }
}
