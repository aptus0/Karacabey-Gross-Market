<?php

$defaultOrigins = implode(',', array_unique(array_filter([
    env('STOREFRONT_URL', env('FRONTEND_URL', 'http://localhost:3001')),
    env('ADMIN_URL', 'http://localhost:8000'),
    env('API_URL'),
])));

$localOriginPatterns = env('APP_ENV', 'production') === 'local' ? [
    '#^https?://localhost(:[0-9]+)?$#',
    '#^https?://127\.0\.0\.1(:[0-9]+)?$#',
    '#^https?://192\.168\.[0-9]+\.[0-9]+(:[0-9]+)?$#',
    '#^https?://10\.[0-9]+\.[0-9]+\.[0-9]+(:[0-9]+)?$#',
    '#^https?://172\.(1[6-9]|2[0-9]|3[0-1])\.[0-9]+\.[0-9]+(:[0-9]+)?$#',
] : [];

$configuredOriginPatterns = env('CORS_ALLOWED_ORIGINS_PATTERNS');
$allowedOriginPatterns = $configuredOriginPatterns
    ? array_values(array_filter(array_map('trim', explode(',', $configuredOriginPatterns))))
    : $localOriginPatterns;

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_unique(array_filter(array_map(
        fn (string $origin): string => rtrim(trim($origin), '/'),
        explode(',', env('CORS_ALLOWED_ORIGINS', $defaultOrigins)),
    )))),

    'allowed_origins_patterns' => $allowedOriginPatterns,

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
