<?php

namespace App\Exportaciones;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventarioExportacion extends ExportacionBase
{
    private ?Collection $cache = null;
    private ?Collection $productosCompletos = null;
    private ?array $totalesProducto = null;

    public function __construct(
        private readonly int    $empresaId,
        private readonly int    $sucursalId,
        private readonly string $agrupar,
        private readonly array  $filtros,
    ) {}

    public function titulo(): string   { return 'Reporte de productos y existencias'; }
    public function empresaId(): ?int  { return $this->empresaId; }
    public function sucursalId(): ?int { return $this->sucursalId; }

    public function filtrosAplicados(): array
    {
        $r = [];
        if (!empty($this->filtros['categoria_id'])) {
            $r['Categoría'] = DB::table('categorias')->where('empresa_id', $this->empresaId)->where('id', $this->filtros['categoria_id'])->value('nombre') ?? 'Sin categoría';
        }
        $r['Costos y precios'] = 'Catálogo actual; promedio ponderado por existencias cuando hay variantes.';
        $etiquetasOrden = ['producto' => 'Producto', 'stock' => 'Existencia', 'comprometido' => 'Comprometido', 'disponible' => 'Disponible', 'costo' => 'Costo actual', 'precio_venta' => 'Precio de venta', 'invertido' => 'Invertido'];
        $r['Orden'] = ($etiquetasOrden[$this->filtros['orden'] ?? 'invertido'] ?? 'Invertido') . (($this->filtros['direccion'] ?? 'desc') === 'asc' ? ' ascendente' : ' descendente');
        if (!empty($this->filtros['q'])) {
            $r['Búsqueda'] = $this->filtros['q'];
        }
        if (!empty($this->filtros['filtro']) && $this->filtros['filtro'] !== 'todos') {
            $etiquetas = [
                'con_existencia' => 'Con existencia',
                'agotados'       => 'Agotados',
                'sin_costo'      => 'Sin costo',
                'bajo_minimo'    => 'Bajo mínimo',
            ];
            $r['Filtro'] = $etiquetas[$this->filtros['filtro']] ?? $this->filtros['filtro'];
        }
        if (($this->filtros['series'] ?? 'todos') !== 'todos') {
            $r['Series'] = $this->filtros['series'] === 'con_series' ? 'Productos con series' : 'Productos sin series';
        }
        $r['Agrupación'] = ucfirst($this->agrupar);
        return $r;
    }

    public function cabeceras(): array
    {
        return match ($this->agrupar) {
            'categoria', 'proveedor' => [
                $this->agrupar === 'categoria' ? 'Categoría' : 'Proveedor',
                'Artículos', 'Existencia', 'Invertido', 'Valor venta', 'Margen %', 'Sin costo', 'Bajo mínimo',
            ],
            default => array_column($this->definicionColumnasProducto(), 'titulo'),
        };
    }

    public function columnWidths(): array
    {
        if ($this->agrupar !== 'producto') {
            return ['A' => 34, 'B' => 12, 'C' => 14, 'D' => 16, 'E' => 16, 'F' => 12, 'G' => 12, 'H' => 14];
        }
        $anchos = [];
        foreach (array_values($this->definicionColumnasProducto()) as $indice => $columna) {
            $anchos[chr(65 + $indice)] = $columna['ancho'];
        }
        return $anchos;
    }

    public function datos(): Collection
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $costo       = 'COALESCE(v.precio_costo, p.precio_costo, 0)';
        $precioVenta = 'COALESCE(v.precio_venta, p.precio_venta, 0)';

        $ultimoProveedor = DB::table('compra_detalles as cd')
            ->join('compras as c', 'c.id', '=', 'cd.compra_id')
            ->where('c.empresa_id', $this->empresaId)
            ->where('c.sucursal_id', $this->sucursalId)
            ->whereIn('c.estado', ['confirmada', 'devuelta_parcial'])
            ->selectRaw('cd.producto_id, cd.variante_id, MAX(c.id) AS ultima_compra_id')
            ->groupBy('cd.producto_id', 'cd.variante_id');

        $base = DB::table('inventario as inv')
            ->join('productos as p', 'p.id', '=', 'inv.producto_id')
            ->leftJoin('producto_variantes as v', 'v.id', '=', 'inv.variante_id')
            ->leftJoin('categorias as cat', 'cat.id', '=', 'p.categoria_id')
            ->leftJoinSub($ultimoProveedor, 'up', function ($join) {
                $join->on('up.producto_id', '=', 'inv.producto_id')
                    ->whereRaw('(up.variante_id = inv.variante_id OR (up.variante_id IS NULL AND inv.variante_id IS NULL))');
            })
            ->leftJoin('compras as uc', 'uc.id', '=', 'up.ultima_compra_id')
            ->leftJoin('proveedores as pr', 'pr.id', '=', 'uc.proveedor_id')
            ->where('inv.empresa_id', $this->empresaId)
            ->where('inv.sucursal_id', $this->sucursalId);
        $base = \App\Servicios\InventarioComprometidoReporte::aplicar($base, $this->empresaId, $this->sucursalId);

        if (!empty($this->filtros['categoria_id'])) {
            $base->where('p.categoria_id', $this->filtros['categoria_id']);
        }

        match ($this->filtros['series'] ?? 'todos') {
            'con_series' => $base->where('p.tiene_series', true),
            'sin_series' => $base->where('p.tiene_series', false),
            default => null,
        };
        if (!empty($this->filtros['q'])) {
            $texto = trim((string) $this->filtros['q']);
            $base->where(function ($q) use ($texto) {
                $q->where('p.nombre', 'like', "%{$texto}%")
                    ->orWhere('p.codigo', 'like', "%{$texto}%")
                    ->orWhere('v.sku', 'like', "%{$texto}%")
                    ->orWhereExists(\App\Servicios\SeriesInventarioReporte::consulta($this->empresaId, $this->sucursalId)
                        ->selectRaw('1')->whereColumn('series.producto_id', 'inv.producto_id')
                        ->whereRaw('(series.variante_id = inv.variante_id OR (series.variante_id IS NULL AND inv.variante_id IS NULL))')
                        ->where(fn($s) => $s->where('imei', 'like', "%{$texto}%")->orWhere('imei2', 'like', "%{$texto}%")->orWhere('serie', 'like', "%{$texto}%")))
                    ->orWhere('v.codigo_barras', 'like', "%{$texto}%")
                    ->orWhere('cat.nombre', 'like', "%{$texto}%")
                    ->orWhere('pr.nombre_comercial', 'like', "%{$texto}%")
                    ->orWhere('pr.razon_social', 'like', "%{$texto}%");
            });
        }

        $filtro = $this->filtros['filtro'] ?? 'todos';
        match ($filtro) {
            'con_existencia' => $base->where('inv.stock', '>', 0),
            'agotados'       => $base->where('inv.stock', '<=', 0),
            'sin_costo'      => $base->whereRaw("{$costo} <= 0"),
            'bajo_minimo'    => $base->where('inv.stock_minimo', '>', 0)->whereColumn('inv.stock', '<=', 'inv.stock_minimo'),
            default          => null,
        };

        $series = $this->agrupar === 'producto'
            ? \App\Servicios\SeriesInventarioReporte::porProducto($this->empresaId, $this->sucursalId, (clone $base)->distinct()->pluck('p.id')->all())
            : collect();

        $rows = match ($this->agrupar) {
            'categoria' => $base
                ->selectRaw("
                    COALESCE(cat.nombre, 'Sin categoría') AS nombre,
                    COUNT(*) AS articulos,
                    COALESCE(SUM(inv.stock), 0) AS unidades,
                    COALESCE(SUM(inv.stock * {$costo}), 0) AS invertido,
                    COALESCE(SUM(inv.stock * {$precioVenta}), 0) AS valor_venta,
                    COALESCE(SUM(CASE WHEN {$costo} <= 0 THEN 1 ELSE 0 END), 0) AS sin_costo,
                    COALESCE(SUM(CASE WHEN inv.stock_minimo > 0 AND inv.stock <= inv.stock_minimo THEN 1 ELSE 0 END), 0) AS bajo_minimo
                ")
                ->groupBy('cat.id', 'cat.nombre')
                ->orderByDesc('invertido')
                ->get()
                ->map(function ($row) {
                    $inv  = (float) $row->invertido;
                    $vv   = (float) $row->valor_venta;
                    $marg = $vv > 0 ? round((($vv - $inv) / $vv) * 100, 2) : 0;
                    return [$row->nombre, (int)$row->articulos, round((float)$row->unidades,2), round($inv,2), round($vv,2), $marg, (int)$row->sin_costo, (int)$row->bajo_minimo];
                }),

            'proveedor' => $base
                ->selectRaw("
                    COALESCE(pr.nombre_comercial, pr.razon_social, 'Sin proveedor') AS nombre,
                    COUNT(*) AS articulos,
                    COALESCE(SUM(inv.stock), 0) AS unidades,
                    COALESCE(SUM(inv.stock * {$costo}), 0) AS invertido,
                    COALESCE(SUM(inv.stock * {$precioVenta}), 0) AS valor_venta,
                    COALESCE(SUM(CASE WHEN {$costo} <= 0 THEN 1 ELSE 0 END), 0) AS sin_costo,
                    COALESCE(SUM(CASE WHEN inv.stock_minimo > 0 AND inv.stock <= inv.stock_minimo THEN 1 ELSE 0 END), 0) AS bajo_minimo
                ")
                ->groupBy('pr.id', 'pr.nombre_comercial', 'pr.razon_social')
                ->orderByDesc('invertido')
                ->get()
                ->map(function ($row) {
                    $inv  = (float) $row->invertido;
                    $vv   = (float) $row->valor_venta;
                    $marg = $vv > 0 ? round((($vv - $inv) / $vv) * 100, 2) : 0;
                    return [$row->nombre, (int)$row->articulos, round((float)$row->unidades,2), round($inv,2), round($vv,2), $marg, (int)$row->sin_costo, (int)$row->bajo_minimo];
                }),

            default => $base
                ->selectRaw("
                    p.id AS producto_id,
                    p.codigo,
                    p.nombre AS producto,
                    COALESCE(cat.nombre, 'Sin categoría') AS categoria,
                    CASE
                        WHEN COUNT(DISTINCT pr.id) = 0 THEN 'Sin proveedor'
                        WHEN COUNT(DISTINCT pr.id) = 1 THEN MAX(COALESCE(pr.nombre_comercial, pr.razon_social))
                        ELSE 'Varios proveedores'
                    END AS proveedor,
                    COALESCE(SUM(inv.stock), 0) AS stock,
                    COALESCE(SUM(" . \App\Servicios\InventarioComprometidoReporte::sql() . "), 0) AS comprometido,
                    COALESCE(SUM(" . \App\Servicios\InventarioComprometidoReporte::disponibleSql() . "), 0) AS disponible,
                    COUNT(DISTINCT v.id) AS variantes,
                    CASE
                        WHEN COALESCE(SUM(inv.stock), 0) > 0
                        THEN COALESCE(SUM(inv.stock * {$costo}), 0) / SUM(inv.stock)
                        ELSE COALESCE(MAX({$costo}), 0)
                    END AS costo,
                    CASE WHEN COALESCE(SUM(inv.stock), 0) > 0
                        THEN COALESCE(SUM(inv.stock * {$precioVenta}), 0) / SUM(inv.stock)
                        ELSE COALESCE(MAX({$precioVenta}), 0)
                    END AS precio_venta,
                    COALESCE(SUM(inv.stock * {$costo}), 0) AS invertido,
                    COALESCE(SUM(inv.stock * {$precioVenta}), 0) AS valor_venta,
                    COALESCE(SUM(CASE WHEN {$costo} <= 0 THEN 1 ELSE 0 END), 0) AS sin_costo_count,
                    COALESCE(SUM(CASE WHEN inv.stock_minimo > 0 AND inv.stock <= inv.stock_minimo THEN 1 ELSE 0 END), 0) AS bajo_minimo_count
                ")
                ->groupBy('p.id', 'p.codigo', 'p.nombre', 'cat.nombre')
                ->orderBy(in_array($this->filtros['orden'] ?? '', ['producto', 'stock', 'comprometido', 'disponible', 'costo', 'precio_venta', 'invertido'], true) ? $this->filtros['orden'] : 'invertido', ($this->filtros['direccion'] ?? 'desc') === 'asc' ? 'asc' : 'desc')
                ->orderBy('p.id')
                ->get()
                ->map(function ($row) use ($series) {
                    $inv  = (float) $row->invertido;
                    $vv   = (float) $row->valor_venta;
                    $marg = $vv > 0 ? round((($vv - $inv) / $vv) * 100, 2) : 0;
                    $alertas = [];
                    if ((int)$row->sin_costo_count > 0)  $alertas[] = 'Sin costo';
                    if ((int)$row->bajo_minimo_count > 0) $alertas[] = 'Bajo mínimo';
                    return [
                        $row->codigo ?? '—',
                        $row->producto,
                        $row->categoria,
                        $row->proveedor,
                        round((float)$row->stock, 2),
                        round((float)$row->comprometido, 2),
                        round((float)$row->disponible, 2),
                        (int)$row->variantes,
                        round((float)$row->costo, 2),
                        round($inv, 2),
                        round($vv, 2),
                        $marg,
                        implode(', ', $alertas) ?: '—',
                        round((float)$row->precio_venta, 2),
                        implode(", ", $series->get($row->producto_id, [])),
                    ];
                }),
        };

        if ($this->agrupar === 'producto') {
            $this->productosCompletos = $rows;
            $inv = $rows->sum(fn($f) => $f[9]);
            $vv = $rows->sum(fn($f) => $f[10]);
            $margen = $vv > 0 ? round((($vv - $inv) / $vv) * 100, 2) : 0;
            $this->totalesProducto = $this->filtrarFilaProducto([
                '', 'TOTALES', '', '',
                round($rows->sum(fn($f) => $f[4]), 2),
                round($rows->sum(fn($f) => $f[5]), 2),
                round($rows->sum(fn($f) => $f[6]), 2),
                '', '', round($inv, 2), round($vv, 2), $margen, '', '', '',
            ]);
            $rows = $rows->map(fn($fila) => $this->filtrarFilaProducto($fila));
        }

        $this->cache = $rows;
        return $this->cache;
    }

    public function datosCompletosProducto(): Collection
    {
        $this->datos();
        return $this->productosCompletos ?? collect();
    }

    public function columnasSeleccionadas(): array
    {
        return array_keys($this->definicionColumnasProducto());
    }

    public function totales(): ?array
    {
        $filas = $this->datos();
        if ($filas->isEmpty()) return null;

        if ($this->agrupar !== 'producto') {
            $inv  = $filas->sum(fn($f) => $f[3]);
            $vv   = $filas->sum(fn($f) => $f[4]);
            $marg = $vv > 0 ? round((($vv - $inv) / $vv) * 100, 2) : 0;
            return ['TOTALES', $filas->sum(fn($f) => $f[1]), round($filas->sum(fn($f) => $f[2]),2), round($inv,2), round($vv,2), $marg, $filas->sum(fn($f) => $f[6]), $filas->sum(fn($f) => $f[7])];
        }

        return $this->totalesProducto;
    }

    private function definicionColumnasProducto(): array
    {
        $todas = [
            'clave' => ['titulo' => 'Clave', 'indice' => 0, 'ancho' => 14],
            'producto' => ['titulo' => 'Producto', 'indice' => 1, 'ancho' => 36],
            'categoria' => ['titulo' => 'Categoría', 'indice' => 2, 'ancho' => 22],
            'proveedor' => ['titulo' => 'Proveedor', 'indice' => 3, 'ancho' => 26],
            'stock' => ['titulo' => 'Existencia', 'indice' => 4, 'ancho' => 12],
            'comprometido' => ['titulo' => 'Comprometido', 'indice' => 5, 'ancho' => 14],
            'disponible' => ['titulo' => 'Disponible', 'indice' => 6, 'ancho' => 12],
            'variantes' => ['titulo' => 'Variantes', 'indice' => 7, 'ancho' => 10],
            'costo' => ['titulo' => 'Costo actual', 'indice' => 8, 'ancho' => 14],
            'invertido' => ['titulo' => 'Invertido', 'indice' => 9, 'ancho' => 16],
            'valor_venta' => ['titulo' => 'Valor venta', 'indice' => 10, 'ancho' => 16],
            'margen' => ['titulo' => 'Margen %', 'indice' => 11, 'ancho' => 12],
            'alertas' => ['titulo' => 'Alertas', 'indice' => 12, 'ancho' => 22],
            'precio_venta' => ['titulo' => 'P. venta unit.', 'indice' => 13, 'ancho' => 16],
            'series' => ['titulo' => 'IMEI / Series', 'indice' => 14, 'ancho' => 55],
        ];
        $solicitadas = array_values(array_unique(array_filter((array) ($this->filtros['columnas'] ?? []), fn($c) => isset($todas[$c]))));
        if (!$solicitadas) return $todas;
        if (!in_array('producto', $solicitadas, true)) $solicitadas[] = 'producto';

        return array_intersect_key($todas, array_flip($solicitadas));
    }

    private function filtrarFilaProducto(array $fila): array
    {
        return array_values(array_map(fn($columna) => $fila[$columna['indice']] ?? '', $this->definicionColumnasProducto()));
    }
}
