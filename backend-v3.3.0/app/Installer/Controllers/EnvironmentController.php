<?php

namespace App\Installer\Controllers;

use App\Installer\Helpers\EnvironmentManager;
use App\Installer\Request\UpdateRequest;
use App\Services\LicenseActivationStore;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Class EnvironmentController
 */
class EnvironmentController extends Controller
{
    /**
     * @var EnvironmentManager
     */
    protected $environmentManager;

    public function __construct(
        EnvironmentManager $environmentManager,
        private readonly LicenseActivationStore $activationStore
    ) {
        $this->environmentManager = $environmentManager;
    }

    /**
     * Display the Environment page.
     *
     * @return View
     */
    public function environment()
    {
        $activation = $this->activationStore->current();

        if (empty($activation['purchase_code']) || empty($activation['signature'])) {
            return redirect()->route('LaravelInstaller::license');
        }

        $envConfig = $this->environmentManager->getEnvContent();

        return view('vendor.installer.environment', compact('envConfig'));
    }

    /**
     * @return string
     */
    public function save(UpdateRequest $request)
    {
        $message = $this->environmentManager->saveFile($request);

        return $message;

    }
}
