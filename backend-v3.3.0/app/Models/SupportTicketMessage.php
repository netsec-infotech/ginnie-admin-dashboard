<?php

namespace App\Models;

use App\Enums\SpatieMediaCollectionName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\File;

class SupportTicketMessage extends Model implements HasMedia
{
    use InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'send_by',
        'user_id',
        'ticket_id',
        'client_message_id',
        'type',
        'message',
        'metadata',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(SpatieMediaCollectionName::SUPPORT_ATTACHMENTS())
            // Store chat attachments on the public disk so the UI can render direct storage URLs.
            ->useDisk('public')
            ->acceptsFile(fn (File $file) => in_array($file->mimeType, [
                'image/jpeg', 'image/png', 'image/webp', 'application/pdf',
            ], true));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class);
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
