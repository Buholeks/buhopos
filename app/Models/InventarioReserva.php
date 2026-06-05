<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioReserva extends Model
{
    protected $table = 'inventario_reservas';

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'pedido_id',
        'pedido_detalle_id',
        'producto_id',
        'variante_id',
        'cantidad',
        'estado',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }
}
