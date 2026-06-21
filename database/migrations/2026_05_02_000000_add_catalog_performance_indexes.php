<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index(['tenant_id', 'is_active', 'id'], 'products_tenant_active_id_idx');
            $table->index(['tenant_id', 'is_active', 'price_cents', 'id'], 'products_tenant_active_price_id_idx');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->index(['tenant_id', 'is_active', 'parent_id', 'sort_order'], 'categories_tenant_active_parent_sort_idx');
        });

        Schema::table('navigation_items', function (Blueprint $table) {
            $table->index(['tenant_id', 'is_active', 'placement', 'sort_order'], 'navigation_items_tenant_active_place_sort_idx');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->index(['tenant_id', 'user_id', 'created_at'], 'cart_items_tenant_user_created_idx');
            $table->index(['tenant_id', 'cart_token', 'created_at'], 'cart_items_tenant_token_created_idx');
        });

        Schema::table('cart_coupons', function (Blueprint $table) {
            $table->index(['tenant_id', 'user_id'], 'cart_coupons_tenant_user_idx');
            $table->index(['tenant_id', 'cart_token'], 'cart_coupons_tenant_token_idx');
        });

        Schema::table('category_product', function (Blueprint $table) {
            $table->index(['product_id', 'category_id'], 'category_product_product_category_idx');
        });
    }

    public function down(): void
    {
        Schema::table('cart_coupons', function (Blueprint $table) {
            $table->dropIndex('cart_coupons_tenant_token_idx');
            $table->dropIndex('cart_coupons_tenant_user_idx');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropIndex('cart_items_tenant_token_created_idx');
            $table->dropIndex('cart_items_tenant_user_created_idx');
        });

        Schema::table('navigation_items', function (Blueprint $table) {
            $table->dropIndex('navigation_items_tenant_active_place_sort_idx');
        });

        Schema::table('category_product', function (Blueprint $table) {
            $table->dropIndex('category_product_product_category_idx');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('categories_tenant_active_parent_sort_idx');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_tenant_active_price_id_idx');
            $table->dropIndex('products_tenant_active_id_idx');
        });
    }
};
