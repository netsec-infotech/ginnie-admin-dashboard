<?php

namespace App\Services;

use App\Enums\SpatieMediaCollectionName;
use App\Enums\SplashSlotEnum;
use App\Models\SplashScreen;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SplashScreenService
{
    public const CACHE_PREFIX = 'splash_config:';

    public function save(array $payload): SplashScreen
    {
        $screen = DB::transaction(function () use ($payload): SplashScreen {
            $screen = SplashScreen::query()->updateOrCreate(
                [
                    'app_type' => $payload['app_type'],
                    'slot' => $payload['slot'],
                ],
                [
                    'version' => $this->newVersion(),
                    'media_type' => $payload['bg_type'],
                    'bg_type' => $payload['bg_type'],
                    'bg_color' => $payload['bg_color'] ?? null,
                    'min_display_seconds' => (int) $payload['min_display_seconds'],
                    'activate_at' => $payload['activate_at'] ?? null,
                    'overlay_enabled' => (bool) ($payload['overlay_enabled'] ?? false),
                    'overlay_type' => (bool) ($payload['overlay_enabled'] ?? false) ? ($payload['overlay_type'] ?? null) : null,
                    'overlay_x_percent' => (float) $payload['overlay_x_percent'],
                    'overlay_y_percent' => (float) $payload['overlay_y_percent'],
                    'overlay_width_percent' => (float) $payload['overlay_width_percent'],
                ],
            );

            $this->syncBackgroundMedia($screen, $payload['bg_type']);
            $this->syncOverlayMedia($screen, (bool) ($payload['overlay_enabled'] ?? false), $payload['overlay_type'] ?? null);

            return $screen->fresh();
        });

        $this->clearCache($screen->app_type);

        return $screen;
    }

    public function promote(SplashScreen $staged): SplashScreen
    {
        $current = DB::transaction(function () use ($staged): SplashScreen {
            // Keep one archived row per app so the unique app/slot constraint remains simple.
            SplashScreen::query()
                ->forApp($staged->app_type)
                ->forSlot(SplashSlotEnum::ARCHIVED())
                ->delete();

            SplashScreen::query()
                ->forApp($staged->app_type)
                ->forSlot(SplashSlotEnum::CURRENT())
                ->update(['slot' => SplashSlotEnum::ARCHIVED(), 'version' => $this->newVersion()]);

            $staged->update([
                'slot' => SplashSlotEnum::CURRENT(),
                'version' => $this->newVersion(),
                'activate_at' => null,
            ]);

            return $staged->fresh();
        });

        $this->clearCache($current->app_type);

        return $current;
    }

    public function promoteDueForApp(string $appType): ?SplashScreen
    {
        $staged = SplashScreen::query()
            ->forApp($appType)
            ->forSlot(SplashSlotEnum::STAGED_NEXT())
            ->whereNotNull('activate_at')
            ->where('activate_at', '<=', now())
            ->first();

        if ($staged) {
            return $this->promote($staged);
        }

        return null;
    }

    public function clearCache(string $appType): void
    {
        Cache::forget(self::CACHE_PREFIX.$appType);
    }

    private function syncBackgroundMedia(SplashScreen $screen, string $bgType): void
    {
        if ($bgType === 'image') {
            $this->replaceLayerFromRequest($screen, 'bg_image', SpatieMediaCollectionName::SPLASH_BACKGROUND_IMAGE());
            $screen->clearMediaCollection(SpatieMediaCollectionName::SPLASH_BACKGROUND_VIDEO());
            return;
        }

        if ($bgType === 'video') {
            $this->replaceLayerFromRequest($screen, 'bg_video', SpatieMediaCollectionName::SPLASH_BACKGROUND_VIDEO());
            $screen->clearMediaCollection(SpatieMediaCollectionName::SPLASH_BACKGROUND_IMAGE());
            return;
        }

        $screen->clearMediaCollection(SpatieMediaCollectionName::SPLASH_BACKGROUND_IMAGE());
        $screen->clearMediaCollection(SpatieMediaCollectionName::SPLASH_BACKGROUND_VIDEO());
    }

    private function syncOverlayMedia(SplashScreen $screen, bool $enabled, ?string $overlayType): void
    {
        if (! $enabled) {
            $screen->clearMediaCollection(SpatieMediaCollectionName::SPLASH_OVERLAY_IMAGE());
            $screen->clearMediaCollection(SpatieMediaCollectionName::SPLASH_OVERLAY_LOTTIE());
            return;
        }

        if ($overlayType === 'image') {
            $this->replaceLayerFromRequest($screen, 'overlay_image', SpatieMediaCollectionName::SPLASH_OVERLAY_IMAGE());
            $screen->clearMediaCollection(SpatieMediaCollectionName::SPLASH_OVERLAY_LOTTIE());
            return;
        }

        if ($overlayType === 'lottie') {
            $this->replaceLayerFromRequest($screen, 'overlay_lottie', SpatieMediaCollectionName::SPLASH_OVERLAY_LOTTIE());
            $screen->clearMediaCollection(SpatieMediaCollectionName::SPLASH_OVERLAY_IMAGE());
        }
    }

    private function replaceLayerFromRequest(SplashScreen $screen, string $requestField, string $collectionName): void
    {
        if (! request()->hasFile($requestField)) {
            return;
        }

        $file = request()->file($requestField);
        $baseName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $extension = strtolower($file->getClientOriginalExtension());
        $fileName = "{$baseName}-{$screen->version}.{$extension}";

        $screen->clearMediaCollection($collectionName);
        $screen->addMedia($file)
            ->usingFileName($fileName)
            ->toMediaCollection($collectionName);
    }

    private function newVersion(): string
    {
        return (string) Str::ulid();
    }
}
