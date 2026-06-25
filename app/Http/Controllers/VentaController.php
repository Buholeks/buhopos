<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Models\Cliente;
use App\Models\ClienteSaldoMovimiento;
use App\Models\User;
use App\Models\VentaDetalle;
use App\Models\Inventario;
use App\Models\InventarioReserva;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\ProductoVariante;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Services\FolioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\CorteCaja;
use App\Models\Serie;
use App\Support\ProductVariantSearch;
use App\Support\TerminalResolver;

class VentaController extends Controller
{
    // ── GET /api/ventas ─────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('ventas.ver'), 403, 'Sin permiso: ventas.ver');
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
        abort_unless(Auth::user()->tienePermiso('ventas.ver'), 403, 'Sin permiso: ventas.ver');
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
        abort_unless(Auth::user()->tienePermiso('ventas.crear'), 403, 'Sin permiso: ventas.crear');
        $user       = Auth::user();
        $empresaId  = (int) $user->empresa_id;
        $sucursalId = (int) $user->sucursal_id;
        $terminal = TerminalResolver::fromRequest($request);

        $datos = $request->validate([
            'fecha'                    => ['required', 'date'],
            'cliente_id'               => ['nullable', 'exists:clientes,id'],
            'vendedor_id'              => ['required', 'exists:users,id'],
            'forma_pago'               => ['required', 'in:efectivo,credito,transferencia,tarjeta'],
            'descuento'                => ['nullable', 'numeric', 'min:0'],
            'saldo_aplicado'           => ['nullable', 'numeric', 'min:0'],
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
            'detalles.*.pedido_id'     => ['nullable', 'integer', 'exists:pedidos,id'],
            'detalles.*.pedido_detalle_id' => ['nullable', 'integer', 'exists:pedido_detalles,id'],
        ]);

        $corte = CorteCaja::where('empresa_id', $empresaId)
            ->where('sucursal_id', $sucursalId)
            ->where('terminal', $terminal)
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

        $pedidoDetalleIds = collect($datos['detalles'])
            ->pluck('pedido_detalle_id')
            ->filter()
            ->map(fn($id) => (int) $id);

        if ($pedidoDetalleIds->count() !== $pedidoDetalleIds->unique()->count()) {
            return response()->json([
                'message' => 'Un mismo renglón de pedido no puede agregarse dos veces a la venta.',
                'campo' => 'pedido_detalle_id',
            ], 422);
        }

        $pedidoDetalles = PedidoDetalle::whereIn('id', $pedidoDetalleIds->unique())
            ->whereIn('estado', ['disponible', 'reservado'])
            ->whereHas('pedido', fn($query) => $query
                ->where('empresa_id', $empresaId)
                ->where('sucursal_id', $sucursalId))
            ->with(['pedido:id,cliente_id', 'compraDetalle:id,precio_compra'])
            ->get()
            ->keyBy('id');

        foreach ($datos['detalles'] as $i => &$det) {
            if (empty($det['pedido_detalle_id'])) {
                continue;
            }

            $pedidoDetalle = $pedidoDetalles->get((int) $det['pedido_detalle_id']);
            $coincide = $pedidoDetalle
                && (int) $pedidoDetalle->producto_id === (int) $det['producto_id']
                && (int) ($pedidoDetalle->variante_id ?? 0) === (int) ($det['variante_id'] ?? 0)
                && abs((float) $pedidoDetalle->cantidad - (float) $det['cantidad']) < 0.0001
                && (int) $pedidoDetalle->pedido?->cliente_id === (int) ($datos['cliente_id'] ?? 0);

            if (! $coincide) {
                return response()->json([
                    'message' => 'Uno de los renglones de pedido ya no está disponible o no corresponde al cliente/producto seleccionado.',
                    'campo' => "detalles.{$i}.pedido_detalle_id",
                ], 422);
            }

            $det['pedido_id'] = $pedidoDetalle->pedido_id;
            $det['precio_venta'] = (float) $pedidoDetalle->precio_acordado;
        }
        unset($det);

        $subtotalCalculado = collect($datos['detalles'])
            ->sum(fn($det) => ((float) $det['precio_venta']) * ((int) $det['cantidad']));

        $descuento = (float) ($datos['descuento'] ?? 0);
        $totalCalculado = max(0, $subtotalCalculado - $descuento);
        $saldoAplicado = round((float) ($datos['saldo_aplicado'] ?? 0), 2);
        $montoRecibido = (float) ($datos['monto_recibido'] ?? 0);
        $cambio = (float) ($datos['cambio'] ?? 0);
        $totalACobrar = max(0, $totalCalculado - $saldoAplicado);

        if ($saldoAplicado > 0 && empty($datos['cliente_id'])) {
            return response()->json([
                'message' => 'Selecciona un cliente para aplicar saldo a favor.',
                'campo' => 'cliente_id',
            ], 422);
        }

        if ($saldoAplicado > $totalCalculado) {
            return response()->json([
                'message' => 'El saldo aplicado no puede ser mayor al total de la venta.',
                'campo' => 'saldo_aplicado',
            ], 422);
        }

        if ($saldoAplicado > 0) {
            $saldoDisponible = $this->saldoDisponibleCliente($empresaId, $sucursalId, (int) $datos['cliente_id']);

            if ($saldoAplicado > $saldoDisponible) {
                return response()->json([
                    'message' => "Saldo a favor insuficiente. Disponible: {$saldoDisponible}.",
                    'campo' => 'saldo_aplicado',
                ], 422);
            }

            if ($this->clienteTieneProductosPendientes($empresaId, $sucursalId, (int) $datos['cliente_id'])) {
                $detalleIds = collect($datos['detalles'])
                    ->pluck('pedido_detalle_id')
                    ->filter()
                    ->unique()
                    ->values();

                $detallesValidos = PedidoDetalle::whereIn('id', $detalleIds)
                    ->whereNotIn('estado', ['entregado', 'cancelado'])
                    ->whereHas('pedido', fn($query) => $query
                        ->where('empresa_id', $empresaId)
                        ->where('sucursal_id', $sucursalId)
                        ->where('cliente_id', $datos['cliente_id'])
                        ->whereNotIn('estado', ['entregado', 'cancelado']))
                    ->count();

                if ($detalleIds->count() !== count($datos['detalles']) || $detallesValidos !== $detalleIds->count()) {
                    return response()->json([
                        'message' => 'El cliente tiene productos pendientes. Su saldo a favor solo puede usarse para liquidar esos pedidos o apartados.',
                        'campo' => 'saldo_aplicado',
                    ], 422);
                }
            }
        }

        if ($datos['forma_pago'] === 'efectivo' && $montoRecibido < $totalACobrar) {
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
                'saldo_aplicado'  => $saldoAplicado,
                'notas'           => $datos['notas'] ?? null,
                'monto_recibido'  => $datos['forma_pago'] === 'efectivo' ? $montoRecibido : null,
                'cambio'          => $datos['forma_pago'] === 'efectivo' ? $cambio : 0,
                'estado'          => 'confirmada',
                'subtotal'        => 0,
                'total'           => 0,
            ]);

            foreach ($datos['detalles'] as $det) {
                $productoId  = (int) $det['producto_id'];
                $varianteId  = !empty($det['variante_id']) ? (int) $det['variante_id'] : null;
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

                if (!empty($det['pedido_detalle_id'])) {
                    $precioCosto = (float) (
                        $pedidoDetalles->get((int) $det['pedido_detalle_id'])?->compraDetalle?->precio_compra
                        ?? $precioCosto
                    );
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
                    'pedido_id'     => $det['pedido_id'] ?? null,
                    'pedido_detalle_id' => $det['pedido_detalle_id'] ?? null,
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

                if (!empty($det['pedido_detalle_id'])) {
                    $this->entregarPedidoDetalle(
                        (int) $det['pedido_detalle_id'],
                        $venta,
                        $productoId,
                        $varianteId,
                        $cantidad
                    );
                }
            }

            $venta->recalcularTotales();

            if ($saldoAplicado > 0) {
                $this->registrarAplicacionSaldo(
                    $venta,
                    $corte,
                    (int) $datos['cliente_id'],
                    $saldoAplicado
                );
            }

            $corte->recalcularVentas();

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
        abort_unless(Auth::user()->tienePermiso('ventas.cancelar'), 403, 'Sin permiso: ventas.cancelar');
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

                if ($det->pedido_detalle_id) {
                    $this->revertirPedidoDetalleVenta($det);
                }
            }

            if ((float) ($venta->saldo_aplicado ?? 0) > 0 && $venta->cliente_id) {
                $this->devolverSaldoPorCancelacion($venta);
            }

            $venta->update(['estado' => 'cancelada']);
            $venta->corte?->recalcularVentas();
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

    private function saldoDisponibleCliente(int $empresaId, int $sucursalId, int $clienteId): float
    {
        return round((float) ClienteSaldoMovimiento::where('empresa_id', $empresaId)
            ->where('sucursal_id', $sucursalId)
            ->where('cliente_id', $clienteId)
            ->sum(DB::raw("CASE WHEN tipo IN ('abono','ajuste') THEN monto ELSE -monto END")), 2);
    }

    private function clienteTieneProductosPendientes(int $empresaId, int $sucursalId, int $clienteId): bool
    {
        return PedidoDetalle::whereHas('pedido', fn($query) => $query
            ->where('empresa_id', $empresaId)
            ->where('sucursal_id', $sucursalId)
            ->where('cliente_id', $clienteId)
            ->whereNotIn('estado', ['entregado', 'cancelado']))
            ->whereNotIn('estado', ['entregado', 'cancelado'])
            ->exists();
    }

    private function registrarAplicacionSaldo(Venta $venta, CorteCaja $corte, int $clienteId, float $monto): void
    {
        $saldoAnterior = $this->saldoDisponibleCliente(
            (int) $venta->empresa_id,
            (int) $venta->sucursal_id,
            $clienteId
        );

        ClienteSaldoMovimiento::create([
            'empresa_id' => $venta->empresa_id,
            'sucursal_id' => $venta->sucursal_id,
            'cliente_id' => $clienteId,
            'venta_id' => $venta->id,
            'corte_id' => $corte->id,
            'user_id' => Auth::id(),
            'tipo' => 'aplicacion',
            'forma_pago' => 'saldo_favor',
            'monto' => $monto,
            'saldo_resultante' => max(0, $saldoAnterior - $monto),
            'concepto' => "Aplicacion de saldo en venta {$venta->folio}",
        ]);
    }

    private function devolverSaldoPorCancelacion(Venta $venta): void
    {
        $monto = (float) ($venta->saldo_aplicado ?? 0);
        if ($monto <= 0 || ! $venta->cliente_id) {
            return;
        }

        $saldoAnterior = $this->saldoDisponibleCliente(
            (int) $venta->empresa_id,
            (int) $venta->sucursal_id,
            (int) $venta->cliente_id
        );

        ClienteSaldoMovimiento::create([
            'empresa_id' => $venta->empresa_id,
            'sucursal_id' => $venta->sucursal_id,
            'cliente_id' => $venta->cliente_id,
            'venta_id' => $venta->id,
            'corte_id' => $venta->corte_id,
            'user_id' => Auth::id(),
            'tipo' => 'ajuste',
            'forma_pago' => 'saldo_favor',
            'monto' => $monto,
            'saldo_resultante' => $saldoAnterior + $monto,
            'concepto' => "Devolucion de saldo por cancelacion {$venta->folio}",
        ]);
    }

    private function entregarPedidoDetalle(
        int $pedidoDetalleId,
        Venta $venta,
        int $productoId,
        ?int $varianteId,
        int $cantidad
    ): void {
        $detalle = PedidoDetalle::where('id', $pedidoDetalleId)
            ->where('producto_id', $productoId)
            ->where('variante_id', $varianteId)
            ->whereHas('pedido', fn($q) => $q
                ->where('empresa_id', $venta->empresa_id)
                ->where('sucursal_id', $venta->sucursal_id)
                ->where('cliente_id', $venta->cliente_id))
            ->with('pedido')
            ->lockForUpdate()
            ->firstOrFail();

        if ((float) $detalle->cantidad !== (float) $cantidad) {
            throw new \RuntimeException('La cantidad vendida no coincide con la cantidad del pedido.');
        }

        InventarioReserva::where('pedido_detalle_id', $detalle->id)
            ->where('estado', 'activa')
            ->update(['estado' => 'consumida']);

        $detalle->update(['estado' => 'entregado']);

        $pedido = $detalle->pedido;
        $pedido->update(['venta_id' => $venta->id]);

        $pendientes = $pedido->detalles()
            ->whereNotIn('estado', ['entregado', 'cancelado'])
            ->exists();

        if ($pendientes) {
            $pedido->update(['estado' => 'parcial']);
            return;
        }

        $pedido->update([
            'estado' => 'entregado',
            'estado_pago' => 'pagado',
            'saldo_pendiente' => 0,
        ]);
    }

    private function revertirPedidoDetalleVenta(VentaDetalle $ventaDetalle): void
    {
        $detalle = PedidoDetalle::where('id', $ventaDetalle->pedido_detalle_id)
            ->with('pedido')
            ->lockForUpdate()
            ->first();

        if (! $detalle) {
            return;
        }

        $detalle->update(['estado' => 'disponible']);

        InventarioReserva::updateOrCreate(
            [
                'pedido_detalle_id' => $detalle->id,
                'estado' => 'activa',
            ],
            [
                'empresa_id' => $detalle->pedido->empresa_id,
                'sucursal_id' => $detalle->pedido->sucursal_id,
                'pedido_id' => $detalle->pedido_id,
                'producto_id' => $detalle->producto_id,
                'variante_id' => $detalle->variante_id,
                'cantidad' => $detalle->cantidad,
            ]
        );

        $detalle->pedido->update([
            'estado' => 'disponible',
            'estado_pago' => $this->estadoPagoPedido(
                (float) $detalle->pedido->subtotal,
                (float) $detalle->pedido->anticipo
            ),
            'saldo_pendiente' => max(0, (float) $detalle->pedido->subtotal - (float) $detalle->pedido->anticipo),
        ]);
    }

    private function estadoPagoPedido(float $subtotal, float $anticipo): string
    {
        if ($anticipo <= 0) return 'sin_anticipo';
        if ($anticipo >= $subtotal && $subtotal > 0) return 'pagado';
        return 'con_anticipo';
    }

    public function buscarVariantes(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('ventas.crear'), 403, 'Sin permiso: ventas.crear');
        $user       = Auth::user();
        $empresaId  = (int) $user->empresa_id;
        $sucursalId = (int) $user->sucursal_id;
        $q          = trim($request->q ?? '');
        $pedidoDetalleId = $request->integer('pedido_detalle_id') ?: null;

        if (strlen($q) < 1) return response()->json([]);
        $tokens = ProductVariantSearch::tokens($q);

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

        // ── Helper: stock comprometido — construye un bulk lookup con 2 queries ─
        // Se invoca una vez por caso con todos los (producto_id, variante_id) del
        // resultado, evitando 2×N queries individuales dentro del map().
        $buildStockComprometido = function (array $pares) use ($empresaId, $sucursalId, $pedidoDetalleId): \Closure {
            // $pares = [['producto_id' => X, 'variante_id' => Y|null], ...]
            $productoIds = array_unique(array_column($pares, 'producto_id'));

            $reservas = InventarioReserva::where('empresa_id', $empresaId)
                ->where('sucursal_id', $sucursalId)
                ->whereIn('producto_id', $productoIds)
                ->where('estado', 'activa')
                ->when($pedidoDetalleId, fn($q) => $q->where('pedido_detalle_id', '!=', $pedidoDetalleId))
                ->selectRaw('producto_id, variante_id, SUM(cantidad) as total')
                ->groupBy('producto_id', 'variante_id')
                ->get()
                ->keyBy(fn($r) => $r->producto_id . '-' . ($r->variante_id ?? 'null'));

            $pedidoDisponibles = PedidoDetalle::whereIn('producto_id', $productoIds)
                ->where('estado', 'disponible')
                ->when($pedidoDetalleId, fn($q) => $q->where('id', '!=', $pedidoDetalleId))
                ->whereDoesntHave('reservas', fn($q) => $q->where('estado', 'activa'))
                ->whereHas('pedido', fn($q) => $q
                    ->where('empresa_id', $empresaId)
                    ->where('sucursal_id', $sucursalId)
                    ->whereNotIn('estado', ['entregado', 'cancelado']))
                ->selectRaw('producto_id, variante_id, SUM(cantidad) as total')
                ->groupBy('producto_id', 'variante_id')
                ->get()
                ->keyBy(fn($r) => $r->producto_id . '-' . ($r->variante_id ?? 'null'));

            return function (int $productoId, ?int $varianteId) use ($reservas, $pedidoDisponibles): float {
                $key = $productoId . '-' . ($varianteId ?? 'null');
                return (float) ($reservas[$key]->total ?? 0)
                    + (float) ($pedidoDisponibles[$key]->total ?? 0);
            };
        };

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
            $stockComprometido = $buildStockComprometido([
                ['producto_id' => $serie->producto_id, 'variante_id' => $serie->variante_id],
            ]);
            $inv   = $getStock($empresaId, $sucursalId, $serie->producto_id, $serie->variante_id);
            $stock = max(0, (float) ($inv?->stock ?? 0) - $stockComprometido($serie->producto_id, $serie->variante_id));

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

        if ($tokens === []) {
            return response()->json([]);
        }

        // ══════════════════════════════════════════════════════════════════════
        // CASO 2 — Productos SIN variantes
        // ══════════════════════════════════════════════════════════════════════
        $productos = Producto::where('empresa_id', $empresaId)
            ->where('activo', true)
            ->where('tiene_variantes', false)
            ->tap(fn($query) => ProductVariantSearch::applyProductoTokens($query, $tokens))
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
            ->limit(40)
            ->get()
            ->filter(fn($p) => ProductVariantSearch::matches($tokens, ProductVariantSearch::productoText($p)))
            ->sortByDesc(fn($p) => ProductVariantSearch::score($tokens, $q, ['codigo' => $p->codigo], ProductVariantSearch::productoText($p)))
            ->take(10)
            ->values();

        // ── Bulk stock comprometido para productos sin variante ───────────────
        $paresProductos = $productos->map(fn($p) => ['producto_id' => $p->id, 'variante_id' => null])->all();
        $stockComprometidoP = count($paresProductos) > 0
            ? $buildStockComprometido($paresProductos)
            : fn($p, $v) => 0.0;

        $productos = $productos->map(function ($p) use ($empresaId, $sucursalId, $getStock, $stockComprometidoP) {
                $inv      = $getStock($empresaId, $sucursalId, $p->id, null);
                $stock    = max(0, (float) ($inv?->stock ?? 0) - $stockComprometidoP($p->id, null));
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
                fn($q2) => ProductVariantSearch::applyVarianteTokens($q2, $tokens)
            )
            ->limit(80)
            ->get()
            ->filter(fn($v) => ProductVariantSearch::matches($tokens, ProductVariantSearch::varianteText($v)))
            ->sortByDesc(fn($v) => ProductVariantSearch::score($tokens, $q, [
                'codigo' => $v->producto->codigo,
                'sku' => $v->sku,
                'codigo_barras' => $v->codigo_barras,
            ], ProductVariantSearch::varianteText($v)))
            ->take(15)
            ->values();

        // ── Bulk stock comprometido para variantes ────────────────────────────
        $paresVariantes = $variantes->map(fn($v) => ['producto_id' => $v->producto_id, 'variante_id' => $v->id])->all();
        $stockComprometidoV = count($paresVariantes) > 0
            ? $buildStockComprometido($paresVariantes)
            : fn($p, $v) => 0.0;

        $variantes = $variantes->map(function ($v) use ($empresaId, $sucursalId, $getStock, $resolverPrecio, $stockComprometidoV) {
                $inv      = $getStock($empresaId, $sucursalId, $v->producto_id, $v->id);
                $stock    = max(0, (float) ($inv?->stock ?? 0) - $stockComprometidoV($v->producto_id, $v->id));
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

    public function existenciasPorSucursal(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('ventas.crear'), 403, 'Sin permiso: ventas.crear');

        $user = Auth::user();
        $empresaId = (int) $user->empresa_id;
        $sucursalActualId = (int) $user->sucursal_id;

        $data = $request->validate([
            'producto_id' => ['required', 'integer'],
            'variante_id' => ['nullable', 'integer'],
        ]);

        $productoId = (int) $data['producto_id'];
        $varianteId = array_key_exists('variante_id', $data) && $data['variante_id'] !== null
            ? (int) $data['variante_id']
            : null;

        $producto = Producto::where('empresa_id', $empresaId)
            ->where('id', $productoId)
            ->select('id', 'nombre', 'codigo', 'tiene_variantes')
            ->firstOrFail();

        $variante = $varianteId
            ? ProductoVariante::where('producto_id', $productoId)->where('id', $varianteId)->firstOrFail()
            : null;

        $sucursales = Sucursal::where('empresa_id', $empresaId)
            ->where('activo', true)
            ->where('id', '!=', $sucursalActualId)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $sucursalIds = $sucursales->pluck('id')->all();

        $inventario = Inventario::where('empresa_id', $empresaId)
            ->where('producto_id', $productoId)
            ->whereIn('sucursal_id', $sucursalIds)
            ->when($varianteId, fn($q) => $q->where('variante_id', $varianteId), fn($q) => $q->whereNull('variante_id'))
            ->get()
            ->keyBy('sucursal_id');

        $reservas = InventarioReserva::where('empresa_id', $empresaId)
            ->where('producto_id', $productoId)
            ->whereIn('sucursal_id', $sucursalIds)
            ->where('estado', 'activa')
            ->when($varianteId, fn($q) => $q->where('variante_id', $varianteId), fn($q) => $q->whereNull('variante_id'))
            ->selectRaw('sucursal_id, SUM(cantidad) as total')
            ->groupBy('sucursal_id')
            ->pluck('total', 'sucursal_id');

        $pedidosDisponibles = PedidoDetalle::where('pedido_detalles.producto_id', $productoId)
            ->when($varianteId, fn($q) => $q->where('pedido_detalles.variante_id', $varianteId), fn($q) => $q->whereNull('pedido_detalles.variante_id'))
            ->where('pedido_detalles.estado', 'disponible')
            ->whereDoesntHave('reservas', fn($q) => $q->where('estado', 'activa'))
            ->whereHas('pedido', fn($q) => $q
                ->where('empresa_id', $empresaId)
                ->whereIn('sucursal_id', $sucursalIds)
                ->whereNotIn('estado', ['entregado', 'cancelado']))
            ->join('pedidos', 'pedidos.id', '=', 'pedido_detalles.pedido_id')
            ->selectRaw('pedidos.sucursal_id, SUM(pedido_detalles.cantidad) as total')
            ->groupBy('pedidos.sucursal_id')
            ->pluck('total', 'pedidos.sucursal_id');

        $items = $sucursales->map(function ($sucursal) use ($inventario, $reservas, $pedidosDisponibles, $sucursalActualId) {
            $inv = $inventario->get($sucursal->id);
            $stock = (float) ($inv?->stock ?? 0);
            $reservado = (float) ($reservas[$sucursal->id] ?? 0) + (float) ($pedidosDisponibles[$sucursal->id] ?? 0);
            $disponible = max(0, $stock - $reservado);

            return [
                'sucursal_id' => $sucursal->id,
                'sucursal' => $sucursal->nombre,
                'actual' => (int) $sucursal->id === (int) $sucursalActualId,
                'stock' => $stock,
                'reservado' => $reservado,
                'disponible' => $disponible,
                'exhibido' => (bool) ($inv?->exhibido ?? false),
            ];
        })->values();

        return response()->json([
            'producto' => [
                'id' => $producto->id,
                'nombre' => $producto->nombre,
                'codigo' => $producto->codigo,
                'variante_id' => $variante?->id,
                'variante' => $variante?->nombreVariante(),
            ],
            'sucursales' => $items,
        ]);
    }
}
