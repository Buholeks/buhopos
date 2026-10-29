<?php

namespace App\Http\Controllers;

use App\Support\FiltroCuentaPago;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CuentaPagoFiltroController extends Controller
{
    // GET /api/reportes/cuentas-pago
    // Opciones de terminal y cuenta para los filtros de forma de pago de
    // reportes y consulta de caja; basta con poder ver alguna de esas pantallas.
    public function __invoke(): JsonResponse
    {
        $user = Auth::user();
        abort_unless(
            $user->tienePermiso('reportes.ver') || $user->tienePermiso('reportes.utilidades') || $user->tienePermiso('caja.historial'),
            403,
            'Sin permiso para ver reportes.'
        );

        return response()->json(FiltroCuentaPago::opciones((int) $user->empresa_id));
    }
}
