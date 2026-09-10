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

        return self::consulta($empresaId, $sucursalId)
            ->whereIn('producto_id', $productoIds)
            ->orderBy('id')
            ->get(['producto_id', 'imei', 'imei2', 'serie'])
            ->groupBy('producto_id')
            ->map(fn($series) => $series
                ->map(fn($serie) => $serie->imei ?: ($serie->serie ?: $serie->imei2))
                ->filter()->unique()->values()->all());
    }
}
