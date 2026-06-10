<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SucursalActivaController extends Controller
{
    public function misSucursales(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(
            $user->sucursales()
                ->select('sucursales.id', 'sucursales.nombre', 'sucursales.empresa_id')
                ->orderBy('sucursales.nombre')
                ->get()
        );
    }

    public function cambiarSucursal(Request $request): JsonResponse
    {
        $request->validate(['sucursal_id' => 'required|integer']);

        $user       = $request->user();
        $sucursalId = (int) $request->sucursal_id;

        $permitida = $user->sucursales()->where('sucursales.id', $sucursalId)->exists();

        if (! $permitida) {
            return response()->json(['message' => 'No tienes acceso a esa sucursal.'], 403);
        }

        $user->sucursal_id = $sucursalId;
        $user->save();

        $user->load(['empresa:id,nombre', 'sucursal:id,nombre']);

        // Devolver también los permisos del nuevo contexto de sucursal
        $rol = $user->rolEnSucursal($sucursalId);

        return response()->json([
            ...$user->toArray(),
            'rol'      => $rol?->nombre,
            'permisos' => $user->permisosActivos(),
        ]);
    }
}
