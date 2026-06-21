<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\Mediable;
use App\Support\PublicImageStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MediaController extends Controller
{
    private function empresaId(): int
    {
        return (int) Auth::user()->empresa_id;
    }

    // ── GET /api/media ────────────────────────────────────────────────────────
    // ?tipo=productos|variantes|marcas|modelos|todos   &buscar=xxx   &page=1
    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('productos.ver'), 403);

        $empresaId = $this->empresaId();

        $query = Media::where('empresa_id', $empresaId)
            ->withCount('mediables')
            ->orderBy('created_at', 'desc');

        if ($request->filled('tipo') && $request->tipo !== 'todos') {
            $query->where('carpeta', 'like', $this->prefijoCarpeta($request->tipo) . '%');
        }

        if ($request->filled('buscar')) {
            $query->where('nombre_original', 'like', '%' . $request->buscar . '%');
        }

        $paginator = $query->paginate(60);

        $paginator->getCollection()->transform(fn($m) => $this->formatear($m));

        return response()->json($paginator);
    }

    // ── GET /api/media/resumen ────────────────────────────────────────────────
    // Conteo de imágenes por tipo de carpeta
    public function resumen(): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('productos.ver'), 403);

        $empresaId = $this->empresaId();

        $total = Media::where('empresa_id', $empresaId)->count();
        $huerfanas = Media::where('empresa_id', $empresaId)
            ->whereDoesntHave('mediables')
            ->count();

        $porTipo = [
            ['tipo' => 'productos', 'label' => 'Productos',       'total' => $this->contarPorPrefijo($empresaId, 'productos/')],
            ['tipo' => 'variantes', 'label' => 'Variantes',       'total' => $this->contarPorPrefijo($empresaId, 'variantes/')],
            ['tipo' => 'marcas',    'label' => 'Logos de Marcas', 'total' => $this->contarCarpeta($empresaId, 'marcas/logos')],
            ['tipo' => 'modelos',   'label' => 'Modelos',         'total' => $this->contarCarpeta($empresaId, 'modelos/imagenes')],
        ];

        return response()->json([
            'total'     => $total,
            'huerfanas' => $huerfanas,
            'por_tipo'  => $porTipo,
        ]);
    }

    // ── POST /api/media ───────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('productos.editar'), 403);

        $request->validate([
            'archivo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'tipo'    => ['required', Rule::in(['producto', 'variante', 'marca', 'modelo'])],
        ]);

        $empresaId  = $this->empresaId();
        $archivo    = $request->file('archivo');
        $carpetaReal = $this->carpetaReal($request->tipo, $empresaId);
        $hash       = hash_file('sha256', $archivo->getRealPath());

        $existente = Media::where('empresa_id', $empresaId)->where('hash', $hash)->first();

        if ($existente) {
            return response()->json([
                'data'       => $this->formatear($existente->loadCount('mediables')),
                'reutilizada' => true,
                'message'    => 'Imagen ya existente, se reutilizará.',
            ], 200);
        }

        $ruta = PublicImageStorage::store($archivo, $carpetaReal);

        $media = Media::create([
            'empresa_id'      => $empresaId,
            'hash'            => $hash,
            'ruta'            => $ruta,
            'nombre_original' => $archivo->getClientOriginalName(),
            'carpeta'         => $carpetaReal,
            'tamanio'         => $archivo->getSize(),
            'mime_type'       => $archivo->getMimeType(),
        ]);

        return response()->json([
            'data'       => $this->formatear($media->loadCount('mediables')),
            'reutilizada' => false,
        ], 201);
    }

    // ── DELETE /api/media/{id} ───────────────────────────────────────────────
    public function destroy(int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('productos.editar'), 403);

        $media = Media::where('empresa_id', $this->empresaId())->findOrFail($id);

        $usos = $media->mediables()->count();
        if ($usos > 0) {
            return response()->json([
                'message' => "Esta imagen está en uso por {$usos} elemento(s) y no puede eliminarse.",
            ], 422);
        }

        Storage::disk('public')->delete($media->ruta);
        $media->delete();

        return response()->json(['message' => 'Imagen eliminada correctamente.']);
    }

    // ── DELETE /api/media/limpiar-huerfanas ──────────────────────────────────
    public function limpiarHuerfanas(): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('productos.editar'), 403);

        $empresaId = $this->empresaId();

        $huerfanas = Media::where('empresa_id', $empresaId)
            ->whereDoesntHave('mediables')
            ->get();

        $eliminadas = 0;
        foreach ($huerfanas as $media) {
            Storage::disk('public')->delete($media->ruta);
            $media->delete();
            $eliminadas++;
        }

        return response()->json([
            'message'   => "{$eliminadas} imagen(es) huérfana(s) eliminada(s).",
            'eliminadas' => $eliminadas,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function formatear(Media $m): array
    {
        return [
            'id'              => $m->id,
            'nombre_original' => $m->nombre_original,
            'carpeta'         => $m->carpeta,
            'tipo'            => $m->tipoNormalizado(),
            'carpeta_label'   => $m->carpetaLabel(),
            'tamanio'         => $m->tamanio,
            'tamanio_fmt'     => $m->tamanioFormateado(),
            'mime_type'       => $m->mime_type,
            'url'             => $m->url,
            'usos'            => $m->mediables_count ?? 0,
            'created_at'      => $m->created_at,
        ];
    }

    private function carpetaReal(string $tipo, int $empresaId): string
    {
        return match ($tipo) {
            'producto' => "productos/{$empresaId}",
            'variante' => "variantes/{$empresaId}",
            'marca'    => 'marcas/logos',
            'modelo'   => 'modelos/imagenes',
        };
    }

    private function prefijoCarpeta(string $tipo): string
    {
        return match ($tipo) {
            'productos' => 'productos/',
            'variantes' => 'variantes/',
            'marcas'    => 'marcas/',
            'modelos'   => 'modelos/',
            default     => '',
        };
    }

    private function contarPorPrefijo(int $empresaId, string $prefijo): int
    {
        return Media::where('empresa_id', $empresaId)
            ->where('carpeta', 'like', $prefijo . '%')
            ->count();
    }

    private function contarCarpeta(int $empresaId, string $carpeta): int
    {
        return Media::where('empresa_id', $empresaId)
            ->where('carpeta', $carpeta)
            ->count();
    }
}
