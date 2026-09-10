<?php

namespace App\Servicios;

use Illuminate\Support\Facades\DB;

class InventarioReporteConsulta
{
    public static function costoSql(): string
    {
        return 'COALESCE(v.precio_costo, p.precio_costo, 0)';
    }

    public static function precioVentaSql(): string
    {
        return 'COALESCE(v.precio_venta, p.precio_venta, 0)';
    }

    /**
     * Query base de inventario (joins + filtros de búsqueda) usada tanto por el
     * reporte en pantalla como por la exportación a Excel/PDF, para que ambos
     * siempre reflejen exactamente los mismos productos.
     *
     * $filtros admite: categoria_id, q, series ('todos'|'con_series'|'sin_series'),
     * filtro ('todos'|'con_existencia'|'agotados'|'sin_costo'|'bajo_minimo').
     * $aplicarFiltroExistencia en false omite el filtro de existencia/costo/mínimo
     * (usado para calcular el resumen sobre todo el inventario, sin importar el
     * filtro elegido para la tabla).
     */
    public static function base(int $empresaId, int $sucursalId, array $filtros = [], bool $aplicarFiltroExistencia = true)
    {
        $ultimoProveedor = DB::table('compra_detalles as cd')
            ->join('compras as c', 'c.id', '=', 'cd.compra_id')
            ->where('c.empresa_id', $empresaId)
            ->where('c.sucursal_id', $sucursalId)
            ->whereIn('c.estado', ['confirmada', 'devuelta_parcial'])
            ->selectRaw('cd.producto_id, cd.variante_id, MAX(c.id) AS ultima_compra_id')
            ->groupBy('cd.producto_id', 'cd.variante_id');

        $query = DB::table('inventario as inv')
            ->join('productos as p', 'p.id', '=', 'inv.producto_id')
            ->leftJoin('producto_variantes as v', 'v.id', '=', 'inv.variante_id')
            ->leftJoin('categorias as cat', 'cat.id', '=', 'p.categoria_id')
            ->leftJoinSub($ultimoProveedor, 'up', function ($join) {
                $join->on('up.producto_id', '=', 'inv.producto_id')
                    ->whereRaw('(up.variante_id = inv.variante_id OR (up.variante_id IS NULL AND inv.variante_id IS NULL))');
            })
            ->leftJoin('compras as uc', 'uc.id', '=', 'up.ultima_compra_id')
            ->leftJoin('proveedores as pr', 'pr.id', '=', 'uc.proveedor_id')
            ->where('inv.empresa_id', $empresaId)
            ->where('inv.sucursal_id', $sucursalId);

        if (!empty($filtros['categoria_id'])) {
            $query->where('p.categoria_id', (int) $filtros['categoria_id']);
        }

        match ($filtros['series'] ?? 'todos') {
            'con_series' => $query->where('p.tiene_series', true),
            'sin_series' => $query->where('p.tiene_series', false),
            default => null,
        };

        if (!empty($filtros['q'])) {
            $texto = trim((string) $filtros['q']);
            $query->where(function ($q) use ($texto, $empresaId, $sucursalId) {
                $q->where('p.nombre', 'like', "%{$texto}%")
                    ->orWhere('p.codigo', 'like', "%{$texto}%")
                    ->orWhere('v.sku', 'like', "%{$texto}%")
                    ->orWhereExists(SeriesInventarioReporte::consulta($empresaId, $sucursalId)
                        ->selectRaw('1')->whereColumn('series.producto_id', 'inv.producto_id')
                        ->whereRaw('(series.variante_id = inv.variante_id OR (series.variante_id IS NULL AND inv.variante_id IS NULL))')
                        ->where(fn($s) => $s->where('imei', 'like', "%{$texto}%")->orWhere('imei2', 'like', "%{$texto}%")->orWhere('serie', 'like', "%{$texto}%")))
                    ->orWhere('v.codigo_barras', 'like', "%{$texto}%")
                    ->orWhere('cat.nombre', 'like', "%{$texto}%")
                    ->orWhere('pr.nombre_comercial', 'like', "%{$texto}%")
                    ->orWhere('pr.razon_social', 'like', "%{$texto}%");
            });
        }

        if ($aplicarFiltroExistencia) {
            $costo = self::costoSql();
            match ($filtros['filtro'] ?? 'todos') {
                'con_existencia' => $query->where('inv.stock', '>', 0),
                'agotados' => $query->where('inv.stock', '<=', 0),
                'sin_costo' => $query->whereRaw("{$costo} <= 0"),
                'bajo_minimo' => $query->where('inv.stock_minimo', '>', 0)->whereColumn('inv.stock', '<=', 'inv.stock_minimo'),
                default => null,
            };
        }

        return InventarioComprometidoReporte::aplicar($query, $empresaId, $sucursalId);
    }

    /**
     * Resumen agregado del inventario (usado en pantalla y en el PDF).
     * Recibe la query base ya filtrada (normalmente sin el filtro de existencia,
     * para que el resumen no oculte artículos agotados).
     */
    public static function resumen($baseResumen): array
    {
        $costo        = self::costoSql();
        $precioVenta  = self::precioVentaSql();
        $comprometido = InventarioComprometidoReporte::sql();
        $disponible   = InventarioComprometidoReporte::disponibleSql();

        $fila = $baseResumen
            ->selectRaw("
                COUNT(DISTINCT inv.producto_id) AS articulos,
                COALESCE(SUM(inv.stock), 0) AS unidades,
                COALESCE(SUM({$comprometido}), 0) AS comprometidas,
                COALESCE(SUM({$disponible}), 0) AS disponibles,
                COALESCE(SUM(inv.stock * {$costo}), 0) AS invertido,
                COALESCE(SUM(inv.stock * {$precioVenta}), 0) AS valor_venta,
                COUNT(DISTINCT CASE WHEN {$costo} <= 0 THEN inv.producto_id END) AS sin_costo,
                COUNT(DISTINCT CASE WHEN inv.stock_minimo > 0 AND inv.stock <= inv.stock_minimo THEN inv.producto_id END) AS bajo_minimo
            ")
            ->first();

        $invertido  = (float) $fila->invertido;
        $valorVenta = (float) $fila->valor_venta;

        return [
            'articulos' => (int) $fila->articulos,
            'unidades' => (float) $fila->unidades,
            'comprometidas' => (float) $fila->comprometidas,
            'disponibles' => (float) $fila->disponibles,
            'invertido' => $invertido,
            'valor_venta' => $valorVenta,
            'margen_potencial' => $valorVenta > 0 ? round((($valorVenta - $invertido) / $valorVenta) * 100, 2) : 0,
            'sin_costo' => (int) $fila->sin_costo,
            'bajo_minimo' => (int) $fila->bajo_minimo,
        ];
    }
}
