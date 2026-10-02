<?php

namespace App\Support;

use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\ProductoVarianteGrupo;
use Illuminate\Database\Eloquent\Builder;

class ProductVariantSearch
{
    private const STOPWORDS = ['de', 'del', 'la', 'las', 'el', 'los', 'un', 'una', 'y'];

    public static function tokens(string $query): array
    {
        $normalized = self::normalize($query);
        if ($normalized === '') {
            return [];
        }

        return collect(explode(' ', $normalized))
            ->map(fn($token) => trim($token))
            ->filter(function ($token) {
                if ($token === '' || in_array($token, self::STOPWORDS, true)) {
                    return false;
                }

                if (preg_match('/^\d+$/', $token)) {
                    return true;
                }

                return mb_strlen($token, 'UTF-8') >= 2;
            })
            ->unique()
            ->values()
            ->all();
    }

    private const ACENTOS = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n', 'ç' => 'c',
    ];

    // No usar iconv('...//TRANSLIT...'): su tabla de transliteración depende
    // de la libc del servidor y en algunos entornos (glibc en producción)
    // inserta caracteres como ' o ~ antes de la letra (á → 'a, ñ → ~n), que
    // luego rompen la palabra en dos tokens que ya no hacen match.
    public static function quitarAcentos(string $textoMinusculas): string
    {
        return strtr($textoMinusculas, self::ACENTOS);
    }

    public static function normalize(?string $text): string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }

        $text = mb_strtolower($text, 'UTF-8');
        $text = self::quitarAcentos($text);
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text) ?? '';
        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    /**
     * Separa de la búsqueda las palabras que son códigos de grupo de variantes:
     * - "10096G183" (código exacto del grupo) → ese producto y ese color.
     * - "10096G" (código del producto + G) → todos los colores de ese producto.
     * Así "10096G183 chica" funciona aunque el SKU no contenga el código del grupo
     * (variantes viejas o grupos con código personalizado).
     *
     * @return array{0: string, 1: array<int, array{producto_id: int, atributo_id: int|null}>}
     *         [texto restante, filtros de grupo]
     */
    public static function separarGrupos(string $query, int $empresaId): array
    {
        $palabras = preg_split('/\s+/', trim($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($palabras === []) {
            return ['', []];
        }

        $grupos = ProductoVarianteGrupo::where('empresa_id', $empresaId)
            ->whereIn('codigo', $palabras)
            ->get(['producto_id', 'atributo_id', 'codigo'])
            ->keyBy(fn($grupo) => mb_strtolower($grupo->codigo));

        // "10096G": solo si 10096 es un producto que tiene grupos
        $codigosProducto = collect($palabras)
            ->filter(fn($palabra) => ! $grupos->has(mb_strtolower($palabra)) && preg_match('/^.+g$/i', $palabra))
            ->map(fn($palabra) => mb_substr($palabra, 0, -1));
        $productos = $codigosProducto->isEmpty()
            ? collect()
            : Producto::where('empresa_id', $empresaId)
                ->whereIn('codigo', $codigosProducto->all())
                ->whereIn('id', ProductoVarianteGrupo::where('empresa_id', $empresaId)->select('producto_id'))
                ->pluck('id', 'codigo')
                ->mapWithKeys(fn($id, $codigo) => [mb_strtolower($codigo) => (int) $id]);

        $restantes = [];
        $filtros = [];

        foreach ($palabras as $palabra) {
            $clave = mb_strtolower($palabra);

            if ($grupo = $grupos->get($clave)) {
                $filtros[] = ['producto_id' => (int) $grupo->producto_id, 'atributo_id' => (int) $grupo->atributo_id];
            } elseif (preg_match('/^.+g$/i', $palabra) && ($productoId = $productos->get(mb_substr($clave, 0, -1)))) {
                $filtros[] = ['producto_id' => $productoId, 'atributo_id' => null];
            } else {
                $restantes[] = $palabra;
            }
        }

        return [implode(' ', $restantes), $filtros];
    }

    /** Limita variantes a los grupos encontrados (varios grupos = cualquiera de ellos). */
    public static function applyGrupoFiltros(Builder $query, array $filtros): Builder
    {
        if ($filtros === []) {
            return $query;
        }

        return $query->where(function ($q) use ($filtros) {
            foreach ($filtros as $filtro) {
                $q->orWhere(function ($grupoQuery) use ($filtro) {
                    $grupoQuery->where('producto_variantes.producto_id', $filtro['producto_id']);

                    if ($filtro['atributo_id']) {
                        $grupoQuery->whereHas('atributos', fn($aq) => $aq->where('atributo_id', $filtro['atributo_id']));
                    }
                });
            }
        });
    }

    /**
     * Ordena en SQL antes del limit para que el recorte no deje fuera lo más relevante:
     * coincidencia exacta de SKU/código de barras, luego SKU que empieza con el texto.
     */
    public static function orderVariantesPorRelevancia(Builder $query, string $texto): Builder
    {
        $texto = trim($texto);
        if ($texto === '') {
            return $query->orderBy('producto_variantes.producto_id')->orderBy('producto_variantes.id');
        }

        $prefijo = addcslashes($texto, '\\%_') . '%';

        return $query
            ->orderByRaw(
                'CASE WHEN producto_variantes.sku = ? OR producto_variantes.codigo_barras = ? THEN 0 WHEN producto_variantes.sku LIKE ? THEN 1 ELSE 2 END',
                [$texto, $texto, $prefijo]
            )
            ->orderBy('producto_variantes.producto_id')
            ->orderBy('producto_variantes.id');
    }

    /** Igual que orderVariantesPorRelevancia, para productos sin variantes. */
    public static function orderProductosPorRelevancia(Builder $query, string $texto): Builder
    {
        $texto = trim($texto);
        $prefijo = addcslashes($texto, '\\%_') . '%';

        return $query
            ->orderByRaw(
                'CASE WHEN productos.codigo = ? THEN 0 WHEN productos.codigo LIKE ? THEN 1 ELSE 2 END',
                [$texto, $prefijo]
            )
            ->orderBy('productos.nombre');
    }

    public static function applyProductoTokens(Builder $query, array $tokens): Builder
    {
        foreach ($tokens as $token) {
            $query->where(fn($q) => $q
                ->where('nombre', 'like', "%{$token}%")
                ->orWhere('codigo', 'like', "%{$token}%"));
        }

        return $query;
    }

    public static function applyVarianteTokens(Builder $query, array $tokens): Builder
    {
        foreach ($tokens as $token) {
            $query->where(fn($q) => $q
                ->where('sku', 'like', "%{$token}%")
                ->orWhere('codigo_barras', 'like', "%{$token}%")
                ->orWhereHas('producto', fn($pq) => $pq
                    ->where('nombre', 'like', "%{$token}%")
                    ->orWhere('codigo', 'like', "%{$token}%"))
                ->orWhereHas('atributos.atributo', fn($aq) => $aq
                    ->where('valor', 'like', "%{$token}%"))
                ->orWhereHas('atributos.tipoAtributo', fn($tq) => $tq
                    ->where('nombre', 'like', "%{$token}%")));
        }

        return $query;
    }

    public static function productoText(Producto $producto): string
    {
        return self::normalize(implode(' ', [
            $producto->nombre,
            $producto->codigo,
        ]));
    }

    public static function varianteText(ProductoVariante $variante): string
    {
        $atributos = $variante->atributos
            ->flatMap(fn($va) => [
                $va->tipoAtributo?->nombre,
                $va->atributo?->valor,
            ])
            ->filter()
            ->join(' ');

        return self::normalize(implode(' ', [
            $variante->producto?->nombre,
            $variante->producto?->codigo,
            $variante->sku,
            $variante->codigo_barras,
            $variante->nombreVariante(),
            $atributos,
        ]));
    }

    public static function matches(array $tokens, string $searchText): bool
    {
        if ($tokens === []) {
            return false;
        }

        return collect($tokens)->every(fn($token) => str_contains($searchText, $token));
    }

    public static function score(array $tokens, string $query, array $fields, string $searchText): int
    {
        $normalizedQuery = self::normalize($query);
        $score = 0;

        foreach (['codigo_barras', 'sku', 'codigo'] as $field) {
            $value = self::normalize($fields[$field] ?? '');
            if ($value !== '' && $value === $normalizedQuery) {
                $score += 1000;
            }
        }

        foreach ($tokens as $token) {
            if (preg_match('/\b' . preg_quote($token, '/') . '/', $searchText)) {
                $score += 20;
            } elseif (str_contains($searchText, $token)) {
                $score += 8;
            }
        }

        return $score;
    }
}
