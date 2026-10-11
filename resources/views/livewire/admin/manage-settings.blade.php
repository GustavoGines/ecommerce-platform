<?php

use App\Models\StoreSetting;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

new #[Layout('layouts.app')] class extends Component {
    use WithFileUploads;

    public $store_name = '';
    public $theme_name = 'modern-light';
    public $store_tagline = '';
    
    // Redes sociales individuales
    public $social_facebook = '';
    public $social_instagram = '';
    public $social_twitter = '';
    public $social_tiktok = '';
    public $social_whatsapp = '';

    // Credenciales de Mercado Pago
    public $mp_access_token = '';
    public $mp_public_key = '';

    // Integración Sistema POS Local
    public bool $pos_sync_enabled = false;
    public string $pos_api_token = '';
    public string $pos_webhook_url = '';
    public string $pos_webhook_secret = '';
    public string $pos_sync_on_event = 'order.created';
    public array $pos_logs = [];
    public ?string $pos_test_result = null;
    public bool $pos_test_success = false;

    public $favicon;
    public $current_favicon_url;
    public $logo;
    public $current_logo_url;

    public function mount()
    {
        $settings = StoreSetting::getSettings();
        if ($settings) {
            $this->store_name = $settings->store_name ?? '';
            $this->theme_name = $settings->theme_name ?? 'modern-light';
            
            $this->store_tagline = $settings->store_tagline ?? '';
            
            $social = is_array($settings->social_links) ? $settings->social_links : json_decode($settings->social_links ?? '{}', true);
            if (is_array($social)) {
                $this->social_facebook = $social['facebook'] ?? '';
                $this->social_instagram = $social['instagram'] ?? '';
                $this->social_twitter = $social['twitter'] ?? '';
                $this->social_tiktok = $social['tiktok'] ?? '';
                $this->social_whatsapp = $social['whatsapp'] ?? '';
            }

            $this->mp_access_token = $settings->mp_access_token ?? '';
            $this->mp_public_key = $settings->mp_public_key ?? '';

            $this->pos_sync_enabled = (bool) ($settings->pos_sync_enabled ?? false);
            $this->pos_api_token = $settings->pos_api_token ?? '';
            $this->pos_webhook_url = $settings->pos_webhook_url ?? '';
            $this->pos_webhook_secret = $settings->pos_webhook_secret ?? '';
            $this->pos_sync_on_event = $settings->pos_sync_on_event ?? 'order.created';
            $this->loadPosLogs();

            $this->current_favicon_url = $settings->favicon_url;
            $this->current_logo_url = $settings->logo_url;
        }
    }

    public function loadPosLogs()
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('pos_sync_logs')) {
            $this->pos_logs = \App\Models\PosSyncLog::latest()->take(6)->get()->toArray();
        }
    }

    public function generatePosApiToken()
    {
        $this->pos_api_token = 'pos_' . bin2hex(random_bytes(20));
    }

    public function generatePosWebhookSecret()
    {
        $this->pos_webhook_secret = 'sec_pos_' . bin2hex(random_bytes(16));
    }

    public function testPosWebhook()
    {
        if (empty($this->pos_webhook_url) || empty($this->pos_webhook_secret)) {
            $this->pos_test_result = 'Debes configurar la URL del Webhook y el Secret antes de realizar la prueba.';
            $this->pos_test_success = false;
            return;
        }

        try {
            $timestamp = time();
            $payload = [
                'event_type'   => 'ping',
                'event_id'     => 'evt_ping_' . $timestamp,
                'timestamp'    => $timestamp,
                'store_name'   => $this->store_name,
                'tenant_id'    => tenant('id'),
                'message'      => 'Prueba de conexión desde Ecommerce-Platform hacia Sistema POS',
            ];
            $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $signature = hash_hmac('sha256', $rawBody, $this->pos_webhook_secret);

            $response = \Illuminate\Support\Facades\Http::timeout(5)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Signature'  => $signature,
                    'X-Timestamp'  => (string) $timestamp,
                ])
                ->withBody($rawBody, 'application/json')
                ->post($this->pos_webhook_url);

            if ($response->successful()) {
                $this->pos_test_result = "¡Conexión exitosa! El POS respondió HTTP {$response->status()} OK.";
                $this->pos_test_success = true;
            } else {
                $this->pos_test_result = "El POS respondió con código HTTP {$response->status()}: " . \Illuminate\Support\Str::limit($response->body(), 120);
                $this->pos_test_success = false;
            }
        } catch (\Throwable $e) {
            $this->pos_test_result = "Error de red al conectar con el POS: " . $e->getMessage();
            $this->pos_test_success = false;
        }

        $this->loadPosLogs();
    }

    public function removeFavicon()
    {
        $settings = StoreSetting::getSettings();
        if ($settings && $settings->favicon_url) {
            Storage::disk('public')->delete($settings->favicon_url);
            $settings->favicon_url = null;
            $settings->save();
            $tenantId = tenant('id') ?? 'global';
            Cache::forget('store_settings_' . $tenantId); // BUG-04 FIX
            Cache::forget('store_settings');
        }
        $this->current_favicon_url = null;
        $this->favicon = null;
        session()->flash('message', 'Favicon eliminado correctamente.');
    }

    public function removeLogo()
    {
        $settings = StoreSetting::getSettings();
        if ($settings && $settings->logo_url) {
            Storage::disk('public')->delete($settings->logo_url);
            $settings->logo_url = null;
            $settings->save();
            $tenantId = tenant('id') ?? 'global';
            Cache::forget('store_settings_' . $tenantId); // BUG-04 FIX
            Cache::forget('store_settings');
        }
        $this->current_logo_url = null;
        $this->logo = null;
        session()->flash('message', 'Logo eliminado correctamente.');
    }

    public function validationAttributes()
    {
        return [
            'store_name' => 'nombre de la tienda',
            'theme_name' => 'tema de la tienda',
        ];
    }

    public function save()
    {
        $this->validate([
            'store_name'       => 'required|string|max:255',
            'theme_name'       => 'required|string|in:modern-light,tech-dark',
            'store_tagline'    => 'nullable|string|max:255',
            'social_facebook'  => 'nullable|url|max:255',
            'social_instagram' => 'nullable|url|max:255',
            'social_twitter'   => 'nullable|url|max:255',
            'social_tiktok'    => 'nullable|url|max:255',
            'social_whatsapp'  => 'nullable|string|max:50',
            'favicon'          => 'nullable|image|max:1024',
            'logo'             => 'nullable|image|max:2048',
            'mp_access_token'    => 'nullable|string',
            'mp_public_key'      => 'nullable|string',
            'pos_sync_enabled'   => 'nullable|boolean',
            'pos_api_token'      => 'nullable|string|max:255',
            'pos_webhook_url'    => 'nullable|url|max:255',
            'pos_webhook_secret' => 'nullable|string|max:255',
            'pos_sync_on_event'  => 'nullable|string|in:order.created,order.paid',
        ]);

        $settings = StoreSetting::first() ?? new StoreSetting();
        $settings->store_name    = $this->store_name;
        $settings->theme_name    = $this->theme_name;
        $settings->store_tagline = $this->store_tagline;
        
        $social = [
            'facebook'  => $this->social_facebook,
            'instagram' => $this->social_instagram,
            'twitter'   => $this->social_twitter,
            'tiktok'    => $this->social_tiktok,
            'whatsapp'  => $this->social_whatsapp,
        ];
        $settings->social_links = array_filter($social);
        
        $settings->mp_access_token = $this->mp_access_token;
        $settings->mp_public_key = $this->mp_public_key;

        $settings->pos_sync_enabled   = (bool) $this->pos_sync_enabled;
        $settings->pos_api_token      = $this->pos_api_token;
        $settings->pos_webhook_url    = $this->pos_webhook_url;
        $settings->pos_webhook_secret = $this->pos_webhook_secret;
        $settings->pos_sync_on_event  = $this->pos_sync_on_event;

        $settings->save();

        if ($this->favicon) {
            if ($settings->favicon_url) {
                Storage::disk('public')->delete($settings->favicon_url);
            }
            $path = $this->favicon->store('favicons', 'public');
            $settings->favicon_url = $path;
            $this->current_favicon_url = $path;
            $this->favicon = null;
        }

        if ($this->logo) {
            // Eliminar el logo viejo del disco antes de subir el nuevo
            if ($settings->logo_url) {
                Storage::disk('public')->delete($settings->logo_url);
            }
            $path = $this->logo->store('logos', 'public');
            $settings->logo_url = $path;
            $this->current_logo_url = $path;
            $this->logo = null;
        }

        $settings->save();
        $tenantId = tenant('id') ?? 'global';
        Cache::forget('store_settings_' . $tenantId); // BUG-04 FIX: invalidar caché al guardar
        Cache::forget('store_settings'); // Por si acaso también borramos el global viejo
        session()->flash('message', 'Configuración guardada exitosamente.');
        
        // Redirigir (recargar) la página sin 'navigate' para que los cambios de layout se apliquen instantáneamente.
        $this->redirect(route('admin.settings'), navigate: false);
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-100 leading-tight">
            {{ __('Configuración de la Tienda') }}
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
        <div class="bg-white/80 dark:bg-gray-800/40 backdrop-blur-md border border-gray-200 dark:border-gray-700/50 shadow-xl dark:shadow-2xl overflow-hidden sm:rounded-3xl p-8 transition-colors duration-300" :style="$store.theme.dark ? 'box-shadow: 0 10px 30px -10px var(--color-primary-glow);' : ''">
            @if (session()->has('message'))
                <div class="mb-6 bg-green-100 dark:bg-green-500/20 border border-green-400 dark:border-green-500/30 text-green-700 dark:text-green-400 px-4 py-3 rounded relative backdrop-blur-sm transition-colors duration-300">
                    {{ session('message') }}
                </div>
            @endif

            <form wire:submit="save">
                <div class="mb-5">
                    <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2 uppercase tracking-wider transition-colors" for="store_name">
                        Nombre de la Tienda
                    </label>
                    <input wire:model="store_name" id="store_name" type="text" class="w-full py-3 px-4 bg-gray-50 dark:bg-gray-900/80 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-900 dark:text-gray-100 leading-tight focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent transition-all shadow-sm dark:shadow-none">
                    @error('store_name') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="mb-5">
                    <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2 uppercase tracking-wider transition-colors" for="theme_name">
                        Tema de la Tienda
                    </label>
                    <select wire:model="theme_name" id="theme_name" class="w-full py-3 px-4 bg-gray-50 dark:bg-gray-900/80 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-900 dark:text-gray-100 leading-tight focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent transition-all shadow-sm dark:shadow-none">
                        <option value="modern-light">Modern Light (Tema Claro - JCG)</option>
                        <option value="tech-dark">Tech Dark (Tema Oscuro - G3)</option>
                    </select>
                    @error('theme_name') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="mb-5">
                    <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2 uppercase tracking-wider transition-colors" for="store_tagline">
                        Eslogan de la Tienda
                    </label>
                    <input wire:model="store_tagline" id="store_tagline" type="text" class="w-full py-3 px-4 bg-gray-50 dark:bg-gray-900/80 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-900 dark:text-gray-100 leading-tight focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent transition-all shadow-sm dark:shadow-none">
                    @error('store_tagline') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                {{-- SECCIÓN FAVICON --}}
                <div class="mb-8 p-6 bg-gray-50/50 dark:bg-gray-800/20 rounded-2xl border border-gray-100 dark:border-gray-700/50">
                    <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-3 uppercase tracking-wider transition-colors">
                        Ícono de Pestaña (Favicon)
                    </label>

                    @if($current_favicon_url)
                        <div class="flex items-center gap-4 mb-4 p-3 bg-white dark:bg-gray-900/50 rounded-xl border border-gray-200 dark:border-gray-700">
                            <img src="{{ tenant_asset($current_favicon_url) }}"
                                 alt="Favicon Actual"
                                 class="h-10 w-10 object-contain rounded bg-gray-100 dark:bg-gray-800 p-1 border border-gray-200 dark:border-gray-600">
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Favicon actual</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Se muestra en las pestañas del navegador</p>
                            </div>
                            <button type="button"
                                    wire:click="removeFavicon"
                                    wire:confirm="¿Estás seguro de que querés eliminar el favicon?"
                                    wire:loading.attr="disabled"
                                    class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 border border-red-200 dark:border-red-500/30 rounded-lg transition-all">
                                Quitar
                            </button>
                        </div>
                    @endif

                    @if($favicon)
                        <div class="flex items-center gap-3 mb-3 p-3 bg-blue-50 dark:bg-blue-500/10 rounded-xl border border-blue-200 dark:border-blue-500/30">
                            <img src="{{ $favicon->temporaryUrl() }}"
                                 alt="Preview nuevo favicon"
                                 class="h-10 w-10 object-contain rounded bg-white dark:bg-gray-800 p-1">
                            <div>
                                <p class="text-sm font-medium text-blue-700 dark:text-blue-400">Nuevo favicon listo</p>
                            </div>
                        </div>
                    @endif

                    <input wire:model="favicon"
                           id="favicon"
                           type="file"
                           accept="image/*"
                           class="w-full py-3 px-4 bg-white dark:bg-gray-900/80 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-700 dark:text-gray-300 leading-tight focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] transition-all shadow-sm file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-gray-200 dark:file:bg-gray-800 file:text-gray-700 dark:file:text-gray-300 hover:file:bg-gray-300 dark:hover:file:bg-gray-700 cursor-pointer">
                    @error('favicon') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    <div wire:loading wire:target="favicon" class="text-sm text-gray-500 mt-2">Cargando imagen...</div>
                </div>

                {{-- SECCIÓN REDES SOCIALES --}}
                <div class="mb-8 p-6 bg-gray-50/50 dark:bg-gray-800/20 rounded-2xl border border-gray-100 dark:border-gray-700/50">
                    <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-4 uppercase tracking-wider">
                        Redes Sociales y Contacto
                    </label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1" for="social_whatsapp">WhatsApp (Solo números con código de país)</label>
                            <input wire:model="social_whatsapp" id="social_whatsapp" type="text" class="w-full py-2.5 px-3 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg text-sm" placeholder="Ej: 5493705075839">
                            @error('social_whatsapp') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1" for="social_instagram">Instagram</label>
                            <input wire:model="social_instagram" id="social_instagram" type="url" class="w-full py-2.5 px-3 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg text-sm" placeholder="https://instagram.com/tutienda">
                            @error('social_instagram') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1" for="social_facebook">Facebook</label>
                            <input wire:model="social_facebook" id="social_facebook" type="url" class="w-full py-2.5 px-3 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg text-sm" placeholder="https://facebook.com/tutienda">
                            @error('social_facebook') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1" for="social_tiktok">TikTok</label>
                            <input wire:model="social_tiktok" id="social_tiktok" type="url" class="w-full py-2.5 px-3 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg text-sm" placeholder="https://tiktok.com/@tutienda">
                            @error('social_tiktok') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                {{-- SECCIÓN LOGO --}}
                <div class="mb-8">
                    <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-3 uppercase tracking-wider transition-colors">
                        Logo de la Tienda
                    </label>

                    {{-- Logo actual --}}
                    @if($current_logo_url)
                        <div class="flex items-center gap-4 mb-4 p-3 bg-gray-50 dark:bg-gray-900/50 rounded-xl border border-gray-200 dark:border-gray-700">
                            <img src="{{ tenant_asset($current_logo_url) }}"
                                 alt="Logo Actual"
                                 class="h-16 w-auto object-contain rounded-lg bg-white dark:bg-gray-800 p-1 border border-gray-200 dark:border-gray-600">
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Logo actual</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Seleccioná un nuevo archivo para reemplazarlo</p>
                            </div>
                            <button type="button"
                                    wire:click="removeLogo"
                                    wire:confirm="¿Estás seguro de que querés eliminar el logo?"
                                    wire:loading.attr="disabled"
                                    class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 border border-red-200 dark:border-red-500/30 rounded-lg transition-all">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                Quitar logo
                            </button>
                        </div>
                    @endif

                    {{-- Preview del nuevo logo seleccionado (antes de guardar) --}}
                    @if($logo)
                        <div class="flex items-center gap-3 mb-3 p-3 bg-blue-50 dark:bg-blue-500/10 rounded-xl border border-blue-200 dark:border-blue-500/30">
                            <img src="{{ $logo->temporaryUrl() }}"
                                 alt="Preview nuevo logo"
                                 class="h-14 w-auto object-contain rounded-lg bg-white dark:bg-gray-800 p-1">
                            <div>
                                <p class="text-sm font-medium text-blue-700 dark:text-blue-400">Nuevo logo listo para guardar</p>
                                <p class="text-xs text-blue-500 dark:text-blue-500 mt-0.5">Hacé clic en "Guardar" para confirmar el cambio</p>
                            </div>
                        </div>
                    @endif

                    {{-- Input file --}}
                    <input wire:model="logo"
                           id="logo"
                           type="file"
                           accept="image/*"
                           class="w-full py-3 px-4 bg-gray-50 dark:bg-gray-900/80 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-700 dark:text-gray-300 leading-tight focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] transition-all shadow-sm dark:shadow-none file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-gray-200 dark:file:bg-gray-800 file:text-gray-700 dark:file:text-gray-300 hover:file:bg-gray-300 dark:hover:file:bg-gray-700 cursor-pointer">
                    @error('logo') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    <div wire:loading wire:target="logo" class="text-sm text-gray-500 dark:text-gray-400 mt-2 flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        Procesando imagen...
                    </div>
                </div>

                <!-- Configuración Mercado Pago -->
                <div class="bg-white dark:bg-gray-800 shadow sm:rounded-xl mb-8 border border-gray-200 dark:border-gray-700">
                    <div class="px-4 py-5 sm:p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg leading-6 font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                            Integración con Mercado Pago
                        </h3>
                        <div class="mt-2 max-w-xl text-sm text-gray-500 dark:text-gray-400">
                            <p>Ingresá tus credenciales de Mercado Pago. Si dejás esto vacío, el botón de Mercado Pago no aparecerá en el Checkout y el único medio será coordinar por WhatsApp.</p>
                        </div>
                    </div>
                    <div class="px-4 py-5 sm:p-6">
                        <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                            <div class="sm:col-span-6">
                                <label for="mp_access_token" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Access Token
                                </label>
                                <input type="password" wire:model="mp_access_token" id="mp_access_token" class="w-full py-2.5 px-4 bg-gray-50 dark:bg-gray-900/80 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-700 dark:text-gray-300 leading-tight focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] transition-all shadow-sm dark:shadow-none" placeholder="APP_USR-...">
                                @error('mp_access_token') <p class="mt-2 text-sm font-bold text-red-500">{{ $message }}</p> @enderror
                            </div>

                            <div class="sm:col-span-6">
                                <label for="mp_public_key" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Public Key
                                </label>
                                <input type="text" wire:model="mp_public_key" id="mp_public_key" class="w-full py-2.5 px-4 bg-gray-50 dark:bg-gray-900/80 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-700 dark:text-gray-300 leading-tight focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] transition-all shadow-sm dark:shadow-none" placeholder="APP_USR-...">
                                @error('mp_public_key') <p class="mt-2 text-sm font-bold text-red-500">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Configuración Sistema POS Local -->
                <div class="bg-white dark:bg-gray-800 shadow sm:rounded-xl mb-8 border border-gray-200 dark:border-gray-700">
                    <div class="px-4 py-5 sm:p-6 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div>
                                <h3 class="text-lg leading-6 font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                    <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    Integración con Sistema POS Local (Tienda Física)
                                </h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Sincronización bidireccional automática de inventario y órdenes con el Sistema POS local. Descuenta stock físico ante compras web y actualiza el stock disponible ante ventas en mostrador.
                                </p>
                            </div>

                            <!-- Botón Interruptor Toggle Diferenciado (ON/OFF) -->
                            <div class="flex items-center gap-3 shrink-0">
                                <button type="button"
                                        wire:click="$toggle('pos_sync_enabled')"
                                        class="group relative inline-flex h-8 w-14 shrink-0 cursor-pointer rounded-full p-1 transition-all duration-300 ease-in-out focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-gray-900 shadow-inner"
                                        style="background-color: {{ $pos_sync_enabled ? '#10b981' : '#374151' }}; border: 1px solid {{ $pos_sync_enabled ? '#059669' : '#4b5563' }};"
                                        role="switch"
                                        aria-checked="{{ $pos_sync_enabled ? 'true' : 'false' }}"
                                        title="Haga clic para {{ $pos_sync_enabled ? 'desactivar' : 'activar' }} la sincronización con el POS">
                                    
                                    <!-- Bolita deslizante con sombra e icono -->
                                    <span class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow-lg ring-0 transition-all duration-300 ease-in-out flex items-center justify-center"
                                          style="transform: translateX({{ $pos_sync_enabled ? '24px' : '0px' }});">
                                        @if($pos_sync_enabled)
                                            <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                        @else
                                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        @endif
                                    </span>
                                </button>

                                <!-- Etiqueta / Badge de estado con color vivo y punto animado -->
                                <button type="button" 
                                        wire:click="$toggle('pos_sync_enabled')" 
                                        class="cursor-pointer select-none inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-black tracking-wide uppercase transition-all duration-200 {{ $pos_sync_enabled ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/40 shadow-sm shadow-emerald-500/20' : 'bg-gray-800 text-gray-400 border border-gray-700' }}">
                                    <span class="w-2 h-2 rounded-full {{ $pos_sync_enabled ? 'bg-emerald-400 animate-pulse' : 'bg-gray-500' }}"></span>
                                    {{ $pos_sync_enabled ? 'ACTIVADO' : 'DESACTIVADO' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="px-4 py-5 sm:p-6 space-y-6">
                        <!-- Parámetros para cargar en el Sistema POS -->
                        <div class="p-4 bg-indigo-50/50 dark:bg-indigo-950/20 rounded-xl border border-indigo-100 dark:border-indigo-900/50">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-indigo-700 dark:text-indigo-400 mb-3 flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                Parámetros para vincular en los Ajustes del Sistema POS
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                                <div class="p-2.5 bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800">
                                    <span class="text-gray-500 block">URL Base del E-Commerce:</span>
                                    <code class="text-indigo-600 dark:text-indigo-400 font-mono font-bold select-all">{{ request()->getSchemeAndHttpHost() }}</code>
                                </div>
                                <div class="p-2.5 bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800">
                                    <span class="text-gray-500 block">Store ID / Tenant ID:</span>
                                    <code class="text-indigo-600 dark:text-indigo-400 font-mono font-bold select-all">{{ tenant('id') ?? 'g3' }}</code>
                                </div>
                                <div class="p-2.5 bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800">
                                    <span class="text-gray-500 block">Endpoint Health (Estado):</span>
                                    <code class="text-indigo-600 dark:text-indigo-400 font-mono font-bold select-all">{{ url('/api/v1/health') }}</code>
                                </div>
                                <div class="p-2.5 bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800">
                                    <span class="text-gray-500 block">Endpoint Actualización Stock:</span>
                                    <code class="text-indigo-600 dark:text-indigo-400 font-mono font-bold select-all">{{ url('/api/v1/inventory/update') }}</code>
                                </div>
                            </div>
                        </div>

                        <!-- Credenciales y Configuración de Webhook -->
                        <div class="grid grid-cols-1 gap-y-5 gap-x-4 sm:grid-cols-6">
                            <!-- URL de Destino del Webhook (Web ➔ POS) -->
                            <div class="sm:col-span-6">
                                <label for="pos_webhook_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    URL de Webhook del POS (Ruta a donde la web avisará las ventas)
                                </label>
                                <input type="url" wire:model="pos_webhook_url" id="pos_webhook_url" class="w-full py-2.5 px-4 bg-gray-50 dark:bg-gray-900/80 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-700 dark:text-gray-300 leading-tight focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] transition-all shadow-sm dark:shadow-none" placeholder="https://<URL_DEL_POS>/api/webhooks/ecommerce/ecommerce_platform">
                                @error('pos_webhook_url') <p class="mt-1 text-xs font-bold text-red-500">{{ $message }}</p> @enderror
                            </div>

                            <!-- Webhook Secret (HMAC-SHA256) -->
                            <div class="sm:col-span-6">
                                <div class="flex items-center justify-between mb-1">
                                    <label for="pos_webhook_secret" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Webhook Secret (Clave Criptográfica HMAC-SHA256)
                                    </label>
                                    <button type="button" wire:click="generatePosWebhookSecret" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
                                        Generar nueva clave
                                    </button>
                                </div>
                                <input type="text" wire:model="pos_webhook_secret" id="pos_webhook_secret" class="w-full py-2.5 px-4 font-mono text-sm bg-gray-50 dark:bg-gray-900/80 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-700 dark:text-gray-300 leading-tight focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] transition-all shadow-sm dark:shadow-none" placeholder="sec_pos_...">
                                @error('pos_webhook_secret') <p class="mt-1 text-xs font-bold text-red-500">{{ $message }}</p> @enderror
                            </div>

                            <!-- API Access Token (POS ➔ Web) -->
                            <div class="sm:col-span-6">
                                <div class="flex items-center justify-between mb-1">
                                    <label for="pos_api_token" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        API Access Token (Token Bearer que usará el POS para actualizar stock)
                                    </label>
                                    <button type="button" wire:click="generatePosApiToken" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
                                        Generar nuevo token
                                    </button>
                                </div>
                                <input type="text" wire:model="pos_api_token" id="pos_api_token" class="w-full py-2.5 px-4 font-mono text-sm bg-gray-50 dark:bg-gray-900/80 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-700 dark:text-gray-300 leading-tight focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] transition-all shadow-sm dark:shadow-none" placeholder="pos_...">
                                @error('pos_api_token') <p class="mt-1 text-xs font-bold text-red-500">{{ $message }}</p> @enderror
                            </div>

                            <!-- Disparador de Webhook -->
                            <div class="sm:col-span-3">
                                <label for="pos_sync_on_event" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Disparador de Webhook
                                </label>
                                <select wire:model="pos_sync_on_event" id="pos_sync_on_event" class="w-full py-2.5 px-4 bg-gray-50 dark:bg-gray-900/80 border border-gray-300 dark:border-gray-700 rounded-xl text-gray-700 dark:text-gray-300 leading-tight focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] transition-all shadow-sm dark:shadow-none">
                                    <option value="order.created">Al confirmar el pedido (order.created)</option>
                                    <option value="order.paid">Al acreditarse el pago (order.paid)</option>
                                </select>
                            </div>

                            <!-- Botón de Prueba de Conexión -->
                            <div class="sm:col-span-3 flex items-end">
                                <button type="button" wire:click="testPosWebhook" wire:loading.attr="disabled" class="w-full py-2.5 px-4 rounded-xl border border-indigo-300 dark:border-indigo-700 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 font-semibold text-sm transition-all flex items-center justify-center gap-2">
                                    <span wire:loading.remove wire:target="testPosWebhook">Probar Conexión al POS</span>
                                    <span wire:loading wire:target="testPosWebhook" class="flex items-center gap-1.5">
                                        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                        Probando conexión...
                                    </span>
                                </button>
                            </div>
                        </div>

                        <!-- Alerta con Resultado de Prueba -->
                        @if($pos_test_result)
                            <div class="p-3.5 rounded-xl border {{ $pos_test_success ? 'bg-emerald-50 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300' : 'bg-red-50 dark:bg-red-950/20 border-red-200 dark:border-red-800 text-red-800 dark:text-red-300' }} text-sm flex items-start gap-2">
                                <span class="font-bold">{{ $pos_test_success ? 'Éxito:' : 'Aviso:' }}</span>
                                <span>{{ $pos_test_result }}</span>
                            </div>
                        @endif

                        <!-- Tabla de Logs Recientes -->
                        @if(!empty($pos_logs))
                            <div class="mt-6 pt-5 border-t border-gray-200 dark:border-gray-700">
                                <h4 class="text-sm font-bold text-gray-900 dark:text-gray-100 mb-3 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Últimos Eventos de Sincronización POS
                                </h4>
                                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-xs text-left">
                                        <thead class="bg-gray-50 dark:bg-gray-900/60 text-gray-500">
                                            <tr>
                                                <th class="px-3 py-2">Fecha</th>
                                                <th class="px-3 py-2">Dirección</th>
                                                <th class="px-3 py-2">Evento</th>
                                                <th class="px-3 py-2">Estado</th>
                                                <th class="px-3 py-2">Detalle</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                            @foreach($pos_logs as $log)
                                                <tr>
                                                    <td class="px-3 py-2 whitespace-nowrap">{{ \Carbon\Carbon::parse($log['created_at'])->format('d/m H:i:s') }}</td>
                                                    <td class="px-3 py-2 font-mono uppercase">{{ $log['direction'] === 'inbound' ? 'POS ➔ Web' : 'Web ➔ POS' }}</td>
                                                    <td class="px-3 py-2 font-mono">{{ $log['event_type'] }}</td>
                                                    <td class="px-3 py-2">
                                                        <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold {{ $log['status'] === 'success' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300' }}">
                                                            {{ strtoupper($log['status']) }}
                                                        </span>
                                                    </td>
                                                    <td class="px-3 py-2 max-w-xs truncate text-gray-500 dark:text-gray-400">
                                                        {{ $log['error_message'] ?: ($log['response_code'] ? 'HTTP ' . $log['response_code'] : 'OK') }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-center justify-end">
                    <button class="w-full sm:w-auto text-white font-bold py-3 px-8 rounded-full transition-all hover:opacity-90 shadow-lg hover:shadow-xl hover:-translate-y-0.5" style="background-color: var(--color-primary); box-shadow: 0 4px 14px 0 var(--color-primary-glow);" type="submit">
                        Guardar Configuración
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
