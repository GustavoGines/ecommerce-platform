<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Agrega índices a la tabla products para mejorar la performance del
     * catálogo con 1800+ productos. Sin estos índices, cada filtro por
     * categoría y el ordenamiento por stock hacen un full table scan.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Usado en: WHERE category_id = ?  (filtro del catálogo)
            $table->index('category_id');

            // Usado en: ORDER BY stock > 0 DESC  (priorizar productos con stock)
            $table->index('stock');

            // Usado en: WHERE name = ?  (sync desde Google Sheets, upsert por nombre)
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['category_id']);
            $table->dropIndex(['stock']);
            $table->dropIndex(['name']);
        });
    }
};
