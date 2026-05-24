<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sucursal;
use Illuminate\Http\Request;

class SucursalActivaController extends Controller
{
    public function misSucursales(Request $request)
    {
        $user = $request->user();

        // Devuelve solo las permitidas
        return $user->sucursales()
            ->select('sucursales.id', 'sucursales.nombre', 'sucursales.empresa_id')
            ->orderBy('sucursales.nombre')
            ->get();
    }

    public function cambiarSucursal(Request $request)
    {
        $request->validate([
            'sucursal_id' => 'required|integer',
        ]);

        $user = $request->user();
        $sucursalId = (int) $request->sucursal_id;

        // ✅ Validar que esa sucursal esté asignada al usuario (pivot)
        $permitida = $user->sucursales()->where('sucursales.id', $sucursalId)->exists();

        if (!$permitida) {
            return response()->json([
                'message' => 'No tienes acceso a esa sucursal.'
            ], 403);
        }

        // (Opcional) validar que sea de la misma empresa del usuario
        // $sucursal = Sucursal::findOrFail($sucursalId);
        // if ($sucursal->empresa_id !== $user->empresa_id) { ... }

        // ✅ Setear sucursal activa (por defecto al loguear)
        $user->sucursal_id = $sucursalId;
        $user->save();

        // Regresar el user ya “refrescado”
        return $user->load([
            'empresa:id,nombre',
            'sucursal:id,nombre'
        ]);
    }
}
