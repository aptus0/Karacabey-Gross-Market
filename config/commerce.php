<?php

return [
    'primary_domain' => env('COMMERCE_PRIMARY_DOMAIN', 'karacabeygrossmarket.com'),

    'domains' => [
        'storefront' => env('STOREFRONT_URL', env('FRONTEND_URL', 'https://karacabeygrossmarket.com')),
        'admin' => env('ADMIN_URL', 'https://app.karacabeygrossmarket.com'),
        'api' => env('API_URL', env('APP_URL', 'https://api.karacabeygrossmarket.com')),
        'cdn' => env('CDN_URL', 'https://cdn.karacabeygrossmarket.com'),
    ],

    'api' => [
        'version' => env('API_VERSION', 'v1'),
    ],

    'mobile' => [
        'api_version' => env('MOBILE_API_VERSION', env('API_VERSION', 'v1')),
    ],
];
