<?php

namespace App\Http\Controllers\Plataforma;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PlanController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Plan::where('es_prueba', false)->withCount('suscripciones')->orderBy('precio_mensual')->get());
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(Plan::create($this->validar($request)), 201);
    }

    public function update(Request $request, Plan $plan): JsonResponse
    {
        abort_if($plan->es_prueba, 422, 'El plan interno de prueba no se puede editar.');
        $plan->update($this->validar($request));
        return response()->json($plan->fresh());
    }

    private function validar(Request $request): array
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'precio_mensual' => ['required', 'numeric', 'min:0'],
            'precio_anual' => ['nullable', 'numeric', 'min:0.01'],
            'sucursales_incluidas' => ['required', 'integer', 'min:1'],
            'usuarios_incluidos' => ['nullable', 'integer', 'min:1'],
            'precio_sucursal_adicional' => ['required', 'numeric', 'min:0'],
            'activo' => ['required', 'boolean'],
        ]);

        if (($data['precio_anual'] ?? null) !== null && (float) $data['precio_anual'] >= (float) $data['precio_mensual'] * 12) {
            throw ValidationException::withMessages([
                'precio_anual' => 'El precio anual debe ser menor que el costo de doce mensualidades.',
            ]);
        }

        return $data;
    }
}
