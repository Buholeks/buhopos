<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComisionRegla extends Model
{
    protected $table = 'comision_reglas';

    // Orden de resolución: del más específico al más general.
    public const APLICA_PRODUCTO  = 'producto';
    public const APLICA_CATEGORIA = 'categoria';
    public const APLICA_GENERAL   = 'general';
    public const APLICA_A = [self::APLICA_PRODUCTO, self::APLICA_CATEGORIA, self::APLICA_GENERAL];

    public const BASE_VENTA    = 'venta';
    public const BASE_UTILIDAD = 'utilidad';
    public const BASES = [self::BASE_VENTA, self::BASE_UTILIDAD];

    public const TIPO_PORCENTAJE     = 'porcentaje';
    public const TIPO_FIJO_POR_PIEZA = 'fijo_por_pieza';
    public const TIPOS = [self::TIPO_PORCENTAJE, self::TIPO_FIJO_POR_PIEZA];

    public const FUERA_USAR_SIGUIENTE = 'usar_siguiente';
    public const FUERA_SIN_COMISION   = 'sin_comision';
    public const FUERA_DE_RANGO = [self::FUERA_USAR_SIGUIENTE, self::FUERA_SIN_COMISION];

    protected $fillable = [
        'empresa_id',
        'esquema_id',
        'aplica_a',
        'referencia_id',
        'base',
        'tipo',
        'valor',
        'precio_min',
        'precio_max',
        'fuera_de_rango',
        'activo',
    ];

    protected $casts = [
        'valor'      => 'decimal:4',
        'precio_min' => 'decimal:2',
        'precio_max' => 'decimal:2',
        'activo'     => 'boolean',
    ];

    public function esquema(): BelongsTo
    {
        return $this->belongsTo(ComisionEsquema::class, 'esquema_id');
    }

    public function precios(): HasMany
    {
        return $this->hasMany(ComisionReglaPrecio::class, 'regla_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'referencia_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'referencia_id');
    }

    public function tieneRango(): bool
    {
        return $this->precio_min !== null || $this->precio_max !== null;
    }

    public function cubrePrecio(float $precio): bool
    {
        if ($this->precio_min !== null && $precio < (float) $this->precio_min) {
            return false;
        }
        if ($this->precio_max !== null && $precio > (float) $this->precio_max) {
            return false;
        }
        return true;
    }
}
