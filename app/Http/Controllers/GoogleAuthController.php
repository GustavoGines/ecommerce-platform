<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CartService;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    private function configureSslClient(): void
    {
        // En entorno local (Windows/Laragon), los certificados SSL del sistema
        // no siempre incluyen la cadena completa para googleapis.com.
        // Esto es seguro porque solo aplica en local y en producción se usan
        // los certificados del servidor real.
        $verify = app()->isProduction()
            ? (ini_get('curl.cainfo') ?: true)
            : false;

        $guzzle = new \GuzzleHttp\Client(['verify' => $verify]);
        Socialite::driver('google')->setHttpClient($guzzle);
    }

    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirect(): \Illuminate\Http\RedirectResponse
    {
        try {
            // Store intended URL if not auth pages
            $previousUrl = url()->previous();
            if (! Str::contains($previousUrl, ['/login', '/register', '/auth']) && str_starts_with($previousUrl, config('app.url'))) {
                session(['url.intended' => $previousUrl]);
            }

            $this->configureSslClient();
            // Construir la URL de callback dinámicamente según el dominio actual.
            // Esto permite que funcione en múltiples dominios (JCG, G3, etc.)
            // sin necesitar una URL fija en el .env.
            return Socialite::driver('google')
                ->redirectUrl(url('/auth/google/callback'))
                ->redirect();
        } catch (Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error en redirect Google: ' . $e->getMessage());
            return redirect()->route('login')->with('error', 'Error de conexión con Google. Por favor, intenta nuevamente.');
        }
    }

    /**
     * Obtain the user information from Google.
     */
    public function callback(): \Illuminate\Http\RedirectResponse
    {
        try {
            $this->configureSslClient();
            $googleUser = Socialite::driver('google')
                ->redirectUrl(url('/auth/google/callback'))
                ->user();

            // Check if user already exists
            $user = User::where('google_id', $googleUser->id)->first();

            if ($user) {
                $this->loginAndMergeCart($user);
            } else {
                // If user doesn't exist by google_id, check by email
                $existingUserByEmail = User::where('email', $googleUser->email)->first();

                if ($existingUserByEmail) {
                    // Update existing user with google_id
                    $existingUserByEmail->update([
                        'google_id' => $googleUser->id,
                    ]);
                    $this->loginAndMergeCart($existingUserByEmail);
                } else {
                    // Create new user
                    $newUser = User::create([
                        'name' => $googleUser->name,
                        'email' => $googleUser->email,
                        'google_id' => $googleUser->id,
                        'password' => null,
                    ]);
                    $this->loginAndMergeCart($newUser);
                }
            }

            return redirect()->intended(route('home'));

        } catch (Exception $e) {
            \Illuminate\Support\Facades\Log::error('GOOGLE CALLBACK ERROR: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            // FIX-07: Mostrar mensaje específico si la cuenta está suspendida.
            $errorMessage = $e->getMessage() === 'account_suspended'
                ? 'Tu cuenta ha sido suspendida. Por favor, contacta con soporte si crees que esto es un error.'
                : 'Error al iniciar sesión con Google. Por favor, intenta nuevamente.';

            return redirect()->route('login')->with('error', $errorMessage);
        }
    }

    /**
     * Logs the user in and merges the guest cart into the user's cart.
     *
     * @throws \RuntimeException if the user account is suspended.
     */
    private function loginAndMergeCart(User $user): void
    {
        // FIX-07: Verificar suspensión ANTES de Auth::login().
        // El middleware CheckBanned solo aplica a usuarios ya logueados.
        // Sin este guard, un usuario baneado podría eludir la suspensión
        // usando "Continuar con Google" y obtener una sesión válida.
        if ($user->getAttribute('is_banned')) {
            throw new \RuntimeException('account_suspended');
        }

        $oldSessionId = Session::getId();
        Auth::login($user);
        request()->session()->regenerate();
        app(CartService::class)->mergeGuestCartIntoUserCart($user, $oldSessionId);
    }
}
