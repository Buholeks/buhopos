<?php

namespace App\Http\Controllers;

use App\Exportaciones\ServicioExportacion;
use App\Exportaciones\VentasExportacion;
use App\Models\Venta;
use App\Models\User;
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
        abort_unless(Auth::user()->tienePermiso('reportes.ver'), 403, 'Sin permiso: reportes.ver');
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
                ->when($request->filled('user_id'),    fn($q) => $q->where('user_id', $request->user_id))
                ->when($request->filled('forma_pago'), fn($q) => $q->where('forma_pago', $request->forma_pago))
                ->when($request->filled('estado'),     fn($q) => $q->where('estado', $request->estado))
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

        $cajeros = User::where('empresa_id', $user->empresa_id)
            ->whereHas('ventasRegistradas', fn($q) =>
                $q->where('sucursal_id', $user->sucursal_id)
            )
            ->orderBy('name')
            ->get(['id', 'name']);

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
                    'subtotal'      => round($grupo->where('estado', 'confirmada')->sum('subtotal'), 2),
                    'descuentos'    => round($grupo->where('estado', 'confirmada')->sum('descuento'), 2),
                    'total'         => round($grupo->where('estado', 'confirmada')->sum('total'), 2),
                    'efectivo'      => round($grupo->where('estado', 'confirmada')->where('forma_pago', 'efectivo')->sum('total'), 2),
                    'tarjeta'       => round($grupo->where('estado', 'confirmada')->where('forma_pago', 'tarjeta')->sum('total'), 2),
                    'transferencia' => round($grupo->where('estado', 'confirmada')->where('forma_pago', 'transferencia')->sum('total'), 2),
                    'credito'       => round($grupo->where('estado', 'confirmada')->where('forma_pago', 'credito')->sum('total'), 2),
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
                'cajeros'      => $cajeros,
            ]);
        }

        // ── Listado paginado ──────────────────────────────────────
        $ventas = $query->paginate($request->por_pagina ?? 30);

        return response()->json([
            'ventas'  => $ventas,
            'totales' => $totales,
            'cajeros' => $cajeros,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // GET /api/reportes/ventas/{id}
    // ─────────────────────────────────────────────────────────────
    public function show(int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('reportes.ver'), 403, 'Sin permiso: reportes.ver');
        $user = Auth::user();

        $venta = Venta::where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->with([
                'user:id,name',
                'empresa:id,nombre,rfc,direccion,telefono',
                'sucursal:id,nombre,direccion,telefono',
                'cliente:id,nombre,telefono',
                'vendedor:id,name',
                'detalles.serie',
                'detalles.producto:id,nombre,codigo',
                // Cargamos la variante completa para poder llamar nombreVariante()
                'detalles.variante.atributos.tipoAtributo:id,nombre',
                'detalles.variante.atributos.atributo:id,valor',
            ])
            ->findOrFail($id);

        // Inyectar nombre legible de variante en cada detalle
        $venta->detalles->each(function ($d) {
            $d->nombre_variante = $d->variante?->nombreVariante() ?: null;
            $d->makeHidden('precio_costo');
        });

        return response()->json($venta);
    }

    // ─────────────────────────────────────────────────────────────
    // GET /api/reportes/ventas/exportar
    // ─────────────────────────────────────────────────────────────
    public function exportar(Request $request, ServicioExportacion $servicio)
    {
        abort_unless(Auth::user()->tienePermiso('reportes.ver'), 403, 'Sin permiso: reportes.ver');

        $datos = $request->validate([
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'user_id'     => ['nullable', 'integer'],
            'forma_pago'  => ['nullable', 'in:efectivo,tarjeta,transferencia,credito'],
            'estado'      => ['nullable', 'in:confirmada,cancelada'],
            'folio'       => ['nullable', 'string', 'max:60'],
            'producto'    => ['nullable', 'string', 'max:120'],
            'por_dia'     => ['nullable', 'boolean'],
            'formato'     => ['required', 'in:excel,pdf'],
        ]);

        $user   = Auth::user();
        $porDia = filter_var($datos['por_dia'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $exportacion = new VentasExportacion(
            empresaId:  (int) $user->empresa_id,
            sucursalId: (int) $user->sucursal_id,
            filtros:    $datos,
            porDia:     $porDia,
        );

        $nombre = ($porDia ? 'ventas_por_dia_' : 'ventas_') . now()->format('Ymd_His');

        return $servicio->exportar($exportacion, $datos['formato'], $nombre);
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
            COALESCE(SUM(CASE WHEN estado='confirmada' THEN total ELSE 0 END), 0) AS total,
            COALESCE(SUM(CASE WHEN estado='confirmada' THEN descuento ELSE 0 END), 0) AS descuentos,
            COALESCE(SUM(CASE WHEN forma_pago='efectivo'      AND estado='confirmada' THEN total ELSE 0 END),0) AS efectivo,
            COALESCE(SUM(CASE WHEN forma_pago='tarjeta'       AND estado='confirmada' THEN total ELSE 0 END),0) AS tarjeta,
            COALESCE(SUM(CASE WHEN forma_pago='transferencia' AND estado='confirmada' THEN total ELSE 0 END),0) AS transferencia,
            COALESCE(SUM(CASE WHEN forma_pago='credito'       AND estado='confirmada' THEN total ELSE 0 END),0) AS credito
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
