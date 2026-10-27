<?php

namespace App\Installer\Providers;

use App\Installer\Middleware\canInstall;
use App\Support\EnvWriter;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class LaravelInstallerServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
    }

    /**
     * Bootstrap the application events.
     *
     * @param \Illuminate\Routing\Router $router
     * @return void
     */
    public function boot(Router $router)
    {
        $this->ensureInstallerSessionDriver();
        $this->ensureAppKey();

        $router->middlewareGroup('install', [canInstall::class]);

        $this->loadRoutesFrom(base_path('routes/installer.php'));
    }

    private function ensureInstallerSessionDriver(): void
    {
        if (file_exists(storage_path('installed'))) {
            return;
        }

        config(['session.driver' => 'cookie']);

        try {
            $envPath = $this->app->environmentFilePath();
            $envContent = is_file($envPath) ? file_get_contents($envPath) : '';

            if ($envContent === false) {
                throw new \RuntimeException('Environment file could not be read.');
            }

            if (preg_match('/^\s*SESSION_DRIVER\s*=\s*["\']?cookie["\']?\s*$/mi', $envContent) === 1) {
                return;
            }

            $envContent = EnvWriter::stripKeys($envContent, ['SESSION_DRIVER']);
            EnvWriter::write($envPath, rtrim($envContent, "\n")."\nSESSION_DRIVER=cookie\n");
        } catch (\Throwable $e) {
            logger()->error('Failed to set the installer session driver: '.$e->getMessage());
        }
    }

    private function ensureAppKey(): void
    {
        if (trim((string) config('app.key')) !== '') {
            return;
        }

        $envPath = $this->app->environmentFilePath();

        if ((file_exists($envPath) && ! is_writable($envPath))
            || (! file_exists($envPath) && ! is_writable(dirname($envPath)))) {
            return;
        }

        try {
            $contents = file_exists($envPath) ? (string) file_get_contents($envPath) : '';
            $key = 'base64:'.base64_encode(random_bytes(32));
            $contents = EnvWriter::stripKeys($contents, ['APP_KEY']);
            EnvWriter::write($envPath, rtrim($contents, "\n")."\nAPP_KEY={$key}\n");
            config(['app.key' => $key]);
        } catch (\Throwable $e) {
            logger()->error('Failed to generate APP_KEY: '.$e->getMessage());
        }
    }
}
