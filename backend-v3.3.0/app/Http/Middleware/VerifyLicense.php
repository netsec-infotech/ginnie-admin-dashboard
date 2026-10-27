<?php

namespace App\Http\Middleware;

use App\Services\LicenseActivationStore;
use App\Services\LicenseHostPolicy;
use App\Services\LicenseValidator;
use App\Types\Api\ApiResponseType;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class VerifyLicense
{
    public function __construct(
        private readonly LicenseValidator $validator,
        private readonly LicenseHostPolicy $hostPolicy,
        private readonly LicenseActivationStore $activationStore
    ) {}

    public function handle(Request $request, Closure $next)
    {
        if ($request->is('install*', 'installer*', 'license/revalidate*', 'docs', 'documentation*')) {
            return $next($request);
        }

        $activation = $this->activationStore->current();
        $purchaseCode = (string) ($activation['purchase_code'] ?? '');
        $token = (string) ($activation['token'] ?? '');
        $signature = (string) ($activation['signature'] ?? '');
        $domainUrl = $this->hostPolicy->domainForRequest($request);

        if ($purchaseCode === '' || $token === '' || $signature === '') {
            return $this->deny($request, 'Application is not licensed.');
        }

        $expected = LicenseValidator::signature($purchaseCode, $domainUrl, $token);

        if (! hash_equals($expected, $signature)) {
            $storedDomain = (string) ($activation['domain_url'] ?? '');
            if ($this->hostPolicy->isLocalDevelopment($domainUrl) && $this->hostPolicy->isLocalDevelopment($storedDomain)) {
                $expected = LicenseValidator::signature($purchaseCode, $storedDomain, $token);
            }

            if (! hash_equals($expected, $signature)) {
                return $this->deny($request, 'License signature mismatch.');
            }
        }

        if ($this->hostPolicy->isLocalDevelopment($domainUrl)) {
            return $next($request);
        }

        if (! $request->is('admin', 'admin/*') || ! $this->recheckIsDue($activation)) {
            return $next($request);
        }

        $lock = null;

        try {
            $lock = Cache::lock('license_recheck_lock', 15);

            if (! $lock->get()) {
                return $next($request);
            }
        } catch (\Throwable $e) {
            Log::warning('License recheck lock unavailable: '.$e->getMessage());

            return $next($request);
        }

        try {
            $result = $this->validator->validate($purchaseCode, $domainUrl);

            if ($result['unavailable'] ?? false) {
                $this->rememberUnavailableRetry();

                return $next($request);
            }

            if (! ($result['success'] ?? false)) {
                return $this->deny($request, 'License validation failed: '.($result['message'] ?? 'Unknown'));
            }

            $newToken = (string) ($result['data']['token'] ?? '');

            try {
                $this->activationStore->persist($purchaseCode, $domainUrl, $newToken, 'remote', now());
            } catch (\Throwable $e) {
                Log::warning('License activation refresh could not be persisted: '.$e->getMessage());

                return $next($request);
            }

            $this->rememberSuccessfulRecheck();

            return $next($request);
        } finally {
            try {
                $lock?->release();
            } catch (\Throwable $e) {
                Log::warning('License recheck lock release failed: '.$e->getMessage());
            }
        }
    }

    private function recheckIsDue(array $activation): bool
    {
        try {
            if (Cache::has('license_recheck_retry_after') || Cache::has('license_recheck_ts')) {
                return false;
            }
        } catch (\Throwable $e) {
            return false;
        }

        $lastValidatedAt = $activation['last_validated_at'] ?? null;

        if (! is_string($lastValidatedAt) || $lastValidatedAt === '') {
            return true;
        }

        try {
            return Carbon::parse($lastValidatedAt)
                ->addMinutes((int) config('license.recheck_minutes', 720))
                ->isPast();
        } catch (\Throwable $e) {
            return true;
        }
    }

    private function deny(Request $request, string $message)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return ApiResponseType::sendJsonResponse(false, $message, [], 403);
        }

        return redirect()->route('license.revalidate', [
            'intended' => $request->fullUrl(),
            'message' => $message,
        ]);
    }

    private function rememberUnavailableRetry(): void
    {
        try {
            Cache::put(
                'license_recheck_retry_after',
                now()->timestamp,
                now()->addMinutes((int) config('license.unavailable_retry_minutes', 60))
            );
        } catch (\Throwable $e) {
            Log::warning('License retry window could not be cached: '.$e->getMessage());
        }
    }

    private function rememberSuccessfulRecheck(): void
    {
        try {
            Cache::forget('license_recheck_retry_after');
            Cache::put(
                'license_recheck_ts',
                now()->timestamp,
                now()->addMinutes((int) config('license.recheck_minutes', 720))
            );
        } catch (\Throwable $e) {
            Log::warning('License recheck timestamp could not be cached: '.$e->getMessage());
        }
    }
}
