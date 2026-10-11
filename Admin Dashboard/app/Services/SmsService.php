<?php

namespace App\Services;

use App\Enums\SettingTypeEnum;
use App\Enums\SmsMobileFormatEnum;
use App\Libs\libphonenumber\PhoneNumber;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pure transport for the admin-configured SMS gateway. Knows nothing about
 * OTPs, providers, or message templates — it just substitutes placeholders
 * into the request shape stored under the authentication setting.
 */
class SmsService
{
    public function __construct(protected SettingService $settingService)
    {
    }

    /**
     * Fire the admin-configured SMS request.
     *
     * @param PhoneNumber $mobile  Parsed recipient — the format selector decides which shape is substituted into `{mobile}`.
     * @param string      $message Fully composed message body — callers are responsible for templating.
     * @return array{success: bool, message: string}
     */
    public function sendSms(PhoneNumber $mobile, string $message): array
    {
        $config = $this->settingService
            ->getRawSetting(SettingTypeEnum::AUTHENTICATION())?->value ?? [];

        if (empty($config['customSms'])) {
            return ['success' => false, 'message' => __('labels.custom_sms_not_enabled')];
        }

        return $this->sendCustomSms($mobile, $message, $config);
    }

    /**
     * Fire the SMS using an explicit config payload instead of the saved
     * authentication setting. Used by the admin "Send test SMS" action so a
     * customer can verify a gateway change before persisting it. Skips the
     * "is custom SMS enabled" gate on purpose — the caller already opted in
     * by clicking the test button.
     *
     * @param array<string,mixed> $config Same shape as the authentication setting value.
     * @return array{success: bool, message: string}
     */
    public function sendSmsWithConfig(PhoneNumber $mobile, string $message, array $config): array
    {
        return $this->sendCustomSms($mobile, $message, $config);
    }

    private function sendCustomSms(PhoneNumber $mobile, string $message, array $config): array
    {
        try {
            $url = $config['customSmsUrl'] ?? '';
            if (empty($url)) {
                return ['success' => false, 'message' => __('labels.sms_gateway_url_missing')];
            }

            $method = strtoupper($config['customSmsMethod'] ?? 'GET');
            $bag    = $this->buildPlaceholderBag($mobile, $message, $config);

            $headers = $this->buildPairs($config['customSmsHeaderKey'] ?? [], $config['customSmsHeaderValue'] ?? [], $bag);
            $query   = $this->buildPairs($config['customSmsParamsKey'] ?? [], $config['customSmsParamsValue'] ?? [], $bag);
            $body    = $this->buildPairs($config['customSmsBodyKey']   ?? [], $config['customSmsBodyValue']   ?? [], $bag);

            $url = strtr($url, array_map('urlencode', $bag));

            $request = Http::withHeaders($headers)
                ->timeout(8)
                ->connectTimeout(3);

            $encoded = $this->applyBodyEncoding($request, $headers);

            $response = match ($method) {
                'POST'  => $encoded->post($url . ($query ? '?' . http_build_query($query) : ''), $body),
                'PUT'   => $encoded->put($url, $body),
                'PATCH' => $encoded->patch($url, $body),
                default => $request->get($url, array_merge($query, $body)),
            };

            $gatewayStatus = $response->status();
            $gatewayBody   = $response->body();

            if ($response->successful()) {
                return [
                    'success'        => true,
                    'message'        => __('labels.sms_sent_successfully'),
                    'gateway_status' => $gatewayStatus,
                    'gateway_body'   => $gatewayBody,
                ];
            }

            Log::error('Custom SMS failed', [
                'status' => $gatewayStatus,
                'body'   => $gatewayBody,
            ]);

            return [
                'success'        => false,
                'message'        => __('labels.sms_gateway_error'),
                'gateway_status' => $gatewayStatus,
                'gateway_body'   => $gatewayBody,
            ];
        } catch (\Throwable $e) {
            Log::error('Custom SMS exception', ['error' => $e->getMessage()]);

            return [
                'success'       => false,
                'message'       => __('labels.something_went_wrong'),
                'gateway_error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Resolve `{mobile}` per the admin-selected format and expose every variant
     * as its own placeholder so admins can also pull country code separately
     * when their gateway expects split fields.
     */
    private function buildPlaceholderBag(PhoneNumber $mobile, string $message, array $config): array
    {
        $format = $config['customSmsMobileFormat'] ?? SmsMobileFormatEnum::E164_WITH_PLUS();

        $primary = match ($format) {
            SmsMobileFormatEnum::E164_WITHOUT_PLUS()  => $mobile->getCountryCode() . $mobile->getNationalNumber(),
            SmsMobileFormatEnum::NATIONAL()           => $mobile->getNationalNumber(),
            SmsMobileFormatEnum::NATIONAL_WITH_ZERO() => '0' . $mobile->getNationalNumber(),
            default                                   => $mobile->format(),
        };

        return [
            '{mobile}'              => $primary,
            '{mobile_e164}'         => $mobile->format(),
            '{mobile_local}'        => $mobile->getNationalNumber(),
            '{country_code}'        => $mobile->getCountryCodeWithPlus(),
            '{country_code_digits}' => (string) $mobile->getCountryCode(),
            '{message}'             => $message,
        ];
    }

    /**
     * Pick the body encoding (`asJson` / `asMultipart` / `asForm`) based on
     * the Content-Type the admin configured. Mirrors how Postman / Insomnia
     * decide the wire format from the same header — gateways like Termii or
     * MSG91-Flow require a JSON body, while Twilio expects form-urlencoded.
     */
    private function applyBodyEncoding(PendingRequest $request, array $headers): PendingRequest
    {
        foreach ($headers as $key => $value) {
            if (strcasecmp((string) $key, 'Content-Type') !== 0) {
                continue;
            }
            $contentType = strtolower((string) $value);

            if (str_contains($contentType, 'application/json')) {
                return $request->asJson();
            }
            if (str_contains($contentType, 'multipart/form-data')) {
                return $request->asMultipart();
            }
            break;
        }
        return $request->asForm();
    }

    /**
     * Zip parallel key/value arrays from the admin form into a string-keyed
     * map, skipping rows where the key is blank, and substituting every
     * placeholder in the value.
     */
    private function buildPairs(array $keys, array $values, array $bag): array
    {
        $out = [];
        foreach ($keys as $i => $key) {
            if ($key === null || $key === '') {
                continue;
            }
            $value = $values[$i] ?? '';
            $out[(string) $key] = is_string($value) ? strtr($value, $bag) : $value;
        }
        return $out;
    }
}
