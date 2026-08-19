@php
    $settings = \App\Models\StoreSetting::getSettings();
    $logoUrl = $settings ? $settings->logo_url : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $settings->store_name ?? config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800,900&display=swap" rel="stylesheet" />

        <!-- Tema y Stores de Alpine: inicialización segura -->
        <script>
            (function () {
                var themeName = '{{ $settings->theme_name ?? 'modern-light' }}';
                var isDark = themeName === 'tech-dark';
                
                if (isDark) {
                    document.documentElement.classList.add('dark');
                    localStorage.theme = 'dark';
                } else {
                    document.documentElement.classList.remove('dark');
                    localStorage.theme = 'light';
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
                    }
                });
            });
        </script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            :root {
                @if(($settings->theme_name ?? 'modern-light') === 'modern-light')
                    /* JCG Electrónica Primary Color: Tailwind red-600 */
                    --color-primary: #DC2626;
                    --color-primary-hover: #B91C1C; /* red-700 */
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
            [x-cloak] { display: none !important; }
            @keyframes writeReveal {
                0% { clip-path: inset(0 100% 0 0); opacity: 0; filter: drop-shadow(0 0 0 rgba(0,0,0,0)); }
                30% { opacity: 1; filter: drop-shadow(0 0 10px rgba(0,0,0,0.3)); }
                100% { clip-path: inset(0 0 0 0); opacity: 1; filter: drop-shadow(0 10px 20px rgba(0,0,0,0.4)); }
            }
        </style>
    </head>
        <body class="antialiased min-h-screen flex flex-col relative overflow-x-hidden selection:bg-[var(--color-primary)] selection:text-white bg-[#0f0f11] text-gray-900 transition-colors duration-300">
        
        <!-- Subtle Background Glow (Dark Mode Only) -->
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] max-w-[100vw] h-[500px] opacity-30 pointer-events-none" style="background: radial-gradient(circle, var(--color-primary-glow) 0%, transparent 70%);"></div>

        <div class="min-h-screen flex flex-col items-center pt-6 sm:pt-0 relative z-10 w-full">
            

            
            <div class="flex flex-col items-center w-full animate-fade-in-up z-20 my-auto py-12">
                <a href="/" wire:navigate class="-mb-6 sm:-mb-8 -translate-y-8 sm:-translate-y-10 block transition-transform hover:scale-105 hover:-translate-y-8 sm:hover:-translate-y-10 duration-300 relative z-20">
                    @if($logoUrl)
                        <img src="{{ tenant_asset($logoUrl) }}" alt="Logo" class="w-56 sm:w-72 h-auto object-contain drop-shadow-2xl" style="animation: writeReveal 2.5s ease-out 0.2s both;" />
                    @else
                        <x-application-logo class="w-32 h-auto fill-current text-gray-800 dark:text-white transition-colors" style="animation: writeReveal 2.5s ease-out 0.2s both;" />
                    @endif
                </a>

                <div class="w-full sm:max-w-md px-8 py-10 bg-white/90 dark:bg-gray-800/60 backdrop-blur-2xl border border-gray-100 dark:border-gray-700/50 sm:rounded-[2rem] transition-all duration-300" style="box-shadow: 0 25px 50px -12px rgba(0,0,0,0.1), 0 0 40px -10px var(--color-primary-glow);">
                    {{ $slot }}
                </div>
            </div>
        </div>

    </body>
</html>
