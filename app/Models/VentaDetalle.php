<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentaDetalle extends Model
{
    protected $fillable = [
        'venta_id',
        'producto_id',
        'producto_nombre',
        'variante_id',
        'variante_nombre',
        'serie_id',
        'cantidad',
        'precio_venta',
        'precio_costo',
        'precio_lista_original',
        'precio_aplicado',
        'lista_precio_usada',
        'descuento',
        'subtotal',
        'motivo_precio',
    ];

    protected $casts = [
        'cantidad'     => 'decimal:3',
        'precio_venta' => 'decimal:2',
        'precio_costo' => 'decimal:2',
        'precio_lista_original' => 'decimal:2',
        'precio_aplicado' => 'decimal:2',
        'descuento'    => 'decimal:2',
        'subtotal'     => 'decimal:2',
    ];

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
    public function variante(): BelongsTo
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }
    public function serie()
    {
        return $this->belongsTo(Serie::class, 'serie_id');
    }
}
