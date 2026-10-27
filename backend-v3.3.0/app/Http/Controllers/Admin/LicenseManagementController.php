<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LicenseActivationStore;
use App\Services\LicenseHostPolicy;
use App\Services\LicenseValidator;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LicenseManagementController extends Controller
{
    public function __construct(
        private readonly LicenseValidator $validator,
        private readonly LicenseHostPolicy $hostPolicy,
        private readonly LicenseActivationStore $activationStore
    ) {}

    public function index(Request $request)
    {
        $activation = $this->activationStore->current();
        $domainUrl = ($activation['domain_url'] ?? '') ?: $this->hostPolicy->domainForRequest($request);
        $purchaseCode = (string) ($activation['purchase_code'] ?? '');
        $maskedCode = $purchaseCode === '' ? 'Not Set' : $this->maskPurchaseCode($purchaseCode);

        return view('admin.license.index', compact('domainUrl', 'maskedCode'));
    }

    public function deregister(Request $request)
    {
        $activation = $this->activationStore->current();
        $purchaseCode = (string) ($activation['purchase_code'] ?? '');
        $domainUrl = (string) (($activation['domain_url'] ?? '') ?: $this->hostPolicy->domainForRequest($request));
        $token = (string) ($activation['token'] ?? '');

        if ($purchaseCode === '') {
            return ApiResponseType::sendJsonResponse(false, 'No active license purchase code found to deregister.');
        }

        if (! $this->hostPolicy->isLocalDevelopment($domainUrl)) {
            if ($token === '') {
                return ApiResponseType::sendJsonResponse(false, 'No active license token found to deregister.');
            }

            // Pass the previous activation token required by the remote deregistration endpoint.
            $result = $this->validator->deregister($purchaseCode, $domainUrl, $token);

            if (! ($result['success'] ?? false)) {
                $msg = $result['message'] ?? 'License deregistration failed.';

                return ApiResponseType::sendJsonResponse(false, $msg);
            }
        }

        try {
            $this->activationStore->clear();
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, 'License Error: Failed to remove local license data.');
        }

        try {
            Cache::forget('license_recheck_ts');
            Cache::forget('license_recheck_retry_after');
        } catch (\Throwable $e) {
        }

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'License successfully deregistered. The application is now locked for this domain.',
            data: [
                'redirect_url' => url('/'),
            ]
        );
    }

    private function maskPurchaseCode(string $purchaseCode): string
    {
        if (strlen($purchaseCode) <= 8) {
            return str_repeat('*', strlen($purchaseCode));
        }

        return substr($purchaseCode, 0, 4)
            .str_repeat('*', strlen($purchaseCode) - 8)
            .substr($purchaseCode, -4);
    }
}
