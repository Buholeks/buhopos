<?php

namespace App\Http\Controllers;

use App\Exportaciones\ServicioExportacion;
use App\Exportaciones\VentaGeneralExportacion;
use App\Services\VentaGeneralReporte;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReporteVentaGeneralController extends Controller
{
    private const RANGO_MAXIMO_DIAS = 366;

    private function filtros(Request $request): array
    {
        abort_unless($request->user()->tienePermiso('reportes.venta_general'), 403, 'Sin permiso: reportes.venta_general');
        return $request->validate([
            'fecha_desde' => ['required', 'date_format:Y-m-d'],
            'fecha_hasta' => [
                'required', 'date_format:Y-m-d', 'after_or_equal:fecha_desde',
                function ($attribute, $value, $fail) use ($request) {
                    if (! $request->filled('fecha_desde')) {
                        return;
                    }
                    if (Carbon::parse($request->fecha_desde)->diffInDays(Carbon::parse($value)) > self::RANGO_MAXIMO_DIAS) {
                        $fail('El rango de fechas no puede ser mayor a '.self::RANGO_MAXIMO_DIAS.' días.');
                    }
                },
            ],
            'sucursal_ids' => ['sometimes', 'array'],
            'sucursal_ids.*' => ['integer', 'distinct', Rule::exists('sucursales', 'id')->where('empresa_id', $request->user()->empresa_id)],
        ]);
    }

    public function index(Request $request, VentaGeneralReporte $reporte)
    {
        $filtros = $this->filtros($request);
        return response()->json($reporte->generar((int) $request->user()->empresa_id, $filtros));
    }

    public function exportar(Request $request, VentaGeneralReporte $reporte, ServicioExportacion $servicio)
    {
        $filtros = $this->filtros($request);
        $formato = $request->validate(['formato' => ['required', 'in:excel,pdf']])['formato'];
        $empresaId = (int) $request->user()->empresa_id;
        return $servicio->exportar(new VentaGeneralExportacion($empresaId, $reporte->generar($empresaId, $filtros), $formato), $formato, 'venta_general_'.$filtros['fecha_desde'].'_'.$filtros['fecha_hasta']);
    }
}
