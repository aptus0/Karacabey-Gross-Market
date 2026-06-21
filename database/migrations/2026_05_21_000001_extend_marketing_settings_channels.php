<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_settings', function (Blueprint $table) {
            // Google paketi
            $table->string('google_gtm_id', 60)->nullable()->after('google_site_verification');
            $table->string('google_merchant_id', 60)->nullable()->after('google_gtm_id');
            $table->text('google_merchant_service_account_json')->nullable()->after('google_merchant_id');
            $table->string('google_maps_api_key', 255)->nullable()->after('google_merchant_service_account_json');
            $table->text('ga4_api_secret')->nullable()->after('google_maps_api_key');

            // Meta (Facebook/Instagram)
            $table->text('meta_capi_access_token')->nullable()->after('meta_pixel_id');
            $table->string('meta_capi_test_event_code', 60)->nullable()->after('meta_capi_access_token');
            $table->string('meta_catalog_id', 60)->nullable()->after('meta_capi_test_event_code');
            $table->string('meta_business_id', 60)->nullable()->after('meta_catalog_id');
            $table->string('meta_dataset_id', 60)->nullable()->after('meta_business_id');

            // Yandex
            $table->string('yandex_metrica_id', 32)->nullable()->after('meta_dataset_id');
            $table->string('yandex_verification', 120)->nullable()->after('yandex_metrica_id');
            $table->string('yandex_direct_counter_id', 32)->nullable()->after('yandex_verification');

            // Microsoft / Bing
            $table->string('microsoft_uet_tag_id', 32)->nullable()->after('yandex_direct_counter_id');
            $table->string('microsoft_clarity_id', 32)->nullable()->after('microsoft_uet_tag_id');
            $table->string('bing_verification', 120)->nullable()->after('microsoft_clarity_id');

            // TikTok
            $table->string('tiktok_pixel_id', 60)->nullable()->after('bing_verification');
            $table->text('tiktok_capi_access_token')->nullable()->after('tiktok_pixel_id');

            // Genel
            $table->boolean('tracking_enabled')->default(true)->after('tiktok_capi_access_token');
            $table->boolean('server_side_events_enabled')->default(false)->after('tracking_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('marketing_settings', function (Blueprint $table) {
            $table->dropColumn([
                'google_gtm_id',
                'google_merchant_id',
                'google_merchant_service_account_json',
                'google_maps_api_key',
                'ga4_api_secret',
                'meta_capi_access_token',
                'meta_capi_test_event_code',
                'meta_catalog_id',
                'meta_business_id',
                'meta_dataset_id',
                'yandex_metrica_id',
                'yandex_verification',
                'yandex_direct_counter_id',
                'microsoft_uet_tag_id',
                'microsoft_clarity_id',
                'bing_verification',
                'tiktok_pixel_id',
                'tiktok_capi_access_token',
                'tracking_enabled',
                'server_side_events_enabled',
            ]);
        });
    }
};
