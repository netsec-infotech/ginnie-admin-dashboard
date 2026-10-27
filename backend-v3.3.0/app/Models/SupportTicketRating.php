<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketRating extends Model
{
    protected $fillable = ['ticket_id', 'user_id', 'agent_id', 'score', 'feedback'];

    protected function casts(): array
    {
        return ['score' => 'integer'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
