<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicketType extends Model
{
    protected $fillable = ['title', 'slug', 'context', 'guidance', 'customer_quick_replies', 'agent_quick_replies', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'customer_quick_replies' => 'array',
            'agent_quick_replies' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
