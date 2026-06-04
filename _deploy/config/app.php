<?php

return [
    'name' => 'SUGGAWAYZ',
    'env' => env('APP_ENV', 'local'),
    'url' => env('APP_URL', 'http://localhost:8080'),
    'debug' => env('APP_DEBUG', true),
    'key' => env('APP_KEY', 'suggawayz-base64-secret-key-change-in-production'),
    'cipher' => 'AES-256-CBC',
    'locale' => 'en',
    'currency' => 'USD',
    'currencies' => ['USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD'],
    'tax_rate' => 8.25,
    'shipping_threshold' => 75.00,
    'pagination' => 12,
];
