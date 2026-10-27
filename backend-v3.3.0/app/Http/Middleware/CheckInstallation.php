<?php

namespace App\Http\Middleware;

use App\Installer\Helpers\InstallationState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckInstallation
{
    public function __construct(private readonly InstallationState $installationState) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $installationResource = $request->is('install*', 'docs', 'documentation*');

        if (! $this->installationState->isInstalled() && ! $installationResource) {
            return redirect()->route('LaravelInstaller::welcome');
        }

        return $next($request);
    }
}
