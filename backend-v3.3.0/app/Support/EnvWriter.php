<?php

namespace App\Support;

use RuntimeException;

class EnvWriter
{
    public static function value(string $value): string
    {
        $value = str_replace(["\r", "\n"], '', $value);

        return '"' . addcslashes($value, '\\"$`') . '"';
    }

    public static function stripKeys(string $contents, array $keys): string
    {
        $pattern = '/^\s*(' . implode('|', array_map('preg_quote', $keys)) . ')\s*=/i';

        $kept = array_filter(
            explode("\n", $contents),
            static fn (string $line): bool => preg_match($pattern, $line) !== 1
        );

        return rtrim(implode("\n", $kept), "\n");
    }

    public static function write(string $path, string $contents): void
    {
        if (file_exists($path) && ! @copy($path, $path . '.backup')) {
            throw new RuntimeException('Could not back up the environment file.');
        }

        $temp = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';

        if (@file_put_contents($temp, $contents) === false || ! @rename($temp, $path)) {
            @unlink($temp);

            throw new RuntimeException('Could not write the environment file.');
        }
    }
}
