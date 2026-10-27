<?php

namespace App\Providers;

use App\Http\Controllers\Admin\BulkUpload\BulkUploadController;
use App\Models\DeliveryBoyAssignment;
use App\Models\Order;
use App\Models\OrderItemReturn;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\SellerStatement;
use App\Models\SellerSubscription;
use App\Models\Store;
use App\Models\SubscriptionTransaction;
use App\Models\WalletTransaction;
use App\Models\WatchBuyProfile;
use App\Models\WatchBuyReel;
use App\Observers\DeliveryBoyAssignmentObserver;
use App\Observers\OrderItemReturnObserver;
use App\Observers\OrderObserver;
use App\Observers\SellerObserver;
use App\Observers\SellerStatementObserver;
use App\Observers\SellerSubscriptionObserver;
use App\Observers\StoreObserver;
use App\Observers\SubscriptionTransactionObserver;
use App\Observers\WalletTransactionObserver;
use App\Services\CurrencyService;
use App\Services\LanguageService;
use App\Services\SettingService;
use App\Support\DatabaseSchema;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Spatie\Translatable\Facades\Translatable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerLibphonenumberAutoloader();

        $this->app->singleton(CurrencyService::class, function ($app) {
            return new CurrencyService($app->make(SettingService::class));
        });

        $this->app->scoped(LanguageService::class);
        $this->app->scoped(DatabaseSchema::class);
    }

    /**
     * Expose the in-repo `libphonenumber\` package (see app/Libs/libphonenumber)
     * without relying on a composer dump-autoload step on customer deploys.
     * Registered here because AppServiceProvider boots before any controller
     * that parses Firebase phone callbacks.
     */
    private function registerLibphonenumberAutoloader(): void
    {
        $base = app_path('Libs/libphonenumber');

        spl_autoload_register(function (string $class) use ($base): void {
            if (! str_starts_with($class, 'libphonenumber\\')) {
                return;
            }

            $relative = substr($class, strlen('libphonenumber\\'));
            $path = $base.DIRECTORY_SEPARATOR.str_replace('\\', DIRECTORY_SEPARATOR, $relative).'.php';

            if (is_file($path)) {
                require_once $path;
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        if (class_exists(Translatable::class)) {
           Translatable::fallback('en', false);
        }

        $this->registerShareCartRateLimiters();

        app(LanguageService::class)->registerEnglishGroupAliases();

        Relation::morphMap([
            'profile' => WatchBuyProfile::class,
            'reel' => WatchBuyReel::class,
            'product_variant' => ProductVariant::class,
            'store' => Store::class,
        ]);

        // Load settings with safe guards
        [$systemSettings, $appSettings] = $this->loadSettings();

        // Override the static config timezone with the admin-configured one (default UTC)
        $this->applySystemTimezone($systemSettings);

        // [CHANGED BY AGENT]: Dynamically configure cloud storage driver and Spatie disk name from admin storage settings
        $this->configureRuntimeCloudStorage();

        // Panel and menus
        $panel = $this->detectPanel();
        $menuSeller = config('menu.seller', []);
        $menuAdmin = config('menu.admin', []);

        // Share data to all views
        $this->shareViewData([
            'systemSettings' => $systemSettings,
            'appSettings' => $appSettings,
            'menuSeller' => $menuSeller,
            'menuAdmin' => $menuAdmin,
            'panel' => $panel,
        ]);

        // API docs security
        $this->configureScramble();

        // Force HTTPS in production
        $this->forceHttpsInProduction();

        // Register model observers safely
        $this->registerObservers();

        $this->registerBulkUploadRouteMacro();

        // Dynamically apply broadcast driver settings and register broadcast channels
        $this->applyBroadcastSettings();
        if (file_exists(base_path('routes/channels.php'))) {
            require base_path('routes/channels.php');
        }
    }

    private function registerShareCartRateLimiters(): void
    {
        RateLimiter::for('share-cart-create', fn (Request $request): Limit => Limit::perMinute(10)
            ->by('share-cart-create:'.($request->user()?->id ?? $request->ip())));

        RateLimiter::for('share-cart-import', fn (Request $request): Limit => Limit::perMinute(10)
            ->by('share-cart-import:'.($request->user()?->id ?? $request->ip())));

        RateLimiter::for('share-cart-preview', fn (Request $request): Limit => Limit::perMinute(60)
            ->by('share-cart-preview:'.$request->ip()));
    }

    /**
     * Macro that wires up the standard bulk-upload route set for a given module.
     * Drop one call (Route::bulkUpload('brands')) inside the admin prefix group
     * and the module gets page / preview / confirm / template / jobs.* routes
     * with consistent names — admin.{module}.bulk-upload.{sub}.
     */
    private function registerBulkUploadRouteMacro(): void
    {
        Route::macro('bulkUpload', function (string $module): void {
            Route::prefix($module.'/bulk-upload')
                ->name($module.'.bulk-upload.')
                ->group(function () use ($module) {
                    Route::get('/', [BulkUploadController::class, 'page'])
                        ->defaults('module', $module)->name('page');
                    Route::post('/preview', [BulkUploadController::class, 'preview'])
                        ->defaults('module', $module)->name('preview');
                    Route::post('/confirm', [BulkUploadController::class, 'confirm'])
                        ->defaults('module', $module)->name('confirm');
                    Route::get('/template', [BulkUploadController::class, 'template'])
                        ->defaults('module', $module)->name('template');
                    Route::get('/export', [BulkUploadController::class, 'export'])
                        ->defaults('module', $module)->name('export');
                    Route::get('/export/count', [BulkUploadController::class, 'exportCount'])
                        ->defaults('module', $module)->name('export.count');

                    Route::get('/jobs', [BulkUploadController::class, 'jobsIndex'])
                        ->defaults('module', $module)->name('jobs.index');
                    Route::get('/jobs/datatable', [BulkUploadController::class, 'jobsDatatable'])
                        ->defaults('module', $module)->name('jobs.datatable');
                    Route::get('/jobs/{id}', [BulkUploadController::class, 'jobShow'])
                        ->whereNumber('id')
                        ->defaults('module', $module)->name('jobs.show');
                    Route::get('/jobs/{id}/status', [BulkUploadController::class, 'jobStatus'])
                        ->whereNumber('id')
                        ->defaults('module', $module)->name('jobs.status');
                    Route::post('/jobs/{id}/cancel', [BulkUploadController::class, 'cancel'])
                        ->whereNumber('id')
                        ->defaults('module', $module)->name('jobs.cancel');
                    Route::get('/jobs/{id}/errors', [BulkUploadController::class, 'downloadErrors'])
                        ->whereNumber('id')
                        ->defaults('module', $module)->name('jobs.errors');
                });
        });
    }

    /**
     * Load system and app settings from DB (if available), with safe guards.
     *
     * @return array{0: array, 1: array}
     */
    private function loadSettings(): array
    {
        $systemSettings = [];
        $appSettings = [];
        try {
            if (Schema::hasTable('settings')) {
                $settingService = app(SettingService::class);
                $resource = $settingService->getSettingByVariable('system');
                $systemSettings = $resource ? ($resource->toArray(request())['value'] ?? []) : [];

                try {
                    $appResource = $settingService->getSettingByVariable('app');
                    $appSettings = $appResource ? ($appResource->toArray(request())['value'] ?? []) : [];
                } catch (\Throwable $e) {
                    Log::warning($e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            Log::warning($e->getMessage());
        }

        return [$systemSettings, $appSettings];
    }

    /**
     * [CHANGED BY AGENT]: Dynamically wire admin storage settings into Laravel filesystem & Spatie media library configuration.
     * Safely catches any DB or schema issues on initial installation or migration.
     */
    private function configureRuntimeCloudStorage(): void
    {
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }
            $settingService = app(SettingService::class);
            $resource = $settingService->getSettingByVariable('storage');
            if (! $resource) {
                return;
            }

            $storageSettings = $resource->toArray(request())['value'] ?? [];
            $disk = $storageSettings['storageDisk'] ?? 'local';

            if ($disk === 'local') {
                Config::set('media-library.disk_name', 'public');
            } else {
                Config::set('media-library.disk_name', $disk);
            }

            // Always inject credentials for all cloud disks if they exist in settings.
            // This ensures that if a user switches active providers, media files stored on the old provider remain accessible.
            Config::set('filesystems.disks.s3', array_merge(config('filesystems.disks.s3', []), [
                'key' => $storageSettings['awsAccessKeyId'] ?? '',
                'secret' => $storageSettings['awsSecretAccessKey'] ?? '',
                'region' => $storageSettings['awsRegion'] ?? '',
                'bucket' => $storageSettings['awsBucket'] ?? '',
                'url' => $storageSettings['awsAssetUrl'] ?? '',
            ]));

            Config::set('filesystems.disks.r2', array_merge(config('filesystems.disks.r2', []), [
                'key' => $storageSettings['r2AccessKeyId'] ?? ($storageSettings['awsAccessKeyId'] ?? ''),
                'secret' => $storageSettings['r2SecretAccessKey'] ?? ($storageSettings['awsSecretAccessKey'] ?? ''),
                'region' => 'auto',
                'bucket' => $storageSettings['r2Bucket'] ?? ($storageSettings['awsBucket'] ?? ''),
                'url' => $storageSettings['r2AssetUrl'] ?? ($storageSettings['awsAssetUrl'] ?? ''),
                'endpoint' => $storageSettings['r2Endpoint'] ?? ($storageSettings['awsEndpoint'] ?? ''),
                'use_path_style_endpoint' => true,
            ]));

            // Microsoft Azure
            Config::set('filesystems.disks.azure', array_merge(config('filesystems.disks.azure', []), [
                'name' => $storageSettings['azureAccountName'] ?? '',
                'key' => $storageSettings['azureAccountKey'] ?? '',
                'container' => $storageSettings['azureContainer'] ?? '',
                'url' => $storageSettings['azureEndpoint'] ?? '',
            ]));
        } catch (\Throwable $e) {
            Log::warning('Failed to configure runtime cloud storage: '.$e->getMessage());
        }
    }

    /**
     * Apply the admin-configured timezone to PHP and Laravel config.
     * Falls back to UTC when missing or invalid so Carbon/now() never break.
     */
    private function applySystemTimezone(array $systemSettings): void
    {
        try {
            $timezone = $systemSettings['systemTimezone'] ?? null;

            if (! is_string($timezone) || $timezone === '' || ! in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
                $timezone = 'UTC';
            }

            Config::set('app.timezone', $timezone);
            date_default_timezone_set($timezone);
        } catch (\Throwable $e) {
            Log::warning('Failed to apply system timezone: '.$e->getMessage());
            Config::set('app.timezone', 'UTC');
            date_default_timezone_set('UTC');
        }
    }

    /**
     * Detect current panel (admin or seller).
     */
    private function detectPanel(): string
    {
        return (request()->is('seller/*') || request()->routeIs('seller.*')) ? 'seller' : 'admin';
    }

    /**
     * Share base data and user to views.
     */
    private function shareViewData(array $data): void
    {
        // Include current authenticated user if available
        if (Auth::check()) {
            $data['user'] = Auth::user();
        }

        view()->share($data);

        // Ensure user is always available via composer as well
        View::composer('*', function ($view) {
            if (Auth::check()) {
                $view->with('user', Auth::user());
            }
        });
    }

    /**
     * Configure Scramble (OpenAPI) security scheme.
     */
    private function configureScramble(): void
    {
        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(SecurityScheme::http('bearer'));
            });
    }

    /**
     * Force HTTPS scheme in production environment.
     */
    private function forceHttpsInProduction(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }

    /**
     * Register all model observers with safe guards.
     */
    private function registerObservers(): void
    {
        $pairs = [
            WalletTransaction::class => WalletTransactionObserver::class,
            SellerStatement::class => SellerStatementObserver::class,
            DeliveryBoyAssignment::class => DeliveryBoyAssignmentObserver::class,
            OrderItemReturn::class => OrderItemReturnObserver::class,
            Store::class => StoreObserver::class,
            Seller::class => SellerObserver::class,
            Order::class => OrderObserver::class,
            SubscriptionTransaction::class => SubscriptionTransactionObserver::class,
            SellerSubscription::class => SellerSubscriptionObserver::class,
        ];

        foreach ($pairs as $model => $observer) {
            try {
                /** @var class-string $model */
                $model::observe($observer);
            } catch (\Throwable $e) {
                Log::warning('Failed to register '.class_basename($observer).': '.$e->getMessage());
            }
        }
    }

    // Apply broadcast settings dynamically from database configuration
    private function applyBroadcastSettings(): void
    {
        try {
            if (Schema::hasTable('settings')) {
                app(\App\Services\BroadcastSettingsService::class)->apply();
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to apply broadcast settings: '.$e->getMessage());
        }
    }
}
