<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CategoriaController extends Controller
{
    private function empresaId(): int
    {
        return (int) Auth::user()->empresa_id;
    }

    // GET /api/categorias
    public function index(): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('productos.ver'), 403, 'Sin permiso: productos.ver');
        $categorias = Categoria::deEmpresa($this->empresaId())
            ->raiz()
            ->with('hijosRecursivos')
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        return response()->json($categorias);
    }

    public function buscar(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('productos.ver'), 403, 'Sin permiso: productos.ver');
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
        ]);

        $q = trim($data['q'] ?? '');

        $categorias = Categoria::deEmpresa($this->empresaId())
            ->activas()
            ->with(['padre:id,nombre,categoria_padre_id', 'padre.padre:id,nombre'])
            ->when($q !== '', fn($query) => $query->where('nombre', 'like', "%{$q}%"))
            ->orderBy('nombre')
            ->limit(30)
            ->get(['id', 'nombre', 'categoria_padre_id']);

        return response()->json($categorias);
    }

        public function restore(int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('productos.editar'), 403, 'Sin permiso: productos.editar');
        $categoria = Categoria::withTrashed()
            ->where('empresa_id', $this->empresaId())
            ->findOrFail($id);
        $categoria->restore();
        return response()->json($categoria->load('hijosRecursivos', 'padre'));
    }

// POST /api/categorias
public function store(Request $request): JsonResponse
{
    abort_unless(Auth::user()->tienePermiso('productos.editar'), 403, 'Sin permiso: productos.editar');
    $empresaId  = $this->empresaId();
    $padreId    = $request->input('categoria_padre_id');

    // Verificar si existe eliminado con el mismo nombre en el mismo nivel
    $eliminado = Categoria::withTrashed()
        ->where('empresa_id', $empresaId)
        ->where('nombre', $request->input('nombre'))
        ->when($padreId,
            fn($q) => $q->where('categoria_padre_id', $padreId),
            fn($q) => $q->whereNull('categoria_padre_id')
        )
        ->whereNotNull('deleted_at')
        ->first();

    if ($eliminado) {
        return response()->json([
            'recoverable' => true,
            'id'          => $eliminado->id,
            'nombre'      => $eliminado->nombre,
            'message'     => "Ya existe una categoría eliminada con ese nombre. ¿Deseas recuperarla?",
        ], 409);
    }

    $data = $request->validate([
        'nombre' => [
            'required', 'string', 'min:2', 'max:150',
            Rule::unique('categorias', 'nombre')->where(fn($q) => $q
                ->where('empresa_id', $empresaId)
                ->when($padreId,
                    fn($q) => $q->where('categoria_padre_id', $padreId),
                    fn($q) => $q->whereNull('categoria_padre_id')
                )
                ->whereNull('deleted_at')
            ),
        ],
        'descripcion'         => ['nullable', 'string', 'max:500'],
        'categoria_padre_id'  => ['nullable', 'integer', 'exists:categorias,id'],
        'activo'              => ['nullable', 'boolean'],
        'orden'               => ['nullable', 'integer', 'min:0'],
    ], [
        'nombre.unique' => 'Ya existe una categoría con ese nombre en este nivel.',
    ]);

    // Validar que el padre pertenezca a la misma empresa
    if (!empty($data['categoria_padre_id'])) {
        $padre = Categoria::deEmpresa($empresaId)->find($data['categoria_padre_id']);
        if (!$padre) {
            return response()->json(['message' => 'La categoría padre no pertenece a esta empresa.'], 422);
        }
    }

    $data['empresa_id']  = $empresaId;
    $data['sucursal_id'] = Auth::user()->sucursal_id;
    $data['user_id']     = Auth::id();
    $data['activo']      = $data['activo'] ?? true;
    $data['orden']       = $data['orden'] ?? 0;

    $categoria = new Categoria($data);
    $categoria->profundidad = $categoria->calcularProfundidad();
    $categoria->save();

    return response()->json($categoria->load('hijosRecursivos', 'padre'), 201);
}


    // GET /api/categorias/{id}
    public function show(int $id): JsonResponse
    {
        $categoria = Categoria::deEmpresa($this->empresaId())
            ->with('hijosRecursivos', 'padre')
            ->findOrFail($id);

        return response()->json($categoria);
    }

    // PUT /api/categorias/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('productos.editar'), 403, 'Sin permiso: productos.editar');
        $empresaId = $this->empresaId();

        $categoria = Categoria::deEmpresa($empresaId)->findOrFail($id);

        $data = $request->validate([
            'nombre' => [
                'required',
                'string',
                'min:2',
                'max:150',
                Rule::unique('categorias')
                    ->where(
                        fn($q) => $q
                            ->where('empresa_id', $empresaId)
                            ->where('categoria_padre_id', $categoria->categoria_padre_id)
                            ->whereNull('deleted_at')
                    )
                    ->ignore($id),
            ],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'activo'      => ['nullable', 'boolean'],
            'orden'       => ['nullable', 'integer', 'min:0'],
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.unique'   => 'Ya existe una categoría con ese nombre en este nivel.',
        ]);

        $categoria->fill($data);

        if (array_key_exists('orden', $data)) {
            $categoria->orden = $data['orden'];
        }
        if (array_key_exists('activo', $data)) {
            $categoria->activo = $data['activo'];
        }

        // Si cambia el nombre, el mutator recalcula slug; sigue siendo único por empresa.
        $categoria->save();

        return response()->json($categoria->load('hijosRecursivos', 'padre'));
    }

    // DELETE /api/categorias/{id}
    public function destroy(int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('productos.eliminar'), 403, 'Sin permiso: productos.eliminar');
        $categoria = Categoria::deEmpresa($this->empresaId())
            ->with('hijosRecursivos')
            ->findOrFail($id);

        $this->eliminarRecursivo($categoria);

        return response()->json(['message' => 'Categoría eliminada correctamente.']);
    }

    private function eliminarRecursivo(Categoria $categoria): void
    {
        $categoria->loadMissing('hijos');

        foreach ($categoria->hijos as $hijo) {
            $this->eliminarRecursivo($hijo);
        }

        $categoria->delete();
    }
}
