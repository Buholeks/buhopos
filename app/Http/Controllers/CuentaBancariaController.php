<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CuentaBancaria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CuentaBancariaController extends Controller
{
    private function empresaId(): int
    {
        return (int) Auth::user()->empresa_id;
    }

    private function sucursalId(): int
    {
        return (int) Auth::user()->sucursal_id;
    }

    // GET /api/cuentas-bancarias
    // Acepta ?activo=1 para poblar selects
    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('catalogos.ver'), 403, 'Sin permiso: catalogos.ver');

        $query = CuentaBancaria::deEmpresa($this->empresaId())->orderBy('nombre');

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

        $cuentas = CuentaBancaria::deEmpresa($this->empresaId())
            ->activas()
            ->when($q !== '', fn($query) => $query->where('nombre', 'like', "{$q}%"))
            ->orderBy('nombre')
            ->limit(20)
            ->get(['id', 'nombre', 'banco']);

        return response()->json($cuentas);
    }

    public function restore(int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('catalogos.editar'), 403, 'Sin permiso: catalogos.editar');
        $cuenta = CuentaBancaria::withTrashed()->where('empresa_id', $this->empresaId())->findOrFail($id);
        $cuenta->restore();
        return response()->json($cuenta);
    }

    // POST /api/cuentas-bancarias
    public function store(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('catalogos.editar'), 403, 'Sin permiso: catalogos.editar');
        $empresaId = $this->empresaId();

        $eliminada = CuentaBancaria::withTrashed()
            ->where('empresa_id', $empresaId)
            ->where('nombre', $request->input('nombre'))
            ->whereNotNull('deleted_at')
            ->first();

        if ($eliminada) {
            return response()->json([
                'recoverable' => true,
                'id'          => $eliminada->id,
                'nombre'      => $eliminada->nombre,
                'message'     => "Ya existe una cuenta bancaria eliminada con ese nombre. ¿Deseas recuperarla?",
            ], 409);
        }

        $datos = $request->validate([
            'nombre' => [
                'required', 'string', 'min:2', 'max:150',
                Rule::unique('cuentas_bancarias')->where(fn($q) => $q
                    ->where('empresa_id', $empresaId)
                    ->whereNull('deleted_at')
                ),
            ],
            'banco'         => ['nullable', 'string', 'max:100'],
            'numero_cuenta' => ['nullable', 'string', 'max:30'],
            'clabe'         => ['nullable', 'string', 'max:18'],
            'titular'       => ['nullable', 'string', 'max:150'],
            'activo'        => ['nullable', 'boolean'],
        ], [
            'nombre.required' => 'El nombre de la cuenta es obligatorio.',
            'nombre.unique'   => 'Ya existe una cuenta bancaria con ese nombre en esta empresa.',
        ]);

        $cuenta = CuentaBancaria::create([
            'empresa_id'    => $empresaId,
            'sucursal_id'   => $this->sucursalId(),
            'user_id'       => Auth::id(),
            'nombre'        => $datos['nombre'],
            'banco'         => $datos['banco'] ?? null,
            'numero_cuenta' => $datos['numero_cuenta'] ?? null,
            'clabe'         => $datos['clabe'] ?? null,
            'titular'       => $datos['titular'] ?? null,
            'activo'        => $datos['activo'] ?? true,
        ]);

        return response()->json($cuenta, 201);
    }

    // GET /api/cuentas-bancarias/{id}
    public function show(int $id): JsonResponse
    {
        return response()->json(
            CuentaBancaria::deEmpresa($this->empresaId())->findOrFail($id)
        );
    }

    // PUT /api/cuentas-bancarias/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('catalogos.editar'), 403, 'Sin permiso: catalogos.editar');
        $empresaId = $this->empresaId();
        $cuenta    = CuentaBancaria::deEmpresa($empresaId)->findOrFail($id);

        $datos = $request->validate([
            'nombre' => [
                'required', 'string', 'min:2', 'max:150',
                Rule::unique('cuentas_bancarias')->where(fn($q) => $q
                    ->where('empresa_id', $empresaId)
                    ->whereNull('deleted_at')
                )->ignore($id),
            ],
            'banco'         => ['nullable', 'string', 'max:100'],
            'numero_cuenta' => ['nullable', 'string', 'max:30'],
            'clabe'         => ['nullable', 'string', 'max:18'],
            'titular'       => ['nullable', 'string', 'max:150'],
            'activo'        => ['nullable', 'boolean'],
        ], [
            'nombre.required' => 'El nombre de la cuenta es obligatorio.',
            'nombre.unique'   => 'Ya existe una cuenta bancaria con ese nombre en esta empresa.',
        ]);

        $cuenta->nombre        = $datos['nombre'];
        $cuenta->banco         = $datos['banco'] ?? null;
        $cuenta->numero_cuenta = $datos['numero_cuenta'] ?? null;
        $cuenta->clabe         = $datos['clabe'] ?? null;
        $cuenta->titular       = $datos['titular'] ?? null;
        $cuenta->activo        = $datos['activo'] ?? $cuenta->activo;
        $cuenta->save();

        return response()->json($cuenta);
    }

    // DELETE /api/cuentas-bancarias/{id}
    public function destroy(int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('catalogos.editar'), 403, 'Sin permiso: catalogos.editar');
        CuentaBancaria::deEmpresa($this->empresaId())->findOrFail($id)->delete();

        return response()->json(['message' => 'Cuenta bancaria eliminada correctamente.']);
    }
}
