<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\PosSyncLog;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosCatalogApiController extends Controller
{
    /**
     * Exportación de catálogo completo desde la Web hacia el POS (Web ➔ POS Onboarding).
     * GET /api/v1/catalog/export
     */
    public function exportCatalog(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'brands']);

        $products = $query->get()->map(function ($product) {
            $images = [];
            if (!empty($product->images) && is_array($product->images)) {
                $images = $product->images;
            } elseif (!empty($product->image_url)) {
                $images = [$product->image_url];
            }

            return [
                'id'          => (string) $product->id,
                'sku'         => $product->sku ?? "PROD-{$product->id}",
                'name'        => $product->name,
                'description' => $product->description,
                'price'       => (float) $product->retail_price,
                'cost_price'  => (float) ($product->cost_price ?? 0),
                'stock'       => (int) $product->stock,
                'category'    => $product->category?->name ?? 'General',
                'barcode'     => $product->sku ?? '',
                'images'      => $images,
            ];
        });

        $response = [
            'success'  => true,
            'total'    => $products->count(),
            'products' => $products,
        ];

        PosSyncLog::logEvent(
            direction: 'outbound',
            eventType: 'catalog.export',
            payload: ['query' => $request->all()],
            responseCode: 200,
            responseBody: ['total_exported' => $products->count()],
            status: 'success'
        );

        return response()->json($response, 200);
    }

    /**
     * Siembra inicial de catálogo desde el POS hacia un nuevo Tenant Web (POS ➔ Web Onboarding).
     * POST /api/v1/catalog/bulk-sync
     */
    public function bulkSync(Request $request): JsonResponse
    {
        $request->validate([
            'products'   => 'required|array|min:1',
            'products.*.name'  => 'required|string',
            'products.*.price' => 'required|numeric',
        ]);

        $items = $request->input('products');
        $createdCount = 0;
        $updatedCount = 0;

        DB::transaction(function () use ($items, &$createdCount, &$updatedCount) {
            foreach ($items as $item) {
                $name = trim($item['name']);
                $sku = !empty($item['sku']) ? trim($item['sku']) : null;
                $price = (float) $item['price'];
                $costPrice = isset($item['cost_price']) ? (float) $item['cost_price'] : 0.0;
                $stock = isset($item['stock']) ? max(0, (int) $item['stock']) : 0;
                $categoryName = !empty($item['category']) ? trim($item['category']) : 'General';
                $description = $item['description'] ?? null;

                // 1. Resolver categoría
                $category = Category::firstOrCreate(
                    ['name' => $categoryName],
                    ['slug' => Str::slug($categoryName) ?: 'general']
                );

                // 2. Buscar producto existente por SKU o por Nombre
                $product = null;
                if (!empty($sku)) {
                    $product = Product::where('sku', $sku)->first();
                }
                if (! $product) {
                    $product = Product::where('name', $name)->first();
                }

                if ($product) {
                    $product->update([
                        'name'            => $name,
                        'sku'             => $sku ?: $product->sku,
                        'retail_price'    => $price,
                        'cost_price'      => $costPrice,
                        'stock'           => $stock,
                        'category_id'     => $category->id,
                        'description'     => $description ?: $product->description,
                    ]);
                    $updatedCount++;
                } else {
                    Product::create([
                        'name'            => $name,
                        'sku'             => $sku,
                        'retail_price'    => $price,
                        'cost_price'      => $costPrice,
                        'wholesale_price' => $price,
                        'stock'           => $stock,
                        'category_id'     => $category->id,
                        'description'     => $description,
                    ]);
                    $createdCount++;
                }
            }
        });

        $responseData = [
            'success'       => true,
            'synced_count'  => count($items),
            'created_count' => $createdCount,
            'updated_count' => $updatedCount,
        ];

        PosSyncLog::logEvent(
            direction: 'inbound',
            eventType: 'catalog.bulk_sync',
            payload: ['total_items' => count($items)],
            responseCode: 200,
            responseBody: $responseData,
            status: 'success'
        );

        return response()->json($responseData, 200);
    }
}
