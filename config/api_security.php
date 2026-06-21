<?php

return [
    'enabled' => env('API_SECURITY_ENABLED', true),

    'redirect_url' => env('API_SECURITY_REDIRECT_URL', env('STOREFRONT_URL', '/')),

    'ban_minutes' => env('API_SECURITY_BAN_MINUTES', 1440),

    'trusted_ips' => array_values(array_filter(array_map(
        static fn (string $value): string => trim($value),
        explode(',', (string) env('API_SECURITY_TRUSTED_IPS', ''))
    ))),

    'protected_entrypoints' => [
        'api/v1/c' => ['POST'],
    ],
];
