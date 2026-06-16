<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Marca;
use App\Models\Modelo;
use App\Support\PublicImageStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ModeloController extends Controller
{
    private function empresaId(): int
    {
        return (int) Auth::user()->empresa_id;
    }

    private function sucursalId(): int
    {
        return (int) Auth::user()->sucursal_id;
    }

    // GET /api/modelos?marca_id=1
    public function index(Request $request): JsonResponse
    {
        $query = Modelo::deEmpresa($this->empresaId())
            ->with('marca')
            ->orderBy('nombre');

        // Filtrar por marca si se envía el parámetro
        if ($request->filled('marca_id')) {
            $query->where('marca_id', $request->integer('marca_id'));
        }

        $modelos = $query->get()->map(fn($m) => $this->formatear($m));

        return response()->json($modelos);
    }

    public function buscar(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('productos.ver'), 403, 'Sin permiso: productos.ver');
        $data = $request->validate([
            'marca_id' => ['required', 'integer'],
            'q' => ['nullable', 'string', 'max:150'],
        ]);

        $q = trim($data['q'] ?? '');

        $modelos = Modelo::deEmpresa($this->empresaId())
            ->activos()
            ->where('marca_id', $data['marca_id'])
            ->when($q !== '', fn($query) => $query->where('nombre', 'like', "{$q}%"))
            ->orderBy('nombre')
            ->limit(20)
            ->get(['id', 'marca_id', 'nombre']);

        return response()->json($modelos);
    }

    public function restore(int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('productos.editar'), 403, 'Sin permiso: productos.editar');
        $modelo = Modelo::withTrashed()->where('empresa_id', $this->empresaId())->findOrFail($id);
        $modelo->restore();
        return response()->json($this->formatear($modelo->load('marca')));
    }

    // POST /api/modelos
    public function store(Request $request): JsonResponse
    {
        $empresaId = $this->empresaId();
        $marcaId   = $request->input('marca_id');

        $eliminado = Modelo::withTrashed()
            ->where('marca_id', $marcaId)
            ->where('nombre', $request->input('nombre'))
            ->whereNotNull('deleted_at')
            ->first();

        if ($eliminado) {
            return response()->json([
                'recoverable' => true,
                'id'          => $eliminado->id,
                'nombre'      => $eliminado->nombre,
                'message'     => "Ya existe un modelo eliminado con ese nombre. ¿Deseas recuperarlo?",
            ], 409);
        }

        $datos = $request->validate([
            'marca_id' => ['required', 'integer', 'exists:marcas,id'],
            'nombre'   => [
                'required', 'string', 'min:2', 'max:150',
                // No puede repetirse el mismo nombre dentro de la misma marca
                Rule::unique('modelos')->where(fn($q) => $q
                    ->where('marca_id', $marcaId)
                    ->whereNull('deleted_at')
                ),
            ],
            'imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'activo' => ['nullable', 'boolean'],
        ], [
            'marca_id.required' => 'Debes seleccionar una marca.',
            'marca_id.exists'   => 'La marca seleccionada no existe.',
            'nombre.required'   => 'El nombre del modelo es obligatorio.',
            'nombre.unique'     => 'Ya existe un modelo con ese nombre en esta marca.',
        ]);

        // Verificar que la marca pertenezca a la misma empresa
        $marca = Marca::deEmpresa($empresaId)->find($marcaId);
        if (! $marca) {
            return response()->json(['message' => 'La marca no pertenece a esta empresa.'], 422);
        }

        $modelo = new Modelo();
        $modelo->empresa_id  = $empresaId;
        $modelo->sucursal_id = $this->sucursalId();
        $modelo->user_id     = Auth::id();
        $modelo->marca_id    = $datos['marca_id'];
        $modelo->nombre      = $datos['nombre'];
        $modelo->activo      = $datos['activo'] ?? true;

        if ($request->hasFile('imagen')) {
            $modelo->imagen = PublicImageStorage::store($request->file('imagen'), 'modelos/imagenes');
        }

        $modelo->save();

        return response()->json($this->formatear($modelo->load('marca')), 201);
    }

    // GET /api/modelos/{id}
    public function show(int $id): JsonResponse
    {
        $modelo = Modelo::deEmpresa($this->empresaId())
            ->with('marca')
            ->findOrFail($id);

        return response()->json($this->formatear($modelo));
    }

    // POST /api/modelos/{id}  (POST con _method=PUT para poder enviar imagen)
    public function update(Request $request, int $id): JsonResponse
    {
        $empresaId = $this->empresaId();
        $modelo    = Modelo::deEmpresa($empresaId)->findOrFail($id);

        $datos = $request->validate([
            'nombre' => [
                'required', 'string', 'min:2', 'max:150',
                Rule::unique('modelos')->where(fn($q) => $q
                    ->where('marca_id', $modelo->marca_id)
                    ->whereNull('deleted_at')
                )->ignore($id),
            ],
            'imagen'          => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'eliminar_imagen' => ['nullable', 'boolean'],
            'activo'          => ['nullable', 'boolean'],
        ], [
            'nombre.required' => 'El nombre del modelo es obligatorio.',
            'nombre.unique'   => 'Ya existe un modelo con ese nombre en esta marca.',
        ]);

        $modelo->nombre = $datos['nombre'];
        $modelo->activo = $datos['activo'] ?? $modelo->activo;

        // Eliminar imagen si se pidió
        if (! empty($datos['eliminar_imagen']) && $modelo->imagen) {
            Storage::disk('public')->delete($modelo->imagen);
            $modelo->imagen = null;
        }

        // Subir nueva imagen
        if ($request->hasFile('imagen')) {
            if ($modelo->imagen) {
                Storage::disk('public')->delete($modelo->imagen);
            }
            $modelo->imagen = PublicImageStorage::store($request->file('imagen'), 'modelos/imagenes');
        }

        $modelo->save();

        return response()->json($this->formatear($modelo->load('marca')));
    }

    // DELETE /api/modelos/{id}
    public function destroy(int $id): JsonResponse
    {
        $modelo = Modelo::deEmpresa($this->empresaId())->findOrFail($id);

        if ($modelo->imagen) {
            Storage::disk('public')->delete($modelo->imagen);
        }

        $modelo->delete();

        return response()->json(['mensaje' => 'Modelo eliminado correctamente.']);
    }

    // ─── Helper: agrega imagen_url al JSON ───────────────────────────────────
    private function formatear(Modelo $modelo): array
    {
        return array_merge($modelo->toArray(), [
            'imagen_url' => $modelo->imagenUrl(),
        ]);
    }
}
