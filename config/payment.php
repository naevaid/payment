<?php

return [
    'currency' => env('PAYMENT_DEFAULT_CURRENCY', 'IDR'),

    'auth' => [
        'app_id_header' => env('PAYMENT_AUTH_APP_ID_HEADER', 'X-App-ID'),
        'secret_key_header' => env('PAYMENT_AUTH_SECRET_HEADER', 'X-Secret-Key'),
        'signature_header' => env('PAYMENT_AUTH_SIGNATURE_HEADER', 'X-Payment-Signature'),
        'timestamp_header' => env('PAYMENT_AUTH_TIMESTAMP_HEADER', 'X-Timestamp'),
        'signature_algorithm' => env('PAYMENT_AUTH_SIGNATURE_ALGORITHM', 'sha256'),
        'timestamp_tolerance_seconds' => (int) env('PAYMENT_AUTH_TIMESTAMP_TOLERANCE', 300),
        'allow_legacy_secret_header' => filter_var(
            env('PAYMENT_AUTH_ALLOW_LEGACY_SECRET_HEADER', true),
            FILTER_VALIDATE_BOOL,
            FILTER_NULL_ON_FAILURE,
        ) ?? true,
    ],

    'callback' => [
        'queue' => env('PAYMENT_CALLBACK_QUEUE', 'payment-callbacks'),
        'timeout_seconds' => (int) env('PAYMENT_CALLBACK_TIMEOUT', 10),
        'max_attempts' => (int) env('PAYMENT_CALLBACK_MAX_ATTEMPTS', 3),
        'backoff' => array_map(
            static fn (string $value): int => (int) trim($value),
            explode(',', env('PAYMENT_CALLBACK_BACKOFF', '60,300,900')),
        ),
        'user_agent' => env('PAYMENT_CALLBACK_USER_AGENT', 'Naeva-Payment-Callback/1.0'),
    ],
];
