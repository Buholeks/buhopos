<?php

namespace App\Http\Controllers;

use App\Exportaciones\CajaExportacion;
use App\Exportaciones\ServicioExportacion;
use App\Models\CorteCaja;
use App\Models\MovimientoCaja;
use App\Models\Venta;
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

            $agrupado = $cortes
                ->groupBy(fn ($corte) => $corte->fecha_apertura->copy()->setTimezone('America/Mexico_City')->toDateString())
                ->map(function ($grupo, $fecha) {
                    return [
                        'fecha'                => $fecha,
                        'num_cortes'           => $grupo->count(),
                        'num_ventas'           => (int) $grupo->sum('num_ventas'),
                        'ventas_efectivo'      => round((float) $grupo->sum('ventas_efectivo'), 2),
                        'ventas_tarjeta'       => round((float) $grupo->sum('ventas_tarjeta'), 2),
                        'ventas_transferencia' => round((float) $grupo->sum('ventas_transferencia'), 2),
                        'ventas_credito'       => round((float) $grupo->sum('ventas_credito'), 2),
                        'total_ventas'         => round(
                            (float) $grupo->sum('ventas_efectivo') +
                            (float) $grupo->sum('ventas_tarjeta') +
                            (float) $grupo->sum('ventas_transferencia') +
                            (float) $grupo->sum('ventas_credito'),
                            2
                        ),
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

        $cortes->getCollection()->transform(function ($corte) {
            $corte->total_ventas = $this->sumarTotalVentas($corte);
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

    // ─────────────────────────────────────────────────────────────
    // 3. DETALLE DE UN CORTE  GET /api/reportes/caja/{id}
    // ─────────────────────────────────────────────────────────────
    public function show(int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('reportes.ver'), 403, 'Sin permiso: reportes.ver');
        $user = Auth::user();

        $corte = CorteCaja::query()
            ->where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->with([
                'user:id,name',
                'desglose',
                'movimientos.user:id,name',
            ])
            ->findOrFail($id);

        $corte->total_ventas = $this->sumarTotalVentas($corte);

        $movResumen = $corte->movimientos
            ->groupBy('forma_pago')
            ->map(function ($movimientos, $formaPago) {
                $ingresos = (float) $movimientos->where('tipo', 'ingreso')->sum('monto');
                $egresos  = (float) $movimientos->where('tipo', 'egreso')->sum('monto');

                return [
                    'forma_pago' => $formaPago,
                    'ingresos'   => round($ingresos, 2),
                    'egresos'    => round($egresos, 2),
                    'neto'       => round($ingresos - $egresos, 2),
                ];
            })
            ->values();

        return response()->json([
            'corte'       => $corte,
            'mov_resumen' => $movResumen,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // 3. MOVIMIENTOS DE UN CORTE  GET /api/reportes/caja/{id}/movimientos
    //    Opcional si en algún momento lo quieres paginado aparte
    // ─────────────────────────────────────────────────────────────
    public function movimientos(Request $request, int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('reportes.ver'), 403, 'Sin permiso: reportes.ver');
        $request->validate([
            'tipo'       => ['nullable', 'in:ingreso,egreso'],
            'forma_pago' => ['nullable', 'in:efectivo,tarjeta,transferencia,credito'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $user = Auth::user();

        $corte = CorteCaja::query()
            ->where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->findOrFail($id);

        $movimientos = MovimientoCaja::query()
            ->where('corte_id', $corte->id)
            ->with(['user:id,name'])
            ->when(
                $request->filled('tipo'),
                fn ($q) => $q->where('tipo', $request->tipo)
            )
            ->when(
                $request->filled('forma_pago'),
                fn ($q) => $q->where('forma_pago', $request->forma_pago)
            )
            ->orderByDesc('created_at')
            ->paginate((int) ($request->por_pagina ?? 50));

        return response()->json($movimientos);
    }

    // ─────────────────────────────────────────────────────────────
    // 4. VENTAS DE UN CORTE  GET /api/reportes/caja/{id}/ventas
    // ─────────────────────────────────────────────────────────────
    public function ventas(Request $request, int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('reportes.ver'), 403, 'Sin permiso: reportes.ver');
        $request->validate([
            'forma_pago' => ['nullable', 'in:efectivo,tarjeta,transferencia,credito'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $user = Auth::user();

        $corte = CorteCaja::query()
            ->where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->findOrFail($id);

        $ventas = Venta::query()
            ->where('corte_id', $corte->id)
            ->where('estado', 'confirmada')
            ->when(
                $request->filled('forma_pago'),
                fn ($q) => $q->where('forma_pago', $request->forma_pago)
            )
            ->with([
                'user:id,name',
                'detalles.producto:id,nombre',
                'detalles.variante:id,sku',
            ])
            ->orderByDesc('created_at')
            ->paginate((int) ($request->por_pagina ?? 30));

        return response()->json($ventas);
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers privados
    // ─────────────────────────────────────────────────────────────
    private function calcularTotalesGlobales($cortes): array
    {
        return [
            'num_cortes'           => $cortes->count(),
            'num_ventas'           => (int) $cortes->sum('num_ventas'),
            'ventas_efectivo'      => round((float) $cortes->sum('ventas_efectivo'), 2),
            'ventas_tarjeta'       => round((float) $cortes->sum('ventas_tarjeta'), 2),
            'ventas_transferencia' => round((float) $cortes->sum('ventas_transferencia'), 2),
            'ventas_credito'       => round((float) $cortes->sum('ventas_credito'), 2),
            'total_ventas'         => round(
                (float) $cortes->sum('ventas_efectivo') +
                (float) $cortes->sum('ventas_tarjeta') +
                (float) $cortes->sum('ventas_transferencia') +
                (float) $cortes->sum('ventas_credito'),
                2
            ),
            'movs_efectivo'        => round((float) $cortes->sum('movs_efectivo'), 2),
            'dif_efectivo'         => round((float) $cortes->sum('dif_efectivo'), 2),
            'dif_tarjeta'          => round((float) $cortes->sum('dif_tarjeta'), 2),
            'dif_transferencia'    => round((float) $cortes->sum('dif_transferencia'), 2),
        ];
    }

    private function sumarTotalVentas(CorteCaja $corte): float
    {
        return round(
            (float) $corte->ventas_efectivo +
            (float) $corte->ventas_tarjeta +
            (float) $corte->ventas_transferencia +
            (float) $corte->ventas_credito,
            2
        );
    }
}