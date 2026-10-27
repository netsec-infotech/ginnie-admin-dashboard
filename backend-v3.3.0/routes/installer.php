<?php

// Ensure helper functions are always loaded regardless of the working directory
// functions.php is autoloaded via composer.json

use App\Installer\Controllers\DatabaseController;
use App\Installer\Controllers\EnvironmentController;
use App\Installer\Controllers\FinalController;
use App\Installer\Controllers\LicenseController;
use App\Installer\Controllers\PermissionsController;
use App\Installer\Controllers\RequirementsController;
use App\Installer\Controllers\SuperAdminController;
use App\Installer\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'install', 'as' => 'LaravelInstaller::', 'middleware' => ['web', 'install']], function () {
    Route::get('/', [WelcomeController::class, 'welcome'])->name('welcome');

    // New License step (separate page before environment)
    Route::get('license', [LicenseController::class, 'form'])->name('license');
    Route::post('license/verify', [LicenseController::class, 'verify'])->name('license.verify');

    Route::get('environment', [EnvironmentController::class, 'environment'])->name('environment');

    Route::post('environment/save', [EnvironmentController::class, 'save'])->name('environmentSave');

    // Super Admin creation step (after environment)
    Route::get('super-admin', [SuperAdminController::class, 'form'])->name('superAdmin');
    Route::post('super-admin/save', [SuperAdminController::class, 'store'])->name('superAdmin.store');

    Route::get('requirements', [RequirementsController::class, 'requirements'])->name('requirements');

    Route::get('permissions', [PermissionsController::class, 'permissions'])->name('permissions');

    Route::get('database', [DatabaseController::class, 'show'])->name('database');
    Route::get('database/progress', [DatabaseController::class, 'progress'])->name('database.progress');
    Route::post('database/install', [DatabaseController::class, 'database'])->name('database.install');

    Route::get('final', [FinalController::class, 'finish'])->name('final');

});
