<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Storefront/API performance profile
    |--------------------------------------------------------------------------
    |
    | Bu değerler canlı web vitrininde hızlı ürün listeleme, arama önerisi,
    | sepet işlemi ve SEO tarayıcı trafiği için tek merkezden ayarlanır.
    |
    */

    'cache' => [
        'products_ttl_seconds' => (int) env('KGM_PRODUCTS_CACHE_TTL', 300),
        'product_detail_ttl_seconds' => (int) env('KGM_PRODUCT_DETAIL_CACHE_TTL', 600),
        'product_suggest_ttl_seconds' => (int) env('KGM_PRODUCT_SUGGEST_CACHE_TTL', 120),
        'categories_ttl_seconds' => (int) env('KGM_CATEGORIES_CACHE_TTL', 900),
        'content_ttl_seconds' => (int) env('KGM_CONTENT_CACHE_TTL', 300),
    ],

    'http' => [
        'public_api_max_age' => (int) env('KGM_PUBLIC_API_MAX_AGE', 60),
        'public_api_s_maxage' => (int) env('KGM_PUBLIC_API_S_MAXAGE', 300),
        'public_api_stale_while_revalidate' => (int) env('KGM_PUBLIC_API_STALE_WHILE_REVALIDATE', 86400),
        'private_no_store' => (bool) env('KGM_PRIVATE_NO_STORE', true),
    ],

    'cart' => [
        'request_timeout_ms' => (int) env('KGM_WEB_CART_TIMEOUT_MS', 14000),
        'client_retry_count' => (int) env('KGM_WEB_CART_RETRY_COUNT', 2),
        'client_retry_base_ms' => (int) env('KGM_WEB_CART_RETRY_BASE_MS', 220),
    ],

    'seo' => [
        'sitemap_products_per_page' => (int) env('KGM_SEO_SITEMAP_PRODUCTS_PER_PAGE', 200),
        'sitemap_max_product_pages' => (int) env('KGM_SEO_SITEMAP_MAX_PRODUCT_PAGES', 50),
        'product_schema_price_days' => (int) env('KGM_SEO_PRICE_VALID_DAYS', 7),
    ],
];
