<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('products', 'products_tenant_active_slug_idx', ['tenant_id', 'is_active', 'slug']);
        $this->addIndexIfMissing('products', 'products_tenant_active_stock_id_idx', ['tenant_id', 'is_active', 'stock_quantity', 'id']);
        $this->addIndexIfMissing('products', 'products_tenant_active_updated_id_idx', ['tenant_id', 'is_active', 'updated_at', 'id']);
        $this->addIndexIfMissing('categories', 'categories_tenant_active_slug_idx', ['tenant_id', 'is_active', 'slug']);
        $this->addIndexIfMissing('cart_items', 'cart_items_tenant_updated_idx', ['tenant_id', 'updated_at']);
        $this->addIndexIfMissing('cart_items', 'cart_items_tenant_user_updated_idx', ['tenant_id', 'user_id', 'updated_at']);
        $this->addIndexIfMissing('cart_items', 'cart_items_tenant_token_updated_idx', ['tenant_id', 'cart_token', 'updated_at']);

        if (Schema::hasTable('product_reviews') && Schema::hasColumn('product_reviews', 'status')) {
            $this->addIndexIfMissing('product_reviews', 'product_reviews_product_status_created_idx', ['product_id', 'status', 'created_at']);
        } elseif (Schema::hasTable('product_reviews') && Schema::hasColumn('product_reviews', 'is_approved')) {
            $this->addIndexIfMissing('product_reviews', 'product_reviews_product_approved_created_idx', ['product_id', 'is_approved', 'created_at']);
        }

        $this->addProductFullTextIndexIfMissing();
    }

    public function down(): void
    {
        $this->dropIndexIfExists('products', 'products_search_fulltext_idx');
        $this->dropIndexIfExists('product_reviews', 'product_reviews_product_approved_created_idx');
        $this->dropIndexIfExists('product_reviews', 'product_reviews_product_status_created_idx');
        $this->dropIndexIfExists('cart_items', 'cart_items_tenant_token_updated_idx');
        $this->dropIndexIfExists('cart_items', 'cart_items_tenant_user_updated_idx');
        $this->dropIndexIfExists('cart_items', 'cart_items_tenant_updated_idx');
        $this->dropIndexIfExists('categories', 'categories_tenant_active_slug_idx');
        $this->dropIndexIfExists('products', 'products_tenant_active_updated_id_idx');
        $this->dropIndexIfExists('products', 'products_tenant_active_stock_id_idx');
        $this->dropIndexIfExists('products', 'products_tenant_active_slug_idx');
    }

    /**
     * @param array<int, string> $columns
     */
    private function addIndexIfMissing(string $table, string $index, array $columns): void
    {
        if (! Schema::hasTable($table) || $this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($columns, $index): void {
            $table->index($columns, $index);
        });
    }

    private function addProductFullTextIndexIfMissing(): void
    {
        if (! Schema::hasTable('products') || $this->indexExists('products', 'products_search_fulltext_idx')) {
            return;
        }

        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement('CREATE FULLTEXT INDEX products_search_fulltext_idx ON products (name, brand, barcode, description)');
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (! Schema::hasTable($table) || ! $this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($index): void {
            $table->dropIndex($index);
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return false;
        }

        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
