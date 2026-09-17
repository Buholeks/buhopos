<?php

namespace App\Services;

use App\Models\Sucursal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VentaGeneralReporte
{
    public function generar(int $empresaId, array $filtros): array
    {
        $sucursales = Sucursal::where('empresa_id', $empresaId)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']);
        $desde = $filtros['fecha_desde'];
        $hasta = Carbon::parse($filtros['fecha_hasta'])->addDay()->toDateString();
        $desdeUtc = Carbon::parse($desde, 'America/Mexico_City')->startOfDay()->utc();
        $hastaUtc = Carbon::parse($filtros['fecha_hasta'], 'America/Mexico_City')->endOfDay()->utc();
        $sucursalIds = empty($filtros['sucursal_ids']) ? null : $filtros['sucursal_ids'];
        $ventas = DB::table('ventas')->where('empresa_id', $empresaId)->whereNull('deleted_at')
            ->where('fecha', '>=', $desde)->where('fecha', '<', $hasta)
            ->when($sucursalIds, fn ($q) => $q->whereIn('sucursal_id', $sucursalIds))
            ->selectRaw("sucursal_id,
                SUM(CASE WHEN estado = 'confirmada' THEN total ELSE 0 END) as ventas,
                SUM(CASE WHEN estado = 'confirmada' THEN descuento ELSE 0 END) as descuentos,
                SUM(CASE WHEN estado = 'cancelada' THEN total ELSE 0 END) as canceladas")
            ->groupBy('sucursal_id')->get()->keyBy('sucursal_id');
        $devoluciones = DB::table('devoluciones')->where('empresa_id', $empresaId)
            ->where('estado', 'confirmada')->where('fecha', '>=', $desdeUtc)->where('fecha', '<=', $hastaUtc)
            ->when($sucursalIds, fn ($q) => $q->whereIn('sucursal_id', $sucursalIds))
            ->selectRaw('sucursal_id, SUM(total_devuelto) as importe')
            ->groupBy('sucursal_id')->pluck('importe', 'sucursal_id');
        $filas = $sucursales->filter(fn ($s) => empty($filtros['sucursal_ids']) || in_array($s->id, $filtros['sucursal_ids']))
            ->map(function ($s) use ($ventas, $devoluciones) {
                $v = $ventas->get($s->id);
                $venta = round((float) ($v->ventas ?? 0), 2);
                $dev = round((float) ($devoluciones[$s->id] ?? 0), 2);
                return ['id' => $s->id, 'sucursal' => $s->nombre, 'ventas' => $venta,
                    'descuentos' => round((float) ($v->descuentos ?? 0), 2), 'devoluciones' => $dev,
                    'canceladas' => round((float) ($v->canceladas ?? 0), 2), 'neta' => round($venta - $dev, 2)];
            })->sortByDesc('neta')->values();
        $totales = [];
        foreach (['ventas', 'descuentos', 'devoluciones', 'canceladas', 'neta'] as $campo) {
            $totales[$campo] = round($filas->sum($campo), 2);
        }
        // No expresar participación porcentual cuando hay importes netos negativos.
        $participacionValida = $totales['neta'] > 0 && ! $filas->contains(fn ($f) => $f['neta'] < 0);
        $filas = $filas->map(fn ($f) => $f + ['participacion' => $participacionValida ? round($f['neta'] / $totales['neta'] * 100, 2) : null]);
        return ['datos' => $filas, 'totales' => $totales, 'sucursales' => $sucursales, 'filtros' => $filtros];
    }
}
