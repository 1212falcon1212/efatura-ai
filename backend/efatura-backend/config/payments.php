<?php

return [
    'moka' => [
        // Örn: Prod: https://service.mokaunited.com  | Test: https://service.test.mokaunited.com
        'base_url' => env('MOKA_BASE_URL', 'https://service.mokaunited.com'),
        'dealer_code' => env('MOKA_DEALER_CODE', ''),
        'username' => env('MOKA_USERNAME', ''),
        'password' => env('MOKA_PASSWORD', ''),
        'return_hash_secret' => env('MOKA_RETURN_HASH_SECRET', ''),
    ],
];


