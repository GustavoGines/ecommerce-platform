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
    public string $payment_method = 'transfer'; // 'transfer' o 'mercadopago'
    public bool $has_mp_token = false;

    // G3 mixed-payment properties
    public string $g3_payment_type = 'efectivo'; // 'efectivo' | 'tarjeta' | 'mixto'
    public float|int $g3_cash_amount = 0;
    public float|int $g3_card_amount = 0;

    public function mount()
    {
        $settings = \App\Models\StoreSetting::getSettings();
        if ($settings) {
            $this->theme = $settings->theme_name ?? 'stealth';
            $this->has_mp_token = !empty($settings->mp_access_token);
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
        $totalCartQuantity = array_sum($this->cart);
        return app(PricingService::class)->unitPrice($product, $quantity, auth()->user(), $totalCartQuantity);
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
            'payment_method' => 'required|in:transfer,mercadopago',
        ];

        // Solo requerir datos de envío si la tienda no es luxury (JCG) y tiene activo MP
        if ($this->theme !== 'luxury' && $this->has_mp_token) {
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

            // Guardar un snapshot del carrito para usarlo luego en WhatsApp (Fix BUG-05)
            $cartSnapshot = $this->cart;

            // Transacción DB para asegurar atomicidad
            DB::transaction(function () use (&$redirectUrl, $cartSnapshot) {
                // Fix BUG-03: Recalcular precio con datos frescos
                $freshProducts = Product::whereIn('id', array_keys($this->cart))->get()->keyBy('id');
                $freshTotal = 0;
                $totalUnits = array_sum($this->cart);
                
                $g3Type = $this->g3_payment_type;
                $isG3Transfer = (tenant('id') === 'g3' && $this->theme !== 'modern-light' && $this->payment_method === 'transfer');
                $applyUniformDiscount = ($isG3Transfer && $g3Type === 'efectivo');

                foreach ($this->cart as $productId => $quantity) {
                    if (isset($freshProducts[$productId])) {
                        $price = $this->getPrice($freshProducts[$productId], $quantity);
                        if ($applyUniformDiscount) {
                            $price = $price * 0.90;
                        }
                        $freshTotal += $price * $quantity;
                    }
                }

                // Si es Mixto, el total de la orden se calcula con la formula especial
                // (Los items quedarán con precio de lista, pero el total de la orden será exacto)
                if ($isG3Transfer && $g3Type === 'mixto') {
                    $cashAmt  = floatval($this->g3_cash_amount);
                    $precioContado = $freshTotal * 0.90; // El freshTotal aquí es a precio de lista
                    $restoContado = max(0, $precioContado - $cashAmt);
                    $cardAmt = $restoContado / 0.90;
                    $freshTotal = $cashAmt + $cardAmt;
                }
                
                // Determinar el string a guardar en payment_method
                $savedPaymentMethod = $this->payment_method;
                if ($isG3Transfer) {
                    $savedPaymentMethod = "Transferencia/Local ({$g3Type})";
                }

                // 1. Crear la Orden en DB con estado 'pendiente'
                $order = Order::create([
                    'user_id'         => auth()->id(),
                    'status'          => 'pendiente',
                    'total'           => $freshTotal, // Usar total fresco recalculado
                    'phone'           => $this->phone,
                    'address_street'  => ($this->theme !== 'luxury' && $this->has_mp_token) ? $this->address_street : 'Retiro en Local',
                    'address_number'  => ($this->theme !== 'luxury' && $this->has_mp_token) ? $this->address_number : '-',
                    'city'            => ($this->theme !== 'luxury' && $this->has_mp_token) ? $this->city : '-',
                    'state'           => ($this->theme !== 'luxury' && $this->has_mp_token) ? $this->state : '-',
                    'zip_code'        => ($this->theme !== 'luxury' && $this->has_mp_token) ? $this->zip_code : '-',
                    'delivery_method' => ($this->theme !== 'luxury' && $this->has_mp_token) ? 'envio' : 'retiro', // Fix BUG-12
                    'payment_method'  => $savedPaymentMethod, // Fix BUG-12 + G3 payment types
                    'role_applied'    => (auth()->user() && auth()->user()->isWholesaleCustomer()) 
                                            ? 'vip_mayorista' 
                                            : ($totalUnits >= \App\Services\PricingService::GLOBAL_WHOLESALE_MIN ? 'por_volumen' : 'minorista'),
                ]);

                // 2. Crear Items y descontar stock
                foreach ($this->cart as $productId => $quantity) {
                    if (isset($freshProducts[$productId])) {
                        $product = $freshProducts[$productId];
                        $price = $this->getPrice($product, $quantity);
                        if ($applyUniformDiscount) {
                            $price = $price * 0.90;
                        }

                        OrderItem::create([
                            'order_id'   => $order->id,
                            'product_id' => $product->id,
                            'quantity'   => $quantity,
                            'price'      => $price,
                        ]);

                        // BUG-01 FIX: Atomic decrement — prevents race conditions / overselling.
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

                if ($this->payment_method === 'transfer') {
                    $settings = \App\Models\StoreSetting::getSettings();
                    $social = is_string($settings->social_links) ? json_decode($settings->social_links, true) : ($settings->social_links ?? []);
                    
                    $rawWhatsapp = (is_array($social) && !empty($social['whatsapp'])) ? $social['whatsapp'] : '';
                    $whatsappNumber = !empty($rawWhatsapp) ? preg_replace('/[^0-9]/', '', $rawWhatsapp) : '5493704022685';
                    $sellerPhone = $whatsappNumber;
                    $message = "Hola {$settings->store_name}!\n\nAcabo de realizar el pedido *#{$order->id}* en la web.\n\n*Detalle del pedido:*\n";
                    
                    foreach ($cartSnapshot as $productId => $quantity) {
                        if (isset($this->products[$productId])) {
                            $product = $this->products[$productId];
                            $price = $this->getPrice($product, $quantity);
                            $message .= "• {$quantity}x {$product->name} (\$" . number_format($price * $quantity, 0, ',', '.') . ")\n";
                        }
                    }

                    if (tenant('id') === 'g3') {
                        $g3Type = $this->g3_payment_type;
                        $subtotalBase = $this->subtotal;

                        if ($g3Type === 'tarjeta') {
                            $message .= "\n*Método de pago elegido:* Tarjeta (precio de lista)";
                            $message .= "\n*Total a Pagar:* $" . number_format($subtotalBase, 0, ',', '.') . "\n\n";
                        } elseif ($g3Type === 'efectivo') {
                            $totalConDescuento = $subtotalBase * 0.90;
                            $message .= "\n*Método de pago elegido:* Efectivo / Transferencia (10% OFF)";
                            $message .= "\n*Total Lista:* $" . number_format($subtotalBase, 0, ',', '.') . "\n";
                            $message .= "*Total con 10% OFF:* $" . number_format($totalConDescuento, 0, ',', '.') . "\n\n";
                        } else { // mixto
                            $cashAmt  = floatval($this->g3_cash_amount);
                            $precioContado = $subtotalBase * 0.90;
                            $restoContado = max(0, $precioContado - $cashAmt);
                            $cardAmt = $restoContado / 0.90;
                            $totalMixto = $cashAmt + $cardAmt;
                            
                            $message .= "\n*Método de pago elegido:* Pago Mixto (Efectivo + Tarjeta)";
                            $message .= "\n  - Pagará en Efectivo: $" . number_format($cashAmt, 0, ',', '.') . "\n";
                            $message .= "  - Pagará con Tarjeta (saldo a precio lista): $" . number_format($cardAmt, 0, ',', '.') . "\n";
                            $message .= "*Total Final Mixto:* $" . number_format($totalMixto, 0, ',', '.') . "\n\n";
                        }
                    } else {
                        $message .= "\n*Total a Pagar:* $" . number_format($this->subtotal, 0, ',', '.') . "\n\n";
                    }

                    $message .= "Mi nombre es: *" . auth()->user()->name . "*\n\n";
                    $message .= "Ya cargué mis datos de envío en la web. ¡Aguardo confirmación y datos para transferir!";
                    
                    $redirectUrl = "https://wa.me/{$sellerPhone}?text=" . urlencode($message);
                    return; // Salir del transaction closure
                }

                // 3. Generar Preferencia de Pago en MercadoPago si eligió MP
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
        } catch (\Throwable $e) {
            Log::error('Fatal Checkout Error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            session()->flash('error', 'Error inesperado: ' . $e->getMessage());
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
                                                <span class="text-xs text-gray-400">{{ $quantity }} x ${{ number_format($price, 0, ',', '.') }}</span>
                                                <span class="font-bold">${{ number_format($price * $quantity, 0, ',', '.') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        
                        <hr class="my-6 border-white/5">
                        
                        <div class="flex justify-between items-center mb-6">
                            <span class="text-gray-400">Subtotal</span>
                            <span class="font-medium">${{ number_format($subtotal, 0, ',', '.') }}</span>
                        </div>

                        
                        <hr class="my-6 border-white/5">

                        <div class="flex justify-between items-end mb-4">
                            <span class="text-lg font-bold text-gray-400">{{ tenant('id') === 'g3' ? 'Total Normal (Tarjetas)' : 'Total' }}</span>
                            <span class="text-2xl font-bold text-gray-300">${{ number_format($subtotal, 0, ',', '.') }}</span>
                        </div>
                        @if(tenant('id') === 'g3')
                        <div class="flex justify-between items-end">
                            <span class="text-xl font-bold text-[var(--color-primary)]">Total Especial (Efvo/Transf 10% OFF)</span>
                        <span class="text-4xl font-black text-[var(--color-primary)]">${{ number_format($subtotal * 0.90, 0, ',', '.') }}</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </div>
@elseif($theme === 'modern-light')
    {{-- =========================================================
         MODERN-LIGHT THEME: CHECKOUT (JCG - WhatsApp, fondo blanco)
         ========================================================= --}}
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-900 tracking-tight">
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
            <div class="bg-white border border-gray-200 shadow-sm rounded-3xl p-8">
                <h3 class="text-xl font-bold text-gray-900 mb-6">Tu Pedido</h3>
                <ul class="divide-y divide-gray-100">
                    @foreach($cart as $productId => $quantity)
                        @if(isset($products[$productId]))
                            @php
                                $product = $products[$productId];
                                $cartTotalQty = array_sum($cart);
                                $price = $this->getPrice($product, $quantity);
                                $isWholesale = $product->wholesale_price && $price == $product->wholesale_price;
                                $retailPrice = $product->retail_price;
                            @endphp
                            <li class="py-4 flex justify-between items-center">
                                <div class="flex items-center">
                                    <div class="h-16 w-16 flex-shrink-0 overflow-hidden rounded-xl border border-gray-100 bg-gray-50 mr-4 p-1">
                                        @if($product->image_url)
                                            <img src="{{ asset('storage/' . $product->image_url) }}" class="h-full w-full object-contain mix-blend-multiply">
                                        @endif
                                    </div>
                                    <div>
                                        <p class="font-bold text-gray-900 line-clamp-2 text-sm">{{ $product->name }}</p>
                                        <div class="flex items-center gap-2 mt-1">
                                            @if($isWholesale)
                                                <p class="text-xs text-gray-400 line-through">${{ number_format($retailPrice, 0, ',', '.') }} c/u</p>
                                                <p class="text-xs font-bold text-[var(--color-primary)]">${{ number_format($price, 0, ',', '.') }} c/u</p>
                                            @else
                                                <p class="text-xs text-gray-500">${{ number_format($price, 0, ',', '.') }} c/u</p>
                                            @endif
                                            @if($isWholesale)
                                                <span class="text-[9px] font-black uppercase tracking-widest bg-[var(--color-primary)] text-white px-1.5 py-0.5 rounded-full">MAYORISTA</span>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-3 mt-2">
                                            <div class="flex items-center border border-gray-200 rounded-lg bg-gray-50 p-1">
                                                <button wire:click.prevent="updateQuantity({{ $productId }}, 'decrement')" wire:loading.attr="disabled" type="button" class="w-6 h-6 flex items-center justify-center rounded-md bg-white text-gray-600 hover:bg-gray-100 shadow-sm transition-colors">
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4" /></svg>
                                                </button>
                                                <span class="w-8 text-center text-xs font-bold text-gray-900">
                                                    <span wire:loading.remove wire:target="updateQuantity({{ $productId }}, 'decrement'), updateQuantity({{ $productId }}, 'increment')">{{ $quantity }}</span>
                                                    <span wire:loading wire:target="updateQuantity({{ $productId }}, 'decrement'), updateQuantity({{ $productId }}, 'increment')" class="inline-block animate-pulse w-2 h-2 bg-gray-400 rounded-full"></span>
                                                </span>
                                                <button wire:click.prevent="updateQuantity({{ $productId }}, 'increment')" wire:loading.attr="disabled" type="button" class="w-6 h-6 flex items-center justify-center rounded-md bg-white text-gray-600 hover:bg-gray-100 shadow-sm transition-colors" @if($quantity >= $product->stock) disabled @endif>
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
                                    <div class="font-black text-gray-900 text-lg">
                                        ${{ number_format($price * $quantity, 0, ',', '.') }}
                                    </div>
                                    @if($isWholesale)
                                        <p class="text-xs text-gray-400 line-through mt-0.5">${{ number_format($retailPrice * $quantity, 0, ',', '.') }}</p>
                                        <span class="text-[9px] font-bold uppercase tracking-widest text-[var(--color-primary)] mt-0.5 block">Mayorista</span>
                                    @endif
                                </div>
                            </li>
                        @endif
                    @endforeach
                </ul>
                @php
                    $cartTotalQty = array_sum($cart);
                    $hasWholesaleActive = $cartTotalQty >= 10;
                    $originalTotal = collect($cart)->sum(function($qty, $prodId) {
                        return isset($this->products[$prodId]) ? $this->products[$prodId]->retail_price * $qty : 0;
                    });
                @endphp
                <div class="mt-6 pt-6 border-t border-gray-100">
                    @if($hasWholesaleActive && $originalTotal > $subtotal)
                        <div class="flex justify-between items-center mb-2 text-sm">
                            <span class="text-gray-400">Precio lista:</span>
                            <span class="text-gray-400 line-through">${{ number_format($originalTotal, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-xs font-bold uppercase tracking-wider bg-[var(--color-primary)]/10 text-[var(--color-primary)] px-2 py-0.5 rounded-full">Ahorrás ${{{ number_format($originalTotal - $subtotal, 0, ',', '.') }}}</span>
                        </div>
                    @endif
                    <div class="flex justify-between items-center">
                        <span class="text-xl font-bold text-gray-{{ $hasWholesaleActive && $originalTotal > $subtotal ? '500' : '500' }}">Total @if($hasWholesaleActive && $originalTotal > $subtotal)<span class="text-xs font-normal text-[var(--color-primary)]">(Mayorista)</span>@endif</span>
                        <span class="text-3xl font-black text-gray-900">${{ number_format($subtotal, 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>

            <!-- WhatsApp Form -->
            <div class="bg-white border border-gray-200 shadow-sm rounded-3xl p-8">
                <h3 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
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
                        <input type="text" value="{{ auth()->user()->name }}" disabled class="w-full py-3 px-4 bg-gray-100 border border-gray-200 rounded-xl text-gray-500 shadow-sm cursor-not-allowed">
                    </div>

                    <div class="mb-8">
                        <label class="block text-gray-700 text-xs font-bold mb-2 uppercase tracking-wider">Celular / WhatsApp <span class="text-red-500">*</span></label>
                        <div class="flex">
                            <span class="inline-flex items-center px-4 rounded-l-xl border border-r-0 border-gray-200 bg-gray-50 text-gray-500 font-bold">📞</span>
                            <input wire:model="phone" type="tel" class="w-full py-3 px-4 bg-white border border-gray-200 rounded-r-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent transition-all shadow-sm" placeholder="Ej: 3704 123456">
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

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8"
             @if(tenant('id') === 'g3')
             x-data="{
                payType: @entangle('g3_payment_type').live,
                cashAmt: @entangle('g3_cash_amount').live,
                cardAmt: @entangle('g3_card_amount').live,
                subtotal: {{ $subtotal }},
                get precioContado() { return this.subtotal * 0.90; },
                get restoContado() { return Math.max(0, this.precioContado - parseFloat(this.cashAmt || 0)); },
                get calculatedCardAmt() { return this.restoContado / 0.90; },
                get totalMixto() { return parseFloat(this.cashAmt || 0) + this.calculatedCardAmt; }
             }"
             x-effect="if(payType === 'mixto') { cardAmt = calculatedCardAmt }"
             @endif
        >
            <!-- Order Summary -->
            <div class="order-2 md:order-1 bg-white/80 dark:bg-gray-800/40 backdrop-blur-md border border-gray-200 dark:border-gray-700/50 shadow-xl dark:shadow-2xl sm:rounded-3xl p-8 h-fit sticky top-8">
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
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">${{ number_format($price, 0, ',', '.') }} c/u</p>
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
                                        ${{ number_format($price * $quantity, 0, ',', '.') }}
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
                    <div class="text-right">
                        @if($payment_method === 'transfer' && tenant('id') === 'g3')
                            <div x-show="payType === 'efectivo'" x-cloak>
                                <p class="text-sm font-bold line-through text-gray-400 mb-1">${{ number_format($subtotal, 0, ',', '.') }}</p>
                                <span class="text-3xl font-black text-emerald-500">${{ number_format($subtotal * 0.90, 0, ',', '.') }}</span>
                            </div>
                            <div x-show="payType === 'tarjeta'" x-cloak>
                                <span class="text-3xl font-black text-gray-900 dark:text-white">${{ number_format($subtotal, 0, ',', '.') }}</span>
                            </div>
                            <div x-show="payType === 'mixto'" x-cloak>
                                <p class="text-sm font-bold text-gray-400 mb-1">Total Final (Mixto)</p>
                                <span class="text-3xl font-black text-purple-500">$<span x-text="Math.round(totalMixto).toLocaleString('es-AR')"></span></span>
                            </div>
                        @else
                            <span class="text-3xl font-black text-gray-900 dark:text-white">${{ number_format($subtotal, 0, ',', '.') }}</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Shipping Form -->
            <div class="order-1 md:order-2">
                @if($has_mp_token)
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
                    @else
                    <!-- Simple Contact Form (When MP is disabled) -->
                    <div class="bg-white/80 dark:bg-gray-800/40 backdrop-blur-md border border-gray-200 dark:border-gray-700/50 shadow-xl dark:shadow-2xl sm:rounded-3xl p-8" :style="$store.theme.dark ? 'box-shadow: 0 10px 30px -10px var(--color-primary-glow);' : ''">
                        <div class="mb-6 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 rounded-xl p-4">
                            <h3 class="text-lg font-bold text-emerald-800 dark:text-emerald-400 mb-2 flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                Confirmar por WhatsApp
                            </h3>
                            <p class="text-sm text-emerald-700 dark:text-emerald-300">
                                ¡Excelente elección! Una vez que confirmes tu pedido, nos comunicaremos por WhatsApp para coordinar juntos los detalles del pago y la entrega.
                            </p>
                        </div>
                        <form wire:submit="placeOrder">
                    @endif
                    
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

                    {{-- G3: Selector de tipo de pago (solo para g3 y cuando payment_method = transfer) --}}
                    @if(tenant('id') === 'g3')
                    <div class="mb-8">
                        <label class="block text-gray-700 dark:text-gray-400 text-xs font-bold mb-3 uppercase tracking-wider">¿Cómo vas a pagar?</label>
                        <div class="grid grid-cols-3 gap-3 mb-4">
                            {{-- Efectivo --}}
                            <label class="cursor-pointer">
                                <input type="radio" x-model="payType" value="efectivo" class="sr-only">
                                <div :class="payType === 'efectivo' ? 'border-emerald-500 bg-emerald-500/10 ring-1 ring-emerald-500' : 'border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-750'"
                                     class="rounded-xl border p-3 text-center transition-all cursor-pointer">
                                    <span class="block text-xl mb-1">💵</span>
                                    <span class="block text-xs font-bold text-gray-900 dark:text-white">Efectivo</span>
                                    <span class="block text-[10px] text-emerald-500 font-bold">10% OFF</span>
                                </div>
                            </label>
                            {{-- Tarjeta --}}
                            <label class="cursor-pointer">
                                <input type="radio" x-model="payType" value="tarjeta" class="sr-only">
                                <div :class="payType === 'tarjeta' ? 'border-blue-500 bg-blue-500/10 ring-1 ring-blue-500' : 'border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-750'"
                                     class="rounded-xl border p-3 text-center transition-all cursor-pointer">
                                    <span class="block text-xl mb-1">💳</span>
                                    <span class="block text-xs font-bold text-gray-900 dark:text-white">Tarjeta</span>
                                    <span class="block text-[10px] text-gray-500">Precio lista</span>
                                </div>
                            </label>
                            {{-- Mixto --}}
                            <label class="cursor-pointer">
                                <input type="radio" x-model="payType" value="mixto" class="sr-only">
                                <div :class="payType === 'mixto' ? 'border-purple-500 bg-purple-500/10 ring-1 ring-purple-500' : 'border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-750'"
                                     class="rounded-xl border p-3 text-center transition-all cursor-pointer">
                                    <span class="block text-xl mb-1">💳+💵</span>
                                    <span class="block text-xs font-bold text-gray-900 dark:text-white">Mixto</span>
                                    <span class="block text-[10px] text-gray-500">Combo</span>
                                </div>
                            </label>
                        </div>

                        {{-- Resumen por tipo --}}
                        <div x-show="payType === 'efectivo'" class="bg-emerald-500/10 border border-emerald-500/30 rounded-xl p-4">
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-400">Total lista:</span>
                                <span class="text-gray-300 line-through">${{ number_format($subtotal, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between text-base font-black">
                                <span class="text-emerald-400">Total con 10% OFF:</span>
                                <span class="text-emerald-400">${{ number_format($subtotal * 0.90, 0, ',', '.') }}</span>
                            </div>
                        </div>

                        <div x-show="payType === 'tarjeta'" class="bg-blue-500/10 border border-blue-500/30 rounded-xl p-4">
                            <div class="flex justify-between text-base font-black">
                                <span class="text-blue-400">Total a pagar (precio lista):</span>
                                <span class="text-blue-400">${{ number_format($subtotal, 0, ',', '.') }}</span>
                            </div>
                        </div>

                        <div x-show="payType === 'mixto'" x-cloak class="space-y-4">
                            <div class="bg-purple-500/10 border border-purple-500/30 rounded-xl p-4 mb-2">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-purple-300 font-bold">Total a pagar de Contado:</span>
                                    <span class="text-lg font-black text-emerald-400">${{ number_format($subtotal * 0.90, 0, ',', '.') }}</span>
                                </div>
                                <p class="text-[10px] text-gray-400 mt-1">Si no completas este monto en efectivo, el resto vuelve al precio de lista para pagar con tarjeta.</p>
                            </div>

                            <div class="grid grid-cols-1 gap-4">
                                <div>
                                    <label class="block text-[11px] font-bold text-emerald-400 uppercase tracking-wider mb-2">¿Cuánto vas a entregar en Efectivo?</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 font-bold">$</span>
                                        <input type="number" x-model.number="cashAmt" min="0" :max="precioContado" step="1000"
                                            class="w-full py-3 pl-8 pr-4 bg-gray-50 dark:bg-gray-800 border border-emerald-500/50 dark:border-emerald-500/30 rounded-xl text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 text-lg font-bold">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700/50 rounded-xl p-4">
                                <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mb-2">
                                    <span>Resto de contado (<span x-text="'$'+Math.round(restoContado).toLocaleString('es-AR')"></span>) a precio lista:</span>
                                    <span class="text-blue-500 font-bold">$<span x-text="Math.round(calculatedCardAmt).toLocaleString('es-AR')"></span></span>
                                </div>
                                
                                <div class="flex justify-between text-sm font-bold border-t border-gray-200 dark:border-gray-700 pt-3">
                                    <span class="text-blue-500">Monto final a pagar con Tarjeta:</span>
                                    <span class="text-blue-500 text-lg">$<span x-text="Math.round(calculatedCardAmt).toLocaleString('es-AR')"></span></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    @if($has_mp_token)
                    <div class="mb-8">
                        <label class="block text-gray-700 dark:text-gray-400 text-xs font-bold mb-3 uppercase tracking-wider">Método de Pago</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Transferencia / Efectivo -->
                            <label class="relative flex cursor-pointer rounded-xl border p-4 shadow-sm focus:outline-none transition-all"
                                   :class="$wire.payment_method === 'transfer' ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-500/10 ring-1 ring-emerald-500' : 'border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800'">
                                <input type="radio" wire:model.live="payment_method" value="transfer" class="sr-only">
                                <span class="flex flex-1">
                                    <span class="flex flex-col">
                                        <span class="block text-sm font-bold text-gray-900 dark:text-white">Efectivo / Transferencia</span>
                                        @if(tenant('id') === 'g3')
                                        <span class="mt-1 flex items-center text-xs font-bold text-emerald-600 dark:text-emerald-400">10% OFF Aplicado</span>
                                        @endif
                                        <span class="mt-2 text-xs text-gray-500 dark:text-gray-400">Pagas al retirar o coordinamos envío por WhatsApp.</span>
                                    </span>
                                </span>
                                <svg class="h-5 w-5 text-emerald-600 transition-opacity" :class="$wire.payment_method === 'transfer' ? 'opacity-100' : 'opacity-0'" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                            </label>

                            <!-- Mercado Pago -->
                            @if($has_mp_token)
                            <div class="relative flex items-center p-4 border rounded-xl cursor-pointer transition-all duration-300"
                                   :class="$wire.payment_method === 'mercadopago' ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/5 ring-1 ring-[var(--color-primary)]' : 'border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800'">
                                <input type="radio" wire:model.live="payment_method" value="mercadopago" class="sr-only">
                                <div class="flex-1 ml-3 flex justify-between items-center">
                                    <div>
                                        <span class="block text-sm font-bold text-gray-900 dark:text-white">Mercado Pago</span>
                                        <span class="block text-xs text-gray-500 dark:text-gray-400 mt-0.5">Tarjetas, Efectivo (Rapipago/PagoFácil) o Dinero en cuenta</span>
                                    </div>
                                </div>
                                <svg class="h-5 w-5 text-[var(--color-primary)] transition-opacity" :class="$wire.payment_method === 'mercadopago' ? 'opacity-100' : 'opacity-0'" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif
                    
                    @if($has_mp_token)
                    <div x-show="$wire.payment_method === 'mercadopago'" x-collapse class="mb-8">
                        <div class="bg-[var(--color-primary)]/10 border border-[var(--color-primary)]/30 rounded-xl p-4">
                            <p class="text-sm text-[var(--color-primary)]">
                                <strong>Pago seguro:</strong> Al confirmar, serás redirigido a Mercado Pago para completar tu pago de forma rápida y segura.
                            </p>
                        </div>
                    </div>
                    @endif

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

                    <!-- Total Final Reminder (Mobile friendly) -->
                    @if(tenant('id') === 'g3')
                    <div class="mb-4 text-center">
                        <span class="text-sm font-bold text-gray-500 dark:text-gray-400 block mb-1">Total a Pagar</span>
                        <div x-show="payType === 'efectivo'">
                            <span class="text-3xl font-black text-emerald-500">${{ number_format($subtotal * 0.90, 0, ',', '.') }}</span>
                        </div>
                        <div x-show="payType === 'tarjeta'" style="display: none;">
                            <span class="text-3xl font-black text-gray-900 dark:text-white">${{ number_format($subtotal, 0, ',', '.') }}</span>
                        </div>
                        <div x-show="payType === 'mixto'" style="display: none;">
                            <span class="text-3xl font-black text-purple-500">$<span x-text="Math.round(totalMixto).toLocaleString('es-AR')"></span></span>
                        </div>
                    </div>
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
                        <span wire:loading.remove wire:target="placeOrder">
                            {{ $has_mp_token && $payment_method === 'mercadopago' ? 'Pagar con Mercado Pago' : 'Confirmar Pedido por WhatsApp' }}
                        </span>

                        {{-- Texto cargando --}}
                        <span wire:loading wire:target="placeOrder">
                            {{ $has_mp_token && $payment_method === 'mercadopago' ? 'Redirigiendo a Mercado Pago...' : 'Procesando...' }}
                        </span>

                    </button>
                </form>
            </div>
        </div>
    </div>
@endif
</div>
