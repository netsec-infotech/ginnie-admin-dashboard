<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Str;

final class DemoMode
{
    public static function isDemoUser(?User $user): bool
    {
        if (! $user || ! self::isEnabled()) {
            return false;
        }

        $email = Str::lower(trim((string) $user->email));
        $demoEmails = collect(config('demo.user_emails', []))
            ->map(fn (mixed $configuredEmail): string => Str::lower(trim((string) $configuredEmail)));

        return $demoEmails->contains($email);
    }

    private static function isEnabled(): bool
    {
        return (bool) (Setting::systemSettings()['demoMode'] ?? false);
    }
}
