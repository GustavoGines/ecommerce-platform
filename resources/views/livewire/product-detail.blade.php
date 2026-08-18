<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Product;
use App\Models\StoreSetting;

new #[Layout('layouts.app')] class extends Component {
    public Product $product;
    public $relatedProducts;
    public $recentlyViewedProducts;
    public $theme = 'modern-light';

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
        "{{ tenant_asset($product->image_url) }}"
      ],
      @endif
      "description": "{{ $product->description }}",
      "sku": "PRD-{{ $product->id }}",
      "brand": {
        "@@type": "Brand",
        "name": "{{ config('app.name', 'JCG Electrónica') }}"
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
    {{-- =========================================================
         TECH-DARK THEME: PRODUCT DETAIL (Original Design)
         ========================================================= --}}
    <x-slot name="header">
        <div class="flex items-center space-x-2 text-sm text-gray-500 dark:text-gray-400 dark:text-gray-500">
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
        <div class="bg-white dark:bg-zinc-900/80 dark:bg-gray-800/40 backdrop-blur-md border border-gray-200 dark:border-gray-700/50 shadow-xl dark:shadow-2xl sm:rounded-3xl p-8 mb-12">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
                <!-- Image Gallery -->
                @php
                    $images = $product->images;
                    if (is_string($images)) {
                        $images = json_decode($images, true);
                    }
                    if (!is_array($images) || empty($images)) {
                        $images = $product->image_url ? [$product->image_url] : [];
                    }
                @endphp
                <div class="relative group" x-data="{ lightboxOpen: false, currentSlide: 0, images: {{ Js::from($images) }}, interval: null }"
                     x-init="
                        if(images.length > 1) {
                            interval = setInterval(() => { currentSlide = (currentSlide + 1) % images.length }, 5000);
                        }
                     ">
                    <!-- Main Thumbnail -->
                    <div class="cursor-pointer aspect-square bg-gray-100 dark:bg-gray-900/80 rounded-2xl overflow-hidden border border-gray-200 dark:border-gray-700/50 flex items-center justify-center relative p-8"
                         @mouseenter="if(interval) clearInterval(interval)"
                         @mouseleave="if(images.length > 1) interval = setInterval(() => { currentSlide = (currentSlide + 1) % images.length }, 5000)">
                        
                        <!-- Hint icon -->
                        <div class="absolute top-4 right-4 bg-white dark:bg-zinc-900/50 dark:bg-black dark:bg-white/50 backdrop-blur-md p-2 rounded-full text-gray-700 dark:text-gray-300 opacity-0 group-hover:opacity-100 transition-opacity z-10" @click="lightboxOpen = true">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"></path></svg>
                        </div>

                        <!-- Left/Right Arrows for Carousel -->
                        <button x-show="images.length > 1" @click.stop="currentSlide = currentSlide === 0 ? images.length - 1 : currentSlide - 1" class="absolute left-4 top-1/2 -translate-y-1/2 bg-white/80 dark:bg-black/50 p-2 rounded-full shadow hover:bg-white dark:hover:bg-black transition z-10">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                        </button>
                        <button x-show="images.length > 1" @click.stop="currentSlide = (currentSlide + 1) % images.length" class="absolute right-4 top-1/2 -translate-y-1/2 bg-white/80 dark:bg-black/50 p-2 rounded-full shadow hover:bg-white dark:hover:bg-black transition z-10">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </button>

                        <template x-if="images.length > 0">
                            <img :src="'{{ rtrim(tenant_asset(''), '/') }}/' + images[currentSlide]" alt="{{ $product->name }}" @click="lightboxOpen = true" class="object-contain w-full h-full transform group-hover:scale-105 transition-transform duration-500 relative z-0">
                        </template>
                        <template x-if="images.length === 0">
                            <svg class="h-32 w-32 text-gray-300 dark:text-gray-600 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </template>
                    </div>

                    <!-- Thumbnails -->
                    <div x-show="images.length > 1" class="flex gap-2 mt-4 overflow-x-auto pb-2 snap-x dark-scrollbar">
                        <template x-for="(img, index) in images" :key="index">
                            <div @click="currentSlide = index; if(interval) clearInterval(interval); interval = setInterval(() => { currentSlide = (currentSlide + 1) % images.length }, 5000)"
                                 :class="currentSlide === index ? 'border-[var(--color-primary)] ring-2 ring-[var(--color-primary)]/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'"
                                 class="w-20 h-20 shrink-0 cursor-pointer rounded-lg overflow-hidden border-2 transition-all bg-white dark:bg-gray-900 snap-center">
                                <img :src="'{{ rtrim(tenant_asset(''), '/') }}/' + img" class="w-full h-full object-cover">
                            </div>
                        </template>
                    </div>

                    <!-- Lightbox Modal -->
                    <template x-teleport="body">
                        <div x-show="lightboxOpen" 
                             style="display: none; z-index: 99999;" 
                             class="fixed inset-0 flex items-center justify-center bg-white/95 dark:bg-zinc-950/95 backdrop-blur-md p-4 sm:p-8"
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             x-transition:leave="transition ease-in duration-200"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             @keydown.escape.window="lightboxOpen = false">
                             
                            <!-- Lightbox Click-away Area -->
                            <div class="absolute inset-0 cursor-zoom-out" @click="lightboxOpen = false"></div>
                            
                            <!-- Close Button -->
                            <button @click="lightboxOpen = false" style="z-index: 100000;" class="absolute top-6 right-6 sm:top-8 sm:right-8 text-black dark:text-white hover:text-red-500 transition-colors drop-shadow-lg">
                                <svg class="w-8 h-8 sm:w-10 sm:h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>

                            <!-- Left/Right Arrows for Lightbox -->
                            <button x-show="images.length > 1" @click.stop="currentSlide = currentSlide === 0 ? images.length - 1 : currentSlide - 1" style="z-index: 100000;" class="absolute left-6 top-1/2 -translate-y-1/2 bg-black/50 dark:bg-white/50 text-black dark:text-white p-3 sm:p-4 rounded-full shadow hover:bg-black dark:hover:bg-white transition">
                                <svg class="w-6 h-6 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                            </button>
                            <button x-show="images.length > 1" @click.stop="currentSlide = (currentSlide + 1) % images.length" style="z-index: 100000;" class="absolute right-6 top-1/2 -translate-y-1/2 bg-black/50 dark:bg-white/50 text-black dark:text-white p-3 sm:p-4 rounded-full shadow hover:bg-black dark:hover:bg-white transition">
                                <svg class="w-6 h-6 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                            </button>

                            <!-- Fullscreen Image -->
                            <template x-if="images.length > 0">
                                <img @click.stop src="" :src="'{{ rtrim(tenant_asset(''), '/') }}/' + images[currentSlide]" alt="{{ $product->name }}" style="max-height: 90vh; z-index: 99999;" class="object-contain max-w-full rounded-lg cursor-default shadow-2xl relative"
                                     x-transition:enter="transition ease-out duration-300 transform delay-100"
                                     x-transition:enter-start="opacity-0 scale-95"
                                     x-transition:enter-end="opacity-100 scale-100">
                            </template>
                        </div>
                    </template>
                </div>

                <!-- Info -->
                <div class="flex flex-col justify-center">
                    @if($product->stock <= 0)
                        <span class="inline-block px-3 py-1 bg-red-100 dark:bg-red-500/20 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-500/30 text-xs uppercase tracking-widest font-bold rounded-full w-max mb-4">Agotado</span>
                    @elseif($product->stock <= ($product->min_stock ?? 5))
                        <span class="inline-block px-3 py-1 bg-orange-100 dark:bg-orange-500/20 text-orange-700 dark:text-orange-400 border border-orange-200 dark:border-orange-500/30 text-xs uppercase tracking-widest font-bold rounded-full w-max mb-4" title="Stock Disponible">¡Solo quedan {{ $product->stock }}!</span>
                    @else
                        <span class="inline-block px-3 py-1 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-400 dark:text-gray-500 border border-gray-200 dark:border-gray-700 text-xs uppercase tracking-widest font-bold rounded-full w-max mb-4" title="Stock Disponible">Stock: {{ $product->stock }}</span>
                    @endif

                    <h1 class="text-3xl sm:text-4xl font-black text-gray-900 dark:text-white tracking-tight leading-tight mb-4">{{ $product->name }}</h1>
                    <p class="text-gray-600 dark:text-gray-400 dark:text-gray-500 text-lg mb-8 leading-relaxed whitespace-pre-line">{{ $product->description }}</p>

                    <div class="bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 mb-8">
                        <div class="flex justify-between items-end mb-4">
                            <span class="text-sm font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 dark:text-gray-500">Precio Unitario</span>
                            @if(auth()->check() && auth()->user()->isWholesaleCustomer())
                                <div class="flex flex-col items-end">
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-widest text-emerald-700 bg-emerald-50 border border-emerald-200 shadow-sm mb-1">
                                        🔥 Precio Mayorista VIP
                                    </span>
                                    <span class="text-4xl sm:text-5xl font-black tracking-tighter text-emerald-600">${{ number_format($product->wholesale_price, 2) }}</span>
                                </div>
                            @else
                                <span class="text-4xl sm:text-5xl font-black tracking-tighter text-gray-900 dark:text-white">${{ number_format($product->retail_price, 2) }}</span>
                            @endif
                        </div>
                        
                        @if(!(auth()->check() && auth()->user()->isWholesaleCustomer()) && $theme === 'modern-light')
                        <div class="relative group cursor-help mb-4" title="Descuento automático al llevar 10 o más unidades">
                            <div class="absolute -inset-0.5 bg-gradient-to-r from-emerald-500 to-teal-400 rounded-xl blur opacity-20 group-hover:opacity-40 transition duration-500"></div>
                            <div class="relative flex justify-between items-center p-5 rounded-xl bg-gradient-to-br from-emerald-50 to-emerald-100/30 dark:from-slate-800 dark:to-slate-900 border border-emerald-200/50 dark:border-emerald-700/50 shadow-sm">
                                <div>
                                    <span class="inline-flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 mb-1">
                                        <span class="relative flex h-2.5 w-2.5">
                                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                          <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                                        </span>
                                        Precio Mayorista
                                    </span>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 font-semibold mt-1 uppercase tracking-wider">Llevando {{ \App\Services\PricingService::GLOBAL_WHOLESALE_MIN }} o más artículos en total</p>
                                </div>
                                <div class="text-right flex flex-col items-end">
                                    <span class="text-2xl sm:text-3xl font-black tracking-tighter text-emerald-900 dark:text-emerald-100">${{ number_format($product->wholesale_price, 2) }}</span>
                                    <span class="opacity-75 font-bold text-[10px] bg-emerald-200/50 dark:bg-emerald-900/50 px-1.5 py-0.5 rounded text-emerald-800 dark:text-emerald-200 mt-1">C/U</span>
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
            <div class="bg-white dark:bg-zinc-900/80 dark:bg-gray-800/40 backdrop-blur-md border border-gray-200 dark:border-gray-700/50 shadow-sm rounded-2xl overflow-hidden">
                <dl class="divide-y divide-gray-200 dark:divide-gray-700/50">
                    @foreach($product->technical_specs as $key => $value)
                        <div class="px-6 py-5 grid grid-cols-3 gap-4 hover:bg-gray-50 dark:hover:bg-zinc-800 dark:bg-zinc-950 dark:hover:bg-gray-800/60 transition-colors">
                            <dt class="text-sm font-bold text-gray-500 dark:text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ $key }}</dt>
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
                                <img src="{{ tenant_asset($related->image_url) }}" class="object-contain h-full transform group-hover:scale-105 transition-transform duration-500">
                            @else
                                <svg class="h-12 w-12 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            @endif
                        </div>
                        <div class="p-4">
                            <h4 class="font-bold text-gray-900 dark:text-gray-100 truncate group-hover:text-[var(--color-primary)] transition-colors">{{ $related->name }}</h4>
                            <div class="mt-2 text-[var(--color-primary)] font-black">
                                ${{ number_format($related->retail_price, 2) }}
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>

