<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Atributo;
use App\Models\TipoAtributo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AtributoController extends Controller
{
    private function empresaId(): int
    {
        return (int) Auth::user()->empresa_id;
    }

    private function sucursalId(): int
    {
        return (int) Auth::user()->sucursal_id;
    }

    // GET /api/atributos?tipo_atributo_id=1
    public function index(Request $request): JsonResponse
    {
        $query = Atributo::deEmpresa($this->empresaId())
            ->with('tipo')
            ->orderBy('valor');

        if ($request->filled('tipo_atributo_id')) {
            $query->where('tipo_atributo_id', $request->integer('tipo_atributo_id'));
        }

        return response()->json($query->get());
    }

    // POST /api/atributos
    public function store(Request $request): JsonResponse
    {
        $empresaId = $this->empresaId();
        $tipoId    = $request->input('tipo_atributo_id');

        $datos = $request->validate([
            'tipo_atributo_id' => ['required', 'integer', 'exists:tipo_atributos,id'],
            'valor' => [
                'required', 'string', 'min:1', 'max:150',
                // No puede repetirse el mismo valor en el mismo tipo+empresa
                Rule::unique('atributos')->where(fn($q) => $q
                    ->where('empresa_id',        $empresaId)
                    ->where('tipo_atributo_id',  $tipoId)
                    ->whereNull('deleted_at')
                ),
            ],
            'activo' => ['nullable', 'boolean'],
        ], [
            'tipo_atributo_id.required' => 'Debes seleccionar un tipo de atributo.',
            'tipo_atributo_id.exists'   => 'El tipo de atributo no existe.',
            'valor.required'            => 'El valor es obligatorio.',
            'valor.unique'              => 'Ya existe ese valor en este tipo de atributo.',
        ]);

        // Verificar que el tipo pertenezca a la misma empresa
        $tipo = TipoAtributo::deEmpresa($empresaId)->find($tipoId);
        if (! $tipo) {
            return response()->json(['message' => 'El tipo de atributo no pertenece a esta empresa.'], 422);
        }

        $atributo = Atributo::create([
            'empresa_id'       => $empresaId,
            'sucursal_id'      => $this->sucursalId(),
            'user_id'          => Auth::id(),
            'tipo_atributo_id' => $datos['tipo_atributo_id'],
            'valor'            => $datos['valor'],
            'activo'           => $datos['activo'] ?? true,
        ]);

        return response()->json($atributo->load('tipo'), 201);
    }

    // GET /api/atributos/{id}
    public function show(int $id): JsonResponse
    {
        $atributo = Atributo::deEmpresa($this->empresaId())
            ->with('tipo')
            ->findOrFail($id);

        return response()->json($atributo);
    }

    // PUT /api/atributos/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $empresaId = $this->empresaId();
        $atributo  = Atributo::deEmpresa($empresaId)->findOrFail($id);

        $datos = $request->validate([
            'valor' => [
                'required', 'string', 'min:1', 'max:150',
                Rule::unique('atributos')->where(fn($q) => $q
                    ->where('empresa_id',       $empresaId)
                    ->where('tipo_atributo_id', $atributo->tipo_atributo_id)
                    ->whereNull('deleted_at')
                )->ignore($id),
            ],
            'activo' => ['nullable', 'boolean'],
        ], [
            'valor.required' => 'El valor es obligatorio.',
            'valor.unique'   => 'Ya existe ese valor en este tipo de atributo.',
        ]);

        $atributo->valor  = $datos['valor'];
        $atributo->activo = $datos['activo'] ?? $atributo->activo;
        $atributo->save();

        return response()->json($atributo->load('tipo'));
    }

    // DELETE /api/atributos/{id}
    public function destroy(int $id): JsonResponse
    {
        Atributo::deEmpresa($this->empresaId())->findOrFail($id)->delete();

        return response()->json(['mensaje' => 'Atributo eliminado correctamente.']);
    }
}