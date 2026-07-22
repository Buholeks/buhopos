<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventarioExhibicion extends Model
{
    protected $table = 'inventario_exhibiciones';

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'producto_id',
        'variante_id',
        'atributo_id',
        'user_id',
        'tipo_cobertura',
        'estado_exhibicion',
        'estado',
        'activo',
        'venta_id',
        'venta_detalle_id',
        'vendido_at',
        'retirado_at',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'vendido_at' => 'datetime',
        'retirado_at' => 'datetime',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function variante()
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }

    public function atributo()
    {
        return $this->belongsTo(Atributo::class, 'atributo_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActivas($query)
    {
        return $query->where('activo', true)->where('estado', 'activa');
    }

    public function scopeDeSucursal($query, int $empresaId, int $sucursalId)
    {
        return $query->where('empresa_id', $empresaId)->where('sucursal_id', $sucursalId);
    }

    public function vender(int $ventaId, int $ventaDetalleId): void
    {
        $this->update([
            'activo' => false,
            'estado' => 'vendida',
            'venta_id' => $ventaId,
            'venta_detalle_id' => $ventaDetalleId,
            'vendido_at' => now(),
        ]);
    }

    public function retirar(): void
    {
        $this->update([
            'activo' => false,
            'estado' => 'retirada',
            'retirado_at' => now(),
        ]);
    }
}
