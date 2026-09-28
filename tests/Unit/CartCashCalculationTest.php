<?php

namespace Tests\Unit;

use App\Services\PricingService;
use PHPUnit\Framework\TestCase;

class CartCashCalculationTest extends TestCase
{
    private PricingService $pricingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricingService = new PricingService();
    }

    /**
     * Verifica matemáticamente que al aplicar un recargo del 10% sobre el precio base para obtener
     * el precio de lista, el cálculo para volver al precio en efectivo dividiendo por 1.10
     * devuelve exactamente el valor base original (sin pérdidas ni diferencias por aplicar porcentajes inversos erróneos).
     */
    public function test_reversing_10_percent_markup_returns_exact_base_price(): void
    {
        $basePrices = [
            10.0,
            49.50,
            100.0,
            199.99,
            1000.0,
            3499.0,
            15000.0,
            50000.0,
            100000.0,
            150000.0,
            999999.0,
        ];

        foreach ($basePrices as $basePrice) {
            // Precio de lista con recargo del 10%
            $listPrice = round($basePrice * 1.10, 2);

            // Reversión dividiendo por 1.10
            $cashPrice = $this->pricingService->calculateCashPrice($listPrice);

            $this->assertEquals(
                $basePrice,
                $cashPrice,
                "Failed for base price {$basePrice}: list price was {$listPrice}, got {$cashPrice}"
            );
        }
    }

    /**
     * Demuestra y verifica el error matemático del cálculo anterior (restar 10% o multiplicar por 0.90)
     * frente al cálculo corregido (dividir por 1.10).
     */
    public function test_mathematical_proof_reversing_markup_vs_faulty_subtraction(): void
    {
        $basePrice = 100000.00;

        // 1. Al aplicar el 10% de recargo para precio de lista:
        $listPrice = round($basePrice * 1.10, 2); // 110.000,00
        $this->assertEquals(110000.00, $listPrice);

        // 2. FÓRMULA ERRÓNEA ANTERIOR: restar 10% ($listPrice * 0.90)
        // 110.000 * 0.90 = 99.000 (pérdida de $1.000 respecto al base)
        $faultyCashPrice = $listPrice * 0.90;
        $faultyDifference = $basePrice - $faultyCashPrice;
        $this->assertEquals(99000.00, $faultyCashPrice);
        $this->assertEquals(1000.00, $faultyDifference, 'Faulty formula loses $1.000 (1% of base price)');

        // 3. FÓRMULA CORREGIDA: dividir por 1.10
        // 110.000 / 1.10 = 100.000 (exactamente el precio base original)
        $correctCashPrice = $this->pricingService->calculateCashPrice($listPrice);
        $correctDifference = $basePrice - $correctCashPrice;

        $this->assertEquals(100000.00, $correctCashPrice);
        $this->assertEquals(0.00, $correctDifference, 'Correct formula must return exact base price with 0 loss');
    }

    /**
     * Verifica el cálculo del total en efectivo del carrito con múltiples artículos y cantidades.
     */
    public function test_cart_subtotal_cash_calculation_with_multiple_items(): void
    {
        // Carrito con 3 productos:
        // Producto 1: base $15.000 x 2 = $30.000 (lista: $16.500 x 2 = $33.000)
        // Producto 2: base $45.000 x 1 = $45.000 (lista: $49.500 x 1 = $49.500)
        // Producto 3: base $5.000  x 3 = $15.000 (lista: $5.500  x 3 = $16.500)
        $items = [
            ['base_price' => 15000.00, 'quantity' => 2],
            ['base_price' => 45000.00, 'quantity' => 1],
            ['base_price' => 5000.00,  'quantity' => 3],
        ];

        $expectedBaseTotal = 0;
        $cartSubtotalList = 0;

        foreach ($items as $item) {
            $baseLine = $item['base_price'] * $item['quantity'];
            $listLine = round($item['base_price'] * 1.10, 2) * $item['quantity'];

            $expectedBaseTotal += $baseLine;
            $cartSubtotalList += $listLine;
        }

        // Subtotal de lista: 33.000 + 49.500 + 16.500 = 99.000
        $this->assertEquals(99000.00, $cartSubtotalList);
        // Base esperado: 30.000 + 45.000 + 15.000 = 90.000
        $this->assertEquals(90000.00, $expectedBaseTotal);

        // Cálculo en efectivo sobre el subtotal del carrito
        $cartCashTotal = $this->pricingService->calculateCashPrice($cartSubtotalList);
        $savings = $cartSubtotalList - $cartCashTotal;

        $this->assertEquals($expectedBaseTotal, $cartCashTotal, 'Cash subtotal must equal expected base total');
        $this->assertEquals(9000.00, $savings, 'Savings in cash must be exactly the 10% markup amount');
    }

    /**
     * Verifica casos borde: subtotal null, cero o negativo.
     */
    public function test_edge_cases_zero_negative_and_null(): void
    {
        $this->assertSame(0.0, $this->pricingService->calculateCashPrice(null));
        $this->assertSame(0.0, $this->pricingService->calculateCashPrice(0.0));
        $this->assertSame(0.0, $this->pricingService->calculateCashPrice(-150.0));
    }

    /**
     * Verifica que en un rango exhaustivo de precios continuos (1 a 10.000 centavos),
     * la reversión del 10% mediante división por 1.10 devuelve exactamente el precio base original
     * sin 1 centavo de pérdida ni desvío.
     */
    public function test_continuous_range_exhaustion_guarantees_zero_loss(): void
    {
        for ($cents = 100; $cents <= 1000000; $cents += 50) { // Pasos de 50 centavos hasta $10.000
            $basePrice = $cents / 100.0;
            $listPrice = round($basePrice * 1.10, 2);
            $cashPrice = $this->pricingService->calculateCashPrice($listPrice);

            $this->assertEquals(
                $basePrice,
                $cashPrice,
                "Failed precision for base {$basePrice}"
            );
        }
    }

    /**
     * Verifica que el archivo de vista cart-panel.blade.php no contenga el multiplicador erróneo 0.90
     * y utilice la fórmula corregida (/ 1.10 y calculateCashPrice).
     */
    public function test_cart_panel_blade_does_not_contain_faulty_multiplier(): void
    {
        $path = dirname(__DIR__, 2) . '/resources/views/livewire/cart-panel.blade.php';
        $this->assertFileExists($path);

        $content = file_get_contents($path);

        $this->assertStringNotContainsString('0.90', $content, 'cart-panel should not use faulty 0.90 multiplier');
        $this->assertStringNotContainsString('0.9', $content, 'cart-panel should not use faulty 0.9 multiplier');
        $this->assertStringContainsString('/ 1.10', $content, 'cart-panel should divide by 1.10');
        $this->assertStringContainsString('calculateCashPrice', $content, 'cart-panel should call calculateCashPrice');
    }

    /**
     * Verifica que el precio en efectivo calculado para el subtotal del carrito
     * coincida con la fórmula requerida y el servicio centralizado.
     */
    public function test_cart_subtotal_cash_matches_checkout_logic(): void
    {
        // Ejemplo: subtotal $220.000 de lista -> $200.000 en efectivo
        $subtotal = 220000.00;
        $cashTotal = $this->pricingService->calculateCashPrice($subtotal);
        $savings = $subtotal - $cashTotal;

        $this->assertEquals(200000.00, $cashTotal);
        $this->assertEquals(20000.00, $savings);

        // Verifica equivalencia con checkout ($subtotal / 1.10)
        $checkoutCashTotal = round($subtotal / 1.10, 2);
        $this->assertEquals($checkoutCashTotal, $cashTotal, 'Cart panel cash total must match checkout formula');
    }
}
