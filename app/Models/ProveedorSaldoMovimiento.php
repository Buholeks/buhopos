<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProveedorSaldoMovimiento extends Model
{
    protected $fillable = [
        'empresa_id', 'sucursal_id', 'proveedor_id', 'user_id',
        'devolucion_proveedor_id', 'compra_id', 'tipo', 'monto',
        'saldo_resultante', 'concepto',
    ];
}
