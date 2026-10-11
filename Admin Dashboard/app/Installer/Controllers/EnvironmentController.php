<?php

namespace App\Installer\Controllers;

use Illuminate\Routing\Controller;
use App\Installer\Helpers\EnvironmentManager;
use App\Installer\Request\UpdateRequest;

/**
 * Class EnvironmentController
 * @package App\Installer\Controllers
 */
class EnvironmentController extends Controller
{

    /**
     * @var EnvironmentManager
     */
    protected $environmentManager;

    /**
     * @param EnvironmentManager $environmentManager
     */
    public function __construct(EnvironmentManager $environmentManager)
    {
        $this->environmentManager = $environmentManager;
    }

    /**
     * Display the Environment page.
     *
     * @return \Illuminate\View\View
     */
    public function environment()
    {
        // If license is not yet set, force user to the dedicated License step
        if (!env('LICENSE_PURCHASE_CODE') || !env('LICENSE_SIGNATURE')) {
            return redirect()->route('LaravelInstaller::license');
        }

        $envConfig = $this->environmentManager->getEnvContent();

        return view('vendor.installer.environment', compact('envConfig'));
    }

    /**
     * @param UpdateRequest $request
     * @return string
     */
    public function save(UpdateRequest $request)
    {
        $message = $this->environmentManager->saveFile($request);
        return $message;

    }

}
