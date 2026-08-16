<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PERF-05: Agrega un índice compuesto (cart_id, product_id) en cart_items.
     * Las consultas de carrito hacen JOIN por ambas columnas constantemente.
     * El índice compuesto reduce el tiempo de búsqueda de O(n) a O(log n).
     */
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            // Verifica si el índice no existe antes de crearlo (idempotente)
            $table->index(['cart_id', 'product_id'], 'cart_items_cart_product_idx');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropIndex('cart_items_cart_product_idx');
        });
    }
};
