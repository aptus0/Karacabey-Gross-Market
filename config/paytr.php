<?php

return [
    'merchant_id' => env('PAYTR_MERCHANT_ID'),
    'merchant_key' => env('PAYTR_MERCHANT_KEY'),
    'merchant_salt' => env('PAYTR_MERCHANT_SALT'),

    'test_mode' => (bool) env('PAYTR_TEST_MODE', true),
    'debug' => (bool) env('PAYTR_DEBUG', true),
    'currency' => env('PAYTR_CURRENCY', 'TL'),
    'timeout_limit' => (int) env('PAYTR_TIMEOUT_LIMIT', 30),
    'max_installment' => (int) env('PAYTR_MAX_INSTALLMENT', 0),
    'no_installment' => (int) env('PAYTR_NO_INSTALLMENT', 0),

    'ok_url' => env('PAYTR_OK_URL', env('FRONTEND_URL', env('APP_URL')).'/checkout/success'),
    'fail_url' => env('PAYTR_FAIL_URL', env('FRONTEND_URL', env('APP_URL')).'/checkout/fail'),
    'callback_url' => env('PAYTR_CALLBACK_URL', env('APP_URL').'/api/cb/p'),

    'endpoints' => [
        'iframe_token' => env('PAYTR_IFRAME_TOKEN_URL', 'https://www.paytr.com/odeme/api/get-token'),
        'iframe_secure' => env('PAYTR_IFRAME_SECURE_URL', 'https://www.paytr.com/odeme/guvenli'),
        'direct_payment' => env('PAYTR_DIRECT_PAYMENT_URL', 'https://www.paytr.com/odeme'),
        'refund' => env('PAYTR_REFUND_URL', 'https://www.paytr.com/odeme/iade'),
        'status' => env('PAYTR_STATUS_URL', 'https://www.paytr.com/odeme/durum-sorgu'),
        'card_list' => env('PAYTR_CARD_LIST_URL', 'https://www.paytr.com/odeme/capi/list'),
        'card_delete' => env('PAYTR_CARD_DELETE_URL', 'https://www.paytr.com/odeme/capi/delete'),
    ],
];
