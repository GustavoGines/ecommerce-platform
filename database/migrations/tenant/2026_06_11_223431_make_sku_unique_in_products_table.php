<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * SUG-12: Agrega restricción UNIQUE al campo SKU.
     *
     * Primero normaliza los SKU vacíos a NULL para que la restricción UNIQUE
     * no falle con múltiples filas que tengan SKU = '' (cadena vacía).
     * Los NULL múltiples son permitidos por UNIQUE en MySQL/SQLite.
     */
    public function up(): void
    {
        // Normalizar cadenas vacías a NULL antes de aplicar el índice único
        DB::table('products')->where('sku', '')->update(['sku' => null]);

        Schema::table('products', function (Blueprint $table) {
            $table->unique('sku', 'products_sku_unique');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_sku_unique');
        });
    }
};
