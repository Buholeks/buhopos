<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PedidoDetalle extends Model
{
    protected $table = 'pedido_detalles';

    protected $fillable = [
        'pedido_id',
        'producto_id',
        'variante_id',
        'compra_detalle_id',
        'descripcion',
        'marca_texto',
        'modelo_texto',
        'color_texto',
        'talla_texto',
        'cantidad',
        'precio_acordado',
        'subtotal',
        'estado',
        'notas',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'precio_acordado' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }

    public function compraDetalle(): BelongsTo
    {
        return $this->belongsTo(CompraDetalle::class, 'compra_detalle_id');
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(InventarioReserva::class);
    }
}
