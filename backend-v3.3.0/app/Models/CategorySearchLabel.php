<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategorySearchLabel extends Model
{
    protected $fillable = [
        'category_id',
        'search_labels',
    ];

    protected $casts = [
        'search_labels' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
