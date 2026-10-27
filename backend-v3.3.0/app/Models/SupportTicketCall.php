<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketCall extends Model
{
    protected $fillable = [
        'ticket_id', 'requested_by', 'handled_by', 'phone', 'status', 'scheduled_at',
        'attempted_at', 'outcome', 'private_note',
    ];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'attempted_at' => 'datetime'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class);
    }
}
