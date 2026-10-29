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

    /**
     * IDs de las categorías indicadas más todas sus subcategorías (cualquier nivel),
     * para que filtrar por "CELULARES" incluya también "CELULARES › LIBRE".
     *
     * @param  int|int[]  $ids
     * @return int[]
     */
    public static function idsConDescendientes(int $empresaId, int|array $ids): array
    {
        $resultado = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
        if ($resultado === []) {
            return [];
        }

        $hijosPorPadre = self::withTrashed()
            ->where('empresa_id', $empresaId)
            ->whereNotNull('categoria_padre_id')
            ->get(['id', 'categoria_padre_id'])
            ->groupBy('categoria_padre_id')
            ->map(fn($hijos) => $hijos->pluck('id')->map(fn($id) => (int) $id)->all());

        $pendientes = $resultado;
        while ($pendientes !== []) {
            $padre = array_pop($pendientes);
            foreach ($hijosPorPadre->get($padre, []) as $hijo) {
                // in_array protege de ciclos en datos corruptos.
                if (! in_array($hijo, $resultado, true)) {
                    $resultado[] = $hijo;
                    $pendientes[] = $hijo;
                }
            }
        }

        return $resultado;
    }

    /**
     * Ruta completa ("CELULARES › LIBRE") de las categorías indicadas.
     *
     * @param  iterable<int>  $ids
     * @return array<int, string> id => ruta
     */
    public static function rutasPorId(int $empresaId, iterable $ids): array
    {
        $ids = collect($ids)->filter()->map(fn($id) => (int) $id)->unique();
        if ($ids->isEmpty()) {
            return [];
        }

        $categorias = self::withTrashed()
            ->where('empresa_id', $empresaId)
            ->get(['id', 'nombre', 'categoria_padre_id'])
            ->keyBy('id');

        return $ids->mapWithKeys(function ($id) use ($categorias) {
            $nombres = [];
            $actual = $categorias->get($id);
            // Límite de profundidad para protegerse de ciclos en datos corruptos.
            while ($actual && count($nombres) < 20) {
                array_unshift($nombres, $actual->nombre);
                $actual = $actual->categoria_padre_id ? $categorias->get($actual->categoria_padre_id) : null;
            }

            return [$id => implode(' › ', $nombres)];
        })->filter()->all();
    }

    /**
     * Para listas de filtros: reemplaza el nombre de cada categoría por su ruta
     * completa ("CELULARES › LIBRE") y ordena por ella, así las subcategorías
     * quedan debajo de su padre.
     *
     * @param  iterable<object>  $categorias  filas con id y nombre
     */
    public static function nombresConRuta(int $empresaId, iterable $categorias): \Illuminate\Support\Collection
    {
        $categorias = collect($categorias);
        $rutas = self::rutasPorId($empresaId, $categorias->pluck('id'));

        return $categorias
            ->each(function ($categoria) use ($rutas) {
                $categoria->nombre = $rutas[(int) $categoria->id] ?? $categoria->nombre;
            })
            ->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

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