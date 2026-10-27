<?php

namespace App\Support\Currency;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SystemCurrency
{
    public static function code(string $fallback = 'USD'): string
    {
        try {
            if (! Schema::hasTable('settings')) {
                return $fallback;
            }

            $raw = DB::table('settings')->where('variable', 'system')->value('value');
            $value = is_string($raw) ? json_decode($raw, true) : null;
            $code = is_array($value) ? strtoupper(trim((string) ($value['currency'] ?? ''))) : '';

            return preg_match('/^[A-Z]{3}$/', $code) ? $code : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
