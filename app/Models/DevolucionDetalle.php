<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevolucionDetalle extends Model
{
    protected $table = 'devolucion_detalles';

    protected $fillable = [
        'devolucion_id',
        'venta_detalle_id',
        'producto_id',
        'variante_id',
        'serie_id',
        'cantidad',
        'precio_unitario',
        'importe',
    ];

    protected $casts = [
        'cantidad' => 'decimal:3',
        'precio_unitario' => 'decimal:2',
        'importe' => 'decimal:2',
    ];

    public function devolucion(): BelongsTo
    {
        return $this->belongsTo(Devolucion::class);
    }

    public function ventaDetalle(): BelongsTo
    {
        return $this->belongsTo(VentaDetalle::class);
    }
}
