<?php

namespace App\Installer\Middleware;

use App\Installer\Helpers\InstallationState;
use Closure;
use Illuminate\Http\Request;

/**
 * Class canInstall
 */
class canInstall
{
    public function __construct(private readonly InstallationState $installationState) {}

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @return mixed
     */
    public function handle($request, Closure $next)
    {

        if ($this->alreadyInstalled()) {
            abort(404);
        }

        $this->changePhpConfigs();

        return $next($request);
    }

    /**
     * If application is already installed.
     *
     * @return bool
     */
    public function alreadyInstalled()
    {
        return $this->installationState->isInstalled();
    }

    private function changePhpConfigs()
    {
        try {
            ini_set('max_execution_time', 600);
            ini_set('memory_limit', '512M');
        } catch (\Exception $e) {
            logger()->error('Error changing PHP configurations: '.$e->getMessage());
        }
    }
}
