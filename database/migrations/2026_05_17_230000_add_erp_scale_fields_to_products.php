<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'external_ref')) {
                $table->string('external_ref', 120)->nullable()->after('id');
            }
            if (!Schema::hasColumn('products', 'sku')) {
                $table->string('sku', 120)->nullable()->after('external_ref');
            }
            if (!Schema::hasColumn('products', 'unit_name')) {
                $table->string('unit_name', 32)->default('adet')->after('stock_quantity');
            }
            if (!Schema::hasColumn('products', 'vat_rate_basis_points')) {
                $table->unsignedSmallInteger('vat_rate_basis_points')->default(1000)->after('unit_name');
            }
            if (!Schema::hasColumn('products', 'cdn_image_url')) {
                $table->string('cdn_image_url', 700)->nullable()->after('image_url');
            }
            if (!Schema::hasColumn('products', 'image_etag')) {
                $table->string('image_etag', 120)->nullable()->after('cdn_image_url');
            }
            if (!Schema::hasColumn('products', 'search_keywords')) {
                $table->text('search_keywords')->nullable()->after('metadata');
            }
            if (!Schema::hasColumn('products', 'feed_hash')) {
                $table->char('feed_hash', 64)->nullable()->after('search_keywords');
            }
            if (!Schema::hasColumn('products', 'sync_version')) {
                $table->unsignedBigInteger('sync_version')->default(1)->after('feed_hash');
            }
            if (!Schema::hasColumn('products', 'erp_updated_at')) {
                $table->timestamp('erp_updated_at')->nullable()->after('sync_version');
            }
            if (!Schema::hasColumn('products', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable()->after('erp_updated_at');
            }
        });

        $supportsFullText = Schema::getConnection()->getDriverName() === 'mysql';
        Schema::table('products', function (Blueprint $table) use ($supportsFullText) {
            $table->unique(['tenant_id', 'external_ref'], 'products_tenant_external_ref_unique');
            $table->index(['tenant_id', 'is_active', 'sync_version'], 'products_tenant_active_sync_version_idx');
            $table->index(['tenant_id', 'barcode', 'is_active'], 'products_tenant_barcode_active_idx');
            $table->index(['tenant_id', 'sku', 'is_active'], 'products_tenant_sku_active_idx');
            $table->index(['tenant_id', 'last_synced_at'], 'products_tenant_last_synced_idx');
            if ($supportsFullText) {
                $table->fullText(['name', 'brand', 'barcode', 'search_keywords'], 'products_catalog_fulltext_idx');
            }
        });
    }

    public function down(): void
    {
        $supportsFullText = Schema::getConnection()->getDriverName() === 'mysql';
        Schema::table('products', function (Blueprint $table) use ($supportsFullText) {
            if ($supportsFullText) {
                $table->dropFullText('products_catalog_fulltext_idx');
            }
            $table->dropIndex('products_tenant_last_synced_idx');
            $table->dropIndex('products_tenant_sku_active_idx');
            $table->dropIndex('products_tenant_barcode_active_idx');
            $table->dropIndex('products_tenant_active_sync_version_idx');
            $table->dropUnique('products_tenant_external_ref_unique');
            $table->dropColumn([
                'external_ref',
                'sku',
                'unit_name',
                'vat_rate_basis_points',
                'cdn_image_url',
                'image_etag',
                'search_keywords',
                'feed_hash',
                'sync_version',
                'erp_updated_at',
                'last_synced_at',
            ]);
        });
    }
};
