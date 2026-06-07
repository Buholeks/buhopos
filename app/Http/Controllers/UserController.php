<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\User;
use App\Models\Sucursal;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $texto = trim((string) $request->query('q', ''));

        $usuarios = User::query()
            ->where('empresa_id', $user->empresa_id)
            ->with('sucursal:id,nombre')
            ->when($texto !== '', fn($q) => $q->where(fn($sub) => $sub
                ->where('name', 'like', "%{$texto}%")
                ->orWhere('email', 'like', "%{$texto}%")))
            ->orderBy('name')
            ->paginate(20);

        return response()->json($usuarios);
    }

    public function sucursalesDisponibles(): JsonResponse
    {
        $user = Auth::user();

        $sucursales = Sucursal::query()
            ->where('empresa_id', $user->empresa_id)
            ->where('activo', true)
            ->select('id', 'nombre', 'direccion')
            ->orderBy('nombre')
            ->get();

        return response()->json($sucursales);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = Auth::user();

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'sucursal_id' => [
                'required',
                'integer',
                Rule::exists('sucursales', 'id')->where(fn($q) => $q
                    ->where('empresa_id', $actor->empresa_id)
                    ->where('activo', true)),
            ],
        ], [
            'email.unique' => 'Ya existe un usuario con ese correo.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'sucursal_id.exists' => 'La sucursal seleccionada no pertenece a la empresa activa.',
        ]);

        $usuario = DB::transaction(function () use ($actor, $data) {
            $usuario = User::create([
                'empresa_id' => $actor->empresa_id,
                'sucursal_id' => $data['sucursal_id'],
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'activo' => true,
            ]);

            $usuario->sucursales()->attach($data['sucursal_id']);

            return $usuario;
        });

        return response()->json(
            $usuario->load(['empresa:id,nombre', 'sucursal:id,nombre']),
            201
        );
    }

    public function buscarVendedores(Request $request): JsonResponse
    {
        $user = Auth::user();
        $q = trim((string) $request->get('q', ''));

        $items = User::query()
            ->where('empresa_id', $user->empresa_id)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json($items);
    }
}
