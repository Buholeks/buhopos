<?php

namespace App\Http\Controllers;

use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class RolController extends Controller
{
    // ── GET /api/roles ────────────────────────────────────────────────────────

    public function index(): JsonResponse
    {
        $user = Auth::user();

        abort_unless($user->tienePermiso('usuarios.gestionar'), 403, 'Sin permiso para ver roles.');

        $roles = Rol::where('empresa_id', $user->empresa_id)
            ->withCount('permisos')
            ->orderBy('nombre')
            ->get();

        return response()->json($roles);
    }

    // ── GET /api/roles/{rol} ──────────────────────────────────────────────────

    public function show(int $rol): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user->tienePermiso('usuarios.gestionar'), 403);

        $rol = Rol::where('empresa_id', $user->empresa_id)->with('permisos')->findOrFail($rol);

        return response()->json($rol);
    }

    // ── POST /api/roles ───────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        abort_unless($user->tienePermiso('usuarios.gestionar'), 403, 'Sin permiso para crear roles.');

        $data = $request->validate([
            'nombre'      => [
                'required', 'string', 'min:2', 'max:100',
                Rule::unique('roles')->where(fn($q) => $q->where('empresa_id', $user->empresa_id)),
            ],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'permisos'    => ['nullable', 'array'],
            'permisos.*'  => ['integer', 'exists:permisos,id'],
        ], [
            'nombre.unique' => 'Ya existe un rol con ese nombre en esta empresa.',
        ]);

        $rol = Rol::create([
            'empresa_id'  => $user->empresa_id,
            'nombre'      => $data['nombre'],
            'descripcion' => $data['descripcion'] ?? null,
        ]);

        if (! empty($data['permisos'])) {
            $rol->sincronizarPermisos($data['permisos']);
        }

        $rol->load('permisos');

        return response()->json($rol, 201);
    }

    // ── PUT /api/roles/{rol} ──────────────────────────────────────────────────

    public function update(Request $request, int $rol): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user->tienePermiso('usuarios.gestionar'), 403);

        $rol = Rol::where('empresa_id', $user->empresa_id)->findOrFail($rol);

        $data = $request->validate([
            'nombre'      => [
                'required', 'string', 'min:2', 'max:100',
                Rule::unique('roles')
                    ->where(fn($q) => $q->where('empresa_id', $user->empresa_id))
                    ->ignore($rol->id),
            ],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'permisos'    => ['nullable', 'array'],
            'permisos.*'  => ['integer', 'exists:permisos,id'],
        ], [
            'nombre.unique' => 'Ya existe un rol con ese nombre en esta empresa.',
        ]);

        $rol->update([
            'nombre'      => $data['nombre'],
            'descripcion' => $data['descripcion'] ?? null,
        ]);

        $rol->sincronizarPermisos($data['permisos'] ?? []);
        $rol->load('permisos');

        return response()->json($rol);
    }

    // ── DELETE /api/roles/{rol} ───────────────────────────────────────────────

    public function destroy(int $rol): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user->tienePermiso('usuarios.gestionar'), 403);

        $rol = Rol::where('empresa_id', $user->empresa_id)->findOrFail($rol);

        // No se puede eliminar un rol que tenga usuarios asignados
        $enUso = \App\Models\SucursalUser::where('role_id', $rol->id)->exists();
        if ($enUso) {
            return response()->json([
                'message' => 'No se puede eliminar el rol porque hay usuarios asignados a él.',
            ], 422);
        }

        $rol->delete();

        return response()->json(['message' => 'Rol eliminado.']);
    }

    // ── GET /api/permisos ─────────────────────────────────────────────────────
    // Lista todos los permisos del sistema agrupados por módulo (para el checklist)

    public function listarPermisos(): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('usuarios.gestionar'), 403);

        $permisos = Permiso::orderBy('modulo')->orderBy('descripcion')->get();

        $agrupados = $permisos->groupBy('modulo')->map(fn($grupo) => $grupo->values());

        return response()->json($agrupados);
    }
}
