<?php

namespace App\Servicios;

use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\ProductoVarianteGrupo;
use App\Models\TipoAtributo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class VarianteGrupoServicio
{
    private const NOMBRES_VISUALES = ['color', 'colores', 'colors', 'colour', 'colours'];

    public function sincronizar(Producto $producto): Collection
    {
        $variantes = $producto->variantes()
            ->with(['atributos.tipoAtributo:id,nombre', 'atributos.atributo:id,valor'])
            ->get();

        $tipos = $variantes->flatMap->atributos
            ->map(fn($relacion) => [
                'id' => (int) $relacion->tipo_atributo_id,
                'nombre' => $relacion->tipoAtributo?->nombre ?? '',
            ])
            ->unique('id')
            ->values();

        $tipo = $this->tipoAgrupador($tipos);

        if (! $tipo) {
            return collect();
        }

        $atributos = $variantes->flatMap->atributos
            ->filter(fn($relacion) => (int) $relacion->tipo_atributo_id === (int) $tipo['id'])
            ->unique('atributo_id');

        return $atributos
            ->map(fn($relacion) => $this->obtenerGrupo($producto, (int) $tipo['id'], (int) $relacion->atributo_id))
            ->values();
    }

    /**
     * SKU para una variante nueva. Si el producto es agrupable usa el código del grupo
     * (p. ej. color) + consecutivo; si no, el SKU por producto. Las variantes existentes
     * no se tocan, aunque después cambie el código del grupo.
     *
     * @param  iterable<int, int>  $atributos  tipo_atributo_id => atributo_id de la variante nueva
     */
    public function generarSku(Producto $producto, iterable $atributos): string
    {
        $atributos = collect($atributos)->mapWithKeys(fn($atributoId, $tipoId) => [(int) $tipoId => (int) $atributoId]);

        $tipoIds = DB::table('variante_atributos as va')
            ->join('producto_variantes as pv', 'pv.id', '=', 'va.variante_id')
            ->where('pv.producto_id', $producto->id)
            ->whereNull('pv.deleted_at')
            ->distinct()
            ->pluck('va.tipo_atributo_id')
            ->map(fn($id) => (int) $id)
            ->merge($atributos->keys())
            ->unique();

        $tipos = TipoAtributo::withTrashed()
            ->whereIn('id', $tipoIds)
            ->get(['id', 'nombre'])
            ->map(fn($tipo) => ['id' => (int) $tipo->id, 'nombre' => $tipo->nombre ?? '']);

        $tipo = $this->tipoAgrupador($tipos);
        $atributoId = $tipo ? $atributos->get($tipo['id']) : null;

        if (! $atributoId) {
            return ProductoVariante::generarSku($producto->id, $producto->empresa_id);
        }

        $grupo = $this->obtenerGrupo($producto, $tipo['id'], $atributoId);

        return ProductoVariante::generarSkuEnGrupo($grupo);
    }

    private function tipoAgrupador(Collection $tipos): ?array
    {
        if ($tipos->count() < 2) {
            return null;
        }

        return $tipos->first(fn($item) => in_array($this->normalizar($item['nombre']), self::NOMBRES_VISUALES, true))
            ?: $tipos->sortBy('id')->first();
    }

    private function obtenerGrupo(Producto $producto, int $tipoId, int $atributoId): ProductoVarianteGrupo
    {
        return ProductoVarianteGrupo::firstOrCreate(
            [
                'producto_id' => $producto->id,
                'tipo_atributo_id' => $tipoId,
                'atributo_id' => $atributoId,
            ],
            [
                'empresa_id' => $producto->empresa_id,
                'codigo' => $this->codigoDisponible($producto, $atributoId),
                'es_personalizado' => false,
            ]
        );
    }

    public function productoEsAgrupable(int $productoId): bool
    {
        return DB::table('variante_atributos as va')
            ->join('producto_variantes as pv', 'pv.id', '=', 'va.variante_id')
            ->where('pv.producto_id', $productoId)
            ->whereNull('pv.deleted_at')
            ->distinct()
            ->count('va.tipo_atributo_id') >= 2;
    }

    private function codigoDisponible(Producto $producto, int $atributoId): string
    {
        $base = mb_substr($producto->codigo . 'G' . $atributoId, 0, 100);
        $codigo = $base;
        $numero = 2;

        while (ProductoVarianteGrupo::where('empresa_id', $producto->empresa_id)->where('codigo', $codigo)->exists()) {
            // Sin guion: algunos lectores de código de barras no lo leen bien
            $sufijo = 'R' . $numero++;
            $codigo = mb_substr($base, 0, 100 - mb_strlen($sufijo)) . $sufijo;
        }

        return $codigo;
    }

    private function normalizar(string $valor): string
    {
        return Str::of($valor)->ascii()->lower()->replaceMatches('/\s+/', '')->toString();
    }
}
