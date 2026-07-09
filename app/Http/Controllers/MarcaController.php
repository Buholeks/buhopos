<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Marca;
use App\Support\PublicImageStorage;
use App\Traits\HandlesMediaImages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class MarcaController extends Controller
{
    use HandlesMediaImages;

    private function empresaId(): int
    {
        return (int) Auth::user()->empresa_id;
    }

    private function sucursalId(): int
    {
        return (int) Auth::user()->sucursal_id;
    }

    // GET /api/marcas
    public function index(): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('productos.ver'), 403, 'Sin permiso: productos.ver');
        $marcas = Marca::deEmpresa($this->empresaId())
            ->with('modelos')
            ->orderBy('nombre')
            ->get()
            ->map(fn($m) => $this->formatear($m));

        return response()->json($marcas);
    }

    public function buscar(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('productos.ver'), 403, 'Sin permiso: productos.ver');
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
        ]);

        $q = trim($data['q'] ?? '');

        $marcas = Marca::deEmpresa($this->empresaId())
            ->activas()
            ->when($q !== '', fn($query) => $query->where('nombre', 'like', "{$q}%"))
            ->orderBy('nombre')
            ->limit(20)
            ->get(['id', 'nombre']);

        return response()->json($marcas);
    }

    public function restore(int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('productos.editar'), 403, 'Sin permiso: productos.editar');
        $marca = Marca::withTrashed()->where('empresa_id', $this->empresaId())->findOrFail($id);
        $marca->restore();
        return response()->json($this->formatear($marca->load('modelos')));
    }

    // POST /api/marcas
    public function store(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('productos.editar'), 403, 'Sin permiso: productos.editar');
        $empresaId = $this->empresaId();

        $eliminada = Marca::withTrashed()
            ->where('empresa_id', $empresaId)
            ->where('nombre', $request->input('nombre'))
            ->whereNotNull('deleted_at')
            ->first();

        if ($eliminada) {
            return response()->json([
                'recoverable' => true,
                'id'          => $eliminada->id,
                'nombre'      => $eliminada->nombre,
                'message'     => "Ya existe una marca eliminada con ese nombre. ¿Deseas recuperarla?",
            ], 409);
        }

        $datos = $request->validate([
            'nombre' => [
                'required', 'string', 'min:2', 'max:150',
                Rule::unique('marcas')->where(fn($q) => $q
                    ->where('empresa_id', $empresaId)
                    ->whereNull('deleted_at')
                ),
            ],
            'logo'          => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'logo_media_id' => ['nullable', 'integer'],
            'activo'        => ['nullable', 'boolean'],
        ], [
            'nombre.required' => 'El nombre de la marca es obligatorio.',
            'nombre.unique'   => 'Ya existe una marca con ese nombre en esta empresa.',
            'logo.image'      => 'El logo debe ser una imagen.',
            'logo.max'        => 'El logo no debe superar 2MB.',
        ]);

        $marca = new Marca();
        $marca->empresa_id  = $empresaId;
        $marca->sucursal_id = $this->sucursalId();
        $marca->user_id     = Auth::id();
        $marca->nombre      = $datos['nombre'];
        $marca->activo      = $datos['activo'] ?? true;

        if ($request->filled('logo_media_id')) {
            $marca->save(); // necesitamos id antes de crear mediable
            $marca->logo = $this->asignarImagenDesdeMedia($marca, (int) $request->logo_media_id, 'logo');
        } elseif ($request->hasFile('logo')) {
            $marca->save();
            $marca->logo = $this->subirYRegistrarImagen($marca, $request->file('logo'), 'marcas/logos', 'logo');
        }

        $marca->save();

        return response()->json($this->formatear($marca->load('modelos')), 201);
    }

    // GET /api/marcas/{id}
    public function show(int $id): JsonResponse
    {
        $marca = Marca::deEmpresa($this->empresaId())
            ->with('modelos')
            ->findOrFail($id);

        return response()->json($this->formatear($marca));
    }

    // POST /api/marcas/{id}  (usamos POST para poder enviar archivo con _method=PUT)
    public function update(Request $request, int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('productos.editar'), 403, 'Sin permiso: productos.editar');
        $empresaId = $this->empresaId();
        $marca     = Marca::deEmpresa($empresaId)->findOrFail($id);

        $datos = $request->validate([
            'nombre' => [
                'required', 'string', 'min:2', 'max:150',
                Rule::unique('marcas')->where(fn($q) => $q
                    ->where('empresa_id', $empresaId)
                    ->whereNull('deleted_at')
                )->ignore($id),
            ],
            'logo'          => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'logo_media_id' => ['nullable', 'integer'],
            'eliminar_logo' => ['nullable', 'boolean'],
            'activo'        => ['nullable', 'boolean'],
        ], [
            'nombre.required' => 'El nombre de la marca es obligatorio.',
            'nombre.unique'   => 'Ya existe una marca con ese nombre en esta empresa.',
        ]);

        $marca->nombre = $datos['nombre'];
        $marca->activo = $datos['activo'] ?? $marca->activo;

        // Quitar logo
        if (! empty($datos['eliminar_logo'])) {
            $this->quitarReferenciaMedia($marca, 'logo');
            $this->borrarArchivoLegacy($marca->logo);
            $marca->logo = null;
        }

        // Logo desde biblioteca
        if ($request->filled('logo_media_id')) {
            $marca->logo = $this->asignarImagenDesdeMedia($marca, (int) $request->logo_media_id, 'logo');
        } elseif ($request->hasFile('logo')) {
            $this->borrarArchivoLegacy($marca->logo);
            $marca->logo = $this->subirYRegistrarImagen($marca, $request->file('logo'), 'marcas/logos', 'logo');
        }

        $marca->save();

        return response()->json($this->formatear($marca->load('modelos')));
    }

    // DELETE /api/marcas/{id}
    public function destroy(int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('productos.eliminar'), 403, 'Sin permiso: productos.eliminar');
        $marca = Marca::deEmpresa($this->empresaId())->findOrFail($id);

        $this->quitarReferenciaMedia($marca, 'logo');

        // Los modelos se eliminan en cascada por la FK cascadeOnDelete()
        $marca->delete();

        return response()->json(['mensaje' => 'Marca eliminada correctamente.']);
    }

    // ─── Helper: agrega logo_url al JSON ─────────────────────────────────────
    private function formatear(Marca $marca): array
    {
        $data           = $marca->toArray();
        $data['logo_url'] = $marca->logoUrl();

        // Agregar imagen_url a cada modelo
        $data['modelos'] = collect($marca->modelos)->map(fn($m) => array_merge(
            $m->toArray(),
            ['imagen_url' => $m->imagenUrl()]
        ))->toArray();

        return $data;
    }
}
