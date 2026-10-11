<?php

use App\Enums\PoliciesEnum;
use App\Enums\SettingTypeEnum;
use App\Http\Controllers\CodPaymentController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\LicenseRevalidateController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\Payments\DeliveryBoyCashSettlementController;
use App\Http\Controllers\Payments\FlutterwaveController;
use App\Http\Controllers\Payments\PaystackController;
use App\Http\Controllers\Payments\RazorpayController;
use App\Http\Controllers\Payments\StripeController;
use App\Http\Controllers\Payments\XenditController;
use App\Http\Controllers\PolicyController;
use App\Http\Controllers\Seller\AdWalletController;
use App\Http\Controllers\Seller\PosController;
use App\Http\Controllers\Seller\PosPaymentSessionController;
use App\Http\Controllers\Seller\SubscriptionPlanController;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

require __DIR__ . '/admin-route.php';
require __DIR__ . '/seller-route.php';

// Route to run reward settlement command
Route::get('/reward-settlement', function () {
    try {
        $output = Artisan::call('referral:settle');

        return response()->json([
            'message' => 'Referral settlement process completed.',
            'output' => Artisan::output(),
        ]);
    } catch (Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
});

Route::view('/landing', 'new-welcome');

// Route to clear permission and application cache on the server
Route::get('/clear-cache', function () {
    try {
        Artisan::call('permission:cache-reset');
        $permissionOutput = Artisan::output();

        Artisan::call('cache:clear');
        $cacheOutput = Artisan::output();

        return response()->json([
            'success' => true,
            'message' => 'Cache and permissions cleared successfully.',
            'permission_output' => trim($permissionOutput),
            'cache_output' => trim($cacheOutput),
        ]);
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
        ], 500);
    }
});

Route::prefix('subscription-payment')->name('subscription-payment.')->group(function () {
    Route::get('razorpay/{transaction}', [RazorpayController::class, 'showSubscriptionPaymentPage'])
        ->name('razorpay');
    Route::get('stripe/{transaction}', [StripeController::class, 'showSubscriptionPaymentPage'])
        ->name('stripe');
    Route::get('paystack/{transaction}', [PaystackController::class, 'showSubscriptionPaymentPage'])
        ->name('paystack');
    Route::get('flutterwave/{transaction}', [FlutterwaveController::class, 'showSubscriptionPaymentPage'])
        ->name('flutterwave');
    Route::get('xendit/{transaction}', [XenditController::class, 'showSubscriptionPaymentPage'])
        ->name('xendit');

    Route::get('status/{transaction}', [SubscriptionPlanController::class, 'paymentStatus'])->name('status');
});

Route::prefix('wallet-payment')->name('wallet-payment.')->group(function () {
    Route::get('razorpay/{transaction}', [RazorpayController::class, 'showWalletPaymentPage'])
        ->name('razorpay');
    Route::get('stripe/{transaction}', [StripeController::class, 'showWalletPaymentPage'])
        ->name('stripe');
    Route::get('paystack/{transaction}', [PaystackController::class, 'showWalletPaymentPage'])
        ->name('paystack');
    Route::get('flutterwave/{transaction}', [FlutterwaveController::class, 'showWalletPaymentPage'])
        ->name('flutterwave');
    Route::get('xendit/{transaction}', [XenditController::class, 'showWalletPaymentPage'])
        ->name('xendit');
    Route::get('status/{transaction}', [AdWalletController::class, 'paymentStatus'])
        ->name('status');
});

Route::prefix('order-payment')->name('order-payment.')->group(function () {
    Route::get('status/{order}', [XenditController::class, 'orderPaymentStatus'])
        ->name('status');
});

// Post-order tip payment WebView return status page route
Route::prefix('tip-payment')->name('tip-payment.')->group(function () {
    Route::get('status/{transaction}', [XenditController::class, 'tipPaymentStatus'])
        ->name('status');
});

Route::prefix('cash-collection-payment')->name('cash-collection-payment.')->group(function () {
    Route::get('pay/{uuid}', [DeliveryBoyCashSettlementController::class, 'showPaymentPage'])
        ->name('pay');
    Route::get('status/{uuid}', [DeliveryBoyCashSettlementController::class, 'status'])
        ->name('status');
});

Route::get('/', function () {
    // Check Demo Mode from system settings
    try {
        /** @var SettingService $settingService */
        $settingService = app(SettingService::class);
        $resource = $settingService->getSettingByVariable(SettingTypeEnum::SYSTEM());
        $settings = $resource ? ($resource->toArray(request())['value'] ?? []) : [];
        $isDemo = (bool) ($settings['demoMode'] ?? false);
    } catch (Throwable $e) {
        $isDemo = false;
    }

    if ($isDemo) {
        return view('new-welcome');
    }

    if (auth()->check()) {
        if (auth()->user()->access_panel->value == 'admin') {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->route('seller.dashboard');
    }

    return redirect()->route('admin.login');
});

Route::get('/migrate', function () {
    try {
        Artisan::call('migrate');
        return "<pre>" . Artisan::output() . "</pre>";
    } catch (\Exception $e) {
        return "Error: " . $e->getMessage();
    }
});

Route::get('/seed', function (Request $request) {
    try {
        $class = $request->query('class');
        if ($class) {
            Artisan::call('db:seed', ['--class' => $class]);
        } else {
            Artisan::call('db:seed');
        }

        return response()->json([
            'success' => true,
            'message' => 'Seeding completed successfully.',
            'output' => trim(Artisan::output()),
        ]);
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
        ], 500);
    }
});

Route::get('/storage-link', function () {
    try {
        Artisan::call('storage:link');

        return 'Storage link created successfully.';
    } catch (Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
});

Route::get('/optimize-clean', function () {
    try {
        Artisan::call('config:cache');
        Artisan::call('cache:clear');
        Artisan::call('optimize:clear');

        return 'Optimization cache cleared successfully.';
    } catch (Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
});

Route::get('/clear-queue', function () {
    try {
        Artisan::call('queue:flush');
        Artisan::call('queue:clear');

        return 'Queue cleared successfully.';
    } catch (Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
});

Route::get('/rollback', function () {
    try {
        Artisan::call('migrate:rollback', ['--step' => 0]);

        return 'Rollback of last migration completed successfully.';
    } catch (Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
});

Route::get('/subscription-usage-sync', function () {
    try {
        Artisan::call('subscription:sync-usage');

        return 'Subscription usage synced successfully.';
    } catch (Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
});
Route::get('/subscription-expire', function () {
    try {
        Artisan::call('subscription:expire');

        return 'Subscription usage synced successfully.';
    } catch (Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
});

Route::get('/seed-system-vendor-type', function () {
    try {
        Artisan::call('db:seed', ['--class' => 'SystemVendorTypeSeeder']);

        return 'SystemVendorTypeSeeder executed successfully.';
    } catch (Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
});

Route::get('/seed-default-plan', function () {
    try {
        Artisan::call('db:seed', ['--class' => 'DefaultSubscriptionPlanSeeder']);

        return 'DefaultSubscriptionPlanSeeder executed successfully.';
    } catch (Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
});
Route::get('/seed-wallet-type', function () {
    try {
        Artisan::call('db:seed', ['--class' => 'BackfillWalletTypesSeeder']);

        return 'BackfillWalletTypesSeeder executed successfully.';
    } catch (Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
});

// Email verification — target of the signed link sent by
// sendEmailVerificationNotification(). Public (no auth): the signature is
// the proof. Throttle mirrors Laravel's default verification throttle.
Route::get('/email/verify/{id}/{hash}', function (Request $request, $id, $hash) {
    $user = User::find($id);

    if (! $user || ! hash_equals((string) $hash, sha1((string) $user->getEmailForVerification()))) {
        return response()->view('auth.email-verified', [
            'status' => 'invalid',
        ], 403);
    }

    if ($user->hasVerifiedEmail()) {
        return response()->view('auth.email-verified', [
            'status' => 'already',
            'email' => $user->email,
        ]);
    }

    if ($user->markEmailAsVerified()) {
        event(new Verified($user));
    }

    return response()->view('auth.email-verified', [
        'status' => 'verified',
        'email' => $user->email,
    ]);
})
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

// Customer Password Reset Routes
Route::get('forgot-password', [PasswordResetController::class, 'showForgotPasswordForm'])->name('password.request');
Route::post('forgot-password', [PasswordResetController::class, 'sendResetLinkEmail'])->name('password.email');
Route::get('reset-password/{token}', [PasswordResetController::class, 'showResetPasswordForm'])->name('password.reset');
Route::post('reset-password', [PasswordResetController::class, 'resetPassword'])->name('password.update');

Route::get('countries', [CountryController::class, 'index']);
Route::get('currency', [CountryController::class, 'getCurrency']);

// Language Switcher
Route::get('language/{locale}', function ($locale) {
    if (in_array($locale, ['en', 'es', 'fr', 'de', 'zh'])) {
        session()->put('locale', $locale);
    }

    return redirect()->back();
})->name('set.language');

Route::get('order-invoice', [OrderController::class, 'orderInvoice'])->name('order-invoice');

// Public Policy Preview Route
Route::get('/policies/{policy}', [PolicyController::class, 'show'])
    ->whereIn('policy', PoliciesEnum::values())
    ->name('policies.show');

// License revalidation page (when app is not licensed)
Route::get('/license/revalidate', [LicenseRevalidateController::class, 'form'])->name('license.revalidate');
Route::post('/license/revalidate/verify', [LicenseRevalidateController::class, 'verify'])->name('license.revalidate.verify');

// ── POS Customer-Facing Display ──────────────────
Route::prefix('pos/display')->name('pos.public.cfd.')->group(function () {
    Route::get('{token}', [PosController::class, 'cfdShow'])->name('show');
    Route::get('{token}/state', [PosController::class, 'cfdPull'])->name('pull');
});

// ── POS Online Payment ──────────────────────────
Route::prefix('pay/pos')->name('pos.public.payment.')->group(function () {
    Route::get('{token}', [PosPaymentSessionController::class, 'showPaymentPage'])->name('show');
    Route::get('{token}/status', [PosPaymentSessionController::class, 'publicStatus'])->name('status');
    Route::post('{token}/init/{gateway}', [PosPaymentSessionController::class, 'initGateway'])->name('init');
    Route::post('{token}/verify', [PosPaymentSessionController::class, 'verify'])->name('verify');
    Route::get('{token}/return/{gateway}', [PosPaymentSessionController::class, 'returnFromGateway'])->name('return');
});

// ── COD Doorstep Online Payment ─────────────────────────
Route::prefix('pay/cod')->name('cod.public.payment.')->group(function () {
    Route::get('{token}', [CodPaymentController::class, 'showPaymentPage'])->name('show');
    Route::get('{token}/status', [CodPaymentController::class, 'publicStatus'])->name('status');
    Route::post('{token}/init/{gateway}', [CodPaymentController::class, 'initGateway'])->name('init');
    Route::post('{token}/verify', [CodPaymentController::class, 'verify'])->name('verify');
    Route::get('{token}/return/{gateway}', [CodPaymentController::class, 'returnFromGateway'])->name('return');
});

// Route for revalidate order for scheduled delivery (used by cron job)
Route::get('/revalidate-order', function () {
    try {
        Artisan::call('orders:revalidate-scheduled');

        return 'Revalidation of scheduled orders completed successfully.';
    } catch (Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
});
