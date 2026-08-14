@php
    $settings = \App\Models\StoreSetting::getSettings();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php
            $storeName = $settings->store_name ?? 'G3 Tecnología';
            $logoUrl = $settings->logo_url ? asset('storage/' . $settings->logo_url) : asset('storage/logos/logo-cjg-horizontal.png');
            $description = $settings->meta_description ?? 'Venta de Hardware, Componentes y Electrónica de Alto Rendimiento. Precios mayoristas y minoristas.';
        @endphp

        <title>{{ $storeName }}</title>

        @if(isset($settings) && $settings->favicon_url)
            <link rel="icon" href="{{ asset('storage/' . $settings->favicon_url) }}">
        @else
            <link rel="icon" href="{{ asset('images/favicon.png') }}">
        @endif

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

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800,900&display=swap" rel="stylesheet" />

        <!-- Tema y Stores de Alpine: inicialización segura -->
        <script>
            (function () {
                var themeName = '{{ $settings->theme_name ?? 'stealth' }}';
                var isLuxury = themeName === 'luxury';
                var isModernLight = themeName === 'modern-light';
                
                if (isLuxury) {
                    document.documentElement.classList.add('dark');
                    localStorage.theme = 'dark';
                } else if (isModernLight) {
                    document.documentElement.classList.add('dark');
                    localStorage.theme = 'dark';
                } else {
                    var dark = localStorage.theme === 'dark' ||
                        (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches);
                    document.documentElement.classList.toggle('dark', dark);
                }
            })();

            // Definir stores ANTES de que Alpine arranque
            document.addEventListener('alpine:init', () => {
                Alpine.store('theme', {
                    dark: document.documentElement.classList.contains('dark'),
                    toggle() { 
                        this.dark = !this.dark; 
                        document.documentElement.classList.toggle('dark', this.dark);
                        localStorage.theme = this.dark ? 'dark' : 'light';
                    },
                    apply() { 
                        document.documentElement.classList.toggle('dark', this.dark);
                        localStorage.theme = this.dark ? 'dark' : 'light';
                    }
                });

                Alpine.store('cart', {
                    open: false,
                    show()   { this.open = true;  },
                    hide()   { this.open = false; },
                    toggle() { this.open = !this.open; }
                });
            });
        </script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            :root {
                @if(($settings->theme_name ?? 'stealth') === 'modern-light')
                    /* G3 Tech Primary Color: Blue */
                    --color-primary: #3B82F6; /* blue-500 */
                    --color-primary-hover: #2563EB; /* blue-600 */
                @else
                    /* Default Blue Primary Color */
                    --color-primary: #2563EB;
                    --color-primary-hover: #1D4ED8;
                @endif
                --color-primary-glow: color-mix(in srgb, var(--color-primary) 30%, transparent);
            }
            body {
                font-family: 'Inter', sans-serif;
            }
        </style>
    </head>
    <body class="antialiased min-h-screen flex flex-col relative overflow-x-hidden selection:bg-g3-blue selection:text-white bg-g3-dark text-gray-100 transition-colors duration-300">
        

        <!-- Subtle Background Glow (Dark Mode Only) -->
        <div x-data="{}" x-show="$store.theme.dark && '{{ $settings->theme_name ?? 'stealth' }}' !== 'modern-light'" x-transition.opacity.duration.500ms class="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] h-[500px] opacity-20 pointer-events-none" style="background: radial-gradient(circle, var(--color-primary-glow) 0%, transparent 70%);"></div>

        <div class="min-h-screen flex flex-col relative z-40 w-full">
            <livewire:layout.navigation />

            <!-- Page Heading -->
            @if (isset($header))
                <header class="bg-g3-card/80 backdrop-blur-md border-b border-zinc-800 transition-colors duration-300">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endif

            <!-- Page Content -->
            <main class="flex-grow">
                {{ $slot }}
            </main>
        </div>

        <!-- Slide-over Cart Panel -->
        <livewire:cart-panel />

        <!-- Footer -->
        <livewire:layout.footer />

        <!-- Botón Flotante de WhatsApp Global -->
        @php
            $social = isset($settings) && is_string($settings->social_links) ? json_decode($settings->social_links, true) : (isset($settings) ? $settings->social_links : []);
            $whatsappNumber = is_array($social) && !empty($social['whatsapp']) ? preg_replace('/[^0-9]/', '', $social['whatsapp']) : '5493704022685';
            $whatsappUrl = $whatsappNumber ? 'https://wa.me/' . $whatsappNumber : null;
        @endphp
        
        @if($whatsappUrl)
            <a href="{{ $whatsappUrl }}" target="_blank" 
               class="fixed bottom-6 right-6 z-[100] w-14 h-14 bg-green-500 text-white rounded-full flex items-center justify-center shadow-lg hover:bg-green-600 hover:scale-110 transition-all duration-300"
               aria-label="Contactar por WhatsApp">
                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 00-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                </svg>
                <span class="absolute -top-1 -right-1 flex h-4 w-4">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-4 w-4 bg-g3-blue border-2 border-white"></span>
                </span>
            </a>
        @endif
    </body>
</html>
