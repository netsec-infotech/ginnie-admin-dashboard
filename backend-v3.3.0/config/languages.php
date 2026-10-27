<?php

return [
    'packs_path' => resource_path('language-packs'),
    'enabled_client_surfaces' => ['web', 'customer_app', 'seller_app', 'rider_app'],
    'sources' => [
        'web' => resource_path('language-sources/web/en.json'),
        'customer_app' => resource_path('language-sources/customer_app/en.json'),
        'seller_app' => resource_path('language-sources/seller_app/en.json'),
        'rider_app' => resource_path('language-sources/rider_app/en.json'),
    ],
    'quick_add' => [
        'en' => ['name' => 'English', 'native_name' => 'English', 'direction' => 'ltr', 'country_code' => 'us'],
        'es' => ['name' => 'Spanish', 'native_name' => 'Español', 'direction' => 'ltr', 'country_code' => 'es'],
        'fr' => ['name' => 'French', 'native_name' => 'Français', 'direction' => 'ltr', 'country_code' => 'fr'],
        'de' => ['name' => 'German', 'native_name' => 'Deutsch', 'direction' => 'ltr', 'country_code' => 'de'],
        'zh' => ['name' => 'Chinese', 'native_name' => '中文', 'direction' => 'ltr', 'country_code' => 'cn'],
        'hi' => ['name' => 'Hindi', 'native_name' => 'हिन्दी', 'direction' => 'ltr', 'country_code' => 'in'],
        'ar' => ['name' => 'Arabic', 'native_name' => 'العربية', 'direction' => 'rtl', 'country_code' => 'sa'],
        'gu' => ['name' => 'Gujarati', 'native_name' => 'ગુજરાતી', 'direction' => 'ltr', 'country_code' => 'in'],
        'te' => ['name' => 'Telugu', 'native_name' => 'తెలుగు', 'direction' => 'ltr', 'country_code' => 'in'],
    ],
];
