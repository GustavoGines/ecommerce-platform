<?php

namespace App\Http\Middleware;

use App\Models\StoreSetting;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Database\Models\Domain;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePosApi
{
    /**
     * Handle an incoming request from the POS System.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $manuallyInitialized = false;

        // 1. Resolución de Tenant
        if (! tenancy()->initialized) {
            $tenantId = $request->header('X-Tenant-ID') 
                     ?: $request->header('x-tenant-id') 
                     ?: $request->query('tenant_id');

            if ($tenantId) {
                $tenant = Tenant::find($tenantId);
                if (! $tenant) {
                    return response()->json([
                        'error'   => 'Tenant no encontrado',
                        'message' => "La tienda con identificador '{$tenantId}' no existe en esta plataforma.",
                    ], 400);
                }

                tenancy()->initialize($tenant);
                $manuallyInitialized = true;
            } else {
                // Intentar resolver por host / dominio
                $host = $request->getHost();
                $domainModel = Domain::where('domain', $host)->first();
                if ($domainModel && $domainModel->tenant) {
                    tenancy()->initialize($domainModel->tenant);
                    $manuallyInitialized = true;
                } else {
                    // Intento por token buscando en los tenants registrados
                    $token = $request->bearerToken();
                    if ($token) {
                        foreach (Tenant::all() as $candidate) {
                            $found = false;
                            $candidate->run(function () use ($token, &$found) {
                                $s = StoreSetting::getSettings();
                                if (!empty($s->pos_api_token) && hash_equals((string)$s->pos_api_token, (string)$token)) {
                                    $found = true;
                                }
                            });
                            if ($found) {
                                tenancy()->initialize($candidate);
                                $manuallyInitialized = true;
                                break;
                            }
                        }
                    }
                }
            }

            if (! tenancy()->initialized) {
                return response()->json([
                    'error'   => 'Tenant no especificado',
                    'message' => 'Debe incluir la cabecera X-Tenant-ID (ej: g3 o jcg) o realizar la petición al dominio de la tienda.',
                ], 400);
            }
        }

        // 2. Validación de Token Bearer
        $token = $request->bearerToken();
        if (empty($token)) {
            return response()->json([
                'error'   => 'No autorizado',
                'message' => 'Se requiere token Bearer en la cabecera Authorization: Bearer <token>',
            ], 401);
        }

        $settings = StoreSetting::getSettings();
        $tenantIdStr = strtoupper((string) tenant('id'));
        $expectedToken = $settings->pos_api_token 
                      ?: env("POS_API_TOKEN_{$tenantIdStr}", env('POS_API_TOKEN'));

        if (empty($expectedToken) || ! hash_equals((string) $expectedToken, (string) $token)) {
            Log::warning('POS API: Intento de acceso con token inválido', [
                'tenant' => tenant('id'),
                'ip'     => $request->ip(),
            ]);

            return response()->json([
                'error'   => 'Token inválido',
                'message' => 'El token de acceso proporcionado no coincide con el configurado para esta tienda.',
            ], 401);
        }

        return $next($request);
    }
}
