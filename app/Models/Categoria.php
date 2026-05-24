<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Categoria extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'categorias';

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'user_id',               // ← user_id (alineado con migración y controller)
        'categoria_padre_id',
        'nombre',
        'slug',
        'descripcion',
        'activo',
        'orden',
        'profundidad',
    ];

    protected $casts = [
        'activo'      => 'boolean',
        'orden'       => 'integer',
        'profundidad' => 'integer',
    ];

    // ─── Relaciones ───────────────────────────────────────────────────────────

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

    /** Categoría padre directa */
    public function padre()
    {
        return $this->belongsTo(Categoria::class, 'categoria_padre_id');
    }

    /** Hijos directos (un nivel) */
    public function hijos()
    {
        return $this->hasMany(Categoria::class, 'categoria_padre_id')
                    ->orderBy('orden')
                    ->orderBy('nombre');
    }

    /**
     * Hijos de forma recursiva infinita.
     * Laravel serializa esto como "hijos_recursivos" en el JSON.
     * El componente Vue debe usar ese nombre exacto.
     */
    public function hijosRecursivos()
    {
        return $this->hijos()->with('hijosRecursivos');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /** Filtrar siempre por empresa (y opcionalmente por sucursal) */
    public function scopeDeEmpresa($query, int $empresaId, ?int $sucursalId = null)
    {
        $query->where('empresa_id', $empresaId);

        if (! is_null($sucursalId)) {
            $query->where('sucursal_id', $sucursalId);
        }

        return $query;
    }

    /** Solo categorías raíz (sin padre) */
    public function scopeRaiz($query)
    {
        return $query->whereNull('categoria_padre_id');
    }

    /** Solo activas */
    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    // ─── Mutator ─────────────────────────────────────────────────────────────
    // Solo almacena el nombre. El slug lo genera el evento booted() → saving.

    public function setNombreAttribute($value): void
    {
        $this->attributes['nombre'] = $value;
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** Calcula la profundidad según el padre */
    public function calcularProfundidad(): int
    {
        if (! $this->categoria_padre_id) return 0;

        $padre = self::find($this->categoria_padre_id);

        return $padre ? ($padre->profundidad + 1) : 0;
    }

    // ─── Eventos del modelo ───────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::saving(function (Categoria $categoria) {
            // Sin empresa_id no podemos garantizar unicidad del slug
            if (empty($categoria->empresa_id)) return;

            // Regenerar slug si: es nuevo, cambió el nombre o cambió el padre
            if (
                empty($categoria->slug) ||
                $categoria->isDirty('nombre') ||
                $categoria->isDirty('categoria_padre_id')
            ) {
                $categoria->slug = $categoria->generarSlugPorContexto();
            }
        });
    }

    /** Genera slug prefijado con el slug del padre para evitar colisiones entre niveles */
    private function generarSlugPorContexto(): string
    {
        $base = Str::slug($this->nombre);

        if (! empty($this->categoria_padre_id)) {
            $padre = self::select('id', 'nombre', 'slug')
                         ->find($this->categoria_padre_id);

            if ($padre) {
                $slugPadre = $padre->slug ?: Str::slug($padre->nombre);
                $base      = $slugPadre . '-' . $base;
            }
        }

        return $this->generarSlugUnico($base);
    }

    /** Garantiza unicidad del slug dentro de la misma empresa */
    private function generarSlugUnico(string $base): string
    {
        $slug     = $base;
        $contador = 1;

        while (
            self::where('empresa_id', $this->empresa_id)
                ->where('slug', $slug)
                ->where('id', '!=', $this->id ?? 0)
                ->exists()
        ) {
            $slug = "{$base}-{$contador}";
            $contador++;
        }

        return $slug;
    }
}