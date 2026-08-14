<?php

use Livewire\Volt\Component;

new class extends Component {
    public $product;
    public $quantity = 1;
    public $compact = false;

    public function incrementQuantity()
    {
        if ($this->quantity < $this->product->stock) {
            $this->quantity++;
        }
    }

    public function decrementQuantity()
    {
        if ($this->quantity > 1) {
            $this->quantity--;
        }
    }

    public function addToCart()
    {
        $cartService = app(\App\Services\CartService::class);
        
        if ($cartService->addItem($this->product->id, $this->quantity)) {
            $cart = $cartService->getCartItemsArray();
            $this->dispatch('cart-badge-updated', count: array_sum($cart));
            $this->dispatch('cart-updated');
            $this->dispatch('notify', message: 'Producto añadido al carrito');
        } else {
            $this->dispatch('notify', message: 'No hay stock suficiente', type: 'error');
            // Revert optimistic UI on error
            $cart = $cartService->getCartItemsArray();
            $this->dispatch('cart-badge-updated', count: array_sum($cart));
        }
    }
}; ?>

<div class="{{ $compact ? 'mt-3' : 'mt-4' }} w-full">
    @if($product->stock > 0)
        <div class="flex {{ $compact ? 'flex-col items-center gap-2' : 'items-center gap-4' }} w-full">
            <!-- Selector de cantidad -->
            <div x-data="{ qty: @entangle('quantity') }" class="flex items-center border border-zinc-800 rounded-xl bg-zinc-900 p-1 flex-shrink-0 {{ $compact ? 'w-full' : 'w-32 sm:w-36' }} justify-between">
                <button @click="if(qty > 1) qty--" type="button" class="{{ $compact ? 'w-8 h-8' : 'w-10 h-10' }} flex items-center justify-center rounded-lg bg-zinc-800 text-gray-300 hover:text-white hover:bg-zinc-700 shadow-sm transition-colors disabled:opacity-50 disabled:cursor-not-allowed" :disabled="qty <= 1">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4" /></svg>
                </button>
                <input type="text"
                       inputmode="numeric" 
                       pattern="[0-9]*"
                       x-model.number="qty"
                       @change="if(!qty || qty < 1) qty = 1; if(qty > {{ $product->stock }}) qty = {{ $product->stock }}"
                       class="{{ $compact ? 'w-12 text-sm px-0' : 'w-16 text-base px-1' }} text-center font-bold text-white bg-transparent border-0 border-transparent outline-none focus:ring-0 focus:border-transparent focus:outline-none shadow-none p-0 m-0">
                <button @click="if(qty < {{ $product->stock }}) { qty++ } else { $dispatch('notify', {message: 'Límite de stock disponible ('+{{ $product->stock }}+') alcanzado', type: 'error'}) }" type="button" class="{{ $compact ? 'w-8 h-8' : 'w-10 h-10' }} flex items-center justify-center rounded-lg bg-zinc-800 text-gray-300 hover:text-white hover:bg-zinc-700 shadow-sm transition-colors" :class="{ 'opacity-50 cursor-not-allowed': qty >= {{ $product->stock }} }">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                </button>
            </div>

            <!-- Botón Añadir -->
            <button
                x-data="{ status: 'idle' }"
                @click="
                    if(status === 'loading') return;
                    status = 'loading';
                    $dispatch('cart-added-local', { qty: $wire.quantity });
                    setTimeout(() => { if(status === 'loading') status = 'success'; setTimeout(() => { if(status === 'success') status = 'idle' }, 2000); }, 300);
                    $dispatch('add-to-cart-action', { productId: {{ $product->id }}, quantity: parseInt($wire.quantity) || 1 });
                "
                :disabled="status === 'loading'"
                class="w-full sm:flex-1 flex items-center justify-center {{ $compact ? 'py-2.5 px-2' : 'py-3.5 px-4' }} rounded-xl text-white font-bold tracking-wide transition-all shadow-md hover:-translate-y-0.5 active:translate-y-0 disabled:opacity-70 disabled:cursor-not-allowed group relative overflow-hidden"
                style="background-color: var(--color-primary); box-shadow: 0 4px 10px -2px var(--color-primary-glow);"
                onmouseover="this.style.backgroundColor='var(--color-primary-hover)'"
                onmouseout="this.style.backgroundColor='var(--color-primary)'">
                
                <!-- Estado Normal -->
                <div x-show="status === 'idle'" class="flex items-center justify-center w-full transition-all">
                    <svg class="w-5 h-5 {{ $compact ? 'mr-1 sm:mr-2' : 'mr-2' }} transition-transform group-hover:scale-110" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <span class="whitespace-nowrap {{ $compact ? 'text-sm font-black' : 'text-sm sm:text-base' }}">
                        {{ $compact ? 'Añadir' : 'Añadir al Carrito' }}
                    </span>
                </div>

                <!-- Estado Cargando -->
                <div x-show="status === 'loading'" x-cloak class="flex items-center justify-center w-full">
                    <svg class="animate-spin -ml-1 {{ $compact ? 'mr-1 sm:mr-2' : 'mr-3' }} h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="whitespace-nowrap {{ $compact ? 'text-sm font-black' : 'text-sm sm:text-base' }}">
                        {{ $compact ? '...' : 'Añadiendo...' }}
                    </span>
                </div>

                <!-- Estado Éxito -->
                <div x-show="status === 'success'" x-cloak class="flex items-center justify-center w-full text-green-300">
                    <svg class="w-5 h-5 {{ $compact ? 'mr-1 sm:mr-2' : 'mr-2' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                    </svg>
                    <span class="whitespace-nowrap {{ $compact ? 'text-sm font-black' : 'text-sm sm:text-base' }}">
                        ¡Añadido!
                    </span>
                </div>
            </button>
        </div>
    @else
        <div class="flex {{ $compact ? 'flex-col items-center gap-2' : 'items-center gap-4' }} w-full opacity-60">
            <!-- Selector inactivo para mantener la misma altura y diseño -->
            <div class="flex items-center border border-zinc-800/50 rounded-xl bg-zinc-900/50 p-1 flex-shrink-0 {{ $compact ? 'w-full' : 'w-32 sm:w-36' }} justify-between">
                <button disabled type="button" class="{{ $compact ? 'w-8 h-8' : 'w-10 h-10' }} flex items-center justify-center rounded-lg bg-zinc-800/30 text-zinc-600 cursor-not-allowed">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4" /></svg>
                </button>
                <input disabled type="text" value="0" class="{{ $compact ? 'w-12 text-sm px-0' : 'w-16 text-base px-1' }} text-center font-bold text-zinc-600 bg-transparent border-0 p-0 m-0 cursor-not-allowed">
                <button disabled type="button" class="{{ $compact ? 'w-8 h-8' : 'w-10 h-10' }} flex items-center justify-center rounded-lg bg-zinc-800/30 text-zinc-600 cursor-not-allowed">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                </button>
            </div>
            
            <button disabled class="w-full sm:flex-1 flex items-center justify-center {{ $compact ? 'py-2.5 px-2' : 'py-3.5 px-4' }} bg-zinc-800/50 text-zinc-500 rounded-xl font-bold tracking-wide cursor-not-allowed border border-zinc-700/50">
                Agotado
            </button>
        </div>
    @endif
</div>
