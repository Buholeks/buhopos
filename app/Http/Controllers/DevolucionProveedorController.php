<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\CorteCaja;
use App\Models\DevolucionProveedor;
use App\Models\Inventario;
use App\Models\InventarioReserva;
use App\Models\MovimientoCaja;
use App\Models\ProveedorSaldoMovimiento;
use App\Models\Serie;
use App\Support\TerminalResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DevolucionProveedorController extends Controller
{
    public function buscarCompras(Request $request): JsonResponse
    {
        $user = $request->user();
        $texto = trim((string) $request->query('q', ''));

        return response()->json(
            Compra::where('empresa_id', $user->empresa_id)
                ->where('sucursal_id', $user->sucursal_id)
                ->whereIn('estado', ['confirmada', 'devuelta_parcial', 'devuelta', 'cancelada'])
                ->with('proveedor:id,nombre_comercial')
                ->when($texto !== '', fn($q) => $q->where(fn($sub) => $sub
                    ->where('folio', 'like', "%{$texto}%")
                    ->orWhereHas('proveedor', fn($p) => $p
                        ->where('nombre_comercial', 'like', "%{$texto}%"))))
                ->orderByDesc('fecha')
                ->limit(20)
                ->get(['id', 'proveedor_id', 'folio', 'fecha', 'total', 'saldo', 'estado'])
        );
    }

    public function show(Request $request, int $compraId): JsonResponse
    {
        $user = $request->user();
        $compra = Compra::where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->whereIn('estado', ['confirmada', 'devuelta_parcial', 'devuelta', 'cancelada'])
            ->with(['proveedor:id,nombre_comercial', 'detalles.producto:id,nombre,codigo,tiene_series', 'detalles.variante:id,sku'])
            ->findOrFail($compraId);

        $devuelto = DB::table('devolucion_proveedor_detalles as dd')
            ->join('devoluciones_proveedor as d', 'd.id', '=', 'dd.devolucion_proveedor_id')
            ->where('d.compra_id', $compra->id)
            ->where('d.estado', 'confirmada')
            ->groupBy('dd.compra_detalle_id')
            ->selectRaw('dd.compra_detalle_id, SUM(dd.cantidad) AS cantidad_devuelta')
            ->pluck('cantidad_devuelta', 'dd.compra_detalle_id');

        $compra->detalles->each(function ($detalle) use ($compra, $devuelto) {
            $detalle->cantidad_devuelta = (float) ($devuelto[$detalle->id] ?? 0);
            $detalle->cantidad_devolvible = max(0, (float) $detalle->cantidad - $detalle->cantidad_devuelta);
            $detalle->series_disponibles = $detalle->producto->tiene_series
                ? Serie::where('compra_id', $compra->id)
                    ->where('producto_id', $detalle->producto_id)
                    ->where('variante_id', $detalle->variante_id)
                    ->where('estado', 'disponible')
                    ->get(['id', 'imei', 'imei2', 'serie'])
                : [];
        });

        return response()->json($compra);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $terminal = TerminalResolver::fromRequest($request);
        $data = $request->validate([
            'compra_id' => ['required', 'integer'],
            'fecha' => ['required', 'date'],
            'referencia' => ['nullable', 'string', 'max:100'],
            'motivo' => ['required', 'string', 'min:3'],
            'destino_excedente' => ['nullable', 'in:saldo_favor,caja'],
            'forma_reembolso' => ['nullable', 'in:efectivo,transferencia'],
            'cancelacion' => ['nullable', 'boolean'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.compra_detalle_id' => ['required', 'integer'],
            'detalles.*.cantidad' => ['nullable', 'numeric', 'min:0'],
            'detalles.*.serie_ids' => ['nullable', 'array'],
            'detalles.*.serie_ids.*' => ['integer'],
        ]);

        $devolucion = DB::transaction(function () use ($user, $data, $terminal) {
            $compra = Compra::where('empresa_id', $user->empresa_id)
                ->where('sucursal_id', $user->sucursal_id)
                ->whereIn('estado', ['confirmada', 'devuelta_parcial'])
                ->with('detalles.producto')
                ->lockForUpdate()
                ->findOrFail($data['compra_id']);

            $lineas = [];
            $total = 0;

            foreach ($data['detalles'] as $entrada) {
                $detalle = $compra->detalles->firstWhere('id', (int) $entrada['compra_detalle_id']);
                abort_if(! $detalle, 422, 'Una partida no pertenece a la compra.');

                $serieIds = array_values(array_unique($entrada['serie_ids'] ?? []));
                $cantidad = $detalle->producto->tiene_series
                    ? count($serieIds)
                    : (float) ($entrada['cantidad'] ?? 0);
                if ($cantidad <= 0) continue;

                $yaDevuelto = (float) DB::table('devolucion_proveedor_detalles as dd')
                    ->join('devoluciones_proveedor as d', 'd.id', '=', 'dd.devolucion_proveedor_id')
                    ->where('d.compra_id', $compra->id)
                    ->where('d.estado', 'confirmada')
                    ->where('dd.compra_detalle_id', $detalle->id)
                    ->sum('dd.cantidad');
                abort_if($cantidad > (float) $detalle->cantidad - $yaDevuelto, 422, 'La cantidad excede lo comprado pendiente de devolución.');

                $inventario = Inventario::where([
                    'empresa_id' => $user->empresa_id,
                    'sucursal_id' => $compra->sucursal_id,
                    'producto_id' => $detalle->producto_id,
                    'variante_id' => $detalle->variante_id,
                ])->lockForUpdate()->first();
                abort_if(! $inventario, 422, 'No se encontró inventario para una partida.');

                $reservado = (float) InventarioReserva::where([
                    'empresa_id' => $user->empresa_id,
                    'sucursal_id' => $compra->sucursal_id,
                    'producto_id' => $detalle->producto_id,
                    'variante_id' => $detalle->variante_id,
                    'estado' => 'activa',
                ])->sum('cantidad');
                abort_if($cantidad > (float) $inventario->stock - $reservado, 422, 'No hay stock libre suficiente; parte está vendida o reservada.');

                $series = collect();
                if ($detalle->producto->tiene_series) {
                    $series = Serie::whereIn('id', $serieIds)
                        ->where('compra_id', $compra->id)
                        ->where('producto_id', $detalle->producto_id)
                        ->where('variante_id', $detalle->variante_id)
                        ->where('estado', 'disponible')
                        ->lockForUpdate()
                        ->get();
                    abort_if($series->count() !== count($serieIds), 422, 'Alguna serie no está disponible o no pertenece a esta compra.');
                }

                $subtotal = round($cantidad * (float) $detalle->precio_compra, 2);
                $lineas[] = compact('detalle', 'cantidad', 'subtotal', 'inventario', 'series');
                $total += $subtotal;
            }

            abort_if(empty($lineas), 422, 'Selecciona al menos una cantidad para devolver.');
            $aplicadoSaldo = min((float) $compra->saldo, $total);
            $excedente = round($total - $aplicadoSaldo, 2);
            abort_if($excedente > 0 && empty($data['destino_excedente']), 422, 'Selecciona dónde recibir el dinero de la devolución.');
            abort_if($excedente > 0 && $data['destino_excedente'] === 'caja' && empty($data['forma_reembolso']), 422, 'Selecciona la forma de ingreso a caja.');

            $devolucion = DevolucionProveedor::create([
                'empresa_id' => $user->empresa_id,
                'sucursal_id' => $compra->sucursal_id,
                'compra_id' => $compra->id,
                'proveedor_id' => $compra->proveedor_id,
                'user_id' => $user->id,
                'fecha' => $data['fecha'],
                'referencia' => $data['referencia'] ?? null,
                'motivo' => $data['motivo'],
                'total' => $total,
                'aplicado_saldo' => $aplicadoSaldo,
                'reembolso_pendiente' => $excedente,
                'destino_excedente' => $excedente > 0 ? $data['destino_excedente'] : null,
                'forma_reembolso' => $excedente > 0 && $data['destino_excedente'] === 'caja'
                    ? $data['forma_reembolso']
                    : null,
            ]);

            foreach ($lineas as $linea) {
                $detalleDev = $devolucion->detalles()->create([
                    'compra_detalle_id' => $linea['detalle']->id,
                    'producto_id' => $linea['detalle']->producto_id,
                    'variante_id' => $linea['detalle']->variante_id,
                    'cantidad' => $linea['cantidad'],
                    'precio_compra' => $linea['detalle']->precio_compra,
                    'subtotal' => $linea['subtotal'],
                ]);
                $linea['inventario']->decrement('stock', $linea['cantidad']);
                foreach ($linea['series'] as $serie) {
                    $serie->update(['estado' => 'devuelto']);
                    $detalleDev->series()->attach($serie->id);
                }
            }

            DB::table('compras')->where('id', $compra->id)->update([
                'saldo' => max(0, (float) $compra->saldo - $aplicadoSaldo),
                'estado' => $data['cancelacion'] ?? false
                    ? 'cancelada'
                    : $this->estadoCompraDespuesDeDevolucion($compra->id),
                'updated_at' => now(),
            ]);

            if ($excedente > 0 && $data['destino_excedente'] === 'saldo_favor') {
                $saldoActual = $this->saldoFavorProveedor($user->empresa_id, $compra->sucursal_id, $compra->proveedor_id);
                ProveedorSaldoMovimiento::create([
                    'empresa_id' => $user->empresa_id,
                    'sucursal_id' => $compra->sucursal_id,
                    'proveedor_id' => $compra->proveedor_id,
                    'user_id' => $user->id,
                    'devolucion_proveedor_id' => $devolucion->id,
                    'tipo' => 'credito',
                    'monto' => $excedente,
                    'saldo_resultante' => $saldoActual + $excedente,
                    'concepto' => "Devolución de compra {$compra->folio}",
                ]);
            }

            if ($excedente > 0 && $data['destino_excedente'] === 'caja') {
                $corte = CorteCaja::where('empresa_id', $user->empresa_id)
                    ->where('sucursal_id', $compra->sucursal_id)
                    ->where('terminal', $terminal)
                    ->where('estado', 'abierto')
                    ->lockForUpdate()
                    ->first();
                abort_if(! $corte, 422, 'No hay un corte abierto para registrar el ingreso.');
                $movimiento = MovimientoCaja::create([
                    'corte_id' => $corte->id,
                    'user_id' => $user->id,
                    'tipo' => 'ingreso',
                    'forma_pago' => $data['forma_reembolso'],
                    'monto' => $excedente,
                    'concepto' => "Reembolso proveedor compra {$compra->folio}",
                ]);
                $devolucion->update(['movimiento_caja_id' => $movimiento->id]);
                $corte->recalcularMovimientos();
            }

            return $devolucion;
        });

        return response()->json($devolucion->load('detalles'), 201);
    }

    public function cancelar(Request $request, int $compraId): JsonResponse
    {
        $user = $request->user();
        $compra = Compra::where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->where('estado', 'confirmada')
            ->with('detalles.producto')
            ->findOrFail($compraId);

        $detalles = $compra->detalles->map(function ($detalle) use ($compra) {
            $serieIds = $detalle->producto->tiene_series
                ? Serie::where('compra_id', $compra->id)
                    ->where('producto_id', $detalle->producto_id)
                    ->where('variante_id', $detalle->variante_id)
                    ->where('estado', 'disponible')
                    ->pluck('id')
                    ->all()
                : [];
            abort_if(
                $detalle->producto->tiene_series && count($serieIds) !== (int) $detalle->cantidad,
                422,
                'No se puede cancelar: alguna serie ya fue vendida, apartada o devuelta.'
            );

            return [
                'compra_detalle_id' => $detalle->id,
                'cantidad' => $detalle->producto->tiene_series ? 0 : (float) $detalle->cantidad,
                'serie_ids' => $serieIds,
            ];
        })->all();

        $request->merge([
            'compra_id' => $compra->id,
            'fecha' => $request->input('fecha', now()->toDateString()),
            'motivo' => $request->input('motivo', 'Cancelación total de compra'),
            'cancelacion' => true,
            'detalles' => $detalles,
        ]);

        return $this->store($request);
    }

    private function estadoCompraDespuesDeDevolucion(int $compraId): string
    {
        $comprado = (float) DB::table('compra_detalles')->where('compra_id', $compraId)->sum('cantidad');
        $devuelto = (float) DB::table('devolucion_proveedor_detalles as dd')
            ->join('devoluciones_proveedor as d', 'd.id', '=', 'dd.devolucion_proveedor_id')
            ->where('d.compra_id', $compraId)
            ->where('d.estado', 'confirmada')
            ->sum('dd.cantidad');

        return $devuelto >= $comprado ? 'devuelta' : 'devuelta_parcial';
    }

    private function saldoFavorProveedor(int $empresaId, int $sucursalId, int $proveedorId): float
    {
        return (float) ProveedorSaldoMovimiento::where([
            'empresa_id' => $empresaId,
            'sucursal_id' => $sucursalId,
            'proveedor_id' => $proveedorId,
        ])->selectRaw("COALESCE(SUM(CASE WHEN tipo='credito' THEN monto ELSE -monto END), 0) AS saldo")
            ->value('saldo');
    }
}
