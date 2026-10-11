<?php

use App\Http\Controllers\AddonGroupController;
use App\Http\Controllers\Admin\AiProductDescriptionController;
use App\Http\Controllers\Api\User\UserApiController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\GlobalAttributeController;
use App\Http\Controllers\GlobalAttributeValueController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProductConditionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductFaqController;
use App\Http\Controllers\ProductImageUploadController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReturnRequestController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\Seller\AdCampaignController;
use App\Http\Controllers\Seller\AdWalletController;
use App\Http\Controllers\Seller\AuthController;
use App\Http\Controllers\Seller\DashboardController;
use App\Http\Controllers\Seller\PosController;
use App\Http\Controllers\Seller\PosDashboardController;
use App\Http\Controllers\Seller\PosPaymentSessionController;
use App\Http\Controllers\Seller\SubscriptionPlanController;
use App\Http\Controllers\Seller\WalletController;
use App\Http\Controllers\Seller\WithdrawalController;
use App\Http\Controllers\SellerEarningController;
use App\Http\Controllers\StoreAddonItemController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\StoreProductVariantAddonController;
use App\Http\Controllers\SystemUserController;
use App\Http\Controllers\TaxClassController;
use App\Http\Controllers\TaxRateController;
use Illuminate\Support\Facades\Route;

Route::prefix('seller')->name('seller.')->group(function () {
    Route::middleware(['guest'])->group(function () {
        Route::get('login', [AuthController::class, 'loginSeller'])->name('login');
        Route::post('login', [AuthController::class, 'login'])->name('login.post');
        Route::get('/', function () {
            return view('seller.auth.login');
        })->name('login.index');

        // Password Reset Routes
        Route::get('forgot-password', [PasswordResetController::class, 'showForgotPasswordForm'])->name('password.request');
        Route::post('forgot-password', [PasswordResetController::class, 'sendResetLinkEmail'])->name('password.email');
        Route::get('reset-password/{token}', [PasswordResetController::class, 'showResetPasswordForm'])->name('password.reset');
        Route::post('reset-password', [PasswordResetController::class, 'resetPassword'])->name('password.update');
    });

    Route::middleware([
        'seller.query.token',
        'validate.seller',
    ])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('index');

        Route::get('logout', [AuthController::class, 'logout'])->name('logout');

        // Browser FCM token sync for the seller panel.
        Route::post('devices/sync', [DeviceTokenController::class, 'sync'])->name('devices.sync');
        Route::delete('devices', [DeviceTokenController::class, 'forget'])->name('devices.forget');

        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('dashboard/chart-data', [DashboardController::class, 'getChartData'])->name('dashboard.chart-data');
        Route::get('dashboard/data', [DashboardController::class, 'getDashboardData'])->name('dashboard.data');

        // Global search endpoint for Seller panel (Tenant-scoped: Menu, Products, Orders, Returns)
        Route::get('global-search', [\App\Http\Controllers\Seller\GlobalSearchController::class, 'search'])->name('global-search');

        // Roles
        Route::prefix('roles')->name('roles.')->group(function () {
            Route::get('/', [RoleController::class, 'index'])->name('index');
            Route::post('/', [RoleController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [RoleController::class, 'edit'])->name('edit');
            Route::post('/{id}', [RoleController::class, 'update'])->name('update');
            Route::delete('/{id}', [RoleController::class, 'destroy'])->name('destroy');
            Route::get('/get-roles', [RoleController::class, 'getRoles'])->name('datatable');
            Route::get('/{role}/permissions', [PermissionController::class, 'index'])->name('permissions.index');
        });

        // permissions
        Route::prefix('permissions')->name('permissions.')->group(function () {
            Route::post('/', [PermissionController::class, 'store'])->name('store');
        });

        // profile
        Route::prefix('profile')->name('profile.')->group(function () {
            Route::get('/', [ProfileController::class, 'index'])->name('index');
            Route::get('/edit', [ProfileController::class, 'edit'])->name('edit');
            Route::post('/update', [ProfileController::class, 'update'])->name('update');
            Route::post('/password-update', [ProfileController::class, 'changePassword'])->name('password.update');
        });

        // System Users
        Route::prefix('system-users')->name('system-users.')->group(function () {
            Route::get('/', [SystemUserController::class, 'index'])->name('index');
            Route::post('/', [SystemUserController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [SystemUserController::class, 'show'])->name('show');
            Route::post('/{id}', [SystemUserController::class, 'update'])->name('update');
            Route::delete('/{id}', [SystemUserController::class, 'destroy'])->name('destroy');
            Route::get('/datatable', [SystemUserController::class, 'getSystemUsers'])->name('datatable');
        });

        // brands
        Route::prefix('brands')->name('brands.')->group(function () {
            Route::get('/', [BrandController::class, 'index'])->name('index');
            Route::post('/', [BrandController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [BrandController::class, 'show'])->name('edit');
            Route::get('/{id}/visibility', [BrandController::class, 'visibility'])->name('visibility');
            Route::post('/{id}', [BrandController::class, 'update'])->name('update');
            Route::delete('/{id}', [BrandController::class, 'destroy'])->name('delete');
            Route::get('/datatable', [BrandController::class, 'getBrands'])->name('datatable');
            Route::get('/search', [BrandController::class, 'search'])->name('search');
        });

        // addon groups (seller-owned add-on definitions)
        Route::prefix('addon-groups')->name('addon-groups.')->group(function () {
            Route::get('/', [AddonGroupController::class, 'index'])->name('index');
            Route::get('/datatable', [AddonGroupController::class, 'datatable'])->name('datatable');
            Route::get('/create', [AddonGroupController::class, 'create'])->name('create');
            Route::post('/', [AddonGroupController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [AddonGroupController::class, 'edit'])->whereNumber('id')->name('edit');
            Route::post('/{id}', [AddonGroupController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('/{id}', [AddonGroupController::class, 'destroy'])->whereNumber('id')->name('delete');
        });

        // product addon attachments (store × variant × addon_group × addon_item)
        Route::prefix('product-addons')->name('product-addons.')->group(function () {
            Route::get('/', [StoreProductVariantAddonController::class, 'index'])->name('index');
            Route::get('/datatable', [StoreProductVariantAddonController::class, 'datatable'])->name('datatable');
            Route::get('/create', [StoreProductVariantAddonController::class, 'create'])->name('create');
            Route::post('/', [StoreProductVariantAddonController::class, 'store'])->name('store');

            // AJAX lookups used by the form (Select2/TomSelect + matrix fetch)
            Route::get('/lookup/products', [StoreProductVariantAddonController::class, 'productLookup'])->name('lookup.products');
            Route::get('/lookup/products/{product}/variants', [StoreProductVariantAddonController::class, 'variantLookup'])->whereNumber('product')->name('lookup.variants');
            Route::get('/lookup/variants-bulk', [StoreProductVariantAddonController::class, 'variantsBulkLookup'])->name('lookup.variants-bulk');
            Route::get('/lookup/addon-groups', [StoreProductVariantAddonController::class, 'addonGroupLookup'])->name('lookup.addon-groups');
            Route::get('/matrix/{variant}/{group}', [StoreProductVariantAddonController::class, 'matrix'])->whereNumber('variant')->whereNumber('group')->name('matrix');

            Route::get('/{variant}/{group}/edit', [StoreProductVariantAddonController::class, 'edit'])->whereNumber('variant')->whereNumber('group')->name('edit');
            Route::post('/{variant}/{group}', [StoreProductVariantAddonController::class, 'update'])->whereNumber('variant')->whereNumber('group')->name('update');
            Route::delete('/{variant}/{group}', [StoreProductVariantAddonController::class, 'destroy'])->whereNumber('variant')->whereNumber('group')->name('delete');
        });

        // store addon inventory (store × addon_item rows, with price/cost/stock/availability)
        Route::prefix('store-addon-items')->name('store-addon-items.')->group(function () {
            Route::get('/', [StoreAddonItemController::class, 'index'])->name('index');
            Route::get('/create', [StoreAddonItemController::class, 'create'])->name('create');
            Route::get('/datatable', [StoreAddonItemController::class, 'datatable'])->name('datatable');
            Route::get('/lookup/groups/{groupId}/items', [StoreAddonItemController::class, 'itemsForGroup'])->whereNumber('groupId')->name('items-for-group');
            Route::get('/lookup/state', [StoreAddonItemController::class, 'inventoryState'])->name('state');
            Route::get('/lookup/state-matrix', [StoreAddonItemController::class, 'inventoryStateMatrix'])->name('state-matrix');
            Route::get('/{id}', [StoreAddonItemController::class, 'show'])->whereNumber('id')->name('show');
            Route::post('/', [StoreAddonItemController::class, 'store'])->name('store');
            Route::post('/bulk', [StoreAddonItemController::class, 'bulkStore'])->name('bulk-store');
            Route::post('/{id}', [StoreAddonItemController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('/{id}', [StoreAddonItemController::class, 'destroy'])->whereNumber('id')->name('delete');
        });

        // categories
        Route::prefix('categories')->name('categories.')->group(function () {
            Route::get('/', [CategoryController::class, 'index'])->name('index');
            Route::post('/', [CategoryController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [CategoryController::class, 'show'])->name('edit');
            Route::post('/{id}', [CategoryController::class, 'update'])->name('update');
            Route::delete('/{id}', [CategoryController::class, 'destroy'])->name('delete');
            Route::get('/datatable', [CategoryController::class, 'getCategories'])->name('datatable');
            Route::get('/search', [CategoryController::class, 'search'])->name('search');
        });

        // taxes
        Route::prefix('tax-rates')->name('tax-rates.')->group(function () {
            Route::get('/', [TaxRateController::class, 'index'])->name('index');
            Route::post('/', [TaxRateController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [TaxRateController::class, 'show'])->name('edit');
            Route::post('/{id}', [TaxRateController::class, 'update'])->name('update');
            Route::delete('/{id}', [TaxRateController::class, 'destroy'])->name('delete');
            Route::get('/datatable', [TaxRateController::class, 'getTaxRates'])->name('datatable');
            Route::get('/search', [TaxRateController::class, 'search'])->name('search');
        });

        // Subscription plans (web pages for sellers)
        Route::prefix('subscription-plans')->name('subscription-plans.')->group(function () {
            Route::get('/', [SubscriptionPlanController::class, 'index'])->name('index');
            Route::get('/current', [SubscriptionPlanController::class, 'current'])->name('current');
            Route::get('/history', [SubscriptionPlanController::class, 'history'])->name('history');
            Route::get('/history/datatable', [SubscriptionPlanController::class, 'historyDatatable'])->name('history.datatable');
            Route::get('/{plan}', [SubscriptionPlanController::class, 'show'])
                ->whereNumber('plan')
                ->name('show');
            Route::post('/check-eligibility', [SubscriptionPlanController::class, 'checkEligibility'])->name('eligibility');
            Route::post('/buy', [SubscriptionPlanController::class, 'buy'])->name('buy');
            Route::post('/skip', [SubscriptionPlanController::class, 'skip'])->name('skip');
        });

        // tax classes
        Route::prefix('tax-classes')->name('tax-classes.')->group(function () {
            Route::get('/', [TaxClassController::class, 'index'])->name('index');
            Route::post('/', [TaxClassController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [TaxClassController::class, 'show'])->name('edit');
            Route::post('/{id}', [TaxClassController::class, 'update'])->name('update');
            Route::delete('/{id}', [TaxClassController::class, 'destroy'])->name('delete');
            Route::get('/get-tax-classes', [TaxClassController::class, 'getTaxClasses'])->name('datatable');
            Route::get('/search', [TaxClassController::class, 'search'])->name('search');
        });

        // Stores
        Route::prefix('stores')->name('stores.')->group(function () {
            Route::get('/', [StoreController::class, 'index'])->name('index');
            Route::post('/', [StoreController::class, 'store'])->name('store');
            Route::get('/create', [StoreController::class, 'create'])->name('create');
            Route::get('/{id}/edit', [StoreController::class, 'edit'])->name('edit');
            Route::post('/{id}', [StoreController::class, 'update'])->name('update');
            Route::delete('/{id}', [StoreController::class, 'destroy'])->name('delete');
            Route::get('/datatable', [StoreController::class, 'getStores'])->name('datatable');
            Route::get('/list', [StoreController::class, 'StoreList'])->name('storesList');
            Route::get('/search', [StoreController::class, 'search'])->name('search');
            Route::get('/{id}/configuration', [StoreController::class, 'configuration'])->name('configuration');
            Route::post('/{id}/configuration', [StoreController::class, 'storeConfiguration'])->name('store_configuration');
            Route::post('/{id}/update-status', [StoreController::class, 'updateStatus'])->name('update-status');

            // Delivery Time Slots endpoints accessible via web session in seller panel
            Route::get('/{store}/delivery-time-slots', [\App\Http\Controllers\Api\Seller\SellerDeliveryTimeSlotApiController::class, 'index'])->name('delivery-time-slots.index');
            Route::post('/{store}/delivery-time-slots', [\App\Http\Controllers\Api\Seller\SellerDeliveryTimeSlotApiController::class, 'store'])->name('delivery-time-slots.store');
            Route::put('/{store}/delivery-time-slots/{deliveryTimeSlot}', [\App\Http\Controllers\Api\Seller\SellerDeliveryTimeSlotApiController::class, 'update'])->name('delivery-time-slots.update');
            Route::delete('/{store}/delivery-time-slots/{deliveryTimeSlot}', [\App\Http\Controllers\Api\Seller\SellerDeliveryTimeSlotApiController::class, 'destroy'])->name('delivery-time-slots.destroy');

            // Slot Overrides endpoints
            Route::get('/{store}/slot-overrides', [\App\Http\Controllers\Api\Seller\SellerDeliveryTimeSlotApiController::class, 'listOverrides'])->name('slot-overrides.index');
            Route::post('/{store}/slot-overrides', [\App\Http\Controllers\Api\Seller\SellerDeliveryTimeSlotApiController::class, 'storeOverride'])->name('slot-overrides.store');
            Route::delete('/{store}/slot-overrides/{overrideId}', [\App\Http\Controllers\Api\Seller\SellerDeliveryTimeSlotApiController::class, 'deleteOverride'])->name('slot-overrides.destroy');
            Route::get('/{store}/resolved-slots', [\App\Http\Controllers\Api\Seller\SellerDeliveryTimeSlotApiController::class, 'resolvedSlots'])->name('resolved-slots');
        });

        // products
        Route::prefix('products')->name('products.')->group(function () {
            Route::get('/', [ProductController::class, 'index'])->name('index');
            Route::get('/create', [ProductController::class, 'create'])->name('create');
            Route::post('/generate-ai-description', [AiProductDescriptionController::class, 'generate'])->name('generate-ai-description');
            Route::post('/', [ProductController::class, 'store'])->name('store');
            Route::get('/datatable', [ProductController::class, 'getProducts'])->name('datatable');
            Route::get('/search', [ProductController::class, 'search'])->name('search');
            Route::get('/download-template', [ProductController::class, 'downloadTemplate'])->name('download-template');
            // Bulk upload (Shopify-like CSV)
            Route::get('/bulk-upload', [ProductController::class, 'bulkUploadPage'])->name('bulk-upload.page');
            Route::post('/bulk-upload', [ProductController::class, 'bulkUpload'])->name('bulk-upload');
            // Separate images ZIP upload for products/variants
            Route::get('/images-upload', [ProductImageUploadController::class, 'imagesUploadPage'])->name('images-upload.page');
            Route::post('/images-upload', [ProductImageUploadController::class, 'imagesUpload'])->name('images-upload');
            Route::get('/images-upload/status/{token}', [ProductImageUploadController::class, 'imagesUploadStatus'])->name('images-upload.status');
            Route::get('/{id}/edit', [ProductController::class, 'edit'])->name('edit');
            Route::get('/{id}/visibility', [ProductController::class, 'visibility'])->name('visibility');
            Route::get('/{id}/pricing', [ProductController::class, 'getProductPricing'])->name('pricing');
            Route::post('/{id}', [ProductController::class, 'update'])->name('update');
            Route::post('/{id}/update-status', [ProductController::class, 'updateStatus'])->name('update-status');
            Route::delete('/{id}', [ProductController::class, 'destroy'])->name('delete');
            Route::get('/{id}', [ProductController::class, 'show'])->name('show');
        });

        // global attributes
        Route::prefix('attributes')->name('attributes.')->group(function () {
            Route::get('/', [GlobalAttributeController::class, 'index'])->name('index');
            route::post('/', [GlobalAttributeController::class, 'store'])->name('store');
            Route::get('/datatable', [GlobalAttributeController::class, 'getAttributes'])->name('datatable');
            Route::get('/search', [GlobalAttributeController::class, 'search'])->name('search');
            Route::get('/{id}/edit', [GlobalAttributeController::class, 'edit'])->name('edit');
            Route::post('/{id}', [GlobalAttributeController::class, 'update'])->name('update');
            Route::delete('/{id}', [GlobalAttributeController::class, 'destroy'])->name('delete');
        });

        // product conditions
        Route::prefix('product-conditions')->name('product_conditions.')->group(function () {
            Route::get('/', [ProductConditionController::class, 'index'])->name('index');
            Route::post('/', [ProductConditionController::class, 'store'])->name('store');
            Route::get('/datatable', [ProductConditionController::class, 'getProductConditions'])->name('datatable');
            Route::get('/search', [ProductConditionController::class, 'search'])->name('search');
            Route::get('/{id}/edit', [ProductConditionController::class, 'edit'])->name('edit');
            Route::post('/{id}', [ProductConditionController::class, 'update'])->name('update');
            Route::delete('/{id}', [ProductConditionController::class, 'destroy'])->name('delete');
        });

        route::prefix('attribute/values')->name('attributes.values.')->group(function () {
            Route::get('/datatable', [GlobalAttributeValueController::class, 'getAllAttributeValues'])->name('datatable');
            Route::post('/', [GlobalAttributeValueController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [GlobalAttributeValueController::class, 'edit'])->name('edit');
            Route::post('/{id}', [GlobalAttributeValueController::class, 'update'])->name('update');
            Route::delete('/{id}', [GlobalAttributeValueController::class, 'destroy'])->name('delete');
        });

        // product Faqs
        Route::prefix('product-faqs')->name('product_faqs.')->group(function () {
            Route::get('/', [ProductFaqController::class, 'index'])->name('index');
            Route::post('/', [ProductFaqController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [ProductFaqController::class, 'edit'])->name('edit');
            Route::post('/{id}', [ProductFaqController::class, 'update'])->name('update');
            Route::delete('/{id}', [ProductFaqController::class, 'destroy'])->name('delete');
            Route::get('/datatable', [ProductFaqController::class, 'getProductFaqs'])->name('datatable');
            //            Route::get('/search', [ProductFaqController::class, 'search'])->name('search');
        });

        // wallet
        Route::prefix('wallet')->name('wallet.')->group(function () {
            Route::get('/', [WalletController::class, 'index'])->name('index');
            Route::get('/transactions', [WalletController::class, 'transactions'])->name('transactions');
            Route::get('/transactions/datatable', [WalletController::class, 'getTransactions'])->name('transactions.datatable');
        });

        // ads
        Route::prefix('ads')->name('ads.')->group(function () {
            Route::prefix('wallet')->name('wallet.')->group(function () {
                Route::get('/', [AdWalletController::class, 'index'])->name('index');
                Route::get('/topup', [AdWalletController::class, 'topupForm'])->name('topup');
                Route::post('/topup/gateway', [AdWalletController::class, 'topupGateway'])->name('topup.gateway');
                Route::post('/topup/earnings', [AdWalletController::class, 'topupFromEarnings'])->name('topup.earnings');
                Route::get('/transactions', [AdWalletController::class, 'transactions'])->name('transactions');
                Route::get('/transactions/datatable', [AdWalletController::class, 'getTransactions'])->name('transactions.datatable');
            });

            Route::prefix('campaigns')->name('campaigns.')->group(function () {
                Route::get('/', [AdCampaignController::class, 'index'])->name('index');
                Route::get('/create', [AdCampaignController::class, 'create'])->name('create');
                Route::post('/', [AdCampaignController::class, 'store'])->name('store');
                Route::get('/datatable', [AdCampaignController::class, 'getCampaigns'])->name('datatable');
                Route::get('/dashboard', [AdCampaignController::class, 'dashboard'])->name('dashboard');
                Route::get('/dashboard/data', [AdCampaignController::class, 'getDashboardData'])->name('dashboard.data');
                Route::post('/{id}/pause', [AdCampaignController::class, 'pause'])->name('pause');
                Route::post('/{id}/resume', [AdCampaignController::class, 'resume'])->name('resume');
            });
        });

        // withdrawals
        Route::prefix('withdrawals')->name('withdrawals.')->group(function () {
            Route::get('/', [WithdrawalController::class, 'index'])->name('index');
            Route::post('/', [WithdrawalController::class, 'store'])->name('store');
            Route::get('/history', [WithdrawalController::class, 'history'])->name('history');
            Route::get('/datatable', [WithdrawalController::class, 'getWithdrawalRequests'])->name('datatable');
            Route::get('/history/datatable', [WithdrawalController::class, 'getWithdrawalHistory'])->name('history.datatable');
        });

        // orders
        Route::prefix('orders')->name('orders.')->group(function () {
            Route::get('/', [OrderController::class, 'index'])->name('index');
            Route::get('/datatable', [OrderController::class, 'getOrders'])->name('datatable');
            Route::get('/list-datatable', [OrderController::class, 'getOrdersGrouped'])->name('list_datatable');
            Route::get('/list-items/{id}', [OrderController::class, 'getOrderItemsForExpand'])->name('list_items');
            Route::get('invoice', [OrderController::class, 'orderInvoice'])->name('invoice');
            Route::get('/{id}', [OrderController::class, 'show'])->name('show');
            // Phase 1D — must be declared before the {id}/{status} catch-all,
            // otherwise "cancel" / "confirm-return" would be matched as a status.
            Route::post('/{id}/cancel-item', [OrderController::class, 'cancelItem'])->name('cancel_item');
            Route::post('/{id}/confirm-return', [OrderController::class, 'confirmReturn'])->name('confirm_return');
            Route::post('/{id}/items/bulk-update-status', [OrderController::class, 'sellerBulkUpdateItemStatus'])->name('items_bulk_update_status');
            Route::post('/{id}/mark-ready-for-pickup', [OrderController::class, 'markReadyForPickup'])->name('mark_ready_for_pickup');
            Route::post('/{id}/verify-pickup-otp', [OrderController::class, 'verifyPickupOtp'])->name('verify_pickup_otp');
            Route::get('/{id}/refund-preview', [OrderController::class, 'refundPreview'])->whereNumber('id')->name('refunds.preview');
            Route::post('/{id}/refund', [OrderController::class, 'createRefund'])->whereNumber('id')->name('refunds.create');
            Route::get('/{id}/refunds', [OrderController::class, 'refundHistory'])->whereNumber('id')->name('refunds.history');

            Route::post('/{id}/{status}', [OrderController::class, 'updateStatus'])->name('update_status');
        });

        // scheduled orders
        Route::prefix('scheduled-orders')->name('scheduled_orders.')->group(function () {
            Route::get('/', [OrderController::class, 'indexScheduled'])->name('index');
            Route::get('/datatable', [OrderController::class, 'getScheduledOrders'])->name('datatable');
            Route::get('/list-datatable', [OrderController::class, 'getScheduledOrdersGrouped'])->name('list_datatable');
            Route::get('/list-items/{id}', [OrderController::class, 'getOrderItemsForExpand'])->name('list_items');
            Route::get('invoice', [OrderController::class, 'orderInvoice'])->name('invoice');
            Route::get('/{id}', [OrderController::class, 'show'])->name('show');
            Route::post('/{id}/cancel-item', [OrderController::class, 'cancelItem'])->name('cancel_item');
            Route::post('/{id}/confirm-return', [OrderController::class, 'confirmReturn'])->name('confirm_return');
            Route::post('/{id}/items/bulk-update-status', [OrderController::class, 'sellerBulkUpdateItemStatus'])->name('items_bulk_update_status');
            Route::post('/{id}/mark-ready-for-pickup', [OrderController::class, 'markReadyForPickup'])->name('mark_ready_for_pickup');
            Route::post('/{id}/verify-pickup-otp', [OrderController::class, 'verifyPickupOtp'])->name('verify_pickup_otp');
            Route::get('/{id}/refund-preview', [OrderController::class, 'refundPreview'])->whereNumber('id')->name('refunds.preview');
            Route::post('/{id}/refund', [OrderController::class, 'createRefund'])->whereNumber('id')->name('refunds.create');
            Route::get('/{id}/refunds', [OrderController::class, 'refundHistory'])->whereNumber('id')->name('refunds.history');
            Route::post('/{id}/{status}', [OrderController::class, 'updateStatus'])->name('update_status');
        });

        // Commission Settlement Routes
        Route::prefix('commissions')->name('commissions.')->group(function () {
            Route::get('/', [SellerEarningController::class, 'index'])->name('index');
            // Credits
            Route::get('/datatable', [SellerEarningController::class, 'getUnsettledCommissions'])->name('datatable');
            // Debits
            Route::get('/debits/datatable', [SellerEarningController::class, 'getUnsettledDebits'])->name('debits.datatable');
            // History
            Route::get('/history', [SellerEarningController::class, 'history'])->name('history');
            Route::get('/history/datatable', [SellerEarningController::class, 'getSettledCommissions'])->name('history.datatable');
        });

        // Notifications Routes
        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/', [NotificationController::class, 'index'])->name('index');
            Route::get('/datatable', [NotificationController::class, 'getNotifications'])->name('datatable');
            Route::get('/{id}', [NotificationController::class, 'show'])->name('show');
            Route::post('/{id}/mark-read', [NotificationController::class, 'markAsRead'])->name('mark-read');
            Route::post('/{id}/mark-unread', [NotificationController::class, 'markAsUnread'])->name('mark-unread');
            Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('mark-all-read');
            Route::delete('/{id}', [NotificationController::class, 'destroy'])->name('destroy');
        });

        // Return requests listing / datatable
        Route::get('order-returns', [ReturnRequestController::class, 'index'])->name('returns.index');
        Route::get('order-returns/datatable', [ReturnRequestController::class, 'datatable'])->name('returns.datatable');
        Route::get('order-returns/{id}', [ReturnRequestController::class, 'show'])->whereNumber('id')->name('returns.show');

        // Approve / reject
        Route::post('order-returns/{id}/decision', [ReturnRequestController::class, 'decision'])->name('returns.decision');
        Route::post('order-returns/{id}/store-confirm', [ReturnRequestController::class, 'storeConfirm'])->name('returns.store-confirm');

        // Product Return Reasons Management for Seller
        Route::get('return-reasons/datatable', [\App\Http\Controllers\Seller\ReturnReasonController::class, 'datatable'])->name('return-reasons.datatable');
        Route::resource('return-reasons', \App\Http\Controllers\Seller\ReturnReasonController::class)->names('return-reasons')->only(['index', 'store', 'update', 'destroy']);

        // ── POS (Point of Sale) ─────────────────────────
        Route::prefix('pos')->name('pos.')->group(function () {
            Route::get('/dashboard', [PosDashboardController::class, 'index'])->name('dashboard');
            Route::get('/dashboard/data', [PosDashboardController::class, 'getData'])->name('dashboard.data');
            Route::get('/', [PosController::class, 'index'])->name('index');
            Route::get('/orders/{id}/receipt', [PosController::class, 'receipt'])
                ->whereNumber('id')->name('orders.receipt');

            Route::prefix('api')->name('api.')->group(function () {
                Route::get('/products', [PosController::class, 'searchProducts'])->name('products.search');
                Route::get('/products/by-barcode', [PosController::class, 'lookupByBarcode'])->name('products.barcode');
                Route::get('/categories', [PosController::class, 'categories'])->name('categories.index');
                Route::get('/customers', [PosController::class, 'searchCustomers'])->name('customers.search');
                Route::get('/users/search', [UserApiController::class, 'search'])->name('users.search');
                Route::post('/customers', [PosController::class, 'registerCustomer'])->name('customers.register');
                Route::post('/orders', [PosController::class, 'createOrder'])->name('orders.create');
                Route::get('/orders/recent', [PosController::class, 'recentOrders'])->name('orders.recent');
                Route::post('/promo/validate', [PosController::class, 'validatePromo'])->name('promo.validate');
                Route::get('/customers/{id}/wallet', [PosController::class, 'customerWallet'])->whereNumber('id')->name('customers.wallet');
                Route::post('/cfd/push', [PosController::class, 'cfdPush'])->name('cfd.push');

                Route::get('/parked', [PosController::class, 'listParkedSales'])->name('parked.list');
                Route::post('/parked', [PosController::class, 'parkSale'])->name('parked.create');
                Route::patch('/parked/{id}', [PosController::class, 'updateParkedSale'])->whereNumber('id')->name('parked.update');
                Route::delete('/parked/{id}', [PosController::class, 'deleteParkedSale'])->whereNumber('id')->name('parked.delete');

                Route::get('/orders/{id}/refund-preview', [PosController::class, 'refundPreview'])->whereNumber('id')->name('refunds.preview');
                Route::post('/orders/{id}/refund', [PosController::class, 'createRefund'])->whereNumber('id')->name('refunds.create');
                Route::get('/orders/{id}/refunds', [PosController::class, 'refundHistory'])->whereNumber('id')->name('refunds.history');

                Route::prefix('payment-sessions')->name('payment_sessions.')->group(function () {
                    Route::post('/', [PosPaymentSessionController::class, 'create'])->name('create');
                    Route::get('/{token}', [PosPaymentSessionController::class, 'status'])->name('status');
                    Route::post('/{token}/cancel', [PosPaymentSessionController::class, 'cancel'])->name('cancel');
                });
            });
        });
    });
});
