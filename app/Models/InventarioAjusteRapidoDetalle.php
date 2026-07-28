<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventarioAjusteRapidoDetalle extends Model
{
    protected $table = 'inventario_ajuste_rapido_detalles';

    protected $fillable = [
        'ajuste_id', 'producto_id', 'variante_id', 'cantidad',
        'stock_antes', 'stock_despues', 'serie_ids',
    ];

    protected $casts = [
        'cantidad' => 'decimal:3',
        'stock_antes' => 'decimal:3',
        'stock_despues' => 'decimal:3',
        'serie_ids' => 'array',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function variante()
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }
}
