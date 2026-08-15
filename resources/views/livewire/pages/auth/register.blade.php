<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $turnstileToken = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ];

        if (config('services.turnstile.enabled')) {
            $rules['turnstileToken'] = ['required', function ($attribute, $value, $fail) {
                $response = \Illuminate\Support\Facades\Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => config('services.turnstile.secret_key'),
                    'response' => $value,
                    'remoteip' => request()->ip(),
                ]);
                if (!$response->json('success')) {
                    $fail('Verificación de seguridad fallida. Por favor recarga e intenta nuevamente.');
                }
            }];
        }

        $validated = $this->validate($rules);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        $oldSessionId = \Illuminate\Support\Facades\Session::getId();
        Auth::login($user);
        app(\App\Services\CartService::class)->mergeGuestCartIntoUserCart($user, $oldSessionId);

        $this->redirectIntended(default: route('home'), navigate: true);
    }
}; ?>

<div>
    <div class="mb-5 text-center">
        <h2 class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">Crea tu cuenta</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Únete a la mejor experiencia en electrónica y controles.</p>
    </div>

    <form wire:submit="register" class="space-y-4">
        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Nombre completo')" />
            <x-text-input wire:model="name" id="name" class="block mt-1 w-full py-2 px-4" type="text" name="name" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Correo electrónico')" />
            <x-text-input wire:model="email" id="email" class="block mt-1 w-full py-2 px-4" type="email" name="email" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Contraseña')" />
            <x-password-input wire:model="password" id="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div>
            <x-input-label for="password_confirmation" :value="__('Confirmar Contraseña')" />
            <x-password-input wire:model="password_confirmation" id="password_confirmation" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <!-- Turnstile -->
        @if(config('services.turnstile.enabled'))
            <div wire:ignore class="mt-4">
                <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-callback="setTurnstileTokenRegister"></div>
                <script>
                    function setTurnstileTokenRegister(token) {
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
            <a class="text-sm font-medium text-[var(--color-primary)] hover:opacity-80 transition-opacity" href="{{ route('login') }}">
                {{ __('¿Ya estás registrado?') }}
            </a>

            <x-primary-button>
                {{ __('CREAR CUENTA') }}
            </x-primary-button>
        </div>

        <div class="pt-3">
            <div class="flex items-center">
                <div class="flex-grow border-t border-gray-200 dark:border-gray-700"></div>
                <span class="px-3 text-sm text-gray-500 bg-transparent">O registrarse con</span>
                <div class="flex-grow border-t border-gray-200 dark:border-gray-700"></div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3">
                <a href="{{ route('google.login') }}" class="w-full inline-flex justify-center py-2.5 px-4 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm bg-white dark:bg-gray-900/50 text-sm font-bold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                    <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                        <path fill="none" d="M1 1h22v22H1z"/>
                    </svg>
                    Continuar con Google
                </a>
            </div>
        </div>
    </form>
</div>
