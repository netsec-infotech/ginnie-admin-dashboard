<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class WatchBuyMediaInspector
{
    /**
     * @return array{duration_ms: int, video_width: int, video_height: int, video_size_bytes: int}
     */
    public function inspect(UploadedFile $video, int $maxDurationSeconds, string $context = 'reel'): array
    {
        $metadata = (new \getID3)->analyze($video->getRealPath());
        $duration = (float) ($metadata['playtime_seconds'] ?? 0);
        $width = (int) ($metadata['video']['resolution_x'] ?? 0);
        $height = (int) ($metadata['video']['resolution_y'] ?? 0);
        $codec = strtolower(implode(' ', array_filter([
            $metadata['video']['codec'] ?? null,
            $metadata['video']['fourcc'] ?? null,
            $metadata['video']['encoder'] ?? null,
        ])));
        $audioCodec = strtolower(implode(' ', array_filter([
            $metadata['audio']['codec'] ?? null,
            $metadata['audio']['dataformat'] ?? null,
        ])));

        $errors = [];
        $allowedExtensions = ['mp4', 'mov'];
        $ext = strtolower($video->getClientOriginalExtension());
        if (! in_array($metadata['fileformat'] ?? null, ['mp4', 'quicktime'], true) || ! in_array($ext, $allowedExtensions, true)) {
            $errors[] = __($context === 'status' ? 'labels.watch_buy_status_video_must_be_mp4' : 'labels.watch_buy_video_must_be_mp4');
        }
        if ($duration <= 0 || $duration > $maxDurationSeconds) {
            $errors[] = __($context === 'status' ? 'labels.watch_buy_status_video_duration_invalid' : 'labels.watch_buy_video_duration_invalid', ['max' => $maxDurationSeconds]);
        }
        if ($width <= 0 || $height <= 0) {
            $errors[] = __($context === 'status' ? 'labels.watch_buy_status_video_dimensions_unreadable' : 'labels.watch_buy_video_dimensions_unreadable');
        }
        if (! $this->isAllowedVideoCodec($codec, $context)) {
            $errors[] = __($context === 'status' ? 'labels.watch_buy_status_video_must_use_h264' : 'labels.watch_buy_video_must_use_h264');
        }
        if (isset($metadata['audio']) && $audioCodec !== '' && ! str_contains($audioCodec, 'aac')) {
            $errors[] = __($context === 'status' ? 'labels.watch_buy_status_audio_must_use_aac' : 'labels.watch_buy_audio_must_use_aac');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([($context === 'status' ? 'media' : 'video') => $errors]);
        }

        return [
            'duration_ms' => (int) round($duration * 1000),
            'video_width' => $width,
            'video_height' => $height,
            'video_size_bytes' => (int) $video->getSize(),
        ];
    }

    private function isAllowedVideoCodec(string $codec, string $context): bool
    {
        if ($codec === '') {
            return true;
        }

        if (str_contains($codec, 'h.264') || str_contains($codec, 'h264') || str_contains($codec, 'avc')) {
            return true;
        }

        return str_contains($codec, 'h.265')
            || str_contains($codec, 'h265')
            || str_contains($codec, 'hevc')
            || str_contains($codec, 'hvc1')
            || str_contains($codec, 'hev1');
    }
}