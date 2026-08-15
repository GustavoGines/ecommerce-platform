<x-app-layout>
    @php
        $settings = \App\Models\StoreSetting::getSettings();
        $storeName = $settings ? $settings->store_name : 'G3 Tecnología';
        
        // Obtenemos los últimos 8 productos para la página de inicio
        $latestProducts = \App\Models\Product::with('category')->latest()->take(8)->get();
        // Categorías principales para acceso rápido
        $categories = \App\Models\Category::take(4)->get();
    @endphp

    <div class="bg-white min-h-screen">
        
        {{-- ════════════════════════════════════════════════════════
             G3 TECH HERO SECTION
        ════════════════════════════════════════════════════════ --}}
        <div class="relative bg-g3-dark overflow-hidden min-h-[600px] flex items-center border-b border-zinc-900">
            <!-- Efectos de iluminación de fondo -->
            <div class="absolute top-1/4 left-1/4 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] opacity-20 pointer-events-none">
                <div class="absolute inset-0 bg-g3-blue blur-[120px] rounded-full mix-blend-screen"></div>
            </div>
            <div class="absolute bottom-0 right-1/4 translate-x-1/4 translate-y-1/4 w-[500px] h-[500px] opacity-20 pointer-events-none">
                <div class="absolute inset-0 bg-g3-green blur-[120px] rounded-full mix-blend-screen"></div>
            </div>
            
            <!-- Patrón de puntos (Grid tech) -->
            <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGNpcmNsZSBjeD0iMSIgY3k9IjEiIHI9IjEiIGZpbGw9InJnYmEoMjU1LDI1NSwyNTUsMC4wNSkiLz48L3N2Zz4=')] [mask-image:linear-gradient(to_bottom,white,transparent)]"></div>

            <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full z-10 py-16">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                    
                    <!-- Contenido Izquierdo -->
                    <div class="text-left">
                        <!-- Badge de Novedad -->
                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-zinc-900/80 border border-zinc-800 mb-6 backdrop-blur-sm">
                            <span class="flex h-2 w-2 relative">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-g3-green opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-g3-green"></span>
                            </span>
                            <span class="text-xs font-bold text-zinc-300 uppercase tracking-widest">Nuevos Ingresos 2026</span>
                        </div>
                        
                        <h1 class="text-5xl md:text-7xl font-extrabold text-white tracking-tight leading-[1.1] mb-6">
                            Lo último en <br>
                            <span class="text-transparent bg-clip-text bg-gradient-to-r from-g3-blue to-g3-green">Tecnología</span> para vos.
                        </h1>
                        
                        <p class="text-lg text-g3-silver mb-8 max-w-xl leading-relaxed font-medium">
                            El catálogo más amplio en smartphones, electrónica, hogar y mucho más. Innovación y los mejores precios con G3 Tecnología.
                        </p>
                        
                        <!-- Botones CTA -->
                        <div class="flex flex-col sm:flex-row gap-4">
                            <a href="{{ route('shop') }}" class="group relative inline-flex items-center justify-center px-8 py-4 text-sm font-bold text-zinc-950 transition-all duration-200 bg-gradient-to-r from-g3-blue to-g3-green rounded-xl hover:shadow-[0_0_20px_rgba(59,130,246,0.4)] focus:outline-none overflow-hidden">
                                <!-- Brillo sobre el botón -->
                                <div class="absolute inset-0 flex h-full w-full justify-center [transform:skew(-12deg)_translateX(-150%)] group-hover:duration-1000 group-hover:[transform:skew(-12deg)_translateX(150%)]">
                                    <div class="relative h-full w-8 bg-white/20"></div>
                                </div>
                                <span class="relative z-10 flex items-center gap-2">
                                    Ver Catálogo
                                    <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                    </svg>
                                </span>
                            </a>
                        </div>
                    </div>

                    <!-- Gráfico / Imagen Derecha -->
                    <div class="relative hidden lg:block">
                        <div class="relative w-full aspect-square rounded-2xl border border-zinc-800/50 bg-gradient-to-b from-zinc-900/50 to-zinc-950/50 backdrop-blur-sm flex items-center justify-center shadow-2xl overflow-hidden group">
                             <div class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1616348436168-de43ad0db179?q=80&w=1000&auto=format&fit=crop')] bg-cover bg-center opacity-30 group-hover:opacity-50 transition-opacity duration-700 mix-blend-luminosity"></div>
                             <div class="absolute inset-0 bg-gradient-to-t from-g3-dark via-transparent to-transparent"></div>
                             
                             <div class="relative z-10 text-center p-8 bg-zinc-950/40 backdrop-blur-md border border-zinc-800 rounded-xl transform transition-transform group-hover:scale-105 duration-500">
                                <span class="block text-5xl font-black text-transparent bg-clip-text bg-gradient-to-br from-white to-zinc-500 mb-1">iPhone 15 Pro</span>
                                <span class="block text-xs font-bold text-g3-green uppercase tracking-[0.3em]">Stock Disponible</span>
                             </div>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>

        {{-- ════════════════════════════════════════════════════════
             CATEGORÍAS DESTACADAS
        ════════════════════════════════════════════════════════ --}}
        <section class="py-16 bg-g3-dark border-b border-zinc-900" x-data="{ intersecting: false }" x-intersect.once="intersecting = true">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 transition-all duration-1000 delay-100 transform" :class="intersecting ? 'translate-y-0 opacity-100' : 'translate-y-12 opacity-0'">
                    
                    <a href="{{ route('shop', ['categoria' => 'Xiaomi']) }}" class="group relative h-32 md:h-40 rounded-2xl overflow-hidden shadow-sm flex items-center justify-center bg-zinc-900 hover:shadow-[0_8px_30px_rgba(59,130,246,0.3)] transition-all duration-300">
                        <div class="absolute inset-0 bg-gradient-to-t from-zinc-950 to-transparent opacity-80 z-10"></div>
                        <img src="https://images.unsplash.com/photo-1598327105666-5b89351aff97?q=80&w=400&auto=format&fit=crop" alt="Xiaomi" class="absolute inset-0 w-full h-full object-cover opacity-40 group-hover:scale-110 group-hover:opacity-60 transition-all duration-500">
                        <span class="relative z-20 text-white font-bold text-sm md:text-base text-center px-4 tracking-wide group-hover:-translate-y-1 transition-transform drop-shadow-md">XIAOMI</span>
                    </a>
                    
                    <a href="{{ route('shop', ['categoria' => 'Apple']) }}" class="group relative h-32 md:h-40 rounded-2xl overflow-hidden shadow-sm flex items-center justify-center bg-zinc-900 hover:shadow-[0_8px_30px_rgba(126,211,33,0.3)] transition-all duration-300">
                        <div class="absolute inset-0 bg-gradient-to-t from-zinc-950 to-transparent opacity-80 z-10"></div>
                        <img src="https://images.unsplash.com/photo-1616348436168-de43ad0db179?q=80&w=400&auto=format&fit=crop" alt="Apple" class="absolute inset-0 w-full h-full object-cover opacity-40 group-hover:scale-110 group-hover:opacity-60 transition-all duration-500">
                        <span class="relative z-20 text-white font-bold text-sm md:text-base text-center px-4 tracking-wide group-hover:-translate-y-1 transition-transform drop-shadow-md">APPLE</span>
                    </a>

                    <a href="{{ route('shop', ['categoria' => 'Samsung']) }}" class="group relative h-32 md:h-40 rounded-2xl overflow-hidden shadow-sm flex items-center justify-center bg-zinc-900 hover:shadow-[0_8px_30px_rgba(59,130,246,0.3)] transition-all duration-300">
                        <div class="absolute inset-0 bg-gradient-to-t from-zinc-950 to-transparent opacity-80 z-10"></div>
                        <img src="https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?q=80&w=400&auto=format&fit=crop" alt="Samsung" class="absolute inset-0 w-full h-full object-cover opacity-40 group-hover:scale-110 group-hover:opacity-60 transition-all duration-500">
                        <span class="relative z-20 text-white font-bold text-sm md:text-base text-center px-4 tracking-wide group-hover:-translate-y-1 transition-transform drop-shadow-md">SAMSUNG</span>
                    </a>

                    <a href="{{ route('shop', ['categoria' => 'Notebook']) }}" class="group relative h-32 md:h-40 rounded-2xl overflow-hidden shadow-sm flex items-center justify-center bg-zinc-900 hover:shadow-[0_8px_30px_rgba(126,211,33,0.3)] transition-all duration-300">
                        <div class="absolute inset-0 bg-gradient-to-t from-zinc-950 to-transparent opacity-80 z-10"></div>
                        <img src="https://images.unsplash.com/photo-1496181133206-80ce9b88a853?q=80&w=400&auto=format&fit=crop" alt="Notebooks" class="absolute inset-0 w-full h-full object-cover opacity-40 group-hover:scale-110 group-hover:opacity-60 transition-all duration-500">
                        <span class="relative z-20 text-white font-bold text-sm md:text-base text-center px-4 tracking-wide group-hover:-translate-y-1 transition-transform drop-shadow-md">NOTEBOOKS</span>
                    </a>
                    
                </div>
            </div>
        </section>

        {{-- ════════════════════════════════════════════════════════
             ÚLTIMOS INGRESOS (Reutilizando diseño de tarjeta)
        ════════════════════════════════════════════════════════ --}}
        <section class="py-20 bg-g3-dark">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <div class="flex items-end justify-between mb-10">
                    <div>
                        <h2 class="text-3xl font-black text-white tracking-tight">Últimos Ingresos</h2>
                        <p class="text-g3-silver mt-2">Novedades y reposición de stock</p>
                    </div>
                    <a href="{{ route('shop') }}" class="hidden sm:inline-flex items-center gap-2 font-bold text-g3-green hover:text-white transition-colors">
                        Ver todo el catálogo
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                    </a>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    @forelse ($latestProducts as $product)
                        <article class="group relative flex flex-col bg-g3-card border border-zinc-800 rounded-lg overflow-hidden shadow-sm hover:shadow-[0_0_15px_rgba(59,130,246,0.2)] hover:-translate-y-0.5 transition-all duration-300">
                            
                            {{-- Contenedor de Imagen (Más compacto) --}}
                            <a href="{{ route('product.detail', $product->slug) }}" wire:navigate class="relative block aspect-square bg-zinc-900 overflow-hidden p-3 border-b border-zinc-800 flex items-center justify-center">
                                @if($product->image_url)
                                    <img src="{{ asset('storage/' . $product->image_url) }}"
                                         alt="{{ $product->name }}"
                                         class="w-full h-full object-contain transition-transform duration-500 group-hover:scale-105 drop-shadow-sm mix-blend-screen"
                                         onerror="this.src='https://images.unsplash.com/photo-1591488320449-011701bb6704?q=80&w=400&auto=format&fit=crop'">
                                @else
                                    <svg class="h-10 w-10 text-zinc-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                @endif
                                @if($product->stock <= 0)
                                    <span class="absolute top-2 right-2 text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded bg-red-100 text-red-700">Sin Stock</span>
                                @endif
                            </a>

                            {{-- Contenido (Más compacto) --}}
                            <div class="flex flex-col flex-grow p-3 bg-g3-card">
                                <div class="flex-grow">
                                    @if($product->category)
                                        <span class="text-[9px] font-bold uppercase tracking-widest text-zinc-500 mb-1 block truncate">{{ $product->category->name }}</span>
                                    @endif
                                    <a href="{{ route('product.detail', $product->slug) }}" wire:navigate>
                                        <h3 class="text-xs sm:text-sm font-bold text-white leading-tight hover:text-g3-blue transition-colors line-clamp-2" title="{{ $product->name }}">
                                            {{ $product->name }}
                                        </h3>
                                    </a>
                                </div>
                                <div class="mt-2 pt-2 border-t border-zinc-800 flex flex-col gap-2">
                                    <div class="flex items-end justify-between">
                                        <div>
                                            <p class="text-lg font-black text-g3-green leading-none">${{ number_format($product->retail_price, 0, ',', '.') }}</p>
                                        </div>
                                    </div>
                                    <div class="w-full">
                                        <livewire:add-to-cart :product="$product" :compact="true" wire:key="add-cart-home-{{ $product->id }}" />
                                    </div>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="col-span-full py-12 text-center text-gray-500">
                            No hay productos disponibles por el momento.
                        </div>
                    @endforelse
                </div>
                
                <div class="mt-10 text-center sm:hidden">
                    <a href="{{ route('shop') }}" class="inline-flex items-center justify-center w-full py-3 px-4 bg-gray-100 text-gray-900 font-bold rounded-xl hover:bg-gray-200 transition-colors">
                        Ver todo el catálogo
                    </a>
                </div>

            </div>
        </section>

    </div>
</x-app-layout>
