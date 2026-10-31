<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class AjustesPrecioResumen
{
    /**
     * Resume las líneas con precio manual (ver VentaDetalle::scopePrecioManual) de un
     * query de Venta ya filtrado: cuántas son y cuánto se cobró por debajo / por encima
     * del precio de lista. No modifica el builder recibido.
     */
    public static function resumen(Builder $ventasQuery): array
    {
        $referencia = 'COALESCE(vd_ajuste.precio_lista_original, vd_ajuste.precio_venta)';
        $aplicado = 'COALESCE(vd_ajuste.precio_aplicado, vd_ajuste.precio_venta)';

        $fila = (clone $ventasQuery)
            ->reorder()
            ->join('venta_detalles as vd_ajuste', 'vd_ajuste.venta_id', '=', 'ventas.id')
            ->whereNotNull('vd_ajuste.motivo_precio')
            ->where('vd_ajuste.motivo_precio', '!=', '')
            ->selectRaw("
                COUNT(*) as lineas,
                COUNT(DISTINCT ventas.id) as ventas,
                SUM(CASE WHEN {$referencia} > {$aplicado} THEN ({$referencia} - {$aplicado}) * vd_ajuste.cantidad ELSE 0 END) as monto_abajo,
                SUM(CASE WHEN {$aplicado} > {$referencia} THEN ({$aplicado} - {$referencia}) * vd_ajuste.cantidad ELSE 0 END) as monto_arriba
            ")
            ->first();

        return [
            'lineas'       => (int) ($fila->lineas ?? 0),
            'ventas'       => (int) ($fila->ventas ?? 0),
            'monto_abajo'  => round((float) ($fila->monto_abajo ?? 0), 2),
            'monto_arriba' => round((float) ($fila->monto_arriba ?? 0), 2),
        ];
    }
}
