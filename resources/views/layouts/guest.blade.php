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

        <title>{{ config('app.name', 'Laravel') }}</title>

        @if(isset($settings) && $settings->favicon_url)
            <link rel="icon" href="{{ asset('storage/' . $settings->favicon_url) }}">
        @else
            <link rel="icon" href="{{ asset('images/favicon.png') }}">
        @endif

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
                    document.documentElement.classList.remove('dark');
                    localStorage.theme = 'light';
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
                    }
                });
            });
        </script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            :root {
                @if(($settings->theme_name ?? 'stealth') === 'modern-light')
                    /* G3 Tecnología Primary Color: Tailwind blue-500 */
                    --color-primary: #3B82F6;
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
            [x-cloak] { display: none !important; }
            @keyframes writeReveal {
                0% { clip-path: inset(0 100% 0 0); opacity: 0; filter: drop-shadow(0 0 0 rgba(59,130,246,0)); }
                30% { opacity: 1; filter: drop-shadow(0 0 10px rgba(59,130,246,0.5)); }
                100% { clip-path: inset(0 0 0 0); opacity: 1; filter: drop-shadow(0 10px 20px rgba(59,130,246,0.3)); }
            }
        </style>
    </head>
        <body class="antialiased min-h-screen flex flex-col relative overflow-x-hidden selection:bg-[var(--color-primary)] selection:text-white bg-[#0f0f11] text-gray-900 transition-colors duration-300">
        
        <!-- Subtle Background Glow -->
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] h-[500px] opacity-30 pointer-events-none" style="background: radial-gradient(circle, var(--color-primary-glow) 0%, transparent 70%);"></div>

        <div class="min-h-screen flex flex-col items-center pt-6 sm:pt-0 relative z-10 w-full">
            
            @if(($settings->theme_name ?? 'stealth') !== 'modern-light' && ($settings->theme_name ?? 'stealth') !== 'luxury')
            <div class="absolute top-6 right-6 z-50">
                <!-- Theme Toggle Button -->
                <button @click="$store.theme.toggle()" class="p-2 rounded-full bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 hover:text-[var(--color-primary)] transition-colors focus:outline-none shadow-md">
                    <svg x-show="!$store.theme.dark" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                    <svg x-show="$store.theme.dark" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                </button>
            </div>
            @endif
            
            <div class="flex flex-col items-center w-full animate-fade-in-up z-20 my-auto py-12">
                <a href="/" wire:navigate class="-mb-6 sm:-mb-8 -translate-y-8 sm:-translate-y-10 block transition-transform hover:scale-105 hover:-translate-y-8 sm:hover:-translate-y-10 duration-300 relative z-20">
                    @if($logoUrl)
                        <img src="{{ asset('storage/' . $logoUrl) }}" alt="Logo" class="w-56 sm:w-72 h-auto object-contain drop-shadow-[0_10px_20px_rgba(59,130,246,0.2)]" style="animation: writeReveal 2.5s ease-out 0.2s both;" />
                    @else
                        <x-application-logo class="w-80 sm:w-96 md:w-[26rem] h-auto text-white fill-current transition-colors drop-shadow-[0_10px_20px_rgba(59,130,246,0.2)]" style="animation: writeReveal 2.5s ease-out 0.2s both;" />
                    @endif
                </a>

                <div class="w-full sm:max-w-md px-8 py-10 bg-white/90 dark:bg-gray-800/60 backdrop-blur-2xl border border-gray-100 dark:border-gray-700/50 sm:rounded-[2rem] transition-all duration-300" style="box-shadow: 0 25px 50px -12px rgba(0,0,0,0.1), 0 0 40px -10px var(--color-primary-glow);">
                    {{ $slot }}
                </div>
            </div>
        </div>

    </body>
</html>
