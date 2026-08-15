<?php

use Livewire\Volt\Component;

new class extends Component {
    public $theme = 'stealth';
    public $storeName = 'Premium Hardware';
    public $tagline = 'El mayor catálogo de controles remotos y electrónica. Ventas por mayor y menor.';
    public $social = [];

    public function mount()
    {
        $settings = \App\Models\StoreSetting::getSettings();
        if ($settings) {
            $this->theme = $settings->theme_name ?? 'stealth';
            $this->storeName = $settings->store_name ?? 'Premium Hardware';
            
            // Si es JCG usa el del setting (controles), sino usa la de hardware (G3)
            $this->tagline = ($this->theme === 'modern-light') 
                ? ($settings->store_tagline ?? 'El mayor catálogo de controles remotos y electrónica. Ventas por mayor y menor.')
                : 'Los mejores precios en hardware y tecnología.';
                
            $this->social = is_array($settings->social_links) ? $settings->social_links : json_decode($settings->social_links ?? '{}', true);
            
            // Si es G3, quitarle el 549 inicial al whatsapp
            if ($this->theme !== 'modern-light' && isset($this->social['whatsapp'])) {
                if (str_starts_with($this->social['whatsapp'], '549')) {
                    $this->social['whatsapp'] = substr($this->social['whatsapp'], 3);
                }
            }
        }
    }
}; ?>

<footer class="w-full mt-auto relative {{ $theme === 'luxury' ? 'bg-[#030712] border-t border-white/5' : 'bg-gray-950 text-gray-400' }} transition-colors duration-300">
    @if($theme === 'luxury')
        @include('themes.luxury.footer')
    @else
        {{-- ==========================================
             MODERN / STEALTH THEME FOOTER
             ========================================== --}}
        
        {{-- 1. Franja de Confianza (Trust Banner) Superior --}}
        <div class="border-t border-gray-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 overflow-hidden">
            <div class="max-w-7xl mx-auto px-0 sm:px-6 lg:px-8 py-6 md:py-12">
                {{-- Contenedor con Scroll Horizontal en móvil (Swipeable) --}}
                <div class="flex md:grid md:grid-cols-3 gap-4 md:gap-8 overflow-x-auto snap-x snap-mandatory hide-scrollbar px-4 sm:px-0 text-center md:divide-x divide-gray-200 dark:divide-slate-700">
                    
                    {{-- Bloque 1: Retiro en Local --}}
                    <div class="flex flex-col items-center justify-center p-4 min-w-[250px] snap-center bg-white dark:bg-zinc-900 md:bg-transparent rounded-2xl md:rounded-none shadow-sm md:shadow-none border md:border-none border-gray-100 dark:border-zinc-800">
                        <div class="w-10 h-10 md:w-12 md:h-12 rounded-full bg-red-100 dark:bg-red-900/30 text-[var(--color-primary)] flex items-center justify-center mb-3 md:mb-4">
                            <svg class="w-5 h-5 md:w-6 md:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                        <h4 class="text-xs md:text-sm font-black text-gray-900 dark:text-white uppercase tracking-widest mb-1">Retiro por Sucursal</h4>
                        <p class="text-[10px] md:text-xs text-gray-500 dark:text-gray-400 font-medium leading-tight">Comprá online y pasá a retirar por nuestro local al instante.</p>
                    </div>

                    {{-- Bloque 2: WhatsApp --}}
                    @if(isset($this->social['whatsapp']) && !empty($this->social['whatsapp']))
                        @php
                            $waNumber = preg_replace('/[^0-9]/', '', $this->social['whatsapp']);
                            $waLink = $waNumber ? 'https://wa.me/' . $waNumber : '#';
                        @endphp
                        <div class="flex flex-col items-center justify-center p-4 min-w-[250px] snap-center bg-white dark:bg-zinc-900 md:bg-transparent rounded-2xl md:rounded-none shadow-sm md:shadow-none border md:border-none border-gray-100 dark:border-zinc-800">
                            <div class="w-10 h-10 md:w-12 md:h-12 rounded-full bg-green-100 dark:bg-green-900/30 text-green-600 flex items-center justify-center mb-3 md:mb-4">
                                <svg class="w-5 h-5 md:w-6 md:h-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 00-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                </svg>
                            </div>
                            <h4 class="text-xs md:text-sm font-black text-gray-900 dark:text-white uppercase tracking-widest mb-1">¿Dudas? Consultanos</h4>
                            <a href="{{ $waLink }}" target="_blank" class="text-[10px] md:text-xs font-bold text-green-600 hover:text-green-700 hover:underline">
                                {{ $this->social['whatsapp'] }}
                            </a>
                        </div>
                    @endif

                    {{-- Bloque 3: Compra Segura --}}
                    <div class="flex flex-col items-center justify-center p-4 min-w-[250px] snap-center bg-white dark:bg-zinc-900 md:bg-transparent rounded-2xl md:rounded-none shadow-sm md:shadow-none border md:border-none border-gray-100 dark:border-zinc-800">
                        <div class="w-10 h-10 md:w-12 md:h-12 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 flex items-center justify-center mb-3 md:mb-4">
                            <svg class="w-5 h-5 md:w-6 md:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <h4 class="text-xs md:text-sm font-black text-gray-900 dark:text-white uppercase tracking-widest mb-1">Compra 100% Segura</h4>
                        <p class="text-[10px] md:text-xs text-gray-500 dark:text-gray-400 font-medium leading-tight">Protegemos tus datos y garantizamos tu reserva.</p>
                    </div>

                </div>
            </div>
            
            {{-- Estilo para ocultar la barra de scroll horizontal en Tailwind --}}
            <style>
                .hide-scrollbar::-webkit-scrollbar { display: none; }
                .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
            </style>
        </div>

        {{-- 2. Footer Principal --}}
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-16">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-10 lg:gap-8">
                
                {{-- Columna 1: Brand Info --}}
                <div class="lg:col-span-1 flex flex-col items-center md:items-start text-center md:text-left">
                    <div class="mb-6 w-full flex justify-center md:justify-start items-center">
                        @php $settings = \App\Models\StoreSetting::getSettings(); @endphp
                        @if(isset($settings) && $settings->logo_url)
                            @if($theme === 'modern-light')
                                <img src="{{ tenant_asset($settings->logo_url) }}" alt="Logo" class="pointer-events-none drop-shadow-md" style="width: 220px; height: auto;">
                            @else
                                <img src="{{ tenant_asset($settings->logo_url) }}" alt="Logo" class="pointer-events-none drop-shadow-md h-16 sm:h-20 w-auto object-contain">
                            @endif
                        @else
                            <div class="flex items-center gap-2">
                                <svg class="w-6 h-6 text-red-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L2 22h20L12 2zm0 4.5l6.5 13h-13L12 6.5z"/></svg>
                                <h4 class="text-xl font-black text-gray-100 dark:text-gray-300 tracking-tight">{{ $storeName }}</h4>
                            </div>
                        @endif
                    </div>
                    <p class="text-gray-400 text-xs md:text-sm mb-6 leading-relaxed">
                        {{ $tagline }}
                    </p>
                </div>

                {{-- Columna 2: Navegación --}}
                <div class="text-center md:text-left">
                    <h5 class="text-[10px] font-black text-gray-100 uppercase tracking-widest mb-4">Navegación</h5>
                    <ul class="grid grid-cols-2 gap-y-3 gap-x-4 text-left inline-grid md:grid">
                        <li><a href="{{ route('home') }}" wire:navigate class="text-gray-400 hover:text-white text-sm font-medium transition-colors">Inicio</a></li>
                        <li><a href="{{ route('shop') }}" wire:navigate class="text-gray-400 hover:text-white text-sm font-medium transition-colors">Tienda</a></li>
                        @auth
                            @if(optional(auth()->user())->isAdmin())
                                <li><a href="{{ route('admin.dashboard') }}" wire:navigate class="text-gray-400 hover:text-white text-sm font-medium transition-colors">Panel</a></li>
                                <li><a href="{{ route('admin.orders') }}" wire:navigate class="text-gray-400 hover:text-white text-sm font-medium transition-colors">Gestión de Órdenes</a></li>
                                <li><a href="{{ route('admin.products') }}" wire:navigate class="text-gray-400 hover:text-white text-sm font-medium transition-colors">Productos</a></li>
                                <li><a href="{{ route('admin.users') }}" wire:navigate class="text-gray-400 hover:text-white text-sm font-medium transition-colors">Usuarios</a></li>
                                <li><a href="{{ route('admin.settings') }}" wire:navigate class="text-gray-400 hover:text-white text-sm font-medium transition-colors">Configuración</a></li>
                            @else
                                <li><a href="{{ route('my-orders') }}" wire:navigate class="text-gray-400 hover:text-white text-sm font-medium transition-colors">Mis Órdenes</a></li>
                                <li><a href="{{ route('profile') }}" wire:navigate class="text-gray-400 hover:text-white text-sm font-medium transition-colors">Mi Perfil</a></li>
                            @endif
                        @else
                            <li><a href="{{ route('login') }}" wire:navigate class="text-gray-400 hover:text-white text-sm font-medium transition-colors">Iniciar Sesión</a></li>
                            <li><a href="{{ route('register') }}" wire:navigate class="text-gray-400 hover:text-white text-sm font-medium transition-colors">Registrarse</a></li>
                        @endauth
                    </ul>
                </div>

                {{-- Columna 3: Medios de Pago --}}
                <div class="text-center md:text-left">
                    <h5 class="text-[10px] font-black text-gray-100 uppercase tracking-widest mb-4">Medios de Pago</h5>
                    <div class="flex flex-wrap justify-center md:justify-start gap-2">
                        <span class="px-3 py-1.5 bg-white/5 border border-white/10 text-gray-300 text-xs font-bold rounded-lg shadow-sm backdrop-blur-sm">Efectivo</span>
                        <span class="px-3 py-1.5 bg-white/5 border border-white/10 text-gray-300 text-xs font-bold rounded-lg shadow-sm backdrop-blur-sm">Transferencia</span>
                        <span class="px-3 py-1.5 bg-white/5 border border-white/10 text-[#009EE3] text-xs font-bold rounded-lg shadow-sm backdrop-blur-sm">MercadoPago</span>
                    </div>
                    <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-4 italic">* Pagos y entregas se coordinan por WhatsApp.</p>
                </div>

                {{-- Columna 4: Contacto --}}
                <div class="text-center md:text-left">
                    <h5 class="text-[10px] font-black text-gray-100 uppercase tracking-widest mb-4">Contacto</h5>
                    <div class="flex flex-row flex-wrap justify-center md:justify-start gap-4 mb-6">
                        @if(isset($this->social['whatsapp']) && !empty($this->social['whatsapp']))
                            @php
                                $waNumber = preg_replace('/[^0-9]/', '', $this->social['whatsapp']);
                                $waLink = $waNumber ? 'https://wa.me/' . $waNumber : '#';
                            @endphp
                            <a href="{{ $waLink }}" target="_blank" class="flex items-center gap-2 text-gray-400 hover:text-green-400 transition-colors group">
                                <div class="w-8 h-8 shrink-0 rounded-full bg-white/5 border border-white/10 group-hover:bg-green-500/20 group-hover:border-green-500/30 flex items-center justify-center transition-colors">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 00-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                                </div>
                                <div class="text-left">
                                    <span class="block text-[9px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">WhatsApp</span>
                                    <span class="text-xs sm:text-sm font-bold text-gray-300">{{ $this->social['whatsapp'] }}</span>
                                </div>
                            </a>
                        @endif
                        @php
                            $contactEmail = ($theme === 'modern-light') ? 'jcgelectronicaoficial@gmail.com' : '3.gines@gmail.com';
                        @endphp
                        <a href="mailto:{{ $contactEmail }}" class="flex items-center gap-2 text-gray-400 hover:text-red-400 transition-colors group">
                            <div class="w-8 h-8 shrink-0 rounded-full bg-white/5 border border-white/10 group-hover:bg-red-500/20 group-hover:border-red-500/30 flex items-center justify-center transition-colors">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                            </div>
                            <div class="text-left">
                                <span class="block text-[9px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Email</span>
                                <span class="text-xs sm:text-sm font-bold text-gray-300">{{ $contactEmail }}</span>
                            </div>
                        </a>
                    </div>
                </div>

                {{-- Columna 5: Redes Sociales --}}
                @if((isset($this->social['instagram']) && !empty($this->social['instagram'])) || (isset($this->social['facebook']) && !empty($this->social['facebook'])) || (isset($this->social['tiktok']) && !empty($this->social['tiktok'])))
                    <div class="text-center md:text-left">
                        <h5 class="text-[10px] font-black text-gray-100 uppercase tracking-widest mb-4">Seguinos en redes</h5>
                        <div class="flex items-center justify-center md:justify-start gap-3">
                            @if(isset($this->social['instagram']) && !empty($this->social['instagram']))
                                <a href="{{ $this->social['instagram'] }}" target="_blank" title="Instagram" class="w-10 h-10 rounded-full bg-white/10 dark:bg-zinc-900/50 border border-white/10 text-gray-400 hover:bg-gradient-to-tr hover:from-yellow-400 hover:via-pink-500 hover:to-purple-600 hover:text-gray-100 dark:text-gray-300 hover:border-transparent flex items-center justify-center transition-all duration-300 shadow-sm">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                                </a>
                            @endif
                            @if(isset($this->social['facebook']) && !empty($this->social['facebook']))
                                <a href="{{ $this->social['facebook'] }}" target="_blank" title="Facebook" class="w-10 h-10 rounded-full bg-white/10 dark:bg-zinc-900/50 border border-white/10 text-gray-400 hover:bg-[#1877F2] hover:text-gray-100 dark:text-gray-300 hover:border-[#1877F2] flex items-center justify-center transition-all duration-300 shadow-sm">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.469h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.469h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                </a>
                            @endif
                            @if(isset($this->social['tiktok']) && !empty($this->social['tiktok']))
                                <a href="{{ $this->social['tiktok'] }}" target="_blank" title="TikTok" class="w-10 h-10 rounded-full bg-white/10 dark:bg-zinc-900/50 border border-white/10 text-gray-400 hover:bg-white dark:bg-zinc-900 hover:text-black hover:border-white flex items-center justify-center transition-all duration-300 shadow-sm">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>
                                </a>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-8">
                <div class="pt-8 border-t border-white/10 flex flex-col md:flex-row items-center justify-between gap-4 text-center md:text-left">
                    <p class="text-gray-500 dark:text-gray-400 text-xs font-medium">
                        &copy; {{ date('Y') }} {{ $storeName }}. Todos los derechos reservados.
                    </p>
                    <p class="text-gray-600 dark:text-gray-300 text-[10px]">
                        Desarrollado por
                        <a href="https://portfolio-two-smoky-75.vercel.app/" target="_blank" rel="noopener noreferrer"
                           class="text-gray-400 hover:text-gray-100 dark:text-gray-300 transition-colors duration-200 font-semibold">
                            Gustavo Ginés
                        </a>
                    </p>
                </div>
            </div>
        </div>

    @endif
</footer>
