<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    // ── /api/me ───────────────────────────────────────────────────────────────

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

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

        $user->load(['empresa:id,nombre,logo', 'sucursal:id,nombre']);

        $rol = $user->rolEnSucursal((int) $user->sucursal_id);

        $empresa = $user->empresa;

        return response()->json([
            ...$user->toArray(),
            'rol'      => $rol?->nombre,
            'permisos' => $user->permisosActivos(),
            'empresa'  => $empresa ? array_merge($empresa->toArray(), [
                'logo_url' => $empresa->logo ? Storage::disk('public')->url($empresa->logo) : null,
            ]) : null,
        ]);
    }

    // ── /api/register ─────────────────────────────────────────────────────────

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'empresa_nombre'    => ['required', 'string', 'min:2', 'max:255'],
            'empresa_correo'    => ['nullable', 'email', 'max:255'],
            'empresa_telefono'  => ['nullable', 'string', 'max:20'],
            'sucursal_nombre'   => ['required', 'string', 'min:2', 'max:255'],
            'sucursal_direccion'=> ['nullable', 'string', 'max:255'],
            'usuario_nombre'    => ['required', 'string', 'min:2', 'max:255'],
            'email'             => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'          => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'empresa_nombre.required'   => 'El nombre de la empresa es obligatorio.',
            'sucursal_nombre.required'  => 'El nombre de la sucursal es obligatorio.',
            'usuario_nombre.required'   => 'Tu nombre es obligatorio.',
            'email.unique'              => 'Ya existe una cuenta con ese correo.',
            'password.confirmed'        => 'La confirmación de contraseña no coincide.',
            'password.min'              => 'La contraseña debe tener al menos 8 caracteres.',
        ]);

        DB::transaction(function () use ($validated) {
            $empresa = Empresa::create([
                'nombre'   => $validated['empresa_nombre'],
                'correo'   => $validated['empresa_correo'] ?? null,
                'telefono' => $validated['empresa_telefono'] ?? null,
                'activo'   => true,
            ]);

            $sucursal = Sucursal::create([
                'empresa_id' => $empresa->id,
                'nombre'     => $validated['sucursal_nombre'],
                'direccion'  => $validated['sucursal_direccion'] ?? null,
                'correo'     => $validated['empresa_correo'] ?? null,
                'telefono'   => $validated['empresa_telefono'] ?? null,
                'activo'     => true,
            ]);

            // El primer usuario de la empresa es siempre super admin.
            // El observer SucursalObserver ya asignó la sucursal al super admin.
            $user = User::create([
                'empresa_id'     => $empresa->id,
                'sucursal_id'    => $sucursal->id,
                'name'           => $validated['usuario_nombre'],
                'email'          => $validated['email'],
                'password'       => $validated['password'],
                'activo'         => false, // pendiente de activación manual
                'es_super_admin' => true,
            ]);

            // El observer ya adjuntó la sucursal al crear la empresa+sucursal,
            // pero el user aún no existía en ese momento (se crea después).
            // Por eso lo adjuntamos explícitamente aquí.
            $user->sucursales()->syncWithoutDetaching([$sucursal->id]);
        });

        return response()->json([
            'message' => 'Registro recibido. Tu cuenta queda pendiente de activación manual.',
        ], 201);
    }

    // ── /api/login ────────────────────────────────────────────────────────────

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
            'remember' => ['sometimes', 'boolean'],
        ]);

        $remember     = (bool) ($validated['remember'] ?? false);
        $credentials  = ['email' => $validated['email'], 'password' => $validated['password']];

        if (! Auth::guard('web')->attempt($credentials, $remember)) {
            return response()->json(['message' => 'Credenciales inválidas'], 422);
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

        $user->load(['empresa:id,nombre,logo', 'sucursal:id,nombre']);
        $rol = $user->rolEnSucursal((int) $user->sucursal_id);

        $empresa = $user->empresa;

        return response()->json([
            ...$user->toArray(),
            'rol'      => $rol?->nombre,
            'permisos' => $user->permisosActivos(),
            'empresa'  => $empresa ? array_merge($empresa->toArray(), [
                'logo_url' => $empresa->logo ? Storage::disk('public')->url($empresa->logo) : null,
            ]) : null,
        ]);
    }

    // ── /api/logout ───────────────────────────────────────────────────────────

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['ok' => true]);
    }
}
