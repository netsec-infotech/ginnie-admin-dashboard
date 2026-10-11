<?php

use App\Http\Controllers\Admin\AiProductDescriptionController;
use App\Http\Controllers\Api\Seller\SellerAdCampaignApiController;
use App\Http\Controllers\Api\Seller\SellerAddonGroupApiController;
use App\Http\Controllers\Api\Seller\SellerAdWalletApiController;
use App\Http\Controllers\Api\Seller\SellerAttributeApiController;
use App\Http\Controllers\Api\Seller\SellerAttributeValueApiController;
use App\Http\Controllers\Api\Seller\SellerAuthApiController;
use App\Http\Controllers\Api\Seller\SellerBrandApiController;
use App\Http\Controllers\Api\Seller\SellerDashboardApiController;
use App\Http\Controllers\Api\Seller\SellerDeliveryTimeSlotApiController;
use App\Http\Controllers\Api\Seller\SellerEarningApiController;
use App\Http\Controllers\Api\Seller\SellerNotificationApiController;
use App\Http\Controllers\Api\Seller\SellerOrderApiController;
use App\Http\Controllers\Api\Seller\SellerPermissionApiController;
use App\Http\Controllers\Api\Seller\SellerPosCustomerApiController;
use App\Http\Controllers\Api\Seller\SellerPosOrderApiController;
use App\Http\Controllers\Api\Seller\SellerPosProductApiController;
use App\Http\Controllers\Api\Seller\SellerProductAddonApiController;
use App\Http\Controllers\Api\Seller\SellerProductApiController;
use App\Http\Controllers\Api\Seller\SellerProductFaqApiController;
use App\Http\Controllers\Api\Seller\SellerReturnReasonApiController;
use App\Http\Controllers\Api\Seller\SellerRoleApiController;
use App\Http\Controllers\Api\Seller\SellerStoreAddonItemApiController;
use App\Http\Controllers\Api\Seller\SellerStoreApiController;
use App\Http\Controllers\Api\Seller\SellerSystemUserApiController;
use App\Http\Controllers\Api\Seller\SellerTaxClassApiController;
use App\Http\Controllers\Api\Seller\SellerWalletApiController;
use App\Http\Controllers\Api\Seller\SellerWithdrawalApiController;
use App\Http\Controllers\Api\Seller\SubscriptionPlanApiController as SellerSubscriptionPlanApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('seller')->name('seller-api.')->group(function () {
    Route::post('register', [SellerAuthApiController::class, 'createSeller'])->name('register');
    Route::post('login', [SellerAuthApiController::class, 'login'])->name('login');
    // [CHANGE] Added Custom SMS OTP routes for Seller with standard rate limiting
    Route::post('auth/send-otp', [SellerAuthApiController::class, 'sendOtp'])->middleware('throttle:6,1')->name('send_otp');
    Route::post('auth/verify-otp', [SellerAuthApiController::class, 'verifyOtp'])->middleware('throttle:6,1')->name('verify_otp');
    Route::post('auth/phone/callback', [SellerAuthApiController::class, 'phoneCallback'])->name('phone_callback');
    Route::post('forget-password', [SellerAuthApiController::class, 'forgotPassword'])->name('forgot_password');
});

Route::middleware([
    'auth:sanctum',
    //    'ensure.seller.subscription'
])->prefix('seller')->name('seller.api.')->group(function () {
    Route::post('logout', [SellerAuthApiController::class, 'logout']);
    Route::delete('delete-account', [SellerAuthApiController::class, 'deleteAccount'])->name('delete_account');

    // Dashboard (single endpoint only)
    Route::get('dashboard', [SellerDashboardApiController::class, 'overview'])->name('dashboard.overview');

    // Notifications (seller)
    Route::prefix('notifications')->group(function () {
        Route::get('/', [SellerNotificationApiController::class, 'index']);
        Route::get('/unread-count', [SellerNotificationApiController::class, 'unreadCount']);
        Route::post('/mark-all-read', [SellerNotificationApiController::class, 'markAllAsRead']);
        Route::post('/{id}/read', [SellerNotificationApiController::class, 'markAsRead']);
        Route::post('/{id}/unread', [SellerNotificationApiController::class, 'markAsUnread']);
    });

    // Stores (seller-owned)
    Route::prefix('stores')->name('stores.')->group(function () {
        Route::get('/', [SellerStoreApiController::class, 'index'])->name('index');
        Route::get('/enums', [SellerStoreApiController::class, 'getStoresEnums'])->name('enums');
        Route::get('/{id}', [SellerStoreApiController::class, 'show'])->name('show');
        Route::post('/', [SellerStoreApiController::class, 'store'])->name('store');
        Route::post('/{id}', [SellerStoreApiController::class, 'update'])->name('update');
        Route::delete('/{id}', [SellerStoreApiController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/status', [SellerStoreApiController::class, 'updateStatus'])->name('update_status');
        Route::post('/{id}/timing-preview', [SellerStoreApiController::class, 'previewTimingImpact'])->name('timing_preview');
        Route::post('/{id}/timing', [SellerStoreApiController::class, 'updateTimings'])->name('update_timings');
        Route::post('/{id}/temporary-closure', [SellerStoreApiController::class, 'toggleTemporaryClosure'])->name('toggle_temporary_closure');

        // Delivery Time Slots for Store
        Route::get('/{store}/delivery-time-slots', [SellerDeliveryTimeSlotApiController::class, 'index'])->name('delivery-time-slots.index');
        Route::post('/{store}/delivery-time-slots', [SellerDeliveryTimeSlotApiController::class, 'store'])->name('delivery-time-slots.store');
        Route::put('/{store}/delivery-time-slots/{deliveryTimeSlot}', [SellerDeliveryTimeSlotApiController::class, 'update'])->name('delivery-time-slots.update');
        Route::delete('/{store}/delivery-time-slots/{deliveryTimeSlot}', [SellerDeliveryTimeSlotApiController::class, 'destroy'])->name('delivery-time-slots.destroy');

        // Slot Overrides endpoints
        Route::get('/{store}/slot-overrides', [SellerDeliveryTimeSlotApiController::class, 'listOverrides'])->name('slot-overrides.index');
        Route::post('/{store}/slot-overrides', [SellerDeliveryTimeSlotApiController::class, 'storeOverride'])->name('slot-overrides.store');
        Route::delete('/{store}/slot-overrides/{overrideId}', [SellerDeliveryTimeSlotApiController::class, 'deleteOverride'])->name('slot-overrides.destroy');
        Route::get('/{store}/resolved-slots', [SellerDeliveryTimeSlotApiController::class, 'resolvedSlots'])->name('resolved-slots');
    });

    // Attributes CRUD
    Route::prefix('attributes')->name('attributes.')->group(function () {
        Route::get('/', [SellerAttributeApiController::class, 'index'])->name('index');
        Route::get('/{id}', [SellerAttributeApiController::class, 'show'])->name('show');
        Route::post('/', [SellerAttributeApiController::class, 'store'])->name('store');
        Route::post('/{id}', [SellerAttributeApiController::class, 'update'])->name('update');
        Route::delete('/{id}', [SellerAttributeApiController::class, 'destroy'])->name('destroy');
    });

    // Attribute values CRUD
    Route::prefix('attribute-values')->name('attribute_values.')->group(function () {
        Route::get('/', [SellerAttributeValueApiController::class, 'index'])->name('index');
        Route::get('/{id}', [SellerAttributeValueApiController::class, 'show'])->name('show');
        Route::post('/', [SellerAttributeValueApiController::class, 'store'])->name('store');
        Route::post('/{id}', [SellerAttributeValueApiController::class, 'update'])->name('update');
        Route::delete('/{id}', [SellerAttributeValueApiController::class, 'destroy'])->name('destroy');
    });

    // Products CRUD
    Route::prefix('products')->name('products.')->group(function () {
        Route::get('/', [SellerProductApiController::class, 'index'])->name('index');
        Route::get('/enums', [SellerProductApiController::class, 'getProductEnums'])->name('enums');
        Route::post('/generate', [AiProductDescriptionController::class, 'generate'])->name('generate-ai-description');
        Route::get('/{id}', [SellerProductApiController::class, 'show'])->name('show');
        Route::post('/', [SellerProductApiController::class, 'store'])->name('store');
        Route::post('/{id}', [SellerProductApiController::class, 'update'])->name('update');
        Route::post('/{id}/update-status', [SellerProductApiController::class, 'updateStatus'])->name('update-status');
        Route::delete('/{id}', [SellerProductApiController::class, 'destroy'])->name('destroy');
    });

    // Product FAQs (seller-managed)
    Route::prefix('product-faqs')->name('product_faqs.')->group(function () {
        Route::get('/', [SellerProductFaqApiController::class, 'index'])->name('index');
        Route::get('/{id}', [SellerProductFaqApiController::class, 'show'])->name('show');
        Route::post('/', [SellerProductFaqApiController::class, 'store'])->name('store');
        Route::post('/{id}', [SellerProductFaqApiController::class, 'update'])->name('update');
        Route::delete('/{id}', [SellerProductFaqApiController::class, 'destroy'])->name('destroy');
    });

    // Tax Classes (read-only for sellers)
    Route::prefix('tax-classes')->name('tax_classes.')->group(function () {
        Route::get('/', [SellerTaxClassApiController::class, 'index'])->name('index');
        Route::get('/{id}', [SellerTaxClassApiController::class, 'show'])->name('show');
    });

    // Brands (read-only for sellers)
    Route::prefix('brands')->name('brands.')->group(function () {
        Route::get('/', [SellerBrandApiController::class, 'index'])->name('index');
        Route::get('/{id}', [SellerBrandApiController::class, 'show'])->name('show');
    });

    // Orders (seller-managed)
    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/', [SellerOrderApiController::class, 'index'])->name('index');
        Route::get('/enums', [SellerOrderApiController::class, 'enums'])->name('enums');
        Route::get('/{id}', [SellerOrderApiController::class, 'show'])->name('show');
        // Phase 2 — must be declared before the {id}/{status} catch-all.
        Route::post('/{id}/cancel-item', [SellerOrderApiController::class, 'cancelItem'])->name('cancel_item');
        Route::post('/{id}/confirm-return', [SellerOrderApiController::class, 'confirmReturn'])->name('confirm_return');
        Route::post('/{id}/mark-ready-for-pickup', [SellerOrderApiController::class, 'markReadyForPickup'])->name('mark_ready_for_pickup');
        Route::post('/{id}/verify-pickup-otp', [SellerOrderApiController::class, 'verifyPickupOtp'])->name('verify_pickup_otp');
        Route::post('/{id}/{status}', [SellerOrderApiController::class, 'updateStatus'])->name('update_status');
    });

    // Wallet
    Route::prefix('wallet')->name('wallet.')->group(function () {
        Route::get('/', [SellerWalletApiController::class, 'show'])->name('show');
        Route::get('/transactions', [SellerWalletApiController::class, 'transactions'])->name('transactions');
        Route::get('/transactions/{id}', [SellerWalletApiController::class, 'transaction'])->name('transactions.show');
    });

    // Withdrawals
    Route::prefix('withdrawals')->name('withdrawals.')->group(function () {
        Route::get('/', [SellerWithdrawalApiController::class, 'getWithdrawalRequests'])->name('index');
        Route::get('/history', [SellerWithdrawalApiController::class, 'history'])->name('history');
        Route::post('/', [SellerWithdrawalApiController::class, 'createWithdrawalRequest'])->name('store');
        Route::get('/{id}', [SellerWithdrawalApiController::class, 'getWithdrawalRequest'])->name('show');
    });

    // Earnings / Commissions
    Route::prefix('commissions')->name('commissions.')->group(function () {
        Route::get('/', [SellerEarningApiController::class, 'unsettled'])->name('index');
        Route::get('/debits', [SellerEarningApiController::class, 'unsettledDebits'])->name('debits');
        Route::get('/history', [SellerEarningApiController::class, 'history'])->name('history');
    });

    // Roles (seller-scoped)
    Route::prefix('roles')->name('roles.')->group(function () {
        Route::get('/', [SellerRoleApiController::class, 'index'])->name('index');
        Route::get('/list', [SellerRoleApiController::class, 'getRoles'])->name('list');
        Route::get('/{id}', [SellerRoleApiController::class, 'show'])->name('show');
        Route::post('/', [SellerRoleApiController::class, 'store'])->name('store');
        Route::post('/{id}', [SellerRoleApiController::class, 'update'])->name('update');
        Route::delete('/{id}', [SellerRoleApiController::class, 'destroy'])->name('destroy');
    });

    // Permissions (seller-scoped)
    Route::prefix('permissions')->name('permissions.')->group(function () {
        // Get grouped + assigned permissions for a role
        Route::get('/{role}', [SellerPermissionApiController::class, 'index'])->name('index');
        // Sync role permissions
        Route::post('/', [SellerPermissionApiController::class, 'store'])->name('store');
    });

    // System Users (seller-scoped)
    Route::prefix('system-users')->name('system_users.')->group(function () {
        Route::get('/', [SellerSystemUserApiController::class, 'index'])->name('index');
        Route::get('/{id}', [SellerSystemUserApiController::class, 'show'])->name('show');
        Route::post('/', [SellerSystemUserApiController::class, 'store'])->name('store');
        Route::post('/{id}', [SellerSystemUserApiController::class, 'update'])->name('update');
        Route::delete('/{id}', [SellerSystemUserApiController::class, 'destroy'])->name('destroy');
    });

    // Addon groups (seller-owned)
    Route::prefix('addon-groups')->name('addon_groups.')->group(function () {
        Route::get('/', [SellerAddonGroupApiController::class, 'index'])->name('index');
        Route::get('/enums', [SellerAddonGroupApiController::class, 'enums'])->name('enums');
        Route::get('/{id}', [SellerAddonGroupApiController::class, 'show'])->whereNumber('id')->name('show');
        Route::post('/', [SellerAddonGroupApiController::class, 'store'])->name('store');
        Route::post('/{id}', [SellerAddonGroupApiController::class, 'update'])->whereNumber('id')->name('update');
        Route::delete('/{id}', [SellerAddonGroupApiController::class, 'destroy'])->whereNumber('id')->name('destroy');
    });

    // Product addon attachments (store × variant × addon_group × addon_item)
    Route::prefix('product-addons')->name('product_addons.')->group(function () {
        Route::get('/', [SellerProductAddonApiController::class, 'index'])->name('index');

        // Lookups used to build the attach form on clients.
        Route::get('/lookup/products', [SellerProductAddonApiController::class, 'productLookup'])->name('lookup.products');
        Route::get('/lookup/products/{product}/variants', [SellerProductAddonApiController::class, 'variantLookup'])->whereNumber('product')->name('lookup.variants');
        Route::get('/lookup/variants-bulk', [SellerProductAddonApiController::class, 'variantsBulkLookup'])->name('lookup.variants_bulk');
        Route::get('/lookup/addon-groups', [SellerProductAddonApiController::class, 'addonGroupLookup'])->name('lookup.addon_groups');

        Route::post('/', [SellerProductAddonApiController::class, 'store'])->name('store');
        // Multi-pair matrix lookup — POST JSON body { pairs: [{variant_id, group_id}, ...] }.
        // Returns one matrix per pair, preserving submission order.
        Route::post('/show', [SellerProductAddonApiController::class, 'show'])->name('show');
        Route::post('/{variant}/{group}', [SellerProductAddonApiController::class, 'update'])->whereNumber('variant')->whereNumber('group')->name('update');
        Route::delete('/{variant}/{group}', [SellerProductAddonApiController::class, 'destroy'])->whereNumber('variant')->whereNumber('group')->name('destroy');
    });

    // Store addon inventory (one row per store × addon_item with price/cost/stock/is_available)
    Route::prefix('store-addon-items')->name('store_addon_items.')->group(function () {
        Route::get('/', [SellerStoreAddonItemApiController::class, 'index'])->name('index');

        // Lookups used to build pickers on the client.
        Route::get('/lookup/stores', [SellerStoreAddonItemApiController::class, 'stores'])->name('lookup.stores');
        Route::get('/lookup/addon-groups', [SellerStoreAddonItemApiController::class, 'addonGroups'])->name('lookup.addon_groups');
        Route::get('/lookup/groups/{groupId}/items', [SellerStoreAddonItemApiController::class, 'itemsForGroup'])->whereNumber('groupId')->name('lookup.items_for_group');
        Route::get('/lookup/state', [SellerStoreAddonItemApiController::class, 'state'])->name('lookup.state');

        Route::get('/{id}', [SellerStoreAddonItemApiController::class, 'show'])->whereNumber('id')->name('show');
        Route::post('/bulk', [SellerStoreAddonItemApiController::class, 'bulkStore'])->name('bulk_store');
        Route::post('/{id}', [SellerStoreAddonItemApiController::class, 'update'])->whereNumber('id')->name('update');
        Route::delete('/{id}', [SellerStoreAddonItemApiController::class, 'destroy'])->whereNumber('id')->name('destroy');
    });

    // Subscription usage (seller-scoped)
    Route::prefix('subscription')->name('subscription.')->group(function () {
        Route::post('/check-eligibility', [SellerSubscriptionPlanApiController::class, 'checkEligibility'])->name('eligibility.check');
        Route::post('/buy', [SellerSubscriptionPlanApiController::class, 'buy'])->name('buy');
        Route::get('/current', [SellerSubscriptionPlanApiController::class, 'current'])->name('current');
        Route::get('/{id}/status', [SellerSubscriptionPlanApiController::class, 'status'])->name('status');
        Route::get('/history', [SellerSubscriptionPlanApiController::class, 'history'])->name('history');
    });

    // Ad wallet (seller-scoped)
    Route::prefix('ad-wallet')->name('ad_wallet.')->group(function () {
        Route::get('/', [SellerAdWalletApiController::class, 'show'])->name('show');
        Route::post('/topup/gateway', [SellerAdWalletApiController::class, 'topupGateway'])->name('topup.gateway');
        Route::post('/topup/earnings', [SellerAdWalletApiController::class, 'topupFromEarnings'])->name('topup.earnings');
        Route::get('/transactions', [SellerAdWalletApiController::class, 'transactions'])->name('transactions');
        Route::get('/transactions/{id}', [SellerAdWalletApiController::class, 'transaction'])->whereNumber('id')->name('transactions.show');
    });

    // Ad campaigns (seller-scoped)
    Route::prefix('ad-campaigns')->name('ad_campaigns.')->group(function () {
        Route::get('/', [SellerAdCampaignApiController::class, 'index'])->name('index');
        Route::get('/config', [SellerAdCampaignApiController::class, 'config'])->name('config');
        Route::get('/{id}', [SellerAdCampaignApiController::class, 'show'])->whereNumber('id')->name('show');
        Route::post('/', [SellerAdCampaignApiController::class, 'store'])->name('store');
        Route::post('/{id}/pause', [SellerAdCampaignApiController::class, 'pause'])->whereNumber('id')->name('pause');
        Route::post('/{id}/resume', [SellerAdCampaignApiController::class, 'resume'])->whereNumber('id')->name('resume');
    });

    // ── POS (Point of Sale) ─────────────────────────
    Route::prefix('pos')->name('pos.')->group(function () {
        Route::get('/customers', [SellerPosCustomerApiController::class, 'search'])->name('customers.search');
        Route::post('/customers', [SellerPosCustomerApiController::class, 'quickRegister'])->name('customers.register');
        Route::get('/products', [SellerPosProductApiController::class, 'search'])->name('products.search');
        Route::post('/orders', [SellerPosOrderApiController::class, 'store'])->name('orders.store');
        Route::get('/orders/{id}/receipt', [SellerPosOrderApiController::class, 'receipt'])
            ->whereNumber('id')->name('orders.receipt');
    });

    // Custom Return Reasons (Seller)
    Route::prefix('return-reasons')->name('return_reasons.')->group(function () {
        Route::get('/', [SellerReturnReasonApiController::class, 'index'])->name('index');
        Route::post('/', [SellerReturnReasonApiController::class, 'store'])->name('store');
        Route::get('/{id}', [SellerReturnReasonApiController::class, 'show'])->whereNumber('id')->name('show');
        Route::put('/{id}', [SellerReturnReasonApiController::class, 'update'])->whereNumber('id')->name('update');
        Route::delete('/{id}', [SellerReturnReasonApiController::class, 'destroy'])->whereNumber('id')->name('destroy');
    });
});
