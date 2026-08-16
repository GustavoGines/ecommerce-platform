<x-app-layout>
    @php
        $settings = \App\Models\StoreSetting::getSettings();
        $storeName = $settings ? $settings->store_name : 'JCG Electrónica';
        
        // Obtenemos los últimos 8 productos para la página de inicio
        $latestProducts = \App\Models\Product::with('category')->latest()->take(8)->get();
        // Categorías principales para acceso rápido
        $categories = \App\Models\Category::take(4)->get();
    @endphp

    <div class="bg-white dark:bg-zinc-900 min-h-screen">
        
        {{-- ════════════════════════════════════════════════════════
             HERO SECTION — Limpio, centrado en Búsqueda
        ════════════════════════════════════════════════════════ --}}
        <section class="relative z-20 bg-gray-50 dark:bg-zinc-950 border-b border-gray-100 dark:border-zinc-800" x-data="{ loaded: false }" x-init="setTimeout(() => loaded = true, 100)">
            {{-- Elementos decorativos de fondo --}}
            <div class="absolute inset-0 overflow-hidden pointer-events-none z-0">
                <div class="absolute top-0 right-0 -mr-20 -mt-20 w-96 h-96 rounded-full bg-red-50 opacity-50 blur-3xl transition-all duration-1000 transform" :class="loaded ? 'scale-100 translate-y-0' : 'scale-50 -translate-y-12'"></div>
                <div class="absolute bottom-0 left-0 -ml-20 -mb-20 w-80 h-80 rounded-full bg-gray-100 dark:bg-zinc-800 opacity-50 blur-3xl transition-all duration-1000 delay-300 transform" :class="loaded ? 'scale-100 translate-x-0' : 'scale-50 -translate-x-12'"></div>
            </div>
            
            <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-28 flex flex-col lg:flex-row items-center gap-12">
                
                {{-- Texto y Búsqueda --}}
                <div class="flex-1 text-center lg:text-left z-10 transition-all duration-1000 transform" :class="loaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-8'">
                    <span class="inline-block py-1 px-3 rounded-full bg-red-100 text-[var(--color-primary)] text-xs font-bold uppercase tracking-wider mb-6 animate-pulse">
                        Mayorista y Minorista
                    </span>
                    <h1 class="text-5xl lg:text-7xl font-black text-gray-900 dark:text-white tracking-tight leading-[1.1] mb-6">
                        Encuentra tu <br class="hidden lg:block">
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-red-600 to-red-400">Control Remoto</span>
                    </h1>
                    <p class="text-lg text-gray-600 dark:text-gray-300 mb-8 max-w-2xl mx-auto lg:mx-0">
                        Tenemos el catálogo más completo de controles remotos para TV, Smart TV, Aire Acondicionado y TV Box. Busca por marca o modelo.
                    </p>
                    
                    {{-- Buscador Hero (Live Search) --}}
                    <div class="transition-all duration-1000 delay-200 transform relative z-50" 
                         :class="loaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-8'">
                        @livewire('search-bar', ['variant' => 'hero'])
                    </div>
                    
                    {{-- Marcas --}}
                    <div class="mt-10 pt-8 border-t border-gray-200 dark:border-zinc-800 transition-all duration-1000 delay-300 transform" :class="loaded ? 'opacity-100' : 'opacity-0'">
                        <p class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-4">Trabajamos con todas las marcas</p>
                        <div class="flex flex-wrap justify-center lg:justify-start gap-4 text-gray-500 dark:text-gray-400 dark:text-gray-500 font-bold text-sm">
                            <span class="hover:text-gray-800 dark:text-gray-200 transition-colors cursor-default">SAMSUNG</span>
                            <span>&bull;</span>
                            <span class="hover:text-gray-800 dark:text-gray-200 transition-colors cursor-default">LG</span>
                            <span>&bull;</span>
                            <span class="hover:text-gray-800 dark:text-gray-200 transition-colors cursor-default">NOBLEX</span>
                            <span>&bull;</span>
                            <span class="hover:text-gray-800 dark:text-gray-200 transition-colors cursor-default">PHILIPS</span>
                            <span>&bull;</span>
                            <span class="hover:text-gray-800 dark:text-gray-200 transition-colors cursor-default">BGH</span>
                        </div>
                    </div>
                </div>
                
                {{-- Imagen Destacada --}}
                <div class="flex-1 w-full max-w-md lg:max-w-full relative z-10 hidden md:block transition-all duration-1000 delay-300 transform" :class="loaded ? 'opacity-100 translate-x-0' : 'opacity-0 translate-x-12'">
                    <div class="relative w-full aspect-square flex items-center justify-center">
                        <div class="absolute inset-0 bg-white dark:bg-zinc-900 rounded-full shadow-2xl opacity-80 scale-75 animate-[pulse_4s_ease-in-out_infinite]"></div>
                        <img src="{{ asset('storage/banners/tv_remote.png') }}" alt="Control Remoto Moderno" class="relative z-10 w-3/4 object-contain drop-shadow-2xl hover:scale-110 transition-transform duration-500 hover:-rotate-6 mix-blend-multiply cursor-crosshair">
                        <img src="{{ asset('storage/banners/ac_remote.png') }}" alt="Control de Aire" class="absolute z-0 w-1/2 object-contain drop-shadow-xl -bottom-10 -right-10 opacity-90 -rotate-12 blur-[1px] mix-blend-multiply animate-[bounce_5s_infinite]">
                    </div>
                </div>
            </div>
        </section>

        {{-- ════════════════════════════════════════════════════════
             HERO CAROUSEL (FLYERS PROMOCIONALES)
        ════════════════════════════════════════════════════════ --}}
        <section class="w-full bg-gray-950 border-b border-gray-900" x-data="{ activeSlide: 1, slides: [1, 2, 3], autoSlide: null }"
                 x-init="autoSlide = setInterval(() => { activeSlide = activeSlide === slides.length ? 1 : activeSlide + 1 }, 5000)">
            <div class="relative w-full h-[400px] md:h-[500px] overflow-hidden">
                
                {{-- Slide 1: Mayorista --}}
                <div class="absolute inset-0 transition-opacity duration-1000"
                     :class="activeSlide === 1 ? 'opacity-100 z-10' : 'opacity-0 z-0'">
                    <img src="{{ asset('storage/banners/bg_slider_1.png') }}" class="absolute inset-0 w-full h-full object-cover opacity-60" alt="Precios Mayoristas">
                    <div class="absolute inset-0 bg-gradient-to-r from-black via-black/80 to-transparent"></div>
                    <div class="relative z-20 h-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col justify-center">
                        <span class="inline-block py-1 px-3 rounded-full bg-red-600/20 text-red-500 border border-red-500/50 text-xs font-bold uppercase tracking-wider mb-4 w-max">Promo Exclusiva</span>
                        <h2 class="text-4xl md:text-6xl font-black text-white dark:text-black mb-4 leading-tight">Llevá más, <br><span class="text-red-500">pagá menos.</span></h2>
                        <p class="text-gray-300 max-w-lg md:text-lg mb-8">Llevá 10 unidades o más en tu primera compra y accedé al beneficio de <strong>PRECIO MAYORISTA</strong> para siempre, incluso comprando por unidad.</p>
                        <a href="{{ route('shop') }}" class="inline-flex items-center justify-center px-8 py-3 rounded-full bg-red-600 text-white dark:text-black font-bold hover:bg-red-700 transition-colors w-max shadow-[0_0_20px_rgba(220,38,38,0.3)]">Ver Catálogo</a>
                    </div>
                </div>

                {{-- Slide 2: Retiro en Local --}}
                <div class="absolute inset-0 transition-opacity duration-1000"
                     :class="activeSlide === 2 ? 'opacity-100 z-10' : 'opacity-0 z-0'">
                    <img src="{{ asset('storage/banners/bg_slider_2.png') }}" class="absolute inset-0 w-full h-full object-cover opacity-60" alt="Retiro Inmediato">
                    <div class="absolute inset-0 bg-gradient-to-r from-black via-black/80 to-transparent"></div>
                    <div class="relative z-20 h-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col justify-center">
                        <span class="inline-block py-1 px-3 rounded-full bg-yellow-500/20 text-yellow-400 border border-yellow-500/50 text-xs font-bold uppercase tracking-wider mb-4 w-max">Sin demoras</span>
                        <h2 class="text-4xl md:text-6xl font-black text-white dark:text-black mb-4 leading-tight">Retiro Inmediato <br><span class="text-yellow-400">en Nuestro Local</span></h2>
                        <p class="text-gray-300 max-w-lg md:text-lg mb-8">Hacé tu pedido online y pasá a buscarlo por nuestra tienda sin hacer filas ni esperar tiempos de envío. ¡Fácil y rápido!</p>
                        <a href="{{ route('shop') }}" class="inline-flex items-center justify-center px-8 py-3 rounded-full bg-yellow-500 text-black font-bold hover:bg-yellow-400 transition-colors w-max shadow-[0_0_20px_rgba(234,179,8,0.3)]">Comprar Ahora</a>
                    </div>
                </div>

                {{-- Slide 3: Accesorios --}}
                <div class="absolute inset-0 transition-opacity duration-1000"
                     :class="activeSlide === 3 ? 'opacity-100 z-10' : 'opacity-0 z-0'">
                    <img src="{{ asset('storage/banners/bg_slider_3.png') }}" class="absolute inset-0 w-full h-full object-cover opacity-60" alt="Accesorios">
                    <div class="absolute inset-0 bg-gradient-to-r from-black via-black/80 to-transparent"></div>
                    <div class="relative z-20 h-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col justify-center">
                        <span class="inline-block py-1 px-3 rounded-full bg-blue-500/20 text-blue-400 border border-blue-500/50 text-xs font-bold uppercase tracking-wider mb-4 w-max">Nuevo Catálogo</span>
                        <h2 class="text-4xl md:text-6xl font-black text-white dark:text-black mb-4 leading-tight">Accesorios <br><span class="text-blue-400">Premium</span></h2>
                        <p class="text-gray-300 max-w-lg md:text-lg mb-8">Descubrí nuestra nueva línea de fundas, cargadores rápidos, cables reforzados y mucho más para tu celular.</p>
                        <a href="{{ route('shop', ['categoria' => 'Accesorios']) }}" class="inline-flex items-center justify-center px-8 py-3 rounded-full bg-blue-600 text-white dark:text-black font-bold hover:bg-blue-500 transition-colors w-max shadow-[0_0_20px_rgba(37,99,235,0.3)]">Ver Accesorios</a>
                    </div>
                </div>

                {{-- Navegación Puntos --}}
                <div class="absolute bottom-6 left-0 right-0 z-30 flex justify-center gap-3">
                    <template x-for="slide in slides" :key="slide">
                        <button @click="activeSlide = slide; clearInterval(autoSlide); autoSlide = setInterval(() => { activeSlide = activeSlide === slides.length ? 1 : activeSlide + 1 }, 5000)"
                                class="w-10 h-1 rounded-full transition-all duration-300"
                                :class="activeSlide === slide ? 'bg-red-600 scale-y-150' : 'bg-white dark:bg-zinc-900/30 hover:bg-white dark:bg-zinc-900/60'"></button>
                    </template>
                </div>
                
                {{-- Flechas Laterales --}}
                <button @click="activeSlide = activeSlide === 1 ? slides.length : activeSlide - 1; clearInterval(autoSlide); autoSlide = setInterval(() => { activeSlide = activeSlide === slides.length ? 1 : activeSlide + 1 }, 5000)" 
                        class="absolute left-4 top-1/2 -translate-y-1/2 z-30 w-12 h-12 flex items-center justify-center rounded-full bg-black dark:bg-white/20 hover:bg-black dark:bg-white/50 text-white dark:text-black backdrop-blur-sm transition-all opacity-0 group-hover:opacity-100 lg:opacity-100">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                </button>
                <button @click="activeSlide = activeSlide === slides.length ? 1 : activeSlide + 1; clearInterval(autoSlide); autoSlide = setInterval(() => { activeSlide = activeSlide === slides.length ? 1 : activeSlide + 1 }, 5000)" 
                        class="absolute right-4 top-1/2 -translate-y-1/2 z-30 w-12 h-12 flex items-center justify-center rounded-full bg-black dark:bg-white/20 hover:bg-black dark:bg-white/50 text-white dark:text-black backdrop-blur-sm transition-all opacity-0 group-hover:opacity-100 lg:opacity-100">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </button>
            </div>
        </section>

        {{-- ════════════════════════════════════════════════════════
             CATEGORÍAS DESTACADAS
        ════════════════════════════════════════════════════════ --}}
        <section class="py-16 bg-white dark:bg-zinc-900 border-b border-gray-100 dark:border-zinc-800" x-data="{ intersecting: false }" x-intersect.once="intersecting = true">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 transition-all duration-1000 delay-100 transform" :class="intersecting ? 'translate-y-0 opacity-100' : 'translate-y-12 opacity-0'">
                    
                    <a href="{{ route('shop', ['categoria' => 'TV']) }}" class="group relative h-32 md:h-40 rounded-2xl overflow-hidden shadow-sm flex items-center justify-center bg-gray-900 hover:shadow-[0_8px_30px_rgba(220,38,38,0.2)] transition-all duration-300">
                        <div class="absolute inset-0 bg-gradient-to-t from-gray-900 to-transparent opacity-80 z-10"></div>
                        <img src="{{ asset('storage/banners/cat_tv.png') }}" alt="Controles TV" class="absolute inset-0 w-full h-full object-cover opacity-60 group-hover:scale-110 group-hover:opacity-80 transition-all duration-500">
                        <span class="relative z-20 text-white dark:text-black font-bold text-sm md:text-base text-center px-4 tracking-wide group-hover:-translate-y-1 transition-transform drop-shadow-md">CONTROLES TV / SMART</span>
                    </a>
                    
                    <a href="{{ route('shop', ['categoria' => 'Aire']) }}" class="group relative h-32 md:h-40 rounded-2xl overflow-hidden shadow-sm flex items-center justify-center bg-gray-900 hover:shadow-[0_8px_30px_rgba(37,99,235,0.2)] transition-all duration-300">
                        <div class="absolute inset-0 bg-gradient-to-t from-gray-900 to-transparent opacity-80 z-10"></div>
                        <img src="{{ asset('storage/banners/cat_ac.png') }}" alt="Aire Acondicionado" class="absolute inset-0 w-full h-full object-cover opacity-60 group-hover:scale-110 group-hover:opacity-80 transition-all duration-500">
                        <span class="relative z-20 text-white dark:text-black font-bold text-sm md:text-base text-center px-4 tracking-wide group-hover:-translate-y-1 transition-transform drop-shadow-md">AIRE ACONDICIONADO</span>
                    </a>

                    <a href="{{ route('shop', ['categoria' => 'TV Box']) }}" class="group relative h-32 md:h-40 rounded-2xl overflow-hidden shadow-sm flex items-center justify-center bg-gray-900 hover:shadow-[0_8px_30px_rgba(245,158,11,0.2)] transition-all duration-300">
                        <div class="absolute inset-0 bg-gradient-to-t from-gray-900 to-transparent opacity-80 z-10"></div>
                        <img src="{{ asset('storage/banners/cat_tvbox.png') }}" alt="TV Box" class="absolute inset-0 w-full h-full object-cover opacity-60 group-hover:scale-110 group-hover:opacity-80 transition-all duration-500">
                        <span class="relative z-20 text-white dark:text-black font-bold text-sm md:text-base text-center px-4 tracking-wide group-hover:-translate-y-1 transition-transform drop-shadow-md">TV BOX</span>
                    </a>

                    <a href="{{ route('shop', ['categoria' => 'Accesorios']) }}" class="group relative h-32 md:h-40 rounded-2xl overflow-hidden shadow-sm flex items-center justify-center bg-gray-900 hover:shadow-[0_8px_30px_rgba(16,185,129,0.2)] transition-all duration-300">
                        <div class="absolute inset-0 bg-gradient-to-t from-gray-900 to-transparent opacity-80 z-10"></div>
                        <img src="{{ asset('storage/banners/cat_accesorios.png') }}" alt="Accesorios Celulares" class="absolute inset-0 w-full h-full object-cover opacity-60 group-hover:scale-110 group-hover:opacity-80 transition-all duration-500">
                        <span class="relative z-20 text-white dark:text-black font-bold text-sm md:text-base text-center px-4 tracking-wide group-hover:-translate-y-1 transition-transform drop-shadow-md">ACCESORIOS Y CELULARES</span>
                    </a>
                    
                </div>
            </div>
        </section>

        {{-- ════════════════════════════════════════════════════════
             ÚLTIMOS INGRESOS (Reutilizando diseño de tarjeta)
        ════════════════════════════════════════════════════════ --}}
        <section class="py-20 bg-white dark:bg-zinc-900">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <div class="flex items-end justify-between mb-10">
                    <div>
                        <h2 class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">Últimos Ingresos</h2>
                        <p class="text-gray-500 dark:text-gray-400 dark:text-gray-500 mt-2">Novedades y reposición de stock</p>
                    </div>
                    <a href="{{ route('shop') }}" class="hidden sm:inline-flex items-center gap-2 font-bold text-[var(--color-primary)] hover:text-[var(--color-primary-hover)] transition-colors">
                        Ver todo el catálogo
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                    </a>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 xl:grid-cols-5 gap-3 sm:gap-4">
                    @forelse ($latestProducts as $product)
                        <article class="group relative flex flex-col bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 rounded-lg overflow-hidden shadow-sm hover:shadow hover:-translate-y-0.5 transition-all duration-300">
                            
                            {{-- Contenedor de Imagen (Más compacto) --}}
                            <a href="{{ route('product.detail', $product->slug) }}" wire:navigate class="relative block aspect-square bg-white dark:bg-zinc-900 overflow-hidden p-3 border-b border-gray-100 dark:border-zinc-800 flex items-center justify-center">
                                @if($product->image_url)
                                    <img src="{{ asset('storage/' . $product->image_url) }}"
                                         alt="{{ $product->name }}"
                                         class="w-full h-full object-contain transition-transform duration-500 group-hover:scale-105 drop-shadow-sm"
                                         onerror="this.src='https://images.unsplash.com/photo-1591488320449-011701bb6704?q=80&w=400&auto=format&fit=crop'">
                                @else
                                    <svg class="h-10 w-10 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                @endif
                                @if($product->stock <= 0)
                                    <span class="absolute top-2 right-2 text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded bg-red-100 text-red-700">Sin Stock</span>
                                @endif
                            </a>

                            {{-- Contenido (Más compacto) --}}
                            <div class="flex flex-col flex-grow p-3 bg-white dark:bg-zinc-900">
                                <div class="flex-grow">
                                    @if($product->category)
                                        <span class="text-[9px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-1 block truncate">{{ $product->category->name }}</span>
                                    @endif
                                    <a href="{{ route('product.detail', $product->slug) }}" wire:navigate>
                                        <h3 class="text-xs sm:text-sm font-bold text-gray-800 dark:text-gray-200 leading-tight hover:text-[var(--color-primary)] transition-colors line-clamp-2" title="{{ $product->name }}">
                                            {{ $product->name }}
                                        </h3>
                                    </a>
                                </div>
                                <div class="mt-2 pt-2 border-t border-gray-100 dark:border-zinc-800 flex flex-col gap-2">
                                    <div class="flex items-end justify-between">
                                        <div>
                                            <p class="text-lg font-black text-[var(--color-primary)] leading-none">${{ number_format($product->retail_price, 2) }}</p>
                                        </div>
                                    </div>
                                    <div class="w-full">
                                        <livewire:add-to-cart :product="$product" :compact="true" wire:key="add-cart-home-{{ $product->id }}" />
                                    </div>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="col-span-full py-12 text-center text-gray-500 dark:text-gray-400 dark:text-gray-500">
                            No hay productos disponibles por el momento.
                        </div>
                    @endforelse
                </div>
                
                <div class="mt-10 text-center sm:hidden">
                    <a href="{{ route('shop') }}" class="inline-flex items-center justify-center w-full py-3 px-4 bg-gray-100 dark:bg-zinc-800 text-gray-900 dark:text-white font-bold rounded-xl hover:bg-gray-200 transition-colors">
                        Ver todo el catálogo
                    </a>
                </div>

            </div>
        </section>

    </div>
</x-app-layout>
