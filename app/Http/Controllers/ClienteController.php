<?php
// app/Http/Controllers/ClienteController.php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\Cliente;
use App\Models\ClienteSaldoMovimiento;
use App\Models\Pedido;
use App\Servicios\ClienteSaldoServicio;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Auth::user()->tienePermiso('clientes.ver'), 403, 'Sin permiso: clientes.ver');
        $user = $request->user();

        // El saldo por alcance nunca debería quedar negativo en un cliente sano (ver
        // ClienteSaldoServicio::resumen), pero si algún día ocurre no debe "cancelar"
        // saldo positivo de otro alcance: cada alcance se recorta a 0 antes de sumarse,
        // igual que hace resumen()/estadoCuenta(), para que el listado y el estado de
        // cuenta de un mismo cliente siempre muestren el mismo total.
        $expr = ClienteSaldoMovimiento::expresionSaldo();
        $q = Cliente::query()
            ->where('empresa_id', $user->empresa_id)
            ->select('clientes.*')
            ->selectSub(
                ClienteSaldoMovimiento::query()
                    ->selectRaw(
                        "COALESCE(GREATEST(0, SUM(CASE WHEN alcance = 'general' THEN ({$expr}) ELSE 0 END)), 0)"
                        . " + COALESCE(GREATEST(0, SUM(CASE WHEN alcance = 'legacy' THEN ({$expr}) ELSE 0 END)), 0)"
                        . " + COALESCE(GREATEST(0, SUM(CASE WHEN alcance = 'pedido' THEN ({$expr}) ELSE 0 END)), 0)"
                    )
                    ->whereColumn('cliente_saldo_movimientos.cliente_id', 'clientes.id')
                    ->where('cliente_saldo_movimientos.empresa_id', $user->empresa_id)
                    ->where('cliente_saldo_movimientos.sucursal_id', $user->sucursal_id),
                'saldo_favor'
            );

        if ($search = trim($request->q)) {
            $words = preg_split('/\s+/', $search);

            $q->where(function ($query) use ($words) {
                foreach ($words as $word) {
                    $query->where(function ($w) use ($word) {
                        $w->where('nombre', 'like', "%{$word}%")
                            ->orWhere('correo', 'like', "%{$word}%")
                            ->orWhere('telefono', 'like', "%{$word}%");
                    });
                }
            });
        }

        if ($request->boolean('con_saldo')) {
            $q->whereExists(function ($movimientos) use ($user) {
                $exprCsm = ClienteSaldoMovimiento::expresionSaldo('csm');
                $movimientos->selectRaw('1')->from('cliente_saldo_movimientos as csm')
                    ->whereColumn('csm.cliente_id', 'clientes.id')
                    ->where('csm.empresa_id', $user->empresa_id)
                    ->where('csm.sucursal_id', $user->sucursal_id)
                    ->groupBy('csm.cliente_id')
                    ->havingRaw(
                        "COALESCE(GREATEST(0, SUM(CASE WHEN csm.alcance = 'general' THEN ({$exprCsm}) ELSE 0 END)), 0)"
                        . " + COALESCE(GREATEST(0, SUM(CASE WHEN csm.alcance = 'legacy' THEN ({$exprCsm}) ELSE 0 END)), 0)"
                        . " + COALESCE(GREATEST(0, SUM(CASE WHEN csm.alcance = 'pedido' THEN ({$exprCsm}) ELSE 0 END)), 0) > 0"
                    );
            });
        }


        // Puedes usar paginate para tablas
        return $q->orderByDesc('id')->paginate(15);
    }

    public function store(Request $request)
    {
        abort_unless(Auth::user()->tienePermiso('clientes.editar'), 403, 'Sin permiso: clientes.editar');
        $user = $request->user();

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'correo' => [
                'nullable',
                'email',
                'max:150',
                Rule::unique('clientes', 'correo')
                    ->where(fn ($query) => $query->where('empresa_id', $user->empresa_id)),
            ],
            'telefono' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('clientes', 'telefono')
                    ->where(fn ($query) => $query->where('empresa_id', $user->empresa_id)),
            ],
            'direccion' => ['nullable', 'string', 'max:255'],
            'activo' => ['sometimes', 'boolean'], // si no lo mandas, queda default true
        ]);

        $cliente = Cliente::create([
            'empresa_id' => $user->empresa_id,
            'sucursal_id' => $user->sucursal_id,
            'user_id' => $user->id,
            ...$data,
        ]);

        return response()->json($cliente, 201);
    }

    public function show(Request $request, Cliente $cliente)
    {
        abort_unless(Auth::user()->tienePermiso('clientes.ver'), 403, 'Sin permiso: clientes.ver');
        $this->assertTenant($request, $cliente);
        return response()->json($cliente);
    }

    public function update(Request $request, Cliente $cliente)
    {
        abort_unless(Auth::user()->tienePermiso('clientes.editar'), 403, 'Sin permiso: clientes.editar');
        $this->assertTenant($request, $cliente);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'correo' => [
                'nullable',
                'email',
                'max:150',
                Rule::unique('clientes', 'correo')
                    ->ignore($cliente->id)
                    ->where(fn ($query) => $query->where('empresa_id', $request->user()->empresa_id)),
            ],
            'telefono' => [
                'required',
                'string',
                'max:30',
                Rule::unique('clientes', 'telefono')
                    ->ignore($cliente->id)
                    ->where(fn ($query) => $query->where('empresa_id', $request->user()->empresa_id)),
            ],
            'direccion' => ['nullable', 'string', 'max:255'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $cliente->update($data);

        return response()->json($cliente);
    }

    public function destroy(Request $request, Cliente $cliente)
    {
        abort_unless(Auth::user()->tienePermiso('clientes.editar'), 403, 'Sin permiso: clientes.editar');
        $this->assertTenant($request, $cliente);

        // recomendación: borrado lógico (soft delete) si te interesa historial
        $tieneHistorial = $cliente->ventas()->exists()
            || DB::table('pedidos')->where('cliente_id', $cliente->id)->exists()
            || DB::table('cliente_saldo_movimientos')->where('cliente_id', $cliente->id)->exists();
        abort_if($tieneHistorial, 422, 'No se puede eliminar un cliente con historial. Puedes desactivarlo.');

        $cliente->delete();

        return response()->json(['ok' => true]);
    }

    private function assertTenant(Request $request, Cliente $cliente): void
    {
        $user = $request->user();

        abort_if(
            $cliente->empresa_id !== $user->empresa_id,
            403,
            'No autorizado.'
        );
    }

    public function setActivo(Request $request, Cliente $cliente)
    {
        abort_unless(Auth::user()->tienePermiso('clientes.editar'), 403, 'Sin permiso: clientes.editar');
        $this->assertTenant($request, $cliente);

        $data = $request->validate([
            'activo' => ['required', 'boolean'],
        ]);

        $cliente->update($data);

        return response()->json($cliente);
    }

    public function buscar(Request $request): JsonResponse
{
    abort_unless(Auth::user()->tienePermiso('clientes.ver'), 403, 'Sin permiso: clientes.ver');
    $user = Auth::user();
    $q = trim((string) $request->get('q', ''));

    $items = Cliente::query()
        ->where('empresa_id', $user->empresa_id)
        ->when($q !== '', function ($query) use ($q) {
            $query->where(function ($sub) use ($q) {
                $sub->where('nombre', 'like', "%{$q}%")
                    ->orWhere('telefono', 'like', "%{$q}%")
                    ->orWhere('correo', 'like', "%{$q}%");
            });
        })
        ->select('id', 'nombre', 'telefono', 'correo')
        ->orderBy('nombre')
        ->limit(20)
        ->get();

    return response()->json($items);
}

    public function estadoCuenta(Request $request, Cliente $cliente, ClienteSaldoServicio $saldos): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('clientes.saldos.ver') || Auth::user()->tienePermiso('clientes.ver'), 403, 'Sin permiso para consultar saldos de clientes.');
        $this->assertTenant($request, $cliente);
        $user = $request->user();
        $resumen = $saldos->resumen((int) $user->empresa_id, (int) $user->sucursal_id, (int) $cliente->id);

        $movimientos = ClienteSaldoMovimiento::where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)->where('cliente_id', $cliente->id)
            ->with(['pedido:id,folio,tipo', 'venta:id,folio', 'user:id,name', 'origen:id,concepto'])
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('hasta')))
            ->when($request->filled('alcance'), fn ($q) => $q->where('alcance', $request->input('alcance')))
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->input('tipo')))
            ->latest('id')->paginate($request->integer('per_page', 30));
        $movimientos->getCollection()->each(fn ($m) => $m->setAttribute('importe_firmado', $m->importe_firmado));

        $saldosPedido = $saldos->saldosPedidos((int) $user->empresa_id, (int) $user->sucursal_id, (int) $cliente->id);
        $foliosPedido = Pedido::whereIn('id', $saldosPedido->keys())->pluck('folio', 'id');

        return response()->json([
            'cliente' => $cliente,
            'resumen' => $resumen,
            'saldos_pedido' => $saldosPedido->map(fn ($saldo, $pedidoId) => [
                'pedido_id' => (int) $pedidoId,
                'folio' => $foliosPedido[$pedidoId] ?? "Pedido #{$pedidoId}",
                'saldo' => round((float) $saldo, 2),
            ])->values(),
            'movimientos' => $movimientos,
        ]);
    }

    public function ajustarSaldo(Request $request, Cliente $cliente, ClienteSaldoServicio $saldos): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('clientes.saldos.ajustar'), 403, 'Sin permiso: clientes.saldos.ajustar');
        $this->assertTenant($request, $cliente);
        $data = $request->validate([
            'naturaleza' => ['required', 'in:credito,debito'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'motivo' => ['required', 'string', 'max:255'],
        ]);
        $user = $request->user();

        $movimiento = DB::transaction(function () use ($user, $cliente, $data, $saldos) {
            ClienteSaldoMovimiento::where('empresa_id', $user->empresa_id)->where('sucursal_id', $user->sucursal_id)
                ->where('cliente_id', $cliente->id)->lockForUpdate()->get(['id']);
            $saldo = max(0, $saldos->saldo((int) $user->empresa_id, (int) $user->sucursal_id, (int) $cliente->id, 'general'));
            $firmado = $data['naturaleza'] === 'credito' ? (float) $data['monto'] : -(float) $data['monto'];
            abort_if($saldo + $firmado < 0, 422, 'El ajuste no puede dejar el saldo general en negativo.');

            return ClienteSaldoMovimiento::create([
                'empresa_id' => $user->empresa_id, 'sucursal_id' => $user->sucursal_id,
                'cliente_id' => $cliente->id, 'user_id' => $user->id,
                'tipo' => $data['naturaleza'] === 'credito' ? 'ajuste_credito' : 'ajuste_debito',
                'alcance' => 'general', 'monto' => round((float) $data['monto'], 2),
                'saldo_resultante' => round($saldo + $firmado, 2), 'concepto' => $data['motivo'],
            ]);
        });

        return response()->json($movimiento, 201);
    }
}
