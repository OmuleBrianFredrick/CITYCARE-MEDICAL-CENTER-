<?php

return [
    /*
    |--------------------------------------------------------------------------
    | One-time bootstrap administrator
    |--------------------------------------------------------------------------
    |
    | These values are consumed only by DatabaseSeeder. Set both values for the
    | first production seed, then remove them and rebuild the configuration
    | cache after the account has been verified.
    |
    */
    'bootstrap_admin' => [
        'email' => env('CITYCARE_ADMIN_EMAIL'),
        'password' => env('CITYCARE_ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Local demonstration accounts
    |--------------------------------------------------------------------------
    */
    'demo_password' => env('CITYCARE_TEST_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Protected clinical documents
    |--------------------------------------------------------------------------
    |
    | The configured disk must be private. The public disk is deliberately
    | rejected by the attachment service because referrals may contain PHI.
    |
    */
    'clinical_attachments_disk' => env('CITYCARE_CLINICAL_ATTACHMENTS_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Reverse proxy addresses
    |--------------------------------------------------------------------------
    |
    | Provide a comma-separated allow-list only when the application is behind
    | a load balancer or reverse proxy. Leave blank for direct hosting.
    |
    */
    'trusted_proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TRUSTED_PROXIES', '')),
    ))),
];
