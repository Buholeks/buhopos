<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Importaciones\ProductosPlantillaExportacion;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\TipoAtributo;
use App\Models\VarianteAtributo;
use App\Servicios\KardexServicio;
use App\Servicios\ProductosImportacionServicio;
use App\Support\VariantImageResolver;
use App\Traits\HandlesMediaImages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductoController extends Controller
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

    // ── GET /api/productos ────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('productos.ver'), 403, 'Sin permiso: productos.ver');
        $query = Producto::deEmpresa($this->empresaId())
            ->with([
                'categoria:id,nombre',
                'marca:id,nombre',
                'modelo:id,nombre,marca_id',
                'unidadMedida:id,nombre,abreviatura',
            ])
            ->withCount('variantes')
            ->orderBy('id', 'desc');

        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', $request->integer('categoria_id'));
        }

        if ($request->filled('marca_id')) {
            $query->where('marca_id', $request->integer('marca_id'));
        }

        if ($request->filled('modelo_id')) {
            $query->where('modelo_id', $request->integer('modelo_id'));
        }

        if ($request->filled('unidad_medida_id')) {
            $query->where('unidad_medida_id', $request->integer('unidad_medida_id'));
        }

        if ($request->filled('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        if ($request->filled('tiene_variantes')) {
            $query->where('tiene_variantes', $request->boolean('tiene_variantes'));
        }

        if ($request->filled('tiene_series')) {
            $query->where('tiene_series', $request->boolean('tiene_series'));
        }

        if ($request->filled('buscar')) {
            $b = trim((string) $request->buscar);

            $query->where(function ($q) use ($b) {
                $q->where('nombre', 'like', "%{$b}%")
                    ->orWhere('codigo', 'like', "%{$b}%")
                    ->orWhereHas('categoria', fn($sq) => $sq->where('nombre', 'like', "%{$b}%"))
                    ->orWhereHas('marca', fn($sq) => $sq->where('nombre', 'like', "%{$b}%"))
                    ->orWhereHas('modelo', fn($sq) => $sq->where('nombre', 'like', "%{$b}%"))
                    ->orWhereHas('unidadMedida', fn($sq) => $sq
                        ->where('nombre', 'like', "%{$b}%")
                        ->orWhere('abreviatura', 'like', "%{$b}%"))
                    ->orWhereHas('variantes', fn($sq) => $sq
                        ->where('sku', 'like', "%{$b}%")
                        ->orWhere('codigo_barras', 'like', "%{$b}%"));
            });
        }

        $productos = $query->paginate($request->integer('por_pagina', 20));
        $previewImagenes = VariantImageResolver::previewsForProducts($productos->getCollection(), $this->empresaId());

        $productos->getCollection()->transform(function ($producto) use ($previewImagenes) {
            $producto->setAttribute('preview_imagenes', $previewImagenes[$producto->id] ?? []);

            return $producto;
        });

        return response()->json($productos);
    }

    public function restore(int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('productos.editar'), 403, 'Sin permiso: productos.editar');
        $producto = Producto::withTrashed()->where('empresa_id', $this->empresaId())->findOrFail($id);
        $producto->restore();
        return response()->json([
            'message' => 'Producto recuperado correctamente.',
            'data'    => $producto->load(['categoria', 'marca', 'modelo', 'unidadMedida']),
        ]);
    }

    // ── POST /api/productos ───────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('productos.editar'), 403, 'Sin permiso: productos.editar');
        $empresaId = $this->empresaId();

        if ($request->filled('codigo')) {
            $eliminado = Producto::withTrashed()
                ->where('empresa_id', $empresaId)
                ->where('codigo', $request->input('codigo'))
                ->whereNotNull('deleted_at')
                ->first();

            if ($eliminado) {
                return response()->json([
                    'recoverable' => true,
                    'id'          => $eliminado->id,
                    'nombre'      => $eliminado->nombre,
                    'message'     => "Ya existe un producto eliminado con ese código ({$eliminado->codigo}). ¿Deseas recuperarlo?",
                ], 409);
            }
        }

        $datos = $request->validate(
            $this->reglas($empresaId),
            $this->mensajes()
        );
        $this->validarModeloMarca($datos);

        DB::beginTransaction();

        try {
            unset($datos['imagen'], $datos['eliminar_imagen']);

            if (empty($datos['codigo'])) {
                $datos['codigo'] = Producto::generarCodigo($empresaId);
            }

            $producto = new Producto(array_merge($datos, [
                'empresa_id'      => $empresaId,
                'sucursal_id'     => $this->sucursalId(),
                'user_id'         => Auth::id(),
                'activo'          => $datos['activo'] ?? true,
                'tiene_series'    => $datos['tiene_series'] ?? false,
                'tiene_variantes' => false,
            ]));

            if ($request->filled('imagen_media_id')) {
                $producto->save();
                $producto->imagen = $this->asignarImagenDesdeMedia($producto, (int) $request->imagen_media_id);
            } elseif ($request->hasFile('imagen')) {
                $producto->save();
                $producto->imagen = $this->subirYRegistrarImagen($producto, $request->file('imagen'), "productos/{$empresaId}");
            }

            $producto->save();

            app(KardexServicio::class)->registrar([
                'empresa_id' => $empresaId,
                'sucursal_id' => $this->sucursalId(),
                'producto_id' => $producto->id,
                'variante_id' => null,
                'user_id' => Auth::id(),
                'tipo' => 'alta_producto',
                'direccion' => 'neutro',
                'cantidad' => 0,
                'stock_antes' => 0,
                'stock_despues' => 0,
                'costo_unitario' => $producto->precio_costo !== null ? (float) $producto->precio_costo : null,
                'precio_unitario' => $producto->precio_venta !== null ? (float) $producto->precio_venta : null,
                'referencia_tipo' => 'producto',
                'referencia_id' => $producto->id,
                'folio' => $producto->codigo,
                'fecha' => $producto->created_at ?? now(),
                'metadata' => [
                    'nombre' => $producto->nombre,
                    'codigo' => $producto->codigo,
                ],
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Producto creado correctamente.',
                'data' => $producto->load(['categoria', 'marca', 'modelo', 'unidadMedida']),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error al crear el producto.',
            ], 500);
        }
    }

    // ── GET /api/productos/{id} ───────────────────────────────────────────────
    public function show(int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('productos.ver'), 403, 'Sin permiso: productos.ver');
        $producto = Producto::deEmpresa($this->empresaId())
            ->with(['categoria', 'marca', 'modelo', 'unidadMedida', 'variantes.atributos'])
            ->findOrFail($id);

        return response()->json($producto);
    }

    // ── POST /api/productos/{id} (con _method=PUT para imagen) ───────────────
    public function update(Request $request, int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('productos.editar'), 403, 'Sin permiso: productos.editar');
        $empresaId = $this->empresaId();

        $producto = Producto::deEmpresa($empresaId)->findOrFail($id);

        $datos = $request->validate(
            $this->reglas($empresaId, $id),
            $this->mensajes()
        );
        $this->validarModeloMarca($datos);

        if (!empty($datos['eliminar_imagen'])) {
            $this->quitarReferenciaMedia($producto);
            $this->borrarArchivoLegacy($producto->imagen);
            $producto->imagen = null;
        }

        if ($request->filled('imagen_media_id')) {
            $producto->imagen = $this->asignarImagenDesdeMedia($producto, (int) $request->imagen_media_id);
        } elseif ($request->hasFile('imagen')) {
            $this->borrarArchivoLegacy($producto->imagen);
            $producto->imagen = $this->subirYRegistrarImagen($producto, $request->file('imagen'), "productos/{$empresaId}");
        }

        unset($datos['imagen'], $datos['eliminar_imagen']);

        if (empty($datos['codigo'])) {
            $datos['codigo'] = Producto::generarCodigo($empresaId);
        }

        $producto->fill($datos);
        $producto->save();

        return response()->json([
            'message' => 'Producto actualizado correctamente.',
            'data' => $producto->load(['categoria', 'marca', 'modelo', 'unidadMedida']),
        ]);
    }

    // ── DELETE /api/productos/{id} ────────────────────────────────────────────
    public function destroy(int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('productos.eliminar'), 403, 'Sin permiso: productos.eliminar');
        $empresaId = $this->empresaId();

        $producto = Producto::deEmpresa($empresaId)
            ->withCount('variantes')
            ->findOrFail($id);

        $tieneExistencia = Inventario::where('empresa_id', $empresaId)
            ->where('producto_id', $producto->id)
            ->where('stock', '>', 0)
            ->exists();

        if ($producto->variantes_count > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el producto porque tiene variantes registradas.',
            ], 422);
        }

        if ($tieneExistencia) {
            return response()->json([
                'message' => 'No se puede eliminar el producto porque tiene existencias en inventario.',
            ], 422);
        }

        $producto->delete();

        return response()->json([
            'message' => 'Producto eliminado correctamente.',
        ]);
    }

    // ── GET /api/productos/atributos-empresa ──────────────────────────────────
    public function atributosEmpresa(): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('productos.ver'), 403, 'Sin permiso: productos.ver');
        $tipos = TipoAtributo::deEmpresa($this->empresaId())
            ->activos()
            ->with(['atributos' => fn($q) => $q->activos()->orderBy('valor')])
            ->orderBy('nombre')
            ->get();

        return response()->json($tipos);
    }

    // ── Importacion de productos ─────────────────────────────────────────────
    public function plantillaImportacion(): BinaryFileResponse
    {
        abort_unless(Auth::user()->tienePermiso('productos.editar'), 403, 'Sin permiso: productos.editar');

        return Excel::download(new ProductosPlantillaExportacion(), 'plantilla_importacion_productos.xlsx');
    }

    public function previsualizarImportacion(Request $request, ProductosImportacionServicio $servicio): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('productos.editar'), 403, 'Sin permiso: productos.editar');

        $datos = $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        return response()->json($servicio->previsualizar($datos['archivo']));
    }

    public function importar(Request $request, ProductosImportacionServicio $servicio): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('productos.editar'), 403, 'Sin permiso: productos.editar');

        $datos = $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $resultado = $servicio->previsualizar($datos['archivo']);

        if (! $resultado['valido']) {
            return response()->json($resultado, 422);
        }

        return response()->json($servicio->importar($datos['archivo']));
    }

    // ── GET /api/productos/{id}/variantes ─────────────────────────────────────
    public function variantes(int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('productos.ver'), 403, 'Sin permiso: productos.ver');
        $producto = Producto::deEmpresa($this->empresaId())->findOrFail($id);

        $variantes = $producto->variantes()
            ->with(['producto:id,imagen', 'atributos.tipoAtributo', 'atributos.atributo'])
            ->get();

        $variantes = VariantImageResolver::applyResolvedImages($variantes)
            ->map(fn($v) => array_merge($v->toArray(), [
                'nombre_variante' => $v->nombreVariante(),
                'precio_vigente'  => $v->precioVigente(),
                'imagen_url'      => $v->imagen_url,
                'imagen_url_resuelta' => $v->imagen_url_resuelta,
            ]));

        return response()->json($variantes);
    }

    // ── POST /api/productos/{id}/variantes ────────────────────────────────────
    public function storeVariante(Request $request, int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('productos.editar'), 403, 'Sin permiso: productos.editar');
        $empresaId = $this->empresaId();
        $producto = Producto::deEmpresa($empresaId)->findOrFail($id);

        $datos = $request->validate([
            'sku'           => [
                'nullable', 'string', 'max:100',
                Rule::unique('producto_variantes', 'sku')
                    ->where(fn($q) => $q->where('empresa_id', $empresaId)),
            ],
            'codigo_barras' => [
                'nullable', 'string', 'max:100',
                Rule::unique('producto_variantes', 'codigo_barras')
                    ->where(fn($q) => $q->where('empresa_id', $empresaId)),
            ],
            'precio_costo'  => ['nullable', 'numeric', 'min:0'],
            'precio_venta'  => ['nullable', 'numeric', 'min:0'],
            'precio1'       => ['nullable', 'numeric', 'min:0'],
            'precio2'       => ['nullable', 'numeric', 'min:0'],
            'precio3'       => ['nullable', 'numeric', 'min:0'],
            'precio4'       => ['nullable', 'numeric', 'min:0'],
            'precio5'       => ['nullable', 'numeric', 'min:0'],
            'precio_oferta' => ['nullable', 'numeric', 'min:0'],
            'oferta_activa' => ['nullable', 'boolean'],
            'oferta_hasta'  => ['nullable', 'date'],
            'stock_minimo'  => ['nullable', 'numeric', 'min:0'],
            'activo'          => ['nullable', 'boolean'],
            'imagen'          => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'imagen_media_id' => ['nullable', 'integer'],
            'atributos'       => ['required', 'array', 'min:1'],
            'atributos.*'   => ['required', 'integer', 'exists:atributos,id'],
        ]);

        $stockSinVariante = Inventario::where('empresa_id', $empresaId)
            ->where('producto_id', $id)
            ->whereNull('variante_id')
            ->where('stock', '>', 0)
            ->exists();

        if ($stockSinVariante) {
            return response()->json([
                'message' => 'No se pueden agregar variantes a este producto porque ya tiene existencia registrada sin variante. Primero ajusta o cancela ese stock.',
                'errors'  => [
                    'variantes' => ['El producto tiene stock sin variante activo.'],
                ],
            ], 422);
        }

        $atributosNuevos = collect($datos['atributos'])
            ->map(fn($atributoId) => (int) $atributoId)
            ->sort()
            ->values()
            ->toArray();

        $duplicado = ProductoVariante::where('empresa_id', $empresaId)
            ->where('producto_id', $id)
            ->whereNull('deleted_at')
            ->with('atributos')
            ->get()
            ->first(function ($v) use ($atributosNuevos) {
                $existentes = $v->atributos
                    ->pluck('atributo_id')
                    ->sort()
                    ->values()
                    ->toArray();

                return $existentes === $atributosNuevos;
            });

        if ($duplicado) {
            return response()->json([
                'message' => 'Ya existe una variante con esa combinación de atributos.',
                'errors'  => [
                    'atributos' => ['Esta combinación ya está registrada.'],
                ],
            ], 422);
        }

        $toNullIfEmpty = fn($v) => ($v === '' || $v === null) ? null : $v;

        DB::beginTransaction();

        try {
            $variante = ProductoVariante::create([
                'producto_id'   => $id,
                'empresa_id'    => $empresaId,
                'sku'           => $toNullIfEmpty($datos['sku'] ?? null) ?: ProductoVariante::generarSku($id, $empresaId),
                'codigo_barras' => $toNullIfEmpty($datos['codigo_barras'] ?? null),
                'imagen'        => null,
                'precio_costo'  => $toNullIfEmpty($datos['precio_costo'] ?? null),
                'precio_venta'  => $toNullIfEmpty($datos['precio_venta'] ?? null),
                'precio1'       => $toNullIfEmpty($datos['precio1'] ?? null),
                'precio2'       => $toNullIfEmpty($datos['precio2'] ?? null),
                'precio3'       => $toNullIfEmpty($datos['precio3'] ?? null),
                'precio4'       => $toNullIfEmpty($datos['precio4'] ?? null),
                'precio5'       => $toNullIfEmpty($datos['precio5'] ?? null),
                'precio_oferta' => $toNullIfEmpty($datos['precio_oferta'] ?? null),
                'oferta_activa' => $datos['oferta_activa'] ?? false,
                'oferta_hasta'  => $toNullIfEmpty($datos['oferta_hasta'] ?? null),
                'stock_minimo'  => $toNullIfEmpty($datos['stock_minimo'] ?? null),
                'activo'        => $datos['activo'] ?? true,
            ]);

            foreach ($datos['atributos'] as $tipoId => $atributoId) {
                $tipoId = (int) $tipoId;
                $atributoId = (int) $atributoId;

                $valido = \App\Models\Atributo::where('id', $atributoId)
                    ->where('tipo_atributo_id', $tipoId)
                    ->exists();

                if (! $valido) {
                    throw new \RuntimeException("El atributo {$atributoId} no pertenece al tipo {$tipoId}.");
                }

                VarianteAtributo::create([
                    'variante_id'      => $variante->id,
                    'tipo_atributo_id' => $tipoId,
                    'atributo_id'      => $atributoId,
                ]);
            }

            if ($request->filled('imagen_media_id')) {
                $variante->imagen = $this->asignarImagenDesdeMedia($variante, (int) $request->imagen_media_id);
                $variante->save();
            } elseif ($request->hasFile('imagen')) {
                $variante->imagen = $this->subirYRegistrarImagen($variante, $request->file('imagen'), "variantes/{$empresaId}");
                $variante->save();
            }

            if (! $producto->tiene_variantes) {
                $producto->update(['tiene_variantes' => true]);
            }

            app(KardexServicio::class)->registrar([
                'empresa_id' => $empresaId,
                'sucursal_id' => $this->sucursalId(),
                'producto_id' => $producto->id,
                'variante_id' => $variante->id,
                'user_id' => Auth::id(),
                'tipo' => 'alta_variante',
                'direccion' => 'neutro',
                'cantidad' => 0,
                'stock_antes' => 0,
                'stock_despues' => 0,
                'costo_unitario' => $variante->precio_costo !== null ? (float) $variante->precio_costo : null,
                'precio_unitario' => $variante->precio_venta !== null ? (float) $variante->precio_venta : null,
                'referencia_tipo' => 'producto_variante',
                'referencia_id' => $variante->id,
                'folio' => $variante->sku,
                'fecha' => $variante->created_at ?? now(),
                'metadata' => [
                    'producto_id' => $producto->id,
                    'producto_codigo' => $producto->codigo,
                    'sku' => $variante->sku,
                    'codigo_barras' => $variante->codigo_barras,
                ],
            ]);

            DB::commit();

            $variante->load(['producto', 'atributos.tipoAtributo', 'atributos.atributo']);

            return response()->json([
                'message' => 'Variante creada correctamente.',
                'data' => array_merge($variante->toArray(), [
                    'nombre_variante' => $variante->nombreVariante(),
                    'precio_vigente'  => $variante->precioVigente(),
                ]),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error al crear la variante.',
            ], 500);
        }
    }

    // ── PUT /api/productos/{id}/variantes/{varianteId} ────────────────────────
    public function updateVariante(Request $request, int $id, int $varianteId): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('productos.editar'), 403, 'Sin permiso: productos.editar');
        $variante = ProductoVariante::where('empresa_id', $this->empresaId())
            ->where('producto_id', $id)
            ->findOrFail($varianteId);

        $datos = $request->validate([
            'sku'             => [
                'nullable', 'string', 'max:100',
                Rule::unique('producto_variantes', 'sku')
                    ->where(fn($q) => $q->where('empresa_id', $this->empresaId()))
                    ->ignore($varianteId),
            ],
            'codigo_barras'   => [
                'nullable', 'string', 'max:100',
                Rule::unique('producto_variantes', 'codigo_barras')
                    ->where(fn($q) => $q->where('empresa_id', $this->empresaId()))
                    ->ignore($varianteId),
            ],
            'precio_costo'    => ['nullable', 'numeric', 'min:0'],
            'precio_venta'    => ['nullable', 'numeric', 'min:0'],
            'precio1'         => ['nullable', 'numeric', 'min:0'],
            'precio2'         => ['nullable', 'numeric', 'min:0'],
            'precio3'         => ['nullable', 'numeric', 'min:0'],
            'precio4'         => ['nullable', 'numeric', 'min:0'],
            'precio5'         => ['nullable', 'numeric', 'min:0'],
            'precio_oferta'   => ['nullable', 'numeric', 'min:0'],
            'oferta_activa'   => ['nullable', 'boolean'],
            'oferta_hasta'    => ['nullable', 'date'],
            'stock_minimo'    => ['nullable', 'numeric', 'min:0'],
            'activo'          => ['nullable', 'boolean'],
            'imagen'          => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'imagen_media_id' => ['nullable', 'integer'],
            'eliminar_imagen' => ['nullable', 'boolean'],
            'atributos'       => ['nullable', 'array', 'min:1'],
            'atributos.*'     => ['required', 'integer', 'exists:atributos,id'],
        ]);

        $toNullIfEmpty = fn($v) => ($v === '' || $v === null) ? null : $v;

        $atributosEditados = null;
        if (array_key_exists('atributos', $datos)) {
            $atributosEditados = collect($datos['atributos'])
                ->mapWithKeys(fn($atributoId, $tipoId) => [(int) $tipoId => (int) $atributoId])
                ->filter()
                ->sort()
                ->toArray();

            foreach ($atributosEditados as $tipoId => $atributoId) {
                $valido = \App\Models\Atributo::where('id', $atributoId)
                    ->where('tipo_atributo_id', $tipoId)
                    ->exists();

                if (! $valido) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "atributos.{$tipoId}" => ["El atributo seleccionado no pertenece a este tipo."],
                    ]);
                }
            }

            $atributosNuevos = collect($atributosEditados)->values()->sort()->values()->toArray();

            $duplicado = ProductoVariante::where('empresa_id', $this->empresaId())
                ->where('producto_id', $id)
                ->where('id', '!=', $varianteId)
                ->whereNull('deleted_at')
                ->with('atributos')
                ->get()
                ->first(function ($v) use ($atributosNuevos) {
                    $existentes = $v->atributos
                        ->pluck('atributo_id')
                        ->sort()
                        ->values()
                        ->toArray();

                    return $existentes === $atributosNuevos;
                });

            if ($duplicado) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'atributos' => ['Ya existe otra variante con esa combinacion de atributos.'],
                ]);
            }
        }

        $variante->sku           = $toNullIfEmpty($datos['sku'] ?? null);
        $variante->codigo_barras = $toNullIfEmpty($datos['codigo_barras'] ?? null);
        $variante->precio_costo  = $toNullIfEmpty($datos['precio_costo'] ?? null);
        $variante->precio_venta  = $toNullIfEmpty($datos['precio_venta'] ?? null);
        $variante->precio1       = $toNullIfEmpty($datos['precio1'] ?? null);
        $variante->precio2       = $toNullIfEmpty($datos['precio2'] ?? null);
        $variante->precio3       = $toNullIfEmpty($datos['precio3'] ?? null);
        $variante->precio4       = $toNullIfEmpty($datos['precio4'] ?? null);
        $variante->precio5       = $toNullIfEmpty($datos['precio5'] ?? null);
        $variante->precio_oferta = $toNullIfEmpty($datos['precio_oferta'] ?? null);
        $variante->oferta_hasta  = $toNullIfEmpty($datos['oferta_hasta'] ?? null);
        $variante->stock_minimo  = $toNullIfEmpty($datos['stock_minimo'] ?? null);

        if (array_key_exists('oferta_activa', $datos)) {
            $variante->oferta_activa = $datos['oferta_activa'];
        }

        if (array_key_exists('activo', $datos)) {
            $variante->activo = $datos['activo'];
        }

        if (!empty($datos['eliminar_imagen'])) {
            $this->quitarReferenciaMedia($variante);
            $this->borrarArchivoLegacy($variante->imagen);
            $variante->imagen = null;
        } elseif ($request->filled('imagen_media_id')) {
            $variante->imagen = $this->asignarImagenDesdeMedia($variante, (int) $request->imagen_media_id);
        } elseif ($request->hasFile('imagen')) {
            $this->borrarArchivoLegacy($variante->imagen);
            $variante->imagen = $this->subirYRegistrarImagen($variante, $request->file('imagen'), "variantes/{$variante->empresa_id}");
        }

        $variante->save();

        if ($atributosEditados !== null) {
            VarianteAtributo::where('variante_id', $variante->id)->delete();

            foreach ($atributosEditados as $tipoId => $atributoId) {
                VarianteAtributo::create([
                    'variante_id'      => $variante->id,
                    'tipo_atributo_id' => $tipoId,
                    'atributo_id'      => $atributoId,
                ]);
            }
        }

        $variante->load(['producto', 'atributos.tipoAtributo', 'atributos.atributo']);

        return response()->json([
            'message' => 'Variante actualizada correctamente.',
            'data' => array_merge($variante->toArray(), [
                'nombre_variante' => $variante->nombreVariante(),
                'precio_vigente'  => $variante->precioVigente(),
            ]),
        ]);
    }

    // ── PATCH /api/productos/{id}/variantes/restablecer-precios ───────────────
    // Limpia los precios propios de TODAS las variantes del producto para que
    // vuelvan a heredar los precios del producto padre (precio() en el modelo
    // resuelve variante ?? producto cuando el campo es null).
    public function restablecerPreciosVariantes(int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('productos.editar'), 403, 'Sin permiso: productos.editar');
        $empresaId = $this->empresaId();

        Producto::where('empresa_id', $empresaId)->findOrFail($id);

        $actualizadas = ProductoVariante::where('empresa_id', $empresaId)
            ->where('producto_id', $id)
            ->update([
                'precio_costo'  => null,
                'precio_venta'  => null,
                'precio1'       => null,
                'precio2'       => null,
                'precio3'       => null,
                'precio4'       => null,
                'precio5'       => null,
                'precio_oferta' => null,
                'oferta_activa' => false,
                'oferta_hasta'  => null,
            ]);

        return response()->json([
            'message'      => "Se restablecieron los precios de {$actualizadas} variante(s) al precio del producto padre.",
            'actualizadas' => $actualizadas,
        ]);
    }

    // ── DELETE /api/productos/{id}/variantes/{varianteId} ─────────────────────
    public function destroyVariante(int $id, int $varianteId): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('productos.eliminar'), 403, 'Sin permiso: productos.eliminar');
        $empresaId = $this->empresaId();

        $variante = ProductoVariante::where('empresa_id', $empresaId)
            ->where('producto_id', $id)
            ->findOrFail($varianteId);

        $tieneExistencia = Inventario::where('empresa_id', $empresaId)
            ->where('producto_id', $id)
            ->where('variante_id', $varianteId)
            ->where('stock', '>', 0)
            ->exists();

        if ($tieneExistencia) {
            return response()->json([
                'message' => 'No se puede eliminar la variante porque aún tiene existencias en inventario.',
            ], 422);
        }

        if ($variante->imagen) {
            Storage::disk('public')->delete($variante->imagen);
        }

        $variante->delete();

        $quedanVariantes = ProductoVariante::where('empresa_id', $empresaId)
            ->where('producto_id', $id)
            ->count() > 0;

        if (! $quedanVariantes) {
            Producto::where('empresa_id', $empresaId)
                ->where('id', $id)
                ->update(['tiene_variantes' => false]);
        }

        return response()->json([
            'message' => 'Variante eliminada correctamente.',
        ]);
    }

    // ── Privados ───────────────────────────────────────────────────────────────
    private function reglas(int $empresaId, ?int $ignoreId = null): array
    {
        $uniCodigo = Rule::unique('productos', 'codigo')
            ->where(fn($q) => $q->where('empresa_id', $empresaId)->whereNull('deleted_at'));

        if ($ignoreId) {
            $uniCodigo = $uniCodigo->ignore($ignoreId);
        }

        return [
            'nombre'           => ['required', 'string', 'min:2', 'max:200'],
            'codigo'           => ['nullable', 'string', 'max:100', $uniCodigo],
            'descripcion'      => ['nullable', 'string'],
            'categoria_id'     => [
                'nullable',
                'integer',
                Rule::exists('categorias', 'id')->where(fn($q) => $q
                    ->where('empresa_id', $empresaId)
                    ->whereNull('deleted_at')),
            ],
            'marca_id'         => [
                'nullable',
                'integer',
                Rule::exists('marcas', 'id')->where(fn($q) => $q
                    ->where('empresa_id', $empresaId)
                    ->whereNull('deleted_at')),
            ],
            'modelo_id'        => [
                'nullable',
                'integer',
                Rule::exists('modelos', 'id')->where(fn($q) => $q
                    ->where('empresa_id', $empresaId)
                    ->whereNull('deleted_at')),
            ],
            'unidad_medida_id' => [
                'nullable',
                'integer',
                Rule::exists('unidades_medida', 'id')->where(fn($q) => $q
                    ->where('empresa_id', $empresaId)
                    ->whereNull('deleted_at')),
            ],
            'imagen'           => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'imagen_media_id'  => ['nullable', 'integer'],
            'eliminar_imagen'  => ['nullable', 'boolean'],
            'precio_costo'     => ['required', 'numeric', 'min:0'],
            'precio_venta'     => ['required', 'numeric', 'min:0'],
            'precio1'          => ['nullable', 'numeric', 'min:0'],
            'precio2'          => ['nullable', 'numeric', 'min:0'],
            'precio3'          => ['nullable', 'numeric', 'min:0'],
            'precio4'          => ['nullable', 'numeric', 'min:0'],
            'precio5'          => ['nullable', 'numeric', 'min:0'],
            'stock_minimo'     => ['nullable', 'numeric', 'min:0'],
            'peso'             => ['nullable', 'numeric', 'min:0'],
            'activo'           => ['nullable', 'boolean'],
            'tiene_series'     => ['nullable', 'boolean'],
            'pedido_generico'  => ['nullable', 'boolean'],
        ];
    }

    private function mensajes(): array
    {
        return [
            'nombre.required'       => 'El nombre es obligatorio.',
            'codigo.unique'         => 'Ya existe un producto con ese código en esta empresa.',
            'precio_costo.required' => 'El precio de costo es obligatorio.',
            'precio_venta.required' => 'El precio de venta es obligatorio.',
            'imagen.image'          => 'El archivo debe ser una imagen.',
            'imagen.max'            => 'La imagen no debe superar 2MB.',
        ];
    }

    private function validarModeloMarca(array $datos): void
    {
        if (empty($datos['modelo_id']) || empty($datos['marca_id'])) {
            return;
        }

        $pertenece = DB::table('modelos')
            ->where('id', $datos['modelo_id'])
            ->where('marca_id', $datos['marca_id'])
            ->where('empresa_id', $this->empresaId())
            ->whereNull('deleted_at')
            ->exists();

        if (! $pertenece) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'modelo_id' => ['El modelo seleccionado no pertenece a la marca indicada.'],
            ]);
        }
    }
}
