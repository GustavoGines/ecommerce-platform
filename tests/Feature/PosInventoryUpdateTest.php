<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PosSyncLog;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosInventoryUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected string $validToken = 'test_pos_token_123';

    protected function setUp(): void
    {
        parent::setUp();

        $setting = StoreSetting::first() ?? new StoreSetting();
        $setting->store_name = 'G3 Tecnología';
        $setting->pos_sync_enabled = true;
        $setting->pos_api_token = $this->validToken;
        $setting->save();
    }

    private function headers(): array
    {
        return [
            'Authorization' => "Bearer {$this->validToken}",
            'X-Tenant-ID'   => 'g3',
        ];
    }

    public function test_can_update_stock_by_numeric_id()
    {
        $product = Product::factory()->create([
            'stock' => 10,
            'sku'   => 'SKU-NUM-1',
        ]);

        $response = $this->withHeaders($this->headers())->postJson('/api/v1/inventory/update', [
            'product_id' => $product->id,
            'quantity'   => 25.0,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success'       => true,
                     'product_id'    => $product->id,
                     'updated_stock' => 25.0,
                     'status'        => 'instock',
                 ]);

        $this->assertEquals(25, $product->fresh()->stock);

        // Verifica que se guardó en el log de auditoría
        $this->assertDatabaseHas('pos_sync_logs', [
            'direction'  => 'inbound',
            'event_type' => 'inventory.update',
            'status'     => 'success',
        ]);
    }

    public function test_can_update_stock_by_sku()
    {
        $product = Product::factory()->create([
            'stock' => 5,
            'sku'   => 'TAL-PER-13',
        ]);

        $response = $this->withHeaders($this->headers())->postJson('/api/v1/inventory/update', [
            'product_id' => 'TAL-PER-13',
            'quantity'   => 18.0,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success'       => true,
                     'product_id'    => $product->id,
                     'updated_stock' => 18.0,
                     'status'        => 'instock',
                 ]);

        $this->assertEquals(18, $product->fresh()->stock);
    }

    public function test_can_update_stock_by_exact_name_for_g3_provider_list()
    {
        $product = Product::factory()->create([
            'name'  => 'Notebook Asus Vivobook 15 i5',
            'sku'   => null, // Sin SKU (típico en imports directos de Sheets)
            'stock' => 0,
        ]);

        $response = $this->withHeaders($this->headers())->postJson('/api/v1/inventory/update', [
            'product_id' => 'Notebook Asus Vivobook 15 i5',
            'quantity'   => 7.0,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success'       => true,
                     'product_id'    => $product->id,
                     'name'          => 'Notebook Asus Vivobook 15 i5',
                     'updated_stock' => 7.0,
                     'status'        => 'instock',
                 ]);

        $this->assertEquals(7, $product->fresh()->stock);
    }

    public function test_anti_overselling_invariant_quantity_zero_or_negative_marks_outofstock()
    {
        $product = Product::factory()->create([
            'stock' => 15,
            'sku'   => 'PROD-OUT-1',
        ]);

        $response = $this->withHeaders($this->headers())->postJson('/api/v1/inventory/update', [
            'product_id' => $product->id,
            'quantity'   => 0,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success'       => true,
                     'updated_stock' => 0,
                     'status'        => 'outofstock',
                 ]);

        $this->assertEquals(0, $product->fresh()->stock);

        // Si se envía negativo, también debe quedar en 0
        $responseNegative = $this->withHeaders($this->headers())->postJson('/api/v1/inventory/update', [
            'product_id' => $product->id,
            'quantity'   => -5.0,
        ]);

        $responseNegative->assertStatus(200)
                         ->assertJson([
                             'success'       => true,
                             'updated_stock' => 0,
                             'status'        => 'outofstock',
                         ]);

        $this->assertEquals(0, $product->fresh()->stock);
    }

    public function test_can_query_order_detail_by_id_or_formatted_string()
    {
        $user = User::factory()->create([
            'name'  => 'Juan Pérez',
            'email' => 'juan@test.com',
            'phone' => '1122334455',
        ]);

        $product = Product::factory()->create([
            'name' => 'Mouse Gamer RGB',
            'sku'  => 'MOU-RGB-01',
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status'  => 'pendiente',
            'total'   => 5000.00,
            'phone'   => '1122334455',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'quantity'   => 2,
            'price'      => 2500.00,
        ]);

        // Consulta usando string con prefijo "ORD-X"
        $response = $this->withHeaders($this->headers())->getJson("/api/v1/orders/ORD-{$order->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'success'      => true,
                     'order_id'     => "ORD-{$order->id}",
                     'order_number' => $order->id,
                     'status'       => 'pendiente',
                     'total'        => 5000.00,
                     'user'         => [
                         'name'  => 'Juan Pérez',
                         'email' => 'juan@test.com',
                     ],
                 ]);

        $this->assertCount(1, $response->json('items'));
        $this->assertEquals('MOU-RGB-01', $response->json('items.0.sku'));
    }

    public function test_can_cancel_order_from_pos_and_stock_is_restored()
    {
        $product = Product::factory()->create(['stock' => 20]);

        $order = Order::factory()->create([
            'status' => 'pendiente',
            'total'  => 3000,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'quantity'   => 4,
            'price'      => 750,
        ]);

        $response = $this->withHeaders($this->headers())->postJson("/api/v1/orders/{$order->id}/cancel", [
            'reason' => 'Cancelado desde mostrador por quiebre de stock',
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success'  => true,
                     'status'   => 'cancelado',
                     'reason'   => 'Cancelado desde mostrador por quiebre de stock',
                 ]);

        $this->assertEquals('cancelado', $order->fresh()->status);
        $this->assertEquals(24, $product->fresh()->stock, 'Stock should be incremented from 20 to 24');

        $this->assertDatabaseHas('pos_sync_logs', [
            'direction'  => 'inbound',
            'event_type' => 'order.cancel',
            'status'     => 'success',
        ]);
    }
}
