<?php

use Illuminate\Support\Str;

if (!function_exists('hyperAsset')) {
    function hyperAsset($path, $secure = null)
    {
        $url = app('url')->asset($path, $secure);

        // Optional: Add cache-busting using file modification time
        $fullPath = public_path($path);
        if (file_exists($fullPath)) {
            $timestamp = filemtime($fullPath);
            return $url . '?v=' . $timestamp;
        }

        return $url;
    }
}

if (!function_exists('generateUniqueSlug')) {
    function generateUniqueSlug($model, $title, $slugField = 'slug', $id = null): string
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $i = 1;

        while (
            $model::withoutGlobalScopes()
                ->where($slugField, $slug)
                ->when($id, function ($query) use ($id) {
                    $query->where('id', '!=', $id);
                })
                ->exists()
        ) {
            $slug = $originalSlug . '-' . $i++;
        }
        return $slug;
    }
}

if (!function_exists('getCurrencySymbol')) {
    function getCurrencySymbol(): string
    {
        $setting = App\Models\Setting::where('variable', 'system')->first();
        return $setting->value['currencySymbol'] ?? '$';
    }
}

if (!function_exists('formatOrderStatusLabel')) {
    /**
     * Convert a raw order/order-item status string into a localised, human-friendly
     * label. Looks up labels.<status> first; falls back to title-cased underscore
     * replacement when no translation key exists. Used by API resources so mobile
     * apps don't have to mirror the copy.
     */
    function formatOrderStatusLabel(?string $status): string
    {
        if (empty($status)) {
            return '';
        }

        $key = 'labels.' . $status;
        $translated = __($key);
        if ($translated !== $key) {
            return (string) $translated;
        }

        return Str::title(str_replace('_', ' ', $status));
    }
}

// [CHANGED BY AGENT]: Added global media_url helper to resolve media URLs dynamically across local disk or configured cloud storage/CDN
if (!function_exists('media_url')) {
    function media_url(?string $path, ?string $disk = null): string
    {
        if (empty($path)) {
            return '';
        }

        // If path is already an absolute URL (http:// or https://), return directly
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $targetDisk = $disk ?: config('media-library.disk_name', 'public');

        // Fast-path: if disk has a custom asset CDN or base url configured, construct URL directly without adapter overhead
        $configuredUrl = config("filesystems.disks.{$targetDisk}.url");
        if (!empty($configuredUrl)) {
            return rtrim($configuredUrl, '/') . '/' . ltrim($path, '/');
        }

        try {
            return \Illuminate\Support\Facades\Storage::disk($targetDisk)->url($path);
        } catch (\Throwable $e) {
            // Fallback to asset URL if disk url generation fails
            return asset('storage/' . ltrim($path, '/'));
        }
    }
}
