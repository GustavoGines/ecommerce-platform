<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantSuspended
{
    /**
     * Bloquea el acceso con HTTP 503 si el tenant actual se encuentra suspendido temporalmente.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! tenant()) {
            return $next($request);
        }

        // Permitir notificaciones firmadas de MercadoPago en vuelo aunque la tienda se haya pausado
        if ($request->routeIs('webhook.mercadopago') || $request->is('webhooks/mercadopago')) {
            return $next($request);
        }

        $maintenance = tenant('maintenance_mode');

        if (! empty($maintenance)) {
            $data = is_array($maintenance) ? $maintenance : [];

            if (! empty($data['allowed']) && IpUtils::checkIp((string) $request->ip(), (array) $data['allowed'])) {
                return $next($request);
            }

            $retryAfter = isset($data['retry']) && is_numeric($data['retry']) ? (int) $data['retry'] : 3600;
            $message = ! empty($data['message'])
                ? (string) $data['message']
                : 'Esta tienda se encuentra temporalmente fuera de servicio por mantenimiento.';

            $headers = [
                'Retry-After' => (string) $retryAfter,
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ];

            if ($request->expectsJson() || $request->is('api/*') || $request->header('X-Livewire')) {
                return response()->json([
                    'error'   => 'Tienda suspendida temporalmente',
                    'message' => $message,
                ], 503, $headers);
            }

            return response()->view('errors.tenant-suspended', [
                'message'  => $message,
                'tenantId' => tenant('id'),
            ], 503, $headers);
        }

        return $next($request);
    }
}
