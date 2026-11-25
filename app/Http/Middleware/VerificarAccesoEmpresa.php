<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarAccesoEmpresa
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/login', 'api/register', 'api/logout', 'api/plataforma/*', 'sanctum/*')) {
            return $next($request);
        }

        $user = auth('web')->user();
        if (! $user) return $next($request);

        // Un usuario desactivado no conserva ningún acceso, ni siquiera a facturación.
        if (! $user->activo) {
            return response()->json(['message' => 'Tu usuario está inactivo.'], 403);
        }

        // /me y facturación siguen disponibles para que la empresa vea su estado y pague.
        if ($request->is('api/me', 'api/facturacion', 'api/facturacion/*')) {
            return $next($request);
        }

        $user->loadMissing(['empresa.suscripcion', 'sucursal']);

        // 402 para que el cliente redirija a facturación, donde puede pagar para reactivarse.
        if (! $user->empresa?->activo) {
            return response()->json([
                'message' => 'La empresa está suspendida. Regulariza tu pago o contacta a soporte.',
                'codigo' => 'EMPRESA_SUSPENDIDA',
            ], 402);
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
