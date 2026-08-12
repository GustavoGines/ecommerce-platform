@php
    $settings = \App\Models\StoreSetting::getSettings();
    $storeName = $settings ? $settings->store_name : 'G3 Tecnología';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $logoUrl = asset('storage/logos/logo-cjg-horizontal.png');
        $description = 'El mayor catálogo de controles remotos y electrónica. Ventas por mayor y menor.';
    @endphp
    <meta name="description" content="{{ $description }}">
    <title>{{ $storeName }}</title>

    <!-- Open Graph / WhatsApp Preview -->
    <meta property="og:title" content="{{ $storeName }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:image" content="{{ $logoUrl }}">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:type" content="website">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $storeName }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $logoUrl }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800,900&display=swap" rel="stylesheet"/>

    {{-- Anti-flash: aplicar tema ANTES de que Alpine arranque --}}
    <script>
        (function () {
            var dark = localStorage.theme === 'dark' ||
                (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --color-primary: #3b82f6;
            --color-primary-glow: color-mix(in srgb, var(--color-primary) 30%, transparent);
            --color-primary-subtle: color-mix(in srgb, var(--color-primary) 10%, transparent);
        }
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }

        /* ── Dot-grid background light mode ── */
        .dot-grid {
            background-image: radial-gradient(circle, #cbd5e1 1px, transparent 1px);
            background-size: 28px 28px;
        }
        .dark .dot-grid {
            background-image: radial-gradient(circle, rgba(255,255,255,0.06) 1px, transparent 1px);
            background-size: 28px 28px;
        }

        /* ── Card hover glow ── */
        .card-hover { transition: all 0.3s ease; }
        .card-hover:hover { transform: translateY(-4px); }
        .dark .card-hover:hover {
            box-shadow: 0 20px 40px -10px var(--color-primary-glow);
            border-color: color-mix(in srgb, var(--color-primary) 40%, #374151);
        }
        .card-hover:hover {
            box-shadow: 0 20px 50px -12px rgba(0,0,0,0.15);
            border-color: color-mix(in srgb, var(--color-primary) 40%, #e2e8f0);
        }

        /* ── Hero gradient light ── */
        .hero-light {
            background: linear-gradient(135deg,
                #0f172a 0%,
                #1e1b4b 35%,
                #312e81 60%,
                #1e40af 100%
            );
        }
        .dark .hero-light {
            background: linear-gradient(135deg,
                #030712 0%,
                #0f0a1e 40%,
                #0d0d1e 100%
            );
        }

        /* ── Shimmer tag ── */
        @keyframes shimmer {
            0%   { background-position: -200% center; }
            100% { background-position: 200% center; }
        }
        .shimmer-text {
            background: linear-gradient(90deg,
                var(--color-primary) 0%,
                #a5b4fc 45%,
                var(--color-primary) 100%
            );
            background-size: 200% auto;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: shimmer 4s linear infinite;
        }

        /* ── Smooth filter pill ── */
        .filter-pill {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .filter-pill:hover { transform: scale(1.05); }
        .filter-pill.active { transform: scale(1.05); }
    </style>
</head>

<body class="antialiased min-h-screen flex flex-col overflow-x-hidden
    bg-slate-50 text-slate-900
    dark:bg-[#080c14] dark:text-gray-100
    transition-colors duration-300">

    {{-- ── Fondo puntillado que da profundidad en ambos modos ── --}}
    <div class="fixed inset-0 dot-grid opacity-60 dark:opacity-100 pointer-events-none z-0"></div>

    {{-- ── Glow ambient en dark mode ── --}}
    <div x-data="{}" x-show="$store.theme.dark"
         class="fixed top-0 left-1/2 -translate-x-1/2 w-[900px] h-[600px] opacity-20 pointer-events-none z-0"
         style="background: radial-gradient(ellipse at top, var(--color-primary-glow) 0%, transparent 65%);">
    </div>

    {{-- ── Navbar ── --}}
    <livewire:layout.navigation />

    <div class="relative z-10 flex flex-col flex-grow">

        {{-- ════════════════════════════════════════════════════════
             HERO BANNER — Profesional, gradiente adaptable
        ════════════════════════════════════════════════════════ --}}
        <section class="hero-light relative w-full min-h-[520px] sm:min-h-[580px] flex items-center overflow-hidden">

            {{-- Imagen de fondo (hardware gamer) como textura sutil --}}
            <div class="absolute inset-0">
                <img src="{{ asset('storage/banners/hero_banner.png') }}"
                     class="w-full h-full object-cover opacity-10 mix-blend-luminosity">
                {{-- Gradiente que oscurece el lado derecho --}}
                <div class="absolute inset-0 bg-gradient-to-r from-black/60 via-black/20 to-transparent"></div>
            </div>

            {{-- Formas decorativas flotantes --}}
            <div class="absolute top-12 right-12 w-72 h-72 rounded-full opacity-10 blur-3xl pointer-events-none"
                 style="background: radial-gradient(circle, var(--color-primary), transparent 70%);"></div>
            <div class="absolute -bottom-8 right-1/3 w-48 h-48 rounded-full opacity-10 blur-2xl pointer-events-none"
                 style="background: radial-gradient(circle, #a855f7, transparent 70%);"></div>

            {{-- Contenido --}}
            <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full py-20">
                <div class="max-w-2xl">
                    <span class="inline-flex items-center gap-2 py-1.5 px-4 rounded-full text-xs font-bold uppercase tracking-widest mb-6
                                 bg-white/10 text-white border border-white/20 backdrop-blur-sm">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        Nueva Generación · 2026
                    </span>

                    <h1 class="text-5xl sm:text-7xl font-black text-white tracking-tight leading-[1.05] mb-6 drop-shadow-2xl">
                        Potencia<br>
                        <span class="shimmer-text">absoluta.</span>
                    </h1>

                    <p class="text-lg sm:text-xl text-white/75 mb-10 max-w-xl font-light leading-relaxed">
                        Componentes de última generación. Compatibilidad verificada.
                        Armá el setup de tus sueños sin complicaciones.
                    </p>

                    <div class="flex flex-wrap gap-4">
                        <a href="{{ route('shop') }}"
                                class="group px-8 py-4 rounded-2xl text-white font-bold text-sm tracking-wide
                                       shadow-2xl transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_20px_40px_-8px_var(--color-primary-glow)]"
                                style="background: linear-gradient(135deg, var(--color-primary), color-mix(in srgb, var(--color-primary) 70%, #7c3aed));">
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                                Explorar Catálogo
                            </span>
                        </a>
                        <button class="px-8 py-4 rounded-2xl text-white font-bold text-sm tracking-wide
                                       bg-white/10 hover:bg-white/20 border border-white/25 backdrop-blur-sm
                                       transition-all duration-300 hover:-translate-y-1">
                            Ofertas del Mes
                        </button>
                    </div>

                    {{-- Stats --}}
                    <div class="flex flex-wrap gap-8 mt-14 pt-8 border-t border-white/15">
                        <div>
                            <p class="text-2xl font-black text-white">+500</p>
                            <p class="text-xs text-white/50 uppercase tracking-widest">Productos</p>
                        </div>
                        <div>
                            <p class="text-2xl font-black text-white">24hs</p>
                            <p class="text-xs text-white/50 uppercase tracking-widest">Envío express</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Separador ondulado --}}
            <div class="absolute bottom-0 left-0 w-full overflow-hidden leading-none">
                <svg viewBox="0 0 1440 60" preserveAspectRatio="none" class="w-full h-12 sm:h-16 fill-slate-50 dark:fill-[#080c14] transition-colors duration-300">
                    <path d="M0,40 C360,80 1080,0 1440,40 L1440,60 L0,60 Z"/>
                </svg>
            </div>
        </section>

        {{-- ════════════════════════════════════════════════════════
             CATÁLOGO DE PRODUCTOS -> MOVED TO SHOP.BLADE.PHP
        ════════════════════════════════════════════════════════ --}}


    </div>

    {{-- Panel del Carrito --}}
    <livewire:cart-panel />

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
