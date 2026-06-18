<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventarioConteoDetalle extends Model
{
    protected $table = 'inventario_conteo_detalles';

    protected $fillable = [
        'conteo_id',
        'producto_id',
        'variante_id',
        'stock_sistema',
        'cantidad_fisica',
        'diferencia',
        'costo_unitario',
        'estado',
        'series_contadas',
        'notas',
    ];

    protected $casts = [
        'stock_sistema' => 'decimal:3',
        'cantidad_fisica' => 'decimal:3',
        'diferencia' => 'decimal:3',
        'costo_unitario' => 'decimal:2',
        'series_contadas' => 'array',
    ];

    public function conteo()
    {
        return $this->belongsTo(InventarioConteo::class, 'conteo_id');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function variante()
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }
}
