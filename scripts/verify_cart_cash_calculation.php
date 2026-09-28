<?php

/**
 * Script de verificación automatizada del cálculo de precio en efectivo en el carrito.
 *
 * Ejecución: php scripts/verify_cart_cash_calculation.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$pricingService = app(\App\Services\PricingService::class);

echo "========================================================\n";
echo " VERIFICACIÓN AUTOMATIZADA: CÁLCULO PRECIO EN EFECTIVO \n";
echo "========================================================\n\n";

$allPassed = true;
$checks = 0;

function assertCheck($condition, $description, &$allPassed, &$checks) {
    $checks++;
    if ($condition) {
        echo "  [PASS] {$description}\n";
    } else {
        echo "  [FAIL] {$description}\n";
        $allPassed = false;
    }
}

// 1. Verificación matemática: reversión del 10%
echo "1. Verificación matemática de reversión de recargo 10%:\n";
$testPrices = [10.0, 49.50, 100.0, 199.99, 1000.0, 3499.0, 15000.0, 50000.0, 100000.0, 150000.0, 999999.0];

foreach ($testPrices as $basePrice) {
    $listPrice = round($basePrice * 1.10, 2);
    $cashPrice = $pricingService->calculateCashPrice($listPrice);
    assertCheck(
        abs($cashPrice - $basePrice) < 0.001,
        "Base \${$basePrice} -> Lista \${$listPrice} -> Efectivo \${$cashPrice} (exacto, sin pérdida)",
        $allPassed,
        $checks
    );
}

// 2. Demostración matemática: fórmula errónea anterior (* 0.90) vs fórmula corregida (/ 1.10)
echo "\n2. Demostración matemática de la asimetría porcentual:\n";
$baseExample = 100000.00;
$listExample = round($baseExample * 1.10, 2); // 110.000,00
$faultyCash = $listExample * 0.90; // 99.000,00 (pérdida de $1.000)
$correctCash = $pricingService->calculateCashPrice($listExample); // 100.000,00

assertCheck(
    $faultyCash == 99000.00 && ($baseExample - $faultyCash) == 1000.00,
    "Fórmula anterior (* 0.90) pierde \$1.000 (devuelve 99% del base)",
    $allPassed,
    $checks
);

assertCheck(
    $correctCash == 100000.00 && ($baseExample - $correctCash) == 0.00,
    "Fórmula corregida (/ 1.10) recupera el 100% del precio base (\$100.000,00 exactos)",
    $allPassed,
    $checks
);

// 3. Verificación de contenido en cart-panel.blade.php
echo "\n3. Verificación estática de cart-panel.blade.php:\n";
$cartPanelContent = file_get_contents(__DIR__ . '/../resources/views/livewire/cart-panel.blade.php');

assertCheck(
    strpos($cartPanelContent, '0.90') === false,
    "No contiene '0.90'",
    $allPassed,
    $checks
);

assertCheck(
    strpos($cartPanelContent, '0.9') === false,
    "No contiene '0.9'",
    $allPassed,
    $checks
);

assertCheck(
    strpos($cartPanelContent, '/ 1.10') !== false,
    "Contiene '/ 1.10' en Alpine.js getter globalCashTotal",
    $allPassed,
    $checks
);

assertCheck(
    strpos($cartPanelContent, 'calculateCashPrice') !== false,
    "Llama a calculateCashPrice() en calculateSubtotal",
    $allPassed,
    $checks
);

// 4. Casos límite
echo "\n4. Casos límite:\n";
assertCheck(
    $pricingService->calculateCashPrice(0.0) === 0.0,
    "Subtotal 0 devuelve 0.0",
    $allPassed,
    $checks
);

assertCheck(
    $pricingService->calculateCashPrice(-50.0) === 0.0,
    "Subtotal negativo devuelve 0.0",
    $allPassed,
    $checks
);

echo "\n--------------------------------------------------------\n";
echo " Total verificaciones: {$checks} | " . ($allPassed ? "TODAS EXITOSAS [OK]" : "HUBO FALLAS [ERROR]") . "\n";
echo "========================================================\n";

exit($allPassed ? 0 : 1);
