<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homepage_blocks', function (Blueprint $table): void {
            $table->boolean('show_on_mobile')->default(true)->after('is_active');
            $table->boolean('show_on_web')->default(true)->after('show_on_mobile');
        });

        Schema::table('stories', function (Blueprint $table): void {
            $table->boolean('show_on_mobile')->default(true)->after('is_active');
            $table->boolean('show_on_web')->default(true)->after('show_on_mobile');
            $table->string('influencer_name')->nullable()->after('icon');
            $table->string('influencer_handle')->nullable()->after('influencer_name');
            $table->string('promo_code')->nullable()->after('influencer_handle');
            $table->string('cta_url', 500)->nullable()->after('promo_code');
            $table->string('content_type', 40)->nullable()->after('cta_url');
        });

        Schema::table('campaigns', function (Blueprint $table): void {
            $table->boolean('show_on_mobile')->default(true)->after('is_active');
            $table->boolean('show_on_web')->default(true)->after('show_on_mobile');
            $table->string('influencer_name')->nullable()->after('seo');
            $table->string('influencer_handle')->nullable()->after('influencer_name');
            $table->string('promo_code')->nullable()->after('influencer_handle');
            $table->string('cta_url', 500)->nullable()->after('promo_code');
            $table->string('content_type', 40)->nullable()->after('cta_url');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropColumn([
                'show_on_mobile',
                'show_on_web',
                'influencer_name',
                'influencer_handle',
                'promo_code',
                'cta_url',
                'content_type',
            ]);
        });

        Schema::table('stories', function (Blueprint $table): void {
            $table->dropColumn([
                'show_on_mobile',
                'show_on_web',
                'influencer_name',
                'influencer_handle',
                'promo_code',
                'cta_url',
                'content_type',
            ]);
        });

        Schema::table('homepage_blocks', function (Blueprint $table): void {
            $table->dropColumn(['show_on_mobile', 'show_on_web']);
        });
    }
};

