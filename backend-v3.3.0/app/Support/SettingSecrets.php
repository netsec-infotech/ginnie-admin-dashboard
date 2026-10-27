<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Secret setting fields handled by the shared settings pipeline. Payment
 * credentials remain plain text; other declared secrets are encrypted.
 */
class SettingSecrets
{
    public const MASK = '__set__';

    private const KEYS = [
        'payment' => [
            // 'stripeSecretKey',
            // 'stripeWebhookSecretKey',
            // 'razorpaySecretKey',
            // 'razorpayWebhookSecret',
            // 'paystackSecretKey',
            // 'paystackWebhookSecret',
            // 'flutterwaveSecretKey',
            // 'flutterwaveEncryptionKey',
            // 'flutterwaveWebhookSecret',
            // 'xenditSecretKey',
            // 'xenditWebhookVerificationToken',
            // 'mercadoPagoAccessToken',
            // 'mercadoPagoWebhookSecret',
        ],
        'email' => [
            // 'smtpPassword',
        ],
        'storage' => [
            // 'awsAccessKeyId',
            // 'awsSecretAccessKey',
        ],
        'authentication' => [
            // 'fireBaseApiKey',
            // 'customSmsHeaderValue',
            // 'customSmsParamsValue',
            // 'customSmsBodyValue',
        ],
        'notification' => [
            'whatsappAccessToken',
            'whatsappWebhookVerifyToken',
        ],
        'broadcast_driver' => [
            // 'reverbSecret',
            // 'pusherSecret',
        ],
    ];

    public static function keysFor(string $variable): array
    {
        return self::KEYS[$variable] ?? [];
    }

    public static function conceal(string $variable, array $values, array $existing = []): array
    {
        if ($variable === 'payment') {
            foreach (self::keysFor($variable) as $key) {
                if (! array_key_exists($key, $values)) {
                    continue;
                }

                if (self::isBlank($values[$key]) || $values[$key] === self::MASK) {
                    $values[$key] = $existing[$key] ?? $values[$key];
                }
            }

            return $values;
        }

        foreach (self::keysFor($variable) as $key) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            $incoming = $values[$key];

            if (self::isBlank($incoming) || $incoming === self::MASK) {
                $values[$key] = $existing[$key] ?? $incoming;

                continue;
            }

            $values[$key] = self::map($incoming, fn ($plain) => Crypt::encryptString($plain));
        }

        return $values;
    }

    public static function reveal(string $variable, array $values): array
    {
        foreach (self::keysFor($variable) as $key) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            $values[$key] = self::map($values[$key], function ($stored) {
                try {
                    return Crypt::decryptString($stored);
                } catch (DecryptException) {
                    return $stored;
                }
            });
        }

        return $values;
    }

    public static function mask(string $variable, array $values): array
    {
        if ($variable === 'payment') {
            return $values;
        }

        foreach (self::keysFor($variable) as $key) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            $values[$key] = self::map(
                $values[$key],
                fn ($plain) => self::isBlank($plain) ? '' : self::MASK
            );
        }

        return $values;
    }

    private static function map(mixed $value, callable $callback): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => self::map($item, $callback), $value);
        }

        if (! is_string($value) || $value === '') {
            return $value;
        }

        return $callback($value);
    }

    private static function isBlank(mixed $value): bool
    {
        if (is_array($value)) {
            return empty(array_filter($value, fn ($item) => ! self::isBlank($item)));
        }

        return ! is_string($value) || $value === '';
    }
}
