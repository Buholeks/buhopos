<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Marca;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MarcaController extends Controller
{
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
        $marcas = Marca::deEmpresa($this->empresaId())
            ->with('modelos')
            ->orderBy('nombre')
            ->get()
            ->map(fn($m) => $this->formatear($m));

        return response()->json($marcas);
    }

    // POST /api/marcas
    public function store(Request $request): JsonResponse
    {
        $empresaId = $this->empresaId();

        $datos = $request->validate([
            'nombre' => [
                'required', 'string', 'min:2', 'max:150',
                Rule::unique('marcas')->where(fn($q) => $q
                    ->where('empresa_id', $empresaId)
                    ->whereNull('deleted_at')
                ),
            ],
            'logo'   => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'activo' => ['nullable', 'boolean'],
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

        if ($request->hasFile('logo')) {
            $marca->logo = $request->file('logo')->store('marcas/logos', 'public');
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
            'eliminar_logo' => ['nullable', 'boolean'],
            'activo'        => ['nullable', 'boolean'],
        ], [
            'nombre.required' => 'El nombre de la marca es obligatorio.',
            'nombre.unique'   => 'Ya existe una marca con ese nombre en esta empresa.',
        ]);

        $marca->nombre = $datos['nombre'];
        $marca->activo = $datos['activo'] ?? $marca->activo;

        // Eliminar logo si se pidió
        if (! empty($datos['eliminar_logo']) && $marca->logo) {
            Storage::disk('public')->delete($marca->logo);
            $marca->logo = null;
        }

        // Subir nuevo logo
        if ($request->hasFile('logo')) {
            if ($marca->logo) {
                Storage::disk('public')->delete($marca->logo);
            }
            $marca->logo = $request->file('logo')->store('marcas/logos', 'public');
        }

        $marca->save();

        return response()->json($this->formatear($marca->load('modelos')));
    }

    // DELETE /api/marcas/{id}
    public function destroy(int $id): JsonResponse
    {
        $marca = Marca::deEmpresa($this->empresaId())->findOrFail($id);

        // Eliminar logo del storage
        if ($marca->logo) {
            Storage::disk('public')->delete($marca->logo);
        }

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