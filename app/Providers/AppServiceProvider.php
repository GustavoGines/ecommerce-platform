<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // No tocar la DB aquí
    }

    public function boot(): void
    {
        $theme = 'stealth';
        try {
            $settings = \App\Models\StoreSetting::getSettings();

            $dbTheme = ($settings && $settings->theme_name) ? $settings->theme_name : 'stealth';
            if ($dbTheme !== 'stealth') {
                $theme = $dbTheme;
            }
        } catch (\Exception $e) {}

        \Illuminate\Support\Facades\View::share('activeTheme', $theme);
        app()->singleton('activeTheme', fn() => $theme);

        // Fix for Laragon subdirectories: Force Laravel to generate URLs with the correct base path
        if (! app()->environment('testing')) {
            $host = request()->getHost();
            
            if (str_contains($host, 'loca.lt') || str_contains($host, 'ngrok') || str_starts_with(config('app.url'), 'https://') || config('app.tunnel_active')) {
                URL::forceScheme('https');
            } else if (config('app.url')) {
                URL::forceRootUrl(config('app.url'));
            }
            
            // Fix dynamic Google Redirect URI for tunnels
            $googleRedirect = config('services.google.redirect');
            if ($googleRedirect && str_starts_with($googleRedirect, '/')) {
                config(['services.google.redirect' => url($googleRedirect)]);
            }

            $path = parse_url(config('app.url'), PHP_URL_PATH) ?? '';
            $path = trim($path, '/');

            Livewire::setUpdateRoute(function ($handle) use ($path) {
                return Route::post($path.'/livewire/update', $handle)
                    ->middleware(['web']);
            });
        }

        \Illuminate\Support\Facades\Event::listen(
            \App\Events\PaymentApproved::class,
            \App\Listeners\UpdateOrderOnPayment::class,
        );
    }
}
