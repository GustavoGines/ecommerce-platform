<?php

use Livewire\Volt\Component;
use App\Models\Product;
use App\Services\PricingService;
use Livewire\Attributes\On;

new class extends Component {
    public $cart = [];
    public $subtotal = 0;
    public $theme = 'stealth';
    
    public function mount()
    {
        $this->loadCart();
        $settings = \App\Models\StoreSetting::getSettings();
        if ($settings) {
            $this->theme = $settings->theme_name ?? 'stealth';
        }
    }

    #[On('cart-updated')]
    public function loadCart()
    {
        $cartService = app(\App\Services\CartService::class);
        $this->cart = $cartService->getCartItemsArray();
        
        if (count($this->cart) == 0) {
            $this->subtotal = 0;
        }
    }

    #[On('add-to-cart-action')]
    public function addItemToCart($productId, $quantity)
    {
        $cartService = app(\App\Services\CartService::class);
        
        if ($cartService->addItem($productId, $quantity)) {
            $this->loadCart();
            $this->dispatch('cart-badge-updated', count: array_sum($this->cart));
            $this->dispatch('notify', message: 'Producto añadido al carrito');
        } else {
            $this->dispatch('notify', message: 'No hay stock suficiente', type: 'error');
            $this->dispatch('cart-badge-updated', count: array_sum($this->cart));
        }
    }

    public function getPrice($product, $quantity): float
    {
        // DRY-01: Lógica centralizada en PricingService
        return app(PricingService::class)->unitPrice($product, $quantity, auth()->user());
    }

    public $subtotalCash = 0;

    public function calculateSubtotal($products)
    {
        $this->subtotal = 0;
        $totalCartQuantity = array_sum($this->cart);

        foreach ($this->cart as $productId => $quantity) {
            if (isset($products[$productId])) {
                $product = $products[$productId];
                $price = app(PricingService::class)->unitPrice($product, $quantity, auth()->user(), $totalCartQuantity);
                $this->subtotal += $price * $quantity;
            }
        }
        $this->subtotalCash = $this->subtotal * 0.90;
    }

    public function updateQuantity($productId, $action)
    {
        $cartService = app(\App\Services\CartService::class);
        $success = $cartService->updateQuantity($productId, $action);
        
        if (!$success && $action === 'increment') {
            $this->dispatch('notify', message: 'Límite de stock alcanzado', type: 'error');
        } else {
            $this->loadCart();
            $this->dispatch('cart-badge-updated', count: array_sum($this->cart));
        }
    }

    public function setQuantity($productId, $quantity)
    {
        $cartService = app(\App\Services\CartService::class);
        $cartService->setQuantity($productId, $quantity);
        $this->loadCart();
        $this->dispatch('cart-badge-updated', count: array_sum($this->cart));
    }

    public function removeItem($productId)
    {
        $cartService = app(\App\Services\CartService::class);
        $cartService->removeItem($productId);
        $this->loadCart();
        $this->dispatch('cart-badge-updated', count: array_sum($this->cart));
    }

    public function clearCart()
    {
        $cartService = app(\App\Services\CartService::class);
        $cartService->clear();
        $this->loadCart();
        $this->dispatch('cart-badge-updated', count: array_sum($this->cart));
    }

    public function goToCheckout()
    {
        if (!auth()->check()) {
            return $this->redirect(route('login'), navigate: true);
        }
        return $this->redirect(route('checkout'), navigate: true);
    }

    public function with(): array
    {
        $products = collect();
        
        if (count($this->cart) > 0) {
            $products = Product::whereIn('id', array_keys($this->cart))->get()->keyBy('id');
            $this->calculateSubtotal($products);
        }

        return [
            'products' => $products
        ];
    }
}; ?>

<div x-data="{ 
        isClearing: false,
        itemTotals: {},
        itemQtys: {},
        get globalQty() {
            return Object.values(this.itemQtys).reduce((a, b) => a + (b || 0), 0);
        },
        get globalRetailTotal() {
            return Object.values(this.itemTotals).reduce((a, b) => a + (b.retail || 0), 0);
        },
        get globalCashTotal() {
            return this.globalRetailTotal * 0.90;
        },
        formatMoney(value) {
            return new Intl.NumberFormat('es-AR', {minimumFractionDigits: 0, maximumFractionDigits: 0}).format(value).replace(/,/g, '.');
        }
     }"
     x-show="$store.cart.open"
     @open-cart.window="$store.cart.show()"
     @keydown.escape.window="$store.cart.hide()"
     @cart-badge-updated.window="isClearing = false"
     class="relative z-[100]"
     data-cart-ids="{{ json_encode(array_keys($cart)) }}"
     x-effect="
         let validIds = JSON.parse($el.dataset.cartIds || '[]');
         for(let id in itemTotals) {
             if(!validIds.includes(parseInt(id))) {
                 delete itemTotals[id];
             }
         }
     "
     aria-labelledby="slide-over-title" role="dialog" aria-modal="true" x-cloak>
    
    <!-- Backdrop -->
    <div x-show="$store.cart.open" 
         x-transition:enter="ease-in-out duration-500" 
         x-transition:enter-start="opacity-0" 
         x-transition:enter-end="opacity-100" 
         x-transition:leave="ease-in-out duration-500" 
         x-transition:leave-start="opacity-100" 
         x-transition:leave-end="opacity-0" 
         class="fixed inset-0 transition-opacity {{ $theme === 'luxury' ? 'bg-[#030712]/80 backdrop-blur-md' : 'bg-gray-900/60 dark:bg-[#0b0f19]/80 backdrop-blur-sm' }}" 
         @click="$store.cart.hide()">
    </div>

    <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div class="absolute inset-0 overflow-hidden">
            <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                
                <!-- Slide-over panel -->
                <div x-show="$store.cart.open" 
                     x-transition:enter="transform transition ease-in-out duration-500 sm:duration-700" 
                     x-transition:enter-start="translate-x-full" 
                     x-transition:enter-end="translate-x-0" 
                     x-transition:leave="transform transition ease-in-out duration-500 sm:duration-700" 
                     x-transition:leave-start="translate-x-0" 
                     x-transition:leave-end="translate-x-full" 
                     class="pointer-events-auto w-[100vw] sm:w-screen max-w-md">
                     
                     <div class="flex h-full flex-col relative shadow-2xl transition-colors duration-300 {{ $theme === 'luxury' ? 'bg-[#0a0f1c]/90 backdrop-blur-3xl border-l border-white/5' : 'bg-white dark:bg-gray-900 border-l border-gray-200 dark:border-gray-800' }}" :style="('{{ $theme }}' === 'stealth' && $store.theme.dark) ? 'box-shadow: -10px 0 30px -10px var(--color-primary-glow);' : ''">
                        <div class="flex-1 overflow-y-auto px-4 py-6 sm:px-6">
                            <div class="flex items-start justify-between">
                                <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-3" id="slide-over-title">
                                    Carrito de Compras
                                    @if(count($cart) > 0)
                                        <button @click="isClearing = true; $dispatch('cart-cleared-local'); $wire.clearCart()" type="button" class="text-xs font-bold text-red-500 hover:text-red-400 transition-colors uppercase tracking-wider bg-red-50 dark:bg-red-500/10 px-2 py-1 rounded-lg border border-red-100 dark:border-red-500/20">
                                            Vaciar Todo
                                        </button>
                                    @endif
                                </h2>
                                <div class="ml-3 flex h-7 items-center">
                                    <button type="button" @click="$store.cart.hide()" class="relative -m-2 p-2 text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 transition-colors focus:outline-none">
                                        <span class="absolute -inset-0.5"></span>
                                        <span class="sr-only">Cerrar panel</span>
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div class="mt-8" x-show="!isClearing" x-transition.opacity.duration.300ms>
                                <div class="flow-root">
                                    <ul role="list" class="-my-6 divide-y divide-gray-200 dark:divide-gray-800">
                                        @forelse($cart as $productId => $quantity)
                                            @if(isset($products[$productId]))
                                                @php
                                                    $product = $products[$productId];
                                                    $price = $this->getPrice($product, $quantity);
                                                @endphp
                                                <li wire:key="cart-item-{{ $productId }}" class="flex py-6 transition-all duration-300"
                                                    x-show="!isDeleted"
                                                    x-transition.opacity.duration.300ms
                                                    x-data="{ 
                                                        qty: @entangle('cart.' . $productId), 
                                                        stock: {{ $product->stock }},
                                                        isDeleted: false,
                                                        isVip: {{ (auth()->user() && auth()->user()->isWholesaleCustomer()) ? 'true' : 'false' }},
                                                        minWholesaleQty: {{ $product->wholesale_min_quantity }},
                                                        retailPrice: {{ $product->retail_price }},
                                                        wholesalePrice: {{ $product->wholesale_price }},
                                                        timeout: null,
                                                        
                                                        get isWholesale() {
                                                            let q = parseInt(this.qty) || 0;
                                                            return this.isVip || q >= this.minWholesaleQty || globalQty >= {{ \App\Services\PricingService::GLOBAL_WHOLESALE_MIN }};
                                                        },
                                                        get currentUnitPrice() {
                                                            return this.isWholesale ? this.wholesalePrice : this.retailPrice;
                                                        },
                                                        get itemRetailTotal() {
                                                            let q = parseInt(this.qty) || 0;
                                                            return this.currentUnitPrice * q;
                                                        },
                                                        
                                                        formatMoney(value) {
                                                            return new Intl.NumberFormat('es-AR', {minimumFractionDigits: 0, maximumFractionDigits: 0}).format(value).replace(/,/g, '.');
                                                        },
                                                        
                                                        changeQty(amount) {
                                                            let newQty = parseInt(this.qty) + amount;
                                                            if(isNaN(newQty)) newQty = 1;
                                                            if (newQty >= 1 && newQty <= this.stock) {
                                                                this.qty = newQty;
                                                                this.sync();
                                                            }
                                                        },
                                                        sync() {
                                                            clearTimeout(this.timeout);
                                                            this.timeout = setTimeout(() => {
                                                                $wire.setQuantity({{ $productId }}, this.qty);
                                                            }, 400);
                                                        }
                                                    }"
                                                    x-init="
                                                        $watch('qty', val => { 
                                                            let parsed = parseInt(val) || 0;
                                                            if(parsed > stock) { qty = stock; sync(); }
                                                            else if(parsed < 1 && parsed !== 0) { qty = 1; sync(); }
                                                        });
                                                    "
                                                    x-effect="
                                                        itemTotals[{{ $productId }}] = { retail: itemRetailTotal };
                                                        itemQtys[{{ $productId }}] = parseInt(qty) || 0;
                                                    "
                                                >
                                                    <div class="h-24 w-24 flex-shrink-0 overflow-hidden rounded-xl border {{ $theme === 'luxury' ? 'border-white/10 bg-[#0a0f1c]/50' : 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800' }} p-2">
                                                        @if($product->image_url)
                                                            <img src="{{ asset('storage/' . $product->image_url) }}" alt="{{ $product->name }}" class="h-full w-full object-contain mix-blend-multiply dark:mix-blend-normal">
                                                        @else
                                                            <div class="h-full w-full flex items-center justify-center">
                                                                <svg class="h-8 w-8 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                                </svg>
                                                            </div>
                                                        @endif
                                                    </div>

                                                    <div class="ml-4 flex flex-1 flex-col justify-between">
                                                        <div>
                                                            <div class="flex flex-col sm:flex-row sm:justify-between text-base font-bold text-gray-900 dark:text-white gap-1 sm:gap-4">
                                                                <h3 class="line-clamp-2 leading-tight flex-1">
                                                                    {{ $product->name }}
                                                                </h3>
                                                                <p class="text-[var(--color-primary)] whitespace-nowrap" x-text="`$${formatMoney(itemRetailTotal)}`"></p>
                                                            </div>
                                                            <div class="mt-1 flex items-center flex-wrap gap-2">
                                                                <template x-if="isWholesale">
                                                                    <div class="flex items-center gap-2">
                                                                        <p class="text-xs text-gray-400 dark:text-gray-500 line-through" x-text="`$${formatMoney(retailPrice)} c/u`"></p>
                                                                        <p class="text-sm font-black text-emerald-600 dark:text-emerald-400" x-text="`$${formatMoney(wholesalePrice)} c/u`"></p>
                                                                        <span class="inline-flex items-center text-[9px] font-black uppercase tracking-wider text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-transparent border border-emerald-200 dark:border-emerald-500/50 px-1.5 py-0.5 rounded shadow-sm">
                                                                            🔥 Precio Mayorista
                                                                        </span>
                                                                    </div>
                                                                </template>
                                                                <template x-if="!isWholesale">
                                                                    <p class="text-sm text-gray-500 dark:text-gray-400" x-text="`$${formatMoney(retailPrice)} c/u`"></p>
                                                                </template>
                                                            </div>
                                                        </div>
                                                        <div class="flex flex-1 items-end justify-between text-sm mt-3 sm:mt-0">
                                                            <div class="flex flex-col items-start gap-1.5">
                                                                <div class="flex items-center border rounded-full overflow-hidden shadow-sm relative isolate {{ $theme === 'luxury' ? 'border-white/10 bg-white/5' : 'border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800' }}">
                                                                    <button @click="changeQty(-1)" type="button" class="px-3 py-1 font-bold transition-colors disabled:cursor-not-allowed {{ $theme === 'luxury' ? 'text-gray-400 hover:bg-white/10 disabled:text-gray-600' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 disabled:text-gray-300 dark:disabled:text-gray-600' }}" :disabled="qty <= 1">-</button>
                                                                    <input type="text" 
                                                                           inputmode="numeric" 
                                                                           pattern="[0-9]*"
                                                                           x-model.number="qty"
                                                                           @input="sync()"
                                                                           class="px-1 w-14 font-bold text-center bg-transparent border-0 border-transparent outline-none focus:ring-0 focus:border-transparent focus:outline-none shadow-none p-0 m-0 {{ $theme === 'luxury' ? 'text-white' : 'text-gray-900 dark:text-white' }}">
                                                                    <button @click="changeQty(1)" type="button" class="px-3 py-1 font-bold transition-colors disabled:cursor-not-allowed {{ $theme === 'luxury' ? 'text-gray-400 hover:bg-white/10 disabled:text-gray-600' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 disabled:text-gray-300 dark:disabled:text-gray-600' }}" :disabled="qty >= stock">+</button>
                                                                </div>
                                                                <span x-show="qty >= stock" x-cloak x-transition.opacity class="text-[9px] font-black text-red-600 dark:text-red-400 uppercase tracking-widest">
                                                                    ¡Últimas en stock!
                                                                </span>
                                                            </div>

                                                            <div class="flex">
                                                                <button @click.prevent="isDeleted = true; $dispatch('cart-item-deleted-local', {qty: qty}); $wire.removeItem({{ $productId }})" type="button" class="font-medium text-red-500 hover:text-red-400 transition-colors inline-flex items-center gap-1">
                                                                    <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                    </svg>
                                                                    Eliminar
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </li>
                                            @endif
                                        @empty
                                            <li class="py-12 flex-col items-center justify-center text-center flex">
                                                <svg class="h-16 w-16 text-gray-300 dark:text-gray-600 mb-4 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                                </svg>
                                                <p class="text-gray-500 dark:text-gray-400 font-medium">Tu carrito está vacío.</p>
                                                <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">¡Agregá los mejores productos a tu carrito!</p>
                                            </li>
                                        @endforelse
                                    </ul>
                                </div>
                            </div>
                        </div>

                        @if(count($cart) > 0)
                            <div class="border-t px-4 py-6 sm:px-6 transition-colors duration-300 {{ $theme === 'luxury' ? 'border-white/10 bg-white/5' : 'border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50' }}" x-show="!isClearing" x-transition.opacity.duration.300ms>
                                <div class="flex justify-between text-base font-black text-xl {{ $theme === 'luxury' ? 'text-white' : 'text-gray-900 dark:text-white' }}">
                                    <p>Total de Lista</p>
                                    <p x-text="`$${formatMoney(globalRetailTotal)}`">${{ number_format($subtotal, 0, ',', '.') }}</p>
                                </div>
                                <div class="flex justify-between text-base font-black text-emerald-600 dark:text-emerald-400 mt-2">
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                        <p>Efectivo / Transferencia</p>
                                    </div>
                                    <p x-text="`$${formatMoney(globalCashTotal)}`">${{ number_format($subtotalCash ?? 0, 0, ',', '.') }}</p>
                                </div>

                                <div class="mt-4 p-3 bg-emerald-500/10 border border-emerald-500/30 rounded-xl flex items-center justify-between shadow-inner">
                                    <div class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400 font-bold text-sm">
                                        <span class="text-lg">🔥</span>
                                        <span>¡Ahorras pagando en Efectivo!</span>
                                    </div>
                                    <span class="text-emerald-600 dark:text-emerald-400 font-black text-lg" x-text="`$${formatMoney(globalRetailTotal - globalCashTotal)}`"></span>
                                </div>
                                <div class="mt-6">
                                    <button wire:click="goToCheckout"
                                       @click="$store.cart.hide()"
                                       class="flex items-center justify-center w-full rounded-full px-6 py-4 text-base font-bold text-white shadow-lg transition-all hover:opacity-90 hover:-translate-y-0.5 {{ empty($cart) ? 'opacity-50 cursor-not-allowed pointer-events-none' : '' }}"
                                       style="background-color: var(--color-primary); box-shadow: 0 4px 14px 0 var(--color-primary-glow);">
                                        Finalizar Pedido
                                    </button>
                                </div>
                                <div class="mt-6 flex justify-center text-center text-sm">
                                    <p class="text-gray-500 dark:text-gray-400">
                                        o&nbsp;
                                        <button type="button" @click="$store.cart.hide()" class="font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white underline underline-offset-2 transition-colors">
                                            Seguir Comprando
                                            <span aria-hidden="true">&rarr;</span>
                                        </button>
                                    </p>
                                </div>
                            </div>
                        @endif

                        <div x-show="isClearing" class="absolute inset-0 flex flex-col items-center justify-center bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm z-50">
                            <div class="animate-spin rounded-full h-12 w-12 border-4 border-red-500 border-t-transparent mb-4"></div>
                            <p class="text-red-500 font-bold text-lg">Vaciando carrito...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
