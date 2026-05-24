<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReporteVentasController extends Controller
{
    // ─────────────────────────────────────────────────────────────
    // GET /api/reportes/ventas
    // ─────────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'user_id'     => ['nullable', 'integer'],
            'forma_pago'  => ['nullable', 'in:efectivo,tarjeta,transferencia,credito'],
            'estado'      => ['nullable', 'in:confirmada,cancelada'],
            'folio'       => ['nullable', 'string', 'max:60'],
            'producto'    => ['nullable', 'string', 'max:120'],
            'por_dia'     => ['nullable', 'boolean'],
            'por_pagina'  => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $user = Auth::user();

        // ── Helper: aplica filtros comunes a cualquier query ──────
        $aplicarFiltros = function ($q) use ($request) {
            return $q
                ->when($request->filled('fecha_desde'), fn($q) =>
                    $q->whereDate('fecha', '>=', $request->fecha_desde))
                ->when($request->filled('fecha_hasta'), fn($q) =>
                    $q->whereDate('fecha', '<=', $request->fecha_hasta))
                ->when($request->filled('user_id'),    fn($q, $v) => $q->where('user_id', $v))
                ->when($request->filled('forma_pago'), fn($q, $v) => $q->where('forma_pago', $v))
                ->when($request->filled('estado'),     fn($q, $v) => $q->where('estado', $v))
                ->when($request->filled('folio'),      fn($q) =>
                    $q->where('folio', 'like', '%' . $request->folio . '%'))
                ->when($request->filled('producto'),   fn($q) =>
                    $q->whereHas('detalles.producto', fn($pq) =>
                        $pq->where('nombre', 'like', '%' . $request->producto . '%')
                           ->orWhere('codigo', 'like', '%' . $request->producto . '%')
                    ));
        };

        // ── Totales: query limpio SIN with() ni orderBy ───────────
        $totales = $this->calcularTotales(
            $aplicarFiltros(
                Venta::where('empresa_id', $user->empresa_id)
                     ->where('sucursal_id', $user->sucursal_id)
            )
        );

        // ── Query principal (con eager loads) ─────────────────────
        $query = $aplicarFiltros(
            Venta::where('empresa_id', $user->empresa_id)
                 ->where('sucursal_id', $user->sucursal_id)
                 ->with(['user:id,name'])
        )->orderByDesc('fecha')->orderByDesc('id');

        // ── Modo agrupado por día ─────────────────────────────────
        if ($request->boolean('por_dia')) {
            $ventas = $query->get();

            $agrupado = $ventas
                ->groupBy(fn($v) => \Carbon\Carbon::parse($v->fecha)->toDateString())
                ->map(fn($grupo, $fecha) => [
                    'fecha'         => $fecha,
                    'num_ventas'    => $grupo->count(),
                    'canceladas'    => $grupo->where('estado', 'cancelada')->count(),
                    'subtotal'      => round($grupo->sum('subtotal'), 2),
                    'descuentos'    => round($grupo->sum('descuento'), 2),
                    'total'         => round($grupo->sum('total'), 2),
                    'efectivo'      => round($grupo->where('forma_pago', 'efectivo')->sum('total'), 2),
                    'tarjeta'       => round($grupo->where('forma_pago', 'tarjeta')->sum('total'), 2),
                    'transferencia' => round($grupo->where('forma_pago', 'transferencia')->sum('total'), 2),
                    'credito'       => round($grupo->where('forma_pago', 'credito')->sum('total'), 2),
                    'ticket_prom'   => $grupo->where('estado', 'confirmada')->count() > 0
                        ? round(
                            $grupo->where('estado', 'confirmada')->sum('total') /
                            $grupo->where('estado', 'confirmada')->count(), 2)
                        : 0,
                ])
                ->sortKeysDesc()
                ->values();

            return response()->json([
                'agrupado_por' => 'dia',
                'datos'        => $agrupado,
                'totales'      => $totales,
            ]);
        }

        // ── Listado paginado ──────────────────────────────────────
        $ventas = $query->paginate($request->por_pagina ?? 30);

        return response()->json([
            'ventas'  => $ventas,
            'totales' => $totales,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // GET /api/reportes/ventas/{id}
    // ─────────────────────────────────────────────────────────────
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();

        $venta = Venta::where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->with([
                'user:id,name',
                'detalles.producto:id,nombre,codigo',
                // Cargamos la variante completa para poder llamar nombreVariante()
                'detalles.variante.atributos.tipoAtributo:id,nombre',
                'detalles.variante.atributos.atributo:id,valor',
            ])
            ->findOrFail($id);

        $venta->margen = round(
            $venta->detalles->sum(fn($d) =>
                (+$d->precio_venta - +$d->precio_costo) * +$d->cantidad
            ), 2
        );

        // Inyectar nombre legible de variante en cada detalle
        $venta->detalles->each(function ($d) {
            $d->nombre_variante = $d->variante?->nombreVariante() ?: null;
        });

        return response()->json($venta);
    }

    // ─────────────────────────────────────────────────────────────
    // Helper: totales del período
    // Recibe un query SIN with() ni orderBy para que selectRaw
    // no choque con los eager loads.
    // ─────────────────────────────────────────────────────────────
    private function calcularTotales($query): array
    {
        $row = $query->selectRaw("
            COUNT(*)                                                              AS num_ventas,
            COALESCE(SUM(CASE WHEN estado='confirmada' THEN 1 ELSE 0 END), 0)    AS confirmadas,
            COALESCE(SUM(CASE WHEN estado='cancelada'  THEN 1 ELSE 0 END), 0)    AS canceladas,
            COALESCE(SUM(total), 0)                                               AS total,
            COALESCE(SUM(descuento), 0)                                           AS descuentos,
            COALESCE(SUM(CASE WHEN forma_pago='efectivo'      THEN total END),0)  AS efectivo,
            COALESCE(SUM(CASE WHEN forma_pago='tarjeta'       THEN total END),0)  AS tarjeta,
            COALESCE(SUM(CASE WHEN forma_pago='transferencia' THEN total END),0)  AS transferencia,
            COALESCE(SUM(CASE WHEN forma_pago='credito'       THEN total END),0)  AS credito
        ")->first();

        $confirmadas = (int) $row->confirmadas;

        return [
            'num_ventas'    => (int)   $row->num_ventas,
            'confirmadas'   => $confirmadas,
            'canceladas'    => (int)   $row->canceladas,
            'total'         => (float) $row->total,
            'descuentos'    => (float) $row->descuentos,
            'efectivo'      => (float) $row->efectivo,
            'tarjeta'       => (float) $row->tarjeta,
            'transferencia' => (float) $row->transferencia,
            'credito'       => (float) $row->credito,
            'ticket_prom'   => $confirmadas > 0
                ? round($row->total / $confirmadas, 2)
                : 0,
        ];
    }


    
}