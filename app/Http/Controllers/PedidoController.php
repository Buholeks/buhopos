<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\ClienteSaldoMovimiento;
use App\Models\CorteCaja;
use App\Models\Inventario;
use App\Models\InventarioReserva;
use App\Models\MovimientoCaja;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Services\FolioService;
use App\Support\TerminalResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PedidoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('pedidos.ver'), 403, 'Sin permiso: pedidos.ver');
        $user = Auth::user();

        $pedidos = Pedido::query()
            ->where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->with([
                'cliente:id,nombre,telefono',
                'detalles.producto:id,nombre,codigo',
                'detalles.variante:id,sku',
                'detalles.compraDetalle:id,compra_id',
            ])
            ->when($request->filled('tipo'), fn($q) => $q->where('tipo', $request->tipo))
            ->when($request->filled('estado'), fn($q) => $q->where('estado', $request->estado))
            ->when($request->filled('cliente_id'), fn($q) => $q->where('cliente_id', $request->integer('cliente_id')))
            ->when($request->filled('fecha_desde'), fn($q) => $q->whereDate('created_at', '>=', $request->fecha_desde))
            ->when($request->filled('fecha_hasta'), fn($q) => $q->whereDate('created_at', '<=', $request->fecha_hasta))
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $b = trim((string) $request->buscar);
                $q->where(function ($sq) use ($b) {
                    $sq->where('folio', 'like', "%{$b}%")
                        ->orWhereHas('cliente', fn($cq) => $cq
                            ->where('nombre', 'like', "%{$b}%")
                            ->orWhere('telefono', 'like', "%{$b}%"))
                        ->orWhereHas('detalles', fn($dq) => $dq->where('descripcion', 'like', "%{$b}%"));
                });
            })
            ->orderByDesc('id')
            ->paginate($request->integer('por_pagina', 20));

        return response()->json($pedidos);
    }

    public function show(int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('pedidos.ver'), 403, 'Sin permiso: pedidos.ver');
        $user = Auth::user();

        $pedido = Pedido::where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->with([
                'cliente:id,nombre,telefono',
                'detalles.producto:id,nombre,codigo',
                'detalles.variante:id,sku',
                'saldos' => fn($q) => $q->with('user:id,name')->orderBy('created_at'),
            ])
            ->findOrFail($id);

        return response()->json($pedido);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('pedidos.crear'), 403, 'Sin permiso: pedidos.crear');
        $user = Auth::user();
        $empresaId = (int) $user->empresa_id;
        $sucursalId = (int) $user->sucursal_id;
        $terminal = TerminalResolver::fromRequest($request);

        $data = $request->validate([
            'tipo' => ['required', Rule::in(['pedido', 'apartado'])],
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'fecha_promesa' => ['nullable', 'date'],
            'notas' => ['nullable', 'string'],
            'anticipo' => ['nullable', 'numeric', 'min:0'],
            'forma_pago' => ['nullable', Rule::in(['efectivo', 'tarjeta', 'transferencia'])],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['nullable', 'integer', 'exists:productos,id'],
            'detalles.*.variante_id' => ['nullable', 'integer', 'exists:producto_variantes,id'],
            'detalles.*.descripcion' => ['required', 'string', 'max:255'],
            'detalles.*.marca_texto' => ['nullable', 'string', 'max:120'],
            'detalles.*.modelo_texto' => ['nullable', 'string', 'max:120'],
            'detalles.*.color_texto' => ['nullable', 'string', 'max:80'],
            'detalles.*.talla_texto' => ['nullable', 'string', 'max:80'],
            'detalles.*.cantidad' => ['required', 'integer', 'min:1'],
            'detalles.*.precio_acordado' => ['required', 'numeric', 'min:0'],
            'detalles.*.notas' => ['nullable', 'string'],
        ]);

        $clienteValido = Cliente::where('id', $data['cliente_id'])
            ->where('empresa_id', $empresaId)
            ->exists();

        if (! $clienteValido) {
            return response()->json(['message' => 'El cliente no pertenece a esta empresa.'], 422);
        }

        if ($data['tipo'] === 'apartado') {
            foreach ($data['detalles'] as $i => $detalle) {
                if (empty($detalle['producto_id'])) {
                    return response()->json([
                        'message' => 'Un apartado requiere productos existentes en inventario.',
                        'errors' => ["detalles.{$i}.producto_id" => ['Selecciona un producto para apartar.']],
                    ], 422);
                }
            }
        }

        $subtotalValidacion = collect($data['detalles'])->sum(
            fn($d) => ((float) $d['precio_acordado']) * ((int) $d['cantidad'])
        );

        $anticipo = (float) ($data['anticipo'] ?? 0);
        if ($anticipo > 0 && empty($data['forma_pago'])) {
            return response()->json(['message' => 'Selecciona forma de pago para el anticipo.'], 422);
        }

        if ($anticipo > $subtotalValidacion) {
            return response()->json(['message' => 'El anticipo no puede ser mayor al total del pedido.'], 422);
        }

        $corte = null;
        if ($anticipo > 0) {
            $corte = $this->corteAbierto($empresaId, $sucursalId, $terminal);
            if (! $corte) {
                return response()->json(['message' => 'No hay caja abierta para registrar el anticipo.'], 422);
            }
        }

        DB::beginTransaction();

        try {
            $subtotal = collect($data['detalles'])->sum(
                fn($d) => ((float) $d['precio_acordado']) * ((int) $d['cantidad'])
            );

            $pedido = Pedido::create([
                'empresa_id' => $empresaId,
                'sucursal_id' => $sucursalId,
                'user_id' => $user->id,
                'cliente_id' => $data['cliente_id'],
                'folio' => FolioService::siguienteTicket($empresaId, $sucursalId, $data['tipo'] === 'apartado' ? 'APA' : 'PED'),
                'tipo' => $data['tipo'],
                'estado' => $data['tipo'] === 'apartado' ? 'disponible' : 'pendiente',
                'estado_pago' => $this->estadoPago($subtotal, $anticipo),
                'fecha_promesa' => $data['fecha_promesa'] ?? null,
                'subtotal' => $subtotal,
                'anticipo' => $anticipo,
                'saldo_pendiente' => max(0, $subtotal - $anticipo),
                'notas' => $data['notas'] ?? null,
            ]);

            foreach ($data['detalles'] as $detalle) {
                $productoId = $detalle['producto_id'] ?? null;
                $varianteId = $detalle['variante_id'] ?? null;
                $cantidad = (int) $detalle['cantidad'];
                $lineaSubtotal = $cantidad * (float) $detalle['precio_acordado'];

                if ($productoId) {
                    $this->validarProductoTenant($empresaId, $productoId, $varianteId);
                }

                $pedidoDetalle = PedidoDetalle::create([
                    'pedido_id' => $pedido->id,
                    'producto_id' => $productoId,
                    'variante_id' => $varianteId,
                    'descripcion' => $detalle['descripcion'],
                    'marca_texto' => $detalle['marca_texto'] ?? null,
                    'modelo_texto' => $detalle['modelo_texto'] ?? null,
                    'color_texto' => $detalle['color_texto'] ?? null,
                    'talla_texto' => $detalle['talla_texto'] ?? null,
                    'cantidad' => $cantidad,
                    'precio_acordado' => $detalle['precio_acordado'],
                    'subtotal' => $lineaSubtotal,
                    'estado' => $data['tipo'] === 'apartado' ? 'reservado' : 'pendiente',
                    'notas' => $detalle['notas'] ?? null,
                ]);

                if ($data['tipo'] === 'apartado') {
                    $this->reservarInventario($pedido, $pedidoDetalle, $productoId, $varianteId, $cantidad);
                }
            }

            if ($anticipo > 0) {
                $this->registrarAnticipo($pedido, $corte, $anticipo, $data['forma_pago']);
            }

            DB::commit();

            return response()->json($pedido->load(['cliente', 'detalles.producto', 'detalles.variante', 'saldos']), 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function buscarCatalogo(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('pedidos.ver'), 403, 'Sin permiso: pedidos.ver');
        $empresaId = (int) Auth::user()->empresa_id;
        $q = trim((string) $request->q);

        if (strlen($q) < 1) {
            return response()->json([]);
        }

        $productos = Producto::where('empresa_id', $empresaId)
            ->where('activo', true)
            ->where(fn($pq) => $pq
                ->where('nombre', 'like', "%{$q}%")
                ->orWhere('codigo', 'like', "%{$q}%"))
            ->limit(12)
            ->get()
            ->map(fn($p) => [
                'tipo_resultado' => 'producto',
                'id' => null,
                'producto_id' => $p->id,
                'nombre' => $p->nombre,
                'codigo' => $p->codigo,
                'sku' => null,
                'codigo_barras' => null,
                'nombre_variante' => null,
                'precio_venta' => (float) ($p->precio_venta ?? 0),
                'tiene_variantes' => (bool) $p->tiene_variantes,
            ]);

        $variantes = ProductoVariante::whereHas('producto', fn($pq) => $pq
            ->where('empresa_id', $empresaId)
            ->where('activo', true))
            ->with([
                'producto:id,nombre,codigo,precio_venta,tiene_variantes',
                'atributos.tipoAtributo:id,nombre',
                'atributos.atributo:id,valor',
            ])
            ->where(fn($vq) => $vq
                ->where('sku', 'like', "%{$q}%")
                ->orWhere('codigo_barras', 'like', "%{$q}%")
                ->orWhereHas('producto', fn($pq) => $pq
                    ->where('nombre', 'like', "%{$q}%")
                    ->orWhere('codigo', 'like', "%{$q}%")))
            ->limit(20)
            ->get()
            ->map(fn($v) => [
                'tipo_resultado' => 'variante',
                'id' => $v->id,
                'producto_id' => $v->producto_id,
                'nombre' => $v->producto->nombre,
                'codigo' => $v->producto->codigo,
                'sku' => $v->sku,
                'codigo_barras' => $v->codigo_barras,
                'nombre_variante' => $v->nombreVariante(),
                'precio_venta' => (float) ($v->precio_venta ?? $v->producto->precio_venta ?? 0),
                'tiene_variantes' => (bool) $v->producto->tiene_variantes,
            ]);

        return response()->json($productos->merge($variantes)->values());
    }

    public function pendientesCompra(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('pedidos.crear'), 403, 'Sin permiso: pedidos.crear');
        $user = Auth::user();
        $buscar = trim((string) $request->input('buscar', ''));

        $detalles = PedidoDetalle::query()
            ->where('estado', 'pendiente')
            ->whereNull('compra_detalle_id')
            ->whereNotNull('producto_id')
            ->whereHas('pedido', fn($query) => $query
                ->where('empresa_id', $user->empresa_id)
                ->where('sucursal_id', $user->sucursal_id)
                ->where('tipo', 'pedido')
                ->whereIn('estado', ['pendiente', 'en_proceso', 'parcial']))
            ->whereHas('producto', fn($query) => $query->where('pedido_generico', true))
            ->when($buscar !== '', fn($query) => $query->where(function ($subquery) use ($buscar) {
                $subquery
                    ->where('descripcion', 'like', "%{$buscar}%")
                    ->orWhereHas('pedido', fn($pedido) => $pedido
                        ->where('folio', 'like', "%{$buscar}%")
                        ->orWhereHas('cliente', fn($cliente) => $cliente->where('nombre', 'like', "%{$buscar}%")));
            }))
            ->with([
                'pedido:id,cliente_id,folio,created_at',
                'pedido.cliente:id,nombre',
                'producto:id,nombre,codigo,precio_costo,precio_venta,pedido_generico,imagen',
                'variante:id,producto_id,sku,precio_costo,precio_venta,imagen',
            ])
            ->orderBy('id')
            ->limit(500)
            ->get()
            ->map(fn($detalle) => [
                'id' => $detalle->id,
                'pedido_id' => $detalle->pedido_id,
                'folio' => $detalle->pedido?->folio,
                'cliente' => $detalle->pedido?->cliente?->nombre,
                'producto_id' => $detalle->producto_id,
                'variante_id' => $detalle->variante_id,
                'producto' => $detalle->producto?->nombre,
                'codigo' => $detalle->producto?->codigo,
                'sku' => $detalle->variante?->sku,
                'descripcion' => $detalle->descripcion,
                'cantidad' => (float) $detalle->cantidad,
                'precio_acordado' => (float) $detalle->precio_acordado,
                'precio_compra' => (float) ($detalle->variante?->precio_costo ?? $detalle->producto?->precio_costo ?? 0),
                'precio_venta' => (float) $detalle->precio_acordado,
                'imagen_url' => $detalle->variante?->imagen_url ?? $detalle->producto?->imagen_url,
            ]);

        return response()->json($detalles);
    }

    public function abonar(Request $request, int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('pedidos.crear'), 403, 'Sin permiso: pedidos.crear');
        $user = Auth::user();
        $terminal = TerminalResolver::fromRequest($request);
        $pedido = Pedido::where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->findOrFail($id);

        if (in_array($pedido->estado, ['entregado', 'cancelado'])) {
            return response()->json(['message' => 'Este pedido ya está cerrado.'], 422);
        }

        $data = $request->validate([
            'monto' => ['required', 'numeric', 'min:0.01'],
            'forma_pago' => ['required', Rule::in(['efectivo', 'tarjeta', 'transferencia'])],
        ]);

        $monto = (float) $data['monto'];
        if ($monto > (float) $pedido->saldo_pendiente) {
            return response()->json(['message' => 'El abono supera el saldo pendiente del pedido.'], 422);
        }

        $corte = $this->corteAbierto((int) $user->empresa_id, (int) $user->sucursal_id, $terminal);
        if (! $corte) {
            return response()->json(['message' => 'No hay caja abierta para registrar el abono.'], 422);
        }

        DB::beginTransaction();
        try {
            $pedido->anticipo = (float) $pedido->anticipo + $monto;
            $pedido->saldo_pendiente = max(0, (float) $pedido->subtotal - (float) $pedido->anticipo);
            $pedido->estado_pago = $this->estadoPago((float) $pedido->subtotal, (float) $pedido->anticipo);
            $pedido->save();

            $this->registrarAnticipo($pedido, $corte, $monto, $data['forma_pago']);

            DB::commit();
            return response()->json($pedido->fresh()->load(['cliente', 'detalles', 'saldos']));
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function eliminarAbono(int $pedidoId, int $abonoId): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('pedidos.crear'), 403, 'Sin permiso: pedidos.crear');
        $user = Auth::user();

        $pedido = Pedido::where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->findOrFail($pedidoId);

        if (in_array($pedido->estado, ['entregado', 'cancelado'])) {
            return response()->json(['message' => 'No se puede modificar un pedido cerrado.'], 422);
        }

        $abono = ClienteSaldoMovimiento::where('pedido_id', $pedido->id)
            ->where('tipo', 'abono')
            ->findOrFail($abonoId);

        DB::beginTransaction();
        try {
            $monto = (float) $abono->monto;
            $cajaMensaje = '';

            // Si el corte sigue abierto, eliminar el movimiento de caja asociado por FK exacto
            if ($abono->corte_id) {
                $corte = CorteCaja::where('id', $abono->corte_id)->where('estado', 'abierto')->first();
                if ($corte) {
                    if ($abono->movimiento_caja_id) {
                        MovimientoCaja::where('id', $abono->movimiento_caja_id)->delete();
                    } else {
                        // Fallback para abonos antiguos sin FK: búsqueda heurística
                        MovimientoCaja::where('corte_id', $abono->corte_id)
                            ->where('tipo', 'ingreso')
                            ->where('monto', $abono->monto)
                            ->where('concepto', 'like', "%{$pedido->folio}%")
                            ->orderByDesc('id')
                            ->limit(1)
                            ->delete();
                    }
                    $corte->recalcularMovimientos();
                } else {
                    $cajaMensaje = ' El corte de caja ya está cerrado; ajusta manualmente si es necesario.';
                }
            }

            $pedido->anticipo = max(0, (float) $pedido->anticipo - $monto);
            $pedido->saldo_pendiente = max(0, (float) $pedido->subtotal - (float) $pedido->anticipo);
            $pedido->estado_pago = $this->estadoPago((float) $pedido->subtotal, (float) $pedido->anticipo);
            $pedido->save();

            $abono->delete();

            DB::commit();
            return response()->json([
                'message' => 'Abono eliminado.' . $cajaMensaje,
                'pedido' => $pedido->fresh()->load(['cliente', 'detalles', 'saldos']),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function clienteResumen(int $clienteId): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('ventas.crear'), 403, 'Sin permiso: ventas.crear');
        $user = Auth::user();

        $saldo = ClienteSaldoMovimiento::where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->where('cliente_id', $clienteId)
            ->sum(DB::raw("CASE WHEN tipo IN ('abono','devolucion','ajuste') THEN monto ELSE -monto END"));

        $pedidos = Pedido::where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->where('cliente_id', $clienteId)
            ->whereIn('estado', ['disponible', 'parcial'])
            ->with([
                'detalles.producto:id,nombre,codigo',
                'detalles.variante:id,sku',
                'detalles.compraDetalle:id,compra_id',
            ])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $tieneProductosPendientes = PedidoDetalle::whereHas('pedido', fn($query) => $query
            ->where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->where('cliente_id', $clienteId)
            ->whereNotIn('estado', ['entregado', 'cancelado']))
            ->whereNotIn('estado', ['entregado', 'cancelado'])
            ->exists();

        return response()->json([
            'saldo_favor' => round((float) $saldo, 2),
            'pedidos_disponibles' => $pedidos,
            'tiene_productos_pendientes' => $tieneProductosPendientes,
        ]);
    }

    public function cancelar(Request $request, int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('pedidos.cancelar'), 403, 'Sin permiso: pedidos.cancelar');
        $user = Auth::user();

        $pedido = Pedido::where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->findOrFail($id);

        if (in_array($pedido->estado, ['entregado', 'cancelado'])) {
            return response()->json(['message' => 'Este pedido ya está cerrado y no se puede cancelar.'], 422);
        }

        DB::beginTransaction();
        try {
            // Liberar reservas de inventario activas
            InventarioReserva::where('pedido_id', $pedido->id)
                ->where('estado', 'activa')
                ->update(['estado' => 'liberada']);

            // Cancelar todos los detalles y limpiar vínculo con compra
            PedidoDetalle::where('pedido_id', $pedido->id)
                ->whereNotIn('estado', ['entregado', 'cancelado'])
                ->update([
                    'estado' => 'cancelado',
                    'compra_detalle_id' => null,
                ]);

            // Si tiene anticipo, acreditarlo al saldo a favor del cliente
            $anticipo = (float) $pedido->anticipo;
            if ($anticipo > 0) {
                $saldoAnterior = ClienteSaldoMovimiento::where('empresa_id', $pedido->empresa_id)
                    ->where('sucursal_id', $pedido->sucursal_id)
                    ->where('cliente_id', $pedido->cliente_id)
                    ->sum(DB::raw("CASE WHEN tipo IN ('abono','devolucion','ajuste') THEN monto ELSE -monto END"));

                ClienteSaldoMovimiento::create([
                    'empresa_id' => $pedido->empresa_id,
                    'sucursal_id' => $pedido->sucursal_id,
                    'cliente_id' => $pedido->cliente_id,
                    'pedido_id' => $pedido->id,
                    'corte_id' => null,
                    'user_id' => Auth::id(),
                    'tipo' => 'devolucion',
                    'forma_pago' => null,
                    'monto' => $anticipo,
                    'saldo_resultante' => (float) $saldoAnterior + $anticipo,
                    'concepto' => "Cancelación {$pedido->folio} - anticipo acreditado",
                ]);
            }

            $pedido->estado = 'cancelado';
            $pedido->save();

            DB::commit();

            return response()->json([
                'message' => $anticipo > 0
                    ? "Pedido cancelado. Se acreditaron $" . number_format($anticipo, 2) . " al saldo a favor del cliente."
                    : 'Pedido cancelado.',
                'pedido' => $pedido->fresh()->load(['cliente', 'detalles']),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    private function registrarAnticipo(Pedido $pedido, CorteCaja $corte, float $monto, string $formaPago): void
    {
        $movimientoCaja = MovimientoCaja::create([
            'corte_id' => $corte->id,
            'user_id' => Auth::id(),
            'tipo' => 'ingreso',
            'forma_pago' => $formaPago,
            'monto' => $monto,
            'concepto' => "Anticipo {$pedido->tipo} {$pedido->folio}",
        ]);

        $saldoAnterior = ClienteSaldoMovimiento::where('empresa_id', $pedido->empresa_id)
            ->where('sucursal_id', $pedido->sucursal_id)
            ->where('cliente_id', $pedido->cliente_id)
            ->sum(DB::raw("CASE WHEN tipo IN ('abono','devolucion','ajuste') THEN monto ELSE -monto END"));

        ClienteSaldoMovimiento::create([
            'empresa_id'         => $pedido->empresa_id,
            'sucursal_id'        => $pedido->sucursal_id,
            'cliente_id'         => $pedido->cliente_id,
            'pedido_id'          => $pedido->id,
            'corte_id'           => $corte->id,
            'movimiento_caja_id' => $movimientoCaja->id,
            'user_id'            => Auth::id(),
            'tipo'               => 'abono',
            'forma_pago'         => $formaPago,
            'monto'              => $monto,
            'saldo_resultante'   => (float) $saldoAnterior + $monto,
            'concepto'           => "Anticipo {$pedido->folio}",
        ]);

        $corte->recalcularMovimientos();
    }

    private function reservarInventario(Pedido $pedido, PedidoDetalle $detalle, int $productoId, ?int $varianteId, int $cantidad): void
    {
        $inv = Inventario::where([
            'empresa_id' => $pedido->empresa_id,
            'sucursal_id' => $pedido->sucursal_id,
            'producto_id' => $productoId,
            'variante_id' => $varianteId,
        ])->lockForUpdate()->first();

        $reservado = InventarioReserva::where([
            'empresa_id' => $pedido->empresa_id,
            'sucursal_id' => $pedido->sucursal_id,
            'producto_id' => $productoId,
            'variante_id' => $varianteId,
            'estado' => 'activa',
        ])->sum('cantidad');

        $disponible = (float) ($inv?->stock ?? 0) - (float) $reservado;
        if ($disponible < $cantidad) {
            throw new \RuntimeException("Stock disponible insuficiente para apartar {$detalle->descripcion}. Disponible: {$disponible}.");
        }

        InventarioReserva::create([
            'empresa_id' => $pedido->empresa_id,
            'sucursal_id' => $pedido->sucursal_id,
            'pedido_id' => $pedido->id,
            'pedido_detalle_id' => $detalle->id,
            'producto_id' => $productoId,
            'variante_id' => $varianteId,
            'cantidad' => $cantidad,
            'estado' => 'activa',
        ]);
    }

    private function validarProductoTenant(int $empresaId, int $productoId, ?int $varianteId): void
    {
        $producto = Producto::where('empresa_id', $empresaId)->findOrFail($productoId);

        if ($varianteId) {
            ProductoVariante::where('empresa_id', $empresaId)
                ->where('producto_id', $producto->id)
                ->findOrFail($varianteId);
        }
    }

    private function corteAbierto(int $empresaId, int $sucursalId, string $terminal): ?CorteCaja
    {
        return CorteCaja::where('empresa_id', $empresaId)
            ->where('sucursal_id', $sucursalId)
            ->where('terminal', $terminal)
            ->where('estado', 'abierto')
            ->latest('fecha_apertura')
            ->first();
    }

    private function estadoPago(float $subtotal, float $anticipo): string
    {
        if ($anticipo <= 0) return 'sin_anticipo';
        if ($anticipo >= $subtotal && $subtotal > 0) return 'pagado';
        return 'con_anticipo';
    }
}
