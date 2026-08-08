<?php

namespace App\Http\Controllers\Plataforma;

use App\Http\Controllers\Controller;
use App\Models\CodigoPromocional;
use App\Models\Empresa;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CodigoPromocionalController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'codigos' => CodigoPromocional::with(['plan:id,nombre', 'empresaDestino:id,nombre', 'empresaCanje:id,nombre'])->latest()->get(),
            'planes' => Plan::where('activo', true)->orderBy('precio_mensual')->get(['id', 'nombre']),
            'empresas' => Empresa::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'codigo' => ['nullable', 'string', 'min:4', 'max:40', 'alpha_dash', 'unique:codigos_promocionales,codigo'],
            'descripcion' => ['nullable', 'string', 'max:255'], 'plan_id' => ['required', 'exists:planes,id'],
            'empresa_destino_id' => ['nullable', 'exists:empresas,id'], 'duracion_tipo' => ['required', Rule::in(['dias', 'meses'])],
            'duracion_cantidad' => ['required', 'integer', 'min:1', 'max:365'], 'expira_en' => ['nullable', 'date', 'after:now'],
        ]);
        $data['codigo'] = strtoupper($data['codigo'] ?? $this->generarCodigo());
        $data['creado_por_id'] = $request->user('plataforma')->id;

        return response()->json(CodigoPromocional::create($data)->load(['plan:id,nombre', 'empresaDestino:id,nombre']), 201);
    }

    public function update(Request $request, CodigoPromocional $codigo): JsonResponse
    {
        abort_if($codigo->canjeado_en, 422, 'Un código canjeado ya no puede modificarse.');
        $data = $request->validate(['activo' => ['required', 'boolean']]);
        $codigo->update($data);
        return response()->json($codigo->fresh());
    }

    private function generarCodigo(): string
    {
        do { $codigo = 'BUHO-'.strtoupper(Str::random(10)); } while (CodigoPromocional::where('codigo', $codigo)->exists());
        return $codigo;
    }
}
