<?php

namespace App\Http\Controllers;

use App\Models\CorteCaja;
use App\Models\ClienteSaldoMovimiento;
use App\Models\Devolucion;
use App\Models\Empresa;
use App\Models\Inventario;
use App\Models\InventarioReserva;
use App\Models\MovimientoCaja;
use App\Models\PedidoDetalle;
use App\Models\Serie;
use App\Models\Sucursal;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Services\FolioService;
use App\Servicios\KardexServicio;
use App\Support\TerminalResolver;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CancelacionDevolucionController extends Controller
{
    public function buscar(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('ventas.cancelar'), 403, 'Sin permiso: ventas.cancelar');
        $data = $request->validate([
            'folio' => ['required', 'string', 'max:100'],
        ]);

        $user = $request->user();
        $venta = $this->buscarVentaPorFolio($data['folio'], (int) $user->empresa_id, (int) $user->sucursal_id);

        if (! $venta) {
            return response()->json([
                'message' => 'No se encontro un ticket con ese folio en esta sucursal.',
            ], 404);
        }

        return response()->json($this->ventaParaProceso($venta));
    }

    public function exportarPdf(Request $request): mixed
    {
        abort_unless(Auth::user()->tienePermiso('ventas.cancelar'), 403, 'Sin permiso: ventas.cancelar');
        $data = $request->validate(['folio' => ['required', 'string', 'max:100']]);

        $user   = $request->user();
        $venta  = $this->buscarVentaPorFolio($data['folio'], (int) $user->empresa_id, (int) $user->sucursal_id);
        abort_if(! $venta, 404, 'Ticket no encontrado.');

        $empresa  = Empresa::find($user->empresa_id);
        $sucursal = Sucursal::find($user->sucursal_id);

        $logoB64 = null;
        if ($empresa?->logo && Storage::disk('public')->exists($empresa->logo)) {
            $contenido = Storage::disk('public')->get($empresa->logo);
            $mime      = Storage::disk('public')->mimeType($empresa->logo) ?: 'image/png';
            $logoB64   = 'data:' . $mime . ';base64,' . base64_encode($contenido);
        }

        $pdf = Pdf::loadView('pdf.venta-cancelacion', [
            'venta'             => $this->ventaParaProceso($venta),
            'titulo'            => 'Cancelación / Devolución ' . $venta->folio,
            'filtrosAplicados'  => [],
            'empresaNombre'     => $empresa?->nombre ?? config('app.name'),
            'empresaLogoB64'    => $logoB64,
            'empresaDireccion'  => $empresa?->direccion,
            'sucursalNombre'    => $sucursal?->nombre,
            'sucursalDireccion' => $sucursal?->direccion,
            'fecha'             => now('America/Mexico_City')->format('d/m/Y H:i'),
        ])->setPaper('letter', 'portrait');

        return $pdf->download('cancelacion_' . $venta->folio . '.pdf');
    }

    public function cancelar(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('ventas.cancelar'), 403, 'Sin permiso: ventas.cancelar');
        $data = $request->validate([
            'folio' => ['required', 'string', 'max:100'],
            'motivo' => ['required', 'string', 'max:255'],
            'tipo_proceso' => ['nullable', 'in:anulacion,devolucion'],
            'destino_pedido' => ['nullable', 'in:disponible,devuelto'],
            'forma_devolucion' => ['nullable', 'in:efectivo,tarjeta,transferencia,saldo_favor'],
        ]);

        $user = $request->user();
        $terminal = TerminalResolver::fromRequest($request);

        return DB::transaction(function () use ($data, $user, $terminal) {
            $venta = $this->buscarVentaPorFolio($data['folio'], (int) $user->empresa_id, (int) $user->sucursal_id, true);

            if (! $venta) {
                return response()->json([
                    'message' => 'No se encontro un ticket con ese folio en esta sucursal.',
                ], 404);
            }

            if ($venta->estado === 'cancelada') {
                return response()->json(['message' => 'La venta ya esta cancelada.'], 422);
            }

            if ($venta->devoluciones()->where('estado', 'confirmada')->exists()) {
                return response()->json([
                    'message' => 'La venta ya tiene devoluciones registradas. No se puede cancelar completa.',
                ], 422);
            }

            $tipoProceso = ($data['tipo_proceso'] ?? '') ?: 'anulacion';
            $destinoPedido = ($data['destino_pedido'] ?? '') ?: ($tipoProceso === 'devolucion' ? 'devuelto' : 'disponible');
            $formaDevolucion = $data['forma_devolucion'] ?? 'efectivo';

            $corteDevolucion = $this->corteAbiertoPorTerminal((int) $user->empresa_id, (int) $user->sucursal_id, $terminal);
            $desglose = $this->desgloseCancelacion($venta, $formaDevolucion, $corteDevolucion?->id);
            $movimientoTotal = (float) $desglose['ingreso_lineas']->sum('monto') + (float) $desglose['egreso_monto'];

            if ($desglose['saldo'] > 0 && ! $venta->cliente_id) {
                return response()->json([
                    'message' => 'La devolucion a saldo requiere que la venta tenga cliente asignado.',
                ], 422);
            }

            if ($movimientoTotal > 0 && ! $corteDevolucion) {
                return response()->json([
                    'message' => 'No hay caja abierta para registrar el movimiento de dinero.',
                ], 422);
            }

            foreach ($venta->detalles as $detalle) {
                $this->regresarInventario($venta, $detalle, (float) $detalle->cantidad, [
                    'tipo' => 'cancelacion_venta',
                    'user_id' => $user->id,
                    'motivo' => $data['motivo'],
                    'referencia_tipo' => 'venta',
                    'referencia_id' => $venta->id,
                    'referencia_detalle_id' => $detalle->id,
                    'folio' => $venta->folio,
                    'fecha' => now(),
                ]);
                if ($detalle->pedido_detalle_id) {
                    $destinoPedido === 'devuelto'
                        ? $this->marcarPedidoDetalleDevuelto($detalle)
                        : $this->revertirPedidoDetalleVenta($detalle);
                }
            }

            $corteTocado = false;

            if ($corteDevolucion && $desglose['ingreso_lineas']->isNotEmpty()) {
                foreach ($desglose['ingreso_lineas'] as $linea) {
                    MovimientoCaja::create([
                        'corte_id' => $corteDevolucion->id,
                        'user_id' => $user->id,
                        'tipo' => 'ingreso',
                        'forma_pago' => $linea->forma_pago,
                        'cuenta_bancaria_id' => $linea->cuenta_bancaria_id,
                        'terminal_pago_id' => $linea->terminal_pago_id,
                        'monto' => $linea->monto,
                        'concepto' => "Saldo a favor por cancelacion {$venta->folio}",
                    ]);
                }
                $corteTocado = true;
            }

            if ($corteDevolucion && $desglose['egreso_monto'] > 0) {
                MovimientoCaja::create([
                    'corte_id' => $corteDevolucion->id,
                    'user_id' => $user->id,
                    'tipo' => 'egreso',
                    'forma_pago' => $desglose['egreso_forma_pago'],
                    'monto' => $desglose['egreso_monto'],
                    'concepto' => "Reembolso por cancelacion {$venta->folio} (venta de corte anterior)",
                ]);
                $corteTocado = true;
            }

            if ($corteTocado) {
                $corteDevolucion->recalcularMovimientos();
            }

            if ($desglose['saldo'] > 0) {
                $this->registrarMovimientoSaldo(
                    $venta,
                    $desglose['saldo'],
                    $formaDevolucion === 'saldo_favor' ? 'devolucion' : 'ajuste',
                    'saldo_favor',
                    "Devolucion de saldo por cancelacion {$venta->folio}",
                    $corteDevolucion?->id ?? $venta->corte_id
                );
            }

            $venta->update([
                'estado' => 'cancelada',
                'cancelado_por' => $user->id,
                'cancelado_en' => now(),
                'motivo_cancelacion' => $data['motivo'],
            ]);

            $venta->corte?->recalcularVentas();
            $venta->corte?->recalcularEsperados();

            return response()->json([
                'message' => 'Venta cancelada correctamente.',
                'venta' => $this->ventaParaProceso($venta->fresh()),
            ]);
        });
    }

    public function devolver(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('ventas.cancelar'), 403, 'Sin permiso: ventas.cancelar');
        $data = $request->validate([
            'folio' => ['required', 'string', 'max:100'],
            'motivo' => ['required', 'string', 'max:255'],
            'forma_devolucion' => ['required', 'in:efectivo,tarjeta,transferencia,saldo_favor'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.venta_detalle_id' => ['required', 'integer', 'exists:venta_detalles,id'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.001'],
        ]);

        $user = $request->user();
        $terminal = TerminalResolver::fromRequest($request);

        return DB::transaction(function () use ($data, $user, $terminal) {
            $venta = $this->buscarVentaPorFolio($data['folio'], (int) $user->empresa_id, (int) $user->sucursal_id, true);

            if (! $venta) {
                return response()->json([
                    'message' => 'No se encontro un ticket con ese folio en esta sucursal.',
                ], 404);
            }

            if ($venta->estado === 'cancelada') {
                return response()->json(['message' => 'No se puede devolver una venta cancelada.'], 422);
            }

            $detallesVenta = $venta->detalles->keyBy('id');
            $lineas = collect($data['detalles'])
                ->map(fn($linea) => [
                    'venta_detalle_id' => (int) $linea['venta_detalle_id'],
                    'cantidad' => (float) $linea['cantidad'],
                ])
                ->filter(fn($linea) => $linea['cantidad'] > 0)
                ->values();

            if ($lineas->isEmpty()) {
                return response()->json(['message' => 'Selecciona al menos una partida para devolver.'], 422);
            }

            $total = 0;
            foreach ($lineas as $linea) {
                $detalle = $detallesVenta[$linea['venta_detalle_id']] ?? null;

                if (! $detalle) {
                    return response()->json(['message' => 'Una partida no pertenece a la venta seleccionada.'], 422);
                }

                $disponible = $this->cantidadDisponibleDevolucion($detalle);
                if ($linea['cantidad'] > $disponible) {
                    return response()->json([
                        'message' => "La cantidad a devolver de {$detalle->producto_nombre} supera lo disponible.",
                    ], 422);
                }

                if ($detalle->serie_id && abs($linea['cantidad'] - 1) > 0.001) {
                    return response()->json(['message' => 'Una partida con serie/IMEI solo puede devolverse completa.'], 422);
                }

                if ($detalle->pedido_detalle_id && (float) $linea['cantidad'] < (float) $detalle->cantidad) {
                    return response()->json([
                        'message' => 'Las partidas de pedido o apartado deben devolverse completas.',
                    ], 422);
                }

                $total += round($linea['cantidad'] * (float) $detalle->precio_venta, 2);
            }

            $corte = $this->corteAbiertoPorTerminal((int) $user->empresa_id, (int) $user->sucursal_id, $terminal);
            $desglose = $this->desgloseDevolucion($venta, $total, $data['forma_devolucion']);

            if ($desglose['saldo'] > 0 && ! $venta->cliente_id) {
                return response()->json([
                    'message' => 'La devolucion a saldo requiere que la venta tenga cliente asignado.',
                ], 422);
            }

            if ($desglose['caja'] > 0 && ! $corte) {
                return response()->json([
                    'message' => 'No hay caja abierta para registrar la salida de dinero.',
                ], 422);
            }

            $devolucion = Devolucion::create([
                'empresa_id' => $venta->empresa_id,
                'sucursal_id' => $venta->sucursal_id,
                'venta_id' => $venta->id,
                'user_id' => $user->id,
                'corte_id' => $corte?->id,
                'folio' => FolioService::siguienteTicket((int) $venta->empresa_id, (int) $venta->sucursal_id, 'DEV'),
                'fecha' => now(),
                'forma_devolucion' => $data['forma_devolucion'],
                'total_devuelto' => $total,
                'regresa_inventario' => true,
                'motivo' => $data['motivo'],
                'estado' => 'confirmada',
            ]);

            foreach ($lineas as $linea) {
                $detalle = $detallesVenta[$linea['venta_detalle_id']];
                $importe = round($linea['cantidad'] * (float) $detalle->precio_venta, 2);

                $detalleDev = $devolucion->detalles()->create([
                    'venta_detalle_id' => $detalle->id,
                    'producto_id' => $detalle->producto_id,
                    'variante_id' => $detalle->variante_id,
                    'serie_id' => $detalle->serie_id,
                    'cantidad' => $linea['cantidad'],
                    'precio_unitario' => $detalle->precio_venta,
                    'importe' => $importe,
                ]);

                $this->regresarInventario($venta, $detalle, $linea['cantidad'], [
                    'tipo' => 'devolucion_cliente',
                    'user_id' => $user->id,
                    'motivo' => $data['motivo'],
                    'referencia_tipo' => 'devolucion_cliente',
                    'referencia_id' => $devolucion->id,
                    'referencia_detalle_id' => $detalleDev->id,
                    'folio' => $devolucion->folio,
                    'fecha' => $devolucion->created_at ?? now(),
                    'metadata' => [
                        'venta_id' => $venta->id,
                        'venta_folio' => $venta->folio,
                        'venta_detalle_id' => $detalle->id,
                    ],
                ]);
                if ($detalle->pedido_detalle_id) {
                    $this->marcarPedidoDetalleDevuelto($detalle);
                }
            }

            if ($corte && $desglose['caja'] > 0) {
                MovimientoCaja::create([
                    'corte_id' => $corte->id,
                    'user_id' => $user->id,
                    'tipo' => 'egreso',
                    'forma_pago' => $data['forma_devolucion'],
                    'monto' => $desglose['caja'],
                    'concepto' => "Devolucion {$devolucion->folio} de venta {$venta->folio}",
                ]);

                $corte->recalcularMovimientos();
            }

            if ($desglose['saldo'] > 0) {
                $this->registrarMovimientoSaldo(
                    $venta,
                    $desglose['saldo'],
                    'devolucion',
                    'saldo_favor',
                    "Devolucion {$devolucion->folio} de venta {$venta->folio}",
                    $corte?->id
                );
            }

            return response()->json([
                'message' => 'Devolucion registrada correctamente.',
                'devolucion' => $devolucion->load('detalles'),
                'venta' => $this->ventaParaProceso($venta->fresh()),
            ], 201);
        });
    }

    private function buscarVentaPorFolio(string $folio, int $empresaId, int $sucursalId, bool $lock = false): ?Venta
    {
        $query = Venta::where('empresa_id', $empresaId)
            ->where('sucursal_id', $sucursalId)
            ->where('folio', trim($folio))
            ->with([
                'corte',
                'user:id,name',
                'vendedor:id,name',
                'cliente:id,nombre,telefono',
                'detalles.producto:id,nombre,codigo',
                'detalles.variante.atributos.tipoAtributo:id,nombre',
                'detalles.variante.atributos.atributo:id,valor',
                'detalles.serie',
            ]);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function ventaParaProceso(Venta $venta): array
    {
        $venta->loadMissing([
            'user:id,name',
            'vendedor:id,name',
            'cliente:id,nombre,telefono',
            'detalles.producto:id,nombre,codigo',
            'detalles.variante.atributos.tipoAtributo:id,nombre',
            'detalles.variante.atributos.atributo:id,valor',
            'detalles.serie',
            'pagos.cuentaBancaria:id,nombre,banco',
            'pagos.terminalPago:id,nombre,banco',
        ]);

        $metodos = $venta->pagos->where('forma_pago', '!=', 'saldo_favor')->pluck('forma_pago');
        $formaPago = $metodos->isEmpty()
            ? ($venta->pagos->isNotEmpty() ? 'saldo_favor' : null)
            : ($metodos->count() > 1 ? 'mixto' : $metodos->first());

        return [
            'id' => $venta->id,
            'folio' => $venta->folio,
            'fecha' => $venta->fecha,
            'estado' => $venta->estado,
            'forma_pago' => $formaPago,
            'pagos' => $venta->pagos,
            'subtotal' => (float) $venta->subtotal,
            'descuento' => (float) $venta->descuento,
            'saldo_aplicado' => (float) ($venta->saldo_aplicado ?? 0),
            'total' => (float) $venta->total,
            'motivo_cancelacion' => $venta->motivo_cancelacion,
            'cancelado_en' => $venta->cancelado_en,
            'usuario' => $venta->user,
            'vendedor' => $venta->vendedor,
            'cliente' => $venta->cliente,
            'tiene_pedido' => $venta->detalles->contains(fn(VentaDetalle $detalle) => ! empty($detalle->pedido_detalle_id)),
            'detalles' => $venta->detalles->map(function (VentaDetalle $detalle) {
                return [
                    'id' => $detalle->id,
                    'pedido_id' => $detalle->pedido_id,
                    'pedido_detalle_id' => $detalle->pedido_detalle_id,
                    'producto_id' => $detalle->producto_id,
                    'variante_id' => $detalle->variante_id,
                    'serie_id' => $detalle->serie_id,
                    'producto_nombre' => $detalle->producto_nombre ?? $detalle->producto?->nombre,
                    'producto_codigo' => $detalle->producto?->codigo,
                    'variante_nombre' => $detalle->variante_nombre ?? ($detalle->variante?->nombreVariante() ?: null),
                    'serie' => $detalle->serie?->identificador,
                    'cantidad' => (float) $detalle->cantidad,
                    'cantidad_devuelta' => $this->cantidadDevuelta($detalle),
                    'cantidad_disponible_devolucion' => $this->cantidadDisponibleDevolucion($detalle),
                    'precio_venta' => (float) $detalle->precio_venta,
                    'subtotal' => (float) $detalle->subtotal,
                ];
            })->values(),
        ];
    }

    private function cantidadDevuelta(VentaDetalle $detalle): float
    {
        return (float) DB::table('devolucion_detalles as dd')
            ->join('devoluciones as d', 'd.id', '=', 'dd.devolucion_id')
            ->where('dd.venta_detalle_id', $detalle->id)
            ->where('d.estado', 'confirmada')
            ->sum('dd.cantidad');
    }

    private function cantidadDisponibleDevolucion(VentaDetalle $detalle): float
    {
        return max(0, (float) $detalle->cantidad - $this->cantidadDevuelta($detalle));
    }

    private function regresarInventario(Venta $venta, VentaDetalle $detalle, float $cantidad, array $kardex = []): void
    {
        Inventario::firstOrCreate(
            [
                'empresa_id' => $venta->empresa_id,
                'sucursal_id' => $venta->sucursal_id,
                'producto_id' => $detalle->producto_id,
                'variante_id' => $detalle->variante_id,
            ],
            ['stock' => 0, 'stock_minimo' => 0]
        );

        $inventario = Inventario::where([
            'empresa_id' => $venta->empresa_id,
            'sucursal_id' => $venta->sucursal_id,
            'producto_id' => $detalle->producto_id,
            'variante_id' => $detalle->variante_id,
        ])->lockForUpdate()->firstOrFail();

        $stockAntes = (float) $inventario->stock;
        $stockDespues = $stockAntes + $cantidad;
        $inventario->stock = $stockDespues;
        $inventario->save();

        app(KardexServicio::class)->registrar(array_merge([
            'empresa_id' => $venta->empresa_id,
            'sucursal_id' => $venta->sucursal_id,
            'producto_id' => $detalle->producto_id,
            'variante_id' => $detalle->variante_id,
            'serie_id' => $detalle->serie_id,
            'tipo' => 'devolucion_cliente',
            'direccion' => 'entrada',
            'cantidad' => $cantidad,
            'stock_antes' => $stockAntes,
            'stock_despues' => $stockDespues,
            'costo_unitario' => $detalle->precio_costo !== null ? (float) $detalle->precio_costo : null,
            'precio_unitario' => (float) $detalle->precio_venta,
            'importe' => round($cantidad * (float) $detalle->precio_venta, 2),
            'referencia_tipo' => 'venta',
            'referencia_id' => $venta->id,
            'referencia_detalle_id' => $detalle->id,
            'folio' => $venta->folio,
            'fecha' => now(),
        ], $kardex));

        if ($detalle->serie_id) {
            Serie::where('id', $detalle->serie_id)->update([
                'estado' => 'disponible',
                'venta_id' => null,
                'venta_detalle_id' => null,
            ]);
        }
    }

    private function revertirPedidoDetalleVenta(VentaDetalle $ventaDetalle): void
    {
        $detalle = PedidoDetalle::where('id', $ventaDetalle->pedido_detalle_id)
            ->with('pedido')
            ->lockForUpdate()
            ->first();

        if (! $detalle || ! $detalle->pedido) {
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

        $pedido = $detalle->pedido;
        $pedido->actualizarEstadoPorDetalles();
        $pedido->update([
            'estado_pago' => $this->estadoPagoPedido((float) $pedido->subtotal, (float) $pedido->anticipo),
            'saldo_pendiente' => max(0, (float) $pedido->subtotal - (float) $pedido->anticipo),
        ]);
    }

    private function marcarPedidoDetalleDevuelto(VentaDetalle $ventaDetalle): void
    {
        $detalle = PedidoDetalle::where('id', $ventaDetalle->pedido_detalle_id)
            ->with('pedido')
            ->lockForUpdate()
            ->first();

        if (! $detalle || ! $detalle->pedido) {
            return;
        }

        $detalle->update(['estado' => 'devuelto']);

        InventarioReserva::where('pedido_detalle_id', $detalle->id)
            ->where('estado', 'activa')
            ->update(['estado' => 'liberada']);

        $detalle->pedido->actualizarEstadoPorDetalles();
    }

    private function desgloseDevolucion(Venta $venta, float $total, string $formaDevolucion): array
    {
        $total = round(max(0, $total), 2);

        if ($formaDevolucion === 'saldo_favor') {
            return ['caja' => 0.0, 'saldo' => $total];
        }

        $pagadoEnCaja = $this->montoCobradoEnFormaPago($venta);
        $cajaDevueltaPrevia = min(
            (float) $venta->devoluciones()
                ->where('estado', 'confirmada')
                ->where('forma_devolucion', '!=', 'saldo_favor')
                ->sum('total_devuelto'),
            $pagadoEnCaja
        );
        $cajaDisponible = max(0, $pagadoEnCaja - $cajaDevueltaPrevia);
        $caja = min($total, $cajaDisponible);

        return [
            'caja' => round($caja, 2),
            'saldo' => round(max(0, $total - $caja), 2),
        ];
    }

    private function desgloseCancelacion(Venta $venta, string $formaDevolucion, ?int $corteActualId): array
    {
        $total = round(max(0, (float) $venta->total), 2);
        $saldoAplicado = round(max(0, (float) ($venta->saldo_aplicado ?? 0)), 2);
        $mismoCorte = $corteActualId !== null && (int) $venta->corte_id === $corteActualId;

        if ($formaDevolucion === 'saldo_favor') {
            // recalcularVentas() resta esta venta de ventas_efectivo/tarjeta/transferencia
            // en su corte original. Si ese corte es el que sigue abierto ahora mismo, hay que
            // compensar esa resta ahi con un ingreso por cada linea original (el dinero se
            // queda en la caja, no se le entrega al cliente). Si la venta es de un corte
            // distinto (ya cerrado), la resta solo afecta al historico de ese corte y no debe
            // tocarse la caja de hoy, porque hoy no entro ni salio dinero.
            $lineas = $mismoCorte
                ? $venta->pagos()->whereIn('forma_pago', ['efectivo', 'tarjeta', 'transferencia'])->get()
                : collect();

            return [
                'ingreso_lineas' => $lineas,
                'egreso_monto' => 0.0,
                'egreso_forma_pago' => null,
                'saldo' => $total,
            ];
        }

        // Se le entrega dinero al cliente (efectivo/tarjeta/transferencia). Si la venta es
        // del mismo corte que sigue abierto, la resta automatica de ventas_X en ese mismo
        // corte ya refleja esa salida y no hace falta otro movimiento (se duplicaria el
        // efecto). Si la venta es de un corte distinto (ya cerrado), ese dinero sale
        // fisicamente de la caja de HOY y hay que registrar el egreso en el corte actual.
        $pagadoEnCaja = $this->montoCobradoEnFormaPago($venta);

        return [
            'ingreso_lineas' => collect(),
            'egreso_monto' => $mismoCorte ? 0.0 : $pagadoEnCaja,
            'egreso_forma_pago' => $formaDevolucion,
            'saldo' => $saldoAplicado,
        ];
    }

    private function montoCobradoEnFormaPago(Venta $venta): float
    {
        return round((float) $venta->pagos()->whereIn('forma_pago', ['efectivo', 'tarjeta', 'transferencia'])->sum('monto'), 2);
    }

    private function registrarMovimientoSaldo(
        Venta $venta,
        float $monto,
        string $tipo,
        ?string $formaPago,
        string $concepto,
        ?int $corteId
    ): void {
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
            'corte_id' => $corteId,
            'user_id' => Auth::id(),
            'tipo' => $tipo,
            'forma_pago' => $formaPago,
            'monto' => round($monto, 2),
            'saldo_resultante' => round($saldoAnterior + $monto, 2),
            'concepto' => $concepto,
        ]);
    }

    private function saldoDisponibleCliente(int $empresaId, int $sucursalId, int $clienteId): float
    {
        return round((float) ClienteSaldoMovimiento::where('empresa_id', $empresaId)
            ->where('sucursal_id', $sucursalId)
            ->where('cliente_id', $clienteId)
            ->sum(DB::raw("CASE WHEN tipo IN ('abono','devolucion','ajuste') THEN monto ELSE -monto END")), 2);
    }

    private function estadoPagoPedido(float $subtotal, float $anticipo): string
    {
        if ($anticipo <= 0) return 'sin_anticipo';
        if ($anticipo >= $subtotal && $subtotal > 0) return 'pagado';
        return 'con_anticipo';
    }

    private function corteAbiertoPorTerminal(int $empresaId, int $sucursalId, string $terminal): ?CorteCaja
    {
        return CorteCaja::where('empresa_id', $empresaId)
            ->where('sucursal_id', $sucursalId)
            ->where('terminal', $terminal)
            ->where('estado', 'abierto')
            ->latest('fecha_apertura')
            ->first();
    }
}
