<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function user(Request $request)
    {
        $user = $request->user();

        if (! $user->activo) {
            Auth::guard('web')->logout();

            return response()->json([
                'message' => 'Tu cuenta está pendiente de activación. Contacta al administrador.',
            ], 403);
        }

        return response()->json($user);
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'empresa_nombre' => ['required', 'string', 'min:2', 'max:255'],
            'empresa_correo' => ['nullable', 'email', 'max:255'],
            'empresa_telefono' => ['nullable', 'string', 'max:20'],
            'sucursal_nombre' => ['required', 'string', 'min:2', 'max:255'],
            'sucursal_direccion' => ['nullable', 'string', 'max:255'],
            'usuario_nombre' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'empresa_nombre.required' => 'El nombre de la empresa es obligatorio.',
            'sucursal_nombre.required' => 'El nombre de la sucursal es obligatorio.',
            'usuario_nombre.required' => 'Tu nombre es obligatorio.',
            'email.unique' => 'Ya existe una cuenta con ese correo.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ]);

        DB::transaction(function () use ($validated) {
            $empresa = Empresa::create([
                'nombre' => $validated['empresa_nombre'],
                'correo' => $validated['empresa_correo'] ?? null,
                'telefono' => $validated['empresa_telefono'] ?? null,
                'activo' => true,
            ]);

            $sucursal = Sucursal::create([
                'empresa_id' => $empresa->id,
                'nombre' => $validated['sucursal_nombre'],
                'direccion' => $validated['sucursal_direccion'] ?? null,
                'correo' => $validated['empresa_correo'] ?? null,
                'telefono' => $validated['empresa_telefono'] ?? null,
                'activo' => true,
            ]);

            $user = User::create([
                'empresa_id' => $empresa->id,
                'sucursal_id' => $sucursal->id,
                'name' => $validated['usuario_nombre'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'activo' => false,
            ]);

            $user->sucursales()->attach($sucursal->id);
        });

        return response()->json([
            'message' => 'Registro recibido. Tu cuenta queda pendiente de activación manual.',
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'remember' => ['sometimes', 'boolean'],
        ]);

        $remember = (bool) ($validated['remember'] ?? false);
        $credentials = [
            'email' => $validated['email'],
            'password' => $validated['password'],
        ];

        if (! Auth::guard('web')->attempt($credentials, $remember)) {
            return response()->json([
                'message' => 'Credenciales inválidas',
            ], 422);
        }

        $user = Auth::guard('web')->user();

        if (! $user->activo) {
            Auth::guard('web')->logout();

            return response()->json([
                'message' => 'Tu cuenta está pendiente de activación. Contacta al administrador.',
            ], 403);
        }

        if (! $user->empresa_id || ! $user->sucursal_id) {
            Auth::guard('web')->logout();

            return response()->json([
                'message' => 'Tu usuario no tiene empresa o sucursal asignada. Contacta al administrador.',
            ], 403);
        }

        $permitida = $user->sucursales()->where('sucursales.id', $user->sucursal_id)->exists();

        if (! $permitida) {
            Auth::guard('web')->logout();

            return response()->json([
                'message' => 'Tu sucursal activa no está asignada a tu usuario. Contacta al administrador.',
            ], 403);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return $user->load([
            'empresa:id,nombre',
            'sucursal:id,nombre',
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['ok' => true]);
    }
}
