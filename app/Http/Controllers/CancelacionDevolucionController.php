<?php

namespace App\Http\Controllers;

use App\Models\CorteCaja;
use App\Models\ClienteSaldoMovimiento;
use App\Models\Devolucion;
use App\Models\Inventario;
use App\Models\InventarioReserva;
use App\Models\MovimientoCaja;
use App\Models\PedidoDetalle;
use App\Models\Serie;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Services\FolioService;
use App\Support\TerminalResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

    public function cancelar(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('ventas.cancelar'), 403, 'Sin permiso: ventas.cancelar');
        $data = $request->validate([
            'folio' => ['required', 'string', 'max:100'],
            'motivo' => ['required', 'string', 'max:255'],
            'tipo_proceso' => ['nullable', 'in:anulacion,devolucion'],
            'destino_pedido' => ['nullable', 'in:disponible,devuelto'],
            'forma_devolucion' => ['nullable', 'in:efectivo,tarjeta,transferencia,credito'],
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

            $tipoProceso = $data['tipo_proceso'] ?? 'anulacion';
            $destinoPedido = $data['destino_pedido'] ?? ($tipoProceso === 'devolucion' ? 'devuelto' : 'disponible');
            $formaDevolucion = $data['forma_devolucion'] ?? 'efectivo';
            $desglose = $this->desgloseCancelacion($venta, $formaDevolucion);

            if ($desglose['saldo'] > 0 && ! $venta->cliente_id) {
                return response()->json([
                    'message' => 'La devolucion a saldo requiere que la venta tenga cliente asignado.',
                ], 422);
            }

            $corteDevolucion = $this->corteAbiertoPorTerminal((int) $user->empresa_id, (int) $user->sucursal_id, $terminal);
            if ($desglose['movimiento'] > 0 && ! $corteDevolucion) {
                return response()->json([
                    'message' => 'No hay caja abierta para registrar el movimiento de dinero.',
                ], 422);
            }

            foreach ($venta->detalles as $detalle) {
                $this->regresarInventario($venta, $detalle, (float) $detalle->cantidad);
                if ($detalle->pedido_detalle_id) {
                    $destinoPedido === 'devuelto'
                        ? $this->marcarPedidoDetalleDevuelto($detalle)
                        : $this->revertirPedidoDetalleVenta($detalle);
                }
            }

            if ($corteDevolucion && $desglose['movimiento'] > 0) {
                MovimientoCaja::create([
                    'corte_id' => $corteDevolucion->id,
                    'user_id' => $user->id,
                    'tipo' => $desglose['movimiento_tipo'],
                    'forma_pago' => $desglose['movimiento_forma'],
                    'monto' => $desglose['movimiento'],
                    'concepto' => $formaDevolucion === 'credito'
                        ? "Saldo a favor por cancelacion {$venta->folio}"
                        : "Cancelacion venta {$venta->folio}",
                ]);

                $corteDevolucion->recalcularMovimientos();
            }

            if ($desglose['saldo'] > 0) {
                $this->registrarMovimientoSaldo(
                    $venta,
                    $desglose['saldo'],
                    $formaDevolucion === 'credito' ? 'devolucion' : 'ajuste',
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
            'forma_devolucion' => ['required', 'in:efectivo,tarjeta,transferencia,credito'],
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

                $devolucion->detalles()->create([
                    'venta_detalle_id' => $detalle->id,
                    'producto_id' => $detalle->producto_id,
                    'variante_id' => $detalle->variante_id,
                    'serie_id' => $detalle->serie_id,
                    'cantidad' => $linea['cantidad'],
                    'precio_unitario' => $detalle->precio_venta,
                    'importe' => $importe,
                ]);

                $this->regresarInventario($venta, $detalle, $linea['cantidad']);
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
        ]);

        return [
            'id' => $venta->id,
            'folio' => $venta->folio,
            'fecha' => $venta->fecha,
            'estado' => $venta->estado,
            'forma_pago' => $venta->forma_pago,
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

    private function regresarInventario(Venta $venta, VentaDetalle $detalle, float $cantidad): void
    {
        $inventario = Inventario::firstOrCreate(
            [
                'empresa_id' => $venta->empresa_id,
                'sucursal_id' => $venta->sucursal_id,
                'producto_id' => $detalle->producto_id,
                'variante_id' => $detalle->variante_id,
            ],
            ['stock' => 0, 'stock_minimo' => 0]
        );

        $inventario->increment('stock', $cantidad);

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
        $entregados = $pedido->detalles()
            ->where('estado', 'entregado')
            ->exists();

        $pedido->update([
            'estado' => $entregados ? 'parcial' : 'disponible',
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

        $this->actualizarEstadoPedidoPorDetalles($detalle->pedido);
    }

    private function actualizarEstadoPedidoPorDetalles($pedido): void
    {
        $estados = $pedido->detalles()
            ->pluck('estado')
            ->all();

        $activos = array_values(array_filter($estados, fn($estado) => $estado !== 'cancelado'));

        if ($activos === []) {
            $pedido->update(['estado' => 'cancelado']);
            return;
        }

        if (count(array_unique($activos)) === 1 && $activos[0] === 'devuelto') {
            $pedido->update(['estado' => 'devuelto']);
            return;
        }

        if (in_array('entregado', $activos, true) || in_array('devuelto', $activos, true)) {
            $pedido->update(['estado' => 'parcial']);
            return;
        }

        if (in_array('disponible', $activos, true) || in_array('reservado', $activos, true)) {
            $pedido->update(['estado' => 'disponible']);
            return;
        }

        $pedido->update(['estado' => 'pendiente']);
    }

    private function desgloseDevolucion(Venta $venta, float $total, string $formaDevolucion): array
    {
        $total = round(max(0, $total), 2);

        if ($formaDevolucion === 'credito') {
            return ['caja' => 0.0, 'saldo' => $total];
        }

        $pagadoEnCaja = $this->montoCobradoEnFormaPago($venta);
        $cajaDevueltaPrevia = 0.0;
        $venta->devoluciones()
            ->where('estado', 'confirmada')
            ->orderBy('id')
            ->get(['forma_devolucion', 'total_devuelto'])
            ->each(function ($devolucion) use (&$cajaDevueltaPrevia, $pagadoEnCaja) {
                if ($devolucion->forma_devolucion === 'credito') {
                    return;
                }

                $cajaDisponible = max(0, $pagadoEnCaja - $cajaDevueltaPrevia);
                $cajaDevueltaPrevia += min((float) $devolucion->total_devuelto, $cajaDisponible);
            });

        $cajaDisponible = max(0, $pagadoEnCaja - $cajaDevueltaPrevia);
        $caja = min($total, $cajaDisponible);

        return [
            'caja' => round($caja, 2),
            'saldo' => round(max(0, $total - $caja), 2),
        ];
    }

    private function desgloseCancelacion(Venta $venta, string $formaDevolucion): array
    {
        $total = round(max(0, (float) $venta->total), 2);
        $saldoAplicado = round(max(0, (float) ($venta->saldo_aplicado ?? 0)), 2);
        $pagadoEnCaja = $this->montoCobradoEnFormaPago($venta);

        if ($formaDevolucion === 'credito') {
            return [
                'movimiento' => $pagadoEnCaja,
                'movimiento_tipo' => 'ingreso',
                'movimiento_forma' => $this->formaMovimientoVenta($venta),
                'saldo' => $total,
            ];
        }

        return [
            'movimiento' => 0.0,
            'movimiento_tipo' => null,
            'movimiento_forma' => null,
            'saldo' => $saldoAplicado,
        ];
    }

    private function montoCobradoEnFormaPago(Venta $venta): float
    {
        if (! in_array($venta->forma_pago, ['efectivo', 'tarjeta', 'transferencia'], true)) {
            return 0.0;
        }

        return round(max(0, (float) $venta->total - (float) ($venta->saldo_aplicado ?? 0)), 2);
    }

    private function formaMovimientoVenta(Venta $venta): ?string
    {
        return in_array($venta->forma_pago, ['efectivo', 'tarjeta', 'transferencia'], true)
            ? $venta->forma_pago
            : null;
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
