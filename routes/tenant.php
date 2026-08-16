<?php

declare(strict_types=1);

use App\Http\Controllers\CheckoutReturnController;
use App\Http\Controllers\MercadoPagoWebhookController;
use App\Http\Controllers\SitemapController;
use App\Models\StoreSetting;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Livewire\Volt\Volt;
use App\Http\Controllers\GoogleAuthController;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    \App\Http\Middleware\PreventCrossTenantSession::class,
])->group(function () {
    // SEO Sitemap — FIX-06: throttle para limitar crawlers agresivos (máx 30 req/min)
    Route::get('/sitemap.xml', [SitemapController::class, 'index'])
        ->middleware(['throttle:30,1'])
        ->name('sitemap');

    Route::get('/', function () {
        return view('welcome');
    })->name('home');

    Route::get('/shop', function () {
        return view('shop');
    })->name('shop');

    Volt::route('producto/{slug}', 'product-detail')
        ->name('product.detail');

    Route::view('profile', 'profile')
        ->middleware(['auth'])
        ->name('profile');

    require __DIR__.'/auth.php';

    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])
        ->middleware(['throttle:10,1'])
        ->name('google.login');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
        ->middleware(['throttle:10,1'])
        ->name('google.callback');

    Route::middleware(['auth'])->group(function () {
        Volt::route('checkout', 'checkout')->name('checkout');
        Volt::route('mis-ordenes', 'my-orders')->name('my-orders');

        // URLs de retorno de MercadoPago (requieren usuario autenticado)
        Route::get('checkout/success/{order}', [CheckoutReturnController::class, 'success'])->name('checkout.success');
        Route::get('checkout/failure/{order}', [CheckoutReturnController::class, 'failure'])->name('checkout.failure');
        Route::get('checkout/pending/{order}', [CheckoutReturnController::class, 'pending'])->name('checkout.pending');
    });

    Route::middleware(['auth', 'is_admin'])->group(function () {
        Route::get('/admin', function () {
            return redirect()->route('admin.dashboard');
        });
        
        Volt::route('admin/dashboard', 'admin.manage-dashboard')->name('admin.dashboard');
        Volt::route('admin/settings', 'admin.manage-settings')->name('admin.settings');
        Volt::route('admin/products', 'admin.manage-products')->name('admin.products');
        Volt::route('admin/orders', 'admin.manage-orders')->name('admin.orders');
        Volt::route('admin/users', 'admin.manage-users')->name('admin.users');
    });

    // Webhook de MercadoPago — sin auth, sin CSRF (se maneja en bootstrap/app.php)
    Route::post('webhooks/mercadopago', [MercadoPagoWebhookController::class, 'handle'])
        ->middleware(['throttle:60,1'])
        ->name('webhook.mercadopago');

    // FIX: Sobrescribir la ruta de Livewire Preview para que cargue dentro del contexto del Tenant
    Route::get('/livewire/preview-file/{filename}', [\Livewire\Features\SupportFileUploads\FilePreviewController::class, 'handle'])
        ->name('livewire.preview-file');
});
