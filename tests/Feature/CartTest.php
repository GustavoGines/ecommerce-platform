<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_can_load_items_from_session()
    {
        $product = Product::factory()->create(['stock' => 10, 'retail_price' => 100]);
        
        $cartService = app(CartService::class);
        $cartService->addItem($product->id, 2);

        $component = Volt::test('cart-panel');

        $component->assertSet('subtotal', 200)
                  ->assertSee($product->name);
    }

    public function test_cart_can_increase_and_decrease_quantity()
    {
        $product = Product::factory()->create(['stock' => 10, 'retail_price' => 100]);
        
        $cartService = app(CartService::class);
        $cartService->addItem($product->id, 2);

        $component = Volt::test('cart-panel')
            ->call('updateQuantity', $product->id, 'increment')
            ->call('loadCart');
            
        $component->assertSet('subtotal', 300);

        $component->call('updateQuantity', $product->id, 'decrement')
                  ->call('loadCart');
        $component->assertSet('subtotal', 200);
    }

    public function test_cart_cannot_increment_beyond_stock()
    {
        $product = Product::factory()->create(['stock' => 2, 'retail_price' => 100]);
        
        $cartService = app(CartService::class);
        $cartService->addItem($product->id, 2);

        $component = Volt::test('cart-panel')
            ->call('updateQuantity', $product->id, 'increment')
            ->assertDispatched('notify'); // Debe despachar notificación de error

        $component->assertSet('subtotal', 200); // El subtotal no debe aumentar
    }

    public function test_cart_can_remove_item()
    {
        $product1 = Product::factory()->create(['stock' => 10, 'retail_price' => 100]);
        $product2 = Product::factory()->create(['stock' => 10, 'retail_price' => 50]);
        
        $cartService = app(CartService::class);
        $cartService->addItem($product1->id, 1);
        $cartService->addItem($product2->id, 1);

        $component = Volt::test('cart-panel')
            ->assertSet('subtotal', 150)
            ->call('removeItem', $product1->id)
            ->call('loadCart')
            ->assertSet('subtotal', 50);
            
        $this->assertArrayNotHasKey($product1->id, session('cart', []));
    }

    public function test_cart_calculates_subtotal_cash_by_dividing_by_1_10()
    {
        // Producto con precio de lista $110 (base original $100 + 10% recargo)
        $product = Product::factory()->create(['stock' => 10, 'retail_price' => 110]);

        $cartService = app(CartService::class);
        $cartService->addItem($product->id, 2); // 2 unidades -> subtotal lista $220

        $component = Volt::test('cart-panel');

        // Subtotal de lista debe ser 220
        $component->assertSet('subtotal', 220);
        // Subtotal en efectivo debe ser exactamente 200 (220 / 1.10 = 200 exactos, NO 220 * 0.90 = 198)
        $component->assertSet('subtotalCash', 200.0);
    }

    public function test_cart_resets_subtotal_cash_to_zero_when_cart_is_empty()
    {
        $component = Volt::test('cart-panel');

        $component->assertSet('subtotal', 0)
                  ->assertSet('subtotalCash', 0);
    }

    public function test_cart_updates_subtotal_cash_on_quantity_change_and_removal()
    {
        $product1 = Product::factory()->create(['stock' => 10, 'retail_price' => 110]); // cash 100
        $product2 = Product::factory()->create(['stock' => 10, 'retail_price' => 220]); // cash 200

        $cartService = app(CartService::class);
        $cartService->addItem($product1->id, 1);
        $cartService->addItem($product2->id, 1);

        $component = Volt::test('cart-panel');
        // Total list = 330, Total cash = 300
        $component->assertSet('subtotal', 330)
                  ->assertSet('subtotalCash', 300.0);

        // Increment product 1 (now 2 x 110 = 220 + 220 = 440 list -> 400 cash)
        $component->call('updateQuantity', $product1->id, 'increment')
                  ->call('loadCart');
        $component->assertSet('subtotal', 440)
                  ->assertSet('subtotalCash', 400.0);

        // Remove product 1 (now only product 2: 220 list -> 200 cash)
        $component->call('removeItem', $product1->id)
                  ->call('loadCart');
        $component->assertSet('subtotal', 220)
                  ->assertSet('subtotalCash', 200.0);

        // Clear cart -> resets to 0
        $component->call('clearCart')
                  ->assertSet('subtotal', 0)
                  ->assertSet('subtotalCash', 0);
    }

    public function test_cart_renders_cash_price_and_savings_for_g3_tenant()
    {
        tenancy()->end();
        if (file_exists(database_path('tenantg3'))) {
            @unlink(database_path('tenantg3'));
        }
        $g3 = \App\Models\Tenant::create(['id' => 'g3']);
        $g3->domains()->create(['domain' => 'g3.localhost']);
        tenancy()->initialize($g3);

        try {
            $product = Product::factory()->create(['stock' => 10, 'retail_price' => 110]);
            $cartService = app(CartService::class);
            $cartService->addItem($product->id, 2); // 2 x 110 = 220 list -> 200 cash

            $component = Volt::test('cart-panel');

            $component->assertSee('Total de Lista')
                      ->assertSee('Efectivo / Transf.')
                      ->assertSee('¡Ahorras en Efectivo!')
                      ->assertSee('$200.00')
                      ->assertSee('$20.00')
                      ->assertDontSee('$198.00')
                      ->assertDontSee('$22.00');
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
            if (isset($g3)) {
                $g3->delete();
            }
            if (file_exists(database_path('tenantg3'))) {
                @unlink(database_path('tenantg3'));
            }
            if ($this->tenant) {
                tenancy()->initialize($this->tenant);
            }
        }
    }

    public function test_cart_handles_product_with_zero_wholesale_price_without_errors()
    {
        $product = Product::factory()->create([
            'stock' => 5,
            'retail_price' => 150,
            'wholesale_price' => 0,
        ]);

        $cartService = app(CartService::class);
        $cartService->addItem($product->id, 1);

        $component = Volt::test('cart-panel');

        $component->assertSet('subtotal', 150)
                  ->assertSee($product->name);
    }

    public function test_cart_renders_zero_totals_and_handles_empty_state_without_errors()
    {
        $component = Volt::test('cart-panel');

        $component->assertSet('subtotal', 0)
                  ->assertSet('subtotalCash', 0)
                  ->assertSee('Tu carrito está vacío.');
    }
}
