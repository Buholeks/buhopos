<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductoVariante extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'producto_variantes';

    protected $fillable = [
        'producto_id',
        'empresa_id',
        'sku',
        'codigo_barras',
        'imagen',
        'precio_costo',
        'precio_venta',
        'precio1',
        'precio2',
        'precio3',
        'precio4',
        'precio5',
        'precio_oferta',
        'oferta_activa',
        'oferta_hasta',
        'stock_minimo',
        'activo',
    ];

    protected $casts = [
        'precio_costo'  => 'decimal:2',
        'precio_venta'  => 'decimal:2',
        'precio1'       => 'decimal:2',
        'precio2'       => 'decimal:2',
        'precio3'       => 'decimal:2',
        'precio4'       => 'decimal:2',
        'precio5'       => 'decimal:2',
        'precio_oferta' => 'decimal:2',
        'oferta_activa' => 'boolean',
        'oferta_hasta'  => 'date',
        'stock_minimo'  => 'decimal:2',
        'activo'        => 'boolean',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────────

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function atributos()
    {
        return $this->hasMany(VarianteAtributo::class, 'variante_id')
            ->with('tipoAtributo', 'atributo');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Devuelve el valor de un campo de precio.
     * Si la variante tiene NULL en ese campo → hereda del producto padre.
     */
    public function precio(string $campo): float
    {
        return (float) ($this->{$campo} ?? $this->producto?->{$campo} ?? 0);
    }

    /**
     * Precio vigente de la variante.
     * Considera oferta_activa y oferta_hasta propios.
     * Si precio_venta es NULL hereda del producto padre.
     */
    public function precioVigente(): float
    {
        if (
            $this->oferta_activa &&
            $this->precio_oferta > 0 &&
            (! $this->oferta_hasta || $this->oferta_hasta->isFuture())
        ) {
            return (float) $this->precio_oferta;
        }

        return $this->precio('precio_venta');
    }

    /** Nombre legible: "Rojo / XL" basado en los atributos */
    public function nombreVariante(): string
    {
        // Cargar relaciones si aún no están cargadas
        if (! $this->relationLoaded('atributos')) {
            $this->load(['atributos.tipoAtributo', 'atributos.atributo']);
        }

        return $this->atributos
            ->sortBy(fn($va) => $va->tipoAtributo?->nombre)
            ->map(fn($va) => $va->atributo?->valor)
            ->filter()
            ->join(' / ');
    }

    /** Genera SKU automático */
    public static function generarSku(int $productoId, int $empresaId): string
    {
        $count = self::where('producto_id', $productoId)->withTrashed()->count() + 1;
        return "P{$productoId}V" . str_pad($count, 2, '0', STR_PAD_LEFT);
    }


    protected $appends = ['imagen_url'];

    public function getImagenUrlAttribute(): ?string
    {
        $imagen = $this->attributes['imagen'] ?? null;

        if ($imagen) {
            return asset('storage/' . ltrim($imagen, '/'));
        }

        return $this->producto?->imagen_url;
    }
}
