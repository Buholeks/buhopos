<?php

namespace App\Servicios;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SeriesInventarioReporte
{
    public static function consulta(int $empresaId, int $sucursalId)
    {
        return DB::table('series')
            ->where('empresa_id', $empresaId)
            ->where('sucursal_id', $sucursalId)
            ->whereIn('estado', ['disponible', 'apartado'])
            ->whereNotExists(fn($q) => $q->selectRaw('1')
                ->from('traspaso_detalles as td')
                ->join('traspasos as t', 't.id', '=', 'td.traspaso_id')
                ->whereColumn('td.serie_id', 'series.id')
                ->where('td.estado', 'pendiente')
                ->where('t.estado', 'pendiente'));
    }

    public static function porProducto(int $empresaId, int $sucursalId, array $productoIds): Collection
    {
        if (!$productoIds) {
            return collect();
        }

        return self::etiquetasPorProducto(self::filas($empresaId, $sucursalId, $productoIds));
    }

    /**
     * Filas crudas de series (producto_id, variante_id, imei, imei2, serie) para un
     * conjunto de productos. Pensado para pedirse una sola vez y reutilizarse tanto
     * a nivel producto como a nivel variante, evitando consultar la tabla dos veces.
     */
    public static function filas(int $empresaId, int $sucursalId, array $productoIds): Collection
    {
        if (!$productoIds) {
            return collect();
        }

        return self::consulta($empresaId, $sucursalId)
            ->whereIn('producto_id', $productoIds)
            ->orderBy('id')
            ->get(['producto_id', 'variante_id', 'imei', 'imei2', 'serie']);
    }

    public static function etiquetasPorProducto(Collection $filas): Collection
    {
        return $filas->groupBy('producto_id')
            ->map(fn($series) => $series
                ->map(fn($serie) => $serie->imei ?: ($serie->serie ?: $serie->imei2))
                ->filter()->unique()->values()->all());
    }
}
