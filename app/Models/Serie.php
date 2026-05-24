<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Serie extends Model
{
    protected $table = 'series';

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'producto_id',
        'variante_id',
        'compra_id',
        'proveedor_id',
        'venta_id',
        'venta_detalle_id',
        'imei',
        'imei2',
        'serie',
        'precio_costo',
        'precio_venta',
        'estado',
        'notas',
    ];

    protected $casts = [
        'precio_costo' => 'decimal:2',
        'precio_venta' => 'decimal:2',
    ];

    // ── Constantes ────────────────────────────────────────────────────────────
    const ESTADOS = [
        'disponible' => 'Disponible',
        'vendido'    => 'Vendido',
        'apartado'   => 'Apartado',
        'devuelto'   => 'Devuelto',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────────
    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function variante()
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }

    public function compra()
    {
        return $this->belongsTo(Compra::class);
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function ventaDetalle()
    {
        return $this->belongsTo(VentaDetalle::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    public function scopeDisponibles($query)
    {
        return $query->where('estado', 'disponible');
    }

    public function scopeDeEmpresa($query, int $empresaId, int $sucursalId)
    {
        return $query->where('empresa_id', $empresaId)
                     ->where('sucursal_id', $sucursalId);
    }

    public function scopeDeProducto($query, int $productoId, ?int $varianteId = null)
    {
        return $query->where('producto_id', $productoId)
                     ->when($varianteId, fn($q) => $q->where('variante_id', $varianteId));
    }

    // ── Accessor: identificador principal para mostrar ────────────────────────
    public function getIdentificadorAttribute(): string
    {
        if ($this->imei)  return $this->imei;
        if ($this->serie) return $this->serie;
        return "ID#{$this->id}";
    }

    // ── Buscar por IMEI o serie en toda la empresa ────────────────────────────
    public static function buscarIdentificador(string $valor, int $empresaId): ?self
    {
        return self::where('empresa_id', $empresaId)
            ->where(fn($q) =>
                $q->where('imei', $valor)
                  ->orWhere('imei2', $valor)
                  ->orWhere('serie', $valor)
            )
            ->first();
    }

    // ── Marcar como vendido ───────────────────────────────────────────────────
    public function marcarVendido(int $ventaId, int $ventaDetalleId): void
    {
        $this->update([
            'estado'           => 'vendido',
            'venta_id'         => $ventaId,
            'venta_detalle_id' => $ventaDetalleId,
        ]);
    }

    // ── Revertir venta (cancelación) ──────────────────────────────────────────
    public function revertirVenta(): void
    {
        $this->update([
            'estado'           => 'devuelto',
            'venta_id'         => null,
            'venta_detalle_id' => null,
        ]);
    }

    // ── Validar que el identificador no esté duplicado en la empresa ──────────
    public static function existeEnEmpresa(string $imei, int $empresaId, ?int $excludeId = null): bool
    {
        return self::where('empresa_id', $empresaId)
            ->where(fn($q) =>
                $q->where('imei', $imei)
                  ->orWhere('imei2', $imei)
                  ->orWhere('serie', $imei)
            )
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->exists();
    }
}