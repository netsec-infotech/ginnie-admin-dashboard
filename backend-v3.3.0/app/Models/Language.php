<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Language extends Model
{
    protected $fillable = [
        'surface',
        'code',
        'name',
        'native_name',
        'country_code',
        'direction',
        'is_active',
        'is_default',
        'is_recommended',
        'sort_order',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(LanguageTranslation::class);
    }

    public function getFlagUrlAttribute(): ?string
    {
        if (empty($this->country_code)) {
            return null;
        }

        $code = strtolower((string) $this->country_code);
        $relativePath = "assets/theme/img/flags/{$code}.svg";

        if (is_file(public_path($relativePath))) {
            return asset($relativePath);
        }

        return null;
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'is_recommended' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}

