<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class HomeCache
{
    public const LAYOUT_VERSION_KEY = 'home_layout_cache_version';

    public const TTL = 600;

    public static function layoutKey(string $platform, string $scopeType, ?int $scopeId, ?int $marketId, int $page, int $perPage): string
    {
        return sprintf(
            'home_layout_struct:v%d:%s:%s:%s:%s:%d:%d',
            self::version(self::LAYOUT_VERSION_KEY),
            $platform,
            $scopeType,
            $scopeId ?? 'global',
            $marketId ?? 'none',
            $page,
            $perPage,
        );
    }

    /**
     * Invalidate home layout caches by bumping version.
     */
    public static function flushLayout(): void
    {
        self::bump(self::LAYOUT_VERSION_KEY);
    }

    private static function version(string $key): int
    {
        return (int) Cache::get($key, 0);
    }

    private static function bump(string $key): void
    {
        if (Cache::increment($key) === false) {
            Cache::forever($key, 1);
        }
    }
}
