<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('badge_label', 60)->nullable()->after('banner_image_url');
            $table->string('color_hex', 7)->nullable()->default('#FF7A00')->after('badge_label');
            $table->text('body')->nullable()->after('description');
            $table->string('meta_image_url', 500)->nullable()->after('banner_image_url');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['badge_label', 'color_hex', 'body', 'meta_image_url', 'sort_order']);
        });
    }
};
