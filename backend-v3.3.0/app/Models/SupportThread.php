<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportThread extends Model
{
    protected $fillable = ['uuid', 'user_id', 'active_ticket_id', 'last_message_at'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activeTicket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'active_ticket_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'thread_id')->orderBy('id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(SupportThreadRead::class, 'thread_id');
    }
}
