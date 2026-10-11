<?php

use App\Http\Middleware\IsAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'is_admin' => IsAdmin::class,
        ]);

        // FIX-02: Sin proxy/CDN/load-balancer entre el cliente y este VPS.
        // El dominio apunta directamente a la IP pública del servidor (DonWeb Cloud).
        // Con at: [] Laravel ignora cualquier header X-Forwarded-For inyectado
        // por clientes maliciosos y obtiene la IP real desde la conexión TCP.
        // Si en el futuro se agrega Cloudflare u otro proxy, se deben especificar
        // sus rangos de IP aquí en lugar de usar el wildcard '*'.
        $middleware->trustProxies(at: []);

        // Excluir el webhook de MercadoPago del CSRF (es un POST server-to-server)
        $middleware->validateCsrfTokens(except: [
            'webhooks/mercadopago',
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\TrackVisits::class,
            \App\Http\Middleware\CheckBanned::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
