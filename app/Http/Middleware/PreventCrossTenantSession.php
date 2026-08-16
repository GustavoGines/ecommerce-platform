<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventCrossTenantSession
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (tenant()) {
            $sessionTenant = session('tenant_id');
            
            if ($sessionTenant && $sessionTenant !== tenant('id')) {
                // Posible intento de secuestro de sesión entre inquilinos (cross-tenant session hijacking)
                session()->invalidate();
                session()->regenerateToken();
                
                return redirect()->route('home')->with('error', 'Sesión inválida detectada.');
            }
            
            if (! $sessionTenant) {
                session(['tenant_id' => tenant('id')]);
            }
        }

        return $next($request);
    }
}
