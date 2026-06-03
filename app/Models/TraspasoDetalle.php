<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TraspasoDetalle extends Model
{
    protected $table = 'traspaso_detalles';

    protected $fillable = [
        'traspaso_id',
        'producto_id',
        'variante_id',
        'serie_id',
        'producto_nombre',
        'variante_nombre',
        'serie_identificador',
        'cantidad',
        'precio_costo',
        'precio_venta',
        'cantidad_recibida',
        'estado',
    ];

    protected $casts = [
        'cantidad' => 'decimal:3',
        'precio_costo' => 'decimal:2',
        'precio_venta' => 'decimal:2',
        'cantidad_recibida' => 'decimal:3',
    ];

    public function traspaso()
    {
        return $this->belongsTo(Traspaso::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function variante()
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }

    public function serie()
    {
        return $this->belongsTo(Serie::class);
    }
}
