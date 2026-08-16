<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;
    public string $turnstileToken = '';

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        if (config('services.turnstile.enabled')) {
            $this->validate([
                'turnstileToken' => ['required', function ($attribute, $value, $fail) {
                    $response = \Illuminate\Support\Facades\Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                        'secret' => config('services.turnstile.secret_key'),
                        'response' => $value,
                        'remoteip' => request()->ip(),
                    ]);
                    if (!$response->json('success')) {
                        $fail('Verificación de seguridad fallida. Por favor recarga e intenta nuevamente.');
                    }
                }],
            ]);
        }

        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('home'), navigate: true);
    }
}; ?>

<div>
    <div class="mb-5 text-center">
        <h2 class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">Bienvenido de vuelta</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ingresa tus credenciales para acceder a tu cuenta.</p>
    </div>

    {{-- ═══════════════════════════════════════════════════════════
         BANNER: Cuenta Suspendida
         Solo aparece cuando LoginForm lanza 'form.banned'.
         Diseño acorde al tema modern-light: tarjeta blanca, borde
         izquierdo rojo, ícono de escudo, CTA a WhatsApp.
    ════════════════════════════════════════════════════════════ --}}
    @error('form.banned')
        <div class="mb-5 rounded-2xl border border-red-200 bg-white overflow-hidden shadow-lg"
             style="animation: fadeInDown 0.4s ease-out both;">
            {{-- Barra de acento superior --}}
            <div class="h-1 w-full" style="background: linear-gradient(90deg, #DC2626, #EF4444, #DC2626);"></div>

            <div class="p-5">
                {{-- Encabezado con ícono --}}
                <div class="flex items-start gap-4">
                    {{-- Ícono escudo --}}
                    <div class="shrink-0 w-11 h-11 rounded-xl flex items-center justify-center"
                         style="background: rgba(220,38,38,0.08);">
                        <svg class="w-6 h-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>

                    <div class="flex-1 min-w-0">
                        <h3 class="text-sm font-black text-gray-900 tracking-tight">
                            Cuenta suspendida
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 leading-relaxed">
                            Tu cuenta ha sido suspendida temporalmente. Si crees que esto es un error, contactá a nuestro equipo de soporte y lo revisamos a la brevedad.
                        </p>
                    </div>
                </div>

                {{-- Separador --}}
                <div class="mt-4 border-t border-red-100"></div>

                {{-- CTA de contacto --}}
                <div class="mt-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                    <p class="text-xs text-gray-400 font-medium">
                        Código de error: <span class="font-mono text-red-500">ACC_SUSPENDED</span>
                    </p>
                    @php
                        $banSettings  = \App\Models\StoreSetting::getSettings();
                        $banSocial    = is_array($banSettings->social_links) ? $banSettings->social_links : [];
                        $banWa        = !empty($banSocial['whatsapp']) ? preg_replace('/[^0-9]/', '', $banSocial['whatsapp']) : null;
                    @endphp
                    @if($banWa)
                        <a href="https://wa.me/{{ $banWa }}?text={{ urlencode('Hola, mi cuenta fue suspendida y necesito ayuda. (ACC_SUSPENDED)') }}"
                           target="_blank" rel="noopener"
                           class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold text-white transition-all hover:opacity-90 hover:scale-[1.02] active:scale-[0.98] shadow-sm"
                           style="background-color: #16a34a;">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 00-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                            </svg>
                            Contactar soporte
                        </a>
                    @else
                        <p class="text-xs text-gray-500">
                            Contactá a soporte para más información.
                        </p>
                    @endif
                </div>
            </div>
        </div>

        <style>
            @keyframes fadeInDown {
                from { opacity: 0; transform: translateY(-8px); }
                to   { opacity: 1; transform: translateY(0); }
            }
        </style>
    @enderror

    {{-- Mensaje de estado de sesión (password reset, etc.) --}}
    <x-auth-session-status class="mb-4" :status="session('status')" />

    {{-- Error genérico de sesión (errores de Google OAuth, etc.) --}}
    @if (session('error'))
        <div class="mb-4 font-medium text-sm text-red-600 dark:text-red-400 p-3 bg-red-50 dark:bg-red-900/20 rounded-xl border border-red-200 dark:border-red-800">
            {{ session('error') }}
        </div>
    @endif


    <form wire:submit="login" class="space-y-4">
        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="form.email" id="email" class="block mt-1 w-full py-2.5 px-4" type="email" name="email" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('form.email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-password-input wire:model="form.password" id="password" name="password" required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block">
            <label for="remember" class="inline-flex items-center">
                <input wire:model="form.remember" id="remember" type="checkbox" class="rounded border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-[var(--color-primary)] shadow-sm focus:ring-[var(--color-primary)] dark:focus:ring-[var(--color-primary)]" name="remember">
                <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">{{ __('Remember me') }}</span>
            </label>
        </div>

        <!-- Turnstile -->
        @if(config('services.turnstile.enabled'))
            <div wire:ignore class="mt-4">
                <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-callback="setTurnstileTokenLogin"></div>
                <script>
                    function setTurnstileTokenLogin(token) {
                        @this.set('turnstileToken', token);
                    }
                </script>
                @once
                    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                @endonce
            </div>
            <x-input-error :messages="$errors->get('turnstileToken')" class="mt-2" />
        @endif

        <div class="flex items-center justify-between pt-1">
            @if (Route::has('password.request'))
                <a class="text-sm font-medium text-[var(--color-primary)] hover:opacity-80 transition-opacity" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button>
                {{ __('Log in') }}
            </x-primary-button>
        </div>
        
        <div class="text-center mt-4">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                ¿No tienes una cuenta? 
                <a href="{{ route('register') }}" class="font-bold text-[var(--color-primary)] hover:underline">Regístrate aquí</a>
            </p>
        </div>

    </form>

    <div class="pt-3">
        <div class="flex items-center">
            <div class="flex-grow border-t border-gray-200 dark:border-gray-700"></div>
            <span class="px-3 text-sm text-gray-500 bg-transparent">O continuar con</span>
            <div class="flex-grow border-t border-gray-200 dark:border-gray-700"></div>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-3">
            <button 
                type="button"
                onclick="window.location.href='{{ route('google.login') }}'"
                class="w-full inline-flex justify-center py-2.5 px-4 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm bg-white dark:bg-gray-900/50 text-sm font-bold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    <path fill="none" d="M1 1h22v22H1z"/>
                </svg>
                Continuar con Google
            </button>
        </div>
    </div>
</div>
