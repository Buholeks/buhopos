<?php

namespace App\Http\Controllers\Plataforma;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\PagoSuscripcion;
use App\Models\Suscripcion;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'empresas' => Empresa::count(),
            'empresas_activas' => Suscripcion::whereIn('estado', ['activa', 'prueba'])->count(),
            'registros_pendientes' => Suscripcion::where('estado', 'pendiente')->count(),
            'suscripciones_vencidas' => Suscripcion::whereIn('estado', ['vencida', 'suspendida'])->count(),
            'ingresos_mes' => (float) PagoSuscripcion::where('estado', 'confirmado')->whereBetween('fecha_pago', [now()->startOfMonth(), now()->endOfMonth()])->sum('importe'),
            'proximos_vencimientos' => Suscripcion::with(['empresa:id,nombre', 'plan:id,nombre'])
                ->whereIn('estado', ['activa', 'prueba'])->whereBetween('fecha_vencimiento', [today(), today()->addDays(15)])
                ->orderBy('fecha_vencimiento')->limit(10)->get(),
        ]);
    }
}
