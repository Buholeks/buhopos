<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Compra;
use App\Models\PagoProveedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PagoProveedorController extends Controller
{
    public function index(Request $request, $compraId)
    {
        $empresaId  = $request->user()->empresa_id;
        $sucursalId = $request->user()->sucursal_id;

        $compra = Compra::where('empresa_id', $empresaId)
            ->where('sucursal_id', $sucursalId)
            ->findOrFail($compraId);

        $pagos = PagoProveedor::with('user:id,name')
            ->where('compra_id', $compraId)
            ->orderByDesc('fecha_pago')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'compra' => [
                'id'                => $compra->id,
                'folio'             => $compra->folio,
                'total'             => $compra->total,
                'pagado'            => $compra->pagado,
                'saldo'             => $compra->saldo,
                'forma_pago'        => $compra->forma_pago,
                'fecha_vencimiento' => $compra->fecha_vencimiento,
                'estatus_pago'      => $this->getEstatus($compra),
            ],
            'pagos' => $pagos,
        ]);
    }

    public function store(Request $request, $compraId)
    {
        $empresaId  = $request->user()->empresa_id;
        $sucursalId = $request->user()->sucursal_id;

        $compra = Compra::where('empresa_id', $empresaId)
            ->where('sucursal_id', $sucursalId)
            ->findOrFail($compraId);

        if ($compra->saldo <= 0) {
            return response()->json(['message' => 'Esta compra ya está pagada completamente.'], 422);
        }

        $data = $request->validate([
            'monto'      => ['required', 'numeric', 'min:0.01', 'max:' . $compra->saldo],
            'fecha_pago' => ['required', 'date'],
            'forma_pago' => ['required', 'in:efectivo,transferencia,cheque,tarjeta'],
            'referencia' => ['nullable', 'string', 'max:100'],
            'notas'      => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data, $compra, $request, $empresaId, $sucursalId) {
            PagoProveedor::create([
                'empresa_id'  => $empresaId,
                'sucursal_id' => $sucursalId,
                'compra_id'   => $compra->id,
                'user_id'     => $request->user()->id,
                'monto'       => $data['monto'],
                'fecha_pago'  => $data['fecha_pago'],
                'forma_pago'  => $data['forma_pago'],
                'referencia'  => $data['referencia'] ?? null,
                'notas'       => $data['notas'] ?? null,
            ]);

            $nuevoPagado = round($compra->pagado + $data['monto'], 2);
            $nuevoSaldo  = round(max(0, $compra->total - $nuevoPagado), 2);

            // DB::table bypasea $fillable — solución segura
            DB::table('compras')
                ->where('id', $compra->id)
                ->update([
                    'pagado'     => $nuevoPagado,
                    'saldo'      => $nuevoSaldo,
                    'updated_at' => now(),
                ]);
        });

        $compra->refresh();

        return response()->json([
            'message' => 'Pago registrado correctamente.',
            'compra'  => [
                'id'           => $compra->id,
                'total'        => $compra->total,
                'pagado'       => $compra->pagado,
                'saldo'        => $compra->saldo,
                'estatus_pago' => $this->getEstatus($compra),
            ],
        ], 201);
    }

    public function destroy(Request $request, $compraId, $pagoId)
    {
        $empresaId  = $request->user()->empresa_id;
        $sucursalId = $request->user()->sucursal_id;

        $compra = Compra::where('empresa_id', $empresaId)
            ->where('sucursal_id', $sucursalId)
            ->findOrFail($compraId);

        $pago = PagoProveedor::where('compra_id', $compra->id)
            ->findOrFail($pagoId);

        DB::transaction(function () use ($pago, $compra) {
            $nuevoPagado = round(max(0, $compra->pagado - $pago->monto), 2);
            $nuevoSaldo  = round($compra->total - $nuevoPagado, 2);

            DB::table('compras')
                ->where('id', $compra->id)
                ->update([
                    'pagado'     => $nuevoPagado,
                    'saldo'      => $nuevoSaldo,
                    'updated_at' => now(),
                ]);

            $pago->delete();
        });

        $compra->refresh();

        return response()->json([
            'message' => 'Pago eliminado correctamente.',
            'compra'  => [
                'id'           => $compra->id,
                'total'        => $compra->total,
                'pagado'       => $compra->pagado,
                'saldo'        => $compra->saldo,
                'estatus_pago' => $this->getEstatus($compra),
            ],
        ]);
    }

    private function getEstatus(Compra $compra): string
    {
        if ($compra->forma_pago === 'efectivo') return 'pagado';
        if ($compra->saldo <= 0) return 'pagado';
        if ($compra->fecha_vencimiento && $compra->fecha_vencimiento < now()->toDateString()) return 'vencido';
        return 'pendiente';
    }
}