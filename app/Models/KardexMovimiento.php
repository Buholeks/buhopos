<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KardexMovimiento extends Model
{
    protected $table = 'kardex_movimientos';

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'producto_id',
        'variante_id',
        'serie_id',
        'user_id',
        'tipo',
        'direccion',
        'cantidad',
        'entrada',
        'salida',
        'stock_antes',
        'stock_despues',
        'costo_unitario',
        'precio_unitario',
        'importe',
        'referencia_tipo',
        'referencia_id',
        'referencia_detalle_id',
        'folio',
        'motivo',
        'notas',
        'metadata',
        'fecha',
    ];

    protected $casts = [
        'cantidad' => 'decimal:3',
        'entrada' => 'decimal:3',
        'salida' => 'decimal:3',
        'stock_antes' => 'decimal:3',
        'stock_despues' => 'decimal:3',
        'costo_unitario' => 'decimal:4',
        'precio_unitario' => 'decimal:4',
        'importe' => 'decimal:2',
        'metadata' => 'array',
        'fecha' => 'datetime',
    ];
}
