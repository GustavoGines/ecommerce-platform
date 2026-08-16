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
        // 1. Crear tabla pivot
        Schema::create('brand_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            
            // Evitar duplicados
            $table->unique(['brand_id', 'product_id']);
        });

        // 2. Migrar los datos existentes (para no perder la relación que ya tenían)
        $products = DB::table('products')->whereNotNull('brand_id')->get();
        foreach ($products as $product) {
            DB::table('brand_product')->insert([
                'brand_id' => $product->brand_id,
                'product_id' => $product->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. Eliminar la columna vieja
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
            $table->dropColumn('brand_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Restaurar columna
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
        });

        // (No podemos restaurar los datos perfectamente hacia atrás si un producto tenía múltiples marcas,
        // pero podemos intentar asignar la primera marca que encontremos)
        $pivots = DB::table('brand_product')->get();
        foreach ($pivots as $pivot) {
            DB::table('products')->where('id', $pivot->product_id)->update(['brand_id' => $pivot->brand_id]);
        }

        // 2. Eliminar tabla pivot
        Schema::dropIfExists('brand_product');
    }
};
