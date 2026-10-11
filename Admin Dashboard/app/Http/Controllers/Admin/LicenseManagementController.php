<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LicenseValidator;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LicenseManagementController extends Controller
{
    public function index()
    {
        $domainUrl = env('LICENSE_DOMAIN_URL', request()->getSchemeAndHttpHost());
        $purchaseCode = env('LICENSE_PURCHASE_CODE', 'Not Set');
        
        // Mask the purchase code for security
        $maskedCode = 'Not Set';
        if ($purchaseCode !== 'Not Set') {
            $maskedCode = substr($purchaseCode, 0, 4) . str_repeat('*', strlen($purchaseCode) - 8) . substr($purchaseCode, -4);
        }

        return view('admin.license.index', compact('domainUrl', 'maskedCode'));
    }

    public function deregister(Request $request)
    {
        $purchaseCode = env('LICENSE_PURCHASE_CODE');
        $domainUrl = env('LICENSE_DOMAIN_URL', request()->getSchemeAndHttpHost());

        if (empty($purchaseCode)) {
            return ApiResponseType::sendJsonResponse(false, 'No active license purchase code found to deregister.');
        }

        try {
            $validator = new LicenseValidator();
            $result = $validator->deregister($purchaseCode, $domainUrl);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, 'License Error: Unable to reach license server to deregister.');
        }

        if (!($result['success'] ?? false)) {
            $msg = $result['message'] ?? 'License deregistration failed.';
            return ApiResponseType::sendJsonResponse(false, $msg);
        }

        // Successfully deregistered on the validator server.
        // Now remove local license details to lock the app.
        $envPath = base_path('.env');
        if (file_exists($envPath)) {
            $content = file_get_contents($envPath) ?: '';
            $rows = explode("\n", (string)$content);
            $unwanted = 'LICENSE_PURCHASE_CODE|LICENSE_DOMAIN_URL|LICENSE_TOKEN|LICENSE_SIGNATURE';
            $cleanArray = preg_grep("/{$unwanted}/i", $rows, PREG_GREP_INVERT);
            $cleanString = implode("\n", $cleanArray);

            try {
                file_put_contents($envPath, rtrim($cleanString, "\n") . "\n");
            } catch (\Throwable $e) {
                return ApiResponseType::sendJsonResponse(false, 'License Error: Failed to remove license from environment file.');
            }
        }

        try {
            Cache::forget('license_recheck_ts');
        } catch (\Throwable $e) {
        }

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'License successfully deregistered. The application is now locked for this domain.',
            data: [
                'redirect_url' => url('/')
            ]
        );
    }
}
