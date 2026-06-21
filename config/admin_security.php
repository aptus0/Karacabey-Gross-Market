<?php

return [
    'admin_prefix' => env('ADMIN_PANEL_PATH', 'admin'),

    'max_failed_attempts' => (int) env('ADMIN_SECURITY_MAX_FAILED_ATTEMPTS', 5),
    'max_decoy_hits' => (int) env('ADMIN_SECURITY_MAX_DECOY_HITS', 2),
    'window_minutes' => (int) env('ADMIN_SECURITY_WINDOW_MINUTES', 15),
    'block_minutes' => (int) env('ADMIN_SECURITY_BLOCK_MINUTES', 30),

    'trusted_ips' => array_values(array_filter(array_map(
        static fn (string $ip): string => trim($ip),
        explode(',', (string) env('ADMIN_SECURITY_TRUSTED_IPS', ''))
    ))),

    'suspicious_user_agents' => [
        'acunetix',
        'curl',
        'dirbuster',
        'ffuf',
        'masscan',
        'nikto',
        'nmap',
        'python-requests',
        'sqlmap',
        'wget',
        'zgrab',
    ],
];
