<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\Serie;
use App\Models\CompraDetalle;
use App\Models\Inventario;
use App\Models\InventarioReserva;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\ProductoVariante;
use App\Models\Producto;
use App\Models\ProveedorSaldoMovimiento;
use App\Servicios\KardexServicio;
use App\Support\ProductVariantSearch;
use App\Support\VariantImageResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CompraController extends Controller
{
    // ── GET /api/compras ────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('compras.ver'), 403, 'Sin permiso: compras.ver');
        $empresaId = Auth::user()->empresa_id;

        $compras = Compra::where('empresa_id', $empresaId)
            ->with(['proveedor:id,nombre', 'user:id,name'])
            ->when(
                $request->buscar,
                fn($q, $b) =>
                $q->where(
                    fn($q) => $q
                        ->where('folio', 'like', "%{$b}%")
                        ->orWhereHas('proveedor', fn($q) => $q->where('nombre', 'like', "%{$b}%"))
                )
            )
            ->when($request->estado, fn($q, $e) => $q->where('estado', $e))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate($request->por_pagina ?? 20);

        return response()->json($compras);
    }

    // ── GET /api/compras/{id} ───────────────────────────────────────────────
    public function show(int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('compras.ver'), 403, 'Sin permiso: compras.ver');
        $empresaId = Auth::user()->empresa_id;

        $compra = Compra::where('empresa_id', $empresaId)
            ->with([
                'proveedor:id,nombre',
                'user:id,name',
                'detalles.producto:id,nombre,codigo',
                'detalles.variante:id,sku',
            ])
            ->findOrFail($id);

        return response()->json($compra);
    }

    // ── POST /api/compras ───────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('compras.crear'), 403, 'Sin permiso: compras.crear');
        $user       = Auth::user();
        $empresaId  = (int) $user->empresa_id;
        $sucursalId = (int) $user->sucursal_id;

        $datos = $request->validate([
            'proveedor_id'      => ['required', 'exists:proveedores,id'],
            'folio'             => ['nullable', 'string', 'max:100'],
            'fecha'             => ['required', 'date'],
            'forma_pago'        => ['required', 'in:efectivo,credito,transferencia,tarjeta_debito,tarjeta_credito'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha'],
            'notas'             => ['nullable', 'string'],
            'aplicar_saldo_favor' => ['nullable', 'boolean'],

            'detalles'                 => ['required', 'array', 'min:1'],
            'detalles.*.producto_id'   => ['required', 'exists:productos,id'],
            'detalles.*.variante_id'   => ['nullable', 'exists:producto_variantes,id'],
            'detalles.*.cantidad'      => ['required', 'numeric', 'min:0.001'],
            'detalles.*.precio_compra' => ['required', 'numeric', 'min:0'],
            'detalles.*.precio_venta'  => ['nullable', 'numeric', 'min:0'],
            'detalles.*.imeis'         => ['nullable', 'array'],
            'detalles.*.imeis.*'       => ['nullable', 'string', 'max:20'],
            'detalles.*.pedido_detalle_ids' => ['nullable', 'array'],
            'detalles.*.pedido_detalle_ids.*' => ['integer', 'distinct'],
        ]);

        DB::beginTransaction();
        try {
            $compra = Compra::create([
                'empresa_id'        => $empresaId,
                'sucursal_id'       => $sucursalId,
                'user_id'           => $user->id,
                'proveedor_id'      => $datos['proveedor_id']      ?? null,
                'folio'             => $datos['folio']             ?? null,
                'fecha'             => $datos['fecha'],
                'forma_pago'        => $datos['forma_pago'],
                'fecha_vencimiento' => $datos['fecha_vencimiento'] ?? null,
                'notas'             => $datos['notas']             ?? null,
                'estado'            => 'confirmada',
                'subtotal'          => 0,
                'total'             => 0,
            ]);

            foreach ($datos['detalles'] as $det) {
                $productoId   = (int) $det['producto_id'];
                $varianteId   = isset($det['variante_id']) ? (int) $det['variante_id'] : null;
                $cantidad     = (float) $det['cantidad'];
                $precioCompra = (float) $det['precio_compra'];
                $precioVenta  = isset($det['precio_venta']) ? (float) $det['precio_venta'] : null;
                $subtotal     = $cantidad * $precioCompra;

                $compraDetalle = CompraDetalle::create([
                    'compra_id'     => $compra->id,
                    'producto_id'   => $productoId,
                    'variante_id'   => $varianteId,
                    'cantidad'      => $cantidad,
                    'precio_compra' => $precioCompra,
                    'precio_venta'  => $precioVenta,
                    'subtotal'      => $subtotal,
                ]);

                // ── Incrementar inventario ─────────────────────────────────
                $inv = Inventario::firstOrCreate(
                    [
                        'empresa_id'  => $empresaId,
                        'sucursal_id' => $sucursalId,
                        'producto_id' => $productoId,
                        'variante_id' => $varianteId,
                    ],
                    ['stock' => 0]
                );

                $inv = Inventario::where([
                    'empresa_id'  => $empresaId,
                    'sucursal_id' => $sucursalId,
                    'producto_id' => $productoId,
                    'variante_id' => $varianteId,
                ])
                    ->lockForUpdate()
                    ->first();

                $stockAntes = (float) $inv->stock;
                $stockDespues = $stockAntes + $cantidad;
                $inv->stock = $stockDespues;
                $inv->save();

                app(KardexServicio::class)->registrar([
                    'empresa_id' => $empresaId,
                    'sucursal_id' => $sucursalId,
                    'producto_id' => $productoId,
                    'variante_id' => $varianteId,
                    'user_id' => $user->id,
                    'tipo' => 'compra',
                    'direccion' => 'entrada',
                    'cantidad' => $cantidad,
                    'stock_antes' => $stockAntes,
                    'stock_despues' => $stockDespues,
                    'costo_unitario' => $precioCompra,
                    'precio_unitario' => $precioVenta,
                    'importe' => $subtotal,
                    'referencia_tipo' => 'compra',
                    'referencia_id' => $compra->id,
                    'referencia_detalle_id' => $compraDetalle->id,
                    'folio' => $compra->folio,
                    'fecha' => $compra->created_at ?? now(),
                    'metadata' => [
                        'proveedor_id' => $compra->proveedor_id,
                        'pedido_detalle_ids' => $det['pedido_detalle_ids'] ?? [],
                    ],
                ]);

                $this->vincularPedidosSeleccionados(
                    $empresaId,
                    $sucursalId,
                    $productoId,
                    $varianteId,
                    $compraDetalle,
                    $det['pedido_detalle_ids'] ?? []
                );

                // ── Actualizar precios ─────────────────────────────────────
                if ($varianteId) {
                    $updateVariante = ['precio_costo' => $precioCompra];
                    if ($precioVenta) $updateVariante['precio_venta'] = $precioVenta;
                    ProductoVariante::where('id', $varianteId)->update($updateVariante);
                } else {
                    $updateProducto = ['precio_costo' => $precioCompra];
                    if ($precioVenta) $updateProducto['precio_venta'] = $precioVenta;
                    Producto::where('id', $productoId)->update($updateProducto);
                }

                // ── Registrar series / IMEIs ───────────────────────────────
                $imeis = $det['imeis'] ?? [];
                foreach ($imeis as $imei) {
                    if (! $imei) continue;
                    Serie::create([
                        'empresa_id'   => $empresaId,
                        'sucursal_id'  => $sucursalId,
                        'producto_id'  => $productoId,
                        'variante_id'  => $varianteId,
                        'compra_id'    => $compra->id,
                        'proveedor_id' => $datos['proveedor_id'] ?? null,
                        'imei'         => $imei,
                        'precio_costo' => $precioCompra,
                        'precio_venta' => $precioVenta,
                        'estado'       => 'disponible',
                    ]);
                }
            }

            $compra->recalcularTotales();

            // ── Update final: folio + pagado + saldo en un solo query ─────
            $liquidaAlMomento = in_array($datos['forma_pago'], ['efectivo', 'transferencia', 'tarjeta_debito']);
            $saldoFavorDisponible = 0;
            $saldoFavorAplicado = 0;

            if ($datos['aplicar_saldo_favor'] ?? false) {
                $movimientosSaldo = ProveedorSaldoMovimiento::where([
                    'empresa_id' => $empresaId,
                    'sucursal_id' => $sucursalId,
                    'proveedor_id' => $compra->proveedor_id,
                ])->lockForUpdate()->get(['tipo', 'monto']);

                $saldoFavorDisponible = (float) $movimientosSaldo->sum(
                    fn($movimiento) => $movimiento->tipo === 'credito'
                        ? (float) $movimiento->monto
                        : -(float) $movimiento->monto
                );
                $saldoFavorAplicado = round(min(max(0, $saldoFavorDisponible), (float) $compra->total), 2);
            }

            $updateFinal = [
                'pagado' => $liquidaAlMomento ? $compra->total : $saldoFavorAplicado,
                'saldo'  => $liquidaAlMomento ? 0 : max(0, (float) $compra->total - $saldoFavorAplicado),
                'saldo_favor_aplicado' => $saldoFavorAplicado,
            ];

            if (empty($datos['folio'])) {
                // Se usa la fecha de la propia compra (elegida por el usuario) en vez de
                // now(), que corre en UTC y desfasa el folio/conteo respecto al día de
                // México, generando folios duplicados o con la fecha del día siguiente.
                $fechaCompra = $compra->fecha;
                $fechaHoy = $fechaCompra->format('dmy');
                // lockForUpdate para evitar folio duplicado en compras concurrentes sin folio
                $countHoy = DB::table('compras')
                    ->where('empresa_id',  $empresaId)
                    ->where('sucursal_id', $sucursalId)
                    ->whereDate('fecha', $fechaCompra->toDateString())
                    ->lockForUpdate()
                    ->count();

                $updateFinal['folio'] = $fechaHoy . str_pad($countHoy, 2, '0', STR_PAD_LEFT);
            }

            DB::table('compras')
                ->where('id', $compra->id)
                ->update($updateFinal);

            if (! empty($updateFinal['folio'])) {
                DB::table('kardex_movimientos')
                    ->where('referencia_tipo', 'compra')
                    ->where('referencia_id', $compra->id)
                    ->update(['folio' => $updateFinal['folio']]);
            }

            $compra->refresh()->load(['empresa', 'sucursal', 'proveedor']);
            $snapshotBuilder = app(EtiquetaController::class);
            $compra->detalles()
                ->with([
                    'producto.marca', 'producto.modelo', 'producto.categoria',
                    'variante.atributos.tipoAtributo', 'variante.atributos.atributo',
                ])
                ->get()
                ->each(function (CompraDetalle $detalle) use ($compra, $snapshotBuilder) {
                    $detalle->update([
                        'etiqueta_snapshot' => $snapshotBuilder->snapshot(
                            $compra,
                            $detalle->producto,
                            $detalle->variante,
                            (float) $detalle->precio_compra,
                            (float) $detalle->precio_venta
                        ),
                    ]);
                });

            if ($saldoFavorAplicado > 0) {
                $folioCompra = $updateFinal['folio'] ?? $compra->folio ?? "#{$compra->id}";
                ProveedorSaldoMovimiento::create([
                    'empresa_id' => $empresaId,
                    'sucursal_id' => $sucursalId,
                    'proveedor_id' => $compra->proveedor_id,
                    'user_id' => $user->id,
                    'compra_id' => $compra->id,
                    'tipo' => 'aplicacion',
                    'monto' => $saldoFavorAplicado,
                    'saldo_resultante' => max(0, $saldoFavorDisponible - $saldoFavorAplicado),
                    'concepto' => "Aplicacion de saldo en compra {$folioCompra}",
                ]);
            }

            DB::commit();

            return response()->json(
                $compra->fresh()->load(['proveedor', 'detalles.producto', 'detalles.variante', 'user:id,name']),
                201
            );
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // ── POST /api/compras/{id}/cancelar ────────────────────────────────────
    private function vincularPedidosSeleccionados(
        int $empresaId,
        int $sucursalId,
        int $productoId,
        ?int $varianteId,
        CompraDetalle $compraDetalle,
        array $pedidoDetalleIds
    ): void {
        $pedidoDetalleIds = collect($pedidoDetalleIds)
            ->map(fn($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $variante = $varianteId
            ? ProductoVariante::with(['atributos.atributo'])->find($varianteId)
            : null;

        if ($pedidoDetalleIds->isEmpty()) {
            $productoGenerico = Producto::where('id', $productoId)
                ->where('empresa_id', $empresaId)
                ->value('pedido_generico');

            if ($productoGenerico) {
                $tienePedidosPendientes = PedidoDetalle::query()
                    ->where('producto_id', $productoId)
                    ->where('estado', 'pendiente')
                    ->whereNull('compra_detalle_id')
                    ->whereHas('pedido', fn($q) => $q
                        ->where('empresa_id', $empresaId)
                        ->where('sucursal_id', $sucursalId)
                        ->where('tipo', 'pedido')
                        ->whereIn('estado', ['pendiente', 'en_proceso', 'parcial']))
                    ->lockForUpdate()
                    ->get()
                    ->contains(fn($detalle) => $this->detallePedidoCoincideConCompra($detalle, $varianteId, $variante));

                if ($tienePedidosPendientes) {
                    throw ValidationException::withMessages([
                        'detalles' => ['Selecciona al menos un pedido pendiente para vincular la compra del producto genérico.'],
                    ]);
                }

                return;
            }

            $cantidadDisponible = (float) $compraDetalle->cantidad;
            $pedidoDetalleIds = PedidoDetalle::query()
                ->where('producto_id', $productoId)
                ->where('estado', 'pendiente')
                ->whereNull('compra_detalle_id')
                ->whereHas('pedido', fn($q) => $q
                    ->where('empresa_id', $empresaId)
                    ->where('sucursal_id', $sucursalId)
                    ->where('tipo', 'pedido')
                    ->whereIn('estado', ['pendiente', 'en_proceso', 'parcial']))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->filter(fn($detalle) => $this->detallePedidoCoincideConCompra($detalle, $varianteId, $variante))
                ->filter(function ($detalle) use (&$cantidadDisponible) {
                    $cantidad = (float) $detalle->cantidad;
                    if ($cantidad > $cantidadDisponible) {
                        return false;
                    }
                    $cantidadDisponible -= $cantidad;
                    return true;
                })
                ->pluck('id')
                ->values();

            if ($pedidoDetalleIds->isEmpty()) {
                return;
            }
        }

        $detalles = PedidoDetalle::query()
            ->whereIn('id', $pedidoDetalleIds)
            ->where('producto_id', $productoId)
            ->whereIn('estado', ['pendiente', 'disponible'])
            ->whereHas('pedido', fn($q) => $q
                ->where('empresa_id', $empresaId)
                ->where('sucursal_id', $sucursalId)
                ->where('tipo', 'pedido')
                ->whereIn('estado', ['pendiente', 'en_proceso', 'parcial']))
            ->with('pedido')
            ->lockForUpdate()
            ->get();

        if ($detalles->count() !== $pedidoDetalleIds->count()) {
            throw ValidationException::withMessages([
                'detalles' => ['Uno o más renglones de pedido seleccionados ya no están disponibles o no corresponden al producto.'],
            ]);
        }

        $cantidadPedidos = (float) $detalles->sum('cantidad');
        if ($cantidadPedidos > (float) $compraDetalle->cantidad) {
            throw ValidationException::withMessages([
                'detalles' => ['La cantidad de pedidos seleccionados supera la cantidad comprada.'],
            ]);
        }

        foreach ($detalles as $detalle) {
            if (! $this->detallePedidoCoincideConCompra($detalle, $varianteId, $variante)) {
                throw ValidationException::withMessages([
                    'detalles' => ["El renglón del pedido {$detalle->pedido?->folio} no coincide con la variante comprada."],
                ]);
            }

            $cantidad = (float) $detalle->cantidad;
            $detalle->update([
                'compra_detalle_id' => $compraDetalle->id,
                'estado' => 'disponible',
                'disponible_desde' => now(),
            ]);

            InventarioReserva::create([
                'empresa_id' => $empresaId,
                'sucursal_id' => $sucursalId,
                'pedido_id' => $detalle->pedido_id,
                'pedido_detalle_id' => $detalle->id,
                'producto_id' => $productoId,
                'variante_id' => $varianteId,
                'cantidad' => $cantidad,
                'estado' => 'activa',
            ]);

            $this->actualizarEstadoPedido($detalle->pedido);
        }
    }

    public function pedidosPendientes(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('compras.crear'), 403, 'Sin permiso: compras.crear');
        $user = Auth::user();
        $data = $request->validate([
            'producto_id' => ['required', 'integer'],
            'variante_id' => ['nullable', 'integer'],
        ]);

        $productoId = (int) $data['producto_id'];
        $varianteId = isset($data['variante_id']) ? (int) $data['variante_id'] : null;
        $variante = $varianteId
            ? ProductoVariante::with(['atributos.atributo'])->find($varianteId)
            : null;

        $detalles = PedidoDetalle::query()
            ->where('producto_id', $productoId)
            ->where('estado', 'pendiente')
            ->whereNull('compra_detalle_id')
            ->whereHas('pedido', fn($q) => $q
                ->where('empresa_id', $user->empresa_id)
                ->where('sucursal_id', $user->sucursal_id)
                ->where('tipo', 'pedido')
                ->whereIn('estado', ['pendiente', 'en_proceso', 'parcial']))
            ->with('pedido.cliente:id,nombre')
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->filter(fn($detalle) => $this->detallePedidoCoincideConCompra($detalle, $varianteId, $variante))
            ->values()
            ->map(fn($detalle) => [
                'id' => $detalle->id,
                'pedido_id' => $detalle->pedido_id,
                'folio' => $detalle->pedido?->folio,
                'cliente' => $detalle->pedido?->cliente?->nombre,
                'descripcion' => $detalle->descripcion,
                'cantidad' => (float) $detalle->cantidad,
                'precio_acordado' => (float) $detalle->precio_acordado,
            ]);

        return response()->json($detalles);
    }

    private function detallePedidoCoincideConCompra(
        PedidoDetalle $detalle,
        ?int $varianteId,
        ?ProductoVariante $variante
    ): bool {
        if ((int) ($detalle->variante_id ?? 0) === (int) ($varianteId ?? 0)) {
            return true;
        }

        if ($detalle->variante_id !== null || $varianteId === null || ! $variante) {
            return false;
        }

        $valoresPedido = collect([
            $detalle->color_texto,
            $detalle->talla_texto,
            $detalle->modelo_texto,
        ])
            ->map(fn($v) => $this->normalizarTextoVariante($v))
            ->filter()
            ->values();

        if ($valoresPedido->isEmpty()) {
            return false;
        }

        $textoVariante = $this->normalizarTextoVariante(
            $variante->nombreVariante() . ' ' . $variante->sku . ' ' . $variante->codigo_barras
        );

        return $valoresPedido->every(fn($valor) => str_contains($textoVariante, $valor));
    }

    private function normalizarTextoVariante(?string $texto): string
    {
        $texto = trim((string) $texto);
        if ($texto === '') {
            return '';
        }

        $texto = mb_strtolower($texto, 'UTF-8');
        $texto = ProductVariantSearch::quitarAcentos($texto);
        return preg_replace('/[^a-z0-9]+/', '', $texto) ?? '';
    }

    private function actualizarEstadoPedido(Pedido $pedido): void
    {
        $detalles = $pedido->detalles()->get(['estado']);
        if ($detalles->isEmpty()) {
            return;
        }

        if ($detalles->every(fn($d) => in_array($d->estado, ['disponible', 'reservado', 'entregado'], true))) {
            $pedido->update(['estado' => 'disponible']);
            return;
        }

        if ($detalles->contains(fn($d) => in_array($d->estado, ['disponible', 'reservado', 'entregado'], true))) {
            $pedido->update(['estado' => 'parcial']);
        }
    }

    private function liberarPedidosVinculadosACompraDetalle(int $compraDetalleId): void
    {
        $detalles = PedidoDetalle::where('compra_detalle_id', $compraDetalleId)
            ->with('pedido')
            ->lockForUpdate()
            ->get();

        foreach ($detalles as $detalle) {
            InventarioReserva::where('pedido_detalle_id', $detalle->id)
                ->where('estado', 'activa')
                ->update(['estado' => 'liberada']);

            if ($detalle->estado !== 'entregado') {
                $detalle->update([
                    'compra_detalle_id' => null,
                    'estado' => 'pendiente',
                    'disponible_desde' => null,
                ]);
            }

            if ($detalle->pedido) {
                $this->actualizarEstadoPedidoDespuesDeLiberar($detalle->pedido);
            }
        }
    }

    private function actualizarEstadoPedidoDespuesDeLiberar(Pedido $pedido): void
    {
        $detalles = $pedido->detalles()->get(['estado']);
        if ($detalles->isEmpty()) {
            return;
        }

        if ($detalles->every(fn($d) => $d->estado === 'pendiente')) {
            $pedido->update(['estado' => 'pendiente']);
            return;
        }

        if ($detalles->contains(fn($d) => $d->estado === 'pendiente')) {
            $pedido->update(['estado' => 'parcial']);
        }
    }

    public function cancelar(int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('compras.crear'), 403, 'Sin permiso: compras.crear');
        $user      = Auth::user();
        $empresaId = (int) $user->empresa_id;

        $compra = Compra::where('empresa_id', $empresaId)
            ->with('detalles')
            ->findOrFail($id);

        if ($compra->estado === 'cancelada') {
            return response()->json(['message' => 'La compra ya está cancelada.'], 422);
        }
        if ($compra->estado !== 'confirmada') {
            return response()->json(['message' => 'Solo se puede cancelar una compra confirmada.'], 422);
        }

        DB::beginTransaction();
        try {
            foreach ($compra->detalles as $det) {
                $productoId = (int) $det->producto_id;
                $varianteId = $det->variante_id ? (int) $det->variante_id : null;
                $cantidad   = (float) $det->cantidad;

                $inv = Inventario::where([
                    'empresa_id'  => $empresaId,
                    'sucursal_id' => (int) $compra->sucursal_id,
                    'producto_id' => $productoId,
                    'variante_id' => $varianteId,
                ])
                    ->lockForUpdate()
                    ->first();

                if (! $inv) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "Inventario no encontrado para revertir (producto {$productoId}).",
                    ], 422);
                }

                $nuevo = (float) $inv->stock - $cantidad;
                if ($nuevo < 0) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "No se puede cancelar: el stock quedaría negativo (producto {$productoId}).",
                    ], 422);
                }

                $stockAntes = (float) $inv->stock;
                $inv->stock = $nuevo;
                $inv->save();

                app(KardexServicio::class)->registrar([
                    'empresa_id' => $empresaId,
                    'sucursal_id' => (int) $compra->sucursal_id,
                    'producto_id' => $productoId,
                    'variante_id' => $varianteId,
                    'user_id' => $user->id,
                    'tipo' => 'cancelacion_compra',
                    'direccion' => 'salida',
                    'cantidad' => $cantidad,
                    'stock_antes' => $stockAntes,
                    'stock_despues' => $nuevo,
                    'costo_unitario' => (float) $det->precio_compra,
                    'precio_unitario' => $det->precio_venta !== null ? (float) $det->precio_venta : null,
                    'importe' => (float) $det->subtotal,
                    'referencia_tipo' => 'compra',
                    'referencia_id' => $compra->id,
                    'referencia_detalle_id' => $det->id,
                    'folio' => $compra->folio,
                    'fecha' => now(),
                ]);

                $this->liberarPedidosVinculadosACompraDetalle($det->id);
            }

            $compra->update(['estado' => 'cancelada']);
            DB::commit();

            return response()->json(['mensaje' => 'Compra cancelada y stock revertido.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // ── DELETE /api/compras/{id} ────────────────────────────────────────────
    public function destroy(int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('compras.crear'), 403, 'Sin permiso: compras.crear');
        $empresaId = (int) Auth::user()->empresa_id;
        $compra    = Compra::where('empresa_id', $empresaId)->findOrFail($id);

        if ($compra->estado === 'confirmada') {
            return response()->json([
                'message' => 'No se puede eliminar una compra confirmada. Cancélala primero.',
            ], 422);
        }

        $compra->delete();
        return response()->json(['mensaje' => 'Compra eliminada.']);
    }

    // ── GET /api/compras/buscar-variantes ───────────────────────────────────
    //
    // Reglas:
    //   1. Productos SIN variantes → aparece el producto como ítem directo
    //      (variante_id = null, id = null para que el buscador lo identifique como producto)
    //   2. Productos CON variantes → aparecen solo las variantes, nunca el producto padre
    // ─────────────────────────────────────────────────────────────────────────
    public function buscarVariantes(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('compras.crear'), 403, 'Sin permiso: compras.crear');
        $empresaId = (int) Auth::user()->empresa_id;
        $q         = trim($request->q ?? '');

        if (strlen($q) < 1) return response()->json([]);
        $tokens = ProductVariantSearch::tokens($q);

        $productoExacto = Producto::where('empresa_id', $empresaId)
            ->where('activo', true)
            ->where('tiene_variantes', false)
            ->where('codigo', $q)
            ->select('id', 'nombre', 'codigo', 'precio_costo', 'precio_venta', 'imagen', 'tiene_series', 'pedido_generico')
            ->first();

        if ($productoExacto) {
            return response()->json([[
                'id'              => null,
                'producto_id'     => $productoExacto->id,
                'nombre'          => $productoExacto->nombre,
                'codigo'          => $productoExacto->codigo,
                'sku'             => null,
                'codigo_barras'   => null,
                'nombre_variante' => null,
                'precio_compra'   => (float) ($productoExacto->precio_costo ?? 0),
                'precio_venta'    => (float) ($productoExacto->precio_venta ?? 0),
                'imagen_url'      => $productoExacto->imagen_url,
                'tiene_variantes' => false,
                'tiene_series'    => (bool) $productoExacto->tiene_series,
                'pedido_generico' => (bool) $productoExacto->pedido_generico,
                'grupo_producto_id' => $productoExacto->id,
                'grupo_producto'   => $productoExacto->nombre,
            ]]);
        }

        $varianteExacta = ProductoVariante::where('empresa_id', $empresaId)
            ->where('activo', true)
            ->where(fn($vq) => $vq
                ->where('sku', $q)
                ->orWhere('codigo_barras', $q))
            ->whereHas('producto', fn($pq) => $pq
                ->where('empresa_id', $empresaId)
                ->where('activo', true)
                ->where('tiene_variantes', true))
            ->with([
                'producto:id,nombre,codigo,precio_costo,precio_venta,imagen,tiene_series,pedido_generico',
                'atributos.tipoAtributo:id,nombre',
                'atributos.atributo:id,valor',
            ])
            ->select('id', 'producto_id', 'empresa_id', 'sku', 'codigo_barras', 'imagen', 'precio_costo', 'precio_venta')
            ->first();

        if ($varianteExacta) {
            $varianteExacta = VariantImageResolver::applyResolvedImagesWithSiblingImages(collect([$varianteExacta]), $empresaId)->first();

            return response()->json([[
                'id'              => $varianteExacta->id,
                'producto_id'     => $varianteExacta->producto_id,
                'nombre'          => $varianteExacta->producto->nombre,
                'codigo'          => $varianteExacta->producto->codigo,
                'sku'             => $varianteExacta->sku,
                'codigo_barras'   => $varianteExacta->codigo_barras,
                'nombre_variante' => $varianteExacta->nombreVariante(),
                'precio_compra'   => (float) ($varianteExacta->precio_costo ?? $varianteExacta->producto->precio_costo ?? 0),
                'precio_venta'    => (float) ($varianteExacta->precio_venta ?? $varianteExacta->producto->precio_venta ?? 0),
                'imagen_url'      => $varianteExacta->imagen_url_resuelta ?? $varianteExacta->imagen_url,
                'imagen_url_resuelta' => $varianteExacta->imagen_url_resuelta ?? $varianteExacta->imagen_url,
                'tiene_variantes' => true,
                'tiene_series'    => (bool) $varianteExacta->producto->tiene_series,
                'pedido_generico' => (bool) $varianteExacta->producto->pedido_generico,
                'grupo_producto_id' => $varianteExacta->producto_id,
                'grupo_producto'   => $varianteExacta->producto->nombre,
            ]]);
        }

        if ($tokens === []) {
            return response()->json([]);
        }

        $resultados = collect();

        // ── 1. Productos SIN variantes ────────────────────────────────────
        $productos = Producto::where('empresa_id', $empresaId)
            ->where('activo', true)
            ->where('tiene_variantes', false)        // solo los que no tienen variantes
            ->tap(fn($query) => ProductVariantSearch::applyProductoTokens($query, $tokens))
            ->orderByDesc('pedido_generico')
            ->orderBy('nombre')
            ->limit(40)
            ->select('id', 'nombre', 'codigo', 'precio_costo', 'precio_venta', 'imagen', 'tiene_series', 'pedido_generico')
            ->get()
            ->filter(fn($p) => ProductVariantSearch::matches($tokens, ProductVariantSearch::productoText($p)))
            ->map(function ($p) use ($tokens, $q) {
                $searchText = ProductVariantSearch::productoText($p);

                return [
                    'id'              => null,           // sin variante_id
                    'producto_id'     => $p->id,
                    'nombre'          => $p->nombre,
                    'codigo'          => $p->codigo,
                    'sku'             => null,
                    'codigo_barras'   => null,
                    'nombre_variante' => null,
                    'precio_compra'   => (float) ($p->precio_costo ?? 0),
                    'precio_venta'    => (float) ($p->precio_venta ?? 0),
                    'imagen_url'      => $p->imagen_url,
                    'tiene_variantes' => false,
                    'tiene_series'    => (bool) $p->tiene_series,
                    'pedido_generico' => (bool) $p->pedido_generico,
                    'grupo_producto_id' => $p->id,
                    'grupo_producto'   => $p->nombre,
                    '_score'         => ProductVariantSearch::score($tokens, $q, ['codigo' => $p->codigo], $searchText),
                ];
            });

        $resultados = $resultados->merge($productos);

        // ── 2. Variantes de productos CON variantes ───────────────────────
        $variantes = ProductoVariante::whereHas(
            'producto',
            fn($pq) =>
            $pq->where('empresa_id', $empresaId)
                ->where('activo', true)
                ->where('tiene_variantes', true)  // solo de productos con variantes
        )
            ->with([
                'producto:id,nombre,codigo,precio_costo,precio_venta,imagen,tiene_series,pedido_generico',
                'atributos.tipoAtributo:id,nombre',
                'atributos.atributo:id,valor',
            ])
            ->where(
                fn($q2) => ProductVariantSearch::applyVarianteTokens($q2, $tokens)
            )
            ->limit(80)
            ->select('id', 'producto_id', 'empresa_id', 'sku', 'codigo_barras', 'imagen', 'precio_costo', 'precio_venta')
            ->get()
            ->filter(fn($v) => ProductVariantSearch::matches($tokens, ProductVariantSearch::varianteText($v)));

        $variantes = VariantImageResolver::applyResolvedImagesWithSiblingImages($variantes, $empresaId)
            ->map(function ($v) use ($tokens, $q) {
                $searchText = ProductVariantSearch::varianteText($v);

                return [
                    'id'              => $v->id,          // este es el variante_id
                    'producto_id'     => $v->producto_id,
                    'nombre'          => $v->producto->nombre,
                    'codigo'          => $v->producto->codigo,
                    'sku'             => $v->sku,
                    'codigo_barras'   => $v->codigo_barras,
                    'nombre_variante' => $v->nombreVariante(),
                    'precio_compra'   => (float) ($v->precio_costo ?? $v->producto->precio_costo ?? 0),
                    'precio_venta'    => (float) ($v->precio_venta ?? $v->producto->precio_venta ?? 0),
                    'imagen_url'      => $v->imagen_url_resuelta ?? $v->imagen_url,
                    'imagen_url_resuelta' => $v->imagen_url_resuelta ?? $v->imagen_url,
                    'tiene_variantes' => true,
                    'tiene_series'    => (bool) $v->producto->tiene_series,
                    'pedido_generico' => (bool) $v->producto->pedido_generico,
                    'grupo_producto_id' => $v->producto_id,
                    'grupo_producto'   => $v->producto->nombre,
                    '_score'         => ProductVariantSearch::score($tokens, $q, [
                        'codigo' => $v->producto->codigo,
                        'sku' => $v->sku,
                        'codigo_barras' => $v->codigo_barras,
                    ], $searchText),
                ];
            });

        $resultados = $resultados->merge($variantes)->values();
        $resultados = $resultados
            ->sortBy([
                fn($a, $b) => ($b['_score'] ?? 0) <=> ($a['_score'] ?? 0),
                fn($a, $b) => strcmp($a['nombre'] ?? '', $b['nombre'] ?? ''),
                fn($a, $b) => strcmp($a['nombre_variante'] ?? '', $b['nombre_variante'] ?? ''),
            ])
            ->sortByDesc(fn($resultado) => (int) ($resultado['pedido_generico'] ?? false))
            ->take(25)
            ->map(function ($resultado) {
                unset($resultado['_score']);
                return $resultado;
            })
            ->values();

        return response()->json($resultados);
    }
}
