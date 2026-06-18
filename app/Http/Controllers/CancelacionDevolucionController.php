<?php

namespace App\Http\Controllers;

use App\Models\CorteCaja;
use App\Models\Devolucion;
use App\Models\Inventario;
use App\Models\MovimientoCaja;
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
        ]);

        $user = $request->user();

        return DB::transaction(function () use ($data, $user) {
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

            foreach ($venta->detalles as $detalle) {
                $this->regresarInventario($venta, $detalle, (float) $detalle->cantidad);
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

                $total += round($linea['cantidad'] * (float) $detalle->precio_venta, 2);
            }

            $corte = $this->corteAbiertoPorTerminal((int) $user->empresa_id, (int) $user->sucursal_id, $terminal);

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
            }

            if ($corte && $data['forma_devolucion'] !== 'credito' && $total > 0) {
                MovimientoCaja::create([
                    'corte_id' => $corte->id,
                    'user_id' => $user->id,
                    'tipo' => 'egreso',
                    'forma_pago' => $data['forma_devolucion'],
                    'monto' => $total,
                    'concepto' => "Devolucion {$devolucion->folio} de venta {$venta->folio}",
                ]);

                $corte->recalcularMovimientos();
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
            'total' => (float) $venta->total,
            'motivo_cancelacion' => $venta->motivo_cancelacion,
            'cancelado_en' => $venta->cancelado_en,
            'usuario' => $venta->user,
            'vendedor' => $venta->vendedor,
            'cliente' => $venta->cliente,
            'detalles' => $venta->detalles->map(function (VentaDetalle $detalle) {
                return [
                    'id' => $detalle->id,
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
