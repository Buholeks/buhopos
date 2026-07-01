<?php

namespace App\Exportaciones;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventarioExportacion extends ExportacionBase
{
    private ?Collection $cache = null;

    public function __construct(
        private readonly int    $empresaId,
        private readonly int    $sucursalId,
        private readonly string $agrupar,
        private readonly array  $filtros,
    ) {}

    public function titulo(): string   { return 'Inventario — ' . ucfirst($this->agrupar); }
    public function empresaId(): ?int  { return $this->empresaId; }
    public function sucursalId(): ?int { return $this->sucursalId; }

    public function filtrosAplicados(): array
    {
        $r = [];
        if (!empty($this->filtros['q'])) {
            $r['Búsqueda'] = $this->filtros['q'];
        }
        if (!empty($this->filtros['filtro']) && $this->filtros['filtro'] !== 'todos') {
            $etiquetas = [
                'con_existencia' => 'Con existencia',
                'sin_costo'      => 'Sin costo',
                'bajo_minimo'    => 'Bajo mínimo',
            ];
            $r['Filtro'] = $etiquetas[$this->filtros['filtro']] ?? $this->filtros['filtro'];
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
            default => [
                'Clave', 'Producto', 'Categoría', 'Proveedor',
                'Existencia', 'Variantes', 'Costo prom.', 'Invertido', 'Valor venta', 'Margen %', 'Alertas',
            ],
        };
    }

    public function columnWidths(): array
    {
        if ($this->agrupar !== 'producto') {
            return ['A' => 34, 'B' => 12, 'C' => 14, 'D' => 16, 'E' => 16, 'F' => 12, 'G' => 12, 'H' => 14];
        }
        return [
            'A' => 14, 'B' => 36, 'C' => 22, 'D' => 26,
            'E' => 12, 'F' => 10, 'G' => 14, 'H' => 16, 'I' => 16, 'J' => 12, 'K' => 22,
        ];
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

        if (!empty($this->filtros['q'])) {
            $texto = trim((string) $this->filtros['q']);
            $base->where(function ($q) use ($texto) {
                $q->where('p.nombre', 'like', "%{$texto}%")
                    ->orWhere('p.codigo', 'like', "%{$texto}%")
                    ->orWhere('v.sku', 'like', "%{$texto}%")
                    ->orWhere('v.codigo_barras', 'like', "%{$texto}%")
                    ->orWhere('cat.nombre', 'like', "%{$texto}%")
                    ->orWhere('pr.nombre_comercial', 'like', "%{$texto}%")
                    ->orWhere('pr.razon_social', 'like', "%{$texto}%");
            });
        }

        $filtro = $this->filtros['filtro'] ?? 'todos';
        match ($filtro) {
            'con_existencia' => $base->where('inv.stock', '>', 0),
            'sin_costo'      => $base->whereRaw("{$costo} <= 0"),
            'bajo_minimo'    => $base->where('inv.stock_minimo', '>', 0)->whereColumn('inv.stock', '<=', 'inv.stock_minimo'),
            default          => null,
        };

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
                    p.codigo,
                    p.nombre AS producto,
                    COALESCE(cat.nombre, 'Sin categoría') AS categoria,
                    CASE
                        WHEN COUNT(DISTINCT pr.id) = 0 THEN 'Sin proveedor'
                        WHEN COUNT(DISTINCT pr.id) = 1 THEN MAX(COALESCE(pr.nombre_comercial, pr.razon_social))
                        ELSE 'Varios proveedores'
                    END AS proveedor,
                    COALESCE(SUM(inv.stock), 0) AS stock,
                    COUNT(DISTINCT v.id) AS variantes,
                    CASE
                        WHEN COALESCE(SUM(inv.stock), 0) > 0
                        THEN COALESCE(SUM(inv.stock * {$costo}), 0) / SUM(inv.stock)
                        ELSE COALESCE(MAX({$costo}), 0)
                    END AS costo,
                    COALESCE(SUM(inv.stock * {$costo}), 0) AS invertido,
                    COALESCE(SUM(inv.stock * {$precioVenta}), 0) AS valor_venta,
                    COALESCE(SUM(CASE WHEN {$costo} <= 0 THEN 1 ELSE 0 END), 0) AS sin_costo_count,
                    COALESCE(SUM(CASE WHEN inv.stock_minimo > 0 AND inv.stock <= inv.stock_minimo THEN 1 ELSE 0 END), 0) AS bajo_minimo_count
                ")
                ->groupBy('p.id', 'p.codigo', 'p.nombre', 'cat.nombre')
                ->orderByDesc('invertido')
                ->orderBy('p.nombre')
                ->get()
                ->map(function ($row) {
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
                        (int)$row->variantes,
                        round((float)$row->costo, 2),
                        round($inv, 2),
                        round($vv, 2),
                        $marg,
                        implode(', ', $alertas) ?: '—',
                    ];
                }),
        };

        $this->cache = $rows;
        return $this->cache;
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

        $inv  = $filas->sum(fn($f) => $f[7]);
        $vv   = $filas->sum(fn($f) => $f[8]);
        $marg = $vv > 0 ? round((($vv - $inv) / $vv) * 100, 2) : 0;
        return ['', 'TOTALES', '', '', round($filas->sum(fn($f) => $f[4]),2), '', '', round($inv,2), round($vv,2), $marg, ''];
    }
}
