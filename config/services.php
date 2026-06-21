<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google_cse' => [
        // Google Custom Search JSON API — ProductImageResolver içinde
        // ürün adlarından görsel aday çekmek için kullanılır.
        //
        // CX (Engine ID): KGM ürün görseli arama motoru — public ID, gizli değildir.
        //   https://programmablesearchengine.google.com/  > Edit search engine
        // KEY (API key) gizli — sadece .env'de tutulur:
        //   https://console.cloud.google.com/apis/credentials > Create API key
        //   (Custom Search API'yi etkinleştirmeyi unutmayın)
        'key' => env('GOOGLE_CSE_KEY'),
        'cx'  => env('GOOGLE_CSE_CX', 'b74b2c14f5e87448f'),
        'referer' => env('GOOGLE_CSE_REFERER', 'https://karacabeygrossmarket.com/'),
    ],

    'gemini' => [
        // Google Gemini API (https://aistudio.google.com/app/apikey)
        // ProductImageResolver hem search grounding (web'den görsel URL bulma)
        // hem de vision (aday görsellerin ürünle uyumunu skorlama) için kullanır.
        'key'   => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    ],

    'google' => [
        'analytics_id' => env('GOOGLE_ANALYTICS_ID'),
        'ads_id' => env('GOOGLE_ADS_ID'),
        'ads_conversion_label' => env('GOOGLE_ADS_CONVERSION_LABEL'),
        'site_verification' => env('GOOGLE_SITE_VERIFICATION'),
        'oauth_client_id' => env('GOOGLE_CLIENT_ID'),
        'oauth_client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'oauth_redirect' => env('GOOGLE_REDIRECT_URI', rtrim(env('APP_URL', 'http://localhost'), '/').'/oauth/google/callback'),
    ],

    'meta' => [
        'pixel_id' => env('META_PIXEL_ID'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI', rtrim(env('APP_URL', 'http://localhost'), '/').'/oauth/facebook/callback'),
    ],

    'storefront' => [
        'url' => env('STOREFRONT_URL', 'http://127.0.0.1:3000'),
    ],

    'apns' => [
        'key' => env('APNS_KEY_PATH'),
        'key_id' => env('APNS_KEY_ID'),
        'team_id' => env('APNS_TEAM_ID'),
        'bundle_id' => env('APNS_BUNDLE_ID'),
        'production' => env('APNS_PRODUCTION', false),
    ],

    'fcm' => [
        'server_key' => env('FCM_SERVER_KEY'),
        'sender_id' => env('FCM_SENDER_ID'),
    ],

];
