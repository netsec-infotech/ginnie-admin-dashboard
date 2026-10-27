<?php

return [

    'admin' => [
        // ── OVERVIEW ──
        '_section_overview' => [
            'type' => 'section',
            'title' => 'labels.menu_section_overview',
        ],
        'dashboard' => [
            'icon' => 'ti-home',
            'route' => 'admin.dashboard',
            'title' => 'labels.dashboard',
            'active' => 'dashboard',
        ],
        'pos-dashboard' => [
            'icon' => 'ti-chart-bar',
            'route' => 'admin.pos-dashboard',
            'title' => 'labels.pos_dashboard',
            'active' => 'pos-dashboard',
            'permission' => 'pos_dashboard.view',
        ],
        'design_appearance' => [
            'icon' => 'ti-palette',
            'title' => 'labels.design_appearance',
            'active' => 'design_appearance',
            'route' => [
                'home_builder' => [
                    'sub_active' => 'home-builder',
                    'sub_route' => 'admin.home-builder.index',
                    'sub_title' => 'labels.home_builder',
                    'permission' => 'home_builder.view',
                ],
                'onboarding_screens' => [
                    'sub_active' => 'onboarding-screens',
                    'sub_route' => 'admin.onboarding-screens.index',
                    'sub_title' => 'labels.onboarding_screens',
                    'permission' => 'onboarding_screen.view',
                ],
                // Mobile splash screens use saved media previews only.
                'splash_screens' => [
                    'sub_active' => 'splash-screens',
                    'sub_route' => 'admin.splash-screens.index',
                    'sub_title' => 'labels.splash_screens',
                    'permission' => 'splash_screen.view',
                ],
            ],
        ],
        'orders' => [
            'icon' => 'ti-package',
            'route' => 'admin.orders.index',
            'title' => 'labels.orders',
            'active' => 'orders',
            'permission' => 'orders.view',
        ],
        'scheduled_orders' => [
            'icon' => 'ti-calendar-time',
            'route' => 'admin.scheduled_orders.index',
            'title' => 'labels.scheduled_orders',
            'active' => 'scheduled_orders',
            'permission' => 'orders.view',
        ],
        'returns' => [
            'icon' => 'ti-truck-return',
            'title' => 'labels.returns',
            'active' => 'returns',
            'route' => [
                'return_requests' => [
                    'sub_active' => 'returns',
                    'sub_route' => 'admin.returns.index',
                    'sub_title' => 'labels.return_requests',
                    'permission' => 'return.view',
                    'badge_key' => 'return_requests',
                ],
                'return_disputes' => [
                    'sub_active' => 'return-disputes',
                    'sub_route' => 'admin.returns.disputed',
                    'sub_title' => 'labels.return_disputes',
                    'permission' => 'return.view',
                    // 'badge_key' => 'return_disputes',
                ],
                'return_reasons' => [
                    'sub_active' => 'return-reasons',
                    'sub_route' => 'admin.return-reasons.index',
                    'sub_title' => 'labels.return_reasons',
                    'permission' => 'return.view',
                ],
                'return_rejection_reasons' => [
                    'sub_active' => 'return-rejection-reasons',
                    'sub_route' => 'admin.return-rejection-reasons.index',
                    'sub_title' => 'labels.return_rejection_reasons',
                    'permission' => 'return.view',
                ],
            ],
        ],
        'dispatch' => [
            'icon' => 'ti-radar-2',
            'route' => 'admin.dispatch.index',
            'title' => 'labels.dispatch_management',
            'active' => 'dispatch',
            'permission' => 'dispatch.view',
        ],
        // Customer Support Live Chat & Helpdesk
        'support' => [
            'icon' => 'ti-message-circle-question',
            'title' => 'labels.customer_support',
            'active' => 'support',
            'menu_gradient' => 'success',
            'route' => [
                'inbox' => [
                    'sub_active' => 'inbox',
                    'sub_route' => 'admin.support.index',
                    'sub_title' => 'labels.inbox',
                    'permission' => 'support.view',
                ],
                'dashboard' => [
                    'sub_active' => 'dashboard',
                    'sub_route' => 'admin.support.dashboard',
                    'sub_title' => 'labels.dashboard',
                    'permission' => 'support.view',
                ],
            ],
        ],

        // ── SETUP ──
        '_section_setup' => [
            'type' => 'section',
            'title' => 'labels.menu_section_setup',
        ],
        'delivery_zones' => [
            'icon' => 'ti-map-pin',
            'title' => 'Regional Settings',
            'active' => 'delivery_zones',
            'route' => [
                'index' => [
                    'sub_active' => 'delivery_zones',
                    'sub_route' => 'admin.delivery-zones.index',
                    'sub_title' => 'labels.delivery_zones',
                    'permission' => 'delivery_zone.view',
                    'badge_key' => 'delivery_zones',
                ],
                'country_management' => [
                    'sub_active' => 'country_management',
                    'sub_route' => 'admin.country-management.index',
                    'sub_title' => 'Country Management',
                    'permission' => 'delivery_zone.view',
                ],
                'zone_preview' => [
                    'sub_active' => 'zone_preview',
                    'sub_route' => 'admin.zones.preview.index',
                    'sub_title' => 'labels.zone_preview',
                    'permission' => 'zone_preview.view',
                ],
            ],
        ],
        'stores' => [
            'icon' => 'ti-building-warehouse',
            'title' => 'labels.stores',
            'active' => 'stores',
            'route' => [
                'stores' => [
                    'sub_active' => 'stores',
                    'sub_route' => 'admin.sellers.store.index',
                    'sub_title' => 'labels.stores',
                    'permission' => 'store.view',
                    'badge_key' => 'stores',
                ],
                'store_reviews' => [
                    'sub_active' => 'store_reviews',
                    'sub_route' => 'admin.sellers.store.reviews.index',
                    'sub_title' => 'labels.store_reviews',
                    'permission' => 'store.view',
                ],
            ],
        ],

        // ── CATALOG ──
        '_section_catalog' => [
            'type' => 'section',
            'title' => 'labels.menu_section_catalog',
        ],
        'categories' => [
            'icon' => 'ti-category-2',
            'title' => 'labels.categories',
            'active' => 'categories',
            'route' => [
                'categories' => [
                    'sub_active' => 'categories',
                    'sub_route' => 'admin.categories.index',
                    'sub_title' => 'labels.categories',
                    'permission' => 'category.view',
                ],
                'sort' => [
                    'sub_active' => 'sort',
                    'sub_route' => 'admin.categories.sort',
                    'sub_title' => 'labels.sort',
                    'permission' => 'category.create',
                ],
                'bulk_upload' => [
                    'sub_active' => 'bulk_upload',
                    'sub_route' => 'admin.categories.bulk-upload.page',
                    'sub_title' => 'labels.bulk_upload',
                    'permission' => 'category.create',
                ],
            ],
        ],
        'brands' => [
            'icon' => 'ti-sparkles',
            'route' => 'admin.brands.index',
            'title' => 'labels.brands',
            'active' => 'brands',
            'permission' => 'brand.view',
        ],
        'products' => [
            'icon' => 'ti-cube-spark',
            'title' => 'labels.products',
            'active' => 'products',
            'route' => [
                'products' => [
                    'sub_active' => 'products',
                    'sub_route' => 'admin.products.index',
                    'sub_title' => 'labels.products',
                    'permission' => 'product.view',
                ],
                'pending_approval_products' => [
                    'sub_active' => 'pending_approval_products',
                    'sub_route' => 'admin.products.index',
                    'route_param' => ['verification_status' => 'pending_verification'],
                    'sub_title' => 'labels.pending_approval_products',
                    'permission' => 'product.view',
                    'badge_key' => 'products',
                ],
                'bulk_upload' => [
                    'sub_active' => 'bulk_upload',
                    'sub_route' => 'admin.products.bulk-upload.page',
                    'sub_title' => 'labels.bulk_upload',
                    'permission' => 'product.create',
                ],
                'badges' => [
                    'sub_active' => 'badges',
                    'sub_route' => 'admin.badges.index',
                    'sub_title' => 'labels.badges',
                    'permission' => 'badge.view',
                ],
                'product_faqs' => [
                    'sub_active' => 'product_faqs',
                    'sub_route' => 'admin.product_faqs.index',
                    'sub_title' => 'labels.product_faqs',
                    'permission' => 'product_faqs.view',
                ],
                'attributes' => [
                    'sub_active' => 'attributes',
                    'sub_route' => 'admin.attributes.index',
                    'sub_title' => 'labels.attributes',
                    'permission' => 'product.view',
                ],
                'product_reviews' => [
                    'sub_active' => 'product_reviews',
                    'sub_route' => 'admin.products.reviews.index',
                    'sub_title' => 'labels.product_reviews',
                    'permission' => 'product.view',
                ],
            ],
        ],
        'social_and_content' => [
            'icon' => 'ti-device-tv',
            'title' => 'labels.social_and_content',
            'active' => 'social_and_content',
            'route' => [
                'blog_categories' => [
                    'sub_active' => 'blog_categories',
                    'sub_route' => 'admin.blog-categories.index',
                    'sub_title' => 'labels.blog_categories',
                    'permission' => 'blogs.view',
                ],
                'blogs' => [
                    'sub_active' => 'blogs',
                    'sub_route' => 'admin.blogs.index',
                    'sub_title' => 'labels.blogs',
                    'permission' => 'blogs.view',
                ],
                'stories' => [
                    'sub_active' => 'stories',
                    'sub_route' => 'admin.watch-and-buy.statuses.index',
                    'sub_title' => 'labels.watch_buy_statuses',
                    'permission' => 'watch_buy.status.view',
                ],
                'reels' => [
                    'sub_active' => 'reels',
                    'sub_route' => 'admin.watch-and-buy.reels.index',
                    'sub_title' => 'labels.watch_buy_reels',
                    'permission' => 'watch_buy.reel.view',
                ],
                'profiles' => [
                    'sub_active' => 'profiles',
                    'sub_route' => 'admin.watch-and-buy.profiles.index',
                    'sub_title' => 'labels.watch_buy_seller_profiles',
                    'permission' => 'watch_buy.profile.view',
                ],
                'official_profile' => [
                    'sub_active' => 'official_profile',
                    'sub_route' => 'admin.watch-and-buy.official-profile.edit',
                    'sub_title' => 'labels.watch_buy_official_profile',
                    'permission' => 'watch_buy.profile.edit',
                ],
            ],
        ],

        // ── PEOPLE ──
        '_section_people' => [
            'type' => 'section',
            'title' => 'labels.menu_section_people',
        ],
        'seller_management' => [
            'icon' => 'ti-users-group',
            'title' => 'labels.seller_management',
            'active' => 'sellers',
            'route' => [
                'sellers' => [
                    'sub_active' => 'sellers',
                    'sub_route' => 'admin.sellers.index',
                    'sub_title' => 'labels.sellers',
                    'permission' => 'seller.view',
                    'badge_key' => 'sellers',
                ],
                'add_sellers' => [
                    'sub_active' => 'add_sellers',
                    'sub_route' => 'admin.sellers.create',
                    'sub_title' => 'labels.add_sellers',
                    'permission' => 'seller.create',
                ],
                'earning_settlement' => [
                    'sub_active' => 'seller_earning_settlement',
                    'sub_route' => 'admin.commissions.index',
                    'sub_title' => 'labels.earning_settlement',
                    'permission' => 'commission.view',
                    'badge_key' => 'settlements',
                ],
                'seller_withdrawals' => [
                    'sub_active' => 'seller_withdrawals',
                    'sub_route' => 'admin.seller-withdrawals.index',
                    'sub_title' => 'labels.seller_withdrawals',
                    'permission' => 'seller_withdrawal.view',
                    'badge_key' => 'withdrawals',
                ],
                'seller_withdrawal_history' => [
                    'sub_active' => 'seller_withdrawal_history',
                    'sub_route' => 'admin.seller-withdrawals.history',
                    'sub_title' => 'labels.seller_withdrawal_history',
                    'permission' => 'seller_withdrawal.view',
                ],
                // 'seller_reviews' => [
                //     'sub_active' => 'seller_reviews',
                //     'sub_route'  => 'admin.sellers.reviews.index',
                //     'sub_title'  => 'labels.seller_reviews',
                //     'permission' => 'seller.view',
                // ],
            ],
        ],
        'customers' => [
            'icon' => 'ti-users',
            'title' => 'labels.customers',
            'active' => 'customers',
            'route' => [
                'customers' => [
                    'sub_active' => 'customers',
                    'sub_route' => 'admin.customers.index',
                    'sub_title' => 'labels.customers',
                    'permission' => 'customer.view',
                ],
                'transactions' => [
                    'sub_active' => 'transactions',
                    'sub_route' => 'admin.wallet.transactions',
                    'sub_title' => 'labels.wallet_transactions',
                    'permission' => 'orders.view',
                ],
                'deposits' => [
                    'sub_active' => 'deposits',
                    'sub_route' => 'admin.wallet.deposits',
                    'sub_title' => 'labels.pending_wallet_deposits',
                    'permission' => 'orders.view',
                ],
                'referrals' => [
                    'sub_active' => 'referrals',
                    'sub_route' => 'admin.referrals.index',
                    'sub_title' => 'labels.refer_and_earn',
                    'permission' => 'orders.view',
                ],
                'referral_earnings' => [
                    'sub_active' => 'earnings',
                    'sub_route' => 'admin.referrals.earnings',
                    'sub_title' => 'labels.referral_earnings',
                    'permission' => 'orders.view',
                ],
            ],
        ],
        'delivery_boy_management' => [
            'icon' => 'ti-truck-delivery',
            'title' => 'labels.delivery_boy_management',
            'active' => 'delivery_boy_management',
            'route' => [
                'delivery_boys' => [
                    'sub_active' => 'delivery_boys',
                    'sub_route' => 'admin.delivery-boys.index',
                    'sub_title' => 'labels.delivery_boys',
                    'permission' => 'delivery_boy.view',
                    'badge_key' => 'delivery_boys',
                ],
                'delivery_boy_live_tracking' => [
                    'sub_active' => 'delivery_boy_live_tracking',
                    'sub_route' => 'admin.delivery-boys.live-tracking',
                    'sub_title' => 'labels.delivery_boy_live_tracking',
                    'permission' => 'delivery_boy.view',
                ],
                'delivery_boy_earnings' => [
                    'sub_active' => 'delivery_boy_earnings',
                    'sub_route' => 'admin.delivery-boy-earnings.index',
                    'sub_title' => 'labels.delivery_boy_earnings',
                    'permission' => 'delivery_boy_earning.view',
                ],
                'earning_history' => [
                    'sub_active' => 'earning_history',
                    'sub_route' => 'admin.delivery-boy-earnings.history',
                    'sub_title' => 'labels.earning_history',
                    'permission' => 'delivery_boy_earning.view',
                ],
                'delivery_boy_cash_collections' => [
                    'sub_active' => 'delivery_boy_cash_collections',
                    'sub_route' => 'admin.delivery-boy-cash-collections.index',
                    'sub_title' => 'labels.delivery_boy_cash_collections',
                    'permission' => 'delivery_boy_cash_collection.view',
                ],
                'cash_collection_history' => [
                    'sub_active' => 'cash_collection_history',
                    'sub_route' => 'admin.delivery-boy-cash-collections.history',
                    'sub_title' => 'labels.cash_collection_history',
                    'permission' => 'delivery_boy_cash_collection.view',
                ],
                'delivery_boy_withdrawals' => [
                    'sub_active' => 'delivery_boy_withdrawals',
                    'sub_route' => 'admin.delivery-boy-withdrawals.index',
                    'sub_title' => 'labels.delivery_boy_withdrawals',
                    'permission' => 'delivery_boy_withdrawal.view',
                ],
                'withdrawal_history' => [
                    'sub_active' => 'withdrawal_history',
                    'sub_route' => 'admin.delivery-boy-withdrawals.history',
                    'sub_title' => 'labels.withdrawal_history',
                    'permission' => 'delivery_boy_withdrawal.view',
                ],
                'db_referrals' => [
                    'sub_active' => 'db_referrals',
                    'sub_route' => 'admin.delivery-boy-referrals.index',
                    'sub_title' => 'labels.db_refer_and_earn',
                    'permission' => 'orders.view',
                ],
                'delivery_boy_reviews' => [
                    'sub_active' => 'delivery_boy_reviews',
                    'sub_route' => 'admin.delivery-boys.reviews.index',
                    'sub_title' => 'labels.delivery_boy_reviews',
                    'permission' => 'delivery_boy.view',
                ],
            ],
        ],

        // ── FINANCE ──
        '_section_finance' => [
            'type' => 'section',
            'title' => 'labels.menu_section_finance',
        ],
        'tax_rates' => [
            'icon' => 'ti-square-rounded-percentage',
            'route' => 'admin.tax-rates.index',
            'title' => 'labels.tax_rates',
            'active' => 'tax_rates',
            'permission' => 'tax_class.view',
        ],
        'subscriptions' => [
            'icon' => 'ti-user-dollar',
            'title' => 'labels.subscriptions',
            'active' => 'subscriptions',
            'route' => [
                'plans' => [
                    'sub_active' => 'plans',
                    'sub_route' => 'admin.subscription-plans.index',
                    'sub_title' => 'labels.plans',
                    'permission' => 'subscription_plan.view',
                ],
                'subscribers' => [
                    'sub_active' => 'subscribers',
                    'sub_route' => 'admin.subscription-plans.subscribers',
                    'sub_title' => 'labels.subscribers',
                    'permission' => 'subscription_subscriber.view',
                ],
            ],
        ],

        // ── MARKETING ──
        '_section_marketing' => [
            'type' => 'section',
            'title' => 'labels.menu_section_marketing',
        ],
        // Note: Banners and Featured Sections are now managed via Home Page Builder.
        // Hidden from the sidebar navigation in this update.
        /*
        'banners' => [
            'icon' => 'ti-photo',
            'route' => 'admin.banners.index',
            'title' => 'labels.banners',
            'active' => 'banners',
            'permission' => 'banner.view',
        ],
        'featured_section' => [
            'icon' => 'ti-star',
            'title' => 'labels.featured_section',
            'active' => 'featured_section',
            'route' => [
                'featured_section' => [
                    'sub_active' => 'featured_section',
                    'sub_route' => 'admin.featured-sections.index',
                    'sub_title' => 'labels.featured_section',
                    'permission' => 'featured_section.view',
                ],
                'sort_featured_section' => [
                    'sub_active' => 'sort_featured_section',
                    'sub_route' => 'admin.featured-sections.sort',
                    'sub_title' => 'labels.sort_featured_section',
                    'permission' => 'featured_section.sorting_view',
                ],
            ],
        ],
        */
        'promos' => [
            'icon' => 'ti-ticket',
            'route' => 'admin.promos.index',
            'title' => 'labels.promos',
            'active' => 'promos',
            'permission' => 'promo.view',
        ],
        'ad_campaigns' => [
            'icon' => 'ti-rocket',
            'title' => 'labels.ad_campaigns',
            'active' => 'ad_campaigns',
            'permission' => 'ad_campaign.view',
            'route' => [
                'dashboard' => [
                    'sub_active' => 'ad_campaigns_dashboard',
                    'sub_route' => 'admin.ads.campaigns.dashboard',
                    'sub_title' => 'labels.dashboard',
                    'permission' => 'ad_campaign.dashboard_view',
                ],
                'index' => [
                    'sub_active' => 'ad_campaigns_index',
                    'sub_route' => 'admin.ads.campaigns.index',
                    'sub_title' => 'labels.ad_campaigns',
                    'permission' => 'ad_campaign.view',
                    'badge_key' => 'ad_campaigns',
                ],
                'topups' => [
                    'sub_active' => 'ad_campaigns_topups',
                    'sub_route' => 'admin.ads.campaigns.topups',
                    'sub_title' => 'labels.ad_wallet_sellers',
                    'permission' => 'ad_campaign.view',
                ],
                'topup_transactions' => [
                    'sub_active' => 'ad_campaigns_topup_transactions',
                    'sub_route' => 'admin.ads.campaigns.topup-transactions',
                    'sub_title' => 'labels.pending_wallet_deposits',
                    'permission' => 'ad_campaign.view',
                ],
            ],
        ],

        // ── COMMUNICATION ──
        '_section_communication' => [
            'type' => 'section',
            'title' => 'labels.menu_section_communication',
        ],
        'app_notifications' => [
            'icon' => 'ti-bell-plus',
            'route' => 'admin.app-notifications.index',
            'title' => 'labels.app_notifications',
            'active' => 'app_notifications',
            'permission' => 'notification.view',
        ],
        'notifications' => [
            'icon' => 'ti-bell-ringing',
            'route' => 'admin.notifications.index',
            'title' => 'labels.notifications',
            'active' => 'notifications',
            'permission' => 'notification.view',
        ],
        'faqs' => [
            'icon' => 'ti-help-circle',
            'route' => 'admin.faqs.index',
            'title' => 'labels.faqs',
            'active' => 'faqs',
            'permission' => 'faq.view',
        ],

        // ── SYSTEM ──
        '_section_system' => [
            'type' => 'section',
            'title' => 'labels.menu_section_system',
        ],
        'roles_permissions' => [
            'icon' => 'ti-users-group',
            'title' => 'labels.roles_permissions',
            'active' => 'roles_permissions',
            'route' => [
                'roles' => [
                    'sub_active' => 'roles',
                    'sub_route' => 'admin.roles.index',
                    'sub_title' => 'labels.roles',
                    'permission' => 'role.view',
                ],
                'system_users' => [
                    'sub_active' => 'system_users',
                    'sub_route' => 'admin.system-users.index',
                    'sub_title' => 'labels.system_users',
                    'permission' => 'system_user.view',
                ],
            ],
        ],
        'languages' => [
            'icon' => 'ti-language',
            'title' => 'labels.languages_management',
            'active' => 'languages',
            'route' => [
                'index' => [
                    'sub_active' => 'language_overview',
                    'sub_route' => 'admin.languages.index',
                    'sub_title' => 'labels.overview',
                    'permission' => 'language.view',
                ],
                'panel' => [
                    'sub_active' => 'panel_languages',
                    'sub_route' => 'admin.languages.panel',
                    'sub_title' => 'labels.panel_languages',
                    'permission' => 'language.view',
                ],
                'website' => [
                    'sub_active' => 'website_languages',
                    'sub_route' => 'admin.languages.clients.show',
                    'route_param' => ['surface' => 'web'],
                    'sub_title' => 'labels.website_languages',
                    'permission' => 'language.view',
                ],
                'customer_app' => [
                    'sub_active' => 'customer_app_languages',
                    'sub_route' => 'admin.languages.clients.show',
                    'route_param' => ['surface' => 'customer_app'],
                    'sub_title' => 'labels.customer_app_languages',
                    'permission' => 'language.view',
                ],
                'seller_app' => [
                    'sub_active' => 'seller_app_languages',
                    'sub_route' => 'admin.languages.clients.show',
                    'route_param' => ['surface' => 'seller_app'],
                    'sub_title' => 'labels.seller_app_languages',
                    'permission' => 'language.view',
                ],
                'rider_app' => [
                    'sub_active' => 'rider_app_languages',
                    'sub_route' => 'admin.languages.clients.show',
                    'route_param' => ['surface' => 'rider_app'],
                    'sub_title' => 'labels.rider_app_languages',
                    'permission' => 'language.view',
                ],
            ],
        ],
        'settings' => [
            'icon' => 'ti-settings',
            'title' => 'labels.settings',
            'active' => 'settings',
            'route' => [
                'system' => [
                    'sub_active' => 'system',
                    'sub_route' => 'admin.settings.show',
                    'route_param' => ['setting' => 'system'],
                    'sub_title' => 'labels.menu_system',
                    'permission' => 'setting.system.view',
                ],
                'web' => [
                    'sub_active' => 'web',
                    'sub_route' => 'admin.settings.show',
                    'route_param' => ['setting' => 'web'],
                    'sub_title' => 'labels.menu_web',
                    'permission' => 'setting.web.view',
                ],
                'app' => [
                    'sub_active' => 'app',
                    'sub_route' => 'admin.settings.show',
                    'route_param' => ['setting' => 'app'],
                    'sub_title' => 'labels.menu_app',
                    'permission' => 'setting.app.view',
                ],
                'system_update_settings' => [
                    'sub_active' => 'system_update_settings',
                    'sub_route' => 'admin.settings.show',
                    'route_param' => ['setting' => 'system_update_settings'],
                    'sub_title' => 'labels.system_update_settings',
                    'permission' => 'setting.system_update_settings.view',
                ],
                // Note: Home appearance settings are now managed in Home Page Builder.
                // Hidden from settings sidebar menu in this update.
                /*
                'home_general_settings' => [
                    'sub_active' => 'home_general_settings',
                    'sub_route' => 'admin.settings.show',
                    'route_param' => ['setting' => 'home_general_settings'],
                    'sub_title' => 'labels.home_general_settings',
                    'permission' => 'setting.home_general_settings.view',
                ],
                */
                'storage' => [
                    'sub_active' => 'storage',
                    'sub_route' => 'admin.settings.show',
                    'route_param' => ['setting' => 'storage'],
                    'sub_title' => 'labels.menu_storage',
                    'permission' => 'setting.storage.view',
                ],
                'authentication' => [
                    'sub_active' => 'authentication',
                    'sub_route' => 'admin.settings.show',
                    'route_param' => ['setting' => 'authentication'],
                    'sub_title' => 'labels.menu_authentication',
                    'permission' => 'setting.authentication.view',
                ],
                'email' => [
                    'sub_active' => 'email',
                    'sub_route' => 'admin.settings.show',
                    'route_param' => ['setting' => 'email'],
                    'sub_title' => 'labels.email',
                    'permission' => 'setting.email.view',
                ],
                // Broadcast driver settings (Reverb/Pusher WebSockets)
                'broadcast_driver' => [
                    'sub_active' => 'broadcast_driver',
                    'sub_route' => 'admin.settings.show',
                    'route_param' => ['setting' => 'broadcast_driver'],
                    'sub_title' => 'labels.broadcast_driver',
                    'permission' => 'setting.broadcast_driver.view',
                ],
                'ai' => [
                    'sub_active' => 'ai',
                    'sub_route' => 'admin.settings.show',
                    'route_param' => ['setting' => 'ai'],
                    'sub_title' => 'labels.ai_settings',
                    'permission' => 'setting.ai.view',
                ],
                'payment' => [
                    'sub_active' => 'payment',
                    'sub_route' => 'admin.settings.show',
                    'route_param' => ['setting' => 'payment'],
                    'sub_title' => 'labels.menu_payment',
                    'permission' => 'setting.payment.view',
                ],
                'notification' => [
                    'sub_active' => 'notification',
                    'sub_route' => 'admin.settings.show',
                    'route_param' => ['setting' => 'notification'],
                    'sub_title' => 'labels.menu_notification',
                    'permission' => 'setting.notification.view',
                ],
                'whatsapp' => [
                    'sub_active' => 'whatsapp',
                    'sub_route' => 'admin.settings.whatsapp',
                    'sub_title' => 'WhatsApp',
                    'permission' => 'setting.notification.view',
                ],
                'delivery_boy' => [
                    'sub_active' => 'delivery_boy',
                    'sub_route' => 'admin.settings.show',
                    'route_param' => ['setting' => 'delivery_boy'],
                    'sub_title' => 'labels.delivery_boy',
                    'permission' => 'setting.delivery_boy.view',
                ],
                'seller' => [
                    'sub_active' => 'seller',
                    'sub_route' => 'admin.settings.show',
                    'route_param' => ['setting' => 'seller'],
                    'sub_title' => 'labels.seller',
                    'permission' => 'setting.seller.view',
                ],
                'advertisement' => [
                    'sub_active' => 'advertisement',
                    'sub_route' => 'admin.settings.show',
                    'route_param' => ['setting' => 'advertisement'],
                    'sub_title' => 'labels.advertisement_settings',
                    'permission' => 'setting.advertisement.view',
                ],
                'pos_settings' => [
                    'sub_active' => 'pos_settings',
                    'sub_route' => 'admin.settings.show',
                    'route_param' => ['setting' => 'pos_settings'],
                    'sub_title' => 'labels.pos_settings',
                    'permission' => 'setting.pos_settings.view',
                ],
                'pickup' => [
                    'sub_active' => 'pickup',
                    'sub_route' => 'admin.settings.show',
                    'route_param' => ['setting' => 'pickup'],
                    'sub_title' => 'labels.pickup_settings',
                    'permission' => 'setting.pickup.view',
                ],
                'scheduled_delivery' => [
                    'sub_active' => 'scheduled_delivery',
                    'sub_route' => 'admin.settings.show',
                    'route_param' => ['setting' => 'scheduled_delivery'],
                    'sub_title' => 'labels.scheduled_delivery_title',
                    'permission' => 'setting.scheduled_delivery.view',
                ],
                'loyalty_point' => [
                    'sub_active' => 'loyalty_point',
                    'sub_route' => 'admin.settings.show',
                    'route_param' => ['setting' => 'loyalty_point'],
                    'sub_title' => 'labels.loyalty_points_settings',
                    'permission' => 'setting.loyalty_point.view',
                ],
                'watch_and_buy' => [
                    'sub_active' => 'watch_and_buy',
                    'sub_route' => 'admin.settings.show',
                    'route_param' => ['setting' => 'watch_and_buy'],
                    'sub_title' => 'labels.watch_buy_settings',
                    'permission' => 'setting.watch_and_buy.view',
                ],
            ],
        ],
        'cron_monitor' => [
            'icon' => 'ti-clock-cog',
            'route' => 'admin.cron-monitor.index',
            'title' => 'labels.cron_monitor',
            'active' => 'cron_monitor',
            'permission' => 'cron_monitor.view',
        ],
        'system_updates' => [
            'icon' => 'ti-package',
            'route' => 'admin.system-updates.index',
            'title' => 'labels.system_updates',
            'active' => 'system_updates',
            'permission' => 'setting.system.view',
        ],
        'license_management' => [
            'icon' => 'ti-license',
            'route' => 'admin.license.index',
            'title' => 'labels.license_management',
            'active' => 'license_management',
        ],
        'logout' => [
            'icon' => 'ti-logout-2',
            'route' => 'admin.logout',
            'title' => 'labels.logout',
        ],
    ],

    'delivery-partner' => [
        'dashboard' => [
            'icon' => 'ti-home',
            'route' => 'delivery-partner.dashboard',
            'title' => 'labels.delivery_partner_dashboard',
        ],
    ],

    'seller' => [
        // ── OVERVIEW ──
        '_section_overview' => [
            'type' => 'section',
            'title' => 'labels.menu_section_overview',
        ],
        'dashboard' => [
            'icon' => 'ti-home',
            'route' => 'seller.dashboard',
            'title' => 'labels.seller_dashboard',
            'active' => 'dashboard',
        ],
        'pos' => [
            'icon' => 'ti-devices-pc',
            'title' => 'labels.point_of_sale',
            'active' => 'pos',
            'route' => [
                'pos_overview' => [
                    'sub_active' => 'pos_overview',
                    'sub_route' => 'seller.pos.index',
                    'sub_title' => 'labels.point_of_sale',
                    'permission' => 'pos.view',
                ],
                'pos_dashboard' => [
                    'sub_active' => 'pos_dashboard',
                    'sub_route' => 'seller.pos.dashboard',
                    'sub_title' => 'labels.pos_dashboard',
                    'permission' => 'pos.view',
                ],
            ],
        ],
        'orders' => [
            'icon' => 'ti-package',
            'route' => 'seller.orders.index',
            'title' => 'labels.seller_orders',
            'active' => 'orders',
            'permission' => 'order.view',
        ],
        'scheduled_orders' => [
            'icon' => 'ti-calendar-time',
            'route' => 'seller.scheduled_orders.index',
            'title' => 'labels.scheduled_orders',
            'active' => 'scheduled_orders',
            'permission' => 'order.view',
        ],
        'return_orders' => [
            'icon' => 'ti-truck-return',
            'title' => 'labels.return_orders',
            'active' => 'return_orders',
            'route' => [
                'return_requests' => [
                    'sub_active' => 'return_requests',
                    'sub_route' => 'seller.returns.index',
                    'sub_title' => 'labels.return_requests',
                    'permission' => 'return.view',
                    'badge_key' => 'return_requests',
                ],
                'return_reasons' => [
                    'sub_active' => 'return-reasons',
                    'sub_route' => 'seller.return-reasons.index',
                    'sub_title' => 'labels.return_reasons',
                    'permission' => 'return.view',
                ],
            ],
        ],

        // ── STORE ──
        '_section_store' => [
            'type' => 'section',
            'title' => 'labels.menu_section_store',
        ],
        'stores' => [
            'icon' => 'ti-building-warehouse',
            'title' => 'labels.seller_stores',
            'active' => 'stores',
            'route' => 'seller.stores.index',
            'permission' => 'store.view',
        ],

        // ── CATALOG ──
        '_section_catalog' => [
            'type' => 'section',
            'title' => 'labels.menu_section_catalog',
        ],
        'categories' => [
            'icon' => 'ti-category-2',
            'route' => 'seller.categories.index',
            'title' => 'labels.seller_categories',
            'active' => 'categories',
            'permission' => 'category.view',
        ],
        'brands' => [
            'icon' => 'ti-sparkles',
            'route' => 'seller.brands.index',
            'title' => 'labels.seller_brands',
            'active' => 'brands',
            'permission' => 'brand.view',
        ],
        'products' => [
            'icon' => 'ti-cube-spark',
            'title' => 'labels.manage_products',
            'active' => 'products',
            'route' => [
                'products' => [
                    'sub_active' => 'products',
                    'sub_route' => 'seller.products.index',
                    'sub_title' => 'labels.seller_products',
                    'permission' => 'product.view',

                ],
                'add_products' => [
                    'sub_active' => 'add_products',
                    'sub_route' => 'seller.products.create',
                    'sub_title' => 'labels.add_products',
                    'permission' => 'product.create',

                ],
                'bulk_upload' => [
                    'sub_active' => 'bulk_upload',
                    'sub_route' => 'seller.products.bulk-upload.page',
                    'sub_title' => 'labels.bulk_upload',
                    'permission' => 'product.create',

                ],
                'product_faqs' => [
                    'sub_active' => 'product_faqs',
                    'sub_route' => 'seller.product_faqs.index',
                    'sub_title' => 'labels.seller_product_faqs',
                    'permission' => 'product_faq.view',
                ],
                'addon_groups' => [
                    'sub_active' => 'addon_groups',
                    'sub_route' => 'seller.addon-groups.index',
                    'sub_title' => 'labels.addon_groups',
                    'permission' => 'addon_group.view',
                ],
                'product_addons' => [
                    'sub_active' => 'product_addons',
                    'sub_route' => 'seller.product-addons.index',
                    'sub_title' => 'labels.product_addons',
                    'permission' => 'product_addon.view',
                ],
                'store_addon_items' => [
                    'sub_active' => 'store_addon_items',
                    'sub_route' => 'seller.store-addon-items.index',
                    'sub_title' => 'labels.store_addon_items',
                    'permission' => 'store_addon_item.view',
                ],
            ],
        ],
        'watch_and_buy' => [
            'icon' => 'ti-player-play',
            'title' => 'labels.watch_buy',
            'active' => 'watch_and_buy',
            'route' => [
                'stories' => [
                    'sub_active' => 'stories',
                    'sub_route' => 'seller.watch-and-buy.statuses.index',
                    'sub_title' => 'labels.watch_buy_statuses',
                    'permission' => 'watch_buy.status.view',
                ],
                'reels' => [
                    'sub_active' => 'reels',
                    'sub_route' => 'seller.watch-and-buy.reels.index',
                    'sub_title' => 'labels.watch_buy_reels',
                    'permission' => 'watch_buy.reel.view',
                ],
                'profile' => [
                    'sub_active' => 'profile',
                    'sub_route' => 'seller.watch-and-buy.profile.edit',
                    'sub_title' => 'labels.watch_buy_profile',
                    'permission' => 'watch_buy.profile.view',
                ],
            ],
        ],
        'attributes' => [
            'icon' => 'ti-tag-starred',
            'route' => 'seller.attributes.index',
            'title' => 'labels.attributes',
            'active' => 'attributes',
            'permission' => 'attribute.view',
        ],

        // ── FINANCE ──
        '_section_finance' => [
            'type' => 'section',
            'title' => 'labels.menu_section_finance',
        ],
        'tax_rates' => [
            'icon' => 'ti-square-rounded-percentage',
            'route' => 'seller.tax-rates.index',
            'title' => 'labels.seller_tax_rates',
            'active' => 'tax_rates',
            'permission' => 'tax_rate.view',
        ],
        'earnings' => [
            'icon' => 'ti-currency-dollar',
            'route' => 'seller.commissions.index',
            'title' => 'labels.earnings',
            'active' => 'earnings',
            'permission' => 'earning.view',
        ],
        'wallet' => [
            'icon' => 'ti-wallet',
            'title' => 'labels.wallet',
            'active' => 'wallet',
            'route' => [
                'balance' => [
                    'sub_active' => 'wallet_balance',
                    'sub_route' => 'seller.wallet.index',
                    'sub_title' => 'labels.wallet_balance',
                    'permission' => 'wallet.view',

                ],
                'withdrawals' => [
                    'sub_active' => 'withdrawals',
                    'sub_route' => 'seller.withdrawals.index',
                    'sub_title' => 'labels.seller_withdrawals',
                    'permission' => 'wallet.view',
                    'badge_key' => 'withdrawals',
                ],
                'withdrawal_history' => [
                    'sub_active' => 'withdrawal_history',
                    'sub_route' => 'seller.withdrawals.history',
                    'sub_title' => 'labels.withdrawal_history',
                    'permission' => 'withdrawal.view',
                ],
            ],
        ],
        'subscriptions' => [
            'icon' => 'ti-user-dollar',
            'title' => 'labels.subscriptions',
            'active' => 'subscriptions',
            'route' => [
                'plans' => [
                    'sub_active' => 'plans',
                    'sub_route' => 'seller.subscription-plans.index',
                    'sub_title' => 'labels.plans',
                    'permission' => 'subscription.view',
                ],
                'current' => [
                    'sub_active' => 'current',
                    'sub_route' => 'seller.subscription-plans.current',
                    'sub_title' => 'labels.current_subscription',
                    'permission' => 'subscription.view',
                ],
                'history' => [
                    'sub_active' => 'history',
                    'sub_route' => 'seller.subscription-plans.history',
                    'sub_title' => 'labels.subscription_history',
                    'permission' => 'subscription.view',
                ],
            ],
        ],

        // ── MARKETING ──
        '_section_marketing' => [
            'type' => 'section',
            'title' => 'labels.menu_section_marketing',
        ],
        'ad_campaigns' => [
            'icon' => 'ti-rocket',
            'title' => 'labels.ad_campaigns',
            'active' => 'ad_campaigns',
            'route' => [
                'dashboard' => [
                    'sub_active' => 'ad_campaigns_dashboard',
                    'sub_route' => 'seller.ads.campaigns.dashboard',
                    'sub_title' => 'labels.dashboard',
                    'permission' => 'ad_campaign.dashboard_view',
                ],
                'index' => [
                    'sub_active' => 'ad_campaigns_index',
                    'sub_route' => 'seller.ads.campaigns.index',
                    'sub_title' => 'labels.ad_campaigns',
                    'permission' => 'ad_campaign.view',
                ],
                'create' => [
                    'sub_active' => 'ad_campaigns_create',
                    'sub_route' => 'seller.ads.campaigns.create',
                    'sub_title' => 'labels.create_ad_campaign',
                    'permission' => 'ad_campaign.create',
                ],
            ],
        ],
        'ad_wallet' => [
            'icon' => 'ti-speakerphone',
            'title' => 'labels.ad_wallet',
            'active' => 'ad_wallet',
            'route' => [
                'balance' => [
                    'sub_active' => 'ad_wallet_balance',
                    'sub_route' => 'seller.ads.wallet.index',
                    'sub_title' => 'labels.ad_wallet_balance',
                    'permission' => 'ad_wallet.view',
                ],
                'transactions' => [
                    'sub_active' => 'ad_wallet_transactions',
                    'sub_route' => 'seller.ads.wallet.transactions',
                    'sub_title' => 'labels.transaction_history',
                    'permission' => 'ad_wallet.view',
                ],
            ],
        ],

        // ── COMMUNICATION ──
        '_section_communication' => [
            'type' => 'section',
            'title' => 'labels.menu_section_communication',
        ],
        'notifications' => [
            'icon' => 'ti-bell-ringing',
            'route' => 'seller.notifications.index',
            'title' => 'labels.seller_notifications',
            'active' => 'notifications',
            'permission' => 'notification.view',
        ],

        // ── SYSTEM ──
        '_section_system' => [
            'type' => 'section',
            'title' => 'labels.menu_section_system',
        ],
        'roles_permissions' => [
            'icon' => 'ti-users-group',
            'title' => 'labels.seller_roles_permissions',
            'active' => 'roles_permissions',
            'route' => [
                'roles' => [
                    'sub_active' => 'roles',
                    'sub_route' => 'seller.roles.index',
                    'sub_title' => 'labels.seller_roles',
                    'permission' => 'role.view',

                ],
                'system_users' => [
                    'sub_active' => 'system_users',
                    'sub_route' => 'seller.system-users.index',
                    'sub_title' => 'labels.seller_system_users',
                    'permission' => 'system_user.view',

                ],
            ],
        ],
        'logout' => [
            'icon' => 'ti-logout-2',
            'route' => 'seller.logout',
            'title' => 'labels.seller_logout',
        ],
    ],
];
