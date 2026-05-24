<?php

namespace App\Http\Controllers;

use App\Models\Proveedor;
use Illuminate\Http\Request;

class ProveedorController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();

        $q = Proveedor::where('empresa_id', $u->empresa_id)
            ->orderBy('nombre_comercial');

        if ($search = trim($request->q)) {
            $words = preg_split('/\s+/', $search);

            $q->where(function ($query) use ($words) {
                foreach ($words as $word) {
                    $query->where(function ($w) use ($word) {
                        $w->where('nombre_comercial', 'like', "%{$word}%")
                            ->orWhere('razon_social', 'like', "%{$word}%")
                            ->orWhere('telefono', 'like', "%{$word}%");
                    });
                }
            });
        }


        return response()->json($q->paginate(15));
    }

    public function store(Request $request)
    {
        $u = $request->user();

        $data = $request->validate([
            'nombre_comercial' => 'required|string|max:255',
            'razon_social' => 'nullable|string|max:255',
            'rfc' => 'nullable|string|max:13',

            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'contacto' => 'nullable|string|max:255',

            'calle' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:50',
            'colonia' => 'nullable|string|max:255',
            'ciudad' => 'nullable|string|max:255',
            'estado' => 'nullable|string|max:255',
            'cp' => 'nullable|string|max:10',

            'sitio_web' => 'nullable|string|max:255',
            'activo' => 'boolean',

            'credito_activo' => 'boolean',
            'dias_credito_default' => 'nullable|integer|min:0',
            'limite_credito' => 'nullable|numeric|min:0',
        ]);

        $proveedor = Proveedor::create([
            'empresa_id' => $u->empresa_id,
            'sucursal_id' => $u->sucursal_id,
            'user_id' => $u->id,
            ...$data
        ]);

        return response()->json($proveedor, 201);
        
    }

    public function show(Request $request, $id)
    {
        $u = $request->user();

        return Proveedor::where('id', $id)
            ->where('empresa_id', $u->empresa_id)
            ->firstOrFail();
    }

    public function update(Request $request, $id)
    {
        $u = $request->user();

        $proveedor = Proveedor::where('id', $id)
            ->where('empresa_id', $u->empresa_id)
            ->firstOrFail();

        $data = $request->validate([
            'nombre_comercial' => 'required|string|max:255',
            'razon_social' => 'nullable|string|max:255',
            'rfc' => 'nullable|string|max:13',

            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'contacto' => 'nullable|string|max:255',

            'calle' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:50',
            'colonia' => 'nullable|string|max:255',
            'ciudad' => 'nullable|string|max:255',
            'estado' => 'nullable|string|max:255',
            'cp' => 'nullable|string|max:10',

            'sitio_web' => 'nullable|string|max:255',
            'activo' => 'boolean',

            'credito_activo' => 'boolean',
            'dias_credito_default' => 'nullable|integer|min:0',
            'limite_credito' => 'nullable|numeric|min:0',
        ]);

        $proveedor->update($data);

        return response()->json($proveedor);
    }

    public function destroy(Request $request, $id)
    {
        $u = $request->user();

        $proveedor = Proveedor::where('id', $id)
            ->where('empresa_id', $u->empresa_id)
            ->where('sucursal_id', $u->sucursal_id)
            ->firstOrFail();

        $proveedor->delete();

        return response()->json(['ok' => true]);
    }
}
