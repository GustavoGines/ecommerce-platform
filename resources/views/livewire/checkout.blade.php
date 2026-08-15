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
    
    
    
    
    
    
    public $phone = '';
    
    public string $turnstileToken = '';

    public function mount()
    {
        $settings = \App\Models\StoreSetting::getSettings();
        if ($settings) {
            
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
        $cartTotalQuantity = array_sum($this->cart);
        return app(PricingService::class)->unitPrice($product, $quantity, auth()->user(), $cartTotalQuantity);
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
                // BUG-03 FIX: Recalcular el total con precios frescos de DB dentro de la transacción.
                // Evita que un cambio de precio entre que el usuario abre el checkout y confirma
                // resulte en un monto incorrecto guardado en la orden.
                $freshProducts = Product::whereIn('id', array_keys($this->cart))->get()->keyBy('id');
                $freshTotal = 0;
                foreach ($this->cart as $productId => $quantity) {
                    if (isset($freshProducts[$productId])) {
                        $freshTotal += $this->getPrice($freshProducts[$productId], $quantity) * $quantity;
                    }
                }

                // 1. Crear la Orden en DB con estado 'pendiente'
                $order = Order::create([
                    'user_id'         => auth()->id(),
                    'status'          => 'pendiente',
                    'total'           => $freshTotal, // BUG-03 FIX: precio calculado desde DB
                    'phone'           => $this->phone,
                    'address_street'  => 'Retiro en Local',
                    'address_number'  => '-',
                    'city'            => '-',
                    'state'           => '-',
                    'zip_code'        => '-',
                    'role_applied'    => (auth()->user() && auth()->user()->isWholesaleCustomer()) ? 'vip_mayorista' : (array_sum($this->cart) >= \App\Services\PricingService::GLOBAL_WHOLESALE_MIN ? 'por_volumen' : null),
                    'delivery_method' => 'retiro',   // BUG-12 FIX
                    'payment_method'  => 'transfer', // BUG-12 FIX
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
                            'price'        => $price,
                            'product_name' => $product->name,
                            'product_sku'  => $product->sku,
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

                // BUG-05 FIX: Guardar snapshot del carrito ANTES de limpiarlo.
                // El foreach del mensaje de WhatsApp (más abajo) necesita los productos;
                // si se itera $this->cart después de clear(), el carrito ya está vacío.
                $cartSnapshot = $this->cart;

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

                $settings = \App\Models\StoreSetting::getSettings();
                    $social = is_string($settings->social_links) ? json_decode($settings->social_links, true) : ($settings->social_links ?? []);
                    $whatsappNumber = is_array($social) && !empty($social['whatsapp']) ? preg_replace('/[^0-9]/', '', $social['whatsapp']) : '5493705075839';
                    $sellerPhone = $whatsappNumber;
                    $message = "Hola {$settings->store_name}! 🚀\n\nAcabo de realizar el pedido *#{$order->id}* en la web.\n\n*Detalle del pedido:*\n";
                    
                    foreach ($cartSnapshot as $productId => $quantity) { // BUG-05 FIX: usar snapshot
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

    {{-- =========================================================
         MODERN-LIGHT THEME: CHECKOUT (WhatsApp)
         ========================================================= --}}
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-900 dark:text-white tracking-tight">
            {{ __('Checkout: Confirmar Pedido') }}
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
        @if (session()->has('error'))
            <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl relative shadow-sm">
                {{ session('error') }}
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Order Summary -->
            <div class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 shadow-sm rounded-3xl p-8">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-6">Tu Pedido</h3>
                <ul class="divide-y divide-gray-100 dark:divide-zinc-800">
                    @foreach($cart as $productId => $quantity)
                        @if(isset($products[$productId]))
                            @php
                                $product = $products[$productId];
                                $price = $this->getPrice($product, $quantity);
                            @endphp
                            <li class="py-4 flex justify-between items-center">
                                <div class="flex items-center">
                                    <div class="h-16 w-16 flex-shrink-0 overflow-hidden rounded-xl border border-gray-100 dark:border-zinc-800 bg-gray-50 dark:bg-zinc-950 mr-4 p-1">
                                        @if($product->image_url)
                                            <img src="{{ asset('storage/' . $product->image_url) }}" class="h-full w-full object-contain mix-blend-multiply">
                                        @endif
                                    </div>
                                    <div>
                                        <p class="font-bold text-gray-900 dark:text-white line-clamp-2 text-sm">{{ $product->name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 dark:text-gray-500 mt-1">${{ number_format($price, 2) }} c/u</p>
                                        <div class="flex items-center gap-3 mt-2">
                                            <div class="flex items-center border border-gray-200 dark:border-zinc-800 rounded-lg bg-gray-50 dark:bg-zinc-950 p-1">
                                                <button wire:click.prevent="updateQuantity({{ $productId }}, 'decrement')" wire:loading.attr="disabled" type="button" class="w-6 h-6 flex items-center justify-center rounded-md bg-white dark:bg-zinc-900 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-zinc-700 dark:bg-zinc-800 shadow-sm transition-colors">
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4" /></svg>
                                                </button>
                                                <span class="w-8 text-center text-xs font-bold text-gray-900 dark:text-white">
                                                    <span wire:loading.remove wire:target="updateQuantity">{{ $quantity }}</span>
                                                    <span wire:loading wire:target="updateQuantity" class="inline-block animate-pulse w-2 h-2 bg-gray-400 rounded-full"></span>
                                                </span>
                                                <button wire:click.prevent="updateQuantity({{ $productId }}, 'increment')" wire:loading.attr="disabled" type="button" class="w-6 h-6 flex items-center justify-center rounded-md bg-white dark:bg-zinc-900 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-zinc-700 dark:bg-zinc-800 shadow-sm transition-colors" @if($quantity >= $product->stock) disabled @endif>
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                                                </button>
                                            </div>
                                            <button wire:click.prevent="removeItem({{ $productId }})" wire:loading.attr="disabled" type="button" class="text-xs text-red-500 hover:text-red-700 font-bold transition-colors inline-flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                Eliminar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-black text-gray-900 dark:text-white text-lg">
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
                <div class="mt-6 pt-6 border-t border-gray-100 dark:border-zinc-800 flex justify-between items-center">
                    <span class="text-xl font-bold text-gray-500 dark:text-gray-400 dark:text-gray-500">Total</span>
                    <span class="text-3xl font-black text-gray-900 dark:text-white">${{ number_format($subtotal, 2) }}</span>
                </div>
            </div>

            <!-- WhatsApp Form -->
            <div class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 shadow-sm rounded-3xl p-8">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-6 flex items-center gap-2">
                    <svg class="w-6 h-6 text-green-500" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.487-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 00-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    Confirmar por WhatsApp
                </h3>
                
                <div class="bg-blue-50 border border-blue-100 rounded-xl p-5 mb-6 text-sm text-blue-900">
                    <p class="font-bold mb-1">Pagos y Envíos</p>
                    <p>¡Excelente elección! Una vez que confirmes tu pedido, nos comunicaremos por WhatsApp para coordinar juntos los detalles del pago y la entrega.</p>
                </div>

                <form wire:submit="placeOrder">
                    <div class="mb-6">
                        <label class="block text-gray-700 text-xs font-bold mb-2 uppercase tracking-wider">Tu Nombre</label>
                        <input type="text" value="{{ auth()->user()->name }}" disabled class="w-full py-3 px-4 bg-gray-100 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-800 rounded-xl text-gray-500 dark:text-gray-400 dark:text-gray-500 shadow-sm cursor-not-allowed">
                    </div>

                    <div class="mb-8">
                        <label class="block text-gray-700 text-xs font-bold mb-2 uppercase tracking-wider">Celular / WhatsApp <span class="text-red-500">*</span></label>
                        <div class="flex">
                            <span class="inline-flex items-center px-4 rounded-l-xl border border-r-0 border-gray-200 dark:border-zinc-800 bg-gray-50 dark:bg-zinc-950 text-gray-500 dark:text-gray-400 dark:text-gray-500 font-bold">
                                📞
                            </span>
                            <input wire:model="phone" type="tel" class="w-full py-3 px-4 bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 rounded-r-xl text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent transition-all shadow-sm" placeholder="Ej: 3704 123456">
                        </div>
                        <p class="text-[10px] text-gray-500 dark:text-gray-400 dark:text-gray-500 mt-1.5 uppercase tracking-wider font-bold">Para coordinar el retiro</p>
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
                            class="w-full inline-flex items-center justify-center gap-2 py-4 px-8 rounded-xl text-white dark:text-black font-bold text-lg tracking-wide transition-all shadow-md hover:shadow-lg hover:-translate-y-0.5 disabled:opacity-70 disabled:cursor-not-allowed bg-green-500 hover:bg-green-600">
                        <span wire:loading.remove wire:target="placeOrder">Confirmar Pedido</span>
                        <span wire:loading wire:target="placeOrder">Procesando...</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

</div>
