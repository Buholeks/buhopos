<?php
namespace App\Http\Controllers;

use App\Models\AbonoProveedor;
use App\Models\CompraProveedor;
use App\Models\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AbonoProveedorController extends Controller
{
    public function store(Request $request)
    {
        $u = $request->user();

        $data = $request->validate([
            'compra_id' => 'required|exists:compras_proveedor,id',
            'fecha' => 'required|date',
            'monto' => 'required|numeric|min:0.01',
            'metodo_pago' => 'nullable|string|max:50',
            'referencia' => 'nullable|string|max:100',
            'nota' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($data, $u) {
            $compra = CompraProveedor::lockForUpdate()
                ->where('id', $data['compra_id'])
                ->where('empresa_id', $u->empresa_id)
                ->where('sucursal_id', $u->sucursal_id)
                ->firstOrFail();

            if ($compra->saldo <= 0) {
                abort(422, 'Esta compra ya está pagada.');
            }

            $monto = (float)$data['monto'];
            if ($monto > (float)$compra->saldo) {
                abort(422, 'El abono no puede ser mayor al saldo de la compra.');
            }

            $abono = AbonoProveedor::create([
                'empresa_id' => $u->empresa_id,
                'sucursal_id' => $u->sucursal_id,
                'user_id' => $u->id,

                'proveedor_id' => $compra->proveedor_id,
                'compra_id' => $compra->id,

                'fecha' => $data['fecha'],
                'monto' => $monto,
                'metodo_pago' => $data['metodo_pago'] ?? null,
                'referencia' => $data['referencia'] ?? null,
                'nota' => $data['nota'] ?? null,
            ]);

            // Actualiza saldo/estatus compra
            $nuevoSaldo = (float)$compra->saldo - $monto;
            $nuevoEstatus = $nuevoSaldo <= 0 ? 'PAGADO' : 'PARCIAL';

            $compra->update([
                'saldo' => $nuevoSaldo,
                'estatus' => $nuevoEstatus,
            ]);

            // Cache proveedor (opcional)
            Proveedor::where('id', $compra->proveedor_id)->update([
                'saldo_credito_cache' => DB::raw("saldo_credito_cache - {$monto}"),
                'total_abonos_cache' => DB::raw("total_abonos_cache + {$monto}"),
            ]);

            return response()->json([
                'abono' => $abono,
                'compra' => $compra->fresh(),
            ], 201);
        });
    }
}
