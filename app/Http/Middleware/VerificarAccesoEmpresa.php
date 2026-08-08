<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarAccesoEmpresa
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/login', 'api/register', 'api/logout', 'api/me', 'api/facturacion', 'api/facturacion/*', 'api/plataforma/*', 'sanctum/*')) {
            return $next($request);
        }

        $user = auth('web')->user();
        if (! $user) return $next($request);

        $user->loadMissing(['empresa.suscripcion', 'sucursal']);

        if (! $user->activo) {
            return response()->json(['message' => 'Tu usuario está inactivo.'], 403);
        }

        if (! $user->empresa?->activo) {
            return response()->json(['message' => 'La empresa está suspendida. Contacta a soporte.'], 403);
        }

        if (! $user->sucursal?->activo) {
            return response()->json(['message' => 'La sucursal activa está suspendida.'], 403);
        }

        // Compatibilidad con empresas creadas antes del módulo de renta.
        // Al asignarles una suscripción, su estado y vigencia pasan a ser obligatorios.
        $suscripcion = $user->empresa->suscripcion;
        if ($suscripcion && ! $suscripcion->permiteAcceso()) {
            return response()->json([
                'message' => 'La suscripción no está vigente. Contacta a soporte para reactivar el servicio.',
                'codigo' => 'SUSCRIPCION_NO_VIGENTE',
            ], 402);
        }

        return $next($request);
    }
}
