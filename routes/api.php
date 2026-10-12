<?php

use App\Http\Controllers\Api\PosCatalogApiController;
use App\Http\Controllers\Api\PosInventoryApiController;
use App\Http\Middleware\AuthenticatePosApi;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application.
| These routes are loaded by bootstrap/app.php under the "api" middleware group.
|
*/

Route::prefix('v1')->middleware([AuthenticatePosApi::class])->group(function () {
    // Health & Status
    Route::get('health', [PosInventoryApiController::class, 'health'])->name('api.pos.health');
    Route::get('store/status', [PosInventoryApiController::class, 'health'])->name('api.pos.status');

    // Inventario (POS ➔ Web)
    Route::post('inventory/update', [PosInventoryApiController::class, 'updateInventory'])->name('api.pos.inventory.update');

    // Consulta y Cancelación de Órdenes
    Route::get('orders/{order_id}', [PosInventoryApiController::class, 'showOrder'])->name('api.pos.orders.show');
    Route::post('orders/{order_id}/cancel', [PosInventoryApiController::class, 'cancelOrder'])->name('api.pos.orders.cancel');

    // Sincronización Inicial de Catálogo (Onboarding)
    Route::get('catalog/export', [PosCatalogApiController::class, 'exportCatalog'])->name('api.pos.catalog.export');
    Route::post('catalog/bulk-sync', [PosCatalogApiController::class, 'bulkSync'])->name('api.pos.catalog.bulk_sync');
});
