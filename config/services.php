<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'merchant_id' => env('MIDTRANS_MERCHANT_ID'),
        'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', true),
        'base_url' => env(
            'MIDTRANS_BASE_URL',
            env('MIDTRANS_IS_PRODUCTION', true)
                ? 'https://app.midtrans.com'
                : 'https://app.sandbox.midtrans.com',
        ),
        'snap_path' => env('MIDTRANS_SNAP_PATH', '/snap/v1/transactions'),
        'timeout' => (int) env('MIDTRANS_TIMEOUT', 10),
        'verify_ssl' => (bool) env('MIDTRANS_VERIFY_SSL', true),
        'enabled_payments' => env('MIDTRANS_ENABLED_PAYMENTS')
            ? array_map('trim', explode(',', (string) env('MIDTRANS_ENABLED_PAYMENTS')))
            : [],
    ],

];
