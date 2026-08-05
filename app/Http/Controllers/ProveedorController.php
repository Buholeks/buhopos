<?php

namespace App\Http\Controllers;

use App\Models\Proveedor;
use App\Models\ProveedorSaldoMovimiento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProveedorController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();

        $q = Proveedor::where('empresa_id', $u->empresa_id)
            ->select('proveedores.*')
            ->selectSub(
                ProveedorSaldoMovimiento::query()
                    ->selectRaw('COALESCE(SUM(' . ProveedorSaldoMovimiento::expresionSaldo() . '), 0)')
                    ->whereColumn('proveedor_saldo_movimientos.proveedor_id', 'proveedores.id')
                    ->where('proveedor_saldo_movimientos.empresa_id', $u->empresa_id)
                    ->where('proveedor_saldo_movimientos.sucursal_id', $u->sucursal_id),
                'saldo_favor'
            )
            ->orderBy('nombre_comercial');

        if ($search = trim($request->q)) {
            $words = preg_split('/\s+/', $search);

            $q->where(function ($query) use ($words) {
                foreach ($words as $word) {
                    $query->where(function ($w) use ($word) {
                        $w->where('nombre_comercial', 'like', "%{$word}%")
                            ->orWhere('razon_social', 'like', "%{$word}%")
                            ->orWhere('telefono', 'like', "%{$word}%");
                    });
                }
            });
        }

        if ($request->boolean('con_saldo')) {
            $q->whereExists(function ($movimientos) use ($u) {
                $movimientos->selectRaw('1')
                    ->from('proveedor_saldo_movimientos as psm')
                    ->whereColumn('psm.proveedor_id', 'proveedores.id')
                    ->where('psm.empresa_id', $u->empresa_id)
                    ->where('psm.sucursal_id', $u->sucursal_id)
                    ->groupBy('psm.proveedor_id')
                    ->havingRaw('SUM(' . ProveedorSaldoMovimiento::expresionSaldo('psm') . ') > 0');
            });
        }

        return response()->json($q->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request)
    {
        $u = $request->user();

        $data = $request->validate([
            'nombre_comercial' => 'required|string|max:255',
            'razon_social' => 'nullable|string|max:255',
            'rfc' => 'nullable|string|max:13',

            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'contacto' => 'nullable|string|max:255',

            'calle' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:50',
            'colonia' => 'nullable|string|max:255',
            'ciudad' => 'nullable|string|max:255',
            'estado' => 'nullable|string|max:255',
            'cp' => 'nullable|string|max:10',

            'sitio_web' => 'nullable|string|max:255',
            'activo' => 'boolean',

            'credito_activo' => 'boolean',
            'dias_credito_default' => 'nullable|integer|min:0',
            'limite_credito' => 'nullable|numeric|min:0',
        ]);

        $proveedor = Proveedor::create([
            'empresa_id' => $u->empresa_id,
            'sucursal_id' => $u->sucursal_id,
            'user_id' => $u->id,
            ...$data
        ]);

        return response()->json($proveedor, 201);
        
    }

    public function show(Request $request, $id)
    {
        $u = $request->user();

        return Proveedor::where('id', $id)
            ->where('empresa_id', $u->empresa_id)
            ->firstOrFail();
    }

    public function update(Request $request, $id)
    {
        $u = $request->user();

        $proveedor = Proveedor::where('id', $id)
            ->where('empresa_id', $u->empresa_id)
            ->firstOrFail();

        $data = $request->validate([
            'nombre_comercial' => 'required|string|max:255',
            'razon_social' => 'nullable|string|max:255',
            'rfc' => 'nullable|string|max:13',

            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'contacto' => 'nullable|string|max:255',

            'calle' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:50',
            'colonia' => 'nullable|string|max:255',
            'ciudad' => 'nullable|string|max:255',
            'estado' => 'nullable|string|max:255',
            'cp' => 'nullable|string|max:10',

            'sitio_web' => 'nullable|string|max:255',
            'activo' => 'boolean',

            'credito_activo' => 'boolean',
            'dias_credito_default' => 'nullable|integer|min:0',
            'limite_credito' => 'nullable|numeric|min:0',
        ]);

        $proveedor->update($data);

        return response()->json($proveedor);
    }

    public function destroy(Request $request, $id)
    {
        $u = $request->user();

        $proveedor = Proveedor::where('id', $id)
            ->where('empresa_id', $u->empresa_id)
            ->firstOrFail();

        $tieneHistorial = DB::table('compras')->where('proveedor_id', $proveedor->id)->exists()
            || DB::table('proveedor_saldo_movimientos')->where('proveedor_id', $proveedor->id)->exists();
        abort_if($tieneHistorial, 422, 'No se puede eliminar un proveedor con historial. Puedes desactivarlo.');

        $proveedor->delete();

        return response()->json(['ok' => true]);
    }

    public function estadoCuenta(Request $request, Proveedor $proveedor)
    {
        $u = $request->user();
        abort_unless($u->tienePermiso('proveedores.saldos.ver') || $u->tienePermiso('catalogos.ver'), 403, 'Sin permiso para consultar saldos de proveedores.');
        abort_unless((int) $proveedor->empresa_id === (int) $u->empresa_id, 404);

        $base = ProveedorSaldoMovimiento::query()
            ->where('empresa_id', $u->empresa_id)
            ->where('sucursal_id', $u->sucursal_id)
            ->where('proveedor_id', $proveedor->id);

        $saldo = (float) (clone $base)->sum(DB::raw(ProveedorSaldoMovimiento::expresionSaldo()));
        $creditos = (float) (clone $base)->whereIn('tipo', ProveedorSaldoMovimiento::TIPOS_CREDITO)->sum('monto');
        $aplicado = (float) (clone $base)->whereIn('tipo', ['aplicacion', 'ajuste', 'ajuste_debito', 'reverso_credito'])->sum('monto');

        $compras = DB::table('compras')
            ->where('empresa_id', $u->empresa_id)
            ->where('sucursal_id', $u->sucursal_id)
            ->where('proveedor_id', $proveedor->id)
            ->whereNull('deleted_at');

        $movimientos = (clone $base)
            ->with(['compra:id,folio', 'devolucion:id,referencia,compra_id', 'user:id,name'])
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('hasta')))
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', (string) $request->input('tipo')))
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        $movimientos->getCollection()->transform(function (ProveedorSaldoMovimiento $movimiento) {
            $movimiento->setAttribute('importe_firmado', $movimiento->importe_firmado);
            return $movimiento;
        });

        return response()->json([
            'proveedor' => $proveedor,
            'resumen' => [
                'saldo_favor' => round($saldo, 2),
                'saldo_generado' => round($creditos, 2),
                'saldo_aplicado' => round($aplicado, 2),
                'cuentas_por_pagar' => round((float) (clone $compras)->where('saldo', '>', 0)->sum('saldo'), 2),
                'compras_pendientes' => (clone $compras)->where('saldo', '>', 0)->count(),
                'compras_vencidas' => (clone $compras)->where('saldo', '>', 0)->whereDate('fecha_vencimiento', '<', today())->count(),
                'ultima_compra' => (clone $compras)->max('fecha'),
            ],
            'movimientos' => $movimientos,
        ]);
    }

    public function ajustarSaldo(Request $request, Proveedor $proveedor)
    {
        $u = $request->user();
        abort_unless($u->tienePermiso('proveedores.saldos.ajustar'), 403, 'Sin permiso: proveedores.saldos.ajustar');
        abort_unless((int) $proveedor->empresa_id === (int) $u->empresa_id, 404);

        $data = $request->validate([
            'naturaleza' => ['required', 'in:credito,debito'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'motivo' => ['required', 'string', 'max:255'],
        ]);

        $movimiento = DB::transaction(function () use ($u, $proveedor, $data) {
            $movimientos = ProveedorSaldoMovimiento::where([
                'empresa_id' => $u->empresa_id,
                'sucursal_id' => $u->sucursal_id,
                'proveedor_id' => $proveedor->id,
            ])->lockForUpdate()->get();
            $saldo = $movimientos->sum->importe_firmado;
            $firmado = $data['naturaleza'] === 'credito' ? (float) $data['monto'] : -(float) $data['monto'];
            abort_if($saldo + $firmado < 0, 422, 'El ajuste no puede dejar el saldo a favor en negativo.');

            return ProveedorSaldoMovimiento::create([
                'empresa_id' => $u->empresa_id,
                'sucursal_id' => $u->sucursal_id,
                'proveedor_id' => $proveedor->id,
                'user_id' => $u->id,
                'tipo' => $data['naturaleza'] === 'credito' ? 'ajuste_credito' : 'ajuste_debito',
                'monto' => round((float) $data['monto'], 2),
                'saldo_resultante' => round($saldo + $firmado, 2),
                'concepto' => $data['motivo'],
            ]);
        });

        return response()->json($movimiento, 201);
    }
}
