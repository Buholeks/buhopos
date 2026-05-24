<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\UnidadMedida;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UnidadMedidaController extends Controller
{
    private function empresaId(): int
    {
        return (int) Auth::user()->empresa_id;
    }

    private function sucursalId(): int
    {
        return (int) Auth::user()->sucursal_id;
    }

    private function tipos(): array
    {
        return array_keys(UnidadMedida::TIPOS);
    }

    // GET /api/unidades-medida
    // Acepta ?tipo=peso para filtrar
    public function index(Request $request): JsonResponse
    {
        $query = UnidadMedida::deEmpresa($this->empresaId())
            ->orderBy('tipo')
            ->orderBy('nombre');

        if ($request->filled('tipo')) {
            $query->deTipo($request->string('tipo'));
        }

        return response()->json($query->get());
    }

    // POST /api/unidades-medida
    public function store(Request $request): JsonResponse
    {
        $empresaId = $this->empresaId();

        $datos = $request->validate([
            'nombre' => [
                'required', 'string', 'min:2', 'max:100',
                Rule::unique('unidades_medida')->where(fn($q) => $q
                    ->where('empresa_id', $empresaId)
                    ->whereNull('deleted_at')
                ),
            ],
            'abreviatura' => [
                'required', 'string', 'min:1', 'max:20',
                Rule::unique('unidades_medida')->where(fn($q) => $q
                    ->where('empresa_id', $empresaId)
                    ->whereNull('deleted_at')
                ),
            ],
            'tipo'   => ['required', Rule::in($this->tipos())],
            'activo' => ['nullable', 'boolean'],
        ], [
            'nombre.required'      => 'El nombre es obligatorio.',
            'nombre.unique'        => 'Ya existe una unidad con ese nombre.',
            'abreviatura.required' => 'La abreviatura es obligatoria.',
            'abreviatura.unique'   => 'Ya existe una unidad con esa abreviatura.',
            'tipo.required'        => 'El tipo es obligatorio.',
            'tipo.in'              => 'El tipo no es válido.',
        ]);

        $unidad = UnidadMedida::create([
            'empresa_id'  => $empresaId,
            'sucursal_id' => $this->sucursalId(),
            'user_id'     => Auth::id(),
            'nombre'      => $datos['nombre'],
            'abreviatura' => strtolower($datos['abreviatura']),
            'tipo'        => $datos['tipo'],
            'activo'      => $datos['activo'] ?? true,
        ]);

        return response()->json($unidad, 201);
    }

    // GET /api/unidades-medida/{id}
    public function show(int $id): JsonResponse
    {
        return response()->json(
            UnidadMedida::deEmpresa($this->empresaId())->findOrFail($id)
        );
    }

    // PUT /api/unidades-medida/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $empresaId = $this->empresaId();
        $unidad    = UnidadMedida::deEmpresa($empresaId)->findOrFail($id);

        $datos = $request->validate([
            'nombre' => [
                'required', 'string', 'min:2', 'max:100',
                Rule::unique('unidades_medida')->where(fn($q) => $q
                    ->where('empresa_id', $empresaId)
                    ->whereNull('deleted_at')
                )->ignore($id),
            ],
            'abreviatura' => [
                'required', 'string', 'min:1', 'max:20',
                Rule::unique('unidades_medida')->where(fn($q) => $q
                    ->where('empresa_id', $empresaId)
                    ->whereNull('deleted_at')
                )->ignore($id),
            ],
            'tipo'   => ['required', Rule::in($this->tipos())],
            'activo' => ['nullable', 'boolean'],
        ], [
            'nombre.required'      => 'El nombre es obligatorio.',
            'nombre.unique'        => 'Ya existe una unidad con ese nombre.',
            'abreviatura.required' => 'La abreviatura es obligatoria.',
            'abreviatura.unique'   => 'Ya existe una unidad con esa abreviatura.',
            'tipo.required'        => 'El tipo es obligatorio.',
            'tipo.in'              => 'El tipo no es válido.',
        ]);

        $unidad->nombre      = $datos['nombre'];
        $unidad->abreviatura = strtolower($datos['abreviatura']);
        $unidad->tipo        = $datos['tipo'];
        $unidad->activo      = $datos['activo'] ?? $unidad->activo;
        $unidad->save();

        return response()->json($unidad);
    }

    // DELETE /api/unidades-medida/{id}
    public function destroy(int $id): JsonResponse
    {
        UnidadMedida::deEmpresa($this->empresaId())->findOrFail($id)->delete();

        return response()->json(['message' => 'Unidad de medida eliminada correctamente.']);
    }

    // GET /api/unidades-medida/tipos
    // Devuelve el catálogo de tipos disponibles para poblar selects en Vue
    public function listarTipos(): JsonResponse
    {
        return response()->json(
            collect(UnidadMedida::TIPOS)->map(fn($label, $valor) => [
                'valor' => $valor,
                'label' => $label,
            ])->values()
        );
    }
}