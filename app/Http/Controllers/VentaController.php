<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Models\Cliente;
use App\Models\User;
use App\Models\VentaDetalle;
use App\Models\Inventario;
use App\Models\ProductoVariante;
use App\Models\Producto;
use App\Services\FolioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\CorteCaja;
use App\Models\Serie;

class VentaController extends Controller
{
    // ── GET /api/ventas ─────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $empresaId = Auth::user()->empresa_id;

        $ventas = Venta::where('empresa_id', $empresaId)
            ->with(['user:id,name'])
            ->when(
                $request->buscar,
                fn($q, $b) =>
                $q->where(fn($q) => $q->where('folio', 'like', "%{$b}%"))
            )
            ->when($request->estado, fn($q, $e) => $q->where('estado', $e))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate($request->por_pagina ?? 20);

        return response()->json($ventas);
    }

    // ── GET /api/ventas/{id} ────────────────────────────────────────────────
    public function show(int $id): JsonResponse
    {
        $empresaId = Auth::user()->empresa_id;

        $venta = Venta::where('empresa_id', $empresaId)
            ->with([
                'user:id,name',
                'detalles.producto:id,nombre,codigo',
                'detalles.variante:id,sku,codigo_barras',
                'detalles.variante.atributos.tipoAtributo:id,nombre',
                'detalles.variante.atributos.atributo:id,valor',
            ])
            ->findOrFail($id);

        return response()->json($venta);
    }

    public function store(Request $request): JsonResponse
    {
        $user       = Auth::user();
        $empresaId  = (int) $user->empresa_id;
        $sucursalId = (int) $user->sucursal_id;

        $datos = $request->validate([
            'fecha'                    => ['required', 'date'],
            'cliente_id'               => ['nullable', 'exists:clientes,id'],
            'vendedor_id'              => ['required', 'exists:users,id'],
            'forma_pago'               => ['required', 'in:efectivo,credito,transferencia,tarjeta'],
            'descuento'                => ['nullable', 'numeric', 'min:0'],
            'notas'                    => ['nullable', 'string'],
            'monto_recibido'           => ['nullable', 'numeric', 'min:0'],
            'cambio'                   => ['nullable', 'numeric', 'min:0'],

            'detalles'                 => ['required', 'array', 'min:1'],
            'detalles.*.variante_id'   => ['nullable', 'exists:producto_variantes,id'],
            'detalles.*.producto_id'   => ['required', 'exists:productos,id'],
            'detalles.*.cantidad'      => ['required', 'integer', 'min:1'],
            'detalles.*.precio_venta'  => ['required', 'numeric', 'min:0'],
            'detalles.*.lista_precio_usada' => ['nullable', 'string', 'max:30'],
            'detalles.*.motivo_precio' => ['nullable', 'string', 'max:255'],
            'detalles.*.era_exhibido'  => ['nullable', 'boolean'],
            'detalles.*.serie_id'      => ['nullable', 'integer', 'exists:series,id'],
        ]);

        $corte = CorteCaja::where('empresa_id', $empresaId)
            ->where('sucursal_id', $sucursalId)
            ->where('user_id', $user->id)
            ->where('estado', 'abierto')
            ->latest('fecha_apertura')
            ->first();

        if (!$corte) {
            return response()->json([
                'message' => 'No hay caja abierta. Abre caja primero.'
            ], 422);
        }

        if (!empty($datos['cliente_id'])) {
            $clienteValido = Cliente::where('id', $datos['cliente_id'])
                ->where('empresa_id', $empresaId)
                ->exists();

            if (!$clienteValido) {
                return response()->json([
                    'message' => 'El cliente seleccionado no pertenece a esta empresa.',
                ], 422);
            }
        }

        $vendedorValido = User::where('id', $datos['vendedor_id'])
            ->where('empresa_id', $empresaId)
            ->exists();

        if (!$vendedorValido) {
            return response()->json([
                'message' => 'El vendedor seleccionado no pertenece a esta empresa.',
            ], 422);
        }

        $subtotalCalculado = collect($datos['detalles'])
            ->sum(fn($det) => ((float) $det['precio_venta']) * ((int) $det['cantidad']));

        $descuento = (float) ($datos['descuento'] ?? 0);
        $totalCalculado = max(0, $subtotalCalculado - $descuento);
        $montoRecibido = (float) ($datos['monto_recibido'] ?? 0);
        $cambio = (float) ($datos['cambio'] ?? 0);

        if ($datos['forma_pago'] === 'efectivo' && $montoRecibido < $totalCalculado) {
            return response()->json([
                'message' => 'El monto recibido no puede ser menor al total de la venta.',
                'campo'   => 'monto_recibido',
            ], 422);
        }

        $productoIds = collect($datos['detalles'])->pluck('producto_id')->unique()->values();
        $varianteIds = collect($datos['detalles'])->pluck('variante_id')->filter()->unique()->values();

        $productos = Producto::whereIn('id', $productoIds)
            ->select('id', 'nombre', 'precio_costo', 'precio_venta', 'precio1', 'precio2', 'precio3', 'precio4', 'precio5')
            ->get()
            ->keyBy('id');

        $variantes = ProductoVariante::whereIn('id', $varianteIds)
            ->with(['atributos.tipoAtributo:id,nombre', 'atributos.atributo:id,valor'])
            ->select('id', 'producto_id', 'sku', 'precio_costo', 'precio_venta', 'precio1', 'precio2', 'precio3', 'precio4', 'precio5')
            ->get()
            ->keyBy('id');

        DB::beginTransaction();

        try {
            $folio = FolioService::siguienteTicket($empresaId, $sucursalId, 'TKT');

            $venta = Venta::create([
                'empresa_id'      => $empresaId,
                'sucursal_id'     => $sucursalId,
                'user_id'         => $user->id,
                'cliente_id'      => $datos['cliente_id'] ?? null,
                'vendedor_id'     => $datos['vendedor_id'],
                'corte_id'        => $corte->id,
                'folio'           => $folio,
                'fecha'           => $datos['fecha'],
                'forma_pago'      => $datos['forma_pago'],
                'descuento'       => $descuento,
                'notas'           => $datos['notas'] ?? null,
                'monto_recibido'  => $datos['forma_pago'] === 'efectivo' ? $montoRecibido : null,
                'cambio'          => $datos['forma_pago'] === 'efectivo' ? $cambio : 0,
                'estado'          => 'confirmada',
                'subtotal'        => 0,
                'total'           => 0,
            ]);

            foreach ($datos['detalles'] as $det) {
                $productoId  = (int) $det['producto_id'];
                $varianteId  = $det['variante_id'] ? (int) $det['variante_id'] : null;
                $cantidad    = (int) $det['cantidad'];
                $precioVenta = (float) $det['precio_venta'];

                $inv = Inventario::where([
                    'empresa_id'  => $empresaId,
                    'sucursal_id' => $sucursalId,
                    'producto_id' => $productoId,
                    'variante_id' => $varianteId,
                ])->lockForUpdate()->first();

                $stockActual = (float) ($inv?->stock ?? 0);

                if ($stockActual < $cantidad) {
                    $nombre = $varianteId
                        ? ($variantes[$varianteId]?->sku ?? "variante #{$varianteId}")
                        : ($productos[$productoId]?->nombre ?? "producto #{$productoId}");

                    DB::rollBack();
                    return response()->json([
                        'message' => "Stock insuficiente para «{$nombre}». Disponible: {$stockActual}, solicitado: {$cantidad}.",
                        'campo'   => 'stock',
                    ], 422);
                }

                $serieId  = isset($det['serie_id']) ? (int) $det['serie_id'] : null;
                $serieObj = null;

                if ($serieId) {
                    $serieObj = Serie::where('id', $serieId)
                        ->where('empresa_id', $empresaId)
                        ->where('estado', 'disponible')
                        ->lockForUpdate()
                        ->first();

                    if (!$serieObj) {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'La serie/IMEI seleccionada ya no está disponible.',
                            'campo'   => 'serie',
                        ], 422);
                    }
                }

                $precioCosto = 0;

                if ($varianteId) {
                    $precioCosto = (float) (
                        $variantes[$varianteId]?->precio_costo
                        ?? $productos[$productoId]?->precio_costo
                        ?? 0
                    );
                } else {
                    $precioCosto = (float) ($productos[$productoId]?->precio_costo ?? 0);
                }

                if ($serieObj?->precio_costo) {
                    $precioCosto = (float) $serieObj->precio_costo;
                }

                $precioOriginal = $varianteId
                    ? (float) (
                        $variantes[$varianteId]?->precio_venta
                        ?? $productos[$productoId]?->precio_venta
                        ?? $precioVenta
                    )
                    : (float) ($productos[$productoId]?->precio_venta ?? $precioVenta);

                $descuentoLinea = round(max(0, $precioOriginal - $precioVenta) * $cantidad, 2);
                $subtotalLinea = $cantidad * $precioVenta;
                $productoNombre = $productos[$productoId]?->nombre;
                $varianteNombre = $varianteId
                    ? ($variantes[$varianteId]?->nombreVariante() ?: null)
                    : null;
                $listaPrecioUsada = $det['lista_precio_usada']
                    ?? $this->resolverListaPrecioUsada(
                        $precioVenta,
                        $varianteId ? $variantes[$varianteId] ?? null : null,
                        $productos[$productoId] ?? null
                    );

                $detalle = VentaDetalle::create([
                    'venta_id'      => $venta->id,
                    'producto_id'   => $productoId,
                    'producto_nombre' => $productoNombre,
                    'variante_id'   => $varianteId,
                    'variante_nombre' => $varianteNombre,
                    'serie_id'      => $serieId,
                    'cantidad'      => $cantidad,
                    'precio_venta'  => $precioVenta,
                    'precio_costo'  => $precioCosto,
                    'precio_lista_original' => $precioOriginal,
                    'precio_aplicado' => $precioVenta,
                    'lista_precio_usada' => $listaPrecioUsada,
                    'descuento'     => $descuentoLinea,
                    'subtotal'      => $subtotalLinea,
                    'motivo_precio' => $det['motivo_precio'] ?? null,
                ]);

                $inv->descontarVenta(
                    $cantidad,
                    (bool) ($det['era_exhibido'] ?? false)
                );

                if ($serieObj) {
                    $serieObj->marcarVendido($venta->id, $detalle->id);
                }
            }

            $venta->recalcularTotales();

            DB::commit();

            return response()->json(
                $venta->load([
                    'detalles.producto',
                    'detalles.variante',
                    'detalles.variante.atributos.tipoAtributo:id,nombre',
                    'detalles.variante.atributos.atributo:id,valor',
                    'detalles.serie',
                    'empresa:id,nombre,rfc,direccion,telefono',
                    'sucursal:id,nombre,direccion,telefono',
                    'cliente:id,nombre,telefono',
                    'vendedor:id,name',
                    'user:id,name',
                ]),
                201
            );
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error interno: ' . $e->getMessage()
            ], 500);
        }
    }

    // ── DELETE /api/ventas/{id} — solo cancela, no borra ───────────────────
    public function destroy(int $id): JsonResponse
    {
        $user      = Auth::user();
        $empresaId = (int) $user->empresa_id;

        $venta = Venta::where('empresa_id', $empresaId)
            ->with('detalles')
            ->findOrFail($id);

        if ($venta->estado === 'cancelada') {
            return response()->json(['message' => 'La venta ya está cancelada.'], 422);
        }

        DB::beginTransaction();
        try {
            foreach ($venta->detalles as $det) {
                $productoId = (int) $det->producto_id;
                $varianteId = $det->variante_id ? (int) $det->variante_id : null;
                $cantidad   = (float) $det->cantidad;

                $inv = Inventario::where([
                    'empresa_id'  => $empresaId,
                    'sucursal_id' => (int) $venta->sucursal_id,
                    'producto_id' => $productoId,
                    'variante_id' => $varianteId,
                ])
                    ->lockForUpdate()
                    ->first();

                if (!$inv) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "Inventario no encontrado para revertir stock (producto {$productoId}, variante " . ($varianteId ?? 'NULL') . ").",
                    ], 422);
                }

                $inv->stock = (float) $inv->stock + $cantidad;
                $inv->save();
            }

            $venta->update(['estado' => 'cancelada']);
            DB::commit();

            return response()->json(['mensaje' => 'Venta cancelada y stock revertido.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // ── GET /api/ventas/buscar-variantes ────────────────────────────────────────
    // CAMBIOS:
    //   1. Se agrega precio_1..precio_5 al select del producto (with)
    //   2. Se agrega precio_1..precio_5 al select de la variante
    //   3. En el map se resuelve cada precio con herencia: variante ?? producto
    // ────────────────────────────────────────────────────────────────────────────


    private function resolverListaPrecioUsada(float $precioAplicado, ?ProductoVariante $variante, ?Producto $producto): ?string
    {
        $precios = [
            'P.Venta' => $variante?->precio_venta ?? $producto?->precio_venta,
            'P1' => $variante?->precio1 ?? $producto?->precio1,
            'P2' => $variante?->precio2 ?? $producto?->precio2,
            'P3' => $variante?->precio3 ?? $producto?->precio3,
            'P4' => $variante?->precio4 ?? $producto?->precio4,
            'P5' => $variante?->precio5 ?? $producto?->precio5,
        ];

        foreach ($precios as $nombre => $precio) {
            if ($precio !== null && abs((float) $precio - $precioAplicado) < 0.01) {
                return $nombre;
            }
        }

        return null;
    }

    public function buscarVariantes(Request $request): JsonResponse
    {
        $user       = Auth::user();
        $empresaId  = (int) $user->empresa_id;
        $sucursalId = (int) $user->sucursal_id;
        $q          = trim($request->q ?? '');

        if (strlen($q) < 1) return response()->json([]);

        $resultados = collect();

        // ── Helper: precios con herencia variante > producto ─────────────────
        $resolverPrecio = fn($varVal, $prodVal) => ($varVal && (float)$varVal > 0) ? (float)$varVal : (($prodVal && (float)$prodVal > 0) ? (float)$prodVal : null);

        // ── Helper: stock de inventario ───────────────────────────────────────
        $getStock = fn($empresaId, $sucursalId, $productoId, $varianteId) =>
        Inventario::where([
            'empresa_id'  => $empresaId,
            'sucursal_id' => $sucursalId,
            'producto_id' => $productoId,
            'variante_id' => $varianteId,
        ])->first();

        // ══════════════════════════════════════════════════════════════════════
        // CASO 1 — Búsqueda por IMEI (prioridad máxima)
        // Si el query coincide exactamente con un IMEI disponible, devolver ese
        // item directamente con serie_id para que el POS lo agregue sin más pasos
        // ══════════════════════════════════════════════════════════════════════
        $serie = Serie::where('empresa_id', $empresaId)
            ->where('imei', $q)
            ->where('estado', 'disponible')
            ->with('producto:id,nombre,codigo,precio_costo,precio_venta,precio1,precio2,precio3,precio4,precio5,imagen,tiene_variantes,tiene_series')
            ->first();

        if ($serie) {
            $inv   = $getStock($empresaId, $sucursalId, $serie->producto_id, $serie->variante_id);
            $stock = (float) ($inv?->stock ?? 0);

            // Precios: serie > variante > producto
            $precioVenta = ($serie->precio_venta && (float)$serie->precio_venta > 0)
                ? (float)$serie->precio_venta
                : (float)($serie->producto->precio_venta ?? 0);

            $resultados->push([
                'id'              => $serie->variante_id, // null si sin variante
                'producto_id'     => $serie->producto_id,
                'nombre'          => $serie->producto->nombre,
                'codigo'          => $serie->producto->codigo,
                'sku'             => null,
                'codigo_barras'   => $serie->imei,
                'nombre_variante' => null,
                'imagen_url'      => $serie->producto->imagen_url,
                'stock'           => $stock,
                'sin_stock'       => $stock <= 0,
                'exhibido'        => false,
                'tiene_series'    => true,
                'serie_id'        => $serie->id,   // ← para venta directa por IMEI
                'imei'            => $serie->imei,
                'precio_venta'    => $precioVenta,
                'precio_costo'    => (float)($serie->precio_costo ?? $serie->producto->precio_costo ?? 0),
                'precio1'         => null,
                'precio2'         => null,
                'precio3'         => null,
                'precio4'         => null,
                'precio5'         => null,
            ]);

            // Si es IMEI exacto, devolver solo ese resultado
            return response()->json($resultados->values());
        }

        // ══════════════════════════════════════════════════════════════════════
        // CASO 2 — Productos SIN variantes
        // ══════════════════════════════════════════════════════════════════════
        $productos = Producto::where('empresa_id', $empresaId)
            ->where('activo', true)
            ->where('tiene_variantes', false)
            ->where(
                fn($pq) => $pq
                    ->where('nombre', 'like', "%{$q}%")
                    ->orWhere('codigo', 'like', "%{$q}%")
            )
            ->select(
                'id',
                'nombre',
                'codigo',
                'precio_costo',
                'precio_venta',
                'precio1',
                'precio2',
                'precio3',
                'precio4',
                'precio5',
                'imagen',
                'tiene_series'
            )
            ->limit(10)
            ->get()
            ->map(function ($p) use ($empresaId, $sucursalId, $getStock) {
                $inv      = $getStock($empresaId, $sucursalId, $p->id, null);
                $stock    = (float) ($inv?->stock ?? 0);
                $exhibido = (bool)  ($inv?->exhibido ?? false);

                return [
                    'id'              => null,        // sin variante_id
                    'producto_id'     => $p->id,
                    'nombre'          => $p->nombre,
                    'codigo'          => $p->codigo,
                    'sku'             => null,
                    'codigo_barras'   => null,
                    'nombre_variante' => null,
                    'imagen_url'      => $p->imagen_url,
                    'stock'           => $stock,
                    'sin_stock'       => $stock <= 0,
                    'exhibido'        => $exhibido,
                    'tiene_series'    => (bool) $p->tiene_series,
                    'serie_id'        => null,
                    'precio_venta'    => (float) ($p->precio_venta ?? 0),
                    'precio_costo'    => (float) ($p->precio_costo ?? 0),
                    'precio1'         => $p->precio1 ? (float)$p->precio1 : null,
                    'precio2'         => $p->precio2 ? (float)$p->precio2 : null,
                    'precio3'         => $p->precio3 ? (float)$p->precio3 : null,
                    'precio4'         => $p->precio4 ? (float)$p->precio4 : null,
                    'precio5'         => $p->precio5 ? (float)$p->precio5 : null,
                ];
            });

        $resultados = $resultados->merge($productos);

        // ══════════════════════════════════════════════════════════════════════
        // CASO 3 — Variantes de productos CON variantes
        // ══════════════════════════════════════════════════════════════════════
        $variantes = ProductoVariante::whereHas(
            'producto',
            fn($pq) =>
            $pq->where('empresa_id', $empresaId)
                ->where('activo', true)
                ->where('tiene_variantes', true)
        )
            ->with([
                'producto:id,nombre,codigo,precio_costo,precio_venta,imagen,precio1,precio2,precio3,precio4,precio5,tiene_series',
                'atributos.tipoAtributo:id,nombre',
                'atributos.atributo:id,valor',
            ])
            ->select([
                'id',
                'producto_id',
                'sku',
                'codigo_barras',
                'precio_costo',
                'precio_venta',
                'precio1',
                'precio2',
                'precio3',
                'precio4',
                'precio5'
            ])
            ->where(
                fn($q2) => $q2
                    ->where('sku', 'like', "%{$q}%")
                    ->orWhere('codigo_barras', 'like', "%{$q}%")
                    ->orWhereHas(
                        'producto',
                        fn($pq) => $pq
                            ->where('nombre', 'like', "%{$q}%")
                            ->orWhere('codigo', 'like', "%{$q}%")
                    )
            )
            ->limit(15)
            ->get()
            ->map(function ($v) use ($empresaId, $sucursalId, $getStock, $resolverPrecio) {
                $inv      = $getStock($empresaId, $sucursalId, $v->producto_id, $v->id);
                $stock    = (float) ($inv?->stock ?? 0);
                $exhibido = (bool)  ($inv?->exhibido ?? false);

                return [
                    'id'              => $v->id,
                    'producto_id'     => $v->producto_id,
                    'nombre'          => $v->producto->nombre,
                    'codigo'          => $v->producto->codigo,
                    'sku'             => $v->sku,
                    'codigo_barras'   => $v->codigo_barras,
                    'nombre_variante' => $v->nombreVariante(),
                    'imagen_url'      => $v->imagen_url,
                    'stock'           => $stock,
                    'sin_stock'       => $stock <= 0,
                    'exhibido'        => $exhibido,
                    'tiene_series'    => (bool) $v->producto->tiene_series,
                    'serie_id'        => null,
                    'precio_venta'    => $resolverPrecio($v->precio_venta,  $v->producto->precio_venta)  ?? 0,
                    'precio_costo'    => $resolverPrecio($v->precio_costo,  $v->producto->precio_costo)  ?? 0,
                    'precio1'         => $resolverPrecio($v->precio1, $v->producto->precio1),
                    'precio2'         => $resolverPrecio($v->precio2, $v->producto->precio2),
                    'precio3'         => $resolverPrecio($v->precio3, $v->producto->precio3),
                    'precio4'         => $resolverPrecio($v->precio4, $v->producto->precio4),
                    'precio5'         => $resolverPrecio($v->precio5, $v->producto->precio5),
                ];
            });

        $resultados = $resultados->merge($variantes)->values();

        return response()->json($resultados);
    }
}
