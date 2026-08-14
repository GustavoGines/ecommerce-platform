<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithTransactions;
use App\Models\Brand;

class ProductsImport implements OnEachRow, WithHeadingRow, WithTransactions
{
    public $importedCount = 0;
    private $allBrands = [];

    public function __construct()
    {
        $commonBrands = config('brands.common', []);
        $dbBrands = Brand::pluck('name')->toArray();
        $this->allBrands = array_unique(array_merge($commonBrands, $dbBrands));
    }

    public function onRow(Row $row)
    {
        $rowArray = $row->toArray();

        // WithHeadingRow normalizes headers to snake_case automatically
        // Example: "Precio Mayorista" -> "precio_mayorista"
        $name = $rowArray['nombre'] ?? null;
        
        if (empty($name)) {
            return;
        }

        // Lógica de Categorías
        $categoryName = $rowArray['categoria'] ?? null;
        $categoryId = null;
        
        if (!empty($categoryName)) {
            $category = Category::firstOrCreate(
                ['name' => $categoryName],
                ['slug' => Str::slug($categoryName)]
            );
            $categoryId = $category->id;
        }

        // Lógica de Marcas (Extracción Inteligente)
        $brandName = $rowArray['marca'] ?? null;
        
        // Si no viene la columna "Marca", intentamos deducirla del nombre o la categoría
        if (empty($brandName)) {
            $brandName = $this->extractBrandFromString($name . ' ' . $categoryName);
        }

        $brandId = null;
        if (!empty($brandName)) {
            $brand = Brand::firstOrCreate(
                ['name' => $brandName],
                ['slug' => Str::slug($brandName)]
            );
            $brandId = $brand->id;
        }

        $sku = $rowArray['sku'] ?? ($rowArray['codigo'] ?? null);
        
        $retailPrice = $rowArray['precio'] ?? 0;
        $wholesalePrice = $rowArray['precio_mayorista'] ?? 0;
        $costPrice = $rowArray['costo'] ?? 0;
        $stock = (int) ($rowArray['stock'] ?? 0);

        // BUG-06 FIX: Evitar insertar productos con valores negativos o ilógicos
        if ($retailPrice < 0 || $wholesalePrice < 0 || $costPrice < 0 || $stock < 0) {
            return;
        }

        $productData = [
            'name' => $name,
            'retail_price' => (float) $retailPrice,
            'wholesale_price' => (float) $wholesalePrice,
            'cost_price' => (float) $costPrice,
            'stock' => $stock,
            'category_id' => $categoryId,
        ];

        // Lógica de actualización o creación
        if (!empty($sku)) {
            $productData['sku'] = $sku;
            $product = Product::updateOrCreate(
                ['sku' => $sku],
                $productData
            );
        } else {
            $product = Product::create($productData);
        }

        // Sincronizar relación Many-to-Many de marca
        if ($brandId) {
            $product->brands()->sync([$brandId]);
        }

        $this->importedCount++;
    }

    /**
     * Extrae una marca conocida de un texto dado.
     */
    private function extractBrandFromString($text)
    {
        if (empty($text)) {
            return null;
        }

        $textLower = mb_strtolower($text);

        foreach ($this->allBrands as $brand) {
            // Buscamos la palabra exacta (con separadores de palabra para no falsos positivos, ej: LG en "ALGO")
            if (preg_match('/\b' . preg_quote(mb_strtolower($brand), '/') . '\b/u', $textLower)) {
                // Retornamos el nombre bien formateado
                return $brand;
            }
        }

        return null;
    }
}
