<?php

namespace App\Models;

use App\Support\PublicImageStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;

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
        if (self::ofertaVigente($this->oferta_activa, $this->precio_oferta, $this->oferta_hasta)) {
            return (float) $this->precio_oferta;
        }

        return $this->precio('precio_venta');
    }

    /**
     * Determina si una oferta sigue vigente. "oferta_hasta" es una fecha (sin
     * hora), así que se compara contra el fin de ese día: una oferta con
     * "válida hasta" hoy debe seguir vigente durante todo el día de hoy, no
     * solo hasta la medianoche.
     */
    public static function ofertaVigente($ofertaActiva, $precioOferta, $ofertaHasta): bool
    {
        if (! $ofertaActiva || (float) $precioOferta <= 0) {
            return false;
        }

        if (! $ofertaHasta) {
            return true;
        }

        $hasta = $ofertaHasta instanceof Carbon ? $ofertaHasta : Carbon::parse($ofertaHasta);

        return $hasta->copy()->endOfDay()->isFuture();
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
        $codigoProducto = Producto::where('empresa_id', $empresaId)
            ->whereKey($productoId)
            ->value('codigo');

        if ($codigoProducto === null) {
            throw new \InvalidArgumentException('El producto no pertenece a la empresa indicada.');
        }

        $consecutivo = self::where('empresa_id', $empresaId)
            ->where('producto_id', $productoId)
            ->withTrashed()
            ->count() + 1;

        do {
            $sufijo = str_pad($consecutivo, 2, '0', STR_PAD_LEFT);
            $prefijo = mb_substr($codigoProducto, 0, 100 - mb_strlen($sufijo));
            $sku = $prefijo . $sufijo;
            $consecutivo++;
        } while (
            self::where('empresa_id', $empresaId)
                ->where('sku', $sku)
                ->withTrashed()
                ->exists()
        );

        return $sku;
    }


    protected $appends = ['imagen_url'];

    public function getImagenUrlAttribute(): ?string
    {
        $imagen = $this->attributes['imagen'] ?? null;

        if ($imagen) {
            return PublicImageStorage::url($imagen);
        }

        return $this->producto?->imagen_url;
    }
}
