<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosCatalogSyncTest extends TestCase
{
    use RefreshDatabase;

    protected string $validToken = 'test_catalog_token_456';

    protected function setUp(): void
    {
        parent::setUp();

        $setting = StoreSetting::first() ?? new StoreSetting();
        $setting->store_name = 'JCG Electrónica';
        $setting->pos_sync_enabled = true;
        $setting->pos_api_token = $this->validToken;
        $setting->save();
    }

    private function headers(): array
    {
        return [
            'Authorization' => "Bearer {$this->validToken}",
            'X-Tenant-ID'   => 'jcg',
        ];
    }

    public function test_can_export_full_catalog_for_pos_initial_import()
    {
        $category = Category::factory()->create(['name' => 'Herramientas Eléctricas']);

        $product1 = Product::factory()->create([
            'name'         => 'Taladro Percutor 13mm',
            'sku'          => 'TAL-PER-01',
            'retail_price' => 15000.00,
            'cost_price'   => 9500.00,
            'stock'        => 12,
            'category_id'  => $category->id,
        ]);

        $product2 = Product::factory()->create([
            'name'         => 'Amoladora Angular 115mm',
            'sku'          => 'AMO-ANG-02',
            'retail_price' => 22000.00,
            'cost_price'   => 14000.00,
            'stock'        => 8,
            'category_id'  => $category->id,
        ]);

        $response = $this->withHeaders($this->headers())->getJson('/api/v1/catalog/export');

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'total'   => 2,
                 ]);

        $products = $response->json('products');
        $this->assertCount(2, $products);
        $this->assertEquals('TAL-PER-01', $products[0]['sku']);
        $this->assertEquals('Taladro Percutor 13mm', $products[0]['name']);
        $this->assertEquals(15000.00, $products[0]['price']);
        $this->assertEquals('Herramientas Eléctricas', $products[0]['category']);

        $this->assertDatabaseHas('pos_sync_logs', [
            'direction'  => 'outbound',
            'event_type' => 'catalog.export',
            'status'     => 'success',
        ]);
    }

    public function test_can_bulk_sync_catalog_from_pos_into_web()
    {
        $payload = [
            'products' => [
                [
                    'sku'        => 'POS-PROD-01',
                    'name'       => 'Soldadora Inverter 200A',
                    'price'      => 85000.00,
                    'cost_price' => 55000.00,
                    'stock'      => 5,
                    'category'   => 'Maquinaria',
                ],
                [
                    'sku'        => 'POS-PROD-02',
                    'name'       => 'Máscara Fotosensible',
                    'price'      => 18000.00,
                    'cost_price' => 11000.00,
                    'stock'      => 20,
                    'category'   => 'Protección',
                ],
            ],
        ];

        $response = $this->withHeaders($this->headers())->postJson('/api/v1/catalog/bulk-sync', $payload);

        $response->assertStatus(200)
                 ->assertJson([
                     'success'       => true,
                     'synced_count'  => 2,
                     'created_count' => 2,
                     'updated_count' => 0,
                 ]);

        $this->assertDatabaseHas('products', [
            'sku'          => 'POS-PROD-01',
            'name'         => 'Soldadora Inverter 200A',
            'retail_price' => 85000.00,
            'stock'        => 5,
        ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'Maquinaria',
        ]);

        // Si se vuelve a enviar con precio y stock actualizado, debe hacer update
        $updatePayload = [
            'products' => [
                [
                    'sku'        => 'POS-PROD-01',
                    'name'       => 'Soldadora Inverter 200A',
                    'price'      => 90000.00,
                    'stock'      => 9,
                ],
            ],
        ];

        $updateResponse = $this->withHeaders($this->headers())->postJson('/api/v1/catalog/bulk-sync', $updatePayload);

        $updateResponse->assertStatus(200)
                       ->assertJson([
                           'success'       => true,
                           'synced_count'  => 1,
                           'created_count' => 0,
                           'updated_count' => 1,
                       ]);

        $this->assertEquals(90000.00, Product::where('sku', 'POS-PROD-01')->first()->retail_price);
        $this->assertEquals(9, Product::where('sku', 'POS-PROD-01')->first()->stock);
    }
}
