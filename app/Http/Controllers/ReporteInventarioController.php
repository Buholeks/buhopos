<?php

namespace App\Http\Controllers;

use App\Exportaciones\InventarioExportacion;
use App\Exportaciones\ServicioExportacion;
use App\Models\Empresa;
use App\Models\Sucursal;
use App\Servicios\InventarioReporteConsulta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ReporteInventarioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('reportes.ver'), 403, 'Sin permiso: reportes.ver');

        $data = $request->validate([
            'categoria_id' => ['nullable', 'integer', Rule::exists('categorias', 'id')->where('empresa_id', $request->user()->empresa_id)],
            'orden' => ['nullable', Rule::in(['producto', 'stock', 'comprometido', 'disponible', 'costo', 'precio_venta', 'invertido'])],
            'direccion' => ['nullable', Rule::in(['asc', 'desc'])],
            'q'          => ['nullable', 'string', 'max:120'],
            'agrupar'    => ['nullable', Rule::in(['producto', 'categoria', 'proveedor'])],
            'filtro'     => ['nullable', Rule::in(['todos', 'con_existencia', 'agotados', 'sin_costo', 'bajo_minimo'])],
            'series'     => ['nullable', Rule::in(['todos', 'con_series', 'sin_series'])],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page'       => ['nullable', 'integer', 'min:1'],
        ]);

        $user = $request->user();
        $agrupar = $data['agrupar'] ?? 'producto';
        $perPage = (int) ($data['por_pagina'] ?? 30);

        $filtrosBase = [
            'categoria_id' => $data['categoria_id'] ?? null,
            'q'            => $data['q'] ?? null,
            'series'       => $data['series'] ?? 'todos',
            'filtro'       => $data['filtro'] ?? 'todos',
        ];

        $base = InventarioReporteConsulta::base($user->empresa_id, $user->sucursal_id, $filtrosBase);
        $baseResumen = InventarioReporteConsulta::base($user->empresa_id, $user->sucursal_id, $filtrosBase, false);
        $costo = InventarioReporteConsulta::costoSql();
        $precioVenta = InventarioReporteConsulta::precioVentaSql();
        $resumen = InventarioReporteConsulta::resumen($baseResumen);

        $direccion = $data['direccion'] ?? 'desc';
        $rows = match ($agrupar) {
            'categoria' => $this->porCategoria($base, $costo, $precioVenta, $perPage, $direccion),
            'proveedor' => $this->porProveedor($base, $costo, $precioVenta, $perPage, $direccion),
            default => $this->porProducto(clone $base, $costo, $precioVenta, $perPage, $data['orden'] ?? 'invertido', $direccion),
        };

        if ($agrupar === 'producto') {
            $productoIds = $rows->getCollection()->pluck('producto_id')->all();
            $seriesFilas = \App\Servicios\SeriesInventarioReporte::filas($user->empresa_id, $user->sucursal_id, $productoIds);
            $series = \App\Servicios\SeriesInventarioReporte::etiquetasPorProducto($seriesFilas);
            $variantes = $this->detalleVariantes(clone $base, $productoIds, $user->empresa_id, $seriesFilas);
            $rows->through(function ($row) use ($series, $variantes) {
                $row['series'] = $series->get($row['producto_id'], []);
                $row['detalle_variantes'] = $variantes->get($row['producto_id'], []);
                return $row;
            });
        }

        return response()->json([
            'categorias' => DB::table('categorias')->where('empresa_id', $user->empresa_id)->whereNull('deleted_at')->orderBy('nombre')->get(['id', 'nombre']),
            'resumen' => $resumen,
            'agrupar' => $agrupar,
            'items' => $rows,
        ]);
    }

    public function exportar(Request $request, ServicioExportacion $servicio): mixed
    {
        abort_unless(Auth::user()->tienePermiso('reportes.ver'), 403, 'Sin permiso: reportes.ver');

        $data = $request->validate([
            'categoria_id' => ['nullable', 'integer', Rule::exists('categorias', 'id')->where('empresa_id', $request->user()->empresa_id)],
            'orden' => ['nullable', Rule::in(['producto', 'stock', 'comprometido', 'disponible', 'costo', 'precio_venta', 'invertido'])],
            'direccion' => ['nullable', Rule::in(['asc', 'desc'])],
            'q'       => ['nullable', 'string', 'max:120'],
            'agrupar' => ['nullable', Rule::in(['producto', 'categoria', 'proveedor'])],
            'filtro'  => ['nullable', Rule::in(['todos', 'con_existencia', 'agotados', 'sin_costo', 'bajo_minimo'])],
            'series'  => ['nullable', Rule::in(['todos', 'con_series', 'sin_series'])],
            'columnas' => ['nullable', 'array'],
            'columnas.*' => ['string', Rule::in(['clave', 'producto', 'categoria', 'proveedor', 'stock', 'comprometido', 'disponible', 'variantes', 'costo', 'precio_venta', 'invertido', 'valor_venta', 'margen', 'alertas', 'series'])],
            'formato' => ['required', 'in:pdf,excel'],
        ]);

        $user    = Auth::user();
        $agrupar = $data['agrupar'] ?? 'producto';
        $filtros = [
            'categoria_id' => $data['categoria_id'] ?? null,
            'orden' => $data['orden'] ?? 'invertido',
            'direccion' => $data['direccion'] ?? 'desc',
            'q'      => $data['q'] ?? null,
            'filtro' => $data['filtro'] ?? 'todos',
            'series' => $data['series'] ?? 'todos',
            'columnas' => $data['columnas'] ?? [],
        ];

        $nombre      = 'inventario_' . $agrupar . '_' . now()->format('Ymd');
        $exportacion = new InventarioExportacion($user->empresa_id, $user->sucursal_id, $agrupar, $filtros);

        if ($data['formato'] === 'pdf') {
            $baseResumen = InventarioReporteConsulta::base($user->empresa_id, $user->sucursal_id, $filtros, false);
            $resumen     = InventarioReporteConsulta::resumen($baseResumen);

            $rawItems = $agrupar === 'producto'
                ? $exportacion->datosCompletosProducto()->toArray()
                : $exportacion->datos()->toArray();

            $items = match ($agrupar) {
                'categoria', 'proveedor' => array_map(fn($f) => [
                    'nombre'      => $f[0],
                    'articulos'   => $f[1],
                    'unidades'    => $f[2],
                    'invertido'   => $f[3],
                    'valor_venta' => $f[4],
                    'margen'      => $f[5],
                    'sin_costo'   => $f[6],
                    'bajo_minimo' => $f[7],
                ], $rawItems),
                default => array_map(fn($f) => [
                    'codigo'      => $f[0],
                    'producto'    => $f[1],
                    'categoria'   => $f[2],
                    'proveedor'   => $f[3],
                    'stock'       => $f[4],
                    'comprometido' => $f[5],
                    'disponible' => $f[6],
                    'variantes'   => $f[7],
                    'costo'       => $f[8],
                    'precio_venta' => $f[13],
                    'series' => $f[14],
                    'invertido'   => $f[9],
                    'valor_venta' => $f[10],
                    'margen'      => $f[11],
                    'sin_costo'   => str_contains($f[12], 'Sin costo'),
                    'bajo_minimo' => str_contains($f[12], 'Bajo mínimo'),
                ], $rawItems),
            };

            $empresa  = Empresa::find($user->empresa_id);
            $sucursal = Sucursal::find($user->sucursal_id);

            $logoB64 = null;
            if ($empresa?->logo && Storage::disk('public')->exists($empresa->logo)) {
                $contenido = Storage::disk('public')->get($empresa->logo);
                $mime      = Storage::disk('public')->mimeType($empresa->logo) ?: 'image/png';
                $logoB64   = 'data:' . $mime . ';base64,' . base64_encode($contenido);
            }

            $pdf = Pdf::loadView('pdf.inventario', [
                'resumen'           => $resumen,
                'items'             => $items,
                'columnas'          => $exportacion->columnasSeleccionadas(),
                'agrupar'           => $agrupar,
                'titulo'            => 'Reporte de productos y existencias',
                'filtrosAplicados'  => $exportacion->filtrosAplicados(),
                'empresaNombre'     => $empresa?->nombre ?? config('app.name'),
                'empresaLogoB64'    => $logoB64,
                'empresaDireccion'  => $empresa?->direccion,
                'sucursalNombre'    => $sucursal?->nombre,
                'sucursalDireccion' => $sucursal?->direccion,
                'fecha'             => now('America/Mexico_City')->format('d/m/Y H:i'),
            ])->setPaper('letter', count($exportacion->columnasSeleccionadas()) <= 5 ? 'portrait' : 'landscape');

            return $pdf->download("{$nombre}.pdf");
        }

        return $servicio->exportar($exportacion, 'excel', $nombre);
    }

    private function detalleVariantes($base, array $productoIds, int $empresaId, \Illuminate\Support\Collection $seriesFilas)
    {
        if (!$productoIds) return collect();

        $inventario = $base->whereIn('inv.producto_id', $productoIds)
            ->select('inv.id', 'inv.producto_id', 'inv.variante_id', 'inv.stock', 'v.sku')
            ->selectRaw(\App\Servicios\InventarioComprometidoReporte::sql() . ' AS comprometido')
            ->selectRaw(\App\Servicios\InventarioComprometidoReporte::disponibleSql() . ' AS disponible')
            ->selectRaw('COALESCE(v.precio_costo, p.precio_costo, 0) AS costo, COALESCE(v.precio_venta, p.precio_venta, 0) AS precio_venta')
            ->orderBy('v.sku')->orderBy('inv.id')->get();
        $variantes = \App\Models\ProductoVariante::withTrashed()
            ->where('empresa_id', $empresaId)->whereIn('id', $inventario->pluck('variante_id')->filter())
            ->with(['atributos.tipoAtributo', 'atributos.atributo'])->get()->keyBy('id');
        $series = $seriesFilas->groupBy(fn($s) => $s->producto_id . ':' . ($s->variante_id ?? ''));

        return $inventario->map(function ($fila) use ($variantes, $series) {
            $fila->nombre = $fila->variante_id
                ? ($variantes->get($fila->variante_id)?->nombreVariante() ?: ($fila->sku ?: 'Variante'))
                : 'Sin variante';
            $fila->series = $series->get($fila->producto_id . ':' . ($fila->variante_id ?? ''), collect())
                ->map(fn($s) => $s->imei ?: ($s->serie ?: $s->imei2))->filter()->unique()->values()->all();
            return $fila;
        })->groupBy('producto_id');
    }

    private function porProducto($base, string $costo, string $precioVenta, int $perPage, string $orden = 'invertido', string $direccion = 'desc')
    {
        $comprometido = \App\Servicios\InventarioComprometidoReporte::sql();
        $disponible = \App\Servicios\InventarioComprometidoReporte::disponibleSql();

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
                COALESCE(SUM({$comprometido}), 0) AS comprometido,
                COALESCE(SUM({$disponible}), 0) AS disponible,
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
            ->orderBy($orden, $direccion)
            ->orderBy('p.id')
            ->paginate($perPage)
            ->through(fn($row) => $this->mapProducto($row));
    }

    private function porCategoria($base, string $costo, string $precioVenta, int $perPage, string $direccion = 'desc')
    {
        return $base
            ->selectRaw("
                cat.id,
                COALESCE(cat.nombre, 'Sin categoria') AS nombre,
                COUNT(DISTINCT p.id) AS articulos,
                COALESCE(SUM(inv.stock), 0) AS unidades,
                COALESCE(SUM(inv.stock * {$costo}), 0) AS invertido,
                COALESCE(SUM(inv.stock * {$precioVenta}), 0) AS valor_venta,
                COUNT(DISTINCT CASE WHEN {$costo} <= 0 THEN p.id END) AS sin_costo,
                COUNT(DISTINCT CASE WHEN inv.stock_minimo > 0 AND inv.stock <= inv.stock_minimo THEN p.id END) AS bajo_minimo
            ")
            ->groupBy('cat.id', 'cat.nombre')
            ->orderBy('invertido', $direccion === 'asc' ? 'asc' : 'desc')
            ->paginate($perPage)
            ->through(fn($row) => $this->mapGrupo($row));
    }

    private function porProveedor($base, string $costo, string $precioVenta, int $perPage, string $direccion = 'desc')
    {
        return $base
            ->selectRaw("
                pr.id,
                COALESCE(pr.nombre_comercial, pr.razon_social, 'Sin proveedor') AS nombre,
                COUNT(DISTINCT p.id) AS articulos,
                COALESCE(SUM(inv.stock), 0) AS unidades,
                COALESCE(SUM(inv.stock * {$costo}), 0) AS invertido,
                COALESCE(SUM(inv.stock * {$precioVenta}), 0) AS valor_venta,
                COUNT(DISTINCT CASE WHEN {$costo} <= 0 THEN p.id END) AS sin_costo,
                COUNT(DISTINCT CASE WHEN inv.stock_minimo > 0 AND inv.stock <= inv.stock_minimo THEN p.id END) AS bajo_minimo
            ")
            ->groupBy('pr.id', 'pr.nombre_comercial', 'pr.razon_social')
            ->orderBy('invertido', $direccion === 'asc' ? 'asc' : 'desc')
            ->paginate($perPage)
            ->through(fn($row) => $this->mapGrupo($row));
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
            'comprometido' => (float) $row->comprometido,
            'disponible' => (float) $row->disponible,
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
