<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DevolucionProveedorDetalle extends Model
{
    protected $table = 'devolucion_proveedor_detalles';

    protected $fillable = [
        'devolucion_proveedor_id', 'compra_detalle_id', 'producto_id',
        'variante_id', 'cantidad', 'precio_compra', 'subtotal',
    ];

    public function series()
    {
        return $this->belongsToMany(Serie::class, 'devolucion_proveedor_series');
    }
}
