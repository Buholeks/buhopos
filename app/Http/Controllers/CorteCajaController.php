<?php

namespace App\Http\Controllers;

use App\Models\CorteCaja;
use App\Models\CorteDesgloseEfectivo;
use App\Models\MovimientoCaja;
use App\Models\Venta;
use App\Support\TerminalResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CorteCajaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('caja.historial'), 403, 'Sin permiso: caja.historial');
        $user      = Auth::user();
        $sucursalID = (int) $user->sucursal_id;

        $cortes = CorteCaja::where('sucursal_id', $sucursalID)
            ->with(['user:id,name'])
            ->when($request->user_id, fn($q, $v) => $q->where('user_id', $v))
            ->when($request->estado,  fn($q, $v) => $q->where('estado', $v))
            ->orderByDesc('fecha_apertura')
            ->paginate($request->por_pagina ?? 20);

        return response()->json($cortes);
    }

    public function abiertas(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('caja.abrir'), 403, 'Sin permiso: caja.abrir');
        $user = Auth::user();

        $cortes = CorteCaja::where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->where('estado', 'abierto')
            ->with(['user:id,name'])
            ->orderBy('terminal')
            ->get([
                'id',
                'empresa_id',
                'sucursal_id',
                'user_id',
                'terminal',
                'fecha_apertura',
                'fondo_inicial_efectivo',
                'esperado_efectivo',
                'ventas_efectivo',
                'ventas_tarjeta',
                'ventas_transferencia',
                'ventas_saldo_favor',
                'movs_efectivo',
                'num_ventas',
            ]);

        return response()->json([
            'terminal_actual' => TerminalResolver::fromRequest($request),
            'data' => $cortes,
        ]);
    }

    public function actual(Request $request): JsonResponse
    {
        $user = Auth::user();
        $terminal = TerminalResolver::fromRequest($request);

        $corte = CorteCaja::where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->where('terminal', $terminal)
            ->where('estado', 'abierto')
            ->with(['desglose', 'movimientos.user:id,name'])
            ->first();

        if ($corte) {
            $corte->recalcularVentas();
            $corte->recalcularMovimientos();
        }

        return response()->json($corte);
    }

    public function abrir(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('caja.abrir'), 403, 'Sin permiso: caja.abrir');
        $user = Auth::user();
        $terminal = TerminalResolver::fromRequest($request);
        $datos = $request->validate([
            'fondo_inicial_efectivo' => ['nullable', 'numeric', 'min:0'],
            'notas_apertura' => ['nullable', 'string'],
        ]);

        $corte = DB::transaction(function () use ($user, $terminal, $datos) {
            $abierto = CorteCaja::where('empresa_id', $user->empresa_id)
                ->where('sucursal_id', $user->sucursal_id)
                ->where('terminal', $terminal)
                ->where('estado', 'abierto')
                ->lockForUpdate()
                ->exists();

            if ($abierto) {
                abort(422, 'Ya hay una caja abierta en esta terminal.');
            }

            return CorteCaja::create([
                'empresa_id'     => (int) $user->empresa_id,
                'sucursal_id'    => (int) $user->sucursal_id,
                'user_id'        => (int) $user->id,
                'terminal'       => $terminal,
                'fecha_apertura' => now(),
                'estado'         => 'abierto',
                'fondo_inicial_efectivo' => (float) ($datos['fondo_inicial_efectivo'] ?? 0),
                'notas_apertura' => $datos['notas_apertura'] ?? null,
            ]);
        });

        return response()->json($corte, 201);
    }

    public function agregarMovimiento(Request $request, int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('caja.abrir'), 403, 'Sin permiso: caja.abrir');
        $user = Auth::user();
        $terminal = TerminalResolver::fromRequest($request);

        $corte = CorteCaja::where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->where('terminal', $terminal)
            ->where('id', $id)
            ->where('estado', 'abierto')
            ->firstOrFail();

        $datos = $request->validate([
            'tipo'       => ['required', 'in:ingreso,egreso'],
            'forma_pago' => ['required', 'in:efectivo,tarjeta,transferencia'],
            'monto'      => ['required', 'numeric', 'min:0.01'],
            'concepto'   => ['required', 'string', 'max:255'],
        ]);

        $mov = MovimientoCaja::create([
            'corte_id'   => $corte->id,
            'user_id'    => $user->id,
            'tipo'       => $datos['tipo'],
            'forma_pago' => $datos['forma_pago'],
            'monto'      => $datos['monto'],
            'concepto'   => $datos['concepto'],
        ]);

        $corte->recalcularMovimientos();

        return response()->json($mov->load('user:id,name'), 201);
    }

public function cerrar(Request $request, int $id): JsonResponse
{
    abort_unless(Auth::user()->tienePermiso('caja.cerrar'), 403, 'Sin permiso: caja.cerrar');
    $user     = Auth::user();
    $terminal = TerminalResolver::fromRequest($request);

    // Validar antes de abrir la transacción para devolver 422 sin overhead de BD
    $datos = $request->validate([
        'modo'             => ['required', 'in:arqueo,manual'],
        'contado_efectivo' => ['nullable', 'numeric', 'min:0', 'required_if:modo,manual'],

        'contado_tarjeta'       => ['nullable', 'numeric', 'min:0'],
        'contado_transferencia' => ['nullable', 'numeric', 'min:0'],
        'notas_cierre'          => ['nullable', 'string'],

        'billetes_1000' => ['nullable', 'integer', 'min:0'],
        'billetes_500'  => ['nullable', 'integer', 'min:0'],
        'billetes_200'  => ['nullable', 'integer', 'min:0'],
        'billetes_100'  => ['nullable', 'integer', 'min:0'],
        'billetes_50'   => ['nullable', 'integer', 'min:0'],
        'billetes_20'   => ['nullable', 'integer', 'min:0'],
        'monedas_20'    => ['nullable', 'integer', 'min:0'],
        'monedas_10'    => ['nullable', 'integer', 'min:0'],
        'monedas_5'     => ['nullable', 'integer', 'min:0'],
        'monedas_2'     => ['nullable', 'integer', 'min:0'],
        'monedas_1'     => ['nullable', 'integer', 'min:0'],
        'monedas_050'   => ['nullable', 'integer', 'min:0'],
    ]);

    DB::beginTransaction();
    try {
        // lockForUpdate dentro de la transacción para evitar doble cierre concurrente
        $corte = CorteCaja::where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->where('terminal', $terminal)
            ->where('id', $id)
            ->where('estado', 'abierto')
            ->lockForUpdate()
            ->firstOrFail();

        $corte->recalcularVentas();
        $corte->recalcularMovimientos();
        $corte->recalcularEsperados();

        $modo          = $datos['modo'];
        $totalEfectivo = 0.0;

        $keysDenoms = [
            'billetes_1000','billetes_500','billetes_200','billetes_100','billetes_50','billetes_20',
            'monedas_20','monedas_10','monedas_5','monedas_2','monedas_1','monedas_050',
        ];

        $traeDenoms = collect($keysDenoms)->contains(
            fn($k) => isset($datos[$k]) && $datos[$k] !== null
        );

        if ($modo === 'manual') {
            $totalEfectivo = (float) $datos['contado_efectivo'];

        } else {
            if (! $traeDenoms) {
                $desglose = CorteDesgloseEfectivo::where('corte_id', $corte->id)->first();

                if (! $desglose) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'No se recibieron denominaciones y no existe desglose guardado para cerrar en modo arqueo.',
                    ], 422);
                }

                $totalEfectivo = (float) $desglose->calcularTotal();
                $desglose->update(['total_calculado' => $totalEfectivo]);

            } else {
                $desglose = CorteDesgloseEfectivo::updateOrCreate(
                    ['corte_id' => $corte->id],
                    [
                        'billetes_1000' => $datos['billetes_1000'] ?? 0,
                        'billetes_500'  => $datos['billetes_500']  ?? 0,
                        'billetes_200'  => $datos['billetes_200']  ?? 0,
                        'billetes_100'  => $datos['billetes_100']  ?? 0,
                        'billetes_50'   => $datos['billetes_50']   ?? 0,
                        'billetes_20'   => $datos['billetes_20']   ?? 0,
                        'monedas_20'    => $datos['monedas_20']    ?? 0,
                        'monedas_10'    => $datos['monedas_10']    ?? 0,
                        'monedas_5'     => $datos['monedas_5']     ?? 0,
                        'monedas_2'     => $datos['monedas_2']     ?? 0,
                        'monedas_1'     => $datos['monedas_1']     ?? 0,
                        'monedas_050'   => $datos['monedas_050']   ?? 0,
                    ]
                );

                $totalEfectivo = (float) $desglose->calcularTotal();
                $desglose->update(['total_calculado' => $totalEfectivo]);
            }
        }

        $corte->update([
            'contado_efectivo'      => $totalEfectivo,
            'contado_tarjeta'       => (float) ($datos['contado_tarjeta'] ?? 0),
            'contado_transferencia' => (float) ($datos['contado_transferencia'] ?? 0),
            'notas_cierre'          => $datos['notas_cierre'] ?? null,
            'fecha_cierre'          => now(),
            'estado'                => 'cerrado',
        ]);

        $corte->recalcularDiferencias();

        DB::commit();

        return response()->json(
            $corte->load(['desglose', 'movimientos.user:id,name', 'user:id,name'])
        );
    } catch (\Throwable $e) {
        DB::rollBack();
        return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
    }
}

    public function show(int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('caja.historial'), 403, 'Sin permiso: caja.historial');
        $user = Auth::user();

        $corte = CorteCaja::where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->with(['user:id,name', 'desglose', 'movimientos.user:id,name'])
            ->findOrFail($id);

        // refrescar totales (si todavía estuviera abierto)
        if ($corte->estado === 'abierto') {
            $corte->recalcularVentas();
            $corte->recalcularMovimientos();
        }

        return response()->json($corte);
    }

    public function ventas(Request $request, int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('caja.historial'), 403, 'Sin permiso: caja.historial');
        $user = Auth::user();

        $corte = CorteCaja::where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->findOrFail($id);

        $ventas = Venta::where('corte_id', $corte->id)
            ->where('estado', 'confirmada')
            ->with([
                'detalles.producto:id,nombre',
                'detalles.variante:id,sku',
                'user:id,name',
            ])
            ->orderByDesc('fecha')
            ->paginate($request->por_pagina ?? 30);

        return response()->json($ventas);
    }


    public function consultaMovimientos(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('caja.historial'), 403, 'Sin permiso: caja.historial');
        $user  = Auth::user();
        $sid   = (int) $user->sucursal_id;

        $hoy   = now()->toDateString();
        $desde = $request->input('desde', $hoy);
        $hasta = $request->input('hasta', $hoy);

        // Rangos de timestamp completos para que los índices en columnas datetime funcionen
        $desdeTs = $desde . ' 00:00:00';
        $hastaTs = $hasta . ' 23:59:59';

        $origen     = $request->input('origen', '');
        $tipo       = $request->input('tipo', '');
        $formaPago  = $request->input('forma_pago', '');
        $userId     = $request->input('user_id', '');
        $concepto   = $request->input('concepto', '');
        $porPagina  = (int) $request->input('por_pagina', 25);

        // ── Rama 1: movimientos manuales ─────────────────────────────────────
        $qMov = DB::table('movimientos_caja as m')
            ->join('cortes_caja as c', 'm.corte_id', '=', 'c.id')
            ->join('users as u', 'm.user_id', '=', 'u.id')
            ->where('c.sucursal_id', $sid)
            ->where('c.fecha_apertura', '>=', $desdeTs)
            ->where('c.fecha_apertura', '<=', $hastaTs)
            ->select([
                'm.id',
                DB::raw("'movimiento' as origen"),
                'm.created_at as fecha_hora',
                'u.name as usuario',
                'm.tipo',
                'm.forma_pago',
                DB::raw('CAST(m.monto AS DECIMAL(14,2)) as monto'),
                'm.concepto',
                'c.terminal',
                DB::raw('NULL as folio'),
            ]);

        if ($tipo)      $qMov->where('m.tipo', $tipo);
        if ($formaPago) $qMov->where('m.forma_pago', $formaPago);
        if ($userId)    $qMov->where('m.user_id', $userId);
        if ($concepto)  $qMov->where('m.concepto', 'like', "%{$concepto}%");

        // ── Rama 2: ventas confirmadas ────────────────────────────────────────
        $qVentas = DB::table('ventas as v')
            ->join('users as u', 'v.user_id', '=', 'u.id')
            ->join('cortes_caja as c', 'v.corte_id', '=', 'c.id')
            ->where('v.sucursal_id', $sid)
            ->where('v.estado', 'confirmada')
            ->whereNotNull('v.corte_id')
            ->where('v.fecha', '>=', $desde)
            ->where('v.fecha', '<=', $hasta)
            ->select([
                'v.id',
                DB::raw("'venta' as origen"),
                'v.created_at as fecha_hora',
                'u.name as usuario',
                DB::raw("'ingreso' as tipo"),
                'v.forma_pago',
                DB::raw('CAST(v.total AS DECIMAL(14,2)) as monto'),
                DB::raw("CONCAT('Venta ', COALESCE(v.folio, v.id)) as concepto"),
                'c.terminal',
                'v.folio',
            ]);

        if ($formaPago) $qVentas->where('v.forma_pago', $formaPago);
        if ($userId)    $qVentas->where('v.user_id', $userId);
        if ($concepto)  $qVentas->where(DB::raw("CONCAT('Venta ', COALESCE(v.folio, v.id))"), 'like', "%{$concepto}%");
        // ventas son siempre ingreso; si filtran egreso, excluimos ventas
        if ($tipo === 'egreso') $qVentas->whereRaw('1=0');

        // ── UNION y paginación ────────────────────────────────────────────────
        if ($origen === 'movimiento') {
            $query = $qMov;
        } elseif ($origen === 'venta') {
            $query = $qVentas;
        } else {
            $query = $qMov->unionAll($qVentas);
        }

        $resultado = DB::table(DB::raw("({$query->toSql()}) as t"))
            ->mergeBindings($query)
            ->orderByDesc('fecha_hora')
            ->paginate($porPagina);

        return response()->json($resultado);
    }

   public function guardarDesgloseEnVivo(Request $request, int $id): JsonResponse
{
    $user = Auth::user();
    $terminal = TerminalResolver::fromRequest($request);

    $corte = CorteCaja::where('empresa_id', $user->empresa_id)
        ->where('sucursal_id', $user->sucursal_id)
        ->where('terminal', $terminal)
        ->where('id', $id)
        ->where('estado', 'abierto')
        ->firstOrFail();

    $datos = $request->validate([
        'billetes_1000' => ['nullable', 'integer', 'min:0'],
        'billetes_500'  => ['nullable', 'integer', 'min:0'],
        'billetes_200'  => ['nullable', 'integer', 'min:0'],
        'billetes_100'  => ['nullable', 'integer', 'min:0'],
        'billetes_50'   => ['nullable', 'integer', 'min:0'],
        'billetes_20'   => ['nullable', 'integer', 'min:0'],
        'monedas_20'    => ['nullable', 'integer', 'min:0'],
        'monedas_10'    => ['nullable', 'integer', 'min:0'],
        'monedas_5'     => ['nullable', 'integer', 'min:0'],
        'monedas_2'     => ['nullable', 'integer', 'min:0'],
        'monedas_1'     => ['nullable', 'integer', 'min:0'],
        'monedas_050'   => ['nullable', 'integer', 'min:0'],
    ]);

    DB::beginTransaction();
    try {
        // ✅ Guardado rápido del desglose
        $desglose = CorteDesgloseEfectivo::updateOrCreate(
            ['corte_id' => $corte->id],
            [
                'billetes_1000' => $datos['billetes_1000'] ?? 0,
                'billetes_500'  => $datos['billetes_500']  ?? 0,
                'billetes_200'  => $datos['billetes_200']  ?? 0,
                'billetes_100'  => $datos['billetes_100']  ?? 0,
                'billetes_50'   => $datos['billetes_50']   ?? 0,
                'billetes_20'   => $datos['billetes_20']   ?? 0,
                'monedas_20'    => $datos['monedas_20']    ?? 0,
                'monedas_10'    => $datos['monedas_10']    ?? 0,
                'monedas_5'     => $datos['monedas_5']     ?? 0,
                'monedas_2'     => $datos['monedas_2']     ?? 0,
                'monedas_1'     => $datos['monedas_1']     ?? 0,
                'monedas_050'   => $datos['monedas_050']   ?? 0,
            ]
        );

        $total = (float) $desglose->calcularTotal();
        $desglose->update(['total_calculado' => $total]);

        // ✅ Actualiza corte SOLO con efectivo contado y diferencia de efectivo
        // (sin recalcular esperado)
        $corte->contado_efectivo = $total;
        $corte->dif_efectivo = (float) $corte->contado_efectivo - (float) ($corte->esperado_efectivo ?? 0);
        $corte->save();

        DB::commit();

        // ✅ Respuesta ligera (sin movimientos)
        return response()->json([
            'corte' => [
                'id'               => $corte->id,
                'esperado_efectivo' => (float) ($corte->esperado_efectivo ?? 0),
                'contado_efectivo'  => (float) ($corte->contado_efectivo ?? 0),
                'dif_efectivo'      => (float) ($corte->dif_efectivo ?? 0),
                'updated_at'        => $corte->updated_at,
            ],
            'desglose' => $desglose->fresh(),
        ]);
    } catch (\Throwable $e) {
        DB::rollBack();
        return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
    }
}
}
