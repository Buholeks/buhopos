<?php

namespace App\Servicios;

use Illuminate\Support\Facades\DB;

class InventarioComprometidoReporte
{
    public static function aplicar($query, int $empresaId, int $sucursalId)
    {
        $reservas = DB::table('inventario_reservas')
            ->where('empresa_id', $empresaId)
            ->where('sucursal_id', $sucursalId)
            ->where('estado', 'activa')
            ->selectRaw('producto_id, variante_id, SUM(cantidad) AS total')
            ->groupBy('producto_id', 'variante_id');

        $pedidosDisponibles = DB::table('pedido_detalles as pd')
            ->join('pedidos as pe', 'pe.id', '=', 'pd.pedido_id')
            ->leftJoin('inventario_reservas as ir_activa', function ($join) {
                $join->on('ir_activa.pedido_detalle_id', '=', 'pd.id')
                    ->where('ir_activa.estado', '=', 'activa');
            })
            ->where('pe.empresa_id', $empresaId)
            ->where('pe.sucursal_id', $sucursalId)
            ->where('pd.estado', 'disponible')
            ->whereNotIn('pe.estado', ['entregado', 'devuelto', 'cancelado', 'vencido'])
            ->whereNull('ir_activa.id')
            ->selectRaw('pd.producto_id, pd.variante_id, SUM(pd.cantidad) AS total')
            ->groupBy('pd.producto_id', 'pd.variante_id');

        return $query
            ->leftJoinSub($reservas, 'ir_comp', function ($join) {
                $join->on('ir_comp.producto_id', '=', 'inv.producto_id')
                    ->whereRaw('(ir_comp.variante_id = inv.variante_id OR (ir_comp.variante_id IS NULL AND inv.variante_id IS NULL))');
            })
            ->leftJoinSub($pedidosDisponibles, 'pd_comp', function ($join) {
                $join->on('pd_comp.producto_id', '=', 'inv.producto_id')
                    ->whereRaw('(pd_comp.variante_id = inv.variante_id OR (pd_comp.variante_id IS NULL AND inv.variante_id IS NULL))');
            });
    }

    public static function sql(): string
    {
        return '(COALESCE(ir_comp.total, 0) + COALESCE(pd_comp.total, 0))';
    }

    public static function disponibleSql(): string
    {
        $comprometido = self::sql();

        return "CASE WHEN inv.stock > {$comprometido} THEN inv.stock - {$comprometido} ELSE 0 END";
    }
}
