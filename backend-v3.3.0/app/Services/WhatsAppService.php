<?php

namespace App\Services;

use App\Enums\SettingTypeEnum;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Support\WhatsAppTemplateCatalog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsAppService
{
    public function __construct(protected SettingService $settingService)
    {
    }

    /**
     * @param  array<int|string, string>  $parameters
     * @return array{success: bool, message: string, provider_message_id?: string|null, gateway_status?: int|null, gateway_body?: mixed}
     */
    public function sendTemplate(
        string $mobile,
        string $templateName,
        array $parameters,
        ?string $language = null,
        ?WhatsAppMessage $message = null,
        string $parameterFormat = WhatsAppTemplateCatalog::PARAMETER_FORMAT_POSITIONAL
    ): array {
        $settings = $this->settings();

        if (! filter_var($settings['whatsappEnabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return ['success' => false, 'message' => 'WhatsApp is disabled.'];
        }

        $phoneNumberId = trim((string) ($settings['whatsappPhoneNumberId'] ?? ''));
        $accessToken = trim((string) ($settings['whatsappAccessToken'] ?? ''));

        if ($phoneNumberId === '' || $accessToken === '') {
            return ['success' => false, 'message' => 'WhatsApp credentials are missing.'];
        }

        $recipient = $this->normalizeMobile($mobile);
        if ($recipient === null) {
            return ['success' => false, 'message' => 'Invalid WhatsApp recipient mobile number.'];
        }

        $payload = $this->templatePayload(
            recipient: $recipient,
            templateName: $templateName,
            language: $language ?: (string) ($settings['whatsappDefaultLanguage'] ?? 'en_US'),
            parameters: $parameters,
            parameterFormat: $parameterFormat
        );

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->asJson()
                ->timeout(15)
                ->post("https://graph.facebook.com/v20.0/{$phoneNumberId}/messages", $payload);

            $body = $response->json();
            $providerMessageId = $body['messages'][0]['id'] ?? null;

            if ($response->successful() && $providerMessageId) {
                return [
                    'success' => true,
                    'message' => 'WhatsApp message sent successfully.',
                    'provider_message_id' => $providerMessageId,
                    'gateway_status' => $response->status(),
                    'gateway_body' => $body,
                ];
            }

            Log::warning('WhatsApp template send failed', [
                'status' => $response->status(),
                'body' => $this->maskSensitiveData($body),
                'message_id' => $message?->id,
            ]);

            return [
                'success' => false,
                'message' => $body['error']['message'] ?? 'WhatsApp provider rejected the message.',
                'gateway_status' => $response->status(),
                'gateway_body' => $body,
            ];
        } catch (\Throwable $e) {
            Log::error('WhatsApp template send exception', [
                'error' => $e->getMessage(),
                'message_id' => $message?->id,
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function normalizeMobile(?string $mobile): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $mobile);

        if (! is_string($digits) || strlen($digits) < 8 || strlen($digits) > 15) {
            return null;
        }

        return $digits;
    }

    /**
     * @param  array<int|string, string>  $parameters
     * @return array<string, mixed>
     */
    public function templatePayload(
        string $recipient,
        string $templateName,
        string $language,
        array $parameters,
        string $parameterFormat = WhatsAppTemplateCatalog::PARAMETER_FORMAT_POSITIONAL
    ): array
    {
        $bodyParameters = $parameterFormat === WhatsAppTemplateCatalog::PARAMETER_FORMAT_NAMED
            ? collect($parameters)
                ->map(fn (string $parameter, string|int $name) => [
                    'type' => 'text',
                    'parameter_name' => (string) $name,
                    'text' => Str::limit($parameter, 1024, ''),
                ])
                ->values()
                ->all()
            : collect($parameters)
                ->map(fn (string $parameter) => [
                    'type' => 'text',
                    'text' => Str::limit($parameter, 1024, ''),
                ])
                ->values()
                ->all();

        $components = [];
        if (! empty($bodyParameters)) {
            $components[] = [
                'type' => 'body',
                'parameters' => $bodyParameters,
            ];
        }

        return [
            'messaging_product' => 'whatsapp',
            'to' => $recipient,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
                'components' => $components,
            ],
        ];
    }

    public function activeApprovedTemplateForModule(string $module): ?WhatsAppTemplate
    {
        return WhatsAppTemplate::query()
            ->where('module', $module)
            ->where('is_active', true)
            ->where('status', WhatsAppTemplateCatalog::STATUS_APPROVED)
            ->whereNotNull('template_name')
            ->first();
    }

    public function queueModuleMessage(
        string $module,
        ?string $mobile,
        array $data,
        array $relations = []
    ): WhatsAppMessage {
        $recipientMobile = $this->normalizeMobile($mobile);
        $template = $this->activeApprovedTemplateForModule($module);

        if (! $recipientMobile) {
            return $this->createSkippedMessage($module, (string) ($mobile ?: 'missing'), $data, $relations, 'Missing or invalid mobile number.');
        }

        if (! $template) {
            return $this->createSkippedMessage($module, $recipientMobile, $data, $relations, 'No approved active WhatsApp template configured for this module.');
        }

        $parameters = $this->resolveTemplateParameters($template, $data);

        $message = WhatsAppMessage::create(array_merge($relations, [
            'module' => $module,
            'recipient_mobile' => $recipientMobile,
            'template_name' => (string) $template->template_name,
            'language' => $template->language,
            'status' => 'queued',
            'payload' => [
                'parameter_format' => $template->parameter_format,
                'parameters' => $parameters,
                'data' => $data,
            ],
        ]));

        SendWhatsAppMessageJob::dispatch($message->id);

        return $message;
    }

    public function sendModuleTest(string $module, string $mobile): array
    {
        $template = $this->activeApprovedTemplateForModule($module);

        if (! $template) {
            return ['success' => false, 'message' => 'No approved active WhatsApp template configured for this module.'];
        }

        return $this->sendTemplate(
            mobile: $mobile,
            templateName: (string) $template->template_name,
            parameters: $this->resolveTemplateParameters($template, WhatsAppTemplateCatalog::sampleData($module)),
            language: $template->language,
            parameterFormat: $template->parameter_format
        );
    }

    /**
     * @return array{success: bool, message: string, results: array<int, array<string, mixed>>}
     */
    public function syncConfiguredTemplates(?string $module = null): array
    {
        $query = WhatsAppTemplate::query()
            ->whereNotNull('template_name')
            ->where('template_name', '!=', '');

        if ($module !== null) {
            $query->where('module', $module);
        }

        $templates = $query->get();
        if ($templates->isEmpty()) {
            return ['success' => false, 'message' => 'No configured WhatsApp templates found to sync.', 'results' => []];
        }

        $results = [];
        $listedTemplates = null;

        foreach ($templates as $template) {
            if ($template->meta_template_id) {
                $result = $this->syncTemplateFromMeta($template);
                $results[] = $result;
                continue;
            }

            if ($listedTemplates === null) {
                $listResult = $this->listMetaTemplates();
                if (! $listResult['success']) {
                    $error = (string) $listResult['message'];
                    foreach ($templates as $failedTemplate) {
                        $failedTemplate->update(['sync_error' => $error]);
                        $results[] = [
                            'module' => $failedTemplate->module,
                            'success' => false,
                            'message' => $error,
                        ];
                    }

                    return ['success' => false, 'message' => $error, 'results' => $results];
                }

                $listedTemplates = $listResult['templates'];
            }

            $metaTemplate = $this->findMatchingMetaTemplate($template, $listedTemplates);
            if (! $metaTemplate) {
                $message = 'Matching Meta template was not found for the configured name and language.';
                $template->update(['sync_error' => $message]);
                $results[] = [
                    'module' => $template->module,
                    'success' => false,
                    'message' => $message,
                    'fetched_templates' => $this->summarizeMetaTemplates($listedTemplates),
                ];
                continue;
            }

            $results[] = $this->applyMetaTemplate($template, $metaTemplate);
        }

        $success = collect($results)->contains(fn (array $result) => (bool) ($result['success'] ?? false));

        return [
            'success' => $success,
            'message' => $success ? 'WhatsApp template sync completed.' : 'No WhatsApp templates were synced.',
            'results' => $results,
        ];
    }

    /**
     * @return array{success: bool, message: string, templates: array<int, array<string, mixed>>, gateway_status?: int|null, gateway_body?: mixed}
     */
    public function listMetaTemplates(): array
    {
        $settings = $this->settings();
        $businessAccountId = trim((string) ($settings['whatsappBusinessAccountId'] ?? ''));
        $accessToken = trim((string) ($settings['whatsappAccessToken'] ?? ''));

        if ($businessAccountId === '' || $accessToken === '') {
            return ['success' => false, 'message' => 'WhatsApp Business Account ID or access token is missing.', 'templates' => []];
        }

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(15)
            ->get("https://graph.facebook.com/v20.0/{$businessAccountId}/message_templates", [
                'fields' => 'id,name,language,status,category,parameter_format,components,quality_score,rejected_reason',
                'limit' => 100,
            ]);

        $body = $response->json();
        if ($response->successful()) {
            return [
                'success' => true,
                'message' => 'Meta templates fetched successfully.',
                'templates' => $body['data'] ?? [],
                'gateway_status' => $response->status(),
                'gateway_body' => $body,
            ];
        }

        return [
            'success' => false,
            'message' => $body['error']['message'] ?? 'Unable to fetch Meta templates.',
            'templates' => [],
            'gateway_status' => $response->status(),
            'gateway_body' => $body,
        ];
    }

    /**
     * @return array{success: bool, message: string, template?: array<string, mixed>, gateway_status?: int|null, gateway_body?: mixed}
     */
    public function getMetaTemplate(string $templateId): array
    {
        $accessToken = trim((string) ($this->settings()['whatsappAccessToken'] ?? ''));
        if ($accessToken === '') {
            return ['success' => false, 'message' => 'WhatsApp access token is missing.'];
        }

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(15)
            ->get("https://graph.facebook.com/v20.0/{$templateId}", [
                'fields' => 'id,name,language,status,category,parameter_format,components,quality_score,rejected_reason',
            ]);

        $body = $response->json();
        if ($response->successful()) {
            return [
                'success' => true,
                'message' => 'Meta template fetched successfully.',
                'template' => $body,
                'gateway_status' => $response->status(),
                'gateway_body' => $body,
            ];
        }

        return [
            'success' => false,
            'message' => $body['error']['message'] ?? 'Unable to fetch Meta template.',
            'gateway_status' => $response->status(),
            'gateway_body' => $body,
        ];
    }

    public function metaStatusToLocalStatus(?string $status): string
    {
        return match (strtoupper((string) $status)) {
            'APPROVED', 'ACTIVE' => WhatsAppTemplateCatalog::STATUS_APPROVED,
            'PENDING', 'IN_APPEAL' => WhatsAppTemplateCatalog::STATUS_SUBMITTED,
            'REJECTED' => WhatsAppTemplateCatalog::STATUS_REJECTED,
            'PAUSED', 'DISABLED', 'LIMIT_EXCEEDED' => WhatsAppTemplateCatalog::STATUS_PAUSED,
            default => WhatsAppTemplateCatalog::STATUS_DRAFT,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $metaTemplates
     */
    protected function findMatchingMetaTemplate(WhatsAppTemplate $template, array $metaTemplates): ?array
    {
        $configuredName = $this->normalizeMetaTemplateName((string) $template->template_name);
        $configuredLanguage = $this->normalizeMetaLanguage((string) $template->language);
        $sameNameTemplates = [];

        foreach ($metaTemplates as $metaTemplate) {
            if ($this->normalizeMetaTemplateName((string) ($metaTemplate['name'] ?? '')) !== $configuredName) {
                continue;
            }

            $sameNameTemplates[] = $metaTemplate;

            if (
                $configuredLanguage !== ''
                && $this->normalizeMetaLanguage((string) ($metaTemplate['language'] ?? '')) === $configuredLanguage
            ) {
                return $metaTemplate;
            }
        }

        // Meta may return "en" for templates shown as English, while admins commonly enter en_US.
        // If the template name is unique, it is safe to sync and let Meta's language replace ours.
        return count($sameNameTemplates) === 1 ? $sameNameTemplates[0] : null;
    }

    protected function normalizeMetaTemplateName(string $name): string
    {
        return Str::of($name)
            ->trim()
            ->lower()
            ->replaceMatches('/\s+/', '_')
            ->toString();
    }

    protected function normalizeMetaLanguage(string $language): string
    {
        $language = strtolower(trim($language));

        return match ($language) {
            'en_us', 'en-us', 'en' => 'en',
            default => str_replace('-', '_', $language),
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $metaTemplates
     */
    protected function summarizeMetaTemplates(array $metaTemplates): string
    {
        if (empty($metaTemplates)) {
            return 'none';
        }

        return collect($metaTemplates)
            ->take(10)
            ->map(fn (array $template) => sprintf(
                '%s (%s)',
                (string) ($template['name'] ?? 'missing_name'),
                (string) ($template['language'] ?? 'missing_language')
            ))
            ->implode(', ');
    }

    /**
     * @return array<string, mixed>
     */
    protected function syncTemplateFromMeta(WhatsAppTemplate $template): array
    {
        $result = $this->getMetaTemplate((string) $template->meta_template_id);
        if (! $result['success']) {
            $template->update(['sync_error' => $result['message']]);

            return [
                'module' => $template->module,
                'success' => false,
                'message' => $result['message'],
            ];
        }

        return $this->applyMetaTemplate($template, $result['template']);
    }

    /**
     * @param  array<string, mixed>  $metaTemplate
     * @return array<string, mixed>
     */
    protected function applyMetaTemplate(WhatsAppTemplate $template, array $metaTemplate): array
    {
        $parameterFormat = strtolower((string) ($metaTemplate['parameter_format'] ?? $template->parameter_format ?: WhatsAppTemplateCatalog::PARAMETER_FORMAT_POSITIONAL));
        if (! in_array($parameterFormat, WhatsAppTemplateCatalog::parameterFormats(), true)) {
            $parameterFormat = WhatsAppTemplateCatalog::PARAMETER_FORMAT_POSITIONAL;
        }

        $template->update([
            'meta_template_id' => (string) ($metaTemplate['id'] ?? $template->meta_template_id),
            'template_name' => (string) ($metaTemplate['name'] ?? $template->template_name),
            'language' => (string) ($metaTemplate['language'] ?? $template->language),
            'parameter_format' => $parameterFormat,
            'status' => $this->metaStatusToLocalStatus($metaTemplate['status'] ?? null),
            'category' => $metaTemplate['category'] ?? null,
            'quality_score' => data_get($metaTemplate, 'quality_score.score'),
            'rejected_reason' => $metaTemplate['rejected_reason'] ?? null,
            'components' => $metaTemplate['components'] ?? null,
            'last_synced_at' => now(),
            'sync_error' => null,
        ]);

        return [
            'module' => $template->module,
            'success' => true,
            'message' => 'Template synced.',
            'status' => $template->fresh()->status,
        ];
    }

    /**
     * Resolve admin-defined Meta variables against event data.
     *
     * @return array<int|string, string>
     */
    public function resolveTemplateParameters(WhatsAppTemplate $template, array $data): array
    {
        $mapping = $template->variables ?: WhatsAppTemplateCatalog::defaultVariables(
            $template->module,
            $template->parameter_format
        );

        if ($template->parameter_format === WhatsAppTemplateCatalog::PARAMETER_FORMAT_NAMED) {
            return collect($mapping)
                ->mapWithKeys(fn (string $source, string|int $name) => [(string) $name => (string) data_get($data, $source, '')])
                ->all();
        }

        return collect($mapping)
            ->sortKeysUsing(fn (string|int $left, string|int $right) => (int) $left <=> (int) $right)
            ->map(fn (string $source) => (string) data_get($data, $source, ''))
            ->values()
            ->all();
    }

    protected function createSkippedMessage(string $module, string $recipientMobile, array $data, array $relations, string $error): WhatsAppMessage
    {
        return WhatsAppMessage::create(array_merge($relations, [
            'module' => $module,
            'recipient_mobile' => $recipientMobile,
            'template_name' => '',
            'language' => (string) ($this->settings()['whatsappDefaultLanguage'] ?? 'en_US'),
            'status' => 'skipped',
            'error' => $error,
            'payload' => ['data' => $data],
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return $this->settingService->getSettingValues(SettingTypeEnum::NOTIFICATION());
    }

    public function maskSensitiveData(mixed $value): mixed
    {
        if (is_array($value)) {
            $masked = [];
            foreach ($value as $key => $item) {
                $masked[$key] = in_array((string) $key, ['access_token', 'token', 'Authorization'], true)
                    ? '***'
                    : $this->maskSensitiveData($item);
            }

            return $masked;
        }

        return $value;
    }
}
