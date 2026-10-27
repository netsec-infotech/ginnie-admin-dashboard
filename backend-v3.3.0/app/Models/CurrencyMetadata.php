<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencyMetadata extends Model
{
    protected $table = 'currency_metadata';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'decimal_places',
        'minor_unit_factor',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'decimal_places' => 'integer',
            'minor_unit_factor' => 'integer',
            'is_enabled' => 'boolean',
        ];
    }
}
