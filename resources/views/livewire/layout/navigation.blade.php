<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    public $transparent = false;

    public function logout(Logout $logout): void
    {
        $logout();
        $this->redirect(route('home'), navigate: true);
    }
}; ?>

<div class="contents">
    {{-- ── Top Announcement Bar (Luxury only, injected via a check) ── --}}
    @php
        $settings = \App\Models\StoreSetting::getSettings();
        $themeName = $settings->theme_name ?? 'stealth';
        $isLuxury = ($themeName === 'luxury');
        $isModernLight = ($themeName === 'modern-light');
        
        $pendingOrdersCount = 0;
        if(auth()->check() && auth()->user()->isAdmin()) {
            $pendingOrdersCount = \App\Models\Order::where('status', 'pendiente')->count();
        }
    @endphp

    <nav x-data="{ open: false, scrolled: false }"
     @scroll.window="scrolled = (window.pageYOffset > 20)"
     :class="{
         'bg-[#0a0f1c]/90 backdrop-blur-xl border-b border-white/5 shadow-none': {{ $isLuxury ? 'true' : 'false' }} && (!{{ $transparent ? 'true' : 'false' }} || scrolled),
         'bg-gray-950 shadow-lg border-b-2 border-red-600': {{ $isModernLight ? 'true' : 'false' }} && (!{{ $transparent ? 'true' : 'false' }} || scrolled),
         'bg-white dark:bg-zinc-900/90 dark:bg-slate-900/90 backdrop-blur-xl border-b border-slate-200/60 dark:border-slate-800/60 shadow-sm dark:shadow-none': (!{{ $isLuxury ? 'true' : 'false' }} && !{{ $isModernLight ? 'true' : 'false' }}) && (!{{ $transparent ? 'true' : 'false' }} || scrolled),
         'bg-transparent border-transparent': {{ $transparent ? 'true' : 'false' }} && !scrolled
     }"
     class="sticky top-0 z-50 transition-all duration-300 relative overflow-visible">
    


    @if($isLuxury)
        {{-- Top Bar Animada --}}
        <div class="bg-gradient-to-r from-red-600 via-red-500 to-red-600 text-gray-900 dark:text-white overflow-hidden relative z-[60] py-1 shadow-lg">
            <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')] opacity-20 mix-blend-overlay"></div>
            <div class="whitespace-nowrap animate-[marquee_20s_linear_infinite] flex items-center gap-8 text-[10px] font-bold uppercase tracking-widest relative z-10">
                <span class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-yellow-300 animate-ping"></span> PRECIOS MAYORISTAS DISPONIBLES</span>
                <span class="text-gray-900 dark:text-white/30">&bull;</span>
                <span class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-green-300 animate-pulse"></span> ENVÍOS A TODO EL PAÍS</span>
                <span class="text-gray-900 dark:text-white/30">&bull;</span>
                <span class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-yellow-300 animate-ping"></span> PRECIOS MAYORISTAS DISPONIBLES</span>
                <span class="text-gray-900 dark:text-white/30">&bull;</span>
                <span class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-green-300 animate-pulse"></span> ENVÍOS A TODO EL PAÍS</span>
                <span class="text-gray-900 dark:text-white/30">&bull;</span>
                <span class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-yellow-300 animate-ping"></span> PRECIOS MAYORISTAS DISPONIBLES</span>
                <span class="text-gray-900 dark:text-white/30">&bull;</span>
                <span class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-green-300 animate-pulse"></span> ENVÍOS A TODO EL PAÍS</span>
            </div>
            <style>
                @keyframes marquee {
                    0% { transform: translateX(0%); }
                    100% { transform: translateX(-50%); }
                }
            </style>
        </div>
    @elseif($isModernLight)
        {{-- Ticker Animado (Marquesina) para Modern Light --}}
        <div class="bg-red-600 text-white overflow-hidden relative z-[60] py-1.5 flex shadow-md">
            {{-- Marca de Agua "JCG" intercalada en el fondo --}}
            <div class="absolute inset-0 pointer-events-none opacity-[0.5] mix-blend-screen" 
                 style="background-image: url('{{ tenant_asset('logos/watermark-cjg.png') }}'), url('{{ tenant_asset('logos/watermark-cjg.png') }}'); background-repeat: repeat, repeat; background-size: 60px, 60px; background-position: 0 0, 30px 30px;">
            </div>

            <div class="whitespace-nowrap animate-[marquee_25s_linear_infinite] flex items-center gap-10 text-[11px] sm:text-xs font-black uppercase tracking-[0.2em] relative z-10 drop-shadow-md">
                <span class="flex items-center gap-2 text-white"><span class="animate-pulse text-lg">🔥</span> PRECIOS MAYORISTAS EN TODOS LOS CONTROLES</span>
                <span class="text-white/50">&bull;</span>
                <span class="flex items-center gap-2 text-yellow-300"><span class="text-lg">✅</span> RETIRO INMEDIATO EN LOCAL</span>
                <span class="text-white/50">&bull;</span>
                <span class="flex items-center gap-2 text-white"><span class="text-lg">🛡️</span> COMPRA 100% SEGURA</span>
                <span class="text-white/50">&bull;</span>
                <span class="flex items-center gap-2 text-white"><span class="animate-pulse text-lg">🔥</span> PRECIOS MAYORISTAS EN TODOS LOS CONTROLES</span>
                <span class="text-white/50">&bull;</span>
                <span class="flex items-center gap-2 text-yellow-300"><span class="text-lg">✅</span> RETIRO INMEDIATO EN LOCAL</span>
                <span class="text-white/50">&bull;</span>
                <span class="flex items-center gap-2 text-white"><span class="text-lg">🛡️</span> COMPRA 100% SEGURA</span>
                <span class="text-white/50">&bull;</span>
                <span class="flex items-center gap-2 text-white"><span class="animate-pulse text-lg">🔥</span> PRECIOS MAYORISTAS EN TODOS LOS CONTROLES</span>
                <span class="text-white/50">&bull;</span>
                <span class="flex items-center gap-2 text-yellow-300"><span class="text-lg">✅</span> RETIRO INMEDIATO EN LOCAL</span>
                <span class="text-white/50">&bull;</span>
                <span class="flex items-center gap-2 text-white"><span class="text-lg">🛡️</span> COMPRA 100% SEGURA</span>
            </div>
            <style>
                @keyframes marquee {
                    0% { transform: translateX(0%); }
                    100% { transform: translateX(-50%); }
                }
                @keyframes writeReveal {
                    0% { clip-path: inset(0 100% 0 0); opacity: 0; filter: drop-shadow(0 0 0 rgba(220,38,38,0)); }
                    30% { opacity: 1; filter: drop-shadow(0 0 10px rgba(220,38,38,0.5)); }
                    100% { clip-path: inset(0 0 0 0); opacity: 1; filter: drop-shadow(0 10px 20px rgba(220,38,38,0.3)); }
                }
            </style>
        </div>
    @else
        {{-- Ticker Animado (Marquesina) Comercial (G3 / Default) --}}
        <div class="bg-zinc-950 border-b border-zinc-900 text-g3-silver overflow-hidden relative z-[60] py-1.5 flex shadow-md">
            {{-- Marca de Agua intercalada en el fondo (Solo 1 línea como en main) --}}
            <div class="absolute inset-0 pointer-events-none flex items-center overflow-hidden" style="opacity: 0.15; filter: grayscale(100%) brightness(150%);">
                @php
                    $bgFavicon = isset($settings) && $settings->favicon_url ? tenant_asset($settings->favicon_url) : asset('images/favicon.png');
                @endphp
                @for($i = 0; $i < 30; $i++)
                    <img src="{{ $bgFavicon }}" class="w-6 h-auto shrink-0" style="margin-right: 80px;" alt="">
                @endfor
            </div>

            <div class="whitespace-nowrap animate-[marquee_35s_linear_infinite] flex items-center gap-10 text-[11px] sm:text-xs font-bold uppercase tracking-[0.2em] relative z-10 drop-shadow-md">
                {{-- Bloque 1 --}}
                <span class="flex items-center gap-2 text-white"><span class="animate-pulse text-g3-blue text-lg">⚡</span> LOS MEJORES PRECIOS EN HARDWARE</span>
                <span class="text-zinc-700">&bull;</span>
                <span class="flex items-center gap-2 text-white"><span class="text-lg">📦</span> ENVÍOS A TODO EL PAÍS</span>
                <span class="text-zinc-700">&bull;</span>
                <span class="flex items-center gap-2 text-g3-green"><span class="text-lg">✅</span> RETIRO INMEDIATO EN LOCAL</span>
                <span class="text-zinc-700">&bull;</span>
                <span class="flex items-center gap-2 text-white"><span class="text-lg text-g3-green">💵</span> EFECTIVO O TRANSFERENCIA</span>
                <span class="text-zinc-700">&bull;</span>
                <span class="flex items-center gap-2 text-white"><span class="text-lg">🛡️</span> COMPRA 100% SEGURA</span>
                <span class="text-zinc-700">&bull;</span>
                
                {{-- Bloque 2 (Duplicado para el loop continuo) --}}
                <span class="flex items-center gap-2 text-white"><span class="animate-pulse text-g3-blue text-lg">⚡</span> LOS MEJORES PRECIOS EN HARDWARE</span>
                <span class="text-zinc-700">&bull;</span>
                <span class="flex items-center gap-2 text-white"><span class="text-lg">📦</span> ENVÍOS A TODO EL PAÍS</span>
                <span class="text-zinc-700">&bull;</span>
                <span class="flex items-center gap-2 text-g3-green"><span class="text-lg">✅</span> RETIRO INMEDIATO EN LOCAL</span>
                <span class="text-zinc-700">&bull;</span>
                <span class="flex items-center gap-2 text-white"><span class="text-lg text-g3-green">💵</span> EFECTIVO O TRANSFERENCIA</span>
                <span class="text-zinc-700">&bull;</span>
                <span class="flex items-center gap-2 text-white"><span class="text-lg">🛡️</span> COMPRA 100% SEGURA</span>
            </div>
            <style>
                @keyframes marquee {
                    0% { transform: translateX(0%); }
                    100% { transform: translateX(-50%); }
                }
            </style>
        </div>
    @endif

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="flex justify-between items-center h-20 relative">

            @if($isLuxury)
                {{-- ════════════ LUXURY NAVBAR ════════════ --}}
                {{-- Izquierda: Links --}}
                <div class="hidden sm:flex flex-1 items-center gap-6">
                    <x-nav-link :href="route('home')" :active="request()->routeIs('home')">
                        {{ __('Inicio') }}
                    </x-nav-link>
                    <x-nav-link :href="route('shop')" :active="request()->routeIs('shop')">
                        {{ __('Tienda') }}
                    </x-nav-link>
                    @if(auth()->check() && optional(auth()->user())->isAdmin())
                        <x-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                            {{ __('Panel') }}
                        </x-nav-link>
                        <x-nav-link :href="route('admin.products')" :active="request()->routeIs('admin.products')">
                            {{ __('Productos') }}
                        </x-nav-link>
                    @endif
                </div>

                {{-- Centro: Logo (Absolute Center) --}}
                <div class="hidden sm:flex absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 items-center justify-center">
                    <a href="{{ url('/') }}" class="shrink-0 flex items-center">
                        @if(isset($settings) && $settings->logo_url)
                            <img src="{{ tenant_asset($settings->logo_url) }}"
                                 alt="Logo" class="h-8 w-auto object-contain drop-shadow-md hover:scale-105 transition-transform duration-300">
                        @else
                            <x-application-logo class="block h-8 w-auto fill-current text-gray-900 dark:text-white transition-colors"/>
                        @endif
                    </a>
                </div>
                
                {{-- Mobile Solo Logo (se ve en movil cuando links estan ocultos) --}}
                <div class="flex sm:hidden flex-1 items-center">
                    <a href="{{ url('/') }}" class="shrink-0 flex items-center">
                        @if(isset($settings) && $settings->logo_url)
                            <img src="{{ tenant_asset($settings->logo_url) }}" alt="Logo" class="h-7 w-auto object-contain hover:scale-105 transition-transform duration-300">
                        @else
                            <x-application-logo class="block h-7 w-auto fill-current text-gray-900 dark:text-white"/>
                        @endif
                    </a>
                </div>
            @elseif($isModernLight)
                {{-- ════════════ MODERN-LIGHT NAVBAR ════════════ --}}
                
                <div class="flex items-center justify-between flex-1 relative z-10">
                    {{-- ── Izquierda: Links ── --}}
                    <div class="hidden sm:flex items-center gap-6 flex-1">
                        <x-nav-link :href="route('home')" :active="request()->routeIs('home')" class="text-base font-bold text-white/90 hover:text-white border-transparent hover:border-white/50">
                            {{ __('Inicio') }}
                        </x-nav-link>
                        <x-nav-link :href="route('shop')" :active="request()->routeIs('shop')" class="text-base font-bold text-white/90 hover:text-white border-transparent hover:border-white/50">
                            {{ __('Tienda') }}
                        </x-nav-link>
                        @if(auth()->check() && optional(auth()->user())->isAdmin())
                            <x-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')" class="text-base font-bold text-white/90 hover:text-white border-transparent hover:border-white/50">
                                {{ __('Panel') }}
                            </x-nav-link>
                            <x-nav-link :href="route('admin.products')" :active="request()->routeIs('admin.products')" class="text-base font-bold text-white/90 hover:text-white border-transparent hover:border-white/50">
                                {{ __('Productos') }}
                            </x-nav-link>
                        @endif
                    </div>

                    {{-- ── Centro: Logo (Centrado dinámico entre links y buscador) ── --}}
                    <div class="hidden sm:flex items-center justify-center z-[100] px-4">
                        <a href="{{ url('/') }}" class="shrink-0 flex items-center justify-center relative h-20 w-48 sm:w-64 hover:scale-105 transition-transform pointer-events-auto">
                            @if(isset($settings) && $settings->logo_url)
                                <img src="{{ tenant_asset($settings->logo_url) }}"
                                     alt="Logo" class="absolute max-w-none pointer-events-none drop-shadow-[0_10px_20px_rgba(220,38,38,0.3)]" style="top: 72%; left: 50%; width: 220px; height: auto; transform: translate(-50%, -50%); animation: writeReveal 2.5s ease-out 0.5s both;">
                            @else
                                <div class="flex items-center gap-2 text-white" style="animation: writeReveal 2.5s ease-out 0.5s both;">
                                    <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L2 22h20L12 2zm0 4.5l6.5 13h-13L12 6.5z"/></svg>
                                    <span class="text-2xl font-black tracking-tight text-white">JCG Electrónica</span>
                                </div>
                            @endif
                        </a>
                    </div>
                    
                    {{-- Logo en Móvil (Visible solo en pantallas chicas) --}}
                    <div class="flex sm:hidden flex-1 items-center justify-start z-[100]">
                        <a href="{{ url('/') }}" class="shrink-0 flex items-center justify-center relative h-16 w-48 ml-4">
                            @if(isset($settings) && $settings->logo_url)
                                <img src="{{ tenant_asset($settings->logo_url) }}"
                                     alt="Logo" class="absolute max-w-none pointer-events-none drop-shadow-md" style="top: 72%; left: 50%; width: 180px; height: auto; transform: translate(-50%, -50%); animation: writeReveal 2.5s ease-out 0.5s both;">
                            @else
                                <span class="text-lg font-black text-white" style="animation: writeReveal 2.5s ease-out 0.5s both;">JCG</span>
                            @endif
                        </a>
                    </div>

                    {{-- ── Derecha: Buscador + Íconos ── --}}
                    <div class="hidden sm:flex items-center gap-4 flex-1 justify-end">
                        <div class="hidden sm:block w-full max-w-[280px] mr-2 text-white">
                            <livewire:search-bar />
                        </div>
                        <div class="text-white">
                            <livewire:cart-icon />
                        </div>
                        @auth
                            <x-dropdown align="right" width="48">
                                <x-slot name="trigger">
                                    <button class="relative flex items-center gap-2 p-2 rounded-full hover:bg-white/10 transition-colors">
                                        <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center font-bold text-white shadow-sm border border-white/30">
                                            {{ substr(Auth::user()->name, 0, 1) }}
                                        </div>
                                        @if(optional(auth()->user())->isAdmin() && $pendingOrdersCount > 0)
                                            <span class="absolute top-1 right-1 flex h-3 w-3">
                                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                                <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500 border border-zinc-900"></span>
                                            </span>
                                        @endif
                                    </button>
                                </x-slot>
                                <x-slot name="content">
                                        @if(optional(auth()->user())->isAdmin())
                                        <div class="px-3 py-1.5">
                                            <p class="text-[10px] uppercase tracking-widest font-bold text-gray-500 dark:text-gray-400">Administración</p>
                                        </div>
                                        <x-dropdown-link :href="route('admin.orders')" class="flex items-center justify-between w-full">
                                            <span>📦 &nbsp;Órdenes</span>
                                            @if($pendingOrdersCount > 0)
                                                <span class="bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">{{ $pendingOrdersCount }}</span>
                                            @endif
                                        </x-dropdown-link>
                                        <x-dropdown-link :href="route('admin.users')">👥 &nbsp;Usuarios</x-dropdown-link>
                                        <x-dropdown-link :href="route('admin.settings')">⚙️ &nbsp;Configuración</x-dropdown-link>
                                        <div class="my-1 border-t border-gray-100 dark:border-zinc-800"></div>
                                    @else
                                        <x-dropdown-link :href="route('my-orders')">🛍 &nbsp;Mis Órdenes</x-dropdown-link>
                                    @endif
                                    <x-dropdown-link :href="route('profile')">👤 &nbsp;Mi Perfil</x-dropdown-link>
                                    <div class="my-1 border-t border-gray-100 dark:border-zinc-800"></div>
                                    <button wire:click="logout" class="w-full text-start">
                                        <x-dropdown-link class="text-red-600 hover:text-red-700">🚪 &nbsp;Cerrar Sesión</x-dropdown-link>
                                    </button>
                                </x-slot>
                            </x-dropdown>
                        @else
                            <a href="{{ route('login') }}" class="text-sm font-bold text-white/90 hover:text-white transition-colors whitespace-nowrap">
                                Ingresar
                            </a>
                            <a href="{{ route('register') }}" class="px-4 py-2 text-sm font-bold text-[var(--color-primary)] rounded-xl transition-all shadow hover:shadow-md hover:-translate-y-0.5 bg-white hover:bg-gray-50 whitespace-nowrap">
                                Registrarse
                            </a>
                        @endauth
                    </div>
                </div>
            @else
                {{-- ════════════ STEALTH NAVBAR ════════════ --}}
                {{-- ── Izquierda: Logo + Links ── --}}
                <div class="flex items-center gap-8 flex-1">
                    {{-- Logo --}}
                    <a href="{{ url('/') }}" class="shrink-0 flex items-center">
                        @if(isset($settings) && $settings->logo_url)
                            <img src="{{ tenant_asset($settings->logo_url) }}"
                                 alt="Logo" class="h-16 w-auto object-contain drop-shadow-md">
                        @else
                            <x-application-logo class="block h-16 w-auto fill-current text-slate-800 dark:text-slate-200 transition-colors"/>
                        @endif
                    </a>

                    {{-- Nav links desktop --}}
                    <div class="hidden sm:flex items-center gap-1">
                        <x-nav-link :href="route('home')" :active="request()->routeIs('home')">
                            {{ __('Inicio') }}
                        </x-nav-link>
                        <x-nav-link :href="route('shop')" :active="request()->routeIs('shop')">
                            {{ __('Tienda') }}
                        </x-nav-link>
                        @if(auth()->check() && optional(auth()->user())->isAdmin())
                            <x-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                                {{ __('Panel') }}
                            </x-nav-link>
                            <x-nav-link :href="route('admin.products')" :active="request()->routeIs('admin.products')">
                                {{ __('Productos') }}
                            </x-nav-link>
                        @endif
                    </div>
                </div>

                {{-- ── Centro: Buscador ── --}}
                <div class="hidden sm:flex flex-1 items-center justify-center px-6">
                    <div class="w-full max-w-lg">
                        <livewire:search-bar />
                    </div>
                </div>
            @endif

            {{-- ── Derecha: Acciones desktop (Compartido, pero adaptado) ── --}}
            @if(!$isModernLight)
            <div class="hidden sm:flex {{ $isLuxury ? 'flex-1 justify-end' : '' }} items-center gap-4">

                {{-- Solo mostrar buscador compacto en Luxury --}}
                @if($isLuxury)
                    <div class="w-48 hidden lg:block">
                        <livewire:search-bar />
                    </div>
                @endif

                {{-- Carrito --}}
                <livewire:cart-icon />

                {{-- Usuario --}}
                @auth
                    <x-dropdown align="right" width="56" :contentClasses="$isLuxury ? 'py-1 bg-[#0a0f1c] border border-white/5' : 'py-1 bg-white dark:bg-gray-800'">
                        <x-slot name="trigger">
                            <button class="flex items-center gap-2 px-3 py-2 rounded-xl text-sm font-medium
                                           text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-gray-900 dark:text-white
                                           focus:outline-none transition-all">
                                <div class="w-7 h-7 rounded-full flex items-center justify-center text-gray-900 dark:text-white text-xs font-bold shadow-sm"
                                     style="background: linear-gradient(135deg, var(--color-primary), color-mix(in srgb, var(--color-primary) 60%, #7c3aed))">
                                    {{ strtoupper(substr(optional(auth()->user())->name ?? 'U', 0, 1)) }}
                                </div>
                                <span x-data="{{ json_encode(['name' => optional(auth()->user())->name ?? 'Usuario']) }}"
                                      x-text="name"
                                      x-on:profile-updated.window="name = $event.detail.name"
                                      class="max-w-[100px] truncate"></span>
                                <svg class="w-3.5 h-3.5 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            @if(optional(auth()->user())->isAdmin())
                                <div class="px-3 py-1.5">
                                    <p class="text-[10px] uppercase tracking-widest font-bold text-slate-400 dark:text-slate-500">Administración</p>
                                </div>
                                <x-dropdown-link :href="route('admin.orders')">📦 &nbsp;Órdenes</x-dropdown-link>
                                <x-dropdown-link :href="route('admin.users')">👥 &nbsp;Usuarios</x-dropdown-link>
                                <x-dropdown-link :href="route('admin.settings')">⚙️ &nbsp;Configuración</x-dropdown-link>
                                <div class="my-1 border-t border-slate-100 dark:border-slate-700/60"></div>
                            @else
                                <x-dropdown-link :href="route('my-orders')">🛍 &nbsp;Mis Órdenes</x-dropdown-link>
                            @endif
                            <x-dropdown-link :href="route('profile')">👤 &nbsp;Mi Perfil</x-dropdown-link>
                            <div class="my-1 border-t border-slate-100 dark:border-slate-700/60"></div>
                            <button wire:click="logout" class="w-full text-start">
                                <x-dropdown-link>🚪 &nbsp;Cerrar Sesión</x-dropdown-link>
                            </button>
                        </x-slot>
                    </x-dropdown>
                @else
                    <a href="{{ route('login') }}"
                       class="px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-gray-900 dark:text-white transition-colors whitespace-nowrap">
                        Ingresar
                    </a>
                    <a href="{{ route('register') }}"
                       class="px-4 py-2 text-sm font-bold text-gray-900 dark:text-white rounded-xl transition-all
                              hover:opacity-90 hover:-translate-y-0.5 shadow-md whitespace-nowrap"
                       style="background: linear-gradient(135deg, var(--color-primary), color-mix(in srgb, var(--color-primary) 60%, #7c3aed))">
                        Registrarse
                    </a>
                @endauth
            </div>
            @endif

            {{-- ── Móvil: Carrito + Tema + Hamburger ── --}}
            <div class="flex items-center gap-1 sm:hidden">
                <livewire:cart-icon />

                <button @click="open = !open"
                        class="relative p-2 rounded-xl {{ ($isModernLight || $isLuxury) ? 'text-white hover:bg-white/10' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800' }} transition-all"
                        aria-label="Menú">
                    @if(optional(auth()->user())->isAdmin() && $pendingOrdersCount > 0)
                        <span class="absolute top-1 right-1 flex h-2.5 w-2.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500 border border-white dark:border-gray-900"></span>
                        </span>
                    @endif
                    <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': !open}" class="inline-flex"
                              stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        <path :class="{'hidden': !open, 'inline-flex': open}" class="hidden"
                              stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

        </div>
    </div>

    {{-- ── Menú Móvil desplegable ── --}}
    {{-- ── Menú Móvil desplegable ── --}}
    <div :class="{'block': open, 'hidden': !open}"
         class="hidden sm:hidden transition-colors duration-300 {{ $isLuxury ? 'bg-[#0a0f1c] border-t border-white/5' : 'bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800' }}">
        <div class="pt-4 pb-3 space-y-1 px-4">
            <x-responsive-nav-link :href="route('home')" :active="request()->routeIs('home')">
                <span class="w-5 text-center">🏠</span> <span>Inicio</span>
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('shop')" :active="request()->routeIs('shop')">
                <span class="w-5 text-center">🛍️</span> <span>Tienda</span>
            </x-responsive-nav-link>
            @if(auth()->check() && optional(auth()->user())->isAdmin())
                <x-responsive-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                    <span class="w-5 text-center">📊</span> <span>Panel</span>
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.products')" :active="request()->routeIs('admin.products')">
                    <span class="w-5 text-center">🏷️</span> <span>Productos</span>
                </x-responsive-nav-link>
            @endif
        </div>

        @auth
        <div class="pt-4 pb-3 {{ $isLuxury ? 'border-t border-white/5' : 'border-t border-slate-200 dark:border-slate-800' }}">
            <div class="px-4 mb-4 mx-4 py-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl flex items-center gap-3 border border-slate-100 dark:border-slate-700/50">
                <div class="w-10 h-10 rounded-full flex items-center justify-center text-gray-900 dark:text-white text-sm font-bold shadow-sm shrink-0"
                     style="background: linear-gradient(135deg, var(--color-primary), color-mix(in srgb, var(--color-primary) 60%, #7c3aed))">
                    {{ strtoupper(substr(optional(auth()->user())->name ?? 'U', 0, 1)) }}
                </div>
                <div class="overflow-hidden">
                    <div class="font-bold text-slate-800 dark:text-slate-200 text-sm truncate"
                         x-data="{{ json_encode(['name' => optional(auth()->user())->name ?? 'Usuario']) }}"
                         x-text="name"
                         x-on:profile-updated.window="name = $event.detail.name"></div>
                    <div class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ optional(auth()->user())->email }}</div>
                </div>
            </div>
            <div class="space-y-1 px-4">
                @if(optional(auth()->user())->isAdmin())
                    <x-responsive-nav-link :href="route('admin.orders')">
                        <div class="flex items-center justify-between w-full">
                            <div class="flex items-center gap-3">
                                <span class="w-5 text-center">📦</span> <span>Gestión de Órdenes</span>
                            </div>
                            @if($pendingOrdersCount > 0)
                                <span class="bg-red-500 text-gray-900 dark:text-white text-[10px] font-bold px-2 py-0.5 rounded-full">{{ $pendingOrdersCount }}</span>
                            @endif
                        </div>
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.users')">
                        <span class="w-5 text-center">👥</span> <span>Usuarios</span>
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.settings')">
                        <span class="w-5 text-center">⚙️</span> <span>Configuración</span>
                    </x-responsive-nav-link>
                @else
                    <x-responsive-nav-link :href="route('my-orders')">
                        <span class="w-5 text-center">🛍</span> <span>Mis Órdenes</span>
                    </x-responsive-nav-link>
                @endif
                <x-responsive-nav-link :href="route('profile')">
                    <span class="w-5 text-center">👤</span> <span>Mi Perfil</span>
                </x-responsive-nav-link>
                <div class="my-2 border-t border-slate-100 dark:border-slate-800/60 mx-4"></div>
                <button wire:click="logout" class="w-full text-start focus:outline-none">
                    <x-responsive-nav-link class="!text-red-500 hover:!text-red-600 hover:!bg-red-50 dark:hover:!bg-red-900/20">
                        <span class="w-5 text-center">🚪</span> <span>Cerrar Sesión</span>
                    </x-responsive-nav-link>
                </button>
            </div>
        </div>
        @else
        <div class="pt-4 pb-3 space-y-1 px-4 {{ $isLuxury ? 'border-t border-white/5' : 'border-t border-slate-200 dark:border-slate-800' }}">
            <x-responsive-nav-link :href="route('login')">
                <span class="w-5 text-center">🔑</span> <span>Iniciar Sesión</span>
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('register')">
                <span class="w-5 text-center">📝</span> <span>Registrarse</span>
            </x-responsive-nav-link>
        </div>
        @endauth
    </div>

</nav>
</div>
