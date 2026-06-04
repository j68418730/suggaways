<?php

return [
    'paypal' => [
        'enabled' => true,
        'sandbox' => true,
        'client_id' => env('PAYPAL_CLIENT_ID', ''),
        'secret' => env('PAYPAL_SECRET', ''),
    ],
    'stripe' => [
        'enabled' => true,
        'publishable_key' => env('STRIPE_KEY', ''),
        'secret_key' => env('STRIPE_SECRET', ''),
    ],
    'square' => [
        'enabled' => true,
        'access_token' => env('SQUARE_TOKEN', ''),
        'location_id' => env('SQUARE_LOCATION', ''),
    ],
    'cash_app' => [
        'enabled' => true,
    ],
    'apple_pay' => [
        'enabled' => true,
        'merchant_id' => env('APPLE_MERCHANT_ID', ''),
    ],
    'google_pay' => [
        'enabled' => true,
    ],
    'bank_transfer' => [
        'enabled' => true,
        'bank_name' => env('BANK_NAME', 'SUGGAWAYZ Financial'),
        'account_name' => env('BANK_ACCOUNT_NAME', 'SUGGAWAYZ Inc.'),
        'account_number' => env('BANK_ACCOUNT_NUMBER', ''),
        'routing_number' => env('BANK_ROUTING_NUMBER', ''),
        'swift' => env('BANK_SWIFT', ''),
    ],
];
