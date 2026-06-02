<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'remember' => ['sometimes', 'boolean'], // ✅
        ]);
        $remember = (bool) ($validated['remember'] ?? false); // ✅
        $credentials = [
            'email' => $validated['email'],
            'password' => $validated['password'],
        ];

        if (!Auth::guard('web')->attempt($credentials, $remember)) {
            return response()->json([
                'message' => 'Credenciales inválidas'
            ], 422);
        }

        $user = Auth::guard('web')->user();

        // 🔒 BLOQUEO: usuario sin empresa o sucursal
        if (!$user->empresa_id || !$user->sucursal_id) {
            Auth::guard('web')->logout();

            return response()->json([
                'message' => 'Tu usuario no tiene empresa o sucursal asignada. Contacta al administrador.'
            ], 403);
        }

        // ✅ validar que la sucursal activa esté permitida al usuario
        $permitida = $user->sucursales()->where('sucursales.id', $user->sucursal_id)->exists();

        if (!$permitida) {
            Auth::guard('web')->logout();

            return response()->json([
                'message' => 'Tu sucursal activa no está asignada a tu usuario. Contacta al administrador.'
            ], 403);
        }

        // Regenerar sesión solo si hay sesión activa (requests stateful vía Sanctum SPA).
        // En requests sin sesión (API pura / dev server) esto se omite sin error.
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return $user->load([
            'empresa:id,nombre',
            'sucursal:id,nombre'
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
