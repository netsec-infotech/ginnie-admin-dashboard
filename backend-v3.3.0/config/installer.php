<?php

use Database\Seeders\DatabaseSeeder;

return [

    'product_name' => 'HyperMart Installer - Hyperlocal Multivendor Commerce Setup',

    'branding' => [
        'logo' => env('INSTALLER_LOGO', 'logos/hyper-local-logo.png'),
        'favicon' => env('INSTALLER_FAVICON', 'logos/hyper-local-favicon.png'),
        'company_name' => env('INSTALLER_COMPANY_NAME', 'Infinitie Technologies'),
        'company_url' => env('INSTALLER_COMPANY_URL', 'https://infinitietech.com/'),
        'portfolio_url' => env('INSTALLER_PORTFOLIO_URL', 'https://codecanyon.net/user/infinitietech/portfolio'),
        'guide_url' => env('INSTALLER_GUIDE_URL', 'https://docs-hyper-local.vercel.app/introduction'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Server Requirements
    |--------------------------------------------------------------------------
    |
    | This is the default Laravel server requirements, you can add as many
    | as your application require, we check if the extension is enabled
    | by looping through the array and run "extension_loaded" on it.
    |
    */
    'core' => [
        'minPhpVersion' => '8.2',
    ],

    'requirements' => [
        'openssl',
        'pdo',
        'mbstring',
        'tokenizer',
        'fileinfo',
        'curl',
    ],

    /*
    |--------------------------------------------------------------------------
    | Required PHP Functions
    |--------------------------------------------------------------------------
    |
    | These functions must not be listed in php.ini `disable_functions`.
    | The installer runs `function_exists()` on each entry below. Remove an
    | entry if your deployment genuinely does not need it.
    |
    */
    'functions' => [
        'symlink',
        'proc_open',
        'proc_close',
    ],

    /*
    |--------------------------------------------------------------------------
    | Required php.ini Settings
    |--------------------------------------------------------------------------
    |
    | Key = ini directive, value = expected state.
    |   - 'On' / 'Off' (or 1/0/yes/no/true/false) → boolean equality check.
    |   - Size strings ('64M', '1G') and plain integers → MINIMUM check
    |     (current value must be >= the expected value).
    |   - Anything else → case-insensitive exact match.
    |
    */
    'ini_settings' => [
        'file_uploads' => 'On',
        'allow_url_fopen' => 'On',
        'post_max_size' => '128M',
        'upload_max_filesize' => '100M',
        'max_file_uploads' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Folders Permissions
    |--------------------------------------------------------------------------
    |
    | This is the default Laravel folders permissions, if your application
    | requires more permissions just add them to the array list bellow.
    |
    */
    'permissions' => [
        'storage/app/' => '775',
        'storage/framework/' => '775',
        'storage/logs/' => '775',
        'bootstrap/cache/' => '775',
    ],

    'permission_commands' => [
        [
            'label' => 'Ubuntu / Debian',
            'commands' => [
                'sudo chown -R www-data:www-data storage bootstrap/cache',
                'sudo chmod -R 775 storage bootstrap/cache',
            ],
        ],
        [
            'label' => 'RHEL / CentOS / AlmaLinux / Rocky Linux',
            'commands' => [
                'sudo chown -R apache:apache storage bootstrap/cache',
                'sudo chmod -R 775 storage bootstrap/cache',
            ],
        ],
        [
            'label' => 'macOS',
            'commands' => [
                'sudo chown -R _www:_www storage bootstrap/cache',
                'sudo chmod -R 775 storage bootstrap/cache',
            ],
        ],
        [
            'label' => 'Windows IIS (PowerShell as Administrator)',
            'commands' => [
                'icacls storage bootstrap\cache /grant "IIS_IUSRS:(OI)(CI)M" /T',
            ],
        ],
        [
            'label' => 'Shared hosting terminal',
            'commands' => [
                'chmod -R 775 storage bootstrap/cache',
            ],
        ],
    ],

    'seeders' => DatabaseSeeder::INSTALLATION_SEEDERS,

    'progress' => [
        'poll_interval_ms' => 750,
        'max_log_entries' => 250,
        'weights' => [
            'preflight' => 5,
            'migrations' => 60,
            'seeders' => 20,
            'administrator' => 10,
            'finalization' => 5,
        ],
    ],
];
