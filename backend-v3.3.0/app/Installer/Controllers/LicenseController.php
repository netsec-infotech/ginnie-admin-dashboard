<?php

namespace App\Installer\Controllers;

use App\Installer\Helpers\Reply;
use App\Services\LicenseActivationStore;
use App\Services\LicenseHostPolicy;
use App\Services\LicenseValidator;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LicenseController extends Controller
{
    public function __construct(
        private readonly LicenseValidator $validator,
        private readonly LicenseHostPolicy $hostPolicy,
        private readonly LicenseActivationStore $activationStore
    ) {}

    /**
     * Show the license form step.
     */
    public function form()
    {
        return view('vendor.installer.license');
    }

    /**
     * Verify the license against remote API and persist to .env
     */
    public function verify(Request $request)
    {
        $validated = $request->validate([
            'purchase_code' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9-]+$/'],
        ]);
        $purchaseCode = $validated['purchase_code'];
        $domainUrl = $this->hostPolicy->domainForRequest($request);

        if ($this->hostPolicy->isLocalDevelopment($domainUrl)) {
            try {
                $this->activationStore->persist(
                    $purchaseCode,
                    $domainUrl,
                    'local-development',
                    'local'
                );
            } catch (\Throwable $e) {
                return Reply::error('License Error: Failed to persist the local development license.');
            }

            return Reply::redirect(
                route('LaravelInstaller::environment'),
                'Local development license accepted without remote validation.'
            );
        }

        $result = $this->validator->validate($purchaseCode, $domainUrl);

        if (! ($result['success'] ?? false)) {
            if ($result['unavailable'] ?? false) {
                if (! config('license.allow_offline_activation', true)) {
                    return Reply::error('License Error: This public server cannot reach the license service.');
                }

                try {
                    $this->activationStore->persistOffline($purchaseCode, $domainUrl);
                } catch (\Throwable $e) {
                    return Reply::error('License Error: Failed to save the offline license activation.');
                }

                return Reply::redirect(
                    route('LaravelInstaller::environment'),
                    'License service is unavailable. Offline activation saved; validation will be retried automatically.'
                );
            }

            $msg = $result['message'] ?? 'License verification failed.';

            return Reply::error('License Error: '.$msg);
        }

        $token = $result['data']['token'] ?? '';

        try {
            $this->activationStore->persist($purchaseCode, $domainUrl, $token, 'remote', now());
        } catch (\Throwable $e) {
            return Reply::error('License Error: Failed to write license to environment file.');
        }

        return Reply::redirect(route('LaravelInstaller::environment'), 'License verified successfully.');
    }
}
