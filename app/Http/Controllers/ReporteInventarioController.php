<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReporteInventarioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('reportes.ver'), 403, 'Sin permiso: reportes.ver');

        $data = $request->validate([
            'q'          => ['nullable', 'string', 'max:120'],
            'agrupar'    => ['nullable', Rule::in(['producto', 'categoria', 'proveedor'])],
            'filtro'     => ['nullable', Rule::in(['todos', 'con_existencia', 'sin_costo', 'bajo_minimo'])],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page'       => ['nullable', 'integer', 'min:1'],
        ]);

        $user = $request->user();
        $agrupar = $data['agrupar'] ?? 'producto';
        $perPage = (int) ($data['por_pagina'] ?? 30);

        $base = $this->base($request, $user->empresa_id, $user->sucursal_id);
        $costo = $this->costoSql();
        $precioVenta = $this->precioVentaSql();

        $resumen = (clone $base)
            ->selectRaw("
                COUNT(*) AS articulos,
                COALESCE(SUM(inv.stock), 0) AS unidades,
                COALESCE(SUM(inv.stock * {$costo}), 0) AS invertido,
                COALESCE(SUM(inv.stock * {$precioVenta}), 0) AS valor_venta,
                COALESCE(SUM(CASE WHEN {$costo} <= 0 THEN 1 ELSE 0 END), 0) AS sin_costo,
                COALESCE(SUM(CASE WHEN inv.stock_minimo > 0 AND inv.stock <= inv.stock_minimo THEN 1 ELSE 0 END), 0) AS bajo_minimo
            ")
            ->first();

        $rows = match ($agrupar) {
            'categoria' => $this->porCategoria($base, $costo, $precioVenta, $perPage),
            'proveedor' => $this->porProveedor($base, $costo, $precioVenta, $perPage),
            default => $this->porProducto($base, $costo, $precioVenta, $perPage),
        };

        return response()->json([
            'resumen' => [
                'articulos' => (int) $resumen->articulos,
                'unidades' => (float) $resumen->unidades,
                'invertido' => (float) $resumen->invertido,
                'valor_venta' => (float) $resumen->valor_venta,
                'margen_potencial' => (float) $resumen->valor_venta > 0
                    ? round((((float) $resumen->valor_venta - (float) $resumen->invertido) / (float) $resumen->valor_venta) * 100, 2)
                    : 0,
                'sin_costo' => (int) $resumen->sin_costo,
                'bajo_minimo' => (int) $resumen->bajo_minimo,
            ],
            'agrupar' => $agrupar,
            'items' => $rows,
        ]);
    }

    private function base(Request $request, int $empresaId, int $sucursalId)
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

        if ($request->filled('q')) {
            $texto = trim((string) $request->q);
            $query->where(function ($q) use ($texto) {
                $q->where('p.nombre', 'like', "%{$texto}%")
                    ->orWhere('p.codigo', 'like', "%{$texto}%")
                    ->orWhere('v.sku', 'like', "%{$texto}%")
                    ->orWhere('v.codigo_barras', 'like', "%{$texto}%")
                    ->orWhere('cat.nombre', 'like', "%{$texto}%")
                    ->orWhere('pr.nombre_comercial', 'like', "%{$texto}%")
                    ->orWhere('pr.razon_social', 'like', "%{$texto}%");
            });
        }

        $costo = $this->costoSql();
        match ($request->input('filtro', 'todos')) {
            'con_existencia' => $query->where('inv.stock', '>', 0),
            'sin_costo' => $query->whereRaw("{$costo} <= 0"),
            'bajo_minimo' => $query->where('inv.stock_minimo', '>', 0)->whereColumn('inv.stock', '<=', 'inv.stock_minimo'),
            default => null,
        };

        return $query;
    }

    private function porProducto($base, string $costo, string $precioVenta, int $perPage)
    {
        return $base
            ->selectRaw("
                p.id AS producto_id,
                p.codigo,
                p.nombre AS producto,
                p.codigo AS clave,
                COALESCE(cat.nombre, 'Sin categoria') AS categoria,
                CASE
                    WHEN COUNT(DISTINCT pr.id) = 0 THEN 'Sin proveedor'
                    WHEN COUNT(DISTINCT pr.id) = 1 THEN MAX(COALESCE(pr.nombre_comercial, pr.razon_social))
                    ELSE 'Varios proveedores'
                END AS proveedor,
                COUNT(*) AS renglones_inventario,
                COUNT(DISTINCT v.id) AS variantes,
                COALESCE(SUM(inv.stock), 0) AS stock,
                COALESCE(MAX(inv.stock_minimo), 0) AS stock_minimo,
                CASE
                    WHEN COALESCE(SUM(inv.stock), 0) > 0
                    THEN COALESCE(SUM(inv.stock * {$costo}), 0) / SUM(inv.stock)
                    ELSE COALESCE(MAX({$costo}), 0)
                END AS costo,
                CASE
                    WHEN COALESCE(SUM(inv.stock), 0) > 0
                    THEN COALESCE(SUM(inv.stock * {$precioVenta}), 0) / SUM(inv.stock)
                    ELSE COALESCE(MAX({$precioVenta}), 0)
                END AS precio_venta,
                COALESCE(SUM(inv.stock * {$costo}), 0) AS invertido,
                COALESCE(SUM(inv.stock * {$precioVenta}), 0) AS valor_venta,
                COALESCE(SUM(CASE WHEN {$costo} <= 0 THEN 1 ELSE 0 END), 0) AS sin_costo_count,
                COALESCE(SUM(CASE WHEN inv.stock_minimo > 0 AND inv.stock <= inv.stock_minimo THEN 1 ELSE 0 END), 0) AS bajo_minimo_count
            ")
            ->groupBy('p.id', 'p.codigo', 'p.nombre', 'cat.nombre')
            ->orderByDesc('invertido')
            ->orderBy('p.nombre')
            ->paginate($perPage)
            ->through(fn($row) => $this->mapProducto($row));
    }

    private function porCategoria($base, string $costo, string $precioVenta, int $perPage)
    {
        return $base
            ->selectRaw("
                cat.id,
                COALESCE(cat.nombre, 'Sin categoria') AS nombre,
                COUNT(*) AS articulos,
                COALESCE(SUM(inv.stock), 0) AS unidades,
                COALESCE(SUM(inv.stock * {$costo}), 0) AS invertido,
                COALESCE(SUM(inv.stock * {$precioVenta}), 0) AS valor_venta,
                COALESCE(SUM(CASE WHEN {$costo} <= 0 THEN 1 ELSE 0 END), 0) AS sin_costo,
                COALESCE(SUM(CASE WHEN inv.stock_minimo > 0 AND inv.stock <= inv.stock_minimo THEN 1 ELSE 0 END), 0) AS bajo_minimo
            ")
            ->groupBy('cat.id', 'cat.nombre')
            ->orderByDesc('invertido')
            ->paginate($perPage)
            ->through(fn($row) => $this->mapGrupo($row));
    }

    private function porProveedor($base, string $costo, string $precioVenta, int $perPage)
    {
        return $base
            ->selectRaw("
                pr.id,
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
            ->paginate($perPage)
            ->through(fn($row) => $this->mapGrupo($row));
    }

    private function costoSql(): string
    {
        return 'COALESCE(v.precio_costo, p.precio_costo, 0)';
    }

    private function precioVentaSql(): string
    {
        return 'COALESCE(v.precio_venta, p.precio_venta, 0)';
    }

    private function mapProducto(object $row): array
    {
        $stock = (float) $row->stock;
        $stockMinimo = (float) $row->stock_minimo;
        $valorVenta = (float) $row->valor_venta;
        $invertido = (float) $row->invertido;

        return [
            'id' => (int) $row->producto_id,
            'producto_id' => (int) $row->producto_id,
            'codigo' => $row->codigo,
            'clave' => $row->clave,
            'producto' => $row->producto,
            'categoria' => $row->categoria,
            'proveedor' => $row->proveedor,
            'articulos' => (int) $row->renglones_inventario,
            'variantes' => (int) $row->variantes,
            'stock' => $stock,
            'stock_minimo' => $stockMinimo,
            'costo' => (float) $row->costo,
            'precio_venta' => (float) $row->precio_venta,
            'invertido' => $invertido,
            'valor_venta' => $valorVenta,
            'margen' => $valorVenta > 0 ? round((($valorVenta - $invertido) / $valorVenta) * 100, 2) : 0,
            'sin_costo' => (int) $row->sin_costo_count > 0,
            'bajo_minimo' => (int) $row->bajo_minimo_count > 0,
            'sin_costo_count' => (int) $row->sin_costo_count,
            'bajo_minimo_count' => (int) $row->bajo_minimo_count,
        ];
    }

    private function mapGrupo(object $row): array
    {
        $valorVenta = (float) $row->valor_venta;
        $invertido = (float) $row->invertido;

        return [
            'id' => $row->id ? (int) $row->id : null,
            'nombre' => $row->nombre,
            'articulos' => (int) $row->articulos,
            'unidades' => (float) $row->unidades,
            'invertido' => $invertido,
            'valor_venta' => $valorVenta,
            'margen' => $valorVenta > 0 ? round((($valorVenta - $invertido) / $valorVenta) * 100, 2) : 0,
            'sin_costo' => (int) $row->sin_costo,
            'bajo_minimo' => (int) $row->bajo_minimo,
        ];
    }
}
