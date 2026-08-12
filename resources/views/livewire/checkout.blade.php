<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\MercadoPagoService;
use App\Services\PricingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderPlaced;

new #[Layout('layouts.app')] class extends Component {
    public $cart = [];
    public $products = [];
    public $subtotal = 0;
    
    public $address_street = '';
    public $address_number = '';
    public $city = '';
    public $state = '';
    public $zip_code = '';
    public $phone = '';
    public $theme = 'stealth';
    public string $turnstileToken = '';

    public function mount()
    {
        $settings = \App\Models\StoreSetting::getSettings();
        if ($settings) {
            $this->theme = $settings->theme_name ?? 'stealth';
        }
        $cartService = app(\App\Services\CartService::class);
        $this->cart = $cartService->getCartItemsArray();
        
        if (empty($this->cart)) {
            return redirect()->route('home');
        }

        if (auth()->check() && auth()->user()->phone) {
            $this->phone = auth()->user()->phone;
        }

        $this->products = Product::whereIn('id', array_keys($this->cart))->get()->keyBy('id');
        $this->calculateSubtotal();
    }

    public function getPrice($product, $quantity): float
    {
        // DRY-01: Lógica centralizada en PricingService
        return app(PricingService::class)->unitPrice($product, $quantity, auth()->user());
    }

    public function calculateSubtotal()
    {
        $this->subtotal = 0;

        foreach ($this->cart as $productId => $quantity) {
            if (isset($this->products[$productId])) {
                $product = $this->products[$productId];
                $price = $this->getPrice($product, $quantity);
                $this->subtotal += $price * $quantity;
            }
        }
    }

    public function updateQuantity($productId, $action)
    {
        $cartService = app(\App\Services\CartService::class);
        $cartService->updateQuantity($productId, $action);
        $this->cart = $cartService->getCartItemsArray();
        
        if (empty($this->cart)) {
            return redirect()->route('home');
        }

        $this->calculateSubtotal();
        $this->dispatch('cart-updated');
    }

    public function removeItem($productId)
    {
        $cartService = app(\App\Services\CartService::class);
        $cartService->removeItem($productId);
        $this->cart = $cartService->getCartItemsArray();
        
        if (empty($this->cart)) {
            return redirect()->route('home');
        }

        $this->calculateSubtotal();
        $this->dispatch('cart-updated');
    }

    public function placeOrder()
    {
        $rules = [
            'phone' => 'required|string|min:8|max:25',
        ];

        if ($this->theme !== 'modern-light') {
            $rules = array_merge($rules, [
                'address_street' => 'required|string|max:255',
                'address_number' => 'required|string|max:50',
                'city'           => 'required|string|max:255',
                'state'          => 'required|string|max:255',
                'zip_code'       => 'required|string|max:20',
            ]);
        }

        if (config('services.turnstile.enabled')) {
            $rules['turnstileToken'] = ['required', function ($attribute, $value, $fail) {
                $response = \Illuminate\Support\Facades\Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => config('services.turnstile.secret_key'),
                    'response' => $value,
                    'remoteip' => request()->ip(),
                ]);
                if (!$response->json('success')) {
                    $fail('Verificación anti-bots fallida. Por favor recarga la página.');
                }
            }];
        }
            
        $this->validate($rules, [
            'phone.required' => 'El número de celular/WhatsApp es obligatorio para poder contactarte.',
            'turnstileToken.required' => 'La validación anti-bots es obligatoria.',
        ]);

        if (empty($this->cart)) {
            return;
        }

        try {
            $redirectUrl = null;

            // Transacción DB para asegurar atomicidad
            DB::transaction(function () use (&$redirectUrl) {
                // 1. Crear la Orden en DB con estado 'pendiente'
                $order = Order::create([
                    'user_id'        => auth()->id(),
                    'status'         => 'pendiente',
                    'total'          => $this->subtotal,
                    'phone'          => $this->phone,
                    'address_street' => $this->theme === 'modern-light' ? 'Retiro en Local' : $this->address_street,
                    'address_number' => $this->theme === 'modern-light' ? '-' : $this->address_number,
                    'city'           => $this->theme === 'modern-light' ? '-' : $this->city,
                    'state'          => $this->theme === 'modern-light' ? '-' : $this->state,
                    'zip_code'       => $this->theme === 'modern-light' ? '-' : $this->zip_code,
                    'role_applied'   => (auth()->user() && auth()->user()->isWholesaleCustomer()) ? 'vip_mayorista' : 'por_volumen',
                ]);

                // 2. Crear Items y descontar stock
                foreach ($this->cart as $productId => $quantity) {
                    if (isset($this->products[$productId])) {
                        $product = $this->products[$productId];
                        
                        $price = $this->getPrice($product, $quantity);

                        OrderItem::create([
                            'order_id'   => $order->id,
                            'product_id' => $product->id,
                            'quantity'   => $quantity,
                            'price'      => $price,
                        ]);

                        // BUG-01 FIX: Atomic decrement — prevents race conditions / overselling.
                        // The WHERE clause ensures stock cannot go below zero even with concurrent requests.
                        $decremented = Product::where('id', $product->id)
                            ->where('stock', '>=', $quantity)
                            ->decrement('stock', $quantity);

                        if (!$decremented) {
                            throw new \Exception("Sin stock suficiente para: " . $product->name . ". Es posible que otro cliente lo haya comprado en este momento.");
                        }
                    }
                }

                // Limpiar carrito
                app(\App\Services\CartService::class)->clear();
                $this->dispatch('cart-updated');

                // Actualizar teléfono del usuario si no tenía uno o si lo cambió
                if (auth()->check() && auth()->user()->phone !== $this->phone) {
                    auth()->user()->update(['phone' => $this->phone]);
                }

                // Enviar email OrderPlaced (M-01)
                if (auth()->check() && auth()->user()->email) {
                    try {
                        Mail::to(auth()->user()->email)->send(new OrderPlaced($order));
                    } catch (\Exception $e) {
                        Log::error('Error enviando OrderPlaced email', ['error' => $e->getMessage()]);
                    }
                }

                if ($this->theme === 'modern-light') {
                    $settings = \App\Models\StoreSetting::getSettings();
                    $social = is_string($settings->social_links) ? json_decode($settings->social_links, true) : ($settings->social_links ?? []);
                    $whatsappNumber = is_array($social) && !empty($social['whatsapp']) ? preg_replace('/[^0-9]/', '', $social['whatsapp']) : '5493704022685';
                    $sellerPhone = $whatsappNumber;
                    $message = "Hola {$settings->store_name}! 🚀\n\nAcabo de realizar el pedido *#{$order->id}* en la web.\n\n*Detalle del pedido:*\n";
                    
                    foreach ($this->cart as $productId => $quantity) {
                        if (isset($this->products[$productId])) {
                            $product = $this->products[$productId];
                            $price = $this->getPrice($product, $quantity);
                            $message .= "• {$quantity}x {$product->name} (\$" . number_format($price * $quantity, 0, ',', '.') . ")\n";
                        }
                    }
                    
                    $message .= "\n*Total a abonar:* $" . number_format($this->subtotal, 0, ',', '.') . "\n\n";
                    $message .= "Mi nombre es: *" . auth()->user()->name . "*\n\n";
                    $message .= "Paso a retirarlo por el local. ¡Aguardamos confirmación!";
                    
                    $redirectUrl = "https://wa.me/{$sellerPhone}?text=" . urlencode($message);
                    return; // Salir del transaction closure
                }

                // 3. Generar Preferencia de Pago en MercadoPago
                $mpService  = app(MercadoPagoService::class);
                $preference = $mpService->createPreference($order, $this->cart);

                // 4. Guardar el preference_id en la orden
                $order->update(['mp_preference_id' => $preference['preference_id']]);

                $redirectUrl = app()->isProduction()
                    ? $preference['init_point']
                    : $preference['sandbox_init_point'];
            });

            if ($redirectUrl) {
                return redirect()->away($redirectUrl);
            }

        } catch (\MercadoPago\Exceptions\MPApiException $e) {
            Log::error('Error MP al crear preferencia en checkout', ['error' => $e->getMessage()]);
            session()->flash('error', 'No pudimos conectar con MercadoPago. Por favor intentá de nuevo en unos minutos.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }
}; ?>


<div>
@if($theme === 'luxury')
    {{-- =========================================================
         LUXURY THEME: CHECKOUT
         ========================================================= --}}
    <div class="bg-[#030712] min-h-screen text-white pt-24 pb-32">
        <div class="max-w-6xl mx-auto px-6 lg:px-8">
            
            <div class="mb-12">
                <h1 class="text-4xl font-black tracking-tight mb-2">Finalizar Compra</h1>
                <p class="text-gray-400">Completa tus datos para coordinar el pedido y envío.</p>
                
                @if (session()->has('error'))
                    <div class="mt-6 bg-red-500/10 border border-red-500/20 text-red-400 px-6 py-4 rounded-2xl flex items-center gap-3">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        {{ session('error') }}
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-16">
                <!-- Shipping Form (Col-span 7) -->
                <div class="lg:col-span-7">
                    <form wire:submit="placeOrder">
                        
                        <div class="space-y-8">
                            {{-- Section: Shipping --}}
                            <div>
                                <h3 class="text-xl font-bold mb-6 flex items-center gap-2"><span class="w-8 h-8 rounded-full bg-white/5 flex items-center justify-center text-sm">1</span> Datos de Envío</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-2">Calle</label>
                                        <input wire:model="address_street" type="text" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[var(--color-primary)] focus:border-[var(--color-primary)] transition-colors placeholder-gray-700" placeholder="Ej: Av. Libertador">
                                        @error('address_street') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-2">Número / Piso</label>
                                        <input wire:model="address_number" type="text" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[var(--color-primary)] focus:border-[var(--color-primary)] transition-colors placeholder-gray-700" placeholder="Ej: 1234 Piso 5">
                                        @error('address_number') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="md:col-span-1">
                                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-2">C. Postal</label>
                                        <input wire:model="zip_code" type="text" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[var(--color-primary)] focus:border-[var(--color-primary)] transition-colors placeholder-gray-700" placeholder="Ej: 1000">
                                        @error('zip_code') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="md:col-span-1">
                                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-2">Ciudad</label>
                                        <input wire:model="city" type="text" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[var(--color-primary)] focus:border-[var(--color-primary)] transition-colors placeholder-gray-700" placeholder="CABA">
                                        @error('city') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="md:col-span-1">
                                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-2">Provincia</label>
                                        <input wire:model="state" type="text" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[var(--color-primary)] focus:border-[var(--color-primary)] transition-colors placeholder-gray-700" placeholder="Buenos Aires">
                                        @error('state') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>

                            <hr class="border-white/5">

                            {{-- Section: Contact --}}
                            <div>
                                <h3 class="text-xl font-bold mb-6 flex items-center gap-2"><span class="w-8 h-8 rounded-full bg-white/5 flex items-center justify-center text-sm">2</span> Contacto</h3>
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-2">Celular / WhatsApp</label>
                                    <input wire:model="phone" type="tel" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[var(--color-primary)] focus:border-[var(--color-primary)] transition-colors placeholder-gray-700" placeholder="Ej: 11 1234-5678">
                                    <p class="text-[11px] text-gray-500 mt-2">Lo utilizaremos para enviarte notificaciones sobre el estado de tu orden.</p>
                                    @error('phone') <span class="text-red-400 text-xs mt-1 block font-bold">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Turnstile -->
                        @if(config('services.turnstile.enabled'))
                            <div wire:ignore class="mt-6">
                                <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-callback="setTurnstileTokenCheckout"></div>
                                <script>
                                    function setTurnstileTokenCheckout(token) {
                                        @this.set('turnstileToken', token);
                                    }
                                </script>
                                @once
                                    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                                @endonce
                            </div>
                            @error('turnstileToken') <span class="text-red-400 text-xs mt-1 block font-bold">{{ $message }}</span> @enderror
                        @endif

                        <div class="mt-10">
                            <button type="submit"
                                    wire:loading.attr="disabled"
                                    class="w-full inline-flex items-center justify-center gap-3 py-5 px-8 rounded-2xl text-white font-bold text-lg tracking-wide transition-all bg-[var(--color-primary)] hover:bg-[var(--color-primary)]/90 shadow-[0_0_20px_var(--color-primary-glow)] hover:-translate-y-0.5 disabled:opacity-50 disabled:cursor-not-allowed">
                                
                                <svg wire:loading.remove wire:target="placeOrder" class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>

                                <svg wire:loading wire:target="placeOrder" class="animate-spin w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>

                                <span wire:loading.remove wire:target="placeOrder">Pagar con MercadoPago</span>
                                <span wire:loading wire:target="placeOrder">Generando pago seguro...</span>
                            </button>
                            <div class="mt-4 flex items-center justify-center gap-2 text-xs text-gray-500">
                                <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                Pago 100% encriptado
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Order Summary (Col-span 5) -->
                <div class="lg:col-span-5">
                    <div class="sticky top-32 bg-white/5 backdrop-blur-3xl border border-white/5 rounded-3xl p-8">
                        <h3 class="text-lg font-bold mb-6">Tu Pedido</h3>
                        
                        <div class="space-y-6">
                            @foreach($cart as $productId => $quantity)
                                @if(isset($products[$productId]))
                                    @php
                                        $product = $products[$productId];
                                        // DRY-01 FIX: usa PricingService vía getPrice() — cubre regla VIP mayorista
                                        $price = $this->getPrice($product, $quantity);
                                    @endphp
                                    <div class="flex gap-4">
                                        <div class="w-20 h-20 bg-[#0a0f1c] rounded-xl border border-white/5 flex items-center justify-center p-2 flex-shrink-0">
                                            @if($product->image_url)
                                                <img src="{{ asset('storage/' . $product->image_url) }}" class="w-full h-full object-contain">
                                            @endif
                                        </div>
                                        <div class="flex-1">
                                            <h4 class="font-bold text-sm leading-tight line-clamp-2 mb-1">{{ $product->name }}</h4>
                                            <div class="flex items-center justify-between mt-2">
                                                <span class="text-xs text-gray-400">{{ $quantity }} x ${{ number_format($price, 2) }}</span>
                                                <span class="font-bold">${{ number_format($price * $quantity, 2) }}</span>
                                            </div>
                                            @if($price == $product->wholesale_price)
                                                <span class="text-[9px] font-bold uppercase tracking-widest text-[var(--color-primary)] mt-1 block">Mayorista</span>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        
                        <hr class="my-6 border-white/5">
                        
                        <div class="flex justify-between items-center mb-6">
                            <span class="text-gray-400">Subtotal</span>
                            <span class="font-medium">${{ number_format($subtotal, 2) }}</span>
                        </div>
                        <div class="flex justify-between items-center mb-6">
                            <span class="text-gray-400">Envío</span>
                            <span class="text-emerald-400 font-bold text-sm uppercase tracking-widest">Gratis</span>
                        </div>
                        
                        <hr class="my-6 border-white/5">

                        <div class="flex justify-between items-end">
                            <span class="text-xl font-bold">Total</span>
                            <span class="text-4xl font-black">${{ number_format($subtotal, 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
@elseif($theme === 'modern-light')
    {{-- =========================================================
         MODERN-LIGHT THEME: CHECKOUT (WhatsApp)
         ========================================================= --}}
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white tracking-tight">
            {{ __('Checkout: Confirmar Pedido') }}
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
        @if (session()->has('error'))
            <div class="mb-6 bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-xl relative shadow-sm">
                {{ session('error') }}
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Order Summary -->
            <div class="bg-zinc-900 border border-zinc-800 shadow-xl shadow-black/50 rounded-3xl p-8">
                <h3 class="text-xl font-bold text-white mb-6">Tu Pedido</h3>
                <ul class="divide-y divide-zinc-800">
                    @foreach($cart as $productId => $quantity)
                        @if(isset($products[$productId]))
                            @php
                                $product = $products[$productId];
                                $price = $this->getPrice($product, $quantity);
                            @endphp
                            <li class="py-4 flex justify-between items-center">
                                <div class="flex items-center">
                                    <div class="h-16 w-16 flex-shrink-0 overflow-hidden rounded-xl border border-zinc-800 bg-zinc-950 mr-4 p-1">
                                        @if($product->image_url)
                                            <img src="{{ asset('storage/' . $product->image_url) }}" class="h-full w-full object-contain">
                                        @endif
                                    </div>
                                    <div>
                                        <p class="font-bold text-white line-clamp-2 text-sm">{{ $product->name }}</p>
                                        <p class="text-xs text-gray-400 mt-1">${{ number_format($price, 2) }} c/u</p>
                                        <div class="flex items-center gap-3 mt-2">
                                            <div class="flex items-center border border-zinc-700 rounded-lg bg-zinc-800 p-1">
                                                <button wire:click.prevent="updateQuantity({{ $productId }}, 'decrement')" wire:loading.attr="disabled" type="button" class="w-6 h-6 flex items-center justify-center rounded-md bg-zinc-900 text-gray-300 hover:bg-zinc-700 shadow-sm transition-colors">
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4" /></svg>
                                                </button>
                                                <span class="w-8 text-center text-xs font-bold text-white">
                                                    <span wire:loading.remove wire:target="updateQuantity">{{ $quantity }}</span>
                                                    <span wire:loading wire:target="updateQuantity" class="inline-block animate-pulse w-2 h-2 bg-zinc-600 rounded-full"></span>
                                                </span>
                                                <button wire:click.prevent="updateQuantity({{ $productId }}, 'increment')" wire:loading.attr="disabled" type="button" class="w-6 h-6 flex items-center justify-center rounded-md bg-zinc-900 text-gray-300 hover:bg-zinc-700 shadow-sm transition-colors" @if($quantity >= $product->stock) disabled @endif>
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                                                </button>
                                            </div>
                                            <button wire:click.prevent="removeItem({{ $productId }})" wire:loading.attr="disabled" type="button" class="text-xs text-red-500 hover:text-red-400 font-bold transition-colors inline-flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                Eliminar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-black text-white text-lg">
                                        ${{ number_format($price * $quantity, 2) }}
                                    </div>
                                    @if($price == $product->wholesale_price)
                                        <span class="text-[9px] font-bold uppercase tracking-widest text-[var(--color-primary)] mt-1 block">Mayorista</span>
                                    @endif
                                </div>
                            </li>
                        @endif
                    @endforeach
                </ul>
                </ul>
                <div class="mt-6 pt-6 border-t border-zinc-800 flex justify-between items-center">
                    <span class="text-xl font-bold text-gray-400">Total</span>
                    <span class="text-3xl font-black text-white">${{ number_format($subtotal, 2) }}</span>
                </div>
            </div>

            <!-- WhatsApp Form -->
            <div class="bg-zinc-900 border border-zinc-800 shadow-xl shadow-black/50 rounded-3xl p-8">
                <h3 class="text-xl font-bold text-white mb-6 flex items-center gap-2">
                    <svg class="w-6 h-6 text-green-500" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.487-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 00-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    Confirmar por WhatsApp
                </h3>
                
                <div class="bg-[var(--color-primary)]/10 border border-[var(--color-primary)]/20 rounded-xl p-5 mb-6 text-sm text-[var(--color-primary)]">
                    <p class="font-bold mb-1">Pagos y Envíos</p>
                    <p>¡Excelente elección! Una vez que confirmes tu pedido, nos comunicaremos por WhatsApp para coordinar juntos los detalles del pago y la entrega.</p>
                </div>

                <form wire:submit="placeOrder">
                    <div class="mb-6">
                        <label class="block text-gray-400 text-xs font-bold mb-2 uppercase tracking-wider">Tu Nombre</label>
                        <input type="text" value="{{ auth()->user()->name }}" disabled class="w-full py-3 px-4 bg-zinc-950 border border-zinc-800 rounded-xl text-gray-500 shadow-sm cursor-not-allowed">
                    </div>

                    <div class="mb-8">
                        <label class="block text-gray-400 text-xs font-bold mb-2 uppercase tracking-wider">Celular / WhatsApp <span class="text-red-500">*</span></label>
                        <div class="flex">
                            <span class="inline-flex items-center px-4 rounded-l-xl border border-r-0 border-zinc-700 bg-zinc-800 text-gray-400 font-bold">
                                📞
                            </span>
                            <input wire:model="phone" type="tel" class="w-full py-3 px-4 bg-zinc-950 border border-zinc-700 rounded-r-xl text-white focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent transition-all shadow-sm" placeholder="Ej: 3704 123456">
                        </div>
                        <p class="text-[10px] text-gray-500 mt-1.5 uppercase tracking-wider font-bold">Para coordinar el retiro</p>
                        @error('phone') <span class="text-red-500 text-xs mt-1 block font-bold">{{ $message }}</span> @enderror
                    </div>

                    <!-- Turnstile -->
                    @if(config('services.turnstile.enabled'))
                        <div wire:ignore class="mb-6">
                            <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-callback="setTurnstileTokenCheckout"></div>
                            <script>
                                function setTurnstileTokenCheckout(token) {
                                    @this.set('turnstileToken', token);
                                }
                            </script>
                            @once
                                <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                            @endonce
                        </div>
                        @error('turnstileToken') <span class="text-red-500 text-xs mt-1 block font-bold mb-4">{{ $message }}</span> @enderror
                    @endif

                    <button type="submit"
                            wire:loading.attr="disabled"
                            class="w-full inline-flex items-center justify-center gap-2 py-4 px-8 rounded-xl text-white font-bold text-lg tracking-wide transition-all shadow-md hover:shadow-lg hover:-translate-y-0.5 disabled:opacity-70 disabled:cursor-not-allowed bg-green-500 hover:bg-green-600">
                        <span wire:loading.remove wire:target="placeOrder">Confirmar Pedido</span>
                        <span wire:loading wire:target="placeOrder">Procesando...</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
@else
    {{-- =========================================================
         STEALTH THEME: CHECKOUT
         ========================================================= --}}
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-100 leading-tight">
            {{ __('Checkout: Confirmar Reserva') }}
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
        @if (session()->has('error'))
            <div class="mb-6 bg-red-100 dark:bg-red-500/20 border border-red-400 dark:border-red-500/30 text-red-700 dark:text-red-400 px-4 py-3 rounded relative backdrop-blur-sm">
                {{ session('error') }}
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Order Summary -->
            <div class="bg-white/80 dark:bg-gray-800/40 backdrop-blur-md border border-gray-200 dark:border-gray-700/50 shadow-xl dark:shadow-2xl sm:rounded-3xl p-8">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-6">Resumen de tu Orden</h3>
                <ul class="divide-y divide-gray-200 dark:divide-gray-700/50">
                    @foreach($cart as $productId => $quantity)
                        @if(isset($products[$productId]))
                            @php
                                $product = $products[$productId];
                                // DRY-01 FIX: usa PricingService vía getPrice() — cubre regla VIP mayorista
                                $price = $this->getPrice($product, $quantity);
                            @endphp
                            <li class="py-4 flex justify-between items-center">
                                <div class="flex items-center">
                                    <div class="h-12 w-12 flex-shrink-0 overflow-hidden rounded-md border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 mr-4">
                                        @if($product->image_url)
                                            <img src="{{ asset('storage/' . $product->image_url) }}" class="h-full w-full object-cover">
                                        @endif
                                    </div>
                                    <div>
                                        <p class="font-bold text-gray-900 dark:text-white line-clamp-2 text-sm">{{ $product->name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">${{ number_format($price, 2) }} c/u</p>
                                        <div class="flex items-center gap-3 mt-2">
                                            <div class="flex items-center border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-800/50 p-1">
                                                <button wire:click.prevent="updateQuantity({{ $productId }}, 'decrement')" wire:loading.attr="disabled" type="button" class="w-6 h-6 flex items-center justify-center rounded-md bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 shadow-sm transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4" /></svg>
                                                </button>
                                                <span class="w-8 text-center text-xs font-bold text-gray-900 dark:text-white">
                                                    <span wire:loading.remove wire:target="updateQuantity({{ $productId }}, 'decrement'), updateQuantity({{ $productId }}, 'increment')">{{ $quantity }}</span>
                                                    <span wire:loading wire:target="updateQuantity({{ $productId }}, 'decrement'), updateQuantity({{ $productId }}, 'increment')" class="inline-block animate-pulse w-2 h-2 bg-gray-400 rounded-full"></span>
                                                </span>
                                                <button wire:click.prevent="updateQuantity({{ $productId }}, 'increment')" wire:loading.attr="disabled" type="button" class="w-6 h-6 flex items-center justify-center rounded-md bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 shadow-sm transition-colors disabled:opacity-50 disabled:cursor-not-allowed" @if($quantity >= $product->stock) disabled @endif>
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                                                </button>
                                            </div>
                                            <button wire:click.prevent="removeItem({{ $productId }})" wire:loading.attr="disabled" type="button" class="text-xs text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 font-medium transition-colors inline-flex items-center gap-1 disabled:opacity-50">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                Eliminar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-bold text-gray-900 dark:text-white text-lg">
                                        ${{ number_format($price * $quantity, 2) }}
                                    </div>
                                    @if($price == $product->wholesale_price)
                                        <span class="text-[9px] font-bold uppercase tracking-widest text-emerald-600 dark:text-emerald-400 mt-1 block">Precio Mayorista</span>
                                    @endif
                                </div>
                            </li>
                        @endif
                    @endforeach
                </ul>
                <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700/50 flex justify-between items-center">
                    <span class="text-xl font-bold text-gray-900 dark:text-white">Total a Pagar</span>
                    <span class="text-3xl font-black text-gray-900 dark:text-white">${{ number_format($subtotal, 2) }}</span>
                </div>
            </div>

            <!-- Shipping Form -->
            <div class="bg-white/80 dark:bg-gray-800/40 backdrop-blur-md border border-gray-200 dark:border-gray-700/50 shadow-xl dark:shadow-2xl sm:rounded-3xl p-8" :style="$store.theme.dark ? 'box-shadow: 0 10px 30px -10px var(--color-primary-glow);' : ''">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-6">Detalles de Envío</h3>
                <form wire:submit="placeOrder">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-gray-700 dark:text-gray-400 text-xs font-bold mb-2 uppercase tracking-wider">Calle</label>
                            <input wire:model="address_street" type="text" class="w-full py-3 px-4 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent transition-all shadow-sm dark:shadow-none" placeholder="Av. Siempre Viva">
                            @error('address_street') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-gray-700 dark:text-gray-400 text-xs font-bold mb-2 uppercase tracking-wider">Número/Piso</label>
                            <input wire:model="address_number" type="text" class="w-full py-3 px-4 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent transition-all shadow-sm dark:shadow-none" placeholder="123 Piso 4">
                            @error('address_number') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div>
                            <label class="block text-gray-700 dark:text-gray-400 text-xs font-bold mb-2 uppercase tracking-wider">Ciudad</label>
                            <input wire:model="city" type="text" class="w-full py-3 px-4 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent transition-all shadow-sm dark:shadow-none" placeholder="Rosario">
                            @error('city') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-gray-700 dark:text-gray-400 text-xs font-bold mb-2 uppercase tracking-wider">Provincia</label>
                            <input wire:model="state" type="text" class="w-full py-3 px-4 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent transition-all shadow-sm dark:shadow-none" placeholder="Santa Fe">
                            @error('state') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-gray-700 dark:text-gray-400 text-xs font-bold mb-2 uppercase tracking-wider">C. Postal</label>
                            <input wire:model="zip_code" type="text" class="w-full py-3 px-4 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent transition-all shadow-sm dark:shadow-none" placeholder="2000">
                            @error('zip_code') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-gray-700 dark:text-gray-400 text-xs font-bold mb-2 uppercase tracking-wider">Número de Celular / WhatsApp</label>
                        <div class="flex">
                            <span class="inline-flex items-center px-4 rounded-l-xl border border-r-0 border-gray-300 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 font-bold">
                                📞
                            </span>
                            <input wire:model="phone" type="tel" class="w-full py-3 px-4 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-r-xl text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent transition-all shadow-sm dark:shadow-none" placeholder="Ej: 11 5555-4444">
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Obligatorio para avisarte cuando despachemos tu pedido.</p>
                        @error('phone') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block font-bold">{{ $message }}</span> @enderror
                    </div>
                    
                    <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800/50 rounded-xl p-4 mb-8">
                        <p class="text-sm text-emerald-800 dark:text-emerald-300">
                            <strong>Pago seguro:</strong> Al confirmar, serás redirigido a MercadoPago para completar tu pago de forma segura.
                        </p>
                    </div>

                    <!-- Turnstile -->
                    @if(config('services.turnstile.enabled'))
                        <div wire:ignore class="mb-6">
                            <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-callback="setTurnstileTokenCheckout"></div>
                            <script>
                                function setTurnstileTokenCheckout(token) {
                                    @this.set('turnstileToken', token);
                                }
                            </script>
                            @once
                                <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                            @endonce
                        </div>
                        @error('turnstileToken') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block font-bold mb-4">{{ $message }}</span> @enderror
                    @endif

                    <button type="submit"
                            wire:loading.attr="disabled"
                            class="w-full inline-flex items-center justify-center gap-2 py-4 px-8 rounded-full text-white font-bold text-lg tracking-wide transition-all shadow-lg hover:shadow-xl hover:-translate-y-0.5 disabled:opacity-70 disabled:cursor-not-allowed"
                            style="background-color: var(--color-primary); box-shadow: 0 4px 14px 0 var(--color-primary-glow);">

                        {{-- Ícono candado (estado normal) --}}
                        <svg wire:loading.remove wire:target="placeOrder"
                             class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>

                        {{-- Spinner (estado cargando) --}}
                        <svg wire:loading wire:target="placeOrder"
                             class="animate-spin w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>

                        {{-- Texto normal --}}
                        <span wire:loading.remove wire:target="placeOrder">Pagar con MercadoPago</span>

                        {{-- Texto cargando --}}
                        <span wire:loading wire:target="placeOrder">Redirigiendo a MercadoPago...</span>

                    </button>
                </form>
            </div>
        </div>
    </div>
@endif
</div>
