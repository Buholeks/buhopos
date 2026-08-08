<?php

namespace App\Http\Controllers\Plataforma;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);

        if (! Auth::guard('plataforma')->attempt(['email' => $data['email'], 'password' => $data['password'], 'activo' => true], $request->boolean('remember'))) {
            return response()->json(['message' => 'Credenciales inválidas.'], 422);
        }

        if ($request->hasSession()) $request->session()->regenerate();
        $admin = Auth::guard('plataforma')->user();
        $admin->update(['ultimo_acceso_en' => now()]);

        return response()->json($admin);
    }

    public function me(): JsonResponse
    {
        return response()->json(Auth::guard('plataforma')->user());
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('plataforma')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
        return response()->json(['ok' => true]);
    }
}
