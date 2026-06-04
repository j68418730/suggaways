<?php

return [
    'driver' => 'mysql',
    'host' => env('DB_HOST', 'db'),
    'port' => env('DB_PORT', '3306'),
    'database' => env('DB_DATABASE', 'suggawayz'),
    'username' => env('DB_USERNAME', 'suggawayz'),
    'password' => env('DB_PASSWORD', 'suggawayz_secret'),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
];
