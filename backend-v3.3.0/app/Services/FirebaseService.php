<?php

namespace App\Services;

use App\Models\UserFcmToken;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FirebaseService
{
    protected ?Messaging $messaging = null;

    public function __construct()
    {
        $path = 'settings/service-account-file.json';
        $activeDisk = config('media-library.disk_name', 'public');

        $jsonContent = null;
        if (Storage::disk('local')->exists($path)) {
            $jsonContent = Storage::disk('local')->get($path);
        } elseif (Storage::disk($activeDisk)->exists($path)) {
            $jsonContent = Storage::disk($activeDisk)->get($path);
        }

        if (! $jsonContent) {
            Log::warning('Firebase service account file not found on active cloud or local disk', [
                'path' => $path,
                'activeDisk' => $activeDisk,
            ]);

            return;
        }

        try {
            $json = json_decode($jsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid Firebase service account JSON');
            }

            // Required keys validation
            foreach (['type', 'project_id', 'client_email', 'private_key'] as $key) {
                if (empty($json[$key])) {
                    throw new \Exception("Firebase service account missing key: {$key}");
                }
            }

            $firebase = (new Factory)->withServiceAccount($json);
            $this->messaging = $firebase->createMessaging();

        } catch (\Throwable $e) {
            Log::error('Firebase initialization failed', [
                'error' => $e->getMessage(),
            ]);

            $this->messaging = null; // explicitly disable
        }
    }

    /**
     * Send single notification
     */
    public function sendNotification(
        $token,
        $title,
        $body,
        $image = '',
        $data = [],
        ?array $android = null,
        ?array $apns = null
    ): array {
        if ($this->messaging === null) {
            return [
                'success' => false,
                'error' => 'Firebase is not configured',
            ];
        }

        try {
            $messageData = $this->normalizeData(array_merge([
                'title' => (string) $title,
                'body' => (string) $body,
                'image' => $image ? (string) $image : '',
            ], $data));

            $notification = Notification::create(
                title: $title,
                body: $body,
                imageUrl: $image
            );

            $message = CloudMessage::new()
                ->withData($messageData)
                ->withNotification($notification)
                ->withAndroidConfig($this->buildAndroidConfig($image, $android))
                ->withApnsConfig($this->buildApnsConfig($image, $apns))
                ->toToken($token);

            $this->messaging->send($message);

            return ['success' => true];

        } catch (\Throwable $e) {
            Log::error('Firebase sendNotification failed', [
                'error' => $e->getMessage(),
                'token' => $token,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send bulk notifications
     */
    public function sendBulkNotification(
        array $tokens,
        string $title,
        string $body,
        ?string $image = null,
        array $data = [],
        int $chunkSize = 50,
        ?array $android = null,
        ?array $apns = null
    ): array {
        if ($this->messaging === null) {
            Log::warning('Firebase bulk send skipped: not configured');

            return [
                'success' => 0,
                'failure' => count($tokens),
                'error' => 'Firebase is not configured',
            ];
        }

        $results = [
            'success' => 0,
            'failure' => 0,
            'removed_tokens' => [],
        ];

        $androidConfig = $this->buildAndroidConfig($image, $android);
        $apnsConfig = $this->buildApnsConfig($image, $apns);
        $messageData = $this->normalizeData(array_merge([
            'title' => $title,
            'body' => $body,
            'image' => $image ?? '',
        ], $data));

        Collection::make($tokens)->chunk($chunkSize)->each(function ($chunk) use (&$results, $title, $body, $image, $messageData, $androidConfig, $apnsConfig) {
            try {
                $notification = Notification::create(
                    title: $title,
                    body: $body,
                    imageUrl: $image
                );

                $message = CloudMessage::new()
                    ->withData($messageData)
                    ->withNotification($notification)
                    ->withAndroidConfig($androidConfig)
                    ->withApnsConfig($apnsConfig);

                $multicastResult = $this->messaging->sendMulticast(
                    $message,
                    $chunk->toArray()
                );

                $results['success'] += $multicastResult->successes()->count();
                $results['failure'] += $multicastResult->failures()->count();

                $invalidTokens = $multicastResult->invalidTokens();

                if (! empty($invalidTokens)) {
                    UserFcmToken::whereIn('fcm_token', $invalidTokens)->delete();
                    $results['removed_tokens'] = array_merge(
                        $results['removed_tokens'],
                        $invalidTokens
                    );
                }

            } catch (\Throwable $e) {
                Log::error('Firebase bulk send chunk failed', [
                    'error' => $e->getMessage(),
                ]);

                $results['failure'] += $chunk->count();
            }
        });

        return $results;
    }

    /**
     * FCM's data payload must be a flat map of string => string. Any null,
     * bool, int, or nested value will trigger MessageData::isBinary() errors,
     * so coerce every value to a string here (nulls become empty strings).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    protected function normalizeData(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                $normalized[(string) $key] = '';
            } elseif (is_bool($value)) {
                $normalized[(string) $key] = $value ? 'true' : 'false';
            } elseif (is_scalar($value)) {
                $normalized[(string) $key] = (string) $value;
            } else {
                // arrays/objects can't go in FCM data — JSON-encode them.
                $normalized[(string) $key] = json_encode($value) ?: '';
            }
        }

        return $normalized;
    }

    /**
     * Build APNS payload that ensures iOS displays the notification image.
     * iOS requires `mutable-content: 1` so the Notification Service Extension
     * can fetch the URL from `fcm_options.image` and attach it.
     */
    protected function buildApnsConfig(?string $image, ?array $customApns = null): ApnsConfig
    {
        $config = [
            'headers' => [
                'apns-priority' => '10',
            ],
            'payload' => [
                'aps' => [
                    'mutable-content' => 1,
                    'sound' => 'notification_sound.caf',
                ],
            ],
        ];

        if (! empty($image)) {
            $config['fcm_options'] = ['image' => $image];
        }

        if (! empty($customApns)) {
            $config = array_replace_recursive($config, $customApns);
        }

        return ApnsConfig::fromArray($config);
    }

    /**
     * Build Android payload that explicitly carries the notification image,
     * mirroring the APNS payload for consistency across platforms.
     */
    protected function buildAndroidConfig(?string $image, ?array $customAndroid = null): AndroidConfig
    {
        $config = [
            'priority' => 'high',
            'notification' => [
                'channel_id' => 'custom_sound_channel',
                'sound' => 'notification_sound',
            ],
        ];

        if (! empty($image)) {
            $config['notification']['image'] = $image;
        }

        if (! empty($customAndroid)) {
            $config = array_replace_recursive($config, $customAndroid);
        }

        return AndroidConfig::fromArray($config);
    }
}
