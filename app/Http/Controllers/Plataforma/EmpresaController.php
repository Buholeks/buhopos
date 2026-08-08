<?php

namespace App\Http\Controllers\Plataforma;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EmpresaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));
        $empresas = Empresa::query()
            ->with(['suscripcion.plan:id,nombre'])
            ->withCount(['sucursales', 'usuarios'])
            ->when($q, fn ($query) => $query->where(fn ($sub) => $sub
                ->where('nombre', 'like', "%{$q}%")->orWhere('correo', 'like', "%{$q}%")
                ->orWhere('rfc', 'like', "%{$q}%")))
            ->orderByDesc('id')->paginate(20);
        return response()->json($empresas);
    }

    public function show(Empresa $empresa): JsonResponse
    {
        return response()->json($empresa->load([
            'sucursales' => fn ($q) => $q->orderBy('nombre'),
            'usuarios' => fn ($q) => $q->with('sucursal:id,nombre')->orderBy('name'),
            'suscripcion.plan',
            'suscripcion.pagos' => fn ($q) => $q->with('administrador:id,nombre')->latest('fecha_pago'),
            'suscripcion.solicitudesPago' => fn ($q) => $q->with(['plan:id,nombre', 'cuentaCobro'])->latest(),
        ]));
    }

    public function update(Request $request, Empresa $empresa): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['sometimes', 'string', 'min:2', 'max:255'],
            'propietario' => ['nullable', 'string', 'max:255'],
            'correo' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'rfc' => ['nullable', 'string', 'max:20'],
            'activo' => ['sometimes', 'boolean'],
        ]);
        $empresa->update($data);
        return response()->json($empresa->fresh());
    }

    public function guardarSucursal(Request $request, Empresa $empresa, ?Sucursal $sucursal = null): JsonResponse
    {
        if ($sucursal) abort_unless($sucursal->empresa_id === $empresa->id, 404);
        $data = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'correo' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'activo' => ['sometimes', 'boolean'],
        ]);
        $sucursal = $sucursal
            ? tap($sucursal)->update($data)
            : $empresa->sucursales()->create($data + ['activo' => true]);
        return response()->json($sucursal->fresh(), $sucursal->wasRecentlyCreated ? 201 : 200);
    }

    public function guardarUsuario(Request $request, Empresa $empresa, ?User $user = null): JsonResponse
    {
        if ($user) abort_unless($user->empresa_id === $empresa->id, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'nullable', 'string', 'min:8'],
            'sucursal_id' => ['required', Rule::exists('sucursales', 'id')->where('empresa_id', $empresa->id)],
            'activo' => ['sometimes', 'boolean'],
            'es_super_admin' => ['sometimes', 'boolean'],
        ]);

        $payload = collect($data)->except('password')->all();
        if (! empty($data['password'])) $payload['password'] = $data['password'];
        $payload['empresa_id'] = $empresa->id;

        $user = DB::transaction(function () use ($user, $payload) {
            if ($user) $user->update($payload); else $user = User::create($payload + ['activo' => true]);
            $user->sucursales()->syncWithoutDetaching([$user->sucursal_id]);
            return $user;
        });

        return response()->json($user->fresh('sucursal:id,nombre'), $user->wasRecentlyCreated ? 201 : 200);
    }
}
