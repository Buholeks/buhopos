<?php

namespace App\Models;

use App\Support\PublicImageStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Producto extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'productos';

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'user_id',
        'categoria_id',
        'marca_id',
        'modelo_id',
        'unidad_medida_id',
        'nombre',
        'codigo',
        'descripcion',
        'imagen',
        'precio_costo',
        'precio_venta',
        'precio1',
        'precio2',
        'precio3',
        'precio4',
        'precio5',
        'stock_minimo',
        'peso',
        'tiene_variantes',
        'tiene_series',
        'activo',
        // NOTA: precio_oferta/oferta_activa/oferta_hasta se manejan por variante
    ];

    protected $casts = [
        'precio_costo'    => 'decimal:2',
        'precio_venta'    => 'decimal:2',
        'precio1'         => 'decimal:2',
        'precio2'         => 'decimal:2',
        'precio3'         => 'decimal:2',
        'precio4'         => 'decimal:2',
        'precio5'         => 'decimal:2',
        'stock_minimo'    => 'decimal:2',
        'peso'            => 'decimal:3',
        'tiene_variantes' => 'boolean',
        'activo'          => 'boolean',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────────

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }
    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }
    public function marca()
    {
        return $this->belongsTo(Marca::class);
    }
    public function modelo()
    {
        return $this->belongsTo(Modelo::class);
    }
    public function unidadMedida()
    {
        return $this->belongsTo(UnidadMedida::class);
    }

    public function variantes()
    {
        return $this->hasMany(ProductoVariante::class)->orderBy('id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeDeEmpresa($query, int $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────


    /** Genera código SKU automático para esta empresa */
    public static function generarCodigo(int $empresaId): string
    {
        $total = self::where('empresa_id', $empresaId)->withTrashed()->count() + 1;
        return 'PROD' . str_pad($total, 5, '0', STR_PAD_LEFT);
    }



    protected $appends = ['imagen_url'];

    public function getImagenUrlAttribute(): ?string
    {
        $imagen = $this->attributes['imagen'] ?? null;

        return PublicImageStorage::url($imagen);
    }
}
