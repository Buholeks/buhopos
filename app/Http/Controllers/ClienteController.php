<?php
// app/Http/Controllers/ClienteController.php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\Cliente;
use Illuminate\Validation\Rule;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Auth::user()->tienePermiso('clientes.ver'), 403, 'Sin permiso: clientes.ver');
        $user = $request->user();

        $q = Cliente::query()
            ->where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id);

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
        $cliente->delete();

        return response()->json(['ok' => true]);
    }

    private function assertTenant(Request $request, Cliente $cliente): void
    {
        $user = $request->user();

        abort_if(
            $cliente->empresa_id !== $user->empresa_id || $cliente->sucursal_id !== $user->sucursal_id,
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
}
