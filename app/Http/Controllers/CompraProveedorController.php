<?php
namespace App\Http\Controllers;

use App\Models\CompraProveedor;
use App\Models\CompraDetalleProveedor;
use App\Models\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompraProveedorController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();

        $q = CompraProveedor::with(['proveedor'])
            ->where('empresa_id', $u->empresa_id)
            ->where('sucursal_id', $u->sucursal_id)
            ->orderByDesc('id');

        if ($request->filled('proveedor_id')) {
            $q->where('proveedor_id', $request->proveedor_id);
        }

        return response()->json($q->paginate(20));
    }

    public function store(Request $request)
    {
        $u = $request->user();

        $data = $request->validate([
            'proveedor_id' => 'required|exists:proveedores,id',
            'folio' => 'nullable|string|max:50',
            'fecha_compra' => 'required|date',
            'tipo_pago' => 'required|in:CONTADO,CREDITO,MIXTO',
            'pagado_inicial' => 'nullable|numeric|min:0',
            'dias_credito' => 'nullable|integer|min:0',

            'observaciones' => 'nullable|string',

            'detalles' => 'required|array|min:1',
            'detalles.*.producto_id' => 'nullable|integer',
            'detalles.*.descripcion' => 'nullable|string|max:255',
            'detalles.*.cantidad' => 'required|numeric|min:0.01',
            'detalles.*.costo_unitario' => 'required|numeric|min:0',
        ]);

        // Seguridad multiempresa: proveedor debe pertenecer a empresa si aplica
        // (si tu tabla proveedores tiene empresa_id, valida aquí)
        // Proveedor::where('id',$data['proveedor_id'])->where('empresa_id',$u->empresa_id)->firstOrFail();

        return DB::transaction(function () use ($data, $u) {
            $pagadoInicial = (float)($data['pagado_inicial'] ?? 0);

            // total desde detalles (no confíes en el front)
            $total = 0;
            foreach ($data['detalles'] as $d) {
                $total += ((float)$d['cantidad']) * ((float)$d['costo_unitario']);
            }

            if ($data['tipo_pago'] === 'CONTADO') {
                $pagadoInicial = $total;
            }

            if ($pagadoInicial > $total) {
                abort(422, 'El pagado inicial no puede ser mayor al total.');
            }

            $saldo = $total - $pagadoInicial;

            // vencimiento
            $fechaVenc = null;
            if (in_array($data['tipo_pago'], ['CREDITO','MIXTO']) && $saldo > 0) {
                $dias = (int)($data['dias_credito'] ?? 0);
                if ($dias <= 0) {
                    // si no mandan, usa default del proveedor (si lo tienes)
                    $prov = Proveedor::findOrFail($data['proveedor_id']);
                    $dias = (int)($prov->dias_credito_default ?? 0);
                }
                $fechaVenc = $dias > 0 ? now()->parse($data['fecha_compra'])->addDays($dias) : null;
            }

            $estatus = $saldo <= 0 ? 'PAGADO' : ($pagadoInicial > 0 ? 'PARCIAL' : 'PENDIENTE');

            $compra = CompraProveedor::create([
                'empresa_id' => $u->empresa_id,
                'sucursal_id' => $u->sucursal_id,
                'user_id' => $u->id,

                'proveedor_id' => $data['proveedor_id'],
                'folio' => $data['folio'] ?? null,
                'fecha_compra' => $data['fecha_compra'],
                'tipo_pago' => $data['tipo_pago'],

                'total' => $total,
                'pagado_inicial' => $pagadoInicial,
                'saldo' => $saldo,

                'fecha_vencimiento' => $fechaVenc,
                'estatus' => $estatus,
                'observaciones' => $data['observaciones'] ?? null,
            ]);

            foreach ($data['detalles'] as $d) {
                $subtotal = ((float)$d['cantidad']) * ((float)$d['costo_unitario']);
                CompraDetalleProveedor::create([
                    'compra_id' => $compra->id,
                    'producto_id' => $d['producto_id'] ?? null,
                    'descripcion' => $d['descripcion'] ?? null,
                    'cantidad' => $d['cantidad'],
                    'costo_unitario' => $d['costo_unitario'],
                    'subtotal' => $subtotal,
                ]);
            }

            // Actualiza caches del proveedor (opcional)
            if ($saldo > 0) {
                Proveedor::where('id', $data['proveedor_id'])->update([
                    'saldo_credito_cache' => DB::raw("saldo_credito_cache + {$saldo}"),
                    'total_credito_cache' => DB::raw("total_credito_cache + {$saldo}"),
                ]);
            }

            return response()->json($compra->load('detalles'), 201);
        });
    }
}
