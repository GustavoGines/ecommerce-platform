@php
    $settings = \App\Models\StoreSetting::getSettings();
    $storeName = $settings ? $settings->store_name : 'Premium Hardware';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $logoUrl = asset('storage/logos/logo-cjg-horizontal.png');
        $description = 'El mayor catálogo de controles remotos y electrónica. Ventas por mayor y menor.';
    @endphp
    
    <meta name="description" content="{{ $description }}">
    <title>{{ $storeName }} — Alta Gama</title>

    <!-- Open Graph / WhatsApp Preview -->
    <meta property="og:title" content="{{ $storeName }} — Alta Gama">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:image" content="{{ $logoUrl }}">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:type" content="website">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $storeName }} — Alta Gama">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $logoUrl }}">
    
    <!-- Fonts: Inter for that modern, technical, yet premium look -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800,900&display=swap" rel="stylesheet"/>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --color-primary: #2563EB;
            --color-primary-glow: color-mix(in srgb, var(--color-primary) 40%, transparent);
            --bg-obsidian: #030712; /* Deepest blue/black */
            --bg-slate: #0F172A;
        }
        
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: var(--bg-obsidian);
            color: #E5E7EB;
        }
        
        [x-cloak] { display: none !important; }
        
        /* Smooth scrolling */
        html { scroll-behavior: smooth; }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-obsidian); }
        ::-webkit-scrollbar-thumb { background: #1f2937; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #374151; }

        /* Typography sizing for Hero */
        .hero-title {
            font-size: clamp(2.5rem, 10vw, 5.5rem);
            line-height: 1.05;
            letter-spacing: -0.02em;
        }

        /* Float animation for the main product image */
        @keyframes float-slow {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }
        .animate-float-slow {
            animation: float-slow 6s cubic-bezier(0.4, 0, 0.2, 1) infinite;
        }

        /* Glass button hover */
        .btn-glass {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            transition: all 0.4s ease;
        }
        .btn-glass:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        /* Solid button hover */
        .btn-solid {
            background: var(--color-primary);
            color: #fff;
            transition: all 0.4s ease;
            box-shadow: 0 0 20px -5px var(--color-primary-glow);
        }
        .btn-solid:hover {
            box-shadow: 0 10px 40px -10px var(--color-primary);
            transform: translateY(-2px);
        }
    </style>
</head>

<body class="antialiased min-h-screen flex flex-col overflow-x-hidden selection:bg-[var(--color-primary)] selection:text-white">

    {{-- Navbar con propiedad transparente activada --}}
    <livewire:layout.navigation :transparent="true" />

    {{-- Main Wrapper --}}
    <main class="flex-grow flex flex-col relative z-10">

        {{-- ════════════════════════════════════════════════════════
             HERO SECTION (Turnstime 50/50 Layout)
        ════════════════════════════════════════════════════════ --}}
        <section class="relative w-full min-h-[90vh] flex items-center pt-20 pb-32">
            
            {{-- Ambient light behind text --}}
            <div class="absolute top-0 left-0 w-full h-full overflow-hidden pointer-events-none -z-10">
                <div class="absolute -top-[20%] -left-[10%] w-[50%] h-[70%] rounded-full mix-blend-screen filter blur-[120px] opacity-20"
                     style="background: radial-gradient(circle, var(--color-primary), transparent 70%);"></div>
            </div>

            <div class="max-w-7xl mx-auto px-6 lg:px-8 w-full">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                    
                    {{-- Left Column: Typography & CTAs --}}
                    <div class="flex flex-col justify-center order-2 lg:order-1 relative z-20">
                        {{-- Badge --}}
                        <div class="mb-8 inline-flex items-center gap-2.5 px-4 py-1.5 rounded-full border border-white/10 bg-white/5 backdrop-blur-md w-fit">
                            <span class="w-2 h-2 rounded-full animate-pulse" style="background-color: var(--color-primary); box-shadow: 0 0 10px var(--color-primary)"></span>
                            <span class="text-xs font-bold uppercase tracking-widest text-gray-300">New Collection 2026</span>
                        </div>

                        {{-- Giant Title --}}
                        <h1 class="hero-title font-black text-white mb-6">
                            Next-Gen <br>
                            <span class="text-transparent bg-clip-text bg-gradient-to-r from-white via-gray-300 to-gray-500">
                                Performance
                            </span>
                        </h1>

                        {{-- Relaxed Description --}}
                        <p class="text-lg text-gray-400 leading-relaxed font-light mb-12 max-w-[500px]">
                            Eleva tu experiencia de juego y creación a niveles sin precedentes. Descubre la selección más exclusiva de hardware premium.
                        </p>

                        {{-- Action Buttons --}}
                        <div class="flex flex-wrap gap-4 items-center">
                            <a href="{{ route('shop') }}" class="btn-solid px-8 py-4 font-bold text-sm tracking-widest uppercase rounded">
                                Explorar Catálogo
                            </a>
                            <a href="#" class="btn-glass px-8 py-4 font-bold text-gray-200 text-sm tracking-widest uppercase rounded flex items-center gap-2 group">
                                <svg class="w-5 h-5 text-gray-400 group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Ver Video
                            </a>
                        </div>
                    </div>

                    {{-- Right Column: Protagonist Image --}}
                    <div class="flex justify-center lg:justify-end items-center relative order-1 lg:order-2">
                        {{-- Subtle glow behind the product --}}
                        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[80%] h-[80%] rounded-full filter blur-[100px] opacity-30 pointer-events-none"
                             style="background-color: var(--color-primary)"></div>
                        
                        <img src="{{ asset('storage/banners/hero_hardware.png') }}" 
                             alt="Premium GPU" 
                             class="relative z-10 w-full max-w-[550px] object-contain animate-float-slow drop-shadow-2xl"
                             onerror="this.src='https://images.unsplash.com/photo-1591488320449-011701bb6704?q=80&w=1000&auto=format&fit=crop'; this.classList.add('rounded-2xl', 'mix-blend-lighten')">
                        
                        {{-- Decorative specs floating box (Turnstime style detail) --}}
                        <div class="absolute bottom-10 -left-10 hidden md:flex items-center gap-4 p-4 rounded-xl glass animate-float-slow" style="animation-delay: 1s;">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center bg-white/10">
                                <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 font-bold uppercase tracking-wider">Arquitectura</p>
                                <p class="text-white font-black">Ultra-Core 4.0</p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            
            {{-- Scroll Indicator / Call to action --}}
            <a href="{{ route('shop') }}" class="absolute bottom-8 left-1/2 -translate-x-1/2 flex flex-col items-center gap-2 opacity-40 hover:opacity-100 transition-opacity animate-bounce cursor-pointer">
                <span class="text-[10px] font-bold uppercase tracking-widest text-white">Descubrir</span>
                <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                </svg>
            </a>
        </section>

        {{-- ════════════════════════════════════════════════════════
             TOP CATEGORIES (Etonal Style with Luxury Skin - Phase 2)
        ════════════════════════════════════════════════════════ --}}
        <section class="py-16 bg-[var(--bg-obsidian)] border-y border-white/5 relative z-10" x-data="{ shown: false }" x-init="setTimeout(() => shown = true, 200)">
            <div class="max-w-7xl mx-auto px-6 lg:px-8">
                <div class="flex items-center justify-between mb-10 opacity-0 translate-y-4 transition-all duration-700" :class="shown ? 'opacity-100 translate-y-0' : ''">
                    <h2 class="text-2xl font-black text-white tracking-widest uppercase">
                        Explorar Categorías
                    </h2>
                    <a href="{{ route('shop') }}" class="text-sm font-bold text-gray-400 hover:text-white transition-colors flex items-center gap-1 group">
                        Ver todo 
                        <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-6">
                    
                    {{-- Category 1 --}}
                    <a href="{{ route('shop') }}?categoria=Gráfica" class="group relative rounded-2xl overflow-hidden bg-[#0a0f1c] border border-white/5 hover:border-white/20 hover:shadow-[0_0_30px_rgba(37,99,235,0.15)] transition-all duration-500 aspect-[4/5] flex flex-col justify-end p-6 opacity-0 translate-y-8" :class="shown ? 'opacity-100 translate-y-0' : ''" style="transition-delay: 100ms;">
                        <img src="https://images.unsplash.com/photo-1591488320449-011701bb6704?q=80&w=600&auto=format&fit=crop" alt="Tarjetas Gráficas" class="absolute inset-0 w-full h-full object-cover opacity-40 group-hover:opacity-70 group-hover:scale-110 transition-all duration-700 mix-blend-lighten">
                        <div class="absolute inset-0 bg-gradient-to-t from-[#030712] via-[#030712]/60 to-transparent"></div>
                        <div class="relative z-10 transform group-hover:-translate-y-2 transition-transform duration-500">
                            <h3 class="text-white font-bold text-lg leading-tight">Gráficas</h3>
                            <p class="text-[10px] text-[var(--color-primary)] font-bold uppercase tracking-widest mt-1">GPU</p>
                        </div>
                    </a>

                    {{-- Category 2 --}}
                    <a href="{{ route('shop') }}?categoria=Procesador" class="group relative rounded-2xl overflow-hidden bg-[#0a0f1c] border border-white/5 hover:border-white/20 hover:shadow-[0_0_30px_rgba(37,99,235,0.15)] transition-all duration-500 aspect-[4/5] flex flex-col justify-end p-6 opacity-0 translate-y-8" :class="shown ? 'opacity-100 translate-y-0' : ''" style="transition-delay: 200ms;">
                        <img src="https://images.unsplash.com/photo-1591799264318-7e6ef8ddb7ea?q=80&w=600&auto=format&fit=crop" alt="Procesadores" class="absolute inset-0 w-full h-full object-cover opacity-40 group-hover:opacity-70 group-hover:scale-110 transition-all duration-700 mix-blend-lighten">
                        <div class="absolute inset-0 bg-gradient-to-t from-[#030712] via-[#030712]/60 to-transparent"></div>
                        <div class="relative z-10 transform group-hover:-translate-y-2 transition-transform duration-500">
                            <h3 class="text-white font-bold text-lg leading-tight">Procesadores</h3>
                            <p class="text-[10px] text-[var(--color-primary)] font-bold uppercase tracking-widest mt-1">CPU</p>
                        </div>
                    </a>

                    {{-- Category 3 --}}
                    <a href="{{ route('shop') }}?categoria=Motherboard" class="group relative rounded-2xl overflow-hidden bg-[#0a0f1c] border border-white/5 hover:border-white/20 hover:shadow-[0_0_30px_rgba(37,99,235,0.15)] transition-all duration-500 aspect-[4/5] flex flex-col justify-end p-6 opacity-0 translate-y-8" :class="shown ? 'opacity-100 translate-y-0' : ''" style="transition-delay: 300ms;">
                        <img src="https://images.unsplash.com/photo-1518770660439-4636190af475?q=80&w=600&auto=format&fit=crop" alt="Motherboards" class="absolute inset-0 w-full h-full object-cover opacity-30 group-hover:opacity-70 group-hover:scale-110 transition-all duration-700 mix-blend-lighten">
                        <div class="absolute inset-0 bg-gradient-to-t from-[#030712] via-[#030712]/60 to-transparent"></div>
                        <div class="relative z-10 transform group-hover:-translate-y-2 transition-transform duration-500">
                            <h3 class="text-white font-bold text-lg leading-tight">Placas Base</h3>
                            <p class="text-[10px] text-[var(--color-primary)] font-bold uppercase tracking-widest mt-1">Motherboard</p>
                        </div>
                    </a>

                    {{-- Category 4 --}}
                    <a href="{{ route('shop') }}?categoria=RAM" class="group relative rounded-2xl overflow-hidden bg-[#0a0f1c] border border-white/5 hover:border-white/20 hover:shadow-[0_0_30px_rgba(37,99,235,0.15)] transition-all duration-500 aspect-[4/5] flex flex-col justify-end p-6 opacity-0 translate-y-8" :class="shown ? 'opacity-100 translate-y-0' : ''" style="transition-delay: 400ms;">
                        <img src="https://images.unsplash.com/photo-1563770660941-20978e870e26?q=80&w=600&auto=format&fit=crop" alt="Memorias RAM" class="absolute inset-0 w-full h-full object-cover opacity-40 group-hover:opacity-70 group-hover:scale-110 transition-all duration-700 mix-blend-lighten">
                        <div class="absolute inset-0 bg-gradient-to-t from-[#030712] via-[#030712]/60 to-transparent"></div>
                        <div class="relative z-10 transform group-hover:-translate-y-2 transition-transform duration-500">
                            <h3 class="text-white font-bold text-lg leading-tight">Memorias</h3>
                            <p class="text-[10px] text-[var(--color-primary)] font-bold uppercase tracking-widest mt-1">RAM</p>
                        </div>
                    </a>

                    {{-- Category 5 --}}
                    <a href="{{ route('shop') }}?categoria=Almacenamiento" class="group relative rounded-2xl overflow-hidden bg-[#0a0f1c] border border-white/5 hover:border-white/20 hover:shadow-[0_0_30px_rgba(37,99,235,0.15)] transition-all duration-500 aspect-[4/5] flex flex-col justify-end p-6 opacity-0 translate-y-8" :class="shown ? 'opacity-100 translate-y-0' : ''" style="transition-delay: 500ms;">
                        <img src="https://images.unsplash.com/photo-1628557044797-f21a177c37ec?q=80&w=600&auto=format&fit=crop" alt="Almacenamiento" class="absolute inset-0 w-full h-full object-cover opacity-40 group-hover:opacity-70 group-hover:scale-110 transition-all duration-700 mix-blend-lighten">
                        <div class="absolute inset-0 bg-gradient-to-t from-[#030712] via-[#030712]/60 to-transparent"></div>
                        <div class="relative z-10 transform group-hover:-translate-y-2 transition-transform duration-500">
                            <h3 class="text-white font-bold text-lg leading-tight">Almacenamiento</h3>
                            <p class="text-[10px] text-[var(--color-primary)] font-bold uppercase tracking-widest mt-1">SSD / HDD</p>
                        </div>
                    </a>

                </div>
            </div>
        </section>

        {{-- ════════════════════════════════════════════════════════
             PROMO BANNER (Apple Style - Phase 2)
        ════════════════════════════════════════════════════════ --}}
        <section class="py-24 bg-[#030712] relative z-10" x-data="{ intersecting: false }" x-intersect.once="intersecting = true">
            <div class="max-w-7xl mx-auto px-6 lg:px-8">
                <div class="relative w-full rounded-[2rem] overflow-hidden bg-[#0a0f1c] border border-white/5 flex flex-col md:flex-row items-center transition-all duration-1000 transform"
                     :class="intersecting ? 'translate-y-0 opacity-100' : 'translate-y-12 opacity-0'">
                    
                    {{-- Ambient backdrop --}}
                    <div class="absolute inset-0 bg-gradient-to-r from-transparent to-[var(--color-primary)]/10"></div>
                    
                    <div class="p-8 md:p-20 flex-1 relative z-10">
                        <h2 class="text-3xl md:text-5xl font-black text-white tracking-tight mb-4">
                            Poder sin<br>compromisos.
                        </h2>
                        <p class="text-gray-400 text-lg mb-8 max-w-sm">
                            Descubre la nueva línea de procesadores con arquitectura cuántica. Diseñados para los más exigentes.
                        </p>
                        <a href="{{ route('shop') }}" class="inline-flex items-center gap-2 text-white font-bold tracking-widest uppercase hover:text-[var(--color-primary)] transition-colors group">
                            Descubrir más 
                            <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                            </svg>
                        </a>
                    </div>
                    
                    <div class="w-full md:w-1/2 p-10 flex justify-center relative z-10">
                        <img src="{{ asset('storage/banners/cpu_banner.png') }}" alt="Promo CPU" class="w-full max-w-sm object-contain drop-shadow-2xl hover:scale-105 transition-transform duration-700" onerror="this.src='https://images.unsplash.com/photo-1591799264318-7e6ef8ddb7ea?q=80&w=800&auto=format&fit=crop'; this.classList.add('rounded-2xl', 'mix-blend-lighten')">
                    </div>
                </div>
            </div>
        </section>

        {{-- ════════════════════════════════════════════════════════
             FEATURED PRODUCTS (Glassmorphism + Hybrid Button - Phase 3)
        ════════════════════════════════════════════════════════ --}}
        <section class="py-24 bg-[var(--bg-obsidian)] relative z-10">
            <div class="max-w-7xl mx-auto px-6 lg:px-8">
                <div class="flex flex-col md:flex-row md:items-end justify-between mb-12 gap-4">
                    <div>
                        <h2 class="text-3xl font-black text-white tracking-widest uppercase mb-2">
                            Selección Premium
                        </h2>
                        <p class="text-gray-400 text-sm">El hardware más exclusivo y demandado del mercado.</p>
                    </div>
                    <a href="{{ route('shop') }}" class="btn-glass px-6 py-2.5 font-bold text-xs tracking-widest uppercase rounded">
                        Ver Catálogo Completo
                    </a>
                </div>

                @php
                    // Fetch latest 4 products for the showcase
                    $featuredProducts = \App\Models\Product::latest()->take(4)->get();
                @endphp

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    @forelse($featuredProducts as $product)
                        <div class="group relative rounded-2xl bg-[#0a0f1c]/80 backdrop-blur-md border border-white/5 hover:border-white/20 hover:shadow-[0_0_30px_rgba(37,99,235,0.1)] transition-all duration-500 overflow-hidden flex flex-col h-full">
                            
                            {{-- Image Container --}}
                            <a href="{{ route('product.detail', $product->slug ?? $product->id) }}" wire:navigate class="relative aspect-square overflow-hidden flex items-center justify-center p-8 bg-gradient-to-b from-white/5 to-transparent">
                                @if($product->image_url)
                                    <img src="{{ asset('storage/' . $product->image_url) }}" alt="{{ $product->name }}" class="w-full h-full object-contain drop-shadow-xl group-hover:scale-110 group-hover:-translate-y-2 transition-transform duration-700">
                                @else
                                    <div class="w-full h-full bg-gray-800/50 rounded-xl flex items-center justify-center">
                                        <span class="text-gray-500">Sin imagen</span>
                                    </div>
                                @endif
                                
                                {{-- Quick View Badge (Turnstime style) --}}
                                <div class="absolute top-4 left-4">
                                    <span class="px-2 py-1 text-[9px] font-bold uppercase tracking-widest text-white bg-white/10 rounded backdrop-blur-md border border-white/10">Nuevo</span>
                                </div>
                            </a>

                            {{-- Product Info --}}
                            <div class="p-6 flex flex-col flex-grow relative">
                                <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mb-1">{{ $product->category->name ?? 'Hardware' }}</p>
                                <a href="{{ route('product.detail', $product->slug ?? $product->id) }}" wire:navigate class="text-white font-bold text-lg leading-tight mb-2 hover:text-[var(--color-primary)] transition-colors line-clamp-2">
                                    {{ $product->name }}
                                </a>
                                
                                <div class="mt-auto pt-4 flex items-center justify-between">
                                    <div class="flex flex-col">
                                        <span class="text-[10px] text-gray-400 uppercase tracking-widest font-semibold mb-0.5">Precio</span>
                                        <span class="text-white font-black text-lg">${{ number_format($product->retail_price ?? 0, 0, ',', '.') }}</span>
                                    </div>
                                    
                                    {{-- Add to Cart Button (Hybrid Desktop/Mobile) --}}
                                    {{-- En mobile se ve siempre, en desktop está oculto hasta hover --}}
                                    <button onclick="window.dispatchEvent(new CustomEvent('cart-add', { detail: { id: {{ $product->id }} } })); POS.openCart();" 
                                            class="w-10 h-10 rounded-full bg-[var(--color-primary)]/20 border border-[var(--color-primary)]/50 text-[var(--color-primary)] flex items-center justify-center transition-all duration-300
                                                   lg:opacity-0 lg:-translate-y-2 lg:group-hover:opacity-100 lg:group-hover:translate-y-0
                                                   hover:bg-[var(--color-primary)] hover:text-white hover:border-transparent hover:shadow-[0_0_15px_var(--color-primary)]"
                                            aria-label="Agregar al carrito">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full py-12 text-center text-gray-500">
                            No hay productos disponibles por el momento.
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        {{-- ════════════════════════════════════════════════════════
             TRUST BADGES (Etonal Trust with Luxury Aesthetics - Phase 4)
        ════════════════════════════════════════════════════════ --}}
        <section class="py-16 bg-[#0a0f1c] border-t border-white/5 relative z-10">
            <div class="max-w-7xl mx-auto px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 lg:gap-12 divide-y md:divide-y-0 md:divide-x divide-white/5">
                    
                    {{-- Badge 2 --}}
                    <div class="flex items-start gap-5 pt-8 md:pt-0 md:px-8">
                        <div class="w-12 h-12 shrink-0 rounded-full bg-white/5 border border-white/10 flex items-center justify-center text-[var(--color-primary)]">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-white font-bold text-sm tracking-widest uppercase mb-1">Pagos Seguros</h4>
                            <p class="text-gray-500 text-sm leading-relaxed">Múltiples pasarelas encriptadas y cuotas sin interés.</p>
                        </div>
                    </div>

                    {{-- Badge 3 --}}
                    <div class="flex items-start gap-5 pt-8 md:pt-0 md:pl-8">
                        <div class="w-12 h-12 shrink-0 rounded-full bg-white/5 border border-white/10 flex items-center justify-center text-[var(--color-primary)]">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-white font-bold text-sm tracking-widest uppercase mb-1">Envío Asegurado</h4>
                            <p class="text-gray-500 text-sm leading-relaxed">Embalaje reforzado y tracking en tiempo real a todo el país.</p>
                        </div>
                    </div>

                </div>
            </div>
        </section>
        
    </main>

    {{-- Cart Panel Slide-over --}}
    <livewire:cart-panel />

    {{-- Global Footer --}}
    <livewire:layout.footer />

    <!-- Botón Flotante de WhatsApp Global -->
    <a href="https://wa.me/5493704022685" target="_blank" 
       class="fixed bottom-6 right-6 z-[100] w-14 h-14 bg-green-500 text-white rounded-full flex items-center justify-center shadow-lg hover:bg-green-600 hover:scale-110 transition-all duration-300"
       aria-label="Contactar por WhatsApp">
        <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 00-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
        </svg>
        <span class="absolute -top-1 -right-1 flex h-4 w-4">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-4 w-4 bg-red-500 border-2 border-white"></span>
        </span>
    </a>

</body>
</html>
