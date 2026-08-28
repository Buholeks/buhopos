<?php

namespace App\Servicios;

use App\Models\Producto;
use App\Models\ProductoVarianteGrupo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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

        $tipo = $tipos->first(fn($item) => in_array($this->normalizar($item['nombre']), self::NOMBRES_VISUALES, true))
            ?: $tipos->sortBy('id')->first();

        if (! $tipo) {
            return collect();
        }

        $atributos = $variantes->flatMap->atributos
            ->filter(fn($relacion) => (int) $relacion->tipo_atributo_id === (int) $tipo['id'])
            ->unique('atributo_id');

        return $atributos->map(function ($relacion) use ($producto, $tipo) {
            return ProductoVarianteGrupo::firstOrCreate(
                [
                    'producto_id' => $producto->id,
                    'tipo_atributo_id' => (int) $tipo['id'],
                    'atributo_id' => (int) $relacion->atributo_id,
                ],
                [
                    'empresa_id' => $producto->empresa_id,
                    'codigo' => $this->codigoDisponible($producto, (int) $relacion->atributo_id),
                    'es_personalizado' => false,
                ]
            );
        })->values();
    }

    private function codigoDisponible(Producto $producto, int $atributoId): string
    {
        $base = mb_substr($producto->codigo . 'G' . $atributoId, 0, 100);
        $codigo = $base;
        $numero = 2;

        while (ProductoVarianteGrupo::where('empresa_id', $producto->empresa_id)->where('codigo', $codigo)->exists()) {
            $sufijo = '-' . $numero++;
            $codigo = mb_substr($base, 0, 100 - mb_strlen($sufijo)) . $sufijo;
        }

        return $codigo;
    }

    private function normalizar(string $valor): string
    {
        return Str::of($valor)->ascii()->lower()->replaceMatches('/\s+/', '')->toString();
    }
}
