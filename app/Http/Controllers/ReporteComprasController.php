<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReporteComprasController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin'    => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'proveedor_id' => ['nullable', 'integer', 'exists:proveedores,id'],
            'estado'       => ['nullable', Rule::in(['confirmada', 'borrador', 'devuelta_parcial', 'devuelta', 'cancelada'])],
            'forma_pago'   => ['nullable', Rule::in(['efectivo', 'credito', 'transferencia', 'tarjeta', 'tarjeta_credito', 'tarjeta_debito'])],
            'per_page'     => ['nullable', 'integer', 'min:5', 'max:100'],
            'page'         => ['nullable', 'integer', 'min:1'],
        ]);

        $user = $request->user();

        $fechaInicio = $data['fecha_inicio'] ?? now()->startOfMonth()->toDateString();
        $fechaFin    = $data['fecha_fin'] ?? now()->toDateString();
        $perPage     = $data['per_page'] ?? 15;

        $base = DB::table('compras')
            ->where('compras.empresa_id', $user->empresa_id)
            ->where('compras.sucursal_id', $user->sucursal_id)
            ->whereBetween('compras.fecha', [$fechaInicio, $fechaFin])
            ->when($data['proveedor_id'] ?? null, fn($q, $v) => $q->where('compras.proveedor_id', $v))
            ->when($data['estado'] ?? null, fn($q, $v) => $q->where('compras.estado', $v))
            ->when($data['forma_pago'] ?? null, fn($q, $v) => $q->where('compras.forma_pago', $v));

        $totales = (clone $base)
            ->selectRaw("
    COUNT(*) AS total_compras,
    COALESCE(SUM(compras.subtotal), 0) AS total_subtotal,
    COALESCE(SUM(compras.total), 0) AS total_general,
    COALESCE(SUM(CASE WHEN compras.forma_pago = 'efectivo' THEN compras.total ELSE 0 END), 0) AS total_efectivo,
    COALESCE(SUM(CASE WHEN compras.forma_pago = 'transferencia' THEN compras.total ELSE 0 END), 0) AS total_transferencia,
    COALESCE(SUM(CASE WHEN compras.forma_pago = 'tarjeta_debito' THEN compras.total ELSE 0 END), 0) AS total_tarjeta_debito,
    COALESCE(SUM(CASE WHEN compras.forma_pago IN ('credito','tarjeta_credito') THEN compras.total ELSE 0 END), 0) AS total_credito,
    COALESCE(SUM(CASE WHEN compras.forma_pago IN ('credito','tarjeta_credito') AND compras.saldo > 0 THEN compras.saldo ELSE 0 END), 0) AS total_saldo_pendiente,
    COALESCE(SUM(CASE WHEN compras.estado = 'confirmada' THEN compras.total ELSE 0 END), 0) AS total_confirmadas,
    COALESCE(SUM(CASE WHEN compras.estado = 'borrador' THEN compras.total ELSE 0 END), 0) AS total_borradores
")
            ->first();

        $porDia = (clone $base)
            ->selectRaw('DATE(compras.fecha) AS dia, COUNT(*) AS cantidad, COALESCE(SUM(compras.total), 0) AS total')
            ->groupByRaw('DATE(compras.fecha)')
            ->orderBy('dia')
            ->get();

        $topProveedores = (clone $base)
            ->leftJoin('proveedores', 'compras.proveedor_id', '=', 'proveedores.id')
            ->selectRaw("
                proveedores.id,
                COALESCE(proveedores.nombre_comercial, 'Sin proveedor') AS nombre,
                COUNT(*) AS cantidad,
                COALESCE(SUM(compras.total), 0) AS total
            ")
            ->groupBy('proveedores.id', 'proveedores.nombre_comercial')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $compras = (clone $base)
            ->leftJoin('proveedores', 'compras.proveedor_id', '=', 'proveedores.id')
            ->select(
                'compras.id',
                'compras.folio',
                'compras.fecha',
                'compras.forma_pago',
                'compras.fecha_vencimiento',
                'compras.subtotal',
                'compras.total',
                'compras.pagado',
                'compras.saldo',
                'compras.estado',
                'compras.notas',
                'proveedores.id as proveedor_id',
                'proveedores.nombre_comercial as proveedor_nombre',
                'proveedores.rfc as proveedor_rfc'
            )
            ->orderByDesc('compras.fecha')
            ->orderByDesc('compras.id')
            ->paginate($perPage);

        $compras->getCollection()->transform(fn($c) => $this->mapCompraRow($c));

        return response()->json([
            'filtros' => [
                'fecha_inicio' => $fechaInicio,
                'fecha_fin'    => $fechaFin,
            ],
            'totales'         => $totales,
            'por_dia'         => $porDia,
            'top_proveedores' => $topProveedores,
            'compras'         => $compras,
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $compra = DB::table('compras')
            ->leftJoin('proveedores', 'compras.proveedor_id', '=', 'proveedores.id')
            ->leftJoin('users', 'compras.user_id', '=', 'users.id')
            ->where('compras.empresa_id', $user->empresa_id)
            ->where('compras.sucursal_id', $user->sucursal_id)
            ->where('compras.id', $id)
            ->select(
                'compras.*',
                'proveedores.nombre_comercial as proveedor_nombre',
                'proveedores.rfc as proveedor_rfc',
                'proveedores.telefono as proveedor_telefono',
                'proveedores.email as proveedor_email',
                'users.name as usuario_nombre'
            )
            ->first();

        abort_if(! $compra, 404, 'Compra no encontrada.');

        $compra->estatus_pago = $this->getEstatus($compra);

        $detalles = DB::table('compra_detalles as cd')
            ->join('compras as c', 'cd.compra_id', '=', 'c.id')
            ->join('productos as p', 'cd.producto_id', '=', 'p.id')
            ->leftJoin('producto_variantes as v', 'cd.variante_id', '=', 'v.id')
            ->where('cd.compra_id', $id)
            ->where('c.empresa_id', $user->empresa_id)
            ->where('c.sucursal_id', $user->sucursal_id)
            ->select(
                'cd.id',
                'p.nombre as producto',
                'v.sku as sku',
                'cd.cantidad',
                'cd.precio_compra',
                'cd.precio_venta',
                'cd.subtotal'
            )
            ->orderBy('cd.id')
            ->get();

        $pagos = DB::table('pagos_proveedor as pp')
            ->join('compras as c', 'pp.compra_id', '=', 'c.id')
            ->leftJoin('users', 'pp.user_id', '=', 'users.id')
            ->where('pp.compra_id', $id)
            ->where('c.empresa_id', $user->empresa_id)
            ->where('c.sucursal_id', $user->sucursal_id)
            ->select(
                'pp.id',
                'pp.monto',
                'pp.fecha_pago',
                'pp.forma_pago',
                'pp.referencia',
                'pp.notas',
                'users.name as usuario_nombre'
            )
            ->orderByDesc('pp.fecha_pago')
            ->orderByDesc('pp.id')
            ->get();

        return response()->json([
            'compra'   => $compra,
            'detalles' => $detalles,
            'pagos'    => $pagos,
        ]);
    }

    public function cuentasPorPagar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin'    => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'proveedor_id' => ['nullable', 'integer', 'exists:proveedores,id'],
            'estatus'      => ['nullable', Rule::in(['pendiente', 'pagado', 'vencido'])],
            'per_page'     => ['nullable', 'integer', 'min:5', 'max:100'],
            'page'         => ['nullable', 'integer', 'min:1'],
        ]);

        $user = $request->user();
        $hoy = now()->toDateString();
        $perPage = $data['per_page'] ?? 15;

        $query = DB::table('compras')
            ->leftJoin('proveedores', 'compras.proveedor_id', '=', 'proveedores.id')
            ->where('compras.empresa_id', $user->empresa_id)
            ->where('compras.sucursal_id', $user->sucursal_id)
            ->whereIn('compras.forma_pago', ['credito', 'tarjeta_credito'])

            ->when($data['proveedor_id'] ?? null, fn($q, $v) => $q->where('compras.proveedor_id', $v))
            ->when($data['fecha_inicio'] ?? null, fn($q, $v) => $q->whereDate('compras.fecha', '>=', $v))
            ->when($data['fecha_fin'] ?? null, fn($q, $v) => $q->whereDate('compras.fecha', '<=', $v));

        match ($data['estatus'] ?? null) {
            'pagado' => $query->where('compras.saldo', '<=', 0),

            'vencido' => $query
                ->where('compras.saldo', '>', 0)
                ->whereDate('compras.fecha_vencimiento', '<', $hoy),

            'pendiente' => $query
                ->where('compras.saldo', '>', 0)
                ->where(function ($q) use ($hoy) {
                    $q->whereNull('compras.fecha_vencimiento')
                        ->orWhereDate('compras.fecha_vencimiento', '>=', $hoy);
                }),

            default => null,
        };

        $totales = (clone $query)
            ->selectRaw("
                COALESCE(SUM(compras.total), 0) AS total_deuda,
                COALESCE(SUM(compras.pagado), 0) AS total_pagado,
                COALESCE(SUM(compras.saldo), 0) AS total_saldo_pendiente
            ")
            ->first();

        $cuentas = (clone $query)
            ->select(
                'compras.id',
                'compras.folio',
                'compras.fecha',
                'compras.fecha_vencimiento',
                'compras.forma_pago',
                'compras.total',
                'compras.pagado',
                'compras.saldo',
                'compras.estado',
                'proveedores.id as proveedor_id',
                'proveedores.nombre_comercial as proveedor_nombre',
                'proveedores.rfc as proveedor_rfc'
            )
            ->orderByRaw(
                'CASE WHEN compras.saldo > 0 AND compras.fecha_vencimiento < ? THEN 0 ELSE 1 END',
                [$hoy]
            )
            ->orderByRaw('compras.fecha_vencimiento IS NULL')
            ->orderBy('compras.fecha_vencimiento')
            ->orderByDesc('compras.id')
            ->paginate($perPage);

        $cuentas->getCollection()->transform(fn($c) => $this->mapCompraRow($c));

        return response()->json([
            'totales' => $totales,
            'cuentas' => $cuentas,
        ]);
    }

    private function mapCompraRow(object $c): object
    {
        $c->proveedor = [
            'id'           => $c->proveedor_id,
            'nombre'       => $c->proveedor_nombre,
            'nombre_comercial' => $c->proveedor_nombre,
            'rfc'          => $c->proveedor_rfc,
        ];

        unset(
            $c->proveedor_id,
            $c->proveedor_nombre,
            $c->proveedor_rfc
        );

        $c->estatus_pago = $this->getEstatus($c);

        return $c;
    }

    private function getEstatus(object $compra): string
    {
        if ($compra->forma_pago === 'efectivo') {
            return 'pagado';
        }

        if ((float) ($compra->saldo ?? 0) <= 0) {
            return 'pagado';
        }

        if (
            ! empty($compra->fecha_vencimiento)
            && substr((string) $compra->fecha_vencimiento, 0, 10) < now()->toDateString()
        ) {
            return 'vencido';
        }

        return 'pendiente';
    }
}
