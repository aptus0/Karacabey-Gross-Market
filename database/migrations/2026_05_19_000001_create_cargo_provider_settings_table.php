<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargo_provider_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // YURTICI | ARAS | PTT | MNG
            $table->string('code', 32)->index();
            $table->string('name', 100);
            $table->boolean('is_active')->default(false);
            // Kargo ücreti (kuruş)
            $table->unsignedInteger('price_cents')->default(0);
            // Bu tutarın üzerindeki siparişlerde kargo bedava
            $table->unsignedInteger('free_threshold_cents')->default(0);
            // Tahmini teslimat (iş günü)
            $table->unsignedTinyInteger('estimated_days_min')->default(1);
            $table->unsignedTinyInteger('estimated_days_max')->default(3);
            // API kimlik bilgileri (JSON, şifreli saklayabiliriz)
            $table->json('credentials')->nullable();
            // Ek ayarlar (bölge kısıtlaması, vb.)
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        // Siparişe kargo sağlayıcısı bağla
        Schema::table('orders', function (Blueprint $table) {
            $table->string('cargo_carrier', 32)->nullable()->after('shipping_address');
            $table->unsignedInteger('shipping_cents')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('cargo_carrier');
        });
        Schema::dropIfExists('cargo_provider_settings');
    }
};
