<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp12_cariler', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 80);
            $table->string('kod', 120)->nullable();
            $table->string('ad', 500);
            $table->string('tur', 40)->nullable();
            $table->string('vergi_no', 80)->nullable();
            $table->string('kimlik_no', 80)->nullable();
            $table->string('vergi_dairesi', 180)->nullable();
            $table->string('telefon', 80)->nullable();
            $table->string('cep', 80)->nullable();
            $table->string('email', 180)->nullable();
            $table->string('web', 300)->nullable();
            $table->string('sehir', 160)->nullable();
            $table->integer('vade')->default(0);
            $table->decimal('risk_limiti', 18, 4)->default(0);
            $table->decimal('toplam_borc', 18, 4)->default(0);
            $table->decimal('toplam_alacak', 18, 4)->default(0);
            $table->decimal('bakiye', 18, 4)->default(0);
            $table->boolean('aktif')->default(true);
            $table->date('erp_created_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'external_id'], 'erp12_cariler_tenant_external_unique');
            $table->index(['tenant_id', 'ad'], 'erp12_cariler_tenant_ad_idx');
            $table->index(['tenant_id', 'kod'], 'erp12_cariler_tenant_kod_idx');
        });

        Schema::create('erp12_faturalar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 80);
            $table->string('belgeno', 160)->nullable();
            $table->date('tarih')->nullable();
            $table->string('tip', 180)->nullable();
            $table->string('vergi_no', 80)->nullable();
            $table->string('cari_external_id', 80)->nullable();
            $table->decimal('tutar', 18, 4)->default(0);
            $table->string('durum', 40)->default('Bekliyor');
            $table->string('email', 180)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'external_id'], 'erp12_faturalar_tenant_external_unique');
            $table->index(['tenant_id', 'tarih'], 'erp12_faturalar_tenant_tarih_idx');
            $table->index(['tenant_id', 'cari_external_id'], 'erp12_faturalar_tenant_cari_idx');
        });

        Schema::create('erp12_fatura_satirlari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 80);
            $table->string('fatura_external_id', 80);
            $table->string('stok_external_id', 80)->nullable();
            $table->string('stok_kod', 160)->nullable();
            $table->string('stok_ad', 500)->nullable();
            $table->string('barkod', 160)->nullable();
            $table->decimal('miktar', 18, 4)->default(0);
            $table->decimal('miktar_giris', 18, 4)->default(0);
            $table->decimal('miktar_cikis', 18, 4)->default(0);
            $table->decimal('fiyat', 18, 4)->default(0);
            $table->decimal('dahil_fiyat', 18, 4)->default(0);
            $table->decimal('tutar', 18, 4)->default(0);
            $table->decimal('kdv', 8, 4)->default(0);
            $table->string('lokasyon', 180)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'external_id'], 'erp12_satirlar_tenant_external_unique');
            $table->index(['tenant_id', 'fatura_external_id'], 'erp12_satirlar_tenant_fatura_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp12_fatura_satirlari');
        Schema::dropIfExists('erp12_faturalar');
        Schema::dropIfExists('erp12_cariler');
    }
};
