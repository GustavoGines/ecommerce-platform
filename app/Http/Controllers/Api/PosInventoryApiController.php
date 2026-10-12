<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PosSyncLog;
use App\Models\Product;
use App\Models\StoreSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PosInventoryApiController extends Controller
{
    /**
     * Endpoint de Salud / Estado del E-Commerce para el POS.
     * GET /api/v1/health o GET /api/v1/store/status
     */
    public function health(): JsonResponse
    {
        $settings = StoreSetting::getSettings();

        return response()->json([
            'status'     => 'ok',
            'version'    => '1.0',
            'store_name' => $settings->store_name ?? 'Mi Tienda Web',
            'tenant_id'  => tenant('id'),
        ], 200);
    }

    /**
     * Endpoint de Actualización de Stock (Invocado por el POS).
     * POST /api/v1/inventory/update
     */
    public function updateInventory(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required',
            'quantity'   => 'required|numeric',
            'variant_id' => 'nullable',
        ]);

        $rawProductId = trim((string) $request->input('product_id'));
        $quantity = (float) $request->input('quantity');
        $variantId = $request->input('variant_id');

        // 1. Búsqueda inteligente de producto en 3 niveles (ID ➔ SKU ➔ Nombre)
        $product = $this->resolveProduct($rawProductId, $variantId, $request->input('name'));

        if (! $product) {
            PosSyncLog::logEvent(
                direction: 'inbound',
                eventType: 'inventory.update',
                payload: $request->all(),
                responseCode: 404,
                responseBody: ['error' => 'Producto no encontrado'],
                status: 'failed',
                errorMessage: "No se encontró ningún producto para el identificador '{$rawProductId}'"
            );

            return response()->json([
                'success' => false,
                'error'   => 'Producto no encontrado',
                'message' => "No se encontró ningún producto con ID, SKU o Nombre '{$rawProductId}' en esta tienda.",
            ], 404);
        }

        // 2. Invariante Anti-Sobreventa y actualización atómica en base de datos
        // Si quantity <= 0, stock es 0 (bloqueo automático de checkout y marcado 'outofstock')
        $newStock = max(0, (int) round($quantity));

        // Actualización directa a nivel de base de datos para máxima velocidad (< 10ms)
        Product::where('id', $product->id)->update([
            'stock'      => $newStock,
            'updated_at' => now(),
        ]);

        $responseData = [
            'success'       => true,
            'product_id'    => $product->id,
            'sku'           => $product->sku,
            'name'          => $product->name,
            'updated_stock' => (float) $newStock,
            'status'        => $newStock > 0 ? 'instock' : 'outofstock',
        ];

        // 3. Auditoría en logs de sincronización
        PosSyncLog::logEvent(
            direction: 'inbound',
            eventType: 'inventory.update',
            payload: $request->all(),
            responseCode: 200,
            responseBody: $responseData,
            status: 'success'
        );

        return response()->json($responseData, 200);
    }

    /**
     * Endpoint de Consulta de Orden para auditoría/conciliación del POS.
     * GET /api/v1/orders/{order_id}
     */
    public function showOrder(string $order_id): JsonResponse
    {
        $id = preg_replace('/^ORD-/i', '', trim($order_id));
        $order = Order::with(['items.product', 'user'])->find($id);

        if (! $order) {
            return response()->json([
                'error'   => 'Orden no encontrada',
                'message' => "La orden #{$order_id} no existe en esta tienda.",
            ], 404);
        }

        $items = $order->items->map(function ($item) {
            return [
              'external_product_id' => (string) ($item->product_id ?? ''),
              'external_variant_id' => null,
              'sku'                 => $item->product?->sku ?? "PROD-{$item->product_id}",
              'name'                => $item->product?->name ?? "Producto #{$item->product_id}",
              'quantity'            => (int) $item->quantity,
              'price'               => (float) $item->price,
            ];
        });

        return response()->json([
            'success'         => true,
            'order_id'        => "ORD-{$order->id}",
            'order_number'    => $order->id,
            'status'          => $order->status,
            'status_label'    => $order->status_label,
            'total'           => (float) $order->total,
            'delivery_method' => $order->delivery_method,
            'payment_method'  => $order->payment_method,
            'created_at'      => $order->created_at?->toIso8601String(),
            'user'            => [
                'name'  => $order->user?->name,
                'email' => $order->user?->email,
                'phone' => $order->phone,
            ],
            'items'           => $items,
        ], 200);
    }

    /**
     * Endpoint de Cancelación de Orden desde el POS por falta de stock físico.
     * POST /api/v1/orders/{order_id}/cancel
     */
    public function cancelOrder(Request $request, string $order_id): JsonResponse
    {
        $id = preg_replace('/^ORD-/i', '', trim($order_id));
        $order = Order::with('items.product')->find($id);

        if (! $order) {
            return response()->json([
                'error'   => 'Orden no encontrada',
                'message' => "La orden #{$order_id} no existe en esta tienda.",
            ], 404);
        }

        if ($order->status === Order::STATUS_CANCELLED) {
            return response()->json([
                'success' => true,
                'message' => 'La orden ya se encontraba previamente cancelada.',
                'order_id' => "ORD-{$order->id}",
                'status'  => Order::STATUS_CANCELLED,
            ], 200);
        }

        $reason = $request->input('reason', 'Cancelado desde POS por falta de stock físico');

        DB::transaction(function () use ($order) {
            // Restituir inventario de los productos de la orden en la web
            foreach ($order->items as $item) {
                if ($item->product) {
                    $item->product->increment('stock', $item->quantity);
                }
            }

            $order->status = Order::STATUS_CANCELLED;
            $order->status_updated_at = now();
            $order->save();
        });

        PosSyncLog::logEvent(
            direction: 'inbound',
            eventType: 'order.cancel',
            orderId: $order->id,
            payload: $request->all(),
            responseCode: 200,
            responseBody: ['success' => true, 'status' => Order::STATUS_CANCELLED, 'reason' => $reason],
            status: 'success'
        );

        return response()->json([
            'success'      => true,
            'message'      => 'Orden cancelada exitosamente y stock restituido en la web.',
            'order_id'     => "ORD-{$order->id}",
            'order_number' => $order->id,
            'status'       => Order::STATUS_CANCELLED,
            'reason'       => $reason,
        ], 200);
    }

    /**
     * Resuelve un producto en cascada:
     * 1. ID numérico (o con prefijo PROD-)
     * 2. SKU
     * 3. Nombre exacto (para compatibilidad G3)
     */
    private function resolveProduct(string $identifier, ?string $variantId = null, ?string $fallbackName = null): ?Product
    {
        // 1. Intentar por ID numérico
        $cleanId = preg_replace('/^PROD-/i', '', $identifier);
        if (is_numeric($cleanId)) {
            $product = Product::find((int) $cleanId);
            if ($product) {
                return $product;
            }
        }

        // 2. Intentar por SKU
        $product = Product::where('sku', $identifier)->first();
        if ($product) {
            return $product;
        }

        if (!empty($variantId) && $variantId !== '0') {
            $product = Product::where('sku', $variantId)->first();
            if ($product) {
                return $product;
            }
        }

        // 3. Intentar por Nombre exacto (fundamental para listas de proveedores de G3)
        $product = Product::where('name', $identifier)->first();
        if ($product) {
            return $product;
        }

        if (!empty($fallbackName)) {
            $product = Product::where('name', $fallbackName)->first();
            if ($product) {
                return $product;
            }
        }

        return null;
    }
}
