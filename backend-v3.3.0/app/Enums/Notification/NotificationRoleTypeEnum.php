<?php

namespace App\Enums\Notification;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * @method static ADMIN()
 * @method static CUSTOMER()
 * @method static SELLER()
 * @method static RIDER()
 */
enum NotificationRoleTypeEnum: string
{
    use InvokableCases, Names, Values;

    case ADMIN = 'admin';
    case CUSTOMER = 'customer';
    case SELLER = 'seller';
    case RIDER = 'rider';

    public static function fromAuthContext(?string $context): ?self
    {
        return match ($context) {
            'admin' => self::ADMIN,
            'seller' => self::SELLER,
            'delivery_boy' => self::RIDER,
            'customer', 'user', 'web', null => self::CUSTOMER,
            default => null,
        };
    }

    public static function fromPanel(string $panel): self
    {
        return match ($panel) {
            'admin' => self::ADMIN,
            'seller' => self::SELLER,
            default => self::CUSTOMER,
        };
    }

    public static function fromSentTo(?string $sentTo): ?self
    {
        return match ($sentTo) {
            'admin' => self::ADMIN,
            'customer', 'user' => self::CUSTOMER,
            'seller' => self::SELLER,
            'delivery_boy', 'rider' => self::RIDER,
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    public function legacySentToValues(): array
    {
        return match ($this) {
            self::ADMIN => ['admin'],
            self::CUSTOMER => ['customer', 'user'],
            self::SELLER => ['seller'],
            self::RIDER => ['delivery_boy', 'rider'],
        };
    }
}
