<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TipoAtributo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TipoAtributoController extends Controller
{
    private function empresaId(): int
    {
        return (int) Auth::user()->empresa_id;
    }

    private function sucursalId(): int
    {
        return (int) Auth::user()->sucursal_id;
    }

    // GET /api/tipo-atributos
    // Devuelve todos los tipos con sus valores anidados
    public function index(): JsonResponse
    {
        $tipos = TipoAtributo::deEmpresa($this->empresaId())
            ->with('atributos')
            ->orderBy('nombre')
            ->get();

        return response()->json($tipos);
    }

    // POST /api/tipo-atributos
    public function store(Request $request): JsonResponse
    {
        $empresaId = $this->empresaId();

        $datos = $request->validate([
            'nombre' => [
                'required',
                'string',
                'min:2',
                'max:100',
                Rule::unique('tipo_atributos')->where(
                    fn($q) => $q
                        ->where('empresa_id', $empresaId)
                        ->whereNull('deleted_at')
                ),
            ],
            'activo' => ['nullable', 'boolean'],
        ], [
            'nombre.required' => 'El nombre del tipo es obligatorio.',
            'nombre.unique'   => 'Ya existe un tipo de atributo con ese nombre.',
        ]);

        $tipo = TipoAtributo::create([
            'empresa_id'  => $empresaId,
            'sucursal_id' => $this->sucursalId(),
            'user_id'     => Auth::id(),
            'nombre'      => $datos['nombre'],
            'activo'      => $datos['activo'] ?? true,
        ]);

        return response()->json($tipo->load('atributos'), 201);
    }

    // GET /api/tipo-atributos/{id}
    public function show(int $id): JsonResponse
    {
        $tipo = TipoAtributo::deEmpresa($this->empresaId())
            ->with('atributos')
            ->findOrFail($id);

        return response()->json($tipo);
    }

    // PUT /api/tipo-atributos/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $empresaId = $this->empresaId();
        $tipo      = TipoAtributo::deEmpresa($empresaId)->findOrFail($id);

        $datos = $request->validate([
            'nombre' => [
                'required',
                'string',
                'min:2',
                'max:100',
                Rule::unique('tipo_atributos')->where(
                    fn($q) => $q
                        ->where('empresa_id', $empresaId)
                        ->whereNull('deleted_at')
                )->ignore($id),
            ],
            'activo' => ['nullable', 'boolean'],
        ], [
            'nombre.required' => 'El nombre del tipo es obligatorio.',
            'nombre.unique'   => 'Ya existe un tipo de atributo con ese nombre.',
        ]);

        $tipo->nombre = $datos['nombre'];
        $tipo->activo = $datos['activo'] ?? $tipo->activo;
        $tipo->save();

        return response()->json($tipo->load('atributos'));
    }

    // DELETE /api/tipo-atributos/{id}
    // Elimina el tipo y todos sus valores (cascadeOnDelete en la FK)
    public function destroy(int $id): JsonResponse
    {
        $tipo = TipoAtributo::deEmpresa($this->empresaId())
            ->with('atributos')
            ->findOrFail($id);

        // Soft delete manual de los atributos hijos (cascadeOnDelete es físico,
        // pero como usamos SoftDeletes preferimos hacerlo así)
        $tipo->atributos()->each(fn($a) => $a->delete());
        $tipo->delete();

        return response()->json(['mensaje' => 'Tipo de atributo eliminado correctamente.']);
    }
}
