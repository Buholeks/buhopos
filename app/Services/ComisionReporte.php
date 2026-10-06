<?php

namespace App\Services;

use App\Models\ComisionLiquidacion;
use App\Models\ComisionMovimiento;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Consultas del reporte de comisiones. El periodo se filtra por la fecha del movimiento:
 * la fecha de la venta para comisiones y la fecha de registro para cancelaciones/devoluciones.
 */
class ComisionReporte
{
    /**
     * Resumen por empleado.
     *
     * @param array{desde: string, hasta: string, sucursal_id?: ?int, user_id?: ?int} $filtros
     * @return array{empleados: array<int, array>, totales: array}
     */
    public function resumen(int $empresaId, array $filtros): array
    {
        $pagada = ComisionLiquidacion::ESTADO_PAGADA;

        $filas = $this->base($empresaId, $filtros)
            ->leftJoin('comision_liquidaciones as liq', 'liq.id', '=', 'comision_movimientos.liquidacion_id')
            ->join('users', 'users.id', '=', 'comision_movimientos.user_id')
            ->groupBy('comision_movimientos.user_id', 'users.name')
            ->orderBy('users.name')
            ->select([
                'comision_movimientos.user_id',
                'users.name as nombre',
                DB::raw("COUNT(DISTINCT CASE WHEN comision_movimientos.tipo = 'venta' AND comision_movimientos.beneficiario = 'vendedor' THEN comision_movimientos.venta_id END) as tickets_vendedor"),
                DB::raw("COUNT(DISTINCT CASE WHEN comision_movimientos.tipo = 'venta' AND comision_movimientos.beneficiario = 'cajero' THEN comision_movimientos.venta_id END) as tickets_cajero"),
                DB::raw("SUM(CASE WHEN comision_movimientos.tipo = 'venta' AND comision_movimientos.beneficiario = 'vendedor' THEN comision_movimientos.monto ELSE 0 END) as comision_vendedor"),
                DB::raw("SUM(CASE WHEN comision_movimientos.tipo = 'venta' AND comision_movimientos.beneficiario = 'cajero' THEN comision_movimientos.monto ELSE 0 END) as comision_cajero"),
                DB::raw("SUM(CASE WHEN comision_movimientos.tipo IN ('cancelacion', 'devolucion') THEN comision_movimientos.monto ELSE 0 END) as descuentos"),
                DB::raw("SUM(CASE WHEN comision_movimientos.tipo = 'ajuste' THEN comision_movimientos.monto ELSE 0 END) as ajustes"),
                DB::raw('SUM(comision_movimientos.monto) as total'),
                DB::raw("SUM(CASE WHEN liq.estado = '{$pagada}' THEN comision_movimientos.monto ELSE 0 END) as pagado"),
            ])
            ->get();

        $empleados = $filas->map(function ($f) {
            $total = round((float) $f->total, 2);
            $pagado = round((float) $f->pagado, 2);
            return [
                'user_id'           => (int) $f->user_id,
                'nombre'            => $f->nombre,
                'tickets_vendedor'  => (int) $f->tickets_vendedor,
                'tickets_cajero'    => (int) $f->tickets_cajero,
                'comision_vendedor' => round((float) $f->comision_vendedor, 2),
                'comision_cajero'   => round((float) $f->comision_cajero, 2),
                'descuentos'        => round((float) $f->descuentos, 2),
                'ajustes'           => round((float) $f->ajustes, 2),
                'total'             => $total,
                'pagado'            => $pagado,
                'pendiente'         => round($total - $pagado, 2),
            ];
        })->values();

        $suma = fn(string $campo) => round((float) $empleados->sum($campo), 2);

        return [
            'empleados' => $empleados->all(),
            'totales'   => [
                'empleados'         => $empleados->count(),
                'comision_vendedor' => $suma('comision_vendedor'),
                'comision_cajero'   => $suma('comision_cajero'),
                'descuentos'        => $suma('descuentos'),
                'ajustes'           => $suma('ajustes'),
                'total'             => $suma('total'),
                'pagado'            => $suma('pagado'),
                'pendiente'         => $suma('pendiente'),
            ],
        ];
    }

    /**
     * Movimientos de un empleado en el periodo, del más reciente al más antiguo.
     *
     * @param array{desde: string, hasta: string, sucursal_id?: ?int, beneficiario?: ?string, tipo?: ?string} $filtros
     */
    public function detalle(int $empresaId, int $userId, array $filtros, int $porPagina = 50): LengthAwarePaginator
    {
        return $this->base($empresaId, $filtros + ['user_id' => $userId])
            ->when($filtros['beneficiario'] ?? null, fn($q, $b) => $q->where('comision_movimientos.beneficiario', $b))
            ->when($filtros['tipo'] ?? null, fn($q, $t) => $q->where('comision_movimientos.tipo', $t))
            ->with([
                'venta:id,folio,fecha',
                'ventaDetalle:id,producto_nombre,variante_nombre,precio_venta',
                'devolucion:id,folio',
                'sucursal:id,nombre',
                'liquidacion:id,estado',
            ])
            ->orderByDesc('comision_movimientos.fecha')
            ->orderByDesc('comision_movimientos.id')
            ->select('comision_movimientos.*')
            ->paginate($porPagina)
            ->through(fn(ComisionMovimiento $m) => [
                'id'           => $m->id,
                'fecha'        => $m->fecha?->toDateString(),
                'tipo'         => $m->tipo,
                'beneficiario' => $m->beneficiario,
                'sucursal'     => $m->sucursal?->nombre,
                'venta_id'     => $m->venta_id,
                'folio'        => $m->venta?->folio,
                'devolucion'   => $m->devolucion?->folio,
                'producto'     => trim(($m->ventaDetalle?->producto_nombre ?? '') . ' ' . ($m->ventaDetalle?->variante_nombre ?? '')) ?: ($m->concepto ?? null),
                'precio'       => $m->ventaDetalle?->precio_venta !== null ? (float) $m->ventaDetalle->precio_venta : null,
                'cantidad'     => (float) $m->cantidad,
                'lista_precio' => $m->lista_precio,
                'base'         => $m->base,
                'regla_tipo'   => $m->regla_tipo,
                'tasa'         => (float) $m->tasa,
                'base_calculo' => (float) $m->base_calculo,
                'monto'        => (float) $m->monto,
                'estado_pago'  => $m->liquidacion?->estado === ComisionLiquidacion::ESTADO_PAGADA ? 'pagada' : 'pendiente',
            ]);
    }

    private function base(int $empresaId, array $filtros): Builder
    {
        return ComisionMovimiento::query()
            ->where('comision_movimientos.empresa_id', $empresaId)
            ->whereDate('comision_movimientos.fecha', '>=', $filtros['desde'])
            ->whereDate('comision_movimientos.fecha', '<=', $filtros['hasta'])
            ->when($filtros['sucursal_id'] ?? null, fn($q, $s) => $q->where('comision_movimientos.sucursal_id', $s))
            ->when($filtros['user_id'] ?? null, fn($q, $u) => $q->where('comision_movimientos.user_id', $u));
    }
}
