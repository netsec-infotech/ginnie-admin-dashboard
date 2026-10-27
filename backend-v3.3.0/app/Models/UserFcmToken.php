<?php

namespace App\Models;

use App\Enums\DeviceTypeEnum;
use App\Enums\Notification\NotificationRoleTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @method static create(array $array)
 * @method static where(string $string, mixed $value)
 * @method static find(mixed $id)
 * @method static updateOrCreate(array $attributes, array $values = [])
 */
class UserFcmToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'fcm_token',
        'device_type',
        'role_type',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'fcm_token' => 'string',
            'device_type' => DeviceTypeEnum::class,
            'role_type' => NotificationRoleTypeEnum::class,
        ];
    }

    /**
     * Get the user that owns the FCM token.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function scopeRoleType($query, NotificationRoleTypeEnum|string $roleType)
    {
        $value = $roleType instanceof NotificationRoleTypeEnum ? $roleType->value : $roleType;

        return $query->where('role_type', $value);
    }
}
