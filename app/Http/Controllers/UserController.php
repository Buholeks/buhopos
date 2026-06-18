<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\Rol;
use App\Models\Sucursal;
use App\Models\SucursalUser;
use App\Models\User;

class UserController extends Controller
{
    // ── GET /api/users ────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $actor  = Auth::user();
        $texto  = trim((string) $request->query('q', ''));

        abort_unless($actor->tienePermiso('usuarios.gestionar'), 403, 'Sin permiso para ver usuarios.');

        $usuarios = User::query()
            ->where('empresa_id', $actor->empresa_id)
            ->with([
                'sucursal:id,nombre',
                'sucursales' => fn($q) => $q->select('sucursales.id')->withPivot('role_id'),
            ])
            ->when($texto !== '', fn($q) => $q->where(fn($sub) => $sub
                ->where('name', 'like', "%{$texto}%")
                ->orWhere('email', 'like', "%{$texto}%")))
            ->orderBy('name')
            ->paginate(20);

        // Cargar nombres de roles para la sucursal activa de cada usuario
        $roleIds = $usuarios->pluck('sucursales')->flatten()
            ->pluck('pivot.role_id')->filter()->unique()->values();
        $rolesMap = \App\Models\Rol::whereIn('id', $roleIds)->pluck('nombre', 'id');

        $usuarios->getCollection()->transform(function ($u) use ($rolesMap) {
            $pivot = $u->sucursales->firstWhere('id', $u->sucursal_id)?->pivot;
            $u->rol_activo = $pivot?->role_id ? $rolesMap->get($pivot->role_id) : null;
            unset($u->sucursales);
            return $u;
        });

        $superAdminsActivos = User::where('empresa_id', $actor->empresa_id)
            ->where('es_super_admin', true)
            ->where('activo', true)
            ->count();

        $page = $usuarios->toArray();
        $page['super_admins_activos']           = $superAdminsActivos;
        $page['puede_gestionar_super_admins']   = (bool) $actor->es_super_admin;
        $page['puede_promover_primer_super_admin'] = $superAdminsActivos === 0;

        return response()->json($page);
    }

    // ── POST /api/users ───────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $actor = Auth::user();

        abort_unless($actor->tienePermiso('usuarios.gestionar'), 403, 'Sin permiso para crear usuarios.');

        $data = $request->validate([
            'name'       => ['required', 'string', 'min:2', 'max:255'],
            'email'      => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'   => ['required', 'string', 'min:8', 'confirmed'],
            'sucursal_id' => [
                'required', 'integer',
                Rule::exists('sucursales', 'id')->where(fn($q) => $q
                    ->where('empresa_id', $actor->empresa_id)
                    ->where('activo', true)),
            ],
            'role_id' => [
                'nullable', 'integer',
                Rule::exists('roles', 'id')->where(fn($q) => $q
                    ->where('empresa_id', $actor->empresa_id)),
            ],
        ], [
            'email.unique'           => 'Ya existe un usuario con ese correo.',
            'password.confirmed'     => 'La confirmación de contraseña no coincide.',
            'password.min'           => 'La contraseña debe tener al menos 8 caracteres.',
            'sucursal_id.exists'     => 'La sucursal seleccionada no pertenece a la empresa activa.',
            'role_id.exists'         => 'El rol seleccionado no pertenece a esta empresa.',
        ]);

        $usuario = DB::transaction(function () use ($actor, $data) {
            $usuario = User::create([
                'empresa_id'  => $actor->empresa_id,
                'sucursal_id' => $data['sucursal_id'],
                'name'        => $data['name'],
                'email'       => $data['email'],
                'password'    => $data['password'],
                'activo'      => true,
            ]);

            $usuario->sucursales()->attach($data['sucursal_id'], [
                'role_id' => $data['role_id'] ?? null,
            ]);

            return $usuario;
        });

        return response()->json(
            $usuario->load(['empresa:id,nombre', 'sucursal:id,nombre']),
            201
        );
    }

    // ── PUT /api/users/{user} ─────────────────────────────────────────────────

    public function update(Request $request, User $user): JsonResponse
    {
        $actor = Auth::user();

        abort_unless($actor->tienePermiso('usuarios.gestionar'), 403);
        abort_unless($user->empresa_id === $actor->empresa_id, 403);

        // Solo un super admin puede editar a otro super admin.
        if ($user->es_super_admin && ! $actor->es_super_admin) {
            abort(403, 'Solo un super administrador puede editar a otro super administrador.');
        }

        // No se puede desactivar al único super administrador activo.
        if ($user->es_super_admin && $request->has('activo') && ! $request->boolean('activo')) {
            $otrosSuperAdmins = User::where('empresa_id', $actor->empresa_id)
                ->where('es_super_admin', true)
                ->where('activo', true)
                ->where('id', '!=', $user->id)
                ->exists();

            if (! $otrosSuperAdmins) {
                return response()->json([
                    'message' => 'No puedes desactivar el único super administrador activo.',
                ], 422);
            }
        }

        $data = $request->validate([
            'name'     => ['sometimes', 'string', 'min:2', 'max:255'],
            'email'    => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
            'activo'   => ['sometimes', 'boolean'],
        ], [
            'email.unique'       => 'Ya existe un usuario con ese correo.',
            'password.min'       => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
        ]);

        $user->update($data);

        return response()->json($user->load('sucursal:id,nombre'));
    }

    // ── PUT /api/users/{user}/super-admin ───────────────────────────────────

    public function actualizarSuperAdmin(Request $request, User $user): JsonResponse
    {
        $actor = Auth::user();

        abort_unless($actor->tienePermiso('usuarios.gestionar'), 403);
        abort_unless($user->empresa_id === $actor->empresa_id, 403);

        $data = $request->validate([
            'es_super_admin' => ['required', 'boolean'],
        ]);

        $promover = (bool) $data['es_super_admin'];

        $totalSuperAdminsActivos = User::where('empresa_id', $actor->empresa_id)
            ->where('es_super_admin', true)
            ->where('activo', true)
            ->count();

        if ($promover) {
            $puedePromoverPrimero = $totalSuperAdminsActivos === 0 && $user->activo;

            abort_unless(
                $actor->es_super_admin || $puedePromoverPrimero,
                403,
                'Solo un super administrador puede otorgar este nivel.'
            );

            DB::transaction(function () use ($user, $actor) {
                $user->update(['es_super_admin' => true]);

                $sucursales = Sucursal::where('empresa_id', $actor->empresa_id)
                    ->where('activo', true)
                    ->pluck('id');

                $user->sucursales()->syncWithoutDetaching($sucursales->all());

                if (! $user->sucursal_id && $sucursales->isNotEmpty()) {
                    $user->update(['sucursal_id' => $sucursales->first()]);
                }
            });
        } else {
            abort_unless($actor->es_super_admin, 403, 'Solo un super administrador puede retirar este nivel.');

            // El usuario objetivo cuenta en el total; si es el único activo, no se puede retirar.
            $esElUnico = $user->activo && $totalSuperAdminsActivos === 1;

            if ($esElUnico) {
                return response()->json([
                    'message' => 'No puedes retirar al único super administrador activo.',
                ], 422);
            }

            $user->update(['es_super_admin' => false]);
        }

        return response()->json($user->fresh()->load('sucursal:id,nombre'));
    }

    // ── GET /api/users/{user}/sucursales ──────────────────────────────────────

    public function sucursalesDeUsuario(User $user): JsonResponse
    {
        $actor = Auth::user();

        abort_unless($actor->tienePermiso('usuarios.gestionar'), 403);
        abort_unless($user->empresa_id === $actor->empresa_id, 403);

        // Todas las sucursales de la empresa
        $todasSucursales = Sucursal::where('empresa_id', $actor->empresa_id)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'direccion']);

        // Sucursales asignadas al usuario con su rol
        $asignadas = SucursalUser::where('user_id', $user->id)
            ->whereIn('sucursal_id', $todasSucursales->pluck('id'))
            ->get(['sucursal_id', 'role_id'])
            ->keyBy('sucursal_id');

        $resultado = $todasSucursales->map(fn($sucursal) => [
            'id'        => $sucursal->id,
            'nombre'    => $sucursal->nombre,
            'direccion' => $sucursal->direccion,
            'asignada'  => $asignadas->has($sucursal->id),
            'role_id'   => $asignadas->get($sucursal->id)?->role_id,
        ]);

        return response()->json($resultado);
    }

    // ── PUT /api/users/{user}/sucursales ──────────────────────────────────────
    // Sincroniza las sucursales + roles de un usuario de una sola vez

    public function sincronizarSucursales(Request $request, User $user): JsonResponse
    {
        $actor = Auth::user();

        abort_unless($actor->tienePermiso('usuarios.gestionar'), 403);
        abort_unless($user->empresa_id === $actor->empresa_id, 403);

        // No se puede modificar las sucursales de un super admin
        // (siempre tiene todas, gestionado por el observer)
        if ($user->es_super_admin) {
            return response()->json([
                'message' => 'Las sucursales del super administrador se gestionan automáticamente.',
            ], 422);
        }

        $data = $request->validate([
            'sucursales'           => ['required', 'array'],
            'sucursales.*.id'      => [
                'required', 'integer',
                Rule::exists('sucursales', 'id')->where(fn($q) => $q
                    ->where('empresa_id', $actor->empresa_id)),
            ],
            'sucursales.*.role_id' => [
                'nullable', 'integer',
                Rule::exists('roles', 'id')->where(fn($q) => $q
                    ->where('empresa_id', $actor->empresa_id)),
            ],
        ]);

        DB::transaction(function () use ($user, $data, $actor) {
            // Construir mapa id => [role_id]
            $sync = collect($data['sucursales'])
                ->mapWithKeys(fn($s) => [
                    $s['id'] => ['role_id' => $s['role_id'] ?? null],
                ])
                ->toArray();

            // Si la sucursal activa del usuario queda sin asignar, ajustar
            $sucursalActivaIncluida = collect($data['sucursales'])
                ->pluck('id')
                ->contains($user->sucursal_id);

            $user->sucursales()->sync($sync);

            // Si la sucursal activa ya no está asignada, reasignarla o limpiarla
            if (! $sucursalActivaIncluida) {
                $user->update([
                    'sucursal_id' => ! empty($data['sucursales'])
                        ? $data['sucursales'][0]['id']
                        : null,
                ]);
            }
        });

        return response()->json(['message' => 'Sucursales actualizadas correctamente.']);
    }

    // ── GET /api/users/vendedores ─────────────────────────────────────────────

    public function buscarVendedores(Request $request): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user->tienePermiso('ventas.crear'), 403, 'Sin permiso: ventas.crear');
        $q    = trim((string) $request->get('q', ''));

        $items = User::query()
            ->where('empresa_id', $user->empresa_id)
            ->when($q !== '', fn($query) => $query->where(fn($sub) => $sub
                ->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")))
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json($items);
    }

    // ── GET /api/users/sucursales-disponibles ─────────────────────────────────

    public function sucursalesDisponibles(): JsonResponse
    {
        $user = Auth::user();

        $sucursales = Sucursal::query()
            ->where('empresa_id', $user->empresa_id)
            ->where('activo', true)
            ->select('id', 'nombre', 'direccion')
            ->orderBy('nombre')
            ->get();

        return response()->json($sucursales);
    }
}
