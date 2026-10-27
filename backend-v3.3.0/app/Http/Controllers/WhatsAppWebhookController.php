<?php

namespace App\Http\Controllers;

use App\Enums\SettingTypeEnum;
use App\Models\WhatsAppMessage;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request, SettingService $settingService): JsonResponse|string
    {
        $settings = $settingService->getSettingValues(SettingTypeEnum::NOTIFICATION());

        $mode = $request->input('hub.mode', $request->input('hub_mode'));
        $token = $request->input('hub.verify_token', $request->input('hub_verify_token'));
        $challenge = $request->input('hub.challenge', $request->input('hub_challenge'));

        if ($mode === 'subscribe' && hash_equals((string) ($settings['whatsappWebhookVerifyToken'] ?? ''), (string) $token)) {
            return (string) $challenge;
        }

        abort(403);
    }

    public function handle(Request $request): JsonResponse
    {
        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) data_get($entry, 'changes', []) as $change) {
                foreach ((array) data_get($change, 'value.statuses', []) as $statusPayload) {
                    $this->updateMessageStatus($statusPayload);
                }
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * Meta sends status updates independently from the original API response,
     * so each timestamp is idempotently applied to the tracked provider id.
     *
     * @param  array<string, mixed>  $statusPayload
     */
    private function updateMessageStatus(array $statusPayload): void
    {
        $messageId = (string) ($statusPayload['id'] ?? '');
        $status = (string) ($statusPayload['status'] ?? '');

        if ($messageId === '' || $status === '') {
            return;
        }

        $message = WhatsAppMessage::where('provider_message_id', $messageId)->first();
        if (! $message) {
            return;
        }

        $timestamp = isset($statusPayload['timestamp'])
            ? now()->setTimestamp((int) $statusPayload['timestamp'])
            : now();

        $updates = ['status' => $status];

        if ($status === 'delivered') {
            $updates['delivered_at'] = $message->delivered_at ?: $timestamp;
        } elseif ($status === 'read') {
            $updates['read_at'] = $message->read_at ?: $timestamp;
        } elseif ($status === 'failed') {
            $updates['failed_at'] = $message->failed_at ?: $timestamp;
            $updates['error'] = data_get($statusPayload, 'errors.0.message', $message->error);
        }

        $message->update($updates);
    }
}
