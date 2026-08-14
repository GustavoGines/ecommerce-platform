<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Product;
use App\Models\StoreSetting;

new #[Layout('layouts.app')] class extends Component {
    public Product $product;
    public $relatedProducts;
    public $recentlyViewedProducts;
    public $theme = 'stealth';

    public function mount($slug)
    {
        $this->product = Product::where('slug', $slug)->firstOrFail();
        
        $this->relatedProducts = Product::where('category_id', $this->product->category_id)
                                        ->where('id', '!=', $this->product->id)
                                        ->take(4)
                                        ->get();
                                        
        $settings = StoreSetting::getSettings();
        if ($settings && $settings->theme_name) {
            $this->theme = $settings->theme_name;
        }
        
        // Track recently viewed products
        $recentlyViewed = session()->get('recently_viewed', []);
        
        // Remove if it exists to put it at the beginning
        if (($key = array_search($this->product->id, $recentlyViewed)) !== false) {
            unset($recentlyViewed[$key]);
        }
        
        array_unshift($recentlyViewed, $this->product->id);
        
        // Keep only last 5 (so we can display 4 excluding current)
        $recentlyViewed = array_slice($recentlyViewed, 0, 5);
        session()->put('recently_viewed', $recentlyViewed);
        
        // Fetch recently viewed products (excluding the current one)
        $this->recentlyViewedProducts = Product::whereIn('id', $recentlyViewed)
                                               ->where('id', '!=', $this->product->id)
                                               ->take(4)
                                               ->get()
                                               ->sortBy(function($model) use ($recentlyViewed) {
                                                   return array_search($model->id, $recentlyViewed);
                                               });
    }
}; ?>

<div>
    <!-- SEO JSON-LD Microdata -->
    <script type="application/ld+json">
    {
      "@@context": "https://schema.org/",
      "@@type": "Product",
      "name": "{{ $product->name }}",
      @if($product->image_url)
      "image": [
        "{{ asset('storage/' . $product->image_url) }}"
      ],
      @endif
      "description": "{{ $product->description }}",
      "sku": "PRD-{{ $product->id }}",
      "brand": {
        "@@type": "Brand",
        "name": "{{ config('app.name', 'G3 Tecnología') }}"
      },
      "offers": {
        "@@type": "Offer",
        "url": "{{ request()->url() }}",
        "priceCurrency": "ARS",
        "price": "{{ $product->retail_price }}",
        "itemCondition": "https://schema.org/NewCondition",
        "availability": "{{ $product->stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock' }}"
      }
    }
    </script>
@if($theme === 'luxury')
    {{-- =========================================================
         LUXURY THEME: PRODUCT DETAIL (Apple/Turnstime Style)
         ========================================================= --}}
    <div class="bg-[#030712] min-h-screen text-white pb-24 -mt-16 pt-24" x-data="{ scrolled: false }" @scroll.window="scrolled = (window.pageYOffset > 50)">
        
        {{-- Custom Breadcrumbs & Top Navigation --}}
        <div class="sticky top-16 z-40 bg-[#030712]/80 backdrop-blur-xl border-b border-white/5 transition-all duration-300" :class="{'shadow-lg shadow-[var(--color-primary-glow)]': scrolled}">
            <div class="max-w-7xl mx-auto px-6 lg:px-8 py-4 flex items-center justify-between">
                <div class="flex items-center space-x-2 text-xs font-bold uppercase tracking-widest text-gray-500">
                    <a href="{{ route('shop') }}" wire:navigate class="hover:text-white transition-colors">Tienda</a>
                    <span class="text-white/20">/</span>
                    @if($product->category)
                        <a href="{{ route('shop', ['categoria' => $product->category->name]) }}" wire:navigate class="hover:text-white transition-colors cursor-pointer">{{ $product->category->name }}</a>
                    @else
                        <span class="text-gray-400">General</span>
                    @endif
                </div>
                {{-- Quick title on scroll --}}
                <div class="hidden md:block opacity-0 transition-opacity duration-500 font-bold tracking-widest uppercase text-sm" :class="{'opacity-100': scrolled}">
                    {{ $product->name }}
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-6 lg:px-8 mt-12">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-16 relative">
                
                {{-- Left: Immersive Image Gallery (Col-span 7) --}}
                <div class="lg:col-span-7">
                    <div class="sticky top-32">
                        {{-- Main Image Area with Glass & Zoom --}}
                        <div class="relative w-full aspect-[4/3] rounded-3xl bg-[#0a0f1c] border border-white/5 overflow-hidden group flex items-center justify-center p-12 cursor-crosshair"
                             x-data="{ zoom: false, x: 0, y: 0 }"
                             @mousemove="x = $event.offsetX; y = $event.offsetY"
                             @mouseenter="zoom = true"
                             @mouseleave="zoom = false">
                            {{-- Ambient glow --}}
                            <div class="absolute inset-0 bg-gradient-to-tr from-[var(--color-primary)]/10 to-transparent opacity-50 pointer-events-none"></div>
                            
                            @if($product->image_url)
                                {{-- Base Image --}}
                                <img src="{{ asset('storage/' . $product->image_url) }}" alt="{{ $product->name }}" class="relative z-10 w-full h-full object-contain transition-opacity duration-300 drop-shadow-2xl mix-blend-lighten pointer-events-none" :class="zoom ? 'opacity-0' : 'opacity-100'">
                                
                                {{-- Zoomed Image --}}
                                <div class="absolute inset-0 z-20 mix-blend-lighten"
                                     x-show="zoom"
                                     :style="`background-image: url('{{ asset('storage/' . $product->image_url) }}'); background-position: ${(x / $el.offsetWidth) * 100}% ${(y / $el.offsetHeight) * 100}%; background-size: 200%; background-repeat: no-repeat;`"
                                     style="display: none;"></div>
                            @else
                                <svg class="h-32 w-32 text-white/10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            @endif
                        </div>
                        
                        {{-- Below Image: Description --}}
                        <div class="mt-16">
                            <h3 class="text-xs font-bold text-[var(--color-primary)] uppercase tracking-widest mb-4">Resumen de Diseño</h3>
                            <p class="text-xl text-gray-400 font-light leading-relaxed">
                                {{ $product->description }}
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Right: Sticky Sidebar (Col-span 5) --}}
                <div class="lg:col-span-5">
                    <div class="sticky top-32 bg-white/5 backdrop-blur-2xl border border-white/10 rounded-[2rem] p-10 shadow-[0_0_50px_rgba(0,0,0,0.5)]">
                        
                        @if($product->stock <= 0)
                            <div class="inline-flex items-center gap-2 px-3 py-1 bg-red-500/10 text-red-400 border border-red-500/20 text-[10px] uppercase tracking-widest font-bold rounded-full mb-6" title="Agotado">
                                <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span> 🔴 Agotado / No Disponible
                            </div>
                        @else
                            <div class="inline-flex items-center gap-2 px-3 py-1 bg-[#1a1f2c]/40 text-gray-300 border border-white/5 text-[10px] uppercase tracking-widest font-bold rounded-full mb-6" title="Disponible">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> 🟢 Stock Disponible
                            </div>
                        @endif

                        <h1 class="text-4xl md:text-5xl font-black text-white tracking-tight leading-none mb-4">{{ $product->name }}</h1>
                        
                        {{-- Price Section --}}
                        <div class="mt-10 mb-10 pb-10 border-b border-white/10">
                            <p class="text-sm font-bold uppercase tracking-wider text-gray-500 mb-2">Precio de Lista</p>
                            <div class="flex items-end gap-4">
                                <span class="text-5xl font-black tracking-tighter text-white">${{ number_format($product->retail_price, 0, ',', '.') }}</span>
                                <span class="text-gray-500 mb-2 font-medium">Final</span>
                            </div>

                            <div class="mt-6 group cursor-help p-5 rounded-2xl bg-white/5 border border-white/5 hover:border-[var(--color-primary)]/50 transition-all duration-300">
                                <div class="flex justify-between items-center mb-1">
                                    <span class="inline-flex items-center gap-2 text-[10px] font-bold uppercase tracking-widest text-emerald-400">
                                        <span class="relative flex h-2 w-2">
                                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <div class="mt-8 pt-6 border-t border-white/10">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                        <span class="text-xs uppercase tracking-widest font-black text-emerald-400">Precio Efectivo / Transferencia</span>
                                    </div>
                                    <div class="flex items-end gap-3">
                                        <span class="text-2xl font-black text-emerald-400">${{ number_format($product->wholesale_price, 0, ',', '.') }} <span class="text-[10px] opacity-70">C/U</span></span>
                                    </div>
                                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-2">10% DE DESCUENTO</p>
                                </div>
                        </div>

                        {{-- Add to Cart livewire component (needs to be adapted to luxury context but we'll use the existing one, it has generic tailwind) --}}
                        <div class="add-to-cart-luxury">
                            <livewire:add-to-cart :product="$product" wire:key="detail-cart-luxury-{{ $product->id }}" />
                        </div>

                        {{-- Trust Badges --}}
                        <div class="mt-10 grid grid-cols-1 gap-4 mb-10">
                            <div class="flex items-center gap-3 text-gray-400">
                                <svg class="w-5 h-5 text-[var(--color-primary)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                <span class="text-xs font-bold uppercase tracking-wider">Pago Seguro</span>
                            </div>
                        </div>
                        
                        {{-- Accordions --}}
                        <div class="border-t border-white/10" x-data="{ active: null }">
                            {{-- Accordion 1 --}}
                            <div class="border-b border-white/10">
                                <button @click="active = (active === 1 ? null : 1)" class="w-full flex justify-between items-center py-5 text-left transition-colors hover:text-[var(--color-primary)]">
                                    <span class="text-sm font-bold tracking-widest uppercase">Envíos y Entregas</span>
                                    <svg class="w-5 h-5 transform transition-transform duration-300" :class="{'rotate-180': active === 1}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 9l-7 7-7-7"></path></svg>
                                </button>
                                <div x-show="active === 1" x-collapse x-cloak>
                                    <div class="pb-5 text-sm text-gray-400 font-light leading-relaxed">
                                        Una vez realizado tu pedido, nos comunicaremos con vos por WhatsApp para coordinar la forma de envío o retiro que mejor se adapte a tus necesidades.
                                    </div>
                                </div>
                            </div>
                            

                        </div>
                    </div>
                </div>
            </div>

            {{-- Technical Specs Full Width --}}
            @if($product->technical_specs && count($product->technical_specs) > 0)
            <div class="mt-32 pt-24 border-t border-white/5">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-12">
                    <div>
                        <h3 class="text-3xl font-black text-white tracking-tight mb-4">Especificaciones<br><span class="text-gray-600">Técnicas</span></h3>
                        <p class="text-gray-400 font-light">Diseñado con precisión milimétrica para ofrecer el máximo rendimiento sin compromisos térmicos.</p>
                    </div>
                    <div class="lg:col-span-2">
                        <div class="divide-y divide-white/5 border-t border-b border-white/5">
                            @foreach($product->technical_specs as $key => $value)
                                <div class="py-6 grid grid-cols-1 md:grid-cols-3 gap-4 group hover:bg-white/5 transition-colors px-4 -mx-4 rounded-xl">
                                    <dt class="text-xs font-bold text-gray-500 uppercase tracking-widest">{{ $key }}</dt>
                                    <dd class="text-base text-gray-200 font-medium md:col-span-2 group-hover:text-white transition-colors">{{ $value }}</dd>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- Related Products --}}
            @if($relatedProducts->count() > 0)
            <div class="mt-32 pt-24 border-t border-white/5">
                <h3 class="text-2xl font-black text-white tracking-tight mb-10 flex items-center justify-between">
                    Completá tu Setup
                    <a href="{{ route('home') }}#catalog" class="text-[10px] uppercase tracking-widest text-gray-500 hover:text-[var(--color-primary)] transition-colors">Ver Todo</a>
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
                    @foreach ($relatedProducts as $related)
                        <a href="{{ route('product.detail', $related->slug) }}" wire:navigate class="group relative flex flex-col bg-white/5 backdrop-blur-xl rounded-2xl border border-white/5 overflow-hidden transition-all duration-500 hover:border-[var(--color-primary)]/50 hover:shadow-[0_20px_40px_-15px_var(--color-primary-glow)] hover:-translate-y-2">
                            <div class="relative aspect-[4/3] bg-[#0a0f1c] p-6 flex items-center justify-center border-b border-white/5 overflow-hidden">
                                @if($related->image_url)
                                    <img src="{{ asset('storage/' . $related->image_url) }}" class="object-contain w-full h-full transform group-hover:scale-110 transition-transform duration-700 drop-shadow-xl" onerror="this.src='https://images.unsplash.com/photo-1587202372775-e229f172b9d7?q=80&w=400&auto=format&fit=crop'; this.classList.add('mix-blend-lighten')">
                                @endif
                                <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center">
                                    <span class="text-xs font-bold text-white uppercase tracking-widest bg-white/10 backdrop-blur-md px-4 py-2 rounded-full">Ver Detalles</span>
                                </div>
                            </div>
                            <div class="p-6">
                                <h4 class="font-bold text-white truncate transition-colors">{{ $related->name }}</h4>
                                <div class="mt-2 text-gray-400 font-bold">
                                    ${{ number_format($related->retail_price, 0, ',', '.') }}
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
            @endif
            
            {{-- Recently Viewed Products --}}
            @if($recentlyViewedProducts && $recentlyViewedProducts->count() > 0)
            <div class="mt-24 pt-24 border-t border-white/5">
                <h3 class="text-2xl font-black text-white tracking-tight mb-10 flex items-center justify-between">
                    Recientemente Vistos
                    <a href="{{ route('shop') }}" wire:navigate class="text-[10px] uppercase tracking-widest text-gray-500 hover:text-[var(--color-primary)] transition-colors">Ir al Catálogo</a>
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
                    @foreach ($recentlyViewedProducts as $viewed)
                        <a href="{{ route('product.detail', $viewed->slug) }}" wire:navigate class="group relative flex flex-col bg-white/5 backdrop-blur-xl rounded-2xl border border-white/5 overflow-hidden transition-all duration-500 hover:border-[var(--color-primary)]/50 hover:shadow-[0_20px_40px_-15px_var(--color-primary-glow)] hover:-translate-y-2">
                            <div class="relative aspect-[4/3] bg-[#0a0f1c] p-6 flex items-center justify-center border-b border-white/5 overflow-hidden">
                                @if($viewed->image_url)
                                    <img src="{{ asset('storage/' . $viewed->image_url) }}" class="object-contain w-full h-full transform group-hover:scale-110 transition-transform duration-700 drop-shadow-xl" onerror="this.src='https://images.unsplash.com/photo-1587202372775-e229f172b9d7?q=80&w=400&auto=format&fit=crop'; this.classList.add('mix-blend-lighten')">
                                @endif
                                <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center">
                                    <span class="text-xs font-bold text-white uppercase tracking-widest bg-white/10 backdrop-blur-md px-4 py-2 rounded-full">Ver Detalles</span>
                                </div>
                            </div>
                            <div class="p-6">
                                <h4 class="font-bold text-white truncate transition-colors">{{ $viewed->name }}</h4>
                                <div class="mt-2 text-gray-400 font-bold">
                                    ${{ number_format($viewed->retail_price, 0, ',', '.') }}
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
@else
    {{-- =========================================================
         STEALTH THEME: PRODUCT DETAIL (Original Design)
         ========================================================= --}}
    <x-slot name="header">
        <div class="flex items-center space-x-2 text-sm text-gray-500 dark:text-gray-400">
            <a href="{{ route('shop') }}" wire:navigate class="hover:text-[var(--color-primary)] transition-colors">Tienda</a>
            <span>/</span>
            @if($product->category)
                <a href="{{ route('shop', ['categoria' => $product->category->name]) }}" wire:navigate class="hover:text-[var(--color-primary)] transition-colors">{{ $product->category->name }}</a>
            @else
                <span>General</span>
            @endif
            <span>/</span>
            <span class="text-gray-900 dark:text-gray-100 font-medium">{{ $product->name }}</span>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
        
        <!-- Product Main Details -->
        <div class="bg-white/80 dark:bg-gray-800/40 backdrop-blur-md border border-gray-200 dark:border-gray-700/50 shadow-xl dark:shadow-2xl sm:rounded-3xl p-8 mb-12">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
                <!-- Image Gallery -->
                <div class="relative group" x-data="{ lightboxOpen: false }">
                    <!-- Thumbnail -->
                    <div @click="lightboxOpen = true" class="cursor-pointer aspect-square bg-gray-100 dark:bg-gray-900/80 rounded-2xl overflow-hidden border border-gray-200 dark:border-gray-700/50 flex items-center justify-center p-8 relative">
                        <!-- Hint icon -->
                        <div class="absolute top-4 right-4 bg-white/50 dark:bg-black/50 backdrop-blur-md p-2 rounded-full text-gray-700 dark:text-gray-300 opacity-0 group-hover:opacity-100 transition-opacity">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"></path></svg>
                        </div>

                        @if($product->image_url)
                            <img src="{{ asset('storage/' . $product->image_url) }}" alt="{{ $product->name }}" class="object-contain w-full h-full transform group-hover:scale-105 transition-transform duration-500">
                        @else
                            <svg class="h-32 w-32 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        @endif
                    </div>

                    <!-- Lightbox Modal -->
                    <template x-teleport="body">
                        <div x-show="lightboxOpen" 
                             style="display: none; z-index: 99999;" 
                             @click="lightboxOpen = false"
                             class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-90 backdrop-blur-md p-4 sm:p-8 cursor-zoom-out"
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             x-transition:leave="transition ease-in duration-200"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             @keydown.escape.window="lightboxOpen = false">
                            
                            <!-- Close Button -->
                            <button @click="lightboxOpen = false" style="z-index: 100000;" class="absolute top-6 right-6 sm:top-8 sm:right-8 text-white hover:text-red-500 transition-colors drop-shadow-lg">
                                <svg class="w-8 h-8 sm:w-10 sm:h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>

                            <!-- Fullscreen Image -->
                            @if($product->image_url)
                                <img @click.stop src="{{ asset('storage/' . $product->image_url) }}" alt="{{ $product->name }}" style="max-height: 90vh;" class="object-contain max-w-full rounded-lg cursor-default shadow-2xl"
                                     x-transition:enter="transition ease-out duration-300 transform delay-100"
                                     x-transition:enter-start="opacity-0 scale-95"
                                     x-transition:enter-end="opacity-100 scale-100">
                            @endif
                        </div>
                    </template>
                </div>

                <!-- Info -->
                <div class="flex flex-col justify-center">
                    @if($product->stock <= 0)
                        <span class="inline-block px-3 py-1 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-800 text-xs uppercase tracking-widest font-bold rounded-full w-max mb-4" title="Agotado">🔴 Agotado / No Disponible</span>
                    @else
                        <span class="inline-block px-3 py-1 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-400 border border-gray-200 dark:border-gray-700 text-xs uppercase tracking-widest font-bold rounded-full w-max mb-4" title="Disponible">🟢 Stock Disponible</span>
                    @endif

                    <h1 class="text-3xl sm:text-4xl font-black text-gray-900 dark:text-white tracking-tight leading-tight mb-4">{{ $product->name }}</h1>
                    <p class="text-gray-600 dark:text-gray-400 text-lg mb-8 leading-relaxed">{{ $product->description }}</p>

                    <div class="bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 mb-8">
                        <div class="flex justify-between items-end mb-4">
                            <span class="text-sm font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Precio Unitario</span>
                            @if(auth()->check() && auth()->user()->isWholesaleCustomer())
                                <div class="flex flex-col items-end">
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-widest text-emerald-700 bg-emerald-50 border border-emerald-200 shadow-sm mb-1">
                                        🔥 Precio Especial VIP
                                    </span>
                                    <span class="text-4xl sm:text-5xl font-black tracking-tighter text-emerald-600">${{ number_format($product->wholesale_price, 0, ',', '.') }}</span>
                                </div>
                            @else
                                <span class="text-4xl sm:text-5xl font-black tracking-tighter text-gray-900 dark:text-white">${{ number_format($product->retail_price, 0, ',', '.') }}</span>
                            @endif
                        </div>
                        
                        @if(!(auth()->check() && auth()->user()->isWholesaleCustomer()) && $theme === 'modern-light')
                        <div class="relative group cursor-help mb-4">
                            <div class="absolute -inset-0.5 bg-gradient-to-r from-emerald-500 to-teal-400 rounded-xl blur opacity-20 group-hover:opacity-40 transition duration-500"></div>
                            <div class="relative flex justify-between items-center p-5 rounded-xl bg-gradient-to-br from-emerald-50 to-emerald-100/30 dark:from-slate-800 dark:to-slate-900 border border-emerald-200/50 dark:border-emerald-700/50 shadow-sm">
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                    <span class="text-xs font-black uppercase tracking-widest text-emerald-600 dark:text-emerald-500">Precio Efectivo / Transferencia</span>
                                </div>
                                <p class="text-xs text-slate-500 dark:text-slate-400 font-semibold mt-1 uppercase tracking-wider">10% DE DESCUENTO</p>
                                </div>
                                <div class="text-right flex flex-col items-end">
                                    <div class="mt-2 flex items-baseline gap-2">
                                    <span class="text-2xl sm:text-3xl font-black tracking-tighter text-emerald-900 dark:text-emerald-100">${{ number_format($product->wholesale_price, 0, ',', '.') }}</span>
                                    <span class="text-xs font-bold text-emerald-700 dark:text-emerald-300">C/U</span>
                                </div>
                                </div>
                            </div>
                        </div>
                        @endif
                        

                    </div>

                    <div>
                        <livewire:add-to-cart :product="$product" wire:key="detail-cart-{{ $product->id }}" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Technical Specs -->
        @if($product->technical_specs && count($product->technical_specs) > 0)
        <div class="mb-16">
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight mb-6 flex items-center">
                <svg class="w-6 h-6 mr-2 text-[var(--color-primary)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path></svg>
                Especificaciones Técnicas
            </h3>
            <div class="bg-white/80 dark:bg-gray-800/40 backdrop-blur-md border border-gray-200 dark:border-gray-700/50 shadow-sm rounded-2xl overflow-hidden">
                <dl class="divide-y divide-gray-200 dark:divide-gray-700/50">
                    @foreach($product->technical_specs as $key => $value)
                        <div class="px-6 py-5 grid grid-cols-3 gap-4 hover:bg-gray-50 dark:hover:bg-gray-800/60 transition-colors">
                            <dt class="text-sm font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $key }}</dt>
                            <dd class="text-sm text-gray-900 dark:text-white font-medium col-span-2">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </div>
        @endif

        <!-- Related Products -->
        @if($relatedProducts->count() > 0)
        <div>
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight mb-6">Productos Similares</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                @foreach ($relatedProducts as $related)
                    <a href="{{ route('product.detail', $related->slug) }}" wire:navigate class="group bg-white dark:bg-gray-800/40 backdrop-blur-md border border-gray-200 dark:border-gray-700/50 rounded-2xl overflow-hidden hover:-translate-y-1 transition-all duration-300 block hover:shadow-xl dark:hover:shadow-[var(--color-primary-glow)]">
                        <div class="aspect-video bg-gray-100 dark:bg-gray-900/80 p-4 flex items-center justify-center border-b border-gray-200 dark:border-gray-700/50">
                            @if($related->image_url)
                                <img src="{{ asset('storage/' . $related->image_url) }}" class="object-contain h-full transform group-hover:scale-105 transition-transform duration-500">
                            @endif
                        </div>
                        <div class="p-4">
                            <h4 class="font-bold text-gray-900 dark:text-gray-100 truncate group-hover:text-[var(--color-primary)] transition-colors">{{ $related->name }}</h4>
                            <div class="mt-2 text-[var(--color-primary)] font-black">
                                ${{ number_format($related->retail_price, 0, ',', '.') }}
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
        @endif
    </div>
@endif
</div>
