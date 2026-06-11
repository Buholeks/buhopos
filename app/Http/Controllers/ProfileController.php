<?php

namespace App\Http\Controllers;

use App\Models\Sucursal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->profileData($request));
    }

    public function updateUser(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'current_password' => ['nullable', 'required_with:password', 'string'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ], [
            'email.unique' => 'Ya existe un usuario con ese correo.',
            'current_password.required_with' => 'Ingresa tu contraseña actual para cambiarla.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
            'password.min' => 'La nueva contraseña debe tener al menos 8 caracteres.',
        ]);

        if (! empty($data['password']) && ! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'La contraseña actual no es correcta.',
            ]);
        }

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            ...(! empty($data['password']) ? ['password' => $data['password']] : []),
        ]);

        return response()->json($this->profileData($request));
    }

    public function updateEmpresa(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->tienePermiso('empresa.editar'), 403, 'Sin permiso para editar la empresa.');

        $data = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:255'],
            'propietario' => ['nullable', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'correo' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'rfc' => ['nullable', 'string', 'max:20'],
        ]);

        $user->empresa()->firstOrFail()->update($data);

        return response()->json($this->profileData($request));
    }

    public function updateSucursal(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->tienePermiso('sucursales.editar'), 403, 'Sin permiso para editar la sucursal.');

        $data = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'correo' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
        ]);

        Sucursal::query()
            ->whereKey($user->sucursal_id)
            ->where('empresa_id', $user->empresa_id)
            ->firstOrFail()
            ->update($data);

        return response()->json($this->profileData($request));
    }

    private function profileData(Request $request): array
    {
        $user = $request->user()->fresh([
            'empresa:id,nombre,propietario,direccion,correo,telefono,rfc',
            'sucursal:id,empresa_id,nombre,direccion,correo,telefono',
        ]);
        $rol = $user->rolEnSucursal((int) $user->sucursal_id);

        return [
            ...$user->toArray(),
            'rol' => $rol?->nombre,
            'permisos' => $user->permisosActivos(),
            'puede_editar_empresa' => $user->tienePermiso('empresa.editar'),
            'puede_editar_sucursal' => $user->tienePermiso('sucursales.editar'),
        ];
    }
}
