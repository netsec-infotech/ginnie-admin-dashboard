    <?php

    use App\Http\Controllers\Admin\AdCampaignController as AdminAdCampaignController;
    use App\Http\Controllers\Admin\AiProductDescriptionController;
    use App\Http\Controllers\Admin\AuthController;
    use App\Http\Controllers\Admin\CronMonitorController;
    use App\Http\Controllers\Admin\CustomerController;
    use App\Http\Controllers\Admin\DashboardController;
    use App\Http\Controllers\Admin\DeliveryBoyCashCollectionController;
    use App\Http\Controllers\Admin\DeliveryBoyEarningController;
    use App\Http\Controllers\Admin\DeliveryBoyReferralController;
    use App\Http\Controllers\Admin\DeliveryBoyWithdrawalController;
    use App\Http\Controllers\Admin\DispatchController;
    use App\Http\Controllers\Admin\HomeBuilderController;
    use App\Http\Controllers\Admin\ImpersonationController;
    use App\Http\Controllers\Admin\PosDashboardController as AdminPosDashboardController;
    use App\Http\Controllers\Admin\PromoController;
    use App\Http\Controllers\Admin\ReferralController;
    use App\Http\Controllers\Admin\ReturnReasonController;
    use App\Http\Controllers\Admin\ReturnRejectionReasonController;
    use App\Http\Controllers\Admin\ReturnRequestController as AdminReturnRequestController;
    use App\Http\Controllers\Admin\ReviewManagementController;
    use App\Http\Controllers\Admin\SellerWithdrawalController;
    use App\Http\Controllers\Admin\SettingController;
    use App\Http\Controllers\Admin\SubscriptionFeatureController;
    use App\Http\Controllers\Admin\SubscriptionPlanController;
    use App\Http\Controllers\Admin\SystemUpdateController;
    use App\Http\Controllers\Admin\VendorTypeController;
    use App\Http\Controllers\Admin\WalletTransactionController;
    use App\Http\Controllers\Admin\ZonePreviewController;
    use App\Http\Controllers\Api\User\UserApiController;
    use App\Http\Controllers\AppNotificationController;
    use App\Http\Controllers\BadgeController;
    use App\Http\Controllers\BannerController;
    use App\Http\Controllers\BrandController;
    use App\Http\Controllers\CategoryController;
    use App\Http\Controllers\DeliveryBoyController;
    use App\Http\Controllers\DeliveryZoneController;
    use App\Http\Controllers\DeviceTokenController;
    use App\Http\Controllers\FaqController;
    use App\Http\Controllers\FeaturedSectionController;
    use App\Http\Controllers\NotificationController;
    use App\Http\Controllers\OrderController;
    use App\Http\Controllers\PasswordResetController;
    use App\Http\Controllers\PermissionController;
    use App\Http\Controllers\ProductController;
    use App\Http\Controllers\ProductFaqController;
    use App\Http\Controllers\ProfileController;
    use App\Http\Controllers\RoleController;
    use App\Http\Controllers\SellerController;
    use App\Http\Controllers\SellerEarningController;
    use App\Http\Controllers\StoreController;
    use App\Http\Controllers\SystemUserController;
    use App\Http\Controllers\TaxClassController;
    use App\Http\Controllers\TaxRateController;
    use Illuminate\Support\Facades\Route;

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware(['guest'])->group(function () {
            Route::get('/', [AuthController::class, 'loginAdmin'])->name('login');
            Route::get('login', [AuthController::class, 'loginAdmin'])->name('login');
            Route::post('login', [AuthController::class, 'login'])->name('login.post');

            // Password Reset Routes
            Route::get('forgot-password', [PasswordResetController::class, 'showForgotPasswordForm'])->name('password.request');
            Route::post('forgot-password', [PasswordResetController::class, 'sendResetLinkEmail'])->name('password.email');
            Route::get('reset-password/{token}', [PasswordResetController::class, 'showResetPasswordForm'])->name('password.reset');
            Route::post('reset-password', [PasswordResetController::class, 'resetPassword'])->name('password.update');
        });

        Route::middleware([
            'auth',
            'validate.admin',
            'ensure.system.type',
            'ensure.subscription.feature',
        ])->group(function () {
            Route::get('logout', [AuthController::class, 'logout'])->name('logout');

            // Browser FCM token sync for the admin panel.
            Route::post('devices/sync', [DeviceTokenController::class, 'sync'])->name('devices.sync');
            Route::delete('devices', [DeviceTokenController::class, 'forget'])->name('devices.forget');

            Route::get('/', [DashboardController::class, 'index'])->name('index');
            Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
            Route::get('dashboard/chart-data', [DashboardController::class, 'getChartData'])->name('dashboard.chart-data');
            Route::get('dashboard/data', [DashboardController::class, 'getDashboardData'])->name('dashboard.data');

            // Global search endpoint for Admin panel (Menu, Products, Orders, Returns, Customers, Sellers)
            Route::get('global-search', [\App\Http\Controllers\Admin\GlobalSearchController::class, 'search'])->name('global-search');

            Route::get('pos-dashboard', [AdminPosDashboardController::class, 'index'])->name('pos-dashboard');
            Route::get('pos-dashboard/data', [AdminPosDashboardController::class, 'getData'])->name('pos-dashboard.data');

            Route::get('system-type', [VendorTypeController::class, 'index'])->name('system-type');
            Route::post('system-type', [VendorTypeController::class, 'store'])->name('system-type.store');

            Route::get('subscription-feature', [SubscriptionFeatureController::class, 'index'])->name('subscription-feature');
            Route::post('subscription-feature', [SubscriptionFeatureController::class, 'store'])->name('subscription-feature.store');

            // Admin <-> Seller impersonation (Single vendor only)
            Route::get('impersonate/seller', [ImpersonationController::class, 'toSeller'])->name('impersonate.to-seller');
            Route::get('impersonate/admin', [ImpersonationController::class, 'toAdmin'])->name('impersonate.to-admin');

            // profile
            Route::prefix('profile')->name('profile.')->group(function () {
                Route::get('/', [ProfileController::class, 'index'])->name('index');
                Route::get('/edit', [ProfileController::class, 'edit'])->name('edit');
                Route::post('/update', [ProfileController::class, 'update'])->name('update');
                Route::post('/password-update', [ProfileController::class, 'changePassword'])->name('password.update');
            });

            // license
            Route::prefix('license')->name('license.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Admin\LicenseManagementController::class, 'index'])->name('index');
                Route::post('/deregister', [\App\Http\Controllers\Admin\LicenseManagementController::class, 'deregister'])->name('deregister');
            });

            // settings
            Route::prefix('settings')->namespace('Settings')->name('settings.')->group(function () {
                Route::get('/', [SettingController::class, 'index'])->name('index');
                Route::post('authentication/test-sms', [SettingController::class, 'testSms'])
                    ->middleware('throttle:6,1')
                    ->name('authentication.test-sms');
                Route::post('email/test', [SettingController::class, 'testEmail'])
                    ->middleware('throttle:6,1')
                    ->name('email.test');
                Route::post('payment/test', [SettingController::class, 'testPayment'])
                    ->middleware('throttle:10,1')
                    ->name('payment.test');
                Route::get('{setting}', [SettingController::class, 'show'])->name('show');
                Route::post('store', [SettingController::class, 'store'])->name('store');
            });

            // system updates
            Route::prefix('system-updates')->name('system-updates.')->group(function () {
                Route::get('/', [SystemUpdateController::class, 'index'])->name('index');
                Route::post('/', [SystemUpdateController::class, 'store'])->name('store');
                Route::get('/datatable', [SystemUpdateController::class, 'datatable'])->name('datatable');
                // Live log endpoints
                Route::get('/latest', [SystemUpdateController::class, 'latest'])->name('latest');
                Route::get('/{update}/log', [SystemUpdateController::class, 'showLog'])->name('log');
            });

            // categories
            Route::prefix('categories')->namespace('Categories')->name('categories.')->group(function () {
                Route::get('/', [CategoryController::class, 'index'])->name('index');
                Route::get('/create', [CategoryController::class, 'create'])->name('create');
                Route::get('/sort', [CategoryController::class, 'sort'])->name('sort');
                Route::get('/datatable', [CategoryController::class, 'getCategories'])->name('datatable');
                Route::get('/search', [CategoryController::class, 'search'])->name('search')->name('search');
                Route::get('/search-labels', [CategoryController::class, 'searchLabels'])->name('search-labels');
                Route::get('/bulk-upload', [CategoryController::class, 'bulkUploadPage'])->name('bulk-upload.page');
                Route::get('/bulk-template', [CategoryController::class, 'downloadTemplate'])->name('bulk-template');
                Route::post('/sort', [CategoryController::class, 'updateSort'])->name('sort.update');
                Route::post('/home-categories', [CategoryController::class, 'updateHomeCategories'])->name('home-categories.update');
                Route::post('/', [CategoryController::class, 'store'])->name('store');
                Route::post('/bulk-upload', [CategoryController::class, 'bulkUpload'])->name('bulk-upload');
                Route::get('/{id}/edit', [CategoryController::class, 'editPage'])->name('edit');
                Route::post('/{id}', [CategoryController::class, 'update'])->name('update');
                Route::post('/{id}/appearance', [CategoryController::class, 'updateAppearance'])->name('appearance.update');
                Route::delete('/{id}', [CategoryController::class, 'destroy'])->name('delete');
                Route::get('/datatable', [CategoryController::class, 'getCategories'])->name('datatable');
                Route::get('/search', [CategoryController::class, 'search'])->name('search');
                // Bulk upload routes
                Route::get('/bulk-upload', [CategoryController::class, 'bulkUploadPage'])->name('bulk-upload.page');
                Route::get('/bulk-template', [CategoryController::class, 'downloadTemplate'])->name('bulk-template');
                Route::get('/{id}', [CategoryController::class, 'show'])->name('show');
            });

            // brands
            Route::prefix('brands')->namespace('Brands')->name('brands.')->group(function () {
                Route::get('/', [BrandController::class, 'index'])->name('index');
                Route::post('/', [BrandController::class, 'store'])->name('store');
                Route::get('/{id}/edit', [BrandController::class, 'show'])->name('edit');
                Route::get('/{id}/visibility', [BrandController::class, 'visibility'])->name('visibility');
                Route::post('/{id}', [BrandController::class, 'update'])->name('update');
                Route::delete('/{id}', [BrandController::class, 'destroy'])->name('delete');
                Route::get('/datatable', [BrandController::class, 'getBrands'])->name('datatable');
                Route::get('/search', [BrandController::class, 'search'])->name('search');
            });
            Route::bulkUpload('brands');

            // badges
            Route::prefix('badges')->name('badges.')->group(function () {
                Route::get('/', [BadgeController::class, 'index'])->name('index');
                Route::get('/datatable', [BadgeController::class, 'getList'])->name('datatable');
                Route::get('/list', [BadgeController::class, 'listAll'])->name('list');
                Route::get('/search', [BadgeController::class, 'search'])->name('search');
                Route::post('/', [BadgeController::class, 'store'])->name('store');
                Route::post('/{id}', [BadgeController::class, 'update'])->whereNumber('id')->name('update');
                Route::delete('/{id}', [BadgeController::class, 'destroy'])->whereNumber('id')->name('delete');
                Route::post('/products/bulk-assign', [BadgeController::class, 'bulkAssign'])->name('products.bulk-assign');
                Route::post('/products/bulk-remove', [BadgeController::class, 'bulkRemove'])->name('products.bulk-remove');
            });

            // promos
            Route::prefix('promos')->name('promos.')->group(function () {
                Route::get('/', [PromoController::class, 'index'])->name('index');
                Route::post('/', [PromoController::class, 'store'])->name('store');
                Route::get('/datatable', [PromoController::class, 'datatable'])->name('datatable');
                Route::get('/{id}', [PromoController::class, 'show'])->name('show');
                Route::put('/{id}', [PromoController::class, 'update'])->name('update');
                Route::delete('/{id}', [PromoController::class, 'destroy'])->name('destroy');
            });

            // customers (web panel users)
            Route::prefix('customers')->name('customers.')->group(function () {
                Route::get('/', [CustomerController::class, 'index'])->name('index');
                Route::get('/datatable', [CustomerController::class, 'datatable'])->name('datatable');
                Route::get('/export', [CustomerController::class, 'export'])->name('export');
                Route::get('/{id}/show', [CustomerController::class, 'show'])->name('show');
                Route::get('/{id}/orders-datatable', [CustomerController::class, 'ordersDatatable'])->name('orders-datatable');
                Route::get('/{id}/wallet-datatable', [CustomerController::class, 'walletDatatable'])->name('wallet-datatable');
                Route::get('/{id}/notifications-datatable', [CustomerController::class, 'notificationsDatatable'])->name('notifications-datatable');
                Route::get('/{id}/loyalty-datatable', [CustomerController::class, 'loyaltyDatatable'])->name('loyalty-datatable');
                Route::post('/{id}/adjust-loyalty-points', [CustomerController::class, 'adjustLoyaltyPoints'])->name('adjust-loyalty-points');
            });

            Route::get('/users/search', [UserApiController::class, 'search'])->name('users.search');

            // sellers
            Route::prefix('sellers')->name('sellers.')->group(function () {
                Route::get('/', [SellerController::class, 'index'])->name('index');
                Route::post('/', [SellerController::class, 'store'])->name('store');
                Route::get('/create', [SellerController::class, 'create'])->name('create');
                Route::get('/{id}/edit', [SellerController::class, 'edit'])->name('edit');
                Route::post('/{id}', [SellerController::class, 'update'])->name('update');
                Route::delete('/{id}', [SellerController::class, 'destroy'])->name('delete');
                Route::get('/datatable', [SellerController::class, 'getSellers'])->name('datatable');
                Route::get('/search', [SellerController::class, 'search'])->name('search')->name('search');
                Route::get('/{id}/show', [SellerController::class, 'show'])->name('show');
                Route::get('/{id}/statements-datatable', [SellerController::class, 'statementsDatatable'])->name('statements-datatable');
                Route::get('/{id}/feedback-datatable', [SellerController::class, 'feedbackDatatable'])->name('feedback-datatable');
                Route::get('/{id}/notifications-datatable', [SellerController::class, 'notificationsDatatable'])->name('notifications-datatable');
            });

            Route::prefix('sellers/reviews')->name('sellers.reviews.')->group(function () {
                Route::get('/', [ReviewManagementController::class, 'index'])->defaults('context', 'sellers')->name('index');
                Route::get('/datatable', [ReviewManagementController::class, 'datatable'])->defaults('context', 'sellers')->name('datatable');
                Route::delete('/{review}', [ReviewManagementController::class, 'destroy'])->defaults('context', 'sellers')->name('destroy');
            });

            // subscription plans
            Route::prefix('subscription-plans')->name('subscription-plans.')->group(function () {
                Route::get('/', [SubscriptionPlanController::class, 'index'])->name('index');
                Route::post('/', [SubscriptionPlanController::class, 'store'])->name('store');
                Route::get('/create', [SubscriptionPlanController::class, 'create'])->name('create');
                Route::get('/datatable', [SubscriptionPlanController::class, 'datatable'])->name('datatable');
                // Subscribers list (sellers who purchased plans)
                Route::get('/subscribers', [SubscriptionPlanController::class, 'subscribers'])->name('subscribers');
                Route::get('/subscribers/datatable', [SubscriptionPlanController::class, 'subscribersDatatable'])->name('subscribers.datatable');
                Route::get('/subscribers/{id}', [SubscriptionPlanController::class, 'subscriberShow'])->name('subscribers.show');
                Route::get('view', function () {
                    return view('admin.subscription-plans.view');
                });
                Route::get('/{id}/edit', [SubscriptionPlanController::class, 'edit'])->name('edit');
                Route::post('/{id}', [SubscriptionPlanController::class, 'update'])->name('update');
                Route::delete('/{id}', [SubscriptionPlanController::class, 'destroy'])->name('destroy');
            });

            // taxes
            Route::prefix('tax-rates')->namespace('TaxRates')->name('tax-rates.')->group(function () {
                Route::get('/', [TaxRateController::class, 'index'])->name('index');
                Route::post('/', [TaxRateController::class, 'store'])->name('store');
                Route::get('/{id}/edit', [TaxRateController::class, 'show'])->name('edit');
                Route::post('/{id}', [TaxRateController::class, 'update'])->name('update');
                Route::delete('/{id}', [TaxRateController::class, 'destroy'])->name('delete');
                Route::get('/datatable', [TaxRateController::class, 'getTaxRates'])->name('datatable');
                Route::get('/search', [TaxRateController::class, 'search'])->name('search');
            });

            // tax classes
            Route::prefix('tax-classes')->namespace('TaxClasses')->name('tax-classes.')->group(function () {
                Route::get('/', [TaxClassController::class, 'index'])->name('index');
                Route::post('/', [TaxClassController::class, 'store'])->name('store');
                Route::get('/{id}/edit', [TaxClassController::class, 'show'])->name('edit');
                Route::post('/{id}', [TaxClassController::class, 'update'])->name('update');
                Route::delete('/{id}', [TaxClassController::class, 'destroy'])->name('delete');
                Route::get('/get-tax-classes', [TaxClassController::class, 'getTaxClasses'])->name('datatable');
                Route::get('/search', [TaxRateController::class, 'search'])->name('search');
            });

            // Roles and Permissions
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
            Route::prefix('permissions')->namespace('Permissions')->name('permissions.')->group(function () {
                Route::post('/', [PermissionController::class, 'store'])->name('store');
            });

            // System Users
            Route::prefix('system-users')->namespace('systemUsers')->name('system-users.')->group(function () {
                Route::get('/', [SystemUserController::class, 'index'])->name('index');
                Route::post('/', [SystemUserController::class, 'store'])->name('store');
                Route::get('/{id}/edit', [SystemUserController::class, 'show'])->name('show');
                Route::post('/{id}', [SystemUserController::class, 'update'])->name('update');
                Route::delete('/{id}', [SystemUserController::class, 'destroy'])->name('destroy');
                Route::get('/datatable', [SystemUserController::class, 'getSystemUsers'])->name('datatable');
            });

            // seller stores
            Route::prefix('sellers/store')->name('sellers.store.')->group(function () {
                Route::get('/', [StoreController::class, 'index'])->name('index');
                Route::get('/', [StoreController::class, 'index'])->name('index');
                Route::get('/reviews', [ReviewManagementController::class, 'index'])->defaults('context', 'stores')->name('reviews.index');
                Route::get('/reviews/datatable', [ReviewManagementController::class, 'datatable'])->defaults('context', 'stores')->name('reviews.datatable');
                Route::delete('/reviews/{review}', [ReviewManagementController::class, 'destroy'])->defaults('context', 'stores')->name('reviews.destroy');
                Route::get('/datatable', [StoreController::class, 'getStores'])->name('datatable');
                Route::get('/search', [StoreController::class, 'search'])->name('search');
                Route::get('/view/{id}', [StoreController::class, 'index'])->name('show.index');
                Route::get('/{id}', [StoreController::class, 'show'])->name('show');
                Route::post('/{id}/verify', [StoreController::class, 'verify'])->name('verify');
                Route::post('/{id}/toggle-recommended', [StoreController::class, 'toggleRecommended'])->name('toggle-recommended');
            });

            // product Faqs
            Route::prefix('faqs')->name('faqs.')->group(function () {
                Route::get('/', [FaqController::class, 'index'])->name('index');
                Route::post('/', [FaqController::class, 'store'])->name('store');
                Route::get('/{id}/edit', [FaqController::class, 'edit'])->name('edit');
                Route::post('/{id}', [FaqController::class, 'update'])->name('update');
                Route::delete('/{id}', [FaqController::class, 'destroy'])->name('delete');
                Route::get('/datatable', [FaqController::class, 'getFaqs'])->name('datatable');
            });

            // banners
            Route::prefix('banners')->name('banners.')->group(function () {
                Route::get('/', [BannerController::class, 'index'])->name('index');
                Route::post('/', [BannerController::class, 'store'])->name('store');
                Route::get('/create', [BannerController::class, 'create'])->name('create');
                Route::get('/{id}/edit', [BannerController::class, 'edit'])->name('edit');
                Route::get('/{id}/visibility', [BannerController::class, 'visibility'])->name('visibility');
                Route::post('/{id}', [BannerController::class, 'update'])->name('update');
                Route::delete('/{id}', [BannerController::class, 'destroy'])->name('delete');
                Route::get('/datatable', [BannerController::class, 'getBanners'])->name('datatable');
            });

            // wallet transactions and deposits
            Route::prefix('wallet')->name('wallet.')->group(function () {
                // All transactions
                Route::get('/transactions', [WalletTransactionController::class, 'transactions'])->name('transactions');
                Route::get('/transactions/datatable', [WalletTransactionController::class, 'transactionsDatatable'])->name('transactions.datatable');

                // Pending deposits
                Route::get('/deposits', [WalletTransactionController::class, 'deposits'])->name('deposits');
                Route::get('/deposits/datatable', [WalletTransactionController::class, 'depositsDatatable'])->name('deposits.datatable');
                Route::post('/deposits/{id}/process', [WalletTransactionController::class, 'processDeposit'])->name('deposits.process');
            });

            // Refer & Earn — referrals & earnings
            Route::prefix('referrals')->name('referrals.')->group(function () {
                Route::get('/', [ReferralController::class, 'index'])->name('index');
                Route::get('/datatable', [ReferralController::class, 'datatable'])->name('datatable');
                Route::get('/earnings', [ReferralController::class, 'earnings'])->name('earnings');
                Route::get('/earnings/datatable', [ReferralController::class, 'earningsDatatable'])->name('earnings.datatable');
            });

            // Delivery Boy Refer & Earn
            Route::prefix('delivery-boy-referrals')->name('delivery-boy-referrals.')->group(function () {
                Route::get('/', [DeliveryBoyReferralController::class, 'index'])->name('index');
                Route::get('/datatable', [DeliveryBoyReferralController::class, 'datatableIndex'])->name('datatable');
                Route::get('/{referral}/earnings', [DeliveryBoyReferralController::class, 'earnings'])->name('earnings');
                Route::get('/{referral}/earnings/datatable', [DeliveryBoyReferralController::class, 'datatableEarnings'])->name('earnings.datatable');
            });

            Route::get('products/search', [ProductController::class, 'search'])->name('products.search');

            // delivery zones
            Route::prefix('delivery-zones')->name('delivery-zones.')->group(function () {
                Route::get('/', [DeliveryZoneController::class, 'index'])->name('index');
                Route::post('/', [DeliveryZoneController::class, 'store'])->name('store');
                Route::get('/create', [DeliveryZoneController::class, 'create'])->name('create');
                Route::get('/search', [DeliveryZoneController::class, 'search'])->name('search');
                Route::get('/{id}/edit', [DeliveryZoneController::class, 'edit'])->name('edit');
                Route::post('/{id}', [DeliveryZoneController::class, 'update'])->name('update');
                Route::delete('/{id}', [DeliveryZoneController::class, 'destroy'])->name('delete');
                Route::get('/datatable', [DeliveryZoneController::class, 'getDeliveryZones'])->name('datatable');
                Route::post('/check-exists', [DeliveryZoneController::class, 'checkExists'])->name('check_exists');
            });

            // zone preview — admin debugging tool that mirrors what a customer in a given zone sees
            Route::prefix('zones/preview')->name('zones.preview.')->group(function () {
                Route::get('/', [ZonePreviewController::class, 'index'])->name('index');
                Route::get('/home-categories', [ZonePreviewController::class, 'homeCategories'])->name('home-categories');
                Route::get('/categories', [ZonePreviewController::class, 'categories'])->name('categories');
                Route::get('/brands', [ZonePreviewController::class, 'brands'])->name('brands');
                Route::get('/banners', [ZonePreviewController::class, 'banners'])->name('banners');
                Route::get('/featured-sections', [ZonePreviewController::class, 'featuredSections'])->name('featured-sections');
                Route::get('/products', [ZonePreviewController::class, 'products'])->name('products');
            });

            // Home page builder
            Route::prefix('home-builder')->name('home-builder.')->group(function () {
                Route::get('/', [HomeBuilderController::class, 'index'])->name('index');
                Route::get('/zones-datatable', [HomeBuilderController::class, 'zonesDatatable'])->name('zones-datatable');
                Route::get('/layout', [HomeBuilderController::class, 'show'])->name('show');
                Route::post('/save', [HomeBuilderController::class, 'save'])->name('save');
                // Global ("All") home appearance — reads/writes the home_general_settings keys
                Route::get('/global-appearance', [HomeBuilderController::class, 'globalAppearanceShow'])->name('global-appearance.show');
                Route::post('/global-appearance', [HomeBuilderController::class, 'globalAppearanceSave'])->name('global-appearance.save');
                Route::post('/sections/{section}/media', [HomeBuilderController::class, 'uploadSectionMedia'])->name('section-media.store');
                Route::delete('/sections/{section}/media', [HomeBuilderController::class, 'deleteSectionMedia'])->name('section-media.delete');
                Route::post('/copy-global', [HomeBuilderController::class, 'copyGlobalToZone'])->name('copy-global');
                Route::post('/reset-zone', [HomeBuilderController::class, 'resetZoneLayout'])->name('reset-zone');
                Route::get('/revisions', [HomeBuilderController::class, 'revisions'])->name('revisions.index');
                Route::post('/revisions/{revision}/restore', [HomeBuilderController::class, 'restoreRevision'])->name('revisions.restore');
                Route::get('/presets', [HomeBuilderController::class, 'savedPresetsIndex'])->name('presets.index');
                Route::post('/presets', [HomeBuilderController::class, 'savePreset'])->name('presets.store');
                Route::delete('/presets/{preset}', [HomeBuilderController::class, 'deletePreset'])->name('presets.destroy');
            });

            // Featured Sections Routes
            Route::prefix('featured-sections')->name('featured-sections.')->group(function () {
                Route::get('/', [FeaturedSectionController::class, 'index'])->name('index');
                Route::post('/', [FeaturedSectionController::class, 'store'])->name('store');
                Route::get('/datatable', [FeaturedSectionController::class, 'getFeaturedSections'])->name('datatable');
                // Sorting routes
                Route::get('/sort', [FeaturedSectionController::class, 'sort'])->name('sort');
                Route::post('/sort', [FeaturedSectionController::class, 'updateSort'])->name('updateSort');

                Route::get('/{id}', [FeaturedSectionController::class, 'show'])->name('show');
                Route::get('/{id}/visibility', [FeaturedSectionController::class, 'visibility'])->name('visibility');
                Route::post('/{id}', [FeaturedSectionController::class, 'update'])->name('update');
                Route::delete('/{id}', [FeaturedSectionController::class, 'destroy'])->name('destroy');
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

            Route::prefix('app-notifications')->name('app-notifications.')->group(function () {
                Route::get('/', [AppNotificationController::class, 'index'])->name('index');
                Route::post('/', [AppNotificationController::class, 'store'])->name('store');
                Route::get('/datatable', [AppNotificationController::class, 'datatable'])->name('datatable');
                Route::get('/search-recipients', [AppNotificationController::class, 'searchRecipients'])->name('search-recipients');
                Route::get('/search-targets', [AppNotificationController::class, 'searchTargets'])->name('search-targets');
                Route::get('/{id}', [AppNotificationController::class, 'show'])->name('show');
            });

            // Delivery Boys Routes
            Route::prefix('delivery-boys')->name('delivery-boys.')->group(function () {
                Route::get('/live-tracking', [DeliveryBoyController::class, 'liveTracking'])->name('live-tracking');
                Route::get('/live-tracking/data', [DeliveryBoyController::class, 'liveTrackingData'])->name('live-tracking.data');
                Route::get('/', [DeliveryBoyController::class, 'index'])->name('index');
                Route::get('/reviews', [ReviewManagementController::class, 'index'])->defaults('context', 'delivery-boys')->name('reviews.index');
                Route::get('/reviews/datatable', [ReviewManagementController::class, 'datatable'])->defaults('context', 'delivery-boys')->name('reviews.datatable');
                Route::delete('/reviews/{review}', [ReviewManagementController::class, 'destroy'])->defaults('context', 'delivery-boys')->name('reviews.destroy');
                Route::get('/datatable', [DeliveryBoyController::class, 'getDeliveryBoys'])->name('datatable');
                Route::get('search', [DeliveryBoyController::class, 'search'])->name('search');
                Route::get('/{id}', [DeliveryBoyController::class, 'show'])->name('show');
                Route::get('/{id}/assignments-datatable', [DeliveryBoyController::class, 'assignmentsDatatable'])->name('assignments-datatable');
                Route::get('/{id}/wallet-datatable', [DeliveryBoyController::class, 'walletDatatable'])->name('wallet-datatable');
                Route::get('/{id}/feedback-datatable', [DeliveryBoyController::class, 'feedbackDatatable'])->name('feedback-datatable');
                Route::post('/{id}/verification-status', [DeliveryBoyController::class, 'updateVerificationStatus'])->name('update-verification-status');
                Route::post('/{id}/block', [DeliveryBoyController::class, 'block'])->name('block');
                Route::post('/{id}/unblock', [DeliveryBoyController::class, 'unblock'])->name('unblock');
                Route::delete('/{id}', [DeliveryBoyController::class, 'destroy'])->name('destroy');
            });

            // Delivery Boy Earnings Routes
            Route::prefix('delivery-boy-earnings')->name('delivery-boy-earnings.')->group(function () {
                Route::get('/', [DeliveryBoyEarningController::class, 'index'])->name('index');
                Route::get('/datatable', [DeliveryBoyEarningController::class, 'getEarnings'])->name('datatable');
                Route::post('/{id}/process-payment', [DeliveryBoyEarningController::class, 'processPayment'])->name('process-payment');
                Route::get('/history', [DeliveryBoyEarningController::class, 'history'])->name('history');
                Route::get('/history/datatable', [DeliveryBoyEarningController::class, 'getPaymentHistory'])->name('history.datatable');
            });

            // Delivery Boy Cash Collection Routes
            Route::prefix('delivery-boy-cash-collections')->name('delivery-boy-cash-collections.')->group(function () {
                Route::get('/', [DeliveryBoyCashCollectionController::class, 'index'])->name('index');
                Route::get('/datatable', [DeliveryBoyCashCollectionController::class, 'getCashCollections'])->name('datatable');
                Route::post('/{id}/process-submission', [DeliveryBoyCashCollectionController::class, 'processCashSubmission'])->name('process-submission');
                Route::get('/history', [DeliveryBoyCashCollectionController::class, 'history'])->name('history');
                Route::get('/history/datatable', [DeliveryBoyCashCollectionController::class, 'getCashSubmissionHistory'])->name('history.datatable');
            });

            // Delivery Boy Withdrawal Routes
            Route::prefix('delivery-boy-withdrawals')->name('delivery-boy-withdrawals.')->group(function () {
                Route::get('/', [DeliveryBoyWithdrawalController::class, 'index'])->name('index');
                Route::get('/datatable', [DeliveryBoyWithdrawalController::class, 'getWithdrawalRequests'])->name('datatable');
                Route::post('/{id}/process', [DeliveryBoyWithdrawalController::class, 'processWithdrawalRequest'])->name('process');
                Route::get('/history', [DeliveryBoyWithdrawalController::class, 'history'])->name('history');
                Route::get('/history/datatable', [DeliveryBoyWithdrawalController::class, 'getWithdrawalHistory'])->name('history.datatable');
                Route::get('/{id}', [DeliveryBoyWithdrawalController::class, 'show'])->name('show');
            });

            // Seller Withdrawal Routes
            Route::prefix('seller-withdrawals')->name('seller-withdrawals.')->group(function () {
                Route::get('/', [SellerWithdrawalController::class, 'index'])->name('index');
                Route::get('/datatable', [SellerWithdrawalController::class, 'getWithdrawalRequests'])->name('datatable');
                Route::post('/{id}/process', [SellerWithdrawalController::class, 'processWithdrawalRequest'])->name('process');
                Route::get('/history', [SellerWithdrawalController::class, 'history'])->name('history');
                Route::get('/history/datatable', [SellerWithdrawalController::class, 'getWithdrawalHistory'])->name('history.datatable');
                Route::get('/{id}', [SellerWithdrawalController::class, 'show'])->name('show');
            });

            // Commission Settlement Routes
            Route::prefix('commissions')->name('commissions.')->group(function () {
                Route::get('/', [SellerEarningController::class, 'index'])->name('index');
                // Credits
                Route::get('/datatable', [SellerEarningController::class, 'getUnsettledCommissions'])->name('datatable');
                Route::post('/{id}/settle', [SellerEarningController::class, 'settleCommission'])->name('settle');
                Route::post('/settle-all', [SellerEarningController::class, 'settleAllCommissions'])->name('settle-all');
                // Debits
                Route::get('/debits/datatable', [SellerEarningController::class, 'getUnsettledDebits'])->name('debits.datatable');
                Route::post('/debits/{id}/settle', [SellerEarningController::class, 'settleDebit'])->name('debits.settle');
                Route::post('/debits/settle-all', [SellerEarningController::class, 'settleAllDebits'])->name('debits.settle-all');
                // History
                Route::get('/history', [SellerEarningController::class, 'history'])->name('history');
                Route::get('/history/datatable', [SellerEarningController::class, 'getSettledCommissions'])->name('history.datatable');
            });

            // orders
            Route::prefix('orders')->name('orders.')->group(function () {
                Route::get('/', [OrderController::class, 'index'])->name('index');
                Route::get('/datatable', [OrderController::class, 'getOrders'])->name('datatable');
                Route::get('/list-datatable', [OrderController::class, 'getOrdersGrouped'])->name('list_datatable');
                Route::get('/list-items/{id}', [OrderController::class, 'getOrderItemsForExpand'])->name('list_items');
                Route::get('invoice', [OrderController::class, 'orderInvoice'])->name('invoice');
                Route::get('/{id}', [OrderController::class, 'show'])->name('show');
                // Phase 3 — admin overrides (declared before the {status} catch-all).
                Route::post('/{id}/force-cancel', [OrderController::class, 'adminForceCancel'])->name('force_cancel');
                //            Route::post('/{id}/force-refund', [OrderController::class, 'adminForceRefund'])->name('force_refund');
                Route::post('/{id}/reassign-rider', [OrderController::class, 'adminReassignRider'])->name('reassign_rider');
                Route::post('/{id}/note', [OrderController::class, 'adminAddNote'])->name('add_note');
                // Settle rider earnings on a CANCELLED_BY_ADMIN assignment. {assignmentId}
                // is the DeliveryBoyAssignment row id (not the order id).
                Route::post('/assignments/{assignmentId}/settle-rider-earnings', [OrderController::class, 'adminSettleRiderEarnings'])->name('settle_rider_earnings');
                Route::post('/items/{itemId}/update-status', [OrderController::class, 'adminUpdateItemStatus'])->name('item_update_status');
                Route::post('/{id}/items/bulk-update-status', [OrderController::class, 'adminBulkUpdateItemStatus'])->name('items_bulk_update_status');
                Route::post('/{id}/mark-payment-received', [OrderController::class, 'adminMarkPaymentReceived'])->name('mark_payment_received');
                Route::get('/{id}/live-tracking', [OrderController::class, 'liveTracking'])->name('live_tracking');
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
                Route::post('/{id}/force-cancel', [OrderController::class, 'adminForceCancel'])->name('force_cancel');
                Route::post('/{id}/reassign-rider', [OrderController::class, 'adminReassignRider'])->name('reassign_rider');
                Route::post('/{id}/note', [OrderController::class, 'adminAddNote'])->name('add_note');
                Route::post('/assignments/{assignmentId}/settle-rider-earnings', [OrderController::class, 'adminSettleRiderEarnings'])->name('settle_rider_earnings');
                Route::post('/items/{itemId}/update-status', [OrderController::class, 'adminUpdateItemStatus'])->name('item_update_status');
                Route::post('/{id}/items/bulk-update-status', [OrderController::class, 'adminBulkUpdateItemStatus'])->name('items_bulk_update_status');
                Route::post('/{id}/mark-payment-received', [OrderController::class, 'adminMarkPaymentReceived'])->name('mark_payment_received');
                Route::get('/{id}/live-tracking', [OrderController::class, 'liveTracking'])->name('live_tracking');
                Route::post('/{id}/{status}', [OrderController::class, 'updateStatus'])->name('update_status');
            });

            // order returns (admin moderation — acts on behalf of seller)
            Route::prefix('order-returns')->name('returns.')->group(function () {
                Route::get('/', [AdminReturnRequestController::class, 'index'])->name('index');
                Route::get('/datatable', [AdminReturnRequestController::class, 'datatable'])->name('datatable');

                // Phase 4 - Disputed Returns
                Route::get('/disputed', [AdminReturnRequestController::class, 'disputedList'])->name('disputed');
                Route::get('/disputed-datatable', [AdminReturnRequestController::class, 'disputedDatatable'])->name('disputed-datatable');
                Route::get('/{id}/dispute', [AdminReturnRequestController::class, 'disputeDetails'])->whereNumber('id')->name('dispute');
                Route::put('/{id}/resolve', [AdminReturnRequestController::class, 'resolveDispute'])->whereNumber('id')->name('resolve');

                Route::get('/{id}', [AdminReturnRequestController::class, 'show'])->whereNumber('id')->name('show');
                Route::post('/{id}/decision', [AdminReturnRequestController::class, 'decision'])->whereNumber('id')->name('decision');
                Route::post('/{id}/assign-rider', [AdminReturnRequestController::class, 'assignRider'])->whereNumber('id')->name('assign-rider');
            });

            // Phase 4 Aliases to support exact user request URLs
            Route::get('/returns/disputed', [AdminReturnRequestController::class, 'disputedList'])->name('returns.disputed.alias');
            Route::get('/returns/{id}/dispute', [AdminReturnRequestController::class, 'disputeDetails'])->whereNumber('id')->name('returns.dispute.alias');
            Route::put('/returns/{id}/resolve', [AdminReturnRequestController::class, 'resolveDispute'])->whereNumber('id')->name('returns.resolve.alias');

            // Return Reasons (Global Admin Management)
            Route::get('return-reasons/datatable', [ReturnReasonController::class, 'datatable'])->name('return-reasons.datatable');
            Route::resource('return-reasons', ReturnReasonController::class)->except(['create', 'edit']);

            // Return Rejection Reasons (Admin Management)
            Route::get('return-rejection-reasons/datatable', [ReturnRejectionReasonController::class, 'datatable'])->name('return-rejection-reasons.datatable');
            Route::resource('return-rejection-reasons', ReturnRejectionReasonController::class)->except(['create', 'edit']);

            // products
            Route::prefix('products')->name('products.')->group(function () {
                Route::get('/', [ProductController::class, 'index'])->name('index');
                Route::get('/reviews', [ReviewManagementController::class, 'index'])->defaults('context', 'products')->name('reviews.index');
                Route::get('/reviews/datatable', [ReviewManagementController::class, 'datatable'])->defaults('context', 'products')->name('reviews.datatable');
                Route::delete('/reviews/{review}', [ReviewManagementController::class, 'destroy'])->defaults('context', 'products')->name('reviews.destroy');
                Route::get('/datatable', [ProductController::class, 'getProducts'])->name('datatable');
                Route::get('/search', [ProductController::class, 'search'])->name('search');
                Route::get('/attributes-by-seller', [ProductController::class, 'getAttributesBySeller'])->name('attributes-by-seller');
                Route::get('/download-template', [ProductController::class, 'downloadTemplate'])->name('download-template');
                // Admin acts on behalf of a seller (seller picked via in-form Tom Select).
                Route::get('/create', [ProductController::class, 'create'])->name('create');
                Route::post('/generate-ai-description', [AiProductDescriptionController::class, 'generate'])->name('generate-ai-description');
                Route::post('/', [ProductController::class, 'store'])->name('store');
                Route::get('/{id}/edit', [ProductController::class, 'edit'])->whereNumber('id')->name('edit');
                Route::get('/{id}/visibility', [ProductController::class, 'visibility'])->whereNumber('id')->name('visibility');
                Route::post('/{id}', [ProductController::class, 'update'])->whereNumber('id')->name('update');
                Route::delete('/{id}', [ProductController::class, 'destroy'])->whereNumber('id')->name('delete');
                Route::get('/{id}/pricing', [ProductController::class, 'getProductPricing'])->name('pricing');
                Route::post('/{id}/verification-status', [ProductController::class, 'updateVerificationStatus'])->name('update-verification-status');
                Route::post('/{id}/update-status', [ProductController::class, 'updateStatus'])->name('update-status');
                Route::get('/{id}', [ProductController::class, 'show'])->whereNumber('id')->name('show');
            });

            Route::get('/stores/list', [StoreController::class, 'StoreList'])->name('stores.list');

            // product Faqs
            Route::prefix('product-faqs')->name('product_faqs.')->group(function () {
                Route::get('/', [ProductFaqController::class, 'index'])->name('index');
                Route::get('/datatable', [ProductFaqController::class, 'getProductFaqs'])->name('datatable');
                //            Route::get('/search', [ProductFaqController::class, 'search'])->name('search');
            });

            // dispatch management
            Route::prefix('dispatch')->name('dispatch.')->group(function () {
                Route::get('/', [DispatchController::class, 'index'])->name('index');
                Route::get('/stats', [DispatchController::class, 'stats'])->name('stats');
                Route::get('/riders-on-delivery', [DispatchController::class, 'ridersOnDelivery'])->name('riders-on-delivery');
                Route::get('/unassigned-orders', [DispatchController::class, 'unassignedOrders'])->name('unassigned-orders');
                Route::get('/ready-for-pickup', [DispatchController::class, 'readyForPickup'])->name('ready-for-pickup');
            });

            // cron monitor
            Route::prefix('cron-monitor')->name('cron-monitor.')->group(function () {
                Route::get('/', [CronMonitorController::class, 'index'])->name('index');
                Route::get('/status', [CronMonitorController::class, 'status'])->name('status');
                Route::post('/run', [CronMonitorController::class, 'run'])->name('run');
                Route::get('/history', [CronMonitorController::class, 'history'])->name('history');
            });

            // ad campaigns — approval & moderation
            Route::prefix('ads/campaigns')->name('ads.campaigns.')->group(function () {
                Route::get('/', [AdminAdCampaignController::class, 'index'])->name('index');
                Route::get('/datatable', [AdminAdCampaignController::class, 'getCampaigns'])->name('datatable');
                Route::get('/dashboard', [AdminAdCampaignController::class, 'dashboard'])->name('dashboard');
                Route::get('/dashboard/data', [AdminAdCampaignController::class, 'getDashboardData'])->name('dashboard.data');
                Route::get('/topups', [AdminAdCampaignController::class, 'topups'])->name('topups');
                Route::get('/topups/datatable', [AdminAdCampaignController::class, 'topupsDatatable'])->name('topups.datatable');
                Route::get('/topup-transactions', [AdminAdCampaignController::class, 'topupTransactions'])->name('topup-transactions');
                Route::get('/topup-transactions/datatable', [AdminAdCampaignController::class, 'topupTransactionsDatatable'])->name('topup-transactions.datatable');
                Route::get('/topup-transactions/history', [AdminAdCampaignController::class, 'topupTransactionHistory'])->name('topup-transactions.history');
                Route::get('/topup-transactions/history/datatable', [AdminAdCampaignController::class, 'topupTransactionHistoryDatatable'])->name('topup-transactions.history.datatable');
                Route::post('/topups/{id}/process', [AdminAdCampaignController::class, 'processTopup'])->name('topups.process');
                Route::post('/{id}/action', [AdminAdCampaignController::class, 'action'])->name('action');
            });
        });
    });
