<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Modificar tipos en `products`
        Schema::table('products', function (Blueprint $table) {
            // Laravel change() requiere doctrine/dbal, pero a partir de Laravel 10+ el driver nativo a veces funciona.
            // Para asegurar, lo hacemos crudo si es sqlite, sino alter table. Asumimos MySQL:
            $table->decimal('profit_margin', 8, 2)->default(0)->change();
            $table->decimal('wholesale_discount', 8, 2)->default(0)->change();
        });

        // 2. Agregar índices en `orders`
        Schema::table('orders', function (Blueprint $table) {
            $table->index('status');
            $table->index('mp_payment_id');
        });

        // 3. Agregar snapshot fields a `order_items`
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('product_name')->nullable()->after('product_id');
            $table->string('product_sku')->nullable()->after('product_name');
        });

        // Populate order_items product_name and sku from existing products if any
        DB::statement("
            UPDATE order_items oi
            INNER JOIN products p ON oi.product_id = p.id
            SET oi.product_name = p.name, oi.product_sku = p.sku
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['product_name', 'product_sku']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['mp_payment_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->integer('profit_margin')->default(0)->change();
            $table->integer('wholesale_discount')->default(0)->change();
        });
    }
};
