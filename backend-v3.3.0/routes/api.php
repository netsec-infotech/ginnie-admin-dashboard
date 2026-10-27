<?php

use App\Http\Controllers\Api\BannerApiController;
use App\Http\Controllers\Api\BlogApiController;
use App\Http\Controllers\Api\BrandApiController;
use App\Http\Controllers\Api\CategoryApiController;
use App\Http\Controllers\Api\DeliveryZoneApiController;
use App\Http\Controllers\Api\FaqApiController;
use App\Http\Controllers\Api\FeaturedSectionApiController;
use App\Http\Controllers\Api\HomeLayoutApiController;
use App\Http\Controllers\Api\HomeNavbarApiController;
use App\Http\Controllers\Api\LanguageApiController;
use App\Http\Controllers\Api\OnboardingScreenApiController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\Product\ProductApiController;
use App\Http\Controllers\Api\Product\ProductFaqApiController;
use App\Http\Controllers\Api\Product\ProductSidebarApiController;
use App\Http\Controllers\Api\Seller\SubscriptionPlanApiController;
use App\Http\Controllers\Api\SellerFeedbackApiController;
use App\Http\Controllers\Api\SettingApiController;
use App\Http\Controllers\Api\SplashScreenApiController;
use App\Http\Controllers\Api\StoreApiController;
use App\Http\Controllers\Api\User\AddressApiController;
use App\Http\Controllers\Api\User\AdEventApiController;
use App\Http\Controllers\Api\User\AuthApiController;
use App\Http\Controllers\Api\User\CartApiController;
use App\Http\Controllers\Api\User\DeliveryTimeSlotApiController;
use App\Http\Controllers\Api\User\LoyaltyPointApiController;
use App\Http\Controllers\Api\User\OrderApiController;
use App\Http\Controllers\Api\User\OtpApiController;
use App\Http\Controllers\Api\User\ProductReviewApiController;
use App\Http\Controllers\Api\User\PromoApiController;
use App\Http\Controllers\Api\User\ReferralApiController;
use App\Http\Controllers\Api\User\ScheduledOrderRecoveryController;
use App\Http\Controllers\Api\User\SharedCartApiController;
use App\Http\Controllers\Api\User\SupportApiController;
use App\Http\Controllers\Api\User\UserApiController;
use App\Http\Controllers\Api\User\UserNotificationApiController;
use App\Http\Controllers\SupportBroadcastAuthController;
use App\Http\Controllers\Api\User\WalletApiController;
use App\Http\Controllers\Api\User\WishlistApiController;
use App\Http\Controllers\Api\WatchBuy\WatchBuyConfigApiController;
use App\Http\Controllers\Api\WatchBuy\WatchBuyLikeApiController;
use App\Http\Controllers\Api\WatchBuy\WatchBuyReelApiController;
use App\Http\Controllers\Api\WatchBuy\WatchBuySeenApiController;
use App\Http\Controllers\Api\WatchBuy\WatchBuyStatusApiController;
use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\Payments\FlutterwaveController;
use App\Http\Controllers\Payments\MercadoPagoController;
use App\Http\Controllers\Payments\PaystackController;
use App\Http\Controllers\Payments\RazorpayController;
use App\Http\Controllers\Payments\StripeController;
use App\Http\Controllers\Payments\XenditController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\get;

require __DIR__ . '/delivery-boy-api.php';
require __DIR__ . '/seller-api.php';

Route::get('/languages', [LanguageApiController::class, 'languages'])->name('api.languages.index');
Route::get('/language-labels', [LanguageApiController::class, 'labels'])->name('api.language-labels.index');

// User Auth Routes
Route::post('register', [AuthApiController::class, 'register'])->name('register');
Route::post('login', [AuthApiController::class, 'login'])->name('login');
Route::post('forget-password', [PasswordResetController::class, 'sendResetLinkEmail'])->name('password');
Route::post('verify-user', [AuthApiController::class, 'verifyUser']);
Route::post('auth/send-otp', [OtpApiController::class, 'sendOtp'])->name('send-otp');
Route::post('auth/verify-otp', [OtpApiController::class, 'verifyOtp'])->name('verify-otp');

Route::post('auth/google/callback', [AuthApiController::class, 'googleCallback'])->name('google-callback');
Route::post('auth/apple/callback', [AuthApiController::class, 'appleCallback'])->name('apple-callback');
Route::post('auth/phone/callback', [AuthApiController::class, 'phoneCallback'])->name('phone-callback');

// settings
Route::prefix('settings')->name('api.')->group(function () {
    Route::get('/', [SettingApiController::class, 'index'])->name('settings.index');
    Route::get('firebase-config', [SettingApiController::class, 'firebaseConfig'])->name('settings.firebase-config');
    Route::get('check-version', [SettingApiController::class, 'checkVersion'])->name('settings.check-version');
    Route::get('/variables', [SettingApiController::class, 'settingVariables'])->name('settings.variables');
    Route::get('/{setting}', [SettingApiController::class, 'show'])->name('settings.show');
});
// Subscription Plans (public)
Route::prefix('subscription')->group(function () {
    Route::get('/plans', [SubscriptionPlanApiController::class, 'index']);
});

// Scheduled Delivery (public store discovery)
Route::prefix('scheduled-delivery/stores/{store}')->group(function () {
    Route::get('/dates', [DeliveryTimeSlotApiController::class, 'getDatesForStore']);
    Route::get('/slots', [DeliveryTimeSlotApiController::class, 'index']);
});

// Customer Support attachment route (supports signed temporary URLs for Flutter / browser <img> tags and authenticated sessions)
Route::get('user/support/attachments/{media}', [SupportApiController::class, 'attachment'])
    ->name('user.support.attachments.show');

Route::get('user/cart/shared/{token}', [SharedCartApiController::class, 'show'])
    ->middleware(['catalog.locale', 'throttle:share-cart-preview'])
    ->name('user.cart.shared.show');

Route::middleware('auth:sanctum')->group(function () {
    // Real-time broadcasting authorization for customer app / web
    Route::post('broadcasting/auth', SupportBroadcastAuthController::class)
        ->name('user.support.broadcasting.auth');

    // logout
    Route::post('logout', [AuthApiController::class, 'logout']);
    Route::post('devices/sync', [DeviceTokenController::class, 'sync']);
    Route::delete('devices', [DeviceTokenController::class, 'forget']);

    // Alias for order creation at /api/orders (in addition to /api/user/orders)
    // This keeps backward compatibility with tests expecting POST /api/orders
    Route::post('orders', [OrderApiController::class, 'createOrder']);

    // users routes
    Route::prefix('user')->name('user.')->group(function () {
        // Customer Support Live Chat API
        Route::prefix('support')->name('support.')->group(function () {
            Route::get('thread', [SupportApiController::class, 'thread'])->name('thread');
            Route::get('thread/messages', [SupportApiController::class, 'messages'])->name('thread.messages');
            Route::get('thread/updates', [SupportApiController::class, 'updates'])->name('thread.updates');
            Route::get('realtime', [SupportApiController::class, 'realtime'])->name('realtime');
            Route::get('topics', [SupportApiController::class, 'topics'])->name('topics');
            Route::post('sessions', [SupportApiController::class, 'storeSession'])->middleware('throttle:10,1,support-sessions:')->name('sessions.store');
            Route::post('sessions/{slug}/messages', [SupportApiController::class, 'storeMessage'])->middleware('throttle:60,1,support-messages:')->name('messages.store');
            Route::post('sessions/{slug}/typing', [SupportApiController::class, 'typing'])->middleware('throttle:120,1,support-typing:')->name('typing');
            Route::post('sessions/{slug}/read', [SupportApiController::class, 'markRead'])->name('read');
            Route::post('sessions/{slug}/callbacks', [SupportApiController::class, 'callback'])->middleware('throttle:5,1,support-callbacks:')->name('callbacks.store');
            Route::post('sessions/{slug}/resolve', [SupportApiController::class, 'resolve'])->middleware('throttle:10,1,support-resolve:')->name('resolve');
            Route::post('sessions/{slug}/rating', [SupportApiController::class, 'rating'])->middleware('throttle:10,1,support-ratings:')->name('rating');
        });

        // delete user account
        Route::delete('/delete-account', [UserApiController::class, 'deleteAccount'])->name('delete-account');
        // change password
        Route::post('/change-password', [UserApiController::class, 'changePassword'])->name('change-password');

        // User profile
        Route::apiResource('/addresses', AddressApiController::class);

        // profile
        Route::get('/profile', [UserApiController::class, 'getProfile']);
        Route::post('/profile', [UserApiController::class, 'updateProfile']);

        // email update + re-verification
        Route::post('/update-email', [UserApiController::class, 'updateEmail'])->name('update-email');
        Route::post('/email/verification-notification', [UserApiController::class, 'resendEmailVerification'])
            ->middleware('throttle:6,1')
            ->name('verification.send');

        // Notifications (app user)
        Route::prefix('notifications')->group(function () {
            Route::get('/', [UserNotificationApiController::class, 'index']);
            Route::get('/unread-count', [UserNotificationApiController::class, 'unreadCount']);
            Route::post('/mark-all-read', [UserNotificationApiController::class, 'markAllAsRead']);
            Route::post('/{id}/read', [UserNotificationApiController::class, 'markAsRead']);
            Route::post('/{id}/unread', [UserNotificationApiController::class, 'markAsUnread']);
        });

        // Wallet routes
        Route::prefix('wallet')->name('wallet.')->group(function () {
            Route::get('/', [WalletApiController::class, 'getWallet']);
            Route::post('/prepare-wallet-recharge', [WalletApiController::class, 'prepareWalletRecharge']);
            Route::post('/deduct-balance', [WalletApiController::class, 'deductBalance']);
            Route::get('/transactions', [WalletApiController::class, 'getTransactions']);
            Route::get('/transactions/{id}', [WalletApiController::class, 'getTransaction']);
        });

        // Wishlist routes
        Route::prefix('wishlists')->name('wishlists.')->middleware('catalog.locale')->group(function () {
            Route::get('/', [WishlistApiController::class, 'index']);
            Route::get('/titles', [WishlistApiController::class, 'getTitles']);
            Route::post('/', [WishlistApiController::class, 'store']); // Combined create wishlist and add item
            Route::post('/create', [WishlistApiController::class, 'createWishlist']); // Create wishlist only
            Route::get('/{id}', [WishlistApiController::class, 'show']);
            Route::put('/{id}', [WishlistApiController::class, 'update']);
            Route::delete('/{id}', [WishlistApiController::class, 'destroy']);

            // Wishlist items management
            Route::delete('/items/{itemId}', [WishlistApiController::class, 'removeItem']);
            Route::put('/items/{itemId}/move', [WishlistApiController::class, 'moveItem']);
        });

        // Cart routes
        Route::prefix('cart')->middleware('catalog.locale')->group(function () {
            Route::get('/', [CartApiController::class, 'getCart']);
            Route::post('/add', [CartApiController::class, 'addToCart']);
            Route::post('/item/{cartItemId}', [CartApiController::class, 'updateCartItemQuantity']);
            Route::delete('/item/{cartItemId}', [CartApiController::class, 'removeFromCart']);
            Route::get('/item/save-for-later', [CartApiController::class, 'getSaveForLaterItems']);
            Route::post('/item/save-for-later/{cartItemId}', [CartApiController::class, 'saveForLater']);
            Route::get('/clear-cart', [CartApiController::class, 'clearCart']);
            Route::post('/sync', [CartApiController::class, 'syncCart']);
            Route::post('/tip', [CartApiController::class, 'updateCartTip']);
            Route::post('/loyalty-points', [CartApiController::class, 'updateCartLoyaltyPoints']);
            Route::post('/share', [SharedCartApiController::class, 'store'])
                ->middleware('throttle:share-cart-create')
                ->name('cart.share');
            Route::post('/shared/{token}/import', [SharedCartApiController::class, 'import'])
                ->middleware('throttle:share-cart-import')
                ->name('cart.shared.import');
        });

        // Scheduled Delivery (cart validation & discovery)
        Route::prefix('scheduled-delivery/cart')->group(function () {
            Route::get('/dates', [DeliveryTimeSlotApiController::class, 'getDatesForCart']);
            Route::get('/slots', [DeliveryTimeSlotApiController::class, 'getForCart']);
        });

        // Promo routes
        Route::prefix('promos')->group(function () {
            Route::get('/available', [PromoApiController::class, 'getUserAvailablePromos']);
            Route::get('/validate', [PromoApiController::class, 'validatePromoCode']);
        });

        // Order routes
        Route::prefix('orders')->group(function () {
            Route::get('/', [OrderApiController::class, 'getUserOrders']);
            Route::post('/', [OrderApiController::class, 'createOrder']);
            Route::post('/items/{orderItemId}/cancel', [OrderApiController::class, 'cancelOrderItem']);
            Route::get('/items/{orderItemId}/return-reasons', [OrderApiController::class, 'getItemReturnReasons']);
            Route::post('/items/{orderItemId}/return', [OrderApiController::class, 'returnOrderItem']);
            Route::post('/items/{orderItemId}/return-cancel', [OrderApiController::class, 'cancelReturnRequest']);
            Route::get('/{orderSlug}', [OrderApiController::class, 'getOrder']);
            Route::get('/{orderSlug}/delivery-boy-location', [OrderApiController::class, 'getOrderDeliveryBoyLocation']);
            Route::post('/{orderSlug}/tip', [OrderApiController::class, 'addOrderTip']);
            // Reorder: add all items from a past order back to cart
            Route::post('/{orderId}/reorder', [OrderApiController::class, 'reorder']);

            // Scheduled Order Recovery: reschedule or cancel flagged orders
            Route::prefix('{orderId}/recovery')->group(function () {
                Route::get('/slots', [ScheduledOrderRecoveryController::class, 'slots']);
                Route::post('/reschedule', [ScheduledOrderRecoveryController::class, 'reschedule']);
                Route::post('/cancel', [ScheduledOrderRecoveryController::class, 'cancel']);
            });
        });

        // Order transaction routes
        Route::prefix('order-transactions')->name('transactions.')->group(function () {
            Route::get('/', [OrderApiController::class, 'getTransactions']);
            Route::get('/{id}', [OrderApiController::class, 'getTransaction']);
        });

        Route::get('payment-transactions/{transactionUuid}/status', [OrderApiController::class, 'getPaymentTransactionStatus']);

        // Referral routes
        Route::prefix('referral')->name('referral.')->group(function () {
            Route::get('/', [ReferralApiController::class, 'getReferralInfo'])->name('info');
            // Route::post('/apply', [ReferralApiController::class, 'applyReferralCode'])->name('apply');
            Route::post('/submit', [ReferralApiController::class, 'submit'])->name('submit');
            Route::get('/earnings', [ReferralApiController::class, 'getEarningsHistory'])->name('earnings');
        });

        // Loyalty points routes
        Route::prefix('loyalty-points')->name('loyalty-points.')->group(function () {
            Route::get('/transactions', [LoyaltyPointApiController::class, 'transactions'])->name('transactions');
        });
    });
});

// reviews
Route::prefix('reviews')->middleware('catalog.locale')->group(function () {
    Route::get('/', [ProductReviewApiController::class, 'index']);
    Route::post('/', [ProductReviewApiController::class, 'store'])->middleware('auth:sanctum');
    Route::get('/available-for-review', [ProductReviewApiController::class, 'getAvailableProductsForReview'])->middleware('auth:sanctum');
    Route::get('/{id}', [ProductReviewApiController::class, 'show']);
    Route::post('/{id}', [ProductReviewApiController::class, 'update'])->middleware('auth:sanctum');
    Route::delete('/{id}', [ProductReviewApiController::class, 'destroy'])->middleware('auth:sanctum');
});

// seller feedback
Route::prefix('seller-feedback')->group(function () {
    Route::get('/', [SellerFeedbackApiController::class, 'index']);
    Route::post('/', [SellerFeedbackApiController::class, 'store'])->middleware('auth:sanctum');
    Route::get('/ratings', [SellerFeedbackApiController::class, 'getSellerRatings']);
    Route::get('/{id}', [SellerFeedbackApiController::class, 'show']);
    Route::post('/{id}', [SellerFeedbackApiController::class, 'update'])->middleware('auth:sanctum');
    Route::delete('/{id}', [SellerFeedbackApiController::class, 'destroy'])->middleware('auth:sanctum');
});

// get banners
Route::get('banners', [BannerApiController::class, 'index']);

// get categories
Route::prefix('categories')->name('categories.')->middleware('catalog.locale')->group(function () {
    Route::get('/', [CategoryApiController::class, 'index']);
    Route::get('/sub-categories', [CategoryApiController::class, 'subCategories']);
    Route::get('/sidebar', [CategoryApiController::class, 'getCategories']);
    Route::get('/search', [CategoryApiController::class, 'search'])->name('search');
});

// Custom Navbar Categories (HomeNavbarItems)
Route::get('home-navbar-items', [HomeNavbarApiController::class, 'index'])->name('home-navbar-items.index');

// get brands
Route::prefix('brands')->name('brands.')->middleware('catalog.locale')->group(function () {
    Route::get('/', [BrandApiController::class, 'index']);
    Route::get('/search', [BrandApiController::class, 'search'])->name('search');
    Route::get('/sidebar', [BrandApiController::class, 'sidebarBrands']);
});

// products
Route::prefix('products')->name('products.')->middleware('catalog.locale')->group(function () {
    //    Route::get('/', [ProductApiController::class, 'getAllProduct']);
    Route::get('/sidebar-filters', [ProductSidebarApiController::class, 'filters'])->name('products.sidebar-filters');
    Route::get('/get-types', [ProductSidebarApiController::class, 'getTypes'])->name('products.get-types');
    Route::get('/search-by-keywords', [ProductApiController::class, 'searchByKeywords']);
    Route::get('/store-wise', [ProductApiController::class, 'storeWise']);
    Route::get('/search', [ProductApiController::class, 'search'])->name('search');
    Route::get('/{slug}', [ProductApiController::class, 'show']);
    Route::get('/{slug}/faqs', [ProductFaqApiController::class, 'getByProduct']);
    Route::get('/{slug}/reviews', [ProductReviewApiController::class, 'getProductReviews']);
});

// stores
Route::prefix('stores')->name('stores.')->group(function () {
    Route::get('/', [StoreApiController::class, 'index']);
    Route::get('/search', [StoreApiController::class, 'search'])->name('search');
    Route::post('/{id}/notify-me', [StoreApiController::class, 'setNotification'])->middleware('auth:sanctum')->name('notify-me');
    Route::get('/{slug}', [StoreApiController::class, 'show']);
    //    Route::get('/zone', [StoreApiController::class, 'getStoresByZone']);
});

// delivery zone check
Route::prefix('delivery-zone')->name('delivery_zone.')->group(function () {
    Route::get('/', [DeliveryZoneApiController::class, 'index']);
    Route::get('/check', [DeliveryZoneApiController::class, 'checkDelivery']);
    Route::get('/stores', [StoreApiController::class, 'getStoresByLocation']);
    Route::get('/products', [ProductApiController::class, 'index'])->middleware('catalog.locale');
    Route::get('/search', [DeliveryZoneApiController::class, 'search'])->name('search');
    Route::get('/{id}', [DeliveryZoneApiController::class, 'show']);
});
Route::post('stores/map', [DeliveryZoneApiController::class, 'storesByMap']);

// Ad event tracking (public — guests also see sponsored products)
Route::prefix('ads')->name('ads.')->group(function () {
    Route::post('/bulk-clicks', [AdEventApiController::class, 'bulkClicks'])->name('bulk_clicks');
    Route::post('/bulk-impressions', [AdEventApiController::class, 'bulkImpressions'])->name('bulk_impressions');
});

// get faqs
Route::prefix('faqs')->name('faqs.')->group(function () {
    Route::get('/', [FaqApiController::class, 'index']);
    Route::get('/{id}', [FaqApiController::class, 'show']);
});

// get product faqs
Route::prefix('product-faqs')->name('product_faqs.')->middleware('catalog.locale')->group(function () {
    Route::get('/', [ProductFaqApiController::class, 'index']);
    Route::get('/{id}', [ProductFaqApiController::class, 'show']);
});

// featured sections
Route::prefix('featured-sections')->name('featured-sections.')->middleware('catalog.locale')->group(function () {
    Route::get('/', [FeaturedSectionApiController::class, 'index'])->name('index');
    Route::get('/search', [FeaturedSectionApiController::class, 'search'])->name('search');
    Route::get('/all', [FeaturedSectionApiController::class, 'all'])->name('all');
    Route::get('/types', [FeaturedSectionApiController::class, 'types'])->name('types');
    Route::get('/{slug}', [FeaturedSectionApiController::class, 'show'])->name('show');
    Route::get('/{slug}/products', [FeaturedSectionApiController::class, 'products'])->name('products');
});

Route::middleware('catalog.locale')->group(function () {
    Route::get('/home-layout', [HomeLayoutApiController::class, 'index'])->name('home-layout');
    Route::get('/home-layout/sections/{section}', [HomeLayoutApiController::class, 'section'])->name('home-layout.section');
});
Route::get('/onboarding-screens', [OnboardingScreenApiController::class, 'index'])->name('onboarding-screens.index');
// Dynamic mobile splash config returns current and optional staged media.
Route::get('/splash-config', [SplashScreenApiController::class, 'index'])->name('splash-config.index');

Route::get('payment/variables', [PaymentController::class, 'paymentVariables']);

// razorpay routes
Route::prefix('razorpay')->group(function () {
    Route::post('create-order', [RazorpayController::class, 'createOrder'])->middleware('auth:sanctum');
    // Route::get('payment/{paymentId}', [RazorpayController::class, 'getPaymentDetails']);
});
Route::post('/webhook/razorpay', [RazorpayController::class, 'handleWebhook'])->name('razorpay.webhook');

// stripe routes
Route::post('stripe/create-order', [StripeController::class, 'createOrderPaymentIntent'])->middleware('auth:sanctum');
Route::post('stripe/webhook', [StripeController::class, 'handleWebhook'])->name('stripe.webhook');
Route::post('stripe/refund-payment', [StripeController::class, 'refundPayment']);

// paystack routes
Route::post('paystack/create-order', [PaystackController::class, 'createOrderPaymentIntent'])->middleware('auth:sanctum');
Route::post('paystack/webhook', [PaystackController::class, 'handleWebhook'])->name('paystack.webhook');
Route::get('paystack/callback', [PaystackController::class, 'handleCallback'])->name('paystack.callback');
Route::post('paystack/refund', [PaystackController::class, 'refundPayment']);

// flutterwave routes
Route::post('flutterwave/post-tip', [FlutterwaveController::class, 'createTipPaymentIntent'])->middleware('auth:sanctum');
Route::post('flutterwave/webhook', [FlutterwaveController::class, 'handleWebhook'])->name('flutterwave.webhook');

// xendit routes
Route::post('xendit/post-tip', [XenditController::class, 'createTipPaymentIntent'])->middleware('auth:sanctum');
Route::post('xendit/create-order', [XenditController::class, 'createOrderPaymentIntent'])->middleware('auth:sanctum');
Route::post('xendit/webhook', [XenditController::class, 'handleWebhook'])->name('xendit.webhook');
Route::post('xendit/refund-payment', [XenditController::class, 'refundPayment']);

// mercadopago routes
Route::post('mercadopago/post-tip', [MercadoPagoController::class, 'createTipPaymentIntent'])->middleware('auth:sanctum');
Route::post('mercadopago/create-order', [MercadoPagoController::class, 'createOrderPaymentIntent'])->middleware('auth:sanctum');
Route::post('mercadopago/webhook', [MercadoPagoController::class, 'handleWebhook'])->name('mercadopago.webhook');
Route::post('mercadopago/refund-payment', [MercadoPagoController::class, 'refundPayment']);

Route::post('/test-fcm', [NotificationController::class, 'test']);
Route::post('/test-fcms', [NotificationController::class, 'sendBulk']);
// Route::post('test-email', [NotificationController::class, 'testEmail'])->name('test-email');

Route::get('whatsapp/webhook', [WhatsAppWebhookController::class, 'verify'])->name('whatsapp.webhook.verify');
Route::post('whatsapp/webhook', [WhatsAppWebhookController::class, 'handle'])->name('whatsapp.webhook.handle');

// Watch & Buy Customer Routes (matching Hypercommerce contract)
Route::prefix('watch-and-buy')->name('watch-and-buy.')->middleware('catalog.locale')->group(function () {
    Route::get('config', WatchBuyConfigApiController::class)->name('config');
    Route::get('reels', [WatchBuyReelApiController::class, 'feed'])->name('reels.feed');
    Route::get('reels/{reel}', [WatchBuyReelApiController::class, 'show'])->name('reels.show');
    Route::get('statuses', [WatchBuyStatusApiController::class, 'feed'])->name('statuses.feed');
    Route::get('profiles/{username}', [WatchBuyReelApiController::class, 'profile'])->name('profiles.show');
    Route::get('profiles/{username}/statuses', [WatchBuyStatusApiController::class, 'profile'])->name('profiles.statuses');

    Route::middleware('auth:sanctum')->group(function () {
        Route::put('reels/likes', [WatchBuyLikeApiController::class, 'sync'])->name('reels.likes.sync');
        Route::put('statuses/seen', [WatchBuySeenApiController::class, 'sync'])->name('statuses.seen.sync');
    });
});

// Blogs Public API
Route::get('blog-categories', [BlogApiController::class, 'categories'])->name('blog-categories.index');
Route::prefix('blogs')->name('blogs.')->middleware('catalog.locale')->group(function () {
    Route::get('/', [BlogApiController::class, 'index'])->name('index');
    Route::get('/categories', [BlogApiController::class, 'categories'])->name('categories');
    Route::get('/{slug}', [BlogApiController::class, 'show'])->name('show');
});
