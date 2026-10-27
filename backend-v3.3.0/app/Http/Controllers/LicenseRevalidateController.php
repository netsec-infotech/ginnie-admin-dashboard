<?php

namespace App\Http\Controllers;

use App\Services\LicenseActivationStore;
use App\Services\LicenseHostPolicy;
use App\Services\LicenseValidator;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LicenseRevalidateController extends Controller
{
    public function __construct(
        private readonly LicenseValidator $validator,
        private readonly LicenseHostPolicy $hostPolicy,
        private readonly LicenseActivationStore $activationStore
    ) {}

    public function form(Request $request)
    {
        $domain = $this->hostPolicy->domainForRequest($request);
        $intended = $this->safeIntended($request);

        return view('license.revalidate', compact('domain', 'intended'));
    }

    public function verify(Request $request)
    {
        $validated = $request->validate([
            'purchase_code' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9-]+$/'],
        ]);
        $purchaseCode = $validated['purchase_code'];
        $domainUrl = $this->hostPolicy->domainForRequest($request);
        $intended = $this->safeIntended($request);

        if ($this->hostPolicy->isLocalDevelopment($domainUrl)) {
            try {
                $this->activationStore->persist(
                    $purchaseCode,
                    $domainUrl,
                    'local-development',
                    'local'
                );
            } catch (\Throwable $e) {
                return ApiResponseType::sendJsonResponse(false, 'License Error: Failed to persist the local development license.');
            }

            return $this->successResponse($intended, 'Local development license accepted without remote validation.');
        }

        $result = $this->validator->validate($purchaseCode, $domainUrl);

        if (! ($result['success'] ?? false)) {
            if ($result['unavailable'] ?? false) {
                if (! config('license.allow_offline_activation', true)) {
                    return ApiResponseType::sendJsonResponse(false, 'License Error: This public server cannot reach the license service.');
                }

                try {
                    $this->activationStore->persistOffline($purchaseCode, $domainUrl);
                } catch (\Throwable $e) {
                    return ApiResponseType::sendJsonResponse(false, 'License Error: Failed to save the offline license activation.');
                }

                return $this->successResponse(
                    $intended,
                    'License service is unavailable. Offline activation saved; validation will be retried automatically.'
                );
            }

            $msg = $result['message'] ?? 'License verification failed.';

            return ApiResponseType::sendJsonResponse(false, $msg);
        }

        $token = $result['data']['token'] ?? '';

        try {
            $this->activationStore->persist($purchaseCode, $domainUrl, $token, 'remote', now());
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'License Error: Failed to write license to environment file.'
            );
        }

        try {
            Cache::forget('license_recheck_ts');
            Cache::forget('license_recheck_retry_after');
        } catch (\Throwable $e) {
        }

        return $this->successResponse($intended, 'License verified successfully.');
    }

    private function successResponse(string $intended, string $message)
    {
        return ApiResponseType::sendJsonResponse(
            success: true,
            message: $message,
            data: [
                'redirect_url' => $intended ?: url('/'),
            ]
        );
    }

    private function safeIntended(Request $request): string
    {
        $fallback = url('/');
        $intended = trim((string) $request->get('intended', $fallback));

        if (str_starts_with($intended, '/') && ! str_starts_with($intended, '//')) {
            return $intended;
        }

        $intendedHost = parse_url($intended, PHP_URL_HOST);
        $intendedScheme = parse_url($intended, PHP_URL_SCHEME);
        $intendedPort = parse_url($intended, PHP_URL_PORT) ?? ($intendedScheme === 'https' ? 443 : 80);

        if (is_string($intendedHost)
            && strcasecmp($intendedHost, $request->getHost()) === 0
            && $intendedScheme === $request->getScheme()
            && $intendedPort === $request->getPort()) {
            return $intended;
        }

        return $fallback;
    }
}
