<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReporteVentasAgrupadoController extends Controller
{
    // ── Entidades disponibles para autocomplete ───────────────────────────
    private const ENTIDADES = [
        'clientes'    => ['tabla' => 'clientes',    'campo' => 'nombre'],
        'categorias'  => ['tabla' => 'categorias',  'campo' => 'nombre'],
        'marcas'      => ['tabla' => 'marcas',       'campo' => 'nombre'],
        'modelos'     => ['tabla' => 'modelos',      'campo' => 'nombre'],
        'proveedores' => ['tabla' => 'proveedores',  'campo' => 'nombre_comercial'],
    ];

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/reportes/ventas-agrupado/buscar/{entidad}
    // ─────────────────────────────────────────────────────────────────────
   public function buscar(Request $request, string $entidad): JsonResponse
{
    if (! isset(self::ENTIDADES[$entidad])) {
        return response()->json(['message' => 'Entidad no válida.'], 422);
    }

    $request->validate(['q' => ['nullable', 'string', 'max:100']]);

    $cfg   = self::ENTIDADES[$entidad];
    $texto = trim((string) $request->query('q', ''));
    $user  = Auth::user();

    $resultados = DB::table($cfg['tabla'])
        ->where('empresa_id',  $user->empresa_id)
        ->where('sucursal_id', $user->sucursal_id) // ← faltaba este
        ->select('id', DB::raw("{$cfg['campo']} as nombre"))
        ->when($texto !== '', fn($q) => $q->where($cfg['campo'], 'like', "%{$texto}%"))
        ->orderBy($cfg['campo'])
        ->limit(15)
        ->get();

    return response()->json($resultados);
}

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/reportes/ventas-agrupado/clientes
    // ─────────────────────────────────────────────────────────────────────
    public function porCliente(Request $request): JsonResponse
    {
        $this->validar($request);
        $user = Auth::user();

        $query = $this->baseVentas($user)
            ->leftJoin('clientes as c', 'c.id', '=', 'v.cliente_id')
            ->when($request->filled('entidad_id'), fn($q) => $q->where('v.cliente_id', $request->entidad_id))
            ->selectRaw("
                v.cliente_id,
                COALESCE(c.nombre, 'Público general') as cliente,
                {$this->colsVenta()}
            ")
            ->groupBy('v.cliente_id', 'c.nombre')
            ->orderByDesc(DB::raw("SUM(CASE WHEN v.estado = 'confirmada' THEN v.total ELSE 0 END)"));

        return response()->json(
            $this->paginar($this->aplicarFiltros($request, $query), $request)
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/reportes/ventas-agrupado/categorias
    // ─────────────────────────────────────────────────────────────────────
    public function porCategoria(Request $request): JsonResponse
    {
        $this->validar($request);
        $user = Auth::user();

        $query = $this->baseDetalles($user)
            ->leftJoin('categorias as cat', 'cat.id', '=', 'p.categoria_id')
            ->when($request->filled('entidad_id'), fn($q) => $q->where('p.categoria_id', $request->entidad_id))
            ->selectRaw("
                p.categoria_id,
                COALESCE(cat.nombre, 'Sin categoría') as categoria,
                {$this->colsDetalle()}
            ")
            ->groupBy('p.categoria_id', 'cat.nombre')
            ->orderByDesc(DB::raw("SUM(CASE WHEN v.estado = 'confirmada' THEN vd.subtotal ELSE 0 END)"));

        return response()->json(
            $this->paginar($this->aplicarFiltros($request, $query), $request)
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/reportes/ventas-agrupado/marcas
    // ─────────────────────────────────────────────────────────────────────
    public function porMarca(Request $request): JsonResponse
    {
        $this->validar($request);
        $user = Auth::user();

        $query = $this->baseDetalles($user)
            ->leftJoin('marcas as m', 'm.id', '=', 'p.marca_id')
            ->when($request->filled('entidad_id'), fn($q) => $q->where('p.marca_id', $request->entidad_id))
            ->selectRaw("
                p.marca_id,
                COALESCE(m.nombre, 'Sin marca') as marca,
                {$this->colsDetalle()}
            ")
            ->groupBy('p.marca_id', 'm.nombre')
            ->orderByDesc(DB::raw("SUM(CASE WHEN v.estado = 'confirmada' THEN vd.subtotal ELSE 0 END)"));

        return response()->json(
            $this->paginar($this->aplicarFiltros($request, $query), $request)
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/reportes/ventas-agrupado/modelos
    // ─────────────────────────────────────────────────────────────────────
    public function porModelo(Request $request): JsonResponse
    {
        $this->validar($request);
        $user = Auth::user();

        $query = $this->baseDetalles($user)
            ->leftJoin('modelos as mo', 'mo.id', '=', 'p.modelo_id')
            ->when($request->filled('entidad_id'), fn($q) => $q->where('p.modelo_id', $request->entidad_id))
            ->selectRaw("
                p.modelo_id,
                COALESCE(mo.nombre, 'Sin modelo') as modelo,
                {$this->colsDetalle()}
            ")
            ->groupBy('p.modelo_id', 'mo.nombre')
            ->orderByDesc(DB::raw("SUM(CASE WHEN v.estado = 'confirmada' THEN vd.subtotal ELSE 0 END)"));

        return response()->json(
            $this->paginar($this->aplicarFiltros($request, $query), $request)
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/reportes/ventas-agrupado/proveedores
    // Simplificado: proveedor más reciente del producto en lugar de el
    // que más unidades vendió (evita la subquery duplicada)
    // ─────────────────────────────────────────────────────────────────────
public function porProveedor(Request $request): JsonResponse
{
    $this->validar($request);
    $user = Auth::user();

    $proveedorProducto = DB::table('compra_detalles as cd')
        ->join('compras as co', 'co.id', '=', 'cd.compra_id')
        ->where('co.empresa_id', $user->empresa_id)
        ->where('co.sucursal_id', $user->sucursal_id)
        ->selectRaw('
            cd.producto_id,
            co.proveedor_id,
            SUM(cd.cantidad) as total_cantidad
        ')
        ->groupBy('cd.producto_id', 'co.proveedor_id');

    $proveedorPrincipal = DB::query()
        ->fromSub($proveedorProducto, 'pp1')
        ->selectRaw('
            pp1.producto_id,
            pp1.proveedor_id,
            pp1.total_cantidad
        ')
        ->whereRaw('
            pp1.total_cantidad = (
                SELECT MAX(pp2.total_cantidad)
                FROM (
                    SELECT
                        cd.producto_id,
                        co.proveedor_id,
                        SUM(cd.cantidad) as total_cantidad
                    FROM compra_detalles cd
                    INNER JOIN compras co ON co.id = cd.compra_id
                    WHERE co.empresa_id = ?
                      AND co.sucursal_id = ?
                    GROUP BY cd.producto_id, co.proveedor_id
                ) as pp2
                WHERE pp2.producto_id = pp1.producto_id
            )
        ', [$user->empresa_id, $user->sucursal_id]);

    $query = $this->baseDetalles($user)
        ->leftJoinSub($proveedorPrincipal, 'pp', function ($join) {
            $join->on('pp.producto_id', '=', 'vd.producto_id');
        })
        ->leftJoin('proveedores as prov', 'prov.id', '=', 'pp.proveedor_id')
        ->when($request->filled('entidad_id'), fn($q) => $q->where('pp.proveedor_id', $request->entidad_id))
        ->selectRaw("
            pp.proveedor_id,
            COALESCE(prov.nombre_comercial, 'Sin proveedor') as proveedor,
            {$this->colsDetalle()}
        ")
        ->groupBy('pp.proveedor_id', 'prov.nombre_comercial')
        ->orderByDesc(DB::raw("SUM(CASE WHEN v.estado = 'confirmada' THEN vd.subtotal ELSE 0 END)"));

    return response()->json(
        $this->paginar($this->aplicarFiltros($request, $query), $request)
    );
}

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/reportes/ventas-agrupado/articulos
    // ─────────────────────────────────────────────────────────────────────
    public function porArticulo(Request $request): JsonResponse
    {
        $this->validarArticulos($request);
        $user = Auth::user();

        $query = $this->baseDetalles($user)
            ->leftJoin('categorias as cat', 'cat.id', '=', 'p.categoria_id')
            ->when($request->filled('categoria_id'), fn($q) => $q->where('p.categoria_id', $request->categoria_id))
            ->when($request->filled('producto_id'),  fn($q) => $q->where('p.id', $request->producto_id))
            ->when($request->filled('producto'), function ($q) use ($request) {
                $txt = trim($request->producto);
                $q->where(fn($s) =>
                    $s->where('p.nombre', 'like', "%{$txt}%")
                      ->orWhere('p.codigo', 'like', "%{$txt}%")
                );
            })
            ->selectRaw("
                p.id         as producto_id,
                p.codigo     as clave,
                p.nombre     as articulo,
                COALESCE(cat.nombre, 'Sin categoría') as categoria,
                COALESCE(SUM(CASE WHEN v.estado = 'confirmada' THEN vd.cantidad  ELSE 0 END), 0) as cantidad_vendida,
                COALESCE(SUM(CASE WHEN v.estado = 'confirmada' THEN vd.subtotal  ELSE 0 END), 0) as importe_total
            ")
            ->groupBy('p.id', 'p.codigo', 'p.nombre', 'cat.nombre')
            ->orderByDesc('cantidad_vendida')
            ->orderBy('p.nombre');

        return response()->json(
            $this->paginar($this->aplicarFiltros($request, $query), $request)
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/reportes/ventas-agrupado/articulos/detalle
    // ─────────────────────────────────────────────────────────────────────
    public function detalleArticulos(Request $request): JsonResponse
    {
        $this->validarArticulos($request);
        $user = Auth::user();

        // Nombre legible de variante desde atributos
        $nombreVariantes = DB::table('variante_atributos as pva')
            ->join('tipo_atributos as ta', 'ta.id', '=', 'pva.tipo_atributo_id')
            ->join('atributos as a', 'a.id', '=', 'pva.atributo_id')
            ->selectRaw("
                pva.variante_id,
                GROUP_CONCAT(CONCAT(ta.nombre, ': ', a.valor) ORDER BY ta.nombre SEPARATOR ' / ') as nombre_variante
            ")
            ->groupBy('pva.variante_id');

        $query = $this->baseDetalles($user)
            ->leftJoin('categorias as cat',        'cat.id', '=', 'p.categoria_id')
            ->leftJoin('producto_variantes as pv', 'pv.id',  '=', 'vd.variante_id')
            ->leftJoinSub($nombreVariantes, 'nv',  fn($j) => $j->on('nv.variante_id', '=', 'pv.id'))
            ->when($request->filled('categoria_id'), fn($q) => $q->where('p.categoria_id', $request->categoria_id))
            ->when($request->filled('producto_id'),  fn($q) => $q->where('p.id', $request->producto_id))
            ->when($request->filled('producto'), function ($q) use ($request) {
                $txt = trim($request->producto);
                $q->where(fn($s) =>
                    $s->where('p.nombre', 'like', "%{$txt}%")
                      ->orWhere('p.codigo', 'like', "%{$txt}%")
                      ->orWhere('pv.sku',   'like', "%{$txt}%")
                );
            })
            ->selectRaw("
                p.id          as producto_id,
                vd.variante_id,
                p.codigo      as clave,
                p.nombre      as articulo,
                COALESCE(cat.nombre, 'Sin categoría')       as categoria,
                pv.sku                                       as sku_variante,
                COALESCE(nv.nombre_variante, 'Sin variante') as nombre_variante,
                vd.precio_venta,
                COALESCE(SUM(CASE WHEN v.estado = 'confirmada' THEN vd.cantidad ELSE 0 END), 0) as cantidad_vendida,
                COALESCE(SUM(CASE WHEN v.estado = 'confirmada' THEN vd.subtotal ELSE 0 END), 0) as importe_subtotal
            ")
            ->groupBy('p.id', 'vd.variante_id', 'p.codigo', 'p.nombre', 'cat.nombre', 'pv.sku', 'nv.nombre_variante', 'vd.precio_venta')
            ->orderBy('p.nombre')
            ->orderBy('pv.sku')
            ->orderBy('vd.precio_venta');

        return response()->json(
            $this->paginar($this->aplicarFiltros($request, $query), $request)
        );
    }

    // ═════════════════════════════════════════════════════════════════════
    // HELPERS PRIVADOS
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Validación unificada — cubre tanto reportes agrupados como artículos
     */
    private function validar(Request $request): void
    {
        $request->validate([
            'fecha_desde' => ['required', 'date'],
            'fecha_hasta' => ['required', 'date', 'after_or_equal:fecha_desde'],
            'forma_pago'  => ['nullable', 'in:efectivo,tarjeta,transferencia,credito'],
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
            'forma_pago'   => ['nullable', 'in:efectivo,tarjeta,transferencia,credito'],
            'estado'       => ['nullable', 'in:confirmada,cancelada'],
            'categoria_id' => ['nullable', 'integer'],
            'producto_id'  => ['nullable', 'integer'],
            'producto'     => ['nullable', 'string', 'max:120'],
            'por_pagina'   => ['nullable', 'integer', 'min:1', 'max:200'],
            'page'         => ['nullable', 'integer', 'min:1'],
        ]);
    }

    /**
     * Query base para reportes de ventas (sin detalles)
     */
    private function baseVentas($user)
    {
        return DB::table('ventas as v')
            ->where('v.empresa_id',  $user->empresa_id)
            ->where('v.sucursal_id', $user->sucursal_id);
    }

    /**
     * Query base para reportes que necesitan venta_detalles + productos
     */
    private function baseDetalles($user)
    {
        return DB::table('ventas as v')
            ->join('venta_detalles as vd', 'vd.venta_id', '=', 'v.id')
            ->join('productos as p',       'p.id',         '=', 'vd.producto_id')
            ->where('v.empresa_id',  $user->empresa_id)
            ->where('v.sucursal_id', $user->sucursal_id);
    }

    /**
     * Aplica filtros de fecha, forma_pago y estado a cualquier query
     */
    private function aplicarFiltros(Request $request, $query)
    {
        return $query
            ->whereDate('v.fecha', '>=', $request->fecha_desde)
            ->whereDate('v.fecha', '<=', $request->fecha_hasta)
            ->when($request->filled('forma_pago'), fn($q) => $q->where('v.forma_pago', $request->forma_pago))
            ->when($request->filled('estado'),     fn($q) => $q->where('v.estado',     $request->estado));
    }

    /**
     * Paginación directo en MySQL — sin traer todo a memoria
     */
    private function paginar($query, Request $request)
    {
        $porPagina = (int) ($request->por_pagina ?? 30);

        $resultado = $query->paginate($porPagina);

        // Agregar ticket_prom donde aplique
        $resultado->getCollection()->transform(function ($row) {
            $row = (array) $row;
            if (isset($row['confirmadas']) && (int) $row['confirmadas'] > 0) {
                $row['ticket_prom'] = round((float) $row['total'] / (int) $row['confirmadas'], 2);
            }
            return $row;
        });

        return $resultado;
    }

    /**
     * Columnas de agrupación para reportes de ventas (sin detalles)
     */
    private function colsVenta(): string
    {
        return "
            COUNT(DISTINCT v.id)                                                                          AS num_ventas,
            COALESCE(SUM(CASE WHEN v.estado = 'confirmada' THEN 1          ELSE 0 END), 0)               AS confirmadas,
            COALESCE(SUM(CASE WHEN v.estado = 'cancelada'  THEN 1          ELSE 0 END), 0)               AS canceladas,
            COALESCE(SUM(CASE WHEN v.estado = 'confirmada' THEN v.total    ELSE 0 END), 0)               AS total,
            COALESCE(SUM(CASE WHEN v.estado = 'confirmada' THEN v.descuento ELSE 0 END), 0)              AS descuentos,
            COALESCE(SUM(CASE WHEN v.forma_pago = 'efectivo'      AND v.estado = 'confirmada' THEN v.total ELSE 0 END), 0) AS efectivo,
            COALESCE(SUM(CASE WHEN v.forma_pago = 'tarjeta'       AND v.estado = 'confirmada' THEN v.total ELSE 0 END), 0) AS tarjeta,
            COALESCE(SUM(CASE WHEN v.forma_pago = 'transferencia' AND v.estado = 'confirmada' THEN v.total ELSE 0 END), 0) AS transferencia,
            COALESCE(SUM(CASE WHEN v.forma_pago = 'credito'       AND v.estado = 'confirmada' THEN v.total ELSE 0 END), 0) AS credito
        ";
    }

    /**
     * Columnas de agrupación para reportes con venta_detalles
     */
    private function colsDetalle(): string
    {
        return "
            COUNT(DISTINCT v.id)                                                                                          AS num_ventas,
            COALESCE(SUM(CASE WHEN v.estado = 'confirmada' THEN vd.cantidad ELSE 0 END), 0)                              AS unidades,
            COALESCE(SUM(CASE WHEN v.estado = 'confirmada' THEN vd.subtotal ELSE 0 END), 0)                              AS total,
            COALESCE(SUM(CASE WHEN v.estado = 'confirmada' THEN (vd.precio_venta - vd.precio_costo) * vd.cantidad ELSE 0 END), 0) AS margen,
            COALESCE(SUM(CASE WHEN v.forma_pago = 'efectivo'      AND v.estado = 'confirmada' THEN vd.subtotal ELSE 0 END), 0) AS efectivo,
            COALESCE(SUM(CASE WHEN v.forma_pago = 'tarjeta'       AND v.estado = 'confirmada' THEN vd.subtotal ELSE 0 END), 0) AS tarjeta,
            COALESCE(SUM(CASE WHEN v.forma_pago = 'transferencia' AND v.estado = 'confirmada' THEN vd.subtotal ELSE 0 END), 0) AS transferencia,
            COALESCE(SUM(CASE WHEN v.forma_pago = 'credito'       AND v.estado = 'confirmada' THEN vd.subtotal ELSE 0 END), 0) AS credito
        ";
    }
}