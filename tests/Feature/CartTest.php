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
}
