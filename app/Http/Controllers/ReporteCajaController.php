<?php

namespace App\Http\Controllers;

use App\Exportaciones\CajaExportacion;
use App\Exportaciones\ServicioExportacion;
use App\Models\CorteCaja;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReporteCajaController extends Controller
{
    // ─────────────────────────────────────────────────────────────
    // 1. LISTADO / COMPARATIVO  GET /api/reportes/caja
    //    Filtros: fecha_desde, fecha_hasta, user_id, estado
    //    Agrupación: por_dia (bool)
    // ─────────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('reportes.ver'), 403, 'Sin permiso: reportes.ver');
        $request->validate([
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'user_id'     => ['nullable', 'integer'],
            'estado'      => ['nullable', 'in:abierto,cerrado,anulado'],
            'por_dia'     => ['nullable', 'boolean'],
            'por_pagina'  => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $user = Auth::user();

        $query = CorteCaja::query()
            ->where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->with(['user:id,name'])
            ->when(
                $request->filled('fecha_desde'),
                fn ($q) => $q->where('fecha_apertura', '>=', Carbon::parse($request->fecha_desde, 'America/Mexico_City')->startOfDay()->utc())
            )
            ->when(
                $request->filled('fecha_hasta'),
                fn ($q) => $q->where('fecha_apertura', '<=', Carbon::parse($request->fecha_hasta, 'America/Mexico_City')->endOfDay()->utc())
            )
            ->when(
                $request->filled('user_id'),
                fn ($q) => $q->where('user_id', $request->user_id)
            )
            ->when(
                $request->filled('estado'),
                fn ($q) => $q->where('estado', $request->estado)
            )
            ->orderByDesc('fecha_apertura');

        if ($request->boolean('por_dia')) {
            $cortes = $query->get();
            $this->refrescarCortesAbiertos($cortes);

            $agrupado = $cortes
                ->groupBy(fn ($corte) => $corte->fecha_apertura->copy()->setTimezone('America/Mexico_City')->toDateString())
                ->map(function ($grupo, $fecha) {
                    $totalCobradoCaja = $this->sumarTotalCobradoCaja($grupo);
                    $totalVendido = $this->sumarTotalVendido($grupo);

                    return [
                        'fecha'                => $fecha,
                        'num_cortes'           => $grupo->count(),
                        'num_ventas'           => (int) $grupo->sum('num_ventas'),
                        'ventas_efectivo'      => round((float) $grupo->sum('ventas_efectivo'), 2),
                        'ventas_tarjeta'       => round((float) $grupo->sum('ventas_tarjeta'), 2),
                        'ventas_transferencia' => round((float) $grupo->sum('ventas_transferencia'), 2),
                        'ventas_saldo_favor'   => round((float) $grupo->sum('ventas_saldo_favor'), 2),
                        'total_cobrado_caja'   => $totalCobradoCaja,
                        'total_vendido'        => $totalVendido,
                        'total_ventas'         => $totalCobradoCaja,
                        'movs_efectivo'        => round((float) $grupo->sum('movs_efectivo'), 2),
                        'movs_tarjeta'         => round((float) $grupo->sum('movs_tarjeta'), 2),
                        'movs_transferencia'   => round((float) $grupo->sum('movs_transferencia'), 2),
                        'esperado_efectivo'    => round((float) $grupo->sum('esperado_efectivo'), 2),
                        'contado_efectivo'     => round((float) $grupo->sum('contado_efectivo'), 2),
                        'dif_efectivo'         => round((float) $grupo->sum('dif_efectivo'), 2),
                        'dif_tarjeta'          => round((float) $grupo->sum('dif_tarjeta'), 2),
                        'dif_transferencia'    => round((float) $grupo->sum('dif_transferencia'), 2),
                    ];
                })
                ->values();

            return response()->json([
                'agrupado_por' => 'dia',
                'datos'        => $agrupado,
                'totales'      => $this->calcularTotalesGlobales($cortes),
            ]);
        }

        $porPagina = (int) ($request->por_pagina ?? 20);

        $cortes = $query->paginate($porPagina);

        $this->refrescarCortesAbiertos($cortes->getCollection());

        $cortes->getCollection()->transform(function ($corte) {
            $this->agregarTotalesVenta($corte);
            return $corte;
        });

        return response()->json($cortes);
    }

    // ─────────────────────────────────────────────────────────────
    // 2. EXPORTAR LISTADO  GET /api/reportes/caja/exportar
    // ─────────────────────────────────────────────────────────────
    public function exportar(Request $request, ServicioExportacion $servicio)
    {
        abort_unless(Auth::user()->tienePermiso('reportes.ver'), 403, 'Sin permiso: reportes.ver');

        $data = $request->validate([
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'user_id'     => ['nullable', 'integer'],
            'estado'      => ['nullable', 'in:abierto,cerrado,anulado'],
            'formato'     => ['required', 'in:excel,pdf'],
        ]);

        $user = Auth::user();

        $exportacion = new CajaExportacion(
            empresaId:  $user->empresa_id,
            sucursalId: $user->sucursal_id,
            filtros:    $data,
        );

        $nombre = 'caja_cortes_' . now()->format('Ymd_His');

        return $servicio->exportar($exportacion, $data['formato'], $nombre);
    }

    private function calcularTotalesGlobales($cortes): array
    {
        return [
            'num_cortes'           => $cortes->count(),
            'num_ventas'           => (int) $cortes->sum('num_ventas'),
            'ventas_efectivo'      => round((float) $cortes->sum('ventas_efectivo'), 2),
            'ventas_tarjeta'       => round((float) $cortes->sum('ventas_tarjeta'), 2),
            'ventas_transferencia' => round((float) $cortes->sum('ventas_transferencia'), 2),
            'ventas_saldo_favor'   => round((float) $cortes->sum('ventas_saldo_favor'), 2),
            'total_cobrado_caja'   => $this->sumarTotalCobradoCaja($cortes),
            'total_vendido'        => $this->sumarTotalVendido($cortes),
            'total_ventas'         => $this->sumarTotalCobradoCaja($cortes),
            'movs_efectivo'        => round((float) $cortes->sum('movs_efectivo'), 2),
            'esperado_efectivo'    => round((float) $cortes->sum('esperado_efectivo'), 2),
            'contado_efectivo'     => round((float) $cortes->sum('contado_efectivo'), 2),
            'dif_efectivo'         => round((float) $cortes->sum('dif_efectivo'), 2),
            'dif_tarjeta'          => round((float) $cortes->sum('dif_tarjeta'), 2),
            'dif_transferencia'    => round((float) $cortes->sum('dif_transferencia'), 2),
        ];
    }

    private function agregarTotalesVenta(CorteCaja $corte): void
    {
        $corte->total_cobrado_caja = $this->sumarCobradoCajaCorte($corte);
        $corte->total_vendido = round($corte->total_cobrado_caja + (float) $corte->ventas_saldo_favor, 2);
        $corte->total_ventas = $corte->total_cobrado_caja;
    }

    private function sumarCobradoCajaCorte(CorteCaja $corte): float
    {
        return round(
            (float) $corte->ventas_efectivo +
            (float) $corte->ventas_tarjeta +
            (float) $corte->ventas_transferencia,
            2
        );
    }

    private function sumarTotalCobradoCaja($cortes): float
    {
        return round((float) $cortes->sum(fn (CorteCaja $corte) => $this->sumarCobradoCajaCorte($corte)), 2);
    }

    private function sumarTotalVendido($cortes): float
    {
        return round((float) $cortes->sum(
            fn (CorteCaja $corte) => $this->sumarCobradoCajaCorte($corte) + (float) $corte->ventas_saldo_favor
        ), 2);
    }

    private function refrescarCortesAbiertos($cortes): void
    {
        // Solo en memoria: consultar el reporte no escribe en los cortes.
        $cortes->each(fn (CorteCaja $corte) => $corte->refrescarTotales());
    }
}
