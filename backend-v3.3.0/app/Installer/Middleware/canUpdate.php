<?php

namespace App\Installer\Middleware;

use App\Installer\Helpers\InstallationState;
use App\Installer\Helpers\MigrationsHelper;
use Closure;
use Illuminate\Http\Request;

class canUpdate
{
    use MigrationsHelper;

    public function __construct(private readonly InstallationState $installationState) {}

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (! $this->installationState->isInstalled()) {
            return redirect()->route('LaravelInstaller::welcome');
        }

        if ($this->alreadyUpdated()) {
            abort(404);
        }

        return $next($request);
    }

    /**
     * If application is already updated.
     *
     * @return bool
     */
    public function alreadyUpdated()
    {
        $migrations = $this->getMigrations();
        $dbMigrations = $this->getExecutedMigrations();

        if (count($migrations) == count($dbMigrations)) {
            return true;
        }

        return false;
    }
}
