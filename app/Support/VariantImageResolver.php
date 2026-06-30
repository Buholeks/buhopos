<?php

namespace App\Support;

use App\Models\ProductoVariante;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class VariantImageResolver
{
    private const VISUAL_ATTRIBUTE_NAMES = [
        'color',
        'colores',
        'colors',
        'colour',
        'colours',
    ];

    public static function applyResolvedImages(Collection $variantes): Collection
    {
        if ($variantes->isEmpty()) {
            return $variantes;
        }

        $visualTypeByProduct = self::visualTypeByProduct($variantes);
        $imageByGroup = [];

        foreach ($variantes as $variante) {
            $groupKey = self::groupKey($variante, $visualTypeByProduct[$variante->producto_id] ?? null);

            if ($groupKey && $variante->imagen) {
                $imageByGroup[$variante->producto_id][$groupKey] ??= $variante->imagen;
            }
        }

        foreach ($variantes as $variante) {
            $groupKey = self::groupKey($variante, $visualTypeByProduct[$variante->producto_id] ?? null);
            $resolved = $variante->imagen
                ?: ($groupKey ? ($imageByGroup[$variante->producto_id][$groupKey] ?? null) : null)
                ?: ($variante->producto?->imagen ?? null);

            $variante->setAttribute('imagen_url_resuelta', PublicImageStorage::url($resolved));
            $variante->setAttribute('grupo_visual', self::groupLabel($variante, $visualTypeByProduct[$variante->producto_id] ?? null));
        }

        return $variantes;
    }

    public static function applyResolvedImagesWithSiblingImages(Collection $variantes, int $empresaId): Collection
    {
        if ($variantes->isEmpty()) {
            return $variantes;
        }

        $productIds = $variantes->pluck('producto_id')->filter()->unique()->values();

        $siblingsWithImages = ProductoVariante::where('empresa_id', $empresaId)
            ->whereIn('producto_id', $productIds)
            ->whereNotNull('imagen')
            ->with([
                'producto:id,imagen',
                'atributos.tipoAtributo:id,nombre',
                'atributos.atributo:id,valor',
            ])
            ->select('id', 'producto_id', 'empresa_id', 'imagen')
            ->get();

        return self::applyResolvedImages($variantes->concat($siblingsWithImages))
            ->filter(fn($variante) => $variantes->contains('id', $variante->id))
            ->unique('id')
            ->values();
    }

    public static function previewsForProducts(Collection $productos, int $empresaId, int $limit = 3): array
    {
        $productIds = $productos
            ->filter(fn($producto) => ! $producto->imagen && $producto->tiene_variantes)
            ->pluck('id')
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return [];
        }

        $variantes = ProductoVariante::where('empresa_id', $empresaId)
            ->whereIn('producto_id', $productIds)
            ->whereNotNull('imagen')
            ->with([
                'atributos.tipoAtributo:id,nombre',
                'atributos.atributo:id,valor',
            ])
            ->select('id', 'producto_id', 'empresa_id', 'imagen')
            ->orderBy('id')
            ->get();

        $visualTypeByProduct = self::visualTypeByProduct($variantes);
        $previews = [];
        $seen = [];

        foreach ($variantes as $variante) {
            $productId = (int) $variante->producto_id;
            if (count($previews[$productId] ?? []) >= $limit) {
                continue;
            }

            $groupKey = self::groupKey($variante, $visualTypeByProduct[$productId] ?? null)
                ?: "variante:{$variante->id}";
            $imageKey = "{$productId}:{$groupKey}:{$variante->imagen}";

            if (isset($seen[$imageKey])) {
                continue;
            }

            $seen[$imageKey] = true;
            $previews[$productId][] = [
                'url' => PublicImageStorage::url($variante->imagen),
                'grupo' => self::groupLabel($variante, $visualTypeByProduct[$productId] ?? null),
            ];
        }

        return $previews;
    }

    private static function visualTypeByProduct(Collection $variantes): array
    {
        $result = [];

        foreach ($variantes->groupBy('producto_id') as $productId => $items) {
            $types = collect($items)
                ->flatMap(fn($variante) => $variante->atributos ?? [])
                ->map(fn($attr) => [
                    'id' => $attr->tipo_atributo_id ?? $attr->tipoAtributo?->id,
                    'name' => $attr->tipoAtributo?->nombre,
                ])
                ->filter(fn($type) => $type['id'])
                ->unique('id')
                ->values();

            $visual = $types->first(fn($type) => in_array(self::normalize($type['name'] ?? ''), self::VISUAL_ATTRIBUTE_NAMES, true))
                ?: $types->sortBy('id')->first();

            if ($visual) {
                $result[$productId] = (int) $visual['id'];
            }
        }

        return $result;
    }

    private static function groupKey(ProductoVariante $variante, ?int $visualTypeId): ?string
    {
        $attr = self::visualAttribute($variante, $visualTypeId);

        return $attr ? 'atributo:' . ($attr->atributo_id ?? $attr->atributo?->id) : null;
    }

    private static function groupLabel(ProductoVariante $variante, ?int $visualTypeId): ?string
    {
        $attr = self::visualAttribute($variante, $visualTypeId);

        return $attr?->atributo?->valor;
    }

    private static function visualAttribute(ProductoVariante $variante, ?int $visualTypeId): mixed
    {
        if (! $visualTypeId) {
            return null;
        }

        return collect($variante->atributos ?? [])
            ->first(fn($attr) => (int) ($attr->tipo_atributo_id ?? $attr->tipoAtributo?->id) === $visualTypeId);
    }

    private static function normalize(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/\s+/', '')
            ->toString();
    }
}
