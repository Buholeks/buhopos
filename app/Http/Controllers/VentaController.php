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
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Models\CorteCaja;
use App\Models\Serie;
use App\Servicios\KardexServicio;
use App\Support\ProductVariantSearch;
use App\Support\TerminalResolver;
use App\Support\VariantImageResolver;

class VentaController extends Controller
{
    // ── GET /api/ventas ─────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('ventas.ver'), 403, 'Sin permiso: ventas.ver');
        $empresaId = Auth::user()->empresa_id;

        $ventas = Venta::where('empresa_id', $empresaId)
            ->with(['user:id,name', 'pagos.cuentaBancaria:id,nombre,banco', 'pagos.terminalPago:id,nombre,banco'])
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
                'pagos.cuentaBancaria:id,nombre,banco',
                'pagos.terminalPago:id,nombre,banco',
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
            'pagos'                    => ['nullable', 'array'],
            'pagos.*.forma_pago'       => ['required', 'in:efectivo,tarjeta,transferencia'],
            'pagos.*.monto'            => ['required', 'numeric', 'min:0.01'],
            'pagos.*.cuenta_bancaria_id' => [
                'required_if:pagos.*.forma_pago,transferencia', 'nullable', 'integer',
                Rule::exists('cuentas_bancarias', 'id')->where(fn($q) => $q->where('empresa_id', $empresaId)->where('activo', true)),
            ],
            'pagos.*.terminal_pago_id' => [
                'required_if:pagos.*.forma_pago,tarjeta', 'nullable', 'integer',
                Rule::exists('terminales_pago', 'id')->where(fn($q) => $q->where('empresa_id', $empresaId)->where('activo', true)),
            ],
            'pagos.*.monto_recibido'   => ['nullable', 'numeric', 'min:0'],
            'descuento'                => ['nullable', 'numeric', 'min:0'],
            'saldo_aplicado'           => ['nullable', 'numeric', 'min:0'],
            'notas'                    => ['nullable', 'string'],
            'idempotency_key'          => ['nullable', 'string', 'max:64'],

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

        if (!empty($datos['idempotency_key'])) {
            $ventaExistente = Venta::where('empresa_id', $empresaId)
                ->where('idempotency_key', $datos['idempotency_key'])
                ->first();

            if ($ventaExistente) {
                return response()->json($this->cargarVentaCompleta($ventaExistente), 200);
            }
        }

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
        $totalACobrar = max(0, $totalCalculado - $saldoAplicado);
        $pagos = $datos['pagos'] ?? [];

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
                    ->whereNotIn('estado', ['entregado', 'devuelto', 'cancelado'])
                    ->whereHas('pedido', fn($query) => $query
                        ->where('empresa_id', $empresaId)
                        ->where('sucursal_id', $sucursalId)
                        ->where('cliente_id', $datos['cliente_id'])
                        ->whereNotIn('estado', ['entregado', 'devuelto', 'cancelado', 'vencido']))
                    ->count();

                if ($detalleIds->count() !== count($datos['detalles']) || $detallesValidos !== $detalleIds->count()) {
                    return response()->json([
                        'message' => 'El cliente tiene productos pendientes. Su saldo a favor solo puede usarse para liquidar esos pedidos o apartados.',
                        'campo' => 'saldo_aplicado',
                    ], 422);
                }
            }
        }

        if ($totalACobrar > 0 && empty($pagos)) {
            return response()->json([
                'message' => 'Selecciona al menos un método de pago.',
                'campo'   => 'pagos',
            ], 422);
        }

        $sumaPagos = round(collect($pagos)->sum(fn($p) => (float) $p['monto']), 2);

        if (abs($sumaPagos - $totalACobrar) > 0.01) {
            return response()->json([
                'message' => 'La suma de los pagos no coincide con el total a cobrar.',
                'campo'   => 'pagos',
            ], 422);
        }

        foreach ($pagos as $i => $p) {
            if ($p['forma_pago'] === 'efectivo' && isset($p['monto_recibido']) && (float) $p['monto_recibido'] < (float) $p['monto']) {
                return response()->json([
                    'message' => 'El monto recibido no puede ser menor al monto de esa línea de pago.',
                    'campo'   => "pagos.{$i}.monto_recibido",
                ], 422);
            }
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
                'descuento'       => $descuento,
                'saldo_aplicado'  => $saldoAplicado,
                'notas'           => $datos['notas'] ?? null,
                'idempotency_key' => $datos['idempotency_key'] ?? null,
                'estado'          => 'confirmada',
                'subtotal'        => 0,
                'total'           => 0,
            ]);

            $this->guardarPagosVenta($venta, $pagos, $saldoAplicado);

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

                $exhibicionVendida = null;

                if ((bool) ($det['era_exhibido'] ?? false)) {
                    $exhibicionVendida = $this->exhibicionActivaParaVenta(
                        $empresaId,
                        $sucursalId,
                        $productoId,
                        $varianteId,
                        true
                    );

                    if (! $exhibicionVendida) {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'La variante seleccionada no corresponde al producto exhibido.',
                            'campo'   => 'era_exhibido',
                        ], 422);
                    }
                }

                $stockDespues = $stockActual - $cantidad;
                $inv->descontarVenta($cantidad, false);

                if ($exhibicionVendida) {
                    $exhibicionVendida->quitarExhibicion();
                }

                app(KardexServicio::class)->registrar([
                    'empresa_id' => $empresaId,
                    'sucursal_id' => $sucursalId,
                    'producto_id' => $productoId,
                    'variante_id' => $varianteId,
                    'serie_id' => $serieId,
                    'user_id' => $user->id,
                    'tipo' => 'venta',
                    'direccion' => 'salida',
                    'cantidad' => $cantidad,
                    'stock_antes' => $stockActual,
                    'stock_despues' => $stockDespues,
                    'costo_unitario' => $precioCosto,
                    'precio_unitario' => $precioVenta,
                    'importe' => $subtotalLinea,
                    'referencia_tipo' => 'venta',
                    'referencia_id' => $venta->id,
                    'referencia_detalle_id' => $detalle->id,
                    'folio' => $venta->folio,
                    'fecha' => $venta->created_at ?? now(),
                    'metadata' => [
                        'cliente_id' => $venta->cliente_id,
                        'vendedor_id' => $venta->vendedor_id,
                        'pedido_id' => $det['pedido_id'] ?? null,
                        'pedido_detalle_id' => $det['pedido_detalle_id'] ?? null,
                        'lista_precio_usada' => $listaPrecioUsada,
                        'era_exhibido' => (bool) ($det['era_exhibido'] ?? false),
                    ],
                ]);

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
            $corte->recalcularEsperados();

            DB::commit();

            return response()->json($this->cargarVentaCompleta($venta), 201);
        } catch (QueryException $e) {
            DB::rollBack();

            // Índice único de idempotency_key: dos envíos casi simultáneos del mismo
            // intento de venta (doble clic, reintento tras timeout) chocan aquí; la
            // petición perdedora regresa la venta que sí se guardó, en vez de un error.
            if ($e->getCode() === '23000' && !empty($datos['idempotency_key'])) {
                $ventaExistente = Venta::where('empresa_id', $empresaId)
                    ->where('idempotency_key', $datos['idempotency_key'])
                    ->first();

                if ($ventaExistente) {
                    return response()->json($this->cargarVentaCompleta($ventaExistente), 200);
                }
            }

            return response()->json([
                'message' => 'Error interno: ' . $e->getMessage()
            ], 500);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error interno: ' . $e->getMessage()
            ], 500);
        }
    }

    private function cargarVentaCompleta(Venta $venta): Venta
    {
        return $venta->load([
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
            'pagos.cuentaBancaria:id,nombre,banco',
            'pagos.terminalPago:id,nombre,banco',
        ]);
    }

    // Crea las líneas de venta_pagos: una por cada método que envió el cajero,
    // más una de saldo_favor si se aplicó saldo del cliente.
    private function guardarPagosVenta(Venta $venta, array $pagos, float $saldoAplicado): void
    {
        foreach ($pagos as $p) {
            $monto = round((float) $p['monto'], 2);
            $montoRecibido = $p['forma_pago'] === 'efectivo' ? (float) ($p['monto_recibido'] ?? $monto) : null;

            $venta->pagos()->create([
                'forma_pago'         => $p['forma_pago'],
                'monto'              => $monto,
                'cuenta_bancaria_id' => $p['forma_pago'] === 'transferencia' ? ($p['cuenta_bancaria_id'] ?? null) : null,
                'terminal_pago_id'   => $p['forma_pago'] === 'tarjeta' ? ($p['terminal_pago_id'] ?? null) : null,
                'monto_recibido'     => $montoRecibido,
                'cambio'             => $montoRecibido !== null ? round($montoRecibido - $monto, 2) : null,
            ]);
        }

        if ($saldoAplicado > 0) {
            $venta->pagos()->create([
                'forma_pago' => 'saldo_favor',
                'monto'      => $saldoAplicado,
            ]);
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
            ->sum(DB::raw("CASE WHEN tipo IN ('abono','devolucion','ajuste') THEN monto ELSE -monto END")), 2);
    }

    private function clienteTieneProductosPendientes(int $empresaId, int $sucursalId, int $clienteId): bool
    {
        return PedidoDetalle::whereHas('pedido', fn($query) => $query
            ->where('empresa_id', $empresaId)
            ->where('sucursal_id', $sucursalId)
            ->where('cliente_id', $clienteId)
            ->whereNotIn('estado', ['entregado', 'devuelto', 'cancelado', 'vencido']))
            ->whereNotIn('estado', ['entregado', 'devuelto', 'cancelado'])
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
            ->whereNotIn('estado', ['entregado', 'devuelto', 'cancelado'])
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
                    ->whereNotIn('estado', ['entregado', 'devuelto', 'cancelado', 'vencido']))
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
            ->with([
                'producto:id,nombre,codigo,precio_costo,precio_venta,precio1,precio2,precio3,precio4,precio5,imagen,tiene_variantes,tiene_series',
                'variante:id,producto_id,empresa_id,sku,imagen',
                'variante.atributos.tipoAtributo:id,nombre',
                'variante.atributos.atributo:id,valor',
            ])
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
            $serieImagenUrl = $serie->producto->imagen_url;

            if ($serie->variante) {
                $varianteSerie = VariantImageResolver::applyResolvedImagesWithSiblingImages(collect([$serie->variante]), $empresaId)->first();
                $serieImagenUrl = $varianteSerie?->imagen_url_resuelta ?? $varianteSerie?->imagen_url ?? $serieImagenUrl;
            }

            $resultados->push([
                'id'              => $serie->variante_id, // null si sin variante
                'producto_id'     => $serie->producto_id,
                'nombre'          => $serie->producto->nombre,
                'codigo'          => $serie->producto->codigo,
                'sku'             => null,
                'codigo_barras'   => $serie->imei,
                'nombre_variante' => null,
                'imagen_url'      => $serieImagenUrl,
                'imagen_url_resuelta' => $serieImagenUrl,
                'stock'           => $stock,
                'sin_stock'       => $stock <= 0,
                'exhibido'        => (bool) $this->exhibicionActivaParaVenta($empresaId, $sucursalId, $serie->producto_id, $serie->variante_id),
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
                $exhibido = (bool) $this->exhibicionActivaParaVenta($empresaId, $sucursalId, $p->id, null);

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
                'precio5',
                'imagen'
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

        $variantes = VariantImageResolver::applyResolvedImagesWithSiblingImages($variantes, $empresaId);

        // ── Bulk stock comprometido para variantes ────────────────────────────
        $paresVariantes = $variantes->map(fn($v) => ['producto_id' => $v->producto_id, 'variante_id' => $v->id])->all();
        $stockComprometidoV = count($paresVariantes) > 0
            ? $buildStockComprometido($paresVariantes)
            : fn($p, $v) => 0.0;

        $variantes = $variantes->map(function ($v) use ($empresaId, $sucursalId, $getStock, $resolverPrecio, $stockComprometidoV) {
                $inv      = $getStock($empresaId, $sucursalId, $v->producto_id, $v->id);
                $stock    = max(0, (float) ($inv?->stock ?? 0) - $stockComprometidoV($v->producto_id, $v->id));
                $exhibido = (bool) $this->exhibicionActivaParaVenta($empresaId, $sucursalId, $v->producto_id, $v->id);

                return [
                    'id'              => $v->id,
                    'producto_id'     => $v->producto_id,
                    'nombre'          => $v->producto->nombre,
                    'codigo'          => $v->producto->codigo,
                    'sku'             => $v->sku,
                    'codigo_barras'   => $v->codigo_barras,
                    'nombre_variante' => $v->nombreVariante(),
                    'imagen_url'      => $v->imagen_url_resuelta ?? $v->imagen_url,
                    'imagen_url_resuelta' => $v->imagen_url_resuelta ?? $v->imagen_url,
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

        $filtroStock = $request->input('filtro_stock', 'todos');
        if ($filtroStock === 'con_existencia') {
            $resultados = $resultados->filter(fn($r) => !$r['sin_stock'])->values();
        } elseif ($filtroStock === 'sin_existencia') {
            $resultados = $resultados->filter(fn($r) => $r['sin_stock'])->values();
        }

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
                ->whereNotIn('estado', ['entregado', 'devuelto', 'cancelado', 'vencido']))
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

    private function exhibicionActivaParaVenta(
        int $empresaId,
        int $sucursalId,
        int $productoId,
        ?int $varianteId,
        bool $lock = false
    ): ?Inventario {
        $query = Inventario::where('empresa_id', $empresaId)
            ->where('sucursal_id', $sucursalId)
            ->where('producto_id', $productoId)
            ->where('exhibido', true);

        if ($varianteId) {
            $query->where('variante_exhibida_id', $varianteId);
        } else {
            $query->whereNull('variante_id')
                ->whereNull('variante_exhibida_id');
        }

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }
}
