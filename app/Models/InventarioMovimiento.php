<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventarioMovimiento extends Model
{
    protected $table = 'inventario_movimientos';

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'producto_id',
        'variante_id',
        'conteo_id',
        'conteo_detalle_id',
        'user_id',
        'tipo',
        'cantidad_anterior',
        'cantidad_movimiento',
        'cantidad_nueva',
        'motivo',
    ];

    protected $casts = [
        'cantidad_anterior' => 'decimal:3',
        'cantidad_movimiento' => 'decimal:3',
        'cantidad_nueva' => 'decimal:3',
    ];
}
