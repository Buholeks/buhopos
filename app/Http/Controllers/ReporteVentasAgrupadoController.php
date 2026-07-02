<?php

namespace App\Http\Controllers;

use App\Exportaciones\ServicioExportacion;
use App\Exportaciones\VentasAgrupadoExportacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReporteVentasAgrupadoController extends Controller
{
    // ── Entidades disponibles para autocomplete ───────────────────────────
    private const ENTIDADES = [
        'clientes'    => ['tabla' => 'clientes',    'campo' => 'nombre',           'por_sucursal' => false],
        'categorias'  => ['tabla' => 'categorias',  'campo' => 'nombre',           'por_sucursal' => false],
        'marcas'      => ['tabla' => 'marcas',       'campo' => 'nombre',           'por_sucursal' => false],
        'modelos'     => ['tabla' => 'modelos',      'campo' => 'nombre',           'por_sucursal' => false],
        'proveedores' => ['tabla' => 'proveedores',  'campo' => 'nombre_comercial', 'por_sucursal' => false],
    ];

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/reportes/ventas-agrupado/buscar/{entidad}
    // ─────────────────────────────────────────────────────────────────────
    public function buscar(Request $request, string $entidad): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('reportes.ver'), 403, 'Sin permiso: reportes.ver');
        if (! isset(self::ENTIDADES[$entidad])) {
            return response()->json(['message' => 'Entidad no válida.'], 422);
        }

        $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        $cfg   = self::ENTIDADES[$entidad];
        $texto = trim((string) $request->query('q', ''));
        $user  = Auth::user();

        $resultados = DB::table($cfg['tabla'])
            ->where('empresa_id',  $user->empresa_id)
            ->when($cfg['por_sucursal'], fn($q) => $q->where('sucursal_id', $user->sucursal_id))
            ->select('id', DB::raw("{$cfg['campo']} as nombre"))
            ->when($texto !== '', fn($q) => $q->where($cfg['campo'], 'like', "%{$texto}%"))
            ->orderBy($cfg['campo'])
            ->limit(15)
            ->get();

        return response()->json($resultados);
    }

    public function porCliente(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('reportes.ver'), 403, 'Sin permiso: reportes.ver');
        $this->validar($request);
        return response()->json($this->paginar($this->queryClientes(Auth::user(), $request), $request));
    }

    public function porCategoria(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('reportes.ver'), 403, 'Sin permiso: reportes.ver');
        $this->validar($request);
        return response()->json($this->paginar($this->queryCategorias(Auth::user(), $request), $request));
    }

    public function porMarca(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('reportes.ver'), 403, 'Sin permiso: reportes.ver');
        $this->validar($request);
        return response()->json($this->paginar($this->queryMarcas(Auth::user(), $request), $request));
    }

    public function porModelo(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('reportes.ver'), 403, 'Sin permiso: reportes.ver');
        $this->validar($request);
        return response()->json($this->paginar($this->queryModelos(Auth::user(), $request), $request));
    }

    public function porProveedor(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('reportes.ver'), 403, 'Sin permiso: reportes.ver');
        $this->validar($request);
        return response()->json($this->paginar($this->queryProveedores(Auth::user(), $request), $request));
    }

    public function porArticulo(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('reportes.ver'), 403, 'Sin permiso: reportes.ver');
        $this->validarArticulos($request);
        return response()->json($this->paginar($this->queryArticulos(Auth::user(), $request), $request));
    }

    public function detalleArticulos(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('reportes.ver'), 403, 'Sin permiso: reportes.ver');
        $this->validarArticulos($request);
        return response()->json($this->paginar($this->queryDetalleArticulos(Auth::user(), $request), $request));
    }

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/reportes/ventas-agrupado/exportar
    // ─────────────────────────────────────────────────────────────────────
    public function exportar(Request $request, ServicioExportacion $servicio)
    {
        abort_unless(Auth::user()->tienePermiso('reportes.ver'), 403, 'Sin permiso: reportes.ver');

        $datos = $request->validate([
            'tab'          => ['required', 'in:clientes,categorias,marcas,modelos,proveedores,articulos,articulos_detalle'],
            'fecha_desde'  => ['required', 'date'],
            'fecha_hasta'  => ['required', 'date', 'after_or_equal:fecha_desde'],
            'forma_pago'   => ['nullable', 'in:efectivo,tarjeta,transferencia'],
            'estado'       => ['nullable', 'in:confirmada,cancelada'],
            'entidad_id'   => ['nullable', 'integer'],
            'categoria_id' => ['nullable', 'integer'],
            'producto_id'  => ['nullable', 'integer'],
            'producto'     => ['nullable', 'string', 'max:120'],
            'formato'      => ['required', 'in:excel,pdf'],
        ]);

        $user = Auth::user();
        $tab  = $datos['tab'];

        [$cabeceras, $filas, $totales, $titulo] = match ($tab) {
            'clientes'          => $this->exportClientes($user, $request),
            'categorias'        => $this->exportCategorias($user, $request),
            'marcas'            => $this->exportMarcas($user, $request),
            'modelos'           => $this->exportModelos($user, $request),
            'proveedores'       => $this->exportProveedores($user, $request),
            'articulos'         => $this->exportArticulos($user, $request),
            'articulos_detalle' => $this->exportDetalleArticulos($user, $request),
        };

        $exportacion = new VentasAgrupadoExportacion(
            empresaId:   (int) $user->empresa_id,
            sucursalId:  (int) $user->sucursal_id,
            titulo:      $titulo,
            cabeceras:   $cabeceras,
            coleccion:   $filas,
            filaTotales: $totales,
            filtros:     $datos,
        );

        $nombre = "ventas_{$tab}_" . now()->format('Ymd_His');

        return $servicio->exportar($exportacion, $datos['formato'], $nombre);
    }

    // ═════════════════════════════════════════════════════════════════════
    // QUERY BUILDERS PRIVADOS
    // ═════════════════════════════════════════════════════════════════════

    private function queryClientes($user, Request $request)
    {
        return $this->aplicarFiltros($request,
            $this->baseVentas($user)
                ->leftJoin('clientes as c', 'c.id', '=', 'v.cliente_id')
                ->when($request->filled('entidad_id'), fn($q) => $q->where('v.cliente_id', $request->entidad_id))
                ->selectRaw("v.cliente_id, COALESCE(c.nombre, 'Público general') as cliente, {$this->colsVenta()}")
                ->groupBy('v.cliente_id', 'c.nombre')
                ->orderByDesc(DB::raw("SUM(CASE WHEN v.estado = 'confirmada' THEN v.total ELSE 0 END)"))
        );
    }

    private function queryCategorias($user, Request $request)
    {
        return $this->aplicarFiltros($request,
            $this->baseDetalles($user)
                ->leftJoin('categorias as cat', 'cat.id', '=', 'p.categoria_id')
                ->when($request->filled('entidad_id'), fn($q) => $q->where('p.categoria_id', $request->entidad_id))
                ->selectRaw("p.categoria_id, COALESCE(cat.nombre, 'Sin categoría') as categoria, {$this->colsDetalle()}")
                ->groupBy('p.categoria_id', 'cat.nombre')
                ->orderByDesc(DB::raw("SUM(CASE WHEN v.estado = 'confirmada' THEN vd.subtotal ELSE 0 END)"))
        );
    }

    private function queryMarcas($user, Request $request)
    {
        return $this->aplicarFiltros($request,
            $this->baseDetalles($user)
                ->leftJoin('marcas as m', 'm.id', '=', 'p.marca_id')
                ->when($request->filled('entidad_id'), fn($q) => $q->where('p.marca_id', $request->entidad_id))
                ->selectRaw("p.marca_id, COALESCE(m.nombre, 'Sin marca') as marca, {$this->colsDetalle()}")
                ->groupBy('p.marca_id', 'm.nombre')
                ->orderByDesc(DB::raw("SUM(CASE WHEN v.estado = 'confirmada' THEN vd.subtotal ELSE 0 END)"))
        );
    }

    private function queryModelos($user, Request $request)
    {
        return $this->aplicarFiltros($request,
            $this->baseDetalles($user)
                ->leftJoin('modelos as mo', 'mo.id', '=', 'p.modelo_id')
                ->when($request->filled('entidad_id'), fn($q) => $q->where('p.modelo_id', $request->entidad_id))
                ->selectRaw("p.modelo_id, COALESCE(mo.nombre, 'Sin modelo') as modelo, {$this->colsDetalle()}")
                ->groupBy('p.modelo_id', 'mo.nombre')
                ->orderByDesc(DB::raw("SUM(CASE WHEN v.estado = 'confirmada' THEN vd.subtotal ELSE 0 END)"))
        );
    }

    private function queryProveedores($user, Request $request)
    {
        $proveedorProducto = DB::table('compra_detalles as cd')
            ->join('compras as co', 'co.id', '=', 'cd.compra_id')
            ->where('co.empresa_id', $user->empresa_id)
            ->where('co.sucursal_id', $user->sucursal_id)
            ->selectRaw('cd.producto_id, co.proveedor_id, SUM(cd.cantidad) as total_cantidad')
            ->groupBy('cd.producto_id', 'co.proveedor_id');

        $proveedorPrincipal = DB::query()
            ->fromSub($proveedorProducto, 'pp1')
            ->selectRaw('pp1.producto_id, pp1.proveedor_id, pp1.total_cantidad')
            ->whereRaw('pp1.total_cantidad = (
                SELECT MAX(pp2.total_cantidad)
                FROM (
                    SELECT cd.producto_id, co.proveedor_id, SUM(cd.cantidad) as total_cantidad
                    FROM compra_detalles cd
                    INNER JOIN compras co ON co.id = cd.compra_id
                    WHERE co.empresa_id = ? AND co.sucursal_id = ?
                    GROUP BY cd.producto_id, co.proveedor_id
                ) as pp2
                WHERE pp2.producto_id = pp1.producto_id
            )', [$user->empresa_id, $user->sucursal_id]);

        return $this->aplicarFiltros($request,
            $this->baseDetalles($user)
                ->leftJoinSub($proveedorPrincipal, 'pp', fn($j) => $j->on('pp.producto_id', '=', 'vd.producto_id'))
                ->leftJoin('proveedores as prov', 'prov.id', '=', 'pp.proveedor_id')
                ->when($request->filled('entidad_id'), fn($q) => $q->where('pp.proveedor_id', $request->entidad_id))
                ->selectRaw("pp.proveedor_id, COALESCE(prov.nombre_comercial, 'Sin proveedor') as proveedor, {$this->colsDetalle()}")
                ->groupBy('pp.proveedor_id', 'prov.nombre_comercial')
                ->orderByDesc(DB::raw("SUM(CASE WHEN v.estado = 'confirmada' THEN vd.subtotal ELSE 0 END)"))
        );
    }

    private function queryArticulos($user, Request $request)
    {
        return $this->aplicarFiltros($request,
            $this->baseDetalles($user)
                ->leftJoin('categorias as cat', 'cat.id', '=', 'p.categoria_id')
                ->when($request->filled('categoria_id'), fn($q) => $q->where('p.categoria_id', $request->categoria_id))
                ->when($request->filled('producto_id'),  fn($q) => $q->where('p.id', $request->producto_id))
                ->when($request->filled('producto'), function ($q) use ($request) {
                    $txt = trim($request->producto);
                    $q->where(fn($s) => $s->where('p.nombre', 'like', "%{$txt}%")->orWhere('p.codigo', 'like', "%{$txt}%"));
                })
                ->selectRaw("
                    p.id as producto_id, p.codigo as clave, p.nombre as articulo,
                    COALESCE(cat.nombre, 'Sin categoría') as categoria,
                    COALESCE(SUM(CASE WHEN v.estado = 'confirmada' THEN vd.cantidad  ELSE 0 END), 0) as cantidad_vendida,
                    COALESCE(SUM(CASE WHEN v.estado = 'confirmada' THEN vd.subtotal  ELSE 0 END), 0) as importe_total
                ")
                ->groupBy('p.id', 'p.codigo', 'p.nombre', 'cat.nombre')
                ->orderByDesc('cantidad_vendida')
                ->orderBy('p.nombre')
        );
    }

    private function queryDetalleArticulos($user, Request $request)
    {
        $nombreVariantes = DB::table('variante_atributos as pva')
            ->join('tipo_atributos as ta', 'ta.id', '=', 'pva.tipo_atributo_id')
            ->join('atributos as a', 'a.id', '=', 'pva.atributo_id')
            ->selectRaw("pva.variante_id, GROUP_CONCAT(CONCAT(ta.nombre, ': ', a.valor) ORDER BY ta.nombre SEPARATOR ' / ') as nombre_variante")
            ->groupBy('pva.variante_id');

        return $this->aplicarFiltros($request,
            $this->baseDetalles($user)
                ->leftJoin('categorias as cat',        'cat.id', '=', 'p.categoria_id')
                ->leftJoin('producto_variantes as pv', 'pv.id',  '=', 'vd.variante_id')
                ->leftJoinSub($nombreVariantes, 'nv',  fn($j) => $j->on('nv.variante_id', '=', 'pv.id'))
                ->when($request->filled('categoria_id'), fn($q) => $q->where('p.categoria_id', $request->categoria_id))
                ->when($request->filled('producto_id'),  fn($q) => $q->where('p.id', $request->producto_id))
                ->when($request->filled('producto'), function ($q) use ($request) {
                    $txt = trim($request->producto);
                    $q->where(fn($s) => $s->where('p.nombre', 'like', "%{$txt}%")->orWhere('p.codigo', 'like', "%{$txt}%")->orWhere('pv.sku', 'like', "%{$txt}%"));
                })
                ->selectRaw("
                    p.id as producto_id, vd.variante_id, p.codigo as clave, p.nombre as articulo,
                    COALESCE(cat.nombre, 'Sin categoría') as categoria,
                    pv.sku as sku_variante,
                    COALESCE(nv.nombre_variante, 'Sin variante') as nombre_variante,
                    vd.precio_venta,
                    COALESCE(SUM(CASE WHEN v.estado = 'confirmada' THEN vd.cantidad ELSE 0 END), 0) as cantidad_vendida,
                    COALESCE(SUM(CASE WHEN v.estado = 'confirmada' THEN vd.subtotal ELSE 0 END), 0) as importe_subtotal
                ")
                ->groupBy('p.id', 'vd.variante_id', 'p.codigo', 'p.nombre', 'cat.nombre', 'pv.sku', 'nv.nombre_variante', 'vd.precio_venta')
                ->orderBy('p.nombre')
                ->orderBy('pv.sku')
                ->orderBy('vd.precio_venta')
        );
    }

    // ═════════════════════════════════════════════════════════════════════
    // HELPERS DE EXPORTACIÓN
    // ═════════════════════════════════════════════════════════════════════

    private function exportClientes($user, Request $request): array
    {
        $rows = $this->withTicket($this->queryClientes($user, $request)->get());
        $cols = ['Cliente', 'Ventas', 'Confirmadas', 'Canceladas', 'Total', 'Descuentos', 'Ticket prom.', 'Efectivo', 'Tarjeta', 'Transferencia'];
        $filas = $rows->map(fn($r) => [
            $r->cliente, $r->num_ventas, $r->confirmadas, $r->canceladas,
            number_format($r->total, 2), number_format($r->descuentos, 2),
            number_format($r->ticket_prom ?? 0, 2),
            number_format($r->efectivo, 2), number_format($r->tarjeta, 2),
            number_format($r->transferencia, 2),
        ]);
        $tot = ['Totales', $rows->sum('num_ventas'), $rows->sum('confirmadas'), $rows->sum('canceladas'),
            number_format($rows->sum('total'), 2), number_format($rows->sum('descuentos'), 2), '',
            number_format($rows->sum('efectivo'), 2), number_format($rows->sum('tarjeta'), 2),
            number_format($rows->sum('transferencia'), 2)];
        return [$cols, $filas, $tot, 'Ventas por Cliente'];
    }

    private function exportCategorias($user, Request $request): array
    {
        return $this->exportAgrupado($this->queryCategorias($user, $request)->get(), 'categoria', 'Categoría', 'Ventas por Categoría');
    }

    private function exportMarcas($user, Request $request): array
    {
        return $this->exportAgrupado($this->queryMarcas($user, $request)->get(), 'marca', 'Marca', 'Ventas por Marca');
    }

    private function exportModelos($user, Request $request): array
    {
        return $this->exportAgrupado($this->queryModelos($user, $request)->get(), 'modelo', 'Modelo', 'Ventas por Modelo');
    }

    private function exportProveedores($user, Request $request): array
    {
        return $this->exportAgrupado($this->queryProveedores($user, $request)->get(), 'proveedor', 'Proveedor', 'Ventas por Proveedor');
    }

    private function exportAgrupado(Collection $rows, string $campo, string $labelCol, string $titulo): array
    {
        $cols = [$labelCol, 'Ventas', 'Unidades', 'Total', 'Margen', 'Efectivo', 'Tarjeta', 'Transferencia'];
        $filas = $rows->map(fn($r) => [
            $r->$campo, $r->num_ventas, $r->unidades,
            number_format($r->total, 2), number_format($r->margen, 2),
            number_format($r->efectivo, 2), number_format($r->tarjeta, 2),
            number_format($r->transferencia, 2),
        ]);
        $tot = ['Totales', $rows->sum('num_ventas'), $rows->sum('unidades'),
            number_format($rows->sum('total'), 2), number_format($rows->sum('margen'), 2),
            number_format($rows->sum('efectivo'), 2), number_format($rows->sum('tarjeta'), 2),
            number_format($rows->sum('transferencia'), 2)];
        return [$cols, $filas, $tot, $titulo];
    }

    private function exportArticulos($user, Request $request): array
    {
        $rows = $this->queryArticulos($user, $request)->get();
        $cols = ['Clave', 'Artículo', 'Categoría', 'Cantidad vendida', 'Importe'];
        $filas = $rows->map(fn($r) => [
            $r->clave ?? '—', $r->articulo, $r->categoria,
            $r->cantidad_vendida, number_format($r->importe_total, 2),
        ]);
        $tot = ['', '', 'Totales', $rows->sum('cantidad_vendida'), number_format($rows->sum('importe_total'), 2)];
        return [$cols, $filas, $tot, 'Ventas por Artículo'];
    }

    private function exportDetalleArticulos($user, Request $request): array
    {
        $rows = $this->queryDetalleArticulos($user, $request)->get();
        $cols = ['Clave', 'Artículo', 'Categoría', 'Variante', 'Cantidad', 'Precio venta', 'Importe'];
        $filas = $rows->map(fn($r) => [
            $r->clave ?? '—', $r->articulo, $r->categoria,
            $r->nombre_variante ?? $r->sku_variante ?? '—',
            $r->cantidad_vendida, number_format($r->precio_venta, 2),
            number_format($r->importe_subtotal, 2),
        ]);
        $tot = ['', '', '', '', $rows->sum('cantidad_vendida'), '', number_format($rows->sum('importe_subtotal'), 2)];
        return [$cols, $filas, $tot, 'Detalle de Artículos Vendidos'];
    }

    // ═════════════════════════════════════════════════════════════════════
    // HELPERS PRIVADOS
    // ═════════════════════════════════════════════════════════════════════

    private function validar(Request $request): void
    {
        $request->validate([
            'fecha_desde' => ['required', 'date'],
            'fecha_hasta' => ['required', 'date', 'after_or_equal:fecha_desde'],
            'forma_pago'  => ['nullable', 'in:efectivo,tarjeta,transferencia'],
            'estado'      => ['nullable', 'in:confirmada,cancelada'],
            'entidad_id'  => ['nullable', 'integer'],
            'por_pagina'  => ['nullable', 'integer', 'min:1', 'max:200'],
            'page'        => ['nullable', 'integer', 'min:1'],
        ]);
    }

    private function validarArticulos(Request $request): void
    {
        $request->validate([
            'fecha_desde'  => ['required', 'date'],
            'fecha_hasta'  => ['required', 'date', 'after_or_equal:fecha_desde'],
            'forma_pago'   => ['nullable', 'in:efectivo,tarjeta,transferencia'],
            'estado'       => ['nullable', 'in:confirmada,cancelada'],
            'categoria_id' => ['nullable', 'integer'],
            'producto_id'  => ['nullable', 'integer'],
            'producto'     => ['nullable', 'string', 'max:120'],
            'por_pagina'   => ['nullable', 'integer', 'min:1', 'max:200'],
            'page'         => ['nullable', 'integer', 'min:1'],
        ]);
    }

    // Subquery con una fila por venta (evita el fan-out de unirse directo a venta_pagos)
    private function subPagosPorVenta()
    {
        return DB::table('venta_pagos')
            ->selectRaw("
                venta_id,
                SUM(CASE WHEN forma_pago = 'efectivo'      THEN monto ELSE 0 END) as efectivo,
                SUM(CASE WHEN forma_pago = 'tarjeta'       THEN monto ELSE 0 END) as tarjeta,
                SUM(CASE WHEN forma_pago = 'transferencia' THEN monto ELSE 0 END) as transferencia
            ")
            ->groupBy('venta_id');
    }

    private function baseVentas($user)
    {
        return DB::table('ventas as v')
            ->leftJoinSub($this->subPagosPorVenta(), 'vp', fn($j) => $j->on('vp.venta_id', '=', 'v.id'))
            ->where('v.empresa_id',  $user->empresa_id)
            ->where('v.sucursal_id', $user->sucursal_id);
    }

    private function baseDetalles($user)
    {
        return DB::table('ventas as v')
            ->join('venta_detalles as vd', 'vd.venta_id', '=', 'v.id')
            ->join('productos as p',       'p.id',         '=', 'vd.producto_id')
            ->leftJoinSub($this->subPagosPorVenta(), 'vp', fn($j) => $j->on('vp.venta_id', '=', 'v.id'))
            ->where('v.empresa_id',  $user->empresa_id)
            ->where('v.sucursal_id', $user->sucursal_id);
    }

    private function aplicarFiltros(Request $request, $query)
    {
        return $query
            ->whereDate('v.fecha', '>=', $request->fecha_desde)
            ->whereDate('v.fecha', '<=', $request->fecha_hasta)
            ->when($request->filled('forma_pago'), fn($q) => $q->whereExists(
                fn($sub) => $sub->select(DB::raw(1))
                    ->from('venta_pagos')
                    ->whereColumn('venta_pagos.venta_id', 'v.id')
                    ->where('venta_pagos.forma_pago', $request->forma_pago)
            ))
            ->when($request->filled('estado'),     fn($q) => $q->where('v.estado',     $request->estado));
    }

    private function paginar($query, Request $request)
    {
        $porPagina = (int) ($request->por_pagina ?? 30);
        $resultado = $query->paginate($porPagina);
        $resultado->getCollection()->transform(function ($row) {
            $row = (array) $row;
            if (isset($row['confirmadas']) && (int) $row['confirmadas'] > 0) {
                $row['ticket_prom'] = round((float) $row['total'] / (int) $row['confirmadas'], 2);
            }
            return $row;
        });
        return $resultado;
    }

    private function withTicket(Collection $rows): Collection
    {
        return $rows->map(function ($row) {
            $row = (array) $row;
            $confirmadas = (int) ($row['confirmadas'] ?? 0);
            $row['ticket_prom'] = $confirmadas > 0
                ? round((float) $row['total'] / $confirmadas, 2)
                : 0;
            return (object) $row;
        });
    }

    private function colsVenta(): string
    {
        return "
            COUNT(DISTINCT v.id)                                                                          AS num_ventas,
            COALESCE(SUM(CASE WHEN v.estado = 'confirmada' THEN 1          ELSE 0 END), 0)               AS confirmadas,
            COALESCE(SUM(CASE WHEN v.estado = 'cancelada'  THEN 1          ELSE 0 END), 0)               AS canceladas,
            COALESCE(SUM(CASE WHEN v.estado = 'confirmada' THEN v.total    ELSE 0 END), 0)               AS total,
            COALESCE(SUM(CASE WHEN v.estado = 'confirmada' THEN v.descuento ELSE 0 END), 0)              AS descuentos,
            COALESCE(SUM(CASE WHEN v.estado = 'confirmada' THEN vp.efectivo      ELSE 0 END), 0) AS efectivo,
            COALESCE(SUM(CASE WHEN v.estado = 'confirmada' THEN vp.tarjeta       ELSE 0 END), 0) AS tarjeta,
            COALESCE(SUM(CASE WHEN v.estado = 'confirmada' THEN vp.transferencia ELSE 0 END), 0) AS transferencia
        ";
    }

    // Nota: efectivo/tarjeta/transferencia se prorratean por línea según la
    // proporción de cada método en el total de la venta (una venta mixta no
    // tiene un solo método por línea de producto).
    private function colsDetalle(): string
    {
        return "
            COUNT(DISTINCT v.id)                                                                                          AS num_ventas,
            COALESCE(SUM(CASE WHEN v.estado = 'confirmada' THEN vd.cantidad ELSE 0 END), 0)                              AS unidades,
            COALESCE(SUM(CASE WHEN v.estado = 'confirmada' THEN vd.subtotal ELSE 0 END), 0)                              AS total,
            COALESCE(SUM(CASE WHEN v.estado = 'confirmada' THEN (vd.precio_venta - vd.precio_costo) * vd.cantidad ELSE 0 END), 0) AS margen,
            COALESCE(SUM(CASE WHEN v.estado = 'confirmada' THEN vd.subtotal * COALESCE(vp.efectivo,0)      / NULLIF(v.total,0) ELSE 0 END), 0) AS efectivo,
            COALESCE(SUM(CASE WHEN v.estado = 'confirmada' THEN vd.subtotal * COALESCE(vp.tarjeta,0)       / NULLIF(v.total,0) ELSE 0 END), 0) AS tarjeta,
            COALESCE(SUM(CASE WHEN v.estado = 'confirmada' THEN vd.subtotal * COALESCE(vp.transferencia,0) / NULLIF(v.total,0) ELSE 0 END), 0) AS transferencia
        ";
    }
}
