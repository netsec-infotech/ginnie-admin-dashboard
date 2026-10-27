<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupportTicket extends Model
{
    protected $fillable = [
        'thread_id',
        'parent_ticket_id',
        'ticket_type_id',
        'user_id',
        'order_id',
        'order_item_id',
        'assigned_to',
        'subject',
        'slug',
        'email',
        'description',
        'resolution_summary',
        'resolved_by',
        'resolved_at',
        'closed_at',
        'last_message_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(SupportThread::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(SupportTicketType::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class, 'ticket_id')->orderBy('id');
    }

    public function latestConversationMessage(): HasOne
    {
        return $this->hasOne(SupportTicketMessage::class, 'ticket_id')
            ->ofMany(['id' => 'max'], fn ($query) => $query->whereIn('send_by', ['user', 'admin']));
    }

    public function calls(): HasMany
    {
        return $this->hasMany(SupportTicketCall::class, 'ticket_id');
    }

    public function rating(): HasOne
    {
        return $this->hasOne(SupportTicketRating::class, 'ticket_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['open', 'in_progress', 'waiting_customer'], true);
    }
}
