<?php

namespace App\Support;

use App\Models\Producto;
use App\Models\ProductoVariante;
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

    public static function normalize(?string $text): string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }

        // No usar iconv('...//TRANSLIT...'): su tabla de transliteración
        // depende de la libc del servidor y en algunos entornos (glibc en
        // producción) inserta caracteres como ' o ~ antes de la letra
        // (á → 'a, ñ → ~n), los cuales el regex siguiente convierte en
        // espacios y parte la palabra en dos tokens que ya no hacen match.
        $text = mb_strtolower($text, 'UTF-8');
        $text = strtr($text, self::ACENTOS);
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text) ?? '';
        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
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
