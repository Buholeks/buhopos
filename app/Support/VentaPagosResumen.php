<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class VentaPagosResumen
{
    /**
     * Suma los montos cobrados por forma_pago (efectivo, tarjeta, transferencia,
     * saldo_favor) a partir de venta_pagos, para un query de Venta ya filtrado
     * (empresa/sucursal/fecha/corte/etc). No modifica el builder recibido.
     */
    public static function porFormaPago(Builder $ventasQuery): array
    {
        $fila = (clone $ventasQuery)
            ->join('venta_pagos', 'venta_pagos.venta_id', '=', 'ventas.id')
            ->selectRaw("
                SUM(CASE WHEN venta_pagos.forma_pago = 'efectivo'      THEN venta_pagos.monto ELSE 0 END) as efectivo,
                SUM(CASE WHEN venta_pagos.forma_pago = 'tarjeta'       THEN venta_pagos.monto ELSE 0 END) as tarjeta,
                SUM(CASE WHEN venta_pagos.forma_pago = 'transferencia' THEN venta_pagos.monto ELSE 0 END) as transferencia,
                SUM(CASE WHEN venta_pagos.forma_pago = 'saldo_favor'   THEN venta_pagos.monto ELSE 0 END) as saldo_favor
            ")
            ->first();

        return [
            'efectivo'      => (float) ($fila->efectivo ?? 0),
            'tarjeta'       => (float) ($fila->tarjeta ?? 0),
            'transferencia' => (float) ($fila->transferencia ?? 0),
            'saldo_favor'   => (float) ($fila->saldo_favor ?? 0),
        ];
    }
}
