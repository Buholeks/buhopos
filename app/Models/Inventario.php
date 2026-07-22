<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventario extends Model
{
    protected $table = 'inventario';

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'producto_id',
        'variante_id',
        'stock',
        'stock_minimo',
        'variante_exhibida_id',
        'estado_exhibicion',
    ];

    protected $casts = [
        'stock' => 'decimal:3',
        'stock_minimo' => 'decimal:3',
        'variante_exhibida_id' => 'integer',
    ];

    public const ESTADOS_EXHIBICION = [
        'perfecto' => 'Perfecto',
        'caja_abierta' => 'Caja abierta',
        'con_detalles' => 'Con detalles',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function variante()
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }

    public function varianteExhibida()
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_exhibida_id');
    }

    public function scopeExhibidos($query)
    {
        return $query->whereExists(fn($sub) => $sub
            ->selectRaw('1')
            ->from('inventario_exhibiciones as ie')
            ->whereColumn('ie.producto_id', 'inventario.producto_id')
            ->whereColumn('ie.empresa_id', 'inventario.empresa_id')
            ->whereColumn('ie.sucursal_id', 'inventario.sucursal_id')
            ->where('ie.activo', true)
            ->where('ie.estado', 'activa'));
    }

    public function scopeSinExhibicion($query)
    {
        return $query->where('stock', '>', 0)
            ->whereNotExists(fn($sub) => $sub
                ->selectRaw('1')
                ->from('inventario_exhibiciones as ie')
                ->whereColumn('ie.producto_id', 'inventario.producto_id')
                ->whereColumn('ie.empresa_id', 'inventario.empresa_id')
                ->whereColumn('ie.sucursal_id', 'inventario.sucursal_id')
                ->where('ie.activo', true)
                ->where('ie.estado', 'activa'));
    }

    public function scopeSinStock($query)
    {
        return $query->where('stock', '<=', 0);
    }

    public function scopeDeSucursal($query, int $empresaId, int $sucursalId)
    {
        return $query->where('inventario.empresa_id', $empresaId)
            ->where('inventario.sucursal_id', $sucursalId);
    }

    public function getStockBodegaAttribute(): float
    {
        return (float) $this->stock;
    }

    public function getEstadoExhibicionLabelAttribute(): ?string
    {
        if (! $this->estado_exhibicion) {
            return null;
        }

        return self::ESTADOS_EXHIBICION[$this->estado_exhibicion] ?? $this->estado_exhibicion;
    }

    public function marcarExhibido(string $estado, ?int $varianteExhibidaId = null): void
    {
        if ($this->stock <= 0) {
            throw new \RuntimeException('Sin stock disponible para exhibir.');
        }

        if (! array_key_exists($estado, self::ESTADOS_EXHIBICION)) {
            throw new \RuntimeException('Estado de exhibicion no valido.');
        }

        if ($varianteExhibidaId) {
            $stockVariante = self::where('empresa_id', $this->empresa_id)
                ->where('sucursal_id', $this->sucursal_id)
                ->where('producto_id', $this->producto_id)
                ->where('variante_id', $varianteExhibidaId)
                ->value('stock');

            if (! $stockVariante || $stockVariante <= 0) {
                throw new \RuntimeException('La variante seleccionada no tiene stock disponible.');
            }
        }

        InventarioExhibicion::create([
            'empresa_id' => $this->empresa_id,
            'sucursal_id' => $this->sucursal_id,
            'producto_id' => $this->producto_id,
            'variante_id' => $varianteExhibidaId,
            'user_id' => auth()->id(),
            'tipo_cobertura' => $varianteExhibidaId ? 'variante' : 'producto',
            'estado_exhibicion' => $estado,
            'estado' => 'activa',
            'activo' => true,
        ]);
    }

    public function quitarExhibicion(): void
    {
        InventarioExhibicion::deSucursal($this->empresa_id, $this->sucursal_id)
            ->activas()
            ->where('producto_id', $this->producto_id)
            ->where(function ($q) {
                $q->where('variante_id', $this->variante_id)
                    ->orWhere('tipo_cobertura', 'producto');
            })
            ->get()
            ->each
            ->retirar();
    }

    public function descontarVenta(float $cantidad, bool $eraExhibido = false): void
    {
        $this->decrement('stock', $cantidad);

        if ($eraExhibido || $this->fresh()->stock <= 0) {
            $this->quitarExhibicion();
        }
    }

    public static function nombresAtributoColor(): array
    {
        return ['color', 'colores', 'colors', 'colour', 'colours'];
    }
}
