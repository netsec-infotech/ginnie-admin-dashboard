<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HomeSavedPreset extends Model
{
    use HasFactory;

    protected $table = 'home_saved_presets';

    protected $fillable = [
        'name',
        'description',
        'type',
        'style',
        'config',
        'items',
        'created_by',
    ];

    /**
     * Get the casts for the model attributes.
     */
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'items' => 'array',
        ];
    }
}
