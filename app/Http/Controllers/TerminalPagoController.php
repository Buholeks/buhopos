<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TerminalPago;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TerminalPagoController extends Controller
{
    private function empresaId(): int
    {
        return (int) Auth::user()->empresa_id;
    }

    private function sucursalId(): int
    {
        return (int) Auth::user()->sucursal_id;
    }

    // GET /api/terminales-pago
    // Acepta ?activo=1 para poblar selects
    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('catalogos.ver'), 403, 'Sin permiso: catalogos.ver');

        $query = TerminalPago::deEmpresa($this->empresaId())->orderBy('nombre');

        if ($request->boolean('activo')) {
            $query->activas();
        }

        return response()->json($query->get());
    }

    public function buscar(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('catalogos.ver'), 403, 'Sin permiso: catalogos.ver');
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
        ]);

        $q = trim($data['q'] ?? '');

        $terminales = TerminalPago::deEmpresa($this->empresaId())
            ->activas()
            ->when($q !== '', fn($query) => $query->where('nombre', 'like', "{$q}%"))
            ->orderBy('nombre')
            ->limit(20)
            ->get(['id', 'nombre', 'banco']);

        return response()->json($terminales);
    }

    public function restore(int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('catalogos.editar'), 403, 'Sin permiso: catalogos.editar');
        $terminal = TerminalPago::withTrashed()->where('empresa_id', $this->empresaId())->findOrFail($id);
        $terminal->restore();
        return response()->json($terminal);
    }

    // POST /api/terminales-pago
    public function store(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('catalogos.editar'), 403, 'Sin permiso: catalogos.editar');
        $empresaId = $this->empresaId();

        $eliminada = TerminalPago::withTrashed()
            ->where('empresa_id', $empresaId)
            ->where('nombre', $request->input('nombre'))
            ->whereNotNull('deleted_at')
            ->first();

        if ($eliminada) {
            return response()->json([
                'recoverable' => true,
                'id'          => $eliminada->id,
                'nombre'      => $eliminada->nombre,
                'message'     => "Ya existe una terminal eliminada con ese nombre. ¿Deseas recuperarla?",
            ], 409);
        }

        $datos = $request->validate([
            'nombre' => [
                'required', 'string', 'min:2', 'max:150',
                Rule::unique('terminales_pago')->where(fn($q) => $q
                    ->where('empresa_id', $empresaId)
                    ->whereNull('deleted_at')
                ),
            ],
            'banco'  => ['nullable', 'string', 'max:100'],
            'activo' => ['nullable', 'boolean'],
        ], [
            'nombre.required' => 'El nombre de la terminal es obligatorio.',
            'nombre.unique'   => 'Ya existe una terminal con ese nombre en esta empresa.',
        ]);

        $terminal = TerminalPago::create([
            'empresa_id'  => $empresaId,
            'sucursal_id' => $this->sucursalId(),
            'user_id'     => Auth::id(),
            'nombre'      => $datos['nombre'],
            'banco'       => $datos['banco'] ?? null,
            'activo'      => $datos['activo'] ?? true,
        ]);

        return response()->json($terminal, 201);
    }

    // GET /api/terminales-pago/{id}
    public function show(int $id): JsonResponse
    {
        return response()->json(
            TerminalPago::deEmpresa($this->empresaId())->findOrFail($id)
        );
    }

    // PUT /api/terminales-pago/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('catalogos.editar'), 403, 'Sin permiso: catalogos.editar');
        $empresaId = $this->empresaId();
        $terminal  = TerminalPago::deEmpresa($empresaId)->findOrFail($id);

        $datos = $request->validate([
            'nombre' => [
                'required', 'string', 'min:2', 'max:150',
                Rule::unique('terminales_pago')->where(fn($q) => $q
                    ->where('empresa_id', $empresaId)
                    ->whereNull('deleted_at')
                )->ignore($id),
            ],
            'banco'  => ['nullable', 'string', 'max:100'],
            'activo' => ['nullable', 'boolean'],
        ], [
            'nombre.required' => 'El nombre de la terminal es obligatorio.',
            'nombre.unique'   => 'Ya existe una terminal con ese nombre en esta empresa.',
        ]);

        $terminal->nombre = $datos['nombre'];
        $terminal->banco  = $datos['banco'] ?? null;
        $terminal->activo = $datos['activo'] ?? $terminal->activo;
        $terminal->save();

        return response()->json($terminal);
    }

    // DELETE /api/terminales-pago/{id}
    public function destroy(int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('catalogos.editar'), 403, 'Sin permiso: catalogos.editar');
        TerminalPago::deEmpresa($this->empresaId())->findOrFail($id)->delete();

        return response()->json(['message' => 'Terminal eliminada correctamente.']);
    }
}
