<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompraDetalle extends Model
{
    protected $fillable = [
        'compra_id', 'producto_id', 'variante_id',
        'cantidad', 'precio_compra', 'precio_venta', 'subtotal',
    ];

    protected $casts = [
        'cantidad'      => 'decimal:3',
        'precio_compra' => 'decimal:2',
        'precio_venta'  => 'decimal:2',
        'subtotal'      => 'decimal:2',
    ];

    public function compra(): BelongsTo   { return $this->belongsTo(Compra::class); }
    public function producto(): BelongsTo { return $this->belongsTo(Producto::class); }
    public function variante(): BelongsTo { return $this->belongsTo(ProductoVariante::class, 'variante_id'); }
}