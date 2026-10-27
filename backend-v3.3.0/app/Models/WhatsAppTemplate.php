<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppTemplate extends Model
{
    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'module',
        'meta_template_id',
        'template_name',
        'language',
        'parameter_format',
        'variables',
        'status',
        'category',
        'quality_score',
        'rejected_reason',
        'components',
        'last_synced_at',
        'sync_error',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'components' => 'array',
            'last_synced_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
