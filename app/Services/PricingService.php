<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Auth;

/**
 * DRY-01: Servicio centralizado de cálculo de precios.
 *
 * Antes de este servicio, la lógica de precio estaba triplicada en:
 * - cart-panel.blade.php
 * - checkout.blade.php
 * - MercadoPagoService.php
 *
 * Ahora hay una única fuente de verdad. Si la regla de negocio cambia,
 * se modifica SOLO aquí.
 */
class PricingService
{
    // Mínimo global de artículos en el carrito para activar precios mayoristas.
    // Fuente de verdad única: si cambia esta regla de negocio, se modifica SOLO aquí.
    public const GLOBAL_WHOLESALE_MIN = 10;

    /**
     * Calcula el precio unitario de un producto para un cliente dado.
     *
     * Reglas (en orden de prioridad):
     * 1. Si el usuario es cliente mayorista VIP → precio mayorista en TODO.
     * 2. Si la suma total de productos en el carrito es >= GLOBAL_WHOLESALE_MIN → precio mayorista.
     * 3. En cualquier otro caso → precio minorista.
     *
     * @param  Product        $product   Producto a calcular.
     * @param  int            $quantity  Cantidad solicitada del producto.
     * @param  \App\Models\User|null  $user  Usuario autenticado (null = invitado).
     * @param  int            $totalCartQuantity Cantidad total de artículos en el carrito actual.
     * @return float          Precio unitario a aplicar.
     */
    public function unitPrice(Product $product, int $quantity, $user = null, int $totalCartQuantity = 0): float
    {
        // 1. Cliente VIP Mayorista
        if ($user && $user->isWholesaleCustomer()) {
            return (float) $product->wholesale_price;
        }

        // 2. Compra por volumen (cantidad total del carrito supera el mínimo global)
        if ($totalCartQuantity >= self::GLOBAL_WHOLESALE_MIN) {
            return (float) $product->wholesale_price;
        }

        // 3. Cliente Minorista
        return (float) $product->retail_price;
    }
}
