<?php

namespace App\Jobs;

use App\Models\WhatsAppMessage;
use App\Services\WhatsAppService;
use App\Support\WhatsAppTemplateCatalog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(protected int $whatsAppMessageId)
    {
    }

    public function handle(WhatsAppService $whatsAppService): void
    {
        $message = WhatsAppMessage::find($this->whatsAppMessageId);
        if (! $message || $message->status === 'sent') {
            return;
        }

        $payload = $message->payload ?? [];
        $result = $whatsAppService->sendTemplate(
            mobile: $message->recipient_mobile,
            templateName: $message->template_name,
            parameters: $payload['parameters'] ?? [],
            language: $message->language,
            message: $message,
            parameterFormat: $payload['parameter_format'] ?? WhatsAppTemplateCatalog::PARAMETER_FORMAT_POSITIONAL
        );

        if ($result['success']) {
            $message->update([
                'provider_message_id' => $result['provider_message_id'] ?? null,
                'status' => 'sent',
                'error' => null,
                'sent_at' => now(),
            ]);

            return;
        }

        $message->update([
            'status' => 'failed',
            'error' => $result['message'],
            'failed_at' => now(),
        ]);
    }
}
