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
     :class="{
         'bg-[#0a0f1c]/90 backdrop-blur-xl border-b border-white/5 shadow-none': {{ $isLuxury ? 'true' : 'false' }} && (!{{ $transparent ? 'true' : 'false' }} || scrolled),
         'bg-g3-dark/95 backdrop-blur-md shadow-lg border-b border-zinc-800': {{ $isModernLight ? 'true' : 'false' }} && (!{{ $transparent ? 'true' : 'false' }} || scrolled),
         'bg-white/90 dark:bg-slate-900/90 backdrop-blur-xl border-b border-slate-200/60 dark:border-slate-800/60 shadow-sm dark:shadow-none': (!{{ $isLuxury ? 'true' : 'false' }} && !{{ $isModernLight ? 'true' : 'false' }}) && (!{{ $transparent ? 'true' : 'false' }} || scrolled),
         'bg-transparent border-transparent': {{ $transparent ? 'true' : 'false' }} && !scrolled
     }"
     class="sticky top-0 z-50 transition-all duration-300 relative overflow-visible">
    


    @if($isLuxury)
        {{-- Top Bar Animada --}}
        <div class="bg-gradient-to-r from-red-600 via-red-500 to-red-600 text-white overflow-hidden relative z-[60] py-1 shadow-lg">
            <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')] opacity-20 mix-blend-overlay"></div>
            <div class="whitespace-nowrap animate-[marquee_20s_linear_infinite] flex items-center gap-8 text-[10px] font-bold uppercase tracking-widest relative z-10">
                <span class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-yellow-300 animate-ping"></span> PRECIOS MAYORISTAS DISPONIBLES</span>
                <span class="text-white/30">&bull;</span>
                <span class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-green-300 animate-pulse"></span> ENVÍOS A TODO EL PAÍS</span>
                <span class="text-white/30">&bull;</span>
                <span class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-yellow-300 animate-ping"></span> PRECIOS MAYORISTAS DISPONIBLES</span>
                <span class="text-white/30">&bull;</span>
                <span class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-green-300 animate-pulse"></span> ENVÍOS A TODO EL PAÍS</span>
                <span class="text-white/30">&bull;</span>
                <span class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-yellow-300 animate-ping"></span> PRECIOS MAYORISTAS DISPONIBLES</span>
                <span class="text-white/30">&bull;</span>
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
        <div class="bg-zinc-950 border-b border-zinc-900 text-g3-silver overflow-hidden relative z-[60] py-1.5 flex shadow-md">
            {{-- Marca de Agua "G3" intercalada en el fondo --}}
            <div class="absolute inset-0 pointer-events-none flex items-center overflow-hidden" style="opacity: 0.15; filter: grayscale(100%) brightness(150%);">
                @for($i = 0; $i < 30; $i++)
                    <img src="{{ asset('images/favicon.png') }}" class="w-6 h-auto shrink-0" style="margin-right: 80px;" alt="">
                @endfor
            </div>

            <div class="whitespace-nowrap animate-[marquee_35s_linear_infinite] flex items-center gap-10 text-[11px] sm:text-xs font-bold uppercase tracking-[0.2em] relative z-10 drop-shadow-md">
                {{-- Bloque 1 --}}
                <span class="flex items-center gap-2 text-white"><span class="animate-pulse text-g3-blue text-lg">⚡</span> LOS MEJORES PRECIOS EN HARDWARE</span>
                <span class="text-zinc-700">&bull;</span>
                <span class="flex items-center gap-2 text-white"><span class="text-lg">🚚</span> ENVÍOS A TODO EL PAÍS</span>
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
                <span class="flex items-center gap-2 text-white"><span class="text-lg">🚚</span> ENVÍOS A TODO EL PAÍS</span>
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
                @keyframes writeReveal {
                    0% { clip-path: inset(0 100% 0 0); opacity: 0; filter: drop-shadow(0 0 0 rgba(220,38,38,0)); }
                    30% { opacity: 1; filter: drop-shadow(0 0 10px rgba(220,38,38,0.5)); }
                    100% { clip-path: inset(0 0 0 0); opacity: 1; filter: drop-shadow(0 10px 20px rgba(220,38,38,0.3)); }
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
                    <x-nav-link :href="route('home')" :active="request()->routeIs('home')" wire:navigate>
                        {{ __('Inicio') }}
                    </x-nav-link>
                    <x-nav-link :href="route('shop')" :active="request()->routeIs('shop')" wire:navigate>
                        {{ __('Tienda') }}
                    </x-nav-link>
                    @if(auth()->check() && optional(auth()->user())->isAdmin())
                        <x-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')" wire:navigate>
                            {{ __('Panel') }}
                        </x-nav-link>
                        <x-nav-link :href="route('admin.products')" :active="request()->routeIs('admin.products')" wire:navigate>
                            {{ __('Productos') }}
                        </x-nav-link>
                    @endif
                </div>

                {{-- Centro: Logo (Absolute Center) --}}
                <div class="hidden sm:flex absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 items-center justify-center">
                    <a href="{{ url('/') }}" wire:navigate class="shrink-0 flex items-center">
                        @if(isset($settings) && $settings->logo_url)
                            <img src="{{ asset('storage/' . $settings->logo_url) }}"
                                 alt="Logo" class="h-14 w-auto object-contain drop-shadow-[0_0_15px_rgba(59,130,246,0.3)] hover:scale-105 transition-transform duration-300">
                        @else
                            <x-application-logo class="block h-14 w-auto fill-current text-white transition-colors drop-shadow-[0_0_15px_rgba(59,130,246,0.3)]"/>
                        @endif
                    </a>
                </div>
                
                {{-- Mobile Solo Logo (se ve en movil cuando links estan ocultos) --}}
                <div class="flex sm:hidden flex-1 items-center">
                    <a href="{{ url('/') }}" wire:navigate class="shrink-0 flex items-center">
                        @if(isset($settings) && $settings->logo_url)
                            <img src="{{ asset('storage/' . $settings->logo_url) }}" alt="Logo" class="h-12 w-auto object-contain hover:scale-105 transition-transform duration-300">
                        @else
                            <x-application-logo class="block h-12 w-auto fill-current text-white"/>
                        @endif
                    </a>
                </div>
            @elseif($isModernLight)
                {{-- ════════════ G3 TECH NAVBAR ════════════ --}}
                
                <div class="flex items-center justify-between flex-1 relative z-10 h-full w-full">
                    {{-- ── Izquierda: Logo (Absolute Left/Center on Mobile) ── --}}
                    <div class="flex-shrink-0 flex items-center">
                        <a href="{{ url('/') }}" wire:navigate class="flex items-center gap-2 group pointer-events-auto">
                            <!-- G3 Logo -->
                            <x-application-logo class="h-16 w-auto group-hover:scale-105 transition-transform duration-300 drop-shadow-[0_0_15px_rgba(59,130,246,0.3)] group-hover:drop-shadow-[0_0_20px_rgba(126,211,33,0.5)]" />
                        </a>
                    </div>
                    
                    {{-- ── Centro: Links (Hidden on small screens) ── --}}
                    <div class="hidden md:flex items-center gap-6 ml-8">
                        <a href="{{ route('home') }}" wire:navigate class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-bold transition-colors {{ request()->routeIs('home') ? 'border-g3-blue text-white' : 'border-transparent text-gray-300 hover:text-white hover:border-g3-blue' }}">
                            {{ __('Inicio') }}
                        </a>
                        <a href="{{ route('shop') }}" wire:navigate class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-bold transition-colors {{ request()->routeIs('shop') ? 'border-g3-blue text-white' : 'border-transparent text-gray-300 hover:text-white hover:border-g3-blue' }}">
                            {{ __('Tienda') }}
                        </a>
                        @if(auth()->check() && optional(auth()->user())->isAdmin())
                            <a href="{{ route('admin.products') }}" wire:navigate class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-bold transition-colors {{ request()->routeIs('admin.products') ? 'border-g3-blue text-white' : 'border-transparent text-gray-300 hover:text-white hover:border-g3-blue' }}">
                                {{ __('Productos') }}
                            </a>
                            <a href="{{ route('admin.dashboard') }}" wire:navigate class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-bold transition-colors {{ request()->routeIs('admin.dashboard') ? 'border-g3-blue text-white' : 'border-transparent text-gray-300 hover:text-white hover:border-g3-blue' }}">
                                {{ __('Panel') }}
                            </a>
                        @endif
                    </div>

                    {{-- ── Derecha: Buscador + Íconos ── --}}
                    <div class="hidden sm:flex items-center gap-4 flex-1 justify-end ml-auto">
                        <div class="w-full max-w-sm mr-2 text-gray-900">
                            {{-- Livewire Search --}}
                            <livewire:search-bar />
                        </div>
                        <div class="text-gray-300 hover:text-white transition-colors">
                            <livewire:cart-icon />
                        </div>
                        @auth
                            <x-dropdown align="right" width="48" contentClasses="py-1 bg-g3-card border border-zinc-800">
                                <x-slot name="trigger">
                                    <button class="flex items-center gap-2 bg-zinc-800 hover:bg-zinc-700 text-white px-3 py-1.5 rounded-lg transition-colors border border-zinc-700 hover:border-g3-blue">
                                        <div class="w-6 h-6 rounded-full bg-gradient-to-tr from-g3-blue to-g3-green flex items-center justify-center font-bold text-white text-xs shadow-sm">
                                            {{ substr(Auth::user()->name, 0, 1) }}
                                        </div>
                                        <span class="text-sm font-medium hidden lg:block">Mi Cuenta</span>
                                        @if(optional(auth()->user())->isAdmin() && $pendingOrdersCount > 0)
                                            <span class="absolute -top-1 -right-1 flex h-3 w-3">
                                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-g3-green opacity-75"></span>
                                                <span class="relative inline-flex rounded-full h-3 w-3 bg-g3-blue"></span>
                                            </span>
                                        @endif
                                    </button>
                                </x-slot>
                                <x-slot name="content">
                                    @if(optional(auth()->user())->isAdmin())
                                        <div class="px-3 py-1.5">
                                            <p class="text-[10px] uppercase tracking-widest font-bold text-g3-silver">Administración</p>
                                        </div>
                                        <a href="{{ route('admin.orders') }}" wire:navigate class="flex items-center justify-between w-full px-4 py-2 text-sm text-gray-300 hover:text-white hover:bg-zinc-800 transition-colors">
                                            <span>📦 &nbsp;Órdenes</span>
                                            @if($pendingOrdersCount > 0)
                                                <span class="bg-g3-blue text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">{{ $pendingOrdersCount }}</span>
                                            @endif
                                        </a>
                                        <a href="{{ route('admin.users') }}" wire:navigate class="block w-full px-4 py-2 text-start text-sm text-gray-300 hover:text-white hover:bg-zinc-800 transition-colors">👥 &nbsp;Usuarios</a>
                                        <a href="{{ route('admin.products') }}" wire:navigate class="block w-full px-4 py-2 text-start text-sm text-gray-300 hover:text-white hover:bg-zinc-800 transition-colors">🏷️ &nbsp;Productos</a>
                                        <a href="{{ route('admin.settings') }}" wire:navigate class="block w-full px-4 py-2 text-start text-sm text-gray-300 hover:text-white hover:bg-zinc-800 transition-colors">⚙️ &nbsp;Configuración</a>
                                        <div class="my-1 border-t border-zinc-800"></div>
                                    @else
                                        <a href="{{ route('my-orders') }}" wire:navigate class="block w-full px-4 py-2 text-start text-sm text-gray-300 hover:text-white hover:bg-zinc-800 transition-colors">🛍 &nbsp;Mis Órdenes</a>
                                    @endif
                                    <a href="{{ route('profile') }}" wire:navigate class="block w-full px-4 py-2 text-start text-sm text-gray-300 hover:text-white hover:bg-zinc-800 transition-colors">👤 &nbsp;Mi Perfil</a>
                                    <div class="my-1 border-t border-zinc-800"></div>
                                    <button wire:click="logout" class="w-full text-start focus:outline-none">
                                        <span class="block w-full px-4 py-2 text-start text-sm text-gray-400 hover:text-white hover:bg-zinc-800 transition-colors">🚪 &nbsp;Cerrar Sesión</span>
                                    </button>
                                </x-slot>
                            </x-dropdown>
                        @else
                            <a href="{{ route('login') }}" class="text-sm font-bold text-g3-silver hover:text-white transition-colors whitespace-nowrap">
                                Ingresar
                            </a>
                            <a href="{{ route('register') }}" class="px-4 py-2 text-sm font-bold text-white rounded-xl transition-all shadow hover:shadow-[0_0_15px_rgba(59,130,246,0.5)] bg-gradient-to-r from-g3-blue to-g3-green whitespace-nowrap border border-transparent">
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
                    <a href="{{ url('/') }}" wire:navigate class="shrink-0 flex items-center">
                        @if(isset($settings) && $settings->logo_url)
                            <img src="{{ asset('storage/' . $settings->logo_url) }}"
                                 alt="Logo" class="h-9 w-auto object-contain drop-shadow-md">
                        @else
                            <x-application-logo class="block h-9 w-auto fill-current text-slate-800 dark:text-slate-200 transition-colors"/>
                        @endif
                    </a>

                    {{-- Nav links desktop --}}
                    <div class="hidden sm:flex items-center gap-1">
                        <x-nav-link :href="route('home')" :active="request()->routeIs('home')" wire:navigate>
                            {{ __('Inicio') }}
                        </x-nav-link>
                        <x-nav-link :href="route('shop')" :active="request()->routeIs('shop')" wire:navigate>
                            {{ __('Tienda') }}
                        </x-nav-link>
                        @if(auth()->check() && optional(auth()->user())->isAdmin())
                            <x-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')" wire:navigate>
                                {{ __('Panel') }}
                            </x-nav-link>
                            <x-nav-link :href="route('admin.products')" :active="request()->routeIs('admin.products')" wire:navigate>
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

                {{-- Toggle Tema — onclick puro, siempre funciona (Sólo en Stealth puro, ocultar en modern-light) --}}
                @if(!$isLuxury)
                    <button onclick="POS.toggleTheme()" id="nav-theme-btn"
                            class="relative p-2.5 rounded-xl
                                   bg-slate-100 dark:bg-slate-800
                                   text-slate-600 dark:text-slate-300
                                   hover:bg-slate-200 dark:hover:bg-slate-700
                                   hover:text-slate-900 dark:hover:text-white
                                   transition-all focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/30"
                            aria-label="Cambiar tema">
                        {{-- Ícono: Sol (visible en dark, clic → light) --}}
                        <svg id="icon-sun" class="w-5 h-5 hidden dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                        {{-- Ícono: Luna (visible en light, clic → dark) --}}
                        <svg id="icon-moon" class="w-5 h-5 block dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                        </svg>
                    </button>
                @endif

                {{-- Usuario --}}
                @auth
                    <x-dropdown align="right" width="56" :contentClasses="$isLuxury ? 'py-1 bg-[#0a0f1c] border border-white/5' : 'py-1 bg-white dark:bg-gray-800'">
                        <x-slot name="trigger">
                            <button class="flex items-center gap-2 px-3 py-2 rounded-xl text-sm font-medium
                                           text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white
                                           focus:outline-none transition-all">
                                <div class="w-7 h-7 rounded-full flex items-center justify-center text-white text-xs font-bold shadow-sm"
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
                                <x-dropdown-link :href="route('admin.orders')" wire:navigate>📦 &nbsp;Órdenes</x-dropdown-link>
                                <x-dropdown-link :href="route('admin.users')" wire:navigate>👥 &nbsp;Usuarios</x-dropdown-link>
                                <x-dropdown-link :href="route('admin.products')" wire:navigate>🏷️ &nbsp;Productos</x-dropdown-link>
                                <x-dropdown-link :href="route('admin.settings')" wire:navigate>⚙️ &nbsp;Configuración</x-dropdown-link>
                                <div class="my-1 border-t border-slate-100 dark:border-slate-700/60"></div>
                            @else
                                <x-dropdown-link :href="route('my-orders')" wire:navigate>🛍 &nbsp;Mis Órdenes</x-dropdown-link>
                            @endif
                            <x-dropdown-link :href="route('profile')" wire:navigate>👤 &nbsp;Mi Perfil</x-dropdown-link>
                            <div class="my-1 border-t border-slate-100 dark:border-slate-700/60"></div>
                            <button wire:click="logout" class="w-full text-start">
                                <x-dropdown-link>🚪 &nbsp;Cerrar Sesión</x-dropdown-link>
                            </button>
                        </x-slot>
                    </x-dropdown>
                @else
                    <a href="{{ route('login') }}"
                       class="px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-colors whitespace-nowrap">
                        Ingresar
                    </a>
                    <a href="{{ route('register') }}"
                       class="px-4 py-2 text-sm font-bold text-white rounded-xl transition-all
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

                @if(!$isLuxury && !$isModernLight)
                    <button onclick="POS.toggleTheme()"
                            class="p-2 rounded-xl text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all"
                            aria-label="Tema">
                        <svg class="w-5 h-5 hidden dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                        <svg class="w-5 h-5 block dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                        </svg>
                    </button>
                @endif

                <button @click="open = !open"
                        class="relative p-2 rounded-xl {{ $isModernLight ? 'text-white hover:bg-white/10' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800' }} transition-all"
                        aria-label="Menú">
                    @if(optional(auth()->user())->isAdmin() && $pendingOrdersCount > 0)
                        <span class="absolute top-1 right-1 flex h-2.5 w-2.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-g3-green opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-g3-blue border border-white dark:border-gray-900"></span>
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
            <x-responsive-nav-link :href="route('home')" :active="request()->routeIs('home')" wire:navigate>
                <span class="w-5 text-center">🏠</span> <span>Inicio</span>
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('shop')" :active="request()->routeIs('shop')" wire:navigate>
                <span class="w-5 text-center">🛍️</span> <span>Tienda</span>
            </x-responsive-nav-link>
            @if(auth()->check() && optional(auth()->user())->isAdmin())
                <x-responsive-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')" wire:navigate>
                    <span class="w-5 text-center">📊</span> <span>Panel</span>
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.products')" :active="request()->routeIs('admin.products')" wire:navigate>
                    <span class="w-5 text-center">🏷️</span> <span>Productos</span>
                </x-responsive-nav-link>
            @endif
        </div>

        @auth
        <div class="pt-4 pb-3 {{ $isLuxury ? 'border-t border-white/5' : 'border-t border-slate-200 dark:border-slate-800' }}">
            <div class="px-4 mb-4 mx-4 py-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl flex items-center gap-3 border border-slate-100 dark:border-slate-700/50">
                <div class="w-10 h-10 rounded-full flex items-center justify-center text-white text-sm font-bold shadow-sm shrink-0"
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
                    <x-responsive-nav-link :href="route('admin.orders')" wire:navigate>
                        <div class="flex items-center justify-between w-full">
                            <div class="flex items-center gap-3">
                                <span class="w-5 text-center">📦</span> <span>Gestión de Órdenes</span>
                            </div>
                            @if($pendingOrdersCount > 0)
                                <span class="bg-g3-blue text-white text-[10px] font-bold px-2 py-0.5 rounded-full">{{ $pendingOrdersCount }}</span>
                            @endif
                        </div>
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.users')" wire:navigate>
                        <span class="w-5 text-center">👥</span> <span>Usuarios</span>
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.products')" wire:navigate>
                        <span class="w-5 text-center">🏷️</span> <span>Productos</span>
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.settings')" wire:navigate>
                        <span class="w-5 text-center">⚙️</span> <span>Configuración</span>
                    </x-responsive-nav-link>
                @else
                    <x-responsive-nav-link :href="route('my-orders')" wire:navigate>
                        <span class="w-5 text-center">🛍</span> <span>Mis Órdenes</span>
                    </x-responsive-nav-link>
                @endif
                <x-responsive-nav-link :href="route('profile')" wire:navigate>
                    <span class="w-5 text-center">👤</span> <span>Mi Perfil</span>
                </x-responsive-nav-link>
                <div class="my-2 border-t border-slate-100 dark:border-slate-800/60 mx-4"></div>
                <button wire:click="logout" class="w-full text-start focus:outline-none">
                    <a class="block w-full pl-3 pr-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-gray-400 hover:text-white hover:bg-zinc-800 transition duration-150 ease-in-out cursor-pointer">
                        <span class="w-5 text-center inline-block">🚪</span> <span>Cerrar Sesión</span>
                    </a>
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
