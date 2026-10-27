<?php

use App\Http\Controllers\Api\DeliveryBoy\DeliveryBoyAuthApiController;
use App\Http\Controllers\Api\DeliveryBoy\DeliveryBoyCashCollectionApiController;
use App\Http\Controllers\Api\DeliveryBoy\DeliveryBoyEarningApiController;
use App\Http\Controllers\Api\DeliveryBoy\DeliveryBoyHomeController;
use App\Http\Controllers\Api\DeliveryBoy\DeliveryBoyNotificationApiController;
use App\Http\Controllers\Api\DeliveryBoy\DeliveryBoyOrderApiController;
use App\Http\Controllers\Api\DeliveryBoy\DeliveryBoyReferralApiController;
use App\Http\Controllers\Api\DeliveryBoy\DeliveryBoyReturnPickupApiController;
use App\Http\Controllers\Api\DeliveryBoy\DeliveryBoyWalletApiController;
use App\Http\Controllers\Api\DeliveryBoy\DeliveryBoyWithdrawalApiController;
use App\Http\Controllers\Api\DeliveryFeedbackApiController;
use App\Http\Middleware\VerifiedDeliveryBoy;
use Illuminate\Support\Facades\Route;

// Delivery Boy Auth Routes
Route::prefix('delivery-boy')->name('delivery-boy.')->group(function () {
    Route::post('register', [DeliveryBoyAuthApiController::class, 'register'])->name('register');
    Route::post('login', [DeliveryBoyAuthApiController::class, 'login'])->name('login');
    // [CHANGE] Added Custom SMS OTP routes for Delivery Boy with standard rate limiting
    Route::post('auth/send-otp', [DeliveryBoyAuthApiController::class, 'sendOtp'])->middleware('throttle:6,1')->name('send_otp');
    Route::post('auth/verify-otp', [DeliveryBoyAuthApiController::class, 'verifyOtp'])->middleware('throttle:6,1')->name('verify_otp');
    Route::post('auth/phone/callback', [DeliveryBoyAuthApiController::class, 'phoneCallback'])->name('phone_callback');
    Route::post('forget-password', [DeliveryBoyAuthApiController::class, 'forgotPassword'])->name('forgot_password');
});

Route::middleware('auth:sanctum')->prefix('delivery-boy')->group(function () {
    Route::delete('delete-account', [DeliveryBoyAuthApiController::class, 'deleteAccount'])->name('delete_account');

    // delivery boy routes (requires a verified delivery boy)
    Route::middleware('verified.delivery.boy')->group(function () {
        // Add delivery boy-specific routes here
        Route::get('profile', [DeliveryBoyAuthApiController::class, 'getProfile'])->name('profile');
        Route::post('profile', [DeliveryBoyAuthApiController::class, 'updateProfile'])->name('update-profile');
        Route::post('status/update', [DeliveryBoyAuthApiController::class, 'updateStatus'])->name('update-status');
        Route::post('update-current-location', [DeliveryBoyAuthApiController::class, 'updateCurrentLocation'])->name('update-location');
        Route::get('get-last-location', [DeliveryBoyAuthApiController::class, 'getLastLocation'])->name('get-last-location');
        Route::get('home', [DeliveryBoyHomeController::class, 'index'])->name('home');

        // Notifications (delivery boy)
        Route::prefix('notifications')->group(function () {
            Route::get('/', [DeliveryBoyNotificationApiController::class, 'index']);
            Route::get('/unread-count', [DeliveryBoyNotificationApiController::class, 'unreadCount']);
            Route::post('/mark-all-read', [DeliveryBoyNotificationApiController::class, 'markAllAsRead']);
            Route::post('/{id}/read', [DeliveryBoyNotificationApiController::class, 'markAsRead']);
            Route::post('/{id}/unread', [DeliveryBoyNotificationApiController::class, 'markAsUnread']);
        });

        // Order routes (requires an active delivery boy)
        Route::middleware('active.delivery.boy')->group(function () {
            Route::get('/orders/available', [DeliveryBoyOrderApiController::class, 'getAvailableOrders'])->name('orders.available');
            Route::post('/orders/{orderId}/accept', [DeliveryBoyOrderApiController::class, 'acceptOrder'])->name('orders.accept');
            Route::post('/orders/{orderId}/drop', [DeliveryBoyOrderApiController::class, 'dropOrder'])->name('orders.drop');
            Route::put('/order-items/{orderItemId}/status', [DeliveryBoyOrderApiController::class, 'updateOrderItemStatus'])->name('order-items.update-status');
            Route::post('/order-items/{orderItemId}/delivery-failed', [DeliveryBoyOrderApiController::class, 'markDeliveryFailed'])->name('order-items.delivery-failed');
            Route::put('/orders/{orderId}/collect-all', [DeliveryBoyOrderApiController::class, 'collectAll'])->name('orders.collect-all');
            Route::put('/orders/{orderId}/deliver-all', [DeliveryBoyOrderApiController::class, 'deliverAll'])->name('orders.deliver-all');

            // COD Doorstep Online Payment routes
            // Route::get('/orders/{orderId}/payment-link', [DeliveryBoyOrderApiController::class, 'getOnlinePaymentLink'])->name('orders.payment-link');
            Route::get('/orders/{orderId}/payment-status', [DeliveryBoyOrderApiController::class, 'getPaymentStatus'])->name('orders.payment-status');
            Route::post('/orders/{orderId}/payment-link/cancel', [DeliveryBoyOrderApiController::class, 'cancelOnlinePayment'])->name('orders.payment-link.cancel');

            // Return pickup routes (similar structure to orders)
            Route::get('/return-pickups/available', [DeliveryBoyReturnPickupApiController::class, 'getAvailablePickups'])->name('return-pickups.available');
            Route::post('/return-pickups/{returnId}/accept', [DeliveryBoyReturnPickupApiController::class, 'acceptPickup'])->name('return-pickups.accept');
            Route::match(['put', 'post', 'patch'], '/return-pickups/{returnId}/status', [DeliveryBoyReturnPickupApiController::class, 'updatePickupStatus'])->name('return-pickups.update-status');
        });

        // My lists and details
        Route::get('/orders/my', [DeliveryBoyOrderApiController::class, 'getMyOrders'])->name('orders.my');
        Route::get('/orders/{orderId}', [DeliveryBoyOrderApiController::class, 'getOrderDetails'])->name('orders.details');
        Route::get('/return-pickups/my', [DeliveryBoyReturnPickupApiController::class, 'getMyPickups'])->name('return-pickups.my');
        Route::get('/return-pickups/{returnId}', [DeliveryBoyReturnPickupApiController::class, 'getPickupDetails'])->name('return-pickups.details');

        Route::middleware([VerifiedDeliveryBoy::class])->group(function () {
            // Wallet routes (scoped to the rider's DELIVERY_BOY wallet)
            Route::prefix('wallet')->name('wallet.')->group(function () {
                Route::get('/', [DeliveryBoyWalletApiController::class, 'show'])->name('show');
                Route::get('/transactions', [DeliveryBoyWalletApiController::class, 'transactions'])->name('transactions.index');
                Route::get('/transactions/{id}', [DeliveryBoyWalletApiController::class, 'transaction'])->name('transactions.show');
            });

            // Withdrawal routes
            Route::post('/withdrawals', [DeliveryBoyWithdrawalApiController::class, 'createWithdrawalRequest'])->name('withdrawals.create');
            Route::get('/withdrawals', [DeliveryBoyWithdrawalApiController::class, 'getWithdrawalRequests'])->name('withdrawals.index');
            Route::get('/withdrawals/{id}', [DeliveryBoyWithdrawalApiController::class, 'getWithdrawalRequest'])->name('withdrawals.show');

            // Earnings routes
            Route::get('/earnings', [DeliveryBoyEarningApiController::class, 'getEarnings'])->name('earnings.index');
            Route::get('/earnings/statistics', [DeliveryBoyEarningApiController::class, 'getStatistics'])->name('earnings.statistics');
            Route::get('/earnings/date-range', [DeliveryBoyEarningApiController::class, 'getEarningsByDateRange'])->name('earnings.date-range');

            // Referral routes
            Route::get('/referral', [DeliveryBoyReferralApiController::class, 'info'])->name('referral.info');
            Route::get('/referral/earnings', [DeliveryBoyReferralApiController::class, 'earnings'])->name('referral.earnings');

            // Cash Collection routes
            Route::get('/cash-collections', [DeliveryBoyCashCollectionApiController::class, 'getCashCollections'])->name('cash-collections.index');
            Route::get('/cash-collections/statistics', [DeliveryBoyCashCollectionApiController::class, 'getStatistics'])->name('cash-collections.statistics');
            Route::get('/cash-collections/date-range', [DeliveryBoyCashCollectionApiController::class, 'getEarningsByDateRange'])->name('cash-collections.date-range');
            Route::post('/cash-collections/settle-online', [DeliveryBoyCashCollectionApiController::class, 'settleOnline'])->name('cash-collections.settle-online');
        });

        Route::prefix('feedback')->group(function () {
            Route::get('/', [DeliveryFeedbackApiController::class, 'index']);
            Route::get('/ratings', [DeliveryFeedbackApiController::class, 'getDeliveryRatings']);
            Route::get('/{id}', [DeliveryFeedbackApiController::class, 'show']);
        });
    });
    Route::post('feedback/', [DeliveryFeedbackApiController::class, 'store']);
    Route::post('feedback/{id}', [DeliveryFeedbackApiController::class, 'update']);
    Route::delete('feedback/{id}', [DeliveryFeedbackApiController::class, 'destroy']);
});
