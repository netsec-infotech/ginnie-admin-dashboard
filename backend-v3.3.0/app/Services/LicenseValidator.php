<?php

namespace App\Services;

use App\Types\Api\ApiResponseType;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

class LicenseValidator
{
    protected Client $http;

    public function __construct(?Client $http = null)
    {
        $this->http = $http ?: new Client([
            'timeout' => (float) config('license.timeout', 5),
            'connect_timeout' => (float) config('license.connect_timeout', 5),
        ]);
    }

    public function validate(string $purchaseCode, string $domainUrl): array
    {
        $endpoint = config('license.endpoint', 'https://validator-new.infinitietech.com/api/v1/validate');
        $apiKey = config('license.api_key');

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if (! empty($apiKey)) {
            $headers['X-Api-Key'] = $apiKey;
        }

        try {
            $response = $this->http->post($endpoint, [
                'headers' => $headers,
                'json' => [
                    'purchase_code' => $purchaseCode,
                    'domain_url' => $domainUrl,
                ],
                'http_errors' => false,
            ]);
        } catch (Throwable $e) {
            Log::warning('License validator unavailable: ' . $e->getMessage());

            return $this->unavailable();
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            Log::warning('License validator returned HTTP ' . $response->getStatusCode());

            return $this->unavailable();
        }

        try {
            $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            Log::warning('License validator returned malformed JSON: ' . $e->getMessage());

            return $this->unavailable();
        }

        if (! is_array($data)) {
            return $this->unavailable();
        }

        $isError = false;
        if (array_key_exists('error', $data)) {
            $isError = (bool) $data['error'];
        } elseif (array_key_exists('success', $data)) {
            $isError = ! (bool) $data['success'];
        } else {
            return $this->unavailable();
        }

        if ($isError) {
            return $this->result(false, (string) ($data['message'] ?? 'License verification failed.'), $data, false);
        }

        $token = $data['token'] ?? $data['data']['token'] ?? null;
        if (empty($token) || trim((string) $token) === '') {
            return $this->unavailable();
        }

        if (! isset($data['token']) && isset($token)) {
            $data['token'] = (string) $token;
        }

        return $this->result(true, (string) ($data['message'] ?? 'License verified successfully.'), $data, false);
    }

    public function deregister(string $purchaseCode, string $domainUrl, string $token): array
    {
        $endpoint = config('license.deregister_endpoint', 'https://validator-new.infinitietech.com/api/v1/deregister');
        $apiKey = config('license.api_key');

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if (! empty($apiKey)) {
            $headers['X-Api-Key'] = $apiKey;
        }

        try {
            // Send the stored activation token required by the new deregistration API.
            $response = $this->http->post($endpoint, [
                'headers' => $headers,
                'json' => [
                    'purchase_code' => $purchaseCode,
                    'domain_url' => $domainUrl,
                    'token' => $token,
                ],
                'http_errors' => false,
            ]);
        } catch (Throwable $e) {
            Log::warning('License deregistration validator unavailable: ' . $e->getMessage());

            return $this->unavailable();
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return $this->unavailable();
        }

        try {
            $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            Log::warning('License deregistration validator returned malformed JSON: ' . $e->getMessage());

            return $this->unavailable();
        }

        if (! is_array($data) || (! array_key_exists('error', $data) && ! array_key_exists('success', $data))) {
            return $this->unavailable();
        }

        $isError = array_key_exists('error', $data)
            ? (bool) $data['error']
            : ! (bool) $data['success'];

        return $this->result(
            ! $isError,
            (string) ($data['message'] ?? ($isError ? 'License deregistration failed.' : 'License deregistered successfully.')),
            $data,
            false
        );
    }

    public static function signature(string $purchaseCode, string $domainUrl, string $token): string
    {
        $key = config('app.key', 'app-key-missing');

        return hash_hmac('sha256', $purchaseCode . '|' . $domainUrl . '|' . $token, $key);
    }

    private function unavailable(): array
    {
        return $this->result(false, 'Unable to reach license server.', [], true);
    }

    private function result(bool $success, string $message, array $data, bool $unavailable): array
    {
        return ApiResponseType::toArray($success, $message, $data) + [
            'unavailable' => $unavailable,
        ];
    }
}
