<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Inventario;
use App\Models\InventarioConteo;
use App\Models\InventarioConteoDetalle;
use App\Models\InventarioConteoEvento;
use App\Models\InventarioExhibicion;
use App\Models\InventarioMovimiento;
use App\Servicios\KardexServicio;
use App\Models\Categoria;
use App\Models\Compra;
use App\Models\Devolucion;
use App\Models\DevolucionProveedor;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Serie;
use App\Models\Sucursal;
use App\Models\Traspaso;
use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InventarioConteoController extends Controller
{
    public function alcances(): JsonResponse
    {
        $this->autorizar('inventario.conteos.ver');
        $user = Auth::user();

        return response()->json([
            'categorias' => Categoria::where('empresa_id', $user->empresa_id)
                ->orderBy('nombre')
                ->get(['id', 'nombre']),
            'marcas' => Marca::where('empresa_id', $user->empresa_id)
                ->orderBy('nombre')
                ->get(['id', 'nombre']),
        ]);
    }

    public function index(): JsonResponse
    {
        $this->autorizar('inventario.conteos.ver');
        $user = Auth::user();
        $estado = request('estado');
        $q = trim((string) request('q', ''));

        $conteos = InventarioConteo::where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->with(['user:id,name', 'sucursal:id,nombre'])
            ->withCount('detalles')
            ->when($estado, fn($query) => $query->where('estado', $estado))
            ->when(request('desde'), fn($query, $desde) => $query->where('created_at', '>=', Carbon::parse($desde, 'America/Mexico_City')->startOfDay()->utc()))
            ->when(request('hasta'), fn($query, $hasta) => $query->where('created_at', '<=', Carbon::parse($hasta, 'America/Mexico_City')->endOfDay()->utc()))
            ->when($q !== '', fn($query) => $query->where(function ($sub) use ($q) {
                $sub->where('folio', 'like', "%{$q}%")
                    ->orWhere('notas', 'like', "%{$q}%");
            }))
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json($conteos);
    }

    public function store(Request $request): JsonResponse
    {
        $this->autorizar('inventario.conteos.crear');
        $user = Auth::user();

        $activo = InventarioConteo::where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->whereIn('estado', ['en_conteo', 'en_revision'])
            ->exists();
        abort_if($activo, 422, 'Ya existe un conteo activo en esta sucursal. Ciérralo o cancélalo antes de crear uno nuevo.');
        $data = $request->validate([
            'notas' => ['nullable', 'string', 'max:1000'],
            'alcance_tipo' => ['nullable', 'in:total,categoria,marca'],
            'alcance_id' => ['nullable', 'integer'],
        ]);

        $conteo = DB::transaction(function () use ($user, $data) {
            [$alcanceTipo, $alcanceId, $alcanceNombre] = $this->resolverAlcance($data);

            $conteo = InventarioConteo::create([
                'empresa_id' => $user->empresa_id,
                'sucursal_id' => $user->sucursal_id,
                'user_id' => $user->id,
                'folio' => $this->siguienteFolio((int) $user->empresa_id, (int) $user->sucursal_id),
                'modo' => 'ciego',
                'alcance_tipo' => $alcanceTipo,
                'alcance_id' => $alcanceId,
                'alcance_nombre' => $alcanceNombre,
                'estado' => 'en_conteo',
                'snapshot_at' => now(),
                'notas' => $data['notas'] ?? null,
            ]);

            $queryInventario = Inventario::where('empresa_id', $user->empresa_id)
                ->where('sucursal_id', $user->sucursal_id)
                ->with(['producto:id,nombre,codigo,precio_costo,tiene_variantes,categoria_id,marca_id', 'variante:id,producto_id,sku,precio_costo']);

            $this->aplicarAlcanceInventario($queryInventario, $alcanceTipo, $alcanceId);

            $queryInventario
                ->chunkById(200, function ($items) use ($conteo) {
                    foreach ($items as $inv) {
                        $detalle = InventarioConteoDetalle::firstOrNew([
                            'conteo_id' => $conteo->id,
                            'producto_id' => $inv->producto_id,
                            'variante_id' => $inv->variante_id,
                        ]);

                        $stockSistema = (float) $detalle->stock_sistema + (float) $inv->stock;
                        $detalle->fill([
                            'stock_sistema' => $stockSistema,
                            'cantidad_fisica' => (float) ($detalle->cantidad_fisica ?? 0),
                            'diferencia' => (float) ($detalle->cantidad_fisica ?? 0) - $stockSistema,
                            'costo_unitario' => (float) ($inv->variante?->precio_costo ?: $inv->producto?->precio_costo ?: 0),
                            'estado' => 'no_contado',
                        ])->save();
                    }
                });

            $this->registrarEvento($conteo, 'creado', 'Conteo creado', [
                'alcance_tipo' => $alcanceTipo,
                'alcance_id' => $alcanceId,
                'alcance_nombre' => $alcanceNombre,
            ]);

            return $conteo;
        });

        return response()->json($this->cargarConteo($conteo->id), 201);
    }

    public function show(int $id): JsonResponse
    {
        $this->autorizar('inventario.conteos.ver');
        return response()->json($this->cargarConteo($id));
    }

    public function exportarPdf(int $id): mixed
    {
        $this->autorizar('inventario.conteos.ver');

        $user     = Auth::user();
        $conteo   = $this->cargarConteo($id);
        $empresa  = Empresa::find($user->empresa_id);
        $sucursal = Sucursal::find($user->sucursal_id);

        $logoB64 = null;
        if ($empresa?->logo && Storage::disk('public')->exists($empresa->logo)) {
            $contenido = Storage::disk('public')->get($empresa->logo);
            $mime      = Storage::disk('public')->mimeType($empresa->logo) ?: 'image/png';
            $logoB64   = 'data:' . $mime . ';base64,' . base64_encode($contenido);
        }

        $pdf = Pdf::loadView('pdf.conteo-inventario', [
            'conteo'            => $conteo,
            'titulo'            => 'Conteo de Inventario ' . $conteo['folio'],
            'filtrosAplicados'  => [],
            'empresaNombre'     => $empresa?->nombre ?? config('app.name'),
            'empresaLogoB64'    => $logoB64,
            'empresaDireccion'  => $empresa?->direccion,
            'sucursalNombre'    => $sucursal?->nombre,
            'sucursalDireccion' => $sucursal?->direccion,
            'fecha'             => now('America/Mexico_City')->format('d/m/Y H:i'),
        ])->setPaper('letter', 'landscape');

        return $pdf->download('conteo_' . $conteo['folio'] . '.pdf');
    }

    public function escanear(Request $request, int $id): JsonResponse
    {
        $this->autorizar('inventario.conteos.capturar');
        $conteo = $this->conteoDeSucursal($id);
        abort_unless($conteo->estado === 'en_conteo', 422, 'El conteo no está abierto para captura.');

        $q = trim((string) $request->query('q', ''));
        if ($q === '') return response()->json(['tipo' => 'vacio', 'resultados' => []]);

        // Busqueda de serie/IMEI exacta -> captura directa
        $serie = $this->buscarSerieDisponible($conteo, $q);

        if ($serie) {
            $detalle = $this->ejecutarCaptura($conteo, [
                'producto_id' => $serie->producto_id,
                'variante_id' => $serie->variante_id,
                'cantidad' => 1,
                'modo' => 'sumar',
                'serie_id' => $serie->id,
                'identificador' => $serie->identificador,
            ]);
            return response()->json(['tipo' => 'capturado', 'detalle' => $this->detallePayload($detalle, false)]);
        }

        // Código de barras exacto en variantes
        $variante = ProductoVariante::whereHas('producto', fn($pq) => $pq
                ->where('empresa_id', $conteo->empresa_id)
                ->where('activo', true)
                ->where('tiene_variantes', true)
                ->when(($conteo->alcance_tipo ?? 'total') === 'categoria' && $conteo->alcance_id, fn($s) => $s->where('categoria_id', $conteo->alcance_id))
                ->when(($conteo->alcance_tipo ?? 'total') === 'marca' && $conteo->alcance_id, fn($s) => $s->where('marca_id', $conteo->alcance_id)))
            ->with('producto:id,nombre,codigo,tiene_series')
            ->where('codigo_barras', $q)
            ->first(['id', 'producto_id', 'sku', 'codigo_barras']);

        if ($variante) {
            if ($variante->producto?->tiene_series) {
                return response()->json(['tipo' => 'seleccion', 'resultados' => [$this->resultadoVariante($variante)]]);
            }

            $detalle = $this->ejecutarCaptura($conteo, [
                'producto_id' => $variante->producto_id,
                'variante_id' => $variante->id,
                'cantidad' => 1,
                'modo' => 'sumar',
            ]);
            return response()->json(['tipo' => 'capturado', 'detalle' => $this->detallePayload($detalle, false)]);
        }

        // Código exacto en productos sin variantes
        $producto = Producto::where('empresa_id', $conteo->empresa_id)
            ->where('activo', true)
            ->where('tiene_variantes', false)
            ->where('codigo', $q)
            ->first(['id', 'nombre', 'codigo', 'tiene_series']);

        if ($producto) {
            $this->aplicarAlcanceProducto(Producto::query(), $conteo);
            if ($this->productoDentroDelAlcance($producto, $conteo)) {
                if ($producto->tiene_series) {
                    return response()->json(['tipo' => 'seleccion', 'resultados' => [$this->resultadoProducto($producto)]]);
                }

                $detalle = $this->ejecutarCaptura($conteo, [
                    'producto_id' => $producto->id,
                    'variante_id' => null,
                    'cantidad' => 1,
                    'modo' => 'sumar',
                ]);
                return response()->json(['tipo' => 'capturado', 'detalle' => $this->detallePayload($detalle, false)]);
            }
        }

        // Sin coincidencia exacta -> busqueda por texto para seleccion manual
        $resultados = $this->buscarResultados($conteo, $q);
        if (empty($resultados)) {
            return response()->json(['tipo' => 'no_encontrado', 'resultados' => []]);
        }
        return response()->json(['tipo' => 'seleccion', 'resultados' => $resultados]);
    }

    private function ejecutarCaptura(InventarioConteo $conteo, array $data): InventarioConteoDetalle
    {
        return DB::transaction(function () use ($conteo, $data) {
            $producto = Producto::where('empresa_id', $conteo->empresa_id)->findOrFail($data['producto_id']);
            abort_unless($this->productoDentroDelAlcance($producto, $conteo), 422, 'El producto no pertenece al alcance de este conteo.');
            $varianteId = $data['variante_id'] ?? null;
            $variante = $varianteId
                ? ProductoVariante::where('producto_id', $producto->id)->findOrFail($varianteId)
                : null;
            $cantidad = (float) ($data['cantidad'] ?? 1);
            $identificador = $data['identificador'] ?? null;

            if ($producto->tiene_series) {
                abort_unless($identificador || !empty($data['serie_id']), 422, 'Escanea el IMEI o serie del producto seriado.');

                $serieQuery = Serie::where('empresa_id', $conteo->empresa_id)
                    ->where('sucursal_id', $conteo->sucursal_id)
                    ->where('producto_id', $producto->id)
                    ->where('variante_id', $varianteId)
                    ->where('estado', 'disponible')
                    ->with(['producto:id,nombre,codigo,tiene_series', 'variante:id,producto_id,sku,codigo_barras']);

                if (!empty($data['serie_id'])) {
                    $serieQuery->whereKey($data['serie_id']);
                } else {
                    $serieQuery->where(fn($sq) => $sq
                        ->where('imei', $identificador)
                        ->orWhere('imei2', $identificador)
                        ->orWhere('serie', $identificador));
                }

                $serie = $serieQuery->first();
                abort_unless($serie, 422, 'La serie/IMEI no está disponible para este producto en la sucursal.');
                abort_unless($this->productoDentroDelAlcance($serie->producto, $conteo), 422, 'El producto no pertenece al alcance de este conteo.');

                $cantidad = 1;
                $identificador = $serie->identificador;
            } elseif ($identificador || !empty($data['serie_id'])) {
                abort(422, 'Solo los productos seriados pueden capturarse por IMEI o serie.');
            }

            $detalle = InventarioConteoDetalle::firstOrCreate(
                ['conteo_id' => $conteo->id, 'producto_id' => $producto->id, 'variante_id' => $varianteId],
                [
                    'stock_sistema' => 0,
                    'cantidad_fisica' => 0,
                    'diferencia' => 0,
                    'costo_unitario' => (float) ($variante?->precio_costo ?: $producto->precio_costo ?? 0),
                    'estado' => 'nuevo_encontrado',
                ]
            );

            if (!empty($identificador)) {
                $series = $detalle->series_contadas ?? [];
                if (in_array($identificador, $series, true)) {
                    abort(422, 'Ese IMEI/serie ya fue contado.');
                }
                $series[] = $identificador;
                $detalle->series_contadas = $series;
            }

            $modo = $producto->tiene_series ? 'sumar' : ($data['modo'] ?? 'sumar');
            $anterior = (float) $detalle->cantidad_fisica;
            $detalle->cantidad_fisica = $modo === 'reemplazar' ? $cantidad : $anterior + $cantidad;

            $this->actualizarEstadoDetalle($detalle);
            $detalle->save();

            $this->registrarEvento($conteo, 'captura', 'Producto capturado', [
                'producto_id' => $producto->id,
                'variante_id' => $varianteId,
                'cantidad' => $cantidad,
                'anterior' => $anterior,
                'nueva' => (float) $detalle->cantidad_fisica,
                'identificador' => $identificador,
            ]);

            return $detalle->fresh(['producto:id,nombre,codigo,tiene_series', 'variante:id,producto_id,sku,codigo_barras']);
        });
    }

    private function buscarResultados(InventarioConteo $conteo, string $q): array
    {
        $productosQuery = Producto::where('empresa_id', $conteo->empresa_id)
            ->where('activo', true)
            ->where('tiene_variantes', false);
        $this->aplicarAlcanceProducto($productosQuery, $conteo);

        $productos = $productosQuery
            ->where(fn($pq) => $pq->where('nombre', 'like', "%{$q}%")->orWhere('codigo', 'like', "%{$q}%"))
            ->limit(8)
            ->get(['id', 'nombre', 'codigo', 'tiene_series'])
            ->map(fn($p) => [
                'producto_id' => $p->id, 'variante_id' => null, 'serie_id' => null, 'identificador' => null,
                'nombre' => $p->nombre, 'codigo' => $p->codigo, 'sku' => null, 'nombre_variante' => null,
                'tiene_series' => (bool) $p->tiene_series,
            ]);

        $variantes = ProductoVariante::whereHas('producto', fn($pq) => $pq
                ->where('empresa_id', $conteo->empresa_id)->where('activo', true)->where('tiene_variantes', true)
                ->when(($conteo->alcance_tipo ?? 'total') === 'categoria' && $conteo->alcance_id, fn($s) => $s->where('categoria_id', $conteo->alcance_id))
                ->when(($conteo->alcance_tipo ?? 'total') === 'marca' && $conteo->alcance_id, fn($s) => $s->where('marca_id', $conteo->alcance_id)))
            ->with('producto:id,nombre,codigo,tiene_series')
            ->where(fn($vq) => $vq->where('sku', 'like', "%{$q}%")->orWhere('codigo_barras', 'like', "%{$q}%")
                ->orWhereHas('producto', fn($pq) => $pq->where('nombre', 'like', "%{$q}%")->orWhere('codigo', 'like', "%{$q}%")))
            ->limit(12)
            ->get(['id', 'producto_id', 'sku', 'codigo_barras'])
            ->map(fn($v) => [
                'producto_id' => $v->producto_id, 'variante_id' => $v->id, 'serie_id' => null, 'identificador' => null,
                'nombre' => $v->producto?->nombre, 'codigo' => $v->producto?->codigo, 'sku' => $v->sku,
                'nombre_variante' => $v->nombreVariante(), 'tiene_series' => (bool) $v->producto?->tiene_series,
            ]);

        return $productos->concat($variantes)->values()->all();
    }

    private function buscarSerieDisponible(InventarioConteo $conteo, string $q): ?Serie
    {
        return Serie::where('empresa_id', $conteo->empresa_id)
            ->where('sucursal_id', $conteo->sucursal_id)
            ->where('estado', 'disponible')
            ->where(fn($sq) => $sq->where('imei', $q)->orWhere('imei2', $q)->orWhere('serie', $q))
            ->with(['producto:id,nombre,codigo,tiene_series,categoria_id,marca_id', 'variante:id,producto_id,sku,codigo_barras'])
            ->whereHas('producto', fn($pq) => $this->aplicarAlcanceProducto($pq, $conteo))
            ->first();
    }

    private function resultadoProducto(Producto $producto): array
    {
        return [
            'producto_id' => $producto->id,
            'variante_id' => null,
            'serie_id' => null,
            'identificador' => null,
            'nombre' => $producto->nombre,
            'codigo' => $producto->codigo,
            'sku' => null,
            'nombre_variante' => null,
            'tiene_series' => (bool) $producto->tiene_series,
        ];
    }

    private function resultadoVariante(ProductoVariante $variante): array
    {
        return [
            'producto_id' => $variante->producto_id,
            'variante_id' => $variante->id,
            'serie_id' => null,
            'identificador' => null,
            'nombre' => $variante->producto?->nombre,
            'codigo' => $variante->producto?->codigo,
            'sku' => $variante->sku,
            'nombre_variante' => $variante->nombreVariante(),
            'tiene_series' => (bool) $variante->producto?->tiene_series,
        ];
    }

    public function buscar(Request $request, int $id): JsonResponse
    {
        $this->autorizar('inventario.conteos.capturar');
        $conteo = $this->conteoDeSucursal($id);
        abort_unless($conteo->estado === 'en_conteo', 422, 'El conteo no está abierto para captura.');

        $q = trim((string) $request->query('q', ''));
        if ($q === '') return response()->json([]);

        $serie = $this->buscarSerieDisponible($conteo, $q);

        if ($serie) {
            return response()->json([[
                'producto_id' => $serie->producto_id,
                'variante_id' => $serie->variante_id,
                'serie_id' => $serie->id,
                'identificador' => $serie->identificador,
                'nombre' => $serie->producto?->nombre,
                'codigo' => $serie->producto?->codigo,
                'sku' => $serie->variante?->sku,
                'nombre_variante' => $serie->variante?->nombreVariante(),
                'tiene_series' => true,
            ]]);
        }

        $productosQuery = Producto::where('empresa_id', $conteo->empresa_id)
            ->where('activo', true)
            ->where('tiene_variantes', false);
        $this->aplicarAlcanceProducto($productosQuery, $conteo);

        $productos = $productosQuery
            ->where(fn($pq) => $pq->where('nombre', 'like', "%{$q}%")->orWhere('codigo', 'like', "%{$q}%"))
            ->limit(8)
            ->get(['id', 'nombre', 'codigo', 'tiene_series'])
            ->map(fn($p) => [
                'producto_id' => $p->id,
                'variante_id' => null,
                'serie_id' => null,
                'identificador' => null,
                'nombre' => $p->nombre,
                'codigo' => $p->codigo,
                'sku' => null,
                'nombre_variante' => null,
                'tiene_series' => (bool) $p->tiene_series,
            ]);

        $variantes = ProductoVariante::whereHas('producto', fn($pq) => $pq
                ->where('empresa_id', $conteo->empresa_id)
                ->where('activo', true)
                ->where('tiene_variantes', true)
                ->when(($conteo->alcance_tipo ?? 'total') === 'categoria' && $conteo->alcance_id, fn($sub) => $sub->where('categoria_id', $conteo->alcance_id))
                ->when(($conteo->alcance_tipo ?? 'total') === 'marca' && $conteo->alcance_id, fn($sub) => $sub->where('marca_id', $conteo->alcance_id)))
            ->with('producto:id,nombre,codigo,tiene_series')
            ->where(fn($vq) => $vq
                ->where('sku', 'like', "%{$q}%")
                ->orWhere('codigo_barras', 'like', "%{$q}%")
                ->orWhereHas('producto', fn($pq) => $pq->where('nombre', 'like', "%{$q}%")->orWhere('codigo', 'like', "%{$q}%")))
            ->limit(12)
            ->get(['id', 'producto_id', 'sku', 'codigo_barras'])
            ->map(fn($v) => [
                'producto_id' => $v->producto_id,
                'variante_id' => $v->id,
                'serie_id' => null,
                'identificador' => null,
                'nombre' => $v->producto?->nombre,
                'codigo' => $v->producto?->codigo,
                'sku' => $v->sku,
                'nombre_variante' => $v->nombreVariante(),
                'tiene_series' => (bool) $v->producto?->tiene_series,
            ]);

        return response()->json($productos->concat($variantes)->values());
    }

    public function capturar(Request $request, int $id): JsonResponse
    {
        $this->autorizar('inventario.conteos.capturar');
        $conteo = $this->conteoDeSucursal($id);
        abort_unless($conteo->estado === 'en_conteo', 422, 'El conteo no está abierto para captura.');

        $data = $request->validate([
            'producto_id' => ['required', 'integer'],
            'variante_id' => ['nullable', 'integer'],
            'cantidad' => ['nullable', 'numeric', 'min:0'],
            'modo' => ['nullable', 'in:sumar,reemplazar'],
            'serie_id' => ['nullable', 'integer'],
            'identificador' => ['nullable', 'string', 'max:120'],
        ]);

        $detalle = $this->ejecutarCaptura($conteo, $data);
        return response()->json($this->detallePayload($detalle, false));
    }

    public function eliminarLinea(Request $request, int $id): JsonResponse
    {
        $this->autorizar('inventario.conteos.capturar');
        $conteo = $this->conteoDeSucursal($id);
        abort_unless($conteo->estado === 'en_conteo', 422, 'El conteo no está abierto para captura.');

        $data = $request->validate([
            'producto_id' => ['required', 'integer'],
            'variante_id' => ['nullable', 'integer'],
        ]);

        DB::transaction(function () use ($conteo, $data) {
            $detalle = InventarioConteoDetalle::where('conteo_id', $conteo->id)
                ->where('producto_id', $data['producto_id'])
                ->where('variante_id', $data['variante_id'] ?? null)
                ->firstOrFail();

            $this->registrarEvento($conteo, 'linea_eliminada', 'Línea eliminada del conteo', [
                'producto_id' => $detalle->producto_id,
                'variante_id' => $detalle->variante_id,
                'cantidad_fisica' => $detalle->cantidad_fisica,
            ]);

            $detalle->delete();
        });

        return response()->json($this->cargarConteo($id));
    }

    public function quitarSerie(Request $request, int $id): JsonResponse
    {
        $this->autorizar('inventario.conteos.capturar');
        $conteo = $this->conteoDeSucursal($id);
        abort_unless($conteo->estado === 'en_conteo', 422, 'El conteo no está abierto para captura.');

        $data = $request->validate([
            'producto_id' => ['required', 'integer'],
            'variante_id' => ['nullable', 'integer'],
            'identificador' => ['required', 'string', 'max:120'],
        ]);

        DB::transaction(function () use ($conteo, $data) {
            $detalle = InventarioConteoDetalle::where('conteo_id', $conteo->id)
                ->where('producto_id', $data['producto_id'])
                ->where('variante_id', $data['variante_id'] ?? null)
                ->firstOrFail();

            $series = $detalle->series_contadas ?? [];
            $series = array_values(array_filter($series, fn($s) => $s !== $data['identificador']));
            $detalle->series_contadas = $series;
            $detalle->cantidad_fisica = count($series);
            $this->actualizarEstadoDetalle($detalle);
            $detalle->save();

            $this->registrarEvento($conteo, 'serie_quitada', 'Serie/IMEI eliminado del conteo', [
                'producto_id' => $detalle->producto_id,
                'variante_id' => $detalle->variante_id,
                'identificador' => $data['identificador'],
            ]);
        });

        return response()->json($this->cargarConteo($id));
    }

    public function cerrar(int $id): JsonResponse
    {
        $this->autorizar('inventario.conteos.revisar');
        $conteo = $this->conteoDeSucursal($id);
        abort_unless($conteo->estado === 'en_conteo', 422, 'El conteo no está en captura.');

        DB::transaction(function () use ($conteo) {
            $conteo->detalles()->each(function ($detalle) {
                $this->actualizarEstadoDetalle($detalle);
                $detalle->save();
            });
            $conteo->update(['estado' => 'en_revision', 'cerrado_at' => now(), 'revisado_por' => Auth::id()]);
            $this->registrarEvento($conteo, 'cerrado', 'Captura cerrada para revision');
        });

        return response()->json($this->cargarConteo($id));
    }

    public function reabrir(int $id): JsonResponse
    {
        $this->autorizar('inventario.conteos.revisar');
        $conteo = $this->conteoDeSucursal($id);
        abort_unless($conteo->estado === 'en_revision', 422, 'Solo se pueden reabrir conteos en revisión.');
        $conteo->update(['estado' => 'en_conteo', 'cerrado_at' => null]);
        $this->registrarEvento($conteo, 'reabierto', 'Conteo reabierto para captura');
        return response()->json($this->cargarConteo($id));
    }

    public function ajustar(Request $request, int $id): JsonResponse
    {
        $this->autorizar('inventario.conteos.ajustar');
        $conteo = $this->conteoDeSucursal($id);
        abort_unless($conteo->estado === 'en_revision', 422, 'El conteo debe estar en revisión.');
        $data = $request->validate(['motivo' => ['required', 'string', 'max:160']]);

        $movimientosPosteriores = $this->movimientosPosteriores($conteo);
        if ($movimientosPosteriores['total'] > 0) {
            $this->registrarEvento($conteo, 'ajuste_bloqueado', 'Ajuste bloqueado por movimientos posteriores al snapshot', $movimientosPosteriores);
            abort(422, 'Hay movimientos posteriores al snapshot. Reabre o crea un nuevo conteo antes de aplicar ajustes.');
        }

        DB::transaction(function () use ($conteo, $data) {
            $conteo->detalles()->where('diferencia', '!=', 0)->each(function ($detalle) use ($conteo, $data) {
                $inv = Inventario::firstOrCreate(
                    [
                        'empresa_id' => $conteo->empresa_id,
                        'sucursal_id' => $conteo->sucursal_id,
                        'producto_id' => $detalle->producto_id,
                        'variante_id' => $detalle->variante_id,
                    ],
                    ['stock' => 0, 'stock_minimo' => 0]
                );

                $anterior = (float) $inv->stock;
                $nuevo = (float) $detalle->cantidad_fisica;
                $delta = $nuevo - $anterior;
                if ($delta == 0.0) return;

                $inv->stock = $nuevo;
                $inv->save();
                if ($nuevo <= 0) {
                    $this->retirarExhibicionSinStock($inv);
                }

                InventarioMovimiento::create([
                    'empresa_id' => $conteo->empresa_id,
                    'sucursal_id' => $conteo->sucursal_id,
                    'producto_id' => $detalle->producto_id,
                    'variante_id' => $detalle->variante_id,
                    'conteo_id' => $conteo->id,
                    'conteo_detalle_id' => $detalle->id,
                    'user_id' => Auth::id(),
                    'tipo' => $delta > 0 ? 'ajuste_positivo' : 'ajuste_negativo',
                    'cantidad_anterior' => $anterior,
                    'cantidad_movimiento' => abs($delta),
                    'cantidad_nueva' => $nuevo,
                    'motivo' => $data['motivo'],
                ]);

                app(KardexServicio::class)->registrar([
                    'empresa_id' => $conteo->empresa_id,
                    'sucursal_id' => $conteo->sucursal_id,
                    'producto_id' => $detalle->producto_id,
                    'variante_id' => $detalle->variante_id,
                    'user_id' => Auth::id(),
                    'tipo' => $delta > 0 ? 'ajuste_positivo' : 'ajuste_negativo',
                    'direccion' => $delta > 0 ? 'entrada' : 'salida',
                    'cantidad' => abs($delta),
                    'stock_antes' => $anterior,
                    'stock_despues' => $nuevo,
                    'referencia_tipo' => 'inventario_conteo',
                    'referencia_id' => $conteo->id,
                    'referencia_detalle_id' => $detalle->id,
                    'motivo' => $data['motivo'],
                    'fecha' => now(),
                    'metadata' => [
                        'stock_sistema' => $anterior,
                        'cantidad_fisica' => $nuevo,
                        'diferencia' => $delta,
                    ],
                ]);
            });

            $conteo->update(['estado' => 'ajustado', 'ajustado_at' => now(), 'ajustado_por' => Auth::id()]);
            $this->registrarEvento($conteo, 'ajustado', 'Ajustes aplicados al inventario', ['motivo' => $data['motivo']]);
        });

        return response()->json($this->cargarConteo($id));
    }

    public function cancelar(int $id): JsonResponse
    {
        $this->autorizar('inventario.conteos.cancelar');
        $conteo = $this->conteoDeSucursal($id);
        abort_unless(in_array($conteo->estado, ['en_conteo', 'en_revision'], true), 422, 'Este conteo ya no se puede cancelar.');
        $conteo->update(['estado' => 'cancelado']);
        $this->registrarEvento($conteo, 'cancelado', 'Conteo cancelado');
        return response()->json($conteo);
    }

    private function cargarConteo(int $id): array
    {
        $conteo = $this->conteoDeSucursal($id)->load(['user:id,name', 'sucursal:id,nombre', 'eventos.user:id,name']);
        $mostrarSistema = $conteo->estado !== 'en_conteo';
        $queryDetalles = $conteo->detalles()
            ->with(['producto:id,nombre,codigo,tiene_series', 'variante:id,producto_id,sku,codigo_barras'])
            ->orderByDesc('updated_at');

        if (! $mostrarSistema) {
            $queryDetalles->where('cantidad_fisica', '>', 0);
        }

        $detalles = $queryDetalles
            ->get()
            ->map(fn($detalle) => $this->detallePayload($detalle, $mostrarSistema));

        return [
            'id' => $conteo->id,
            'folio' => $conteo->folio,
            'estado' => $conteo->estado,
            'modo' => $conteo->modo,
            'alcance_tipo' => $conteo->alcance_tipo ?? 'total',
            'alcance_id' => $conteo->alcance_id,
            'alcance_nombre' => $conteo->alcance_nombre,
            'sucursal' => $conteo->sucursal?->nombre,
            'responsable' => $conteo->user?->name,
            'snapshot_at' => $conteo->snapshot_at,
            'cerrado_at' => $conteo->cerrado_at,
            'ajustado_at' => $conteo->ajustado_at,
            'notas' => $conteo->notas,
            'resumen' => $this->resumen($conteo, $mostrarSistema),
            'movimientos_posteriores' => $mostrarSistema ? $this->movimientosPosteriores($conteo) : ['total' => 0],
            'eventos' => $conteo->eventos->sortByDesc('created_at')->values()->map(fn($evento) => [
                'id' => $evento->id,
                'tipo' => $evento->tipo,
                'descripcion' => $evento->descripcion,
                'usuario' => $evento->user?->name,
                'meta' => $evento->meta ?? [],
                'created_at' => $evento->created_at,
            ]),
            'detalles' => $detalles,
        ];
    }

    private function detallePayload(InventarioConteoDetalle $detalle, bool $mostrarSistema): array
    {
        return [
            'id' => $detalle->id,
            'producto_id' => $detalle->producto_id,
            'variante_id' => $detalle->variante_id,
            'nombre' => $detalle->producto?->nombre,
            'codigo' => $detalle->producto?->codigo,
            'sku' => $detalle->variante?->sku,
            'nombre_variante' => $detalle->variante?->nombreVariante(),
            'cantidad_fisica' => (float) $detalle->cantidad_fisica,
            'stock_sistema' => $mostrarSistema ? (float) $detalle->stock_sistema : null,
            'diferencia' => $mostrarSistema ? (float) $detalle->diferencia : null,
            'estado' => $mostrarSistema ? $detalle->estado : 'contado',
            'series_contadas' => $detalle->series_contadas ?? [],
            'notas' => $detalle->notas,
        ];
    }

    private function resumen(InventarioConteo $conteo, bool $mostrarSistema): array
    {
        $detalles = $conteo->detalles;
        if (! $mostrarSistema) {
            return [
                'lineas' => null,
                'total_snapshot' => $detalles->count(),
                'contadas' => $detalles->where('cantidad_fisica', '>', 0)->count(),
                'diferencias' => null,
                'faltantes' => null,
                'sobrantes' => null,
                'piezas_fisicas' => (float) $detalles->sum('cantidad_fisica'),
                'piezas_sistema' => null,
                'valor_diferencia' => null,
            ];
        }

        return [
            'lineas' => $detalles->count(),
            'contadas' => $detalles->where('cantidad_fisica', '>', 0)->count(),
            'diferencias' => $detalles->where('diferencia', '!=', 0)->count(),
            'faltantes' => $detalles->where('diferencia', '<', 0)->count(),
            'sobrantes' => $detalles->where('diferencia', '>', 0)->count(),
            'piezas_fisicas' => (float) $detalles->sum('cantidad_fisica'),
            'piezas_sistema' => (float) $detalles->sum('stock_sistema'),
            'valor_diferencia' => round((float) $detalles->sum(fn($d) => (float) $d->diferencia * (float) $d->costo_unitario), 2),
        ];
    }

    private function actualizarEstadoDetalle(InventarioConteoDetalle $detalle): void
    {
        $detalle->diferencia = (float) $detalle->cantidad_fisica - (float) $detalle->stock_sistema;
        $detalle->estado = match (true) {
            (float) $detalle->stock_sistema <= 0 && (float) $detalle->cantidad_fisica > 0 => 'nuevo_encontrado',
            (float) $detalle->cantidad_fisica == (float) $detalle->stock_sistema => 'completo',
            (float) $detalle->cantidad_fisica <= 0 && (float) $detalle->stock_sistema > 0 => 'no_contado',
            (float) $detalle->cantidad_fisica < (float) $detalle->stock_sistema => 'faltante',
            default => 'sobrante',
        };
    }

    private function conteoDeSucursal(int $id): InventarioConteo
    {
        $user = Auth::user();
        return InventarioConteo::where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->findOrFail($id);
    }

    private function resolverAlcance(array $data): array
    {
        $tipo = $data['alcance_tipo'] ?? 'total';
        if ($tipo === 'total') return ['total', null, 'Inventario completo'];

        $id = (int) ($data['alcance_id'] ?? 0);
        abort_unless($id > 0, 422, 'Selecciona el alcance del conteo.');

        $user = Auth::user();
        if ($tipo === 'categoria') {
            $nombre = Categoria::where('empresa_id', $user->empresa_id)->whereKey($id)->value('nombre');
            abort_unless($nombre, 422, 'Categoria no valida.');
            return [$tipo, $id, $nombre];
        }

        if ($tipo === 'marca') {
            $nombre = Marca::where('empresa_id', $user->empresa_id)->whereKey($id)->value('nombre');
            abort_unless($nombre, 422, 'Marca no valida.');
            return [$tipo, $id, $nombre];
        }

        abort(422, 'Alcance no valido.');
    }

    private function aplicarAlcanceInventario($query, string $tipo, ?int $id): void
    {
        if ($tipo === 'total' || ! $id) return;

        $query->whereHas('producto', fn($pq) => $this->aplicarAlcanceProducto($pq, (object) [
            'alcance_tipo' => $tipo,
            'alcance_id' => $id,
        ]));
    }

    private function aplicarAlcanceProducto($query, $conteo): void
    {
        if (($conteo->alcance_tipo ?? 'total') === 'categoria' && $conteo->alcance_id) {
            $query->where('categoria_id', $conteo->alcance_id);
        }

        if (($conteo->alcance_tipo ?? 'total') === 'marca' && $conteo->alcance_id) {
            $query->where('marca_id', $conteo->alcance_id);
        }
    }

    private function productoDentroDelAlcance(Producto $producto, InventarioConteo $conteo): bool
    {
        return match ($conteo->alcance_tipo ?? 'total') {
            'categoria' => (int) $producto->categoria_id === (int) $conteo->alcance_id,
            'marca' => (int) $producto->marca_id === (int) $conteo->alcance_id,
            default => true,
        };
    }

    private function registrarEvento(InventarioConteo $conteo, string $tipo, string $descripcion, array $meta = []): void
    {
        InventarioConteoEvento::create([
            'conteo_id' => $conteo->id,
            'user_id' => Auth::id(),
            'tipo' => $tipo,
            'descripcion' => $descripcion,
            'meta' => $meta ?: null,
        ]);
    }

    private function movimientosPosteriores(InventarioConteo $conteo): array
    {
        if (! $conteo->snapshot_at) {
            return ['total' => 0, 'ventas' => 0, 'compras' => 0, 'devoluciones_cliente' => 0, 'devoluciones_proveedor' => 0, 'traspasos' => 0, 'ajustes' => 0];
        }

        $base = [
            'empresa_id' => $conteo->empresa_id,
            'sucursal_id' => $conteo->sucursal_id,
        ];
        $desde = $conteo->snapshot_at;

        $ventas = Venta::where($base)->where('created_at', '>', $desde)->count();
        $compras = Compra::where($base)->where('created_at', '>', $desde)->count();
        $devolucionesCliente = Devolucion::where($base)->where('created_at', '>', $desde)->count();
        $devolucionesProveedor = DevolucionProveedor::where($base)->where('created_at', '>', $desde)->count();
        $traspasos = Traspaso::where('empresa_id', $conteo->empresa_id)
            ->where('created_at', '>', $desde)
            ->where(fn($q) => $q
                ->where('origen_sucursal_id', $conteo->sucursal_id)
                ->orWhere('destino_sucursal_id', $conteo->sucursal_id))
            ->count();
        $ajustes = InventarioMovimiento::where($base)
            ->where('created_at', '>', $desde)
            ->where(fn($q) => $q->whereNull('conteo_id')->orWhere('conteo_id', '!=', $conteo->id))
            ->count();

        return [
            'total' => $ventas + $compras + $devolucionesCliente + $devolucionesProveedor + $traspasos + $ajustes,
            'ventas' => $ventas,
            'compras' => $compras,
            'devoluciones_cliente' => $devolucionesCliente,
            'devoluciones_proveedor' => $devolucionesProveedor,
            'traspasos' => $traspasos,
            'ajustes' => $ajustes,
        ];
    }

    private function siguienteFolio(int $empresaId, int $sucursalId): string
    {
        $total = InventarioConteo::where('empresa_id', $empresaId)
            ->where('sucursal_id', $sucursalId)
            ->lockForUpdate()
            ->count() + 1;
        return 'CNT-' . now()->format('ymd') . '-' . str_pad((string) $total, 4, '0', STR_PAD_LEFT);
    }

    private function retirarExhibicionSinStock(Inventario $inventario): void
    {
        $query = InventarioExhibicion::deSucursal($inventario->empresa_id, $inventario->sucursal_id)
            ->activas()
            ->where('producto_id', $inventario->producto_id);

        if ($inventario->variante_id) {
            $query->where('variante_id', $inventario->variante_id);
        } else {
            $query->where('tipo_cobertura', 'producto');
        }

        $query->get()->each->retirar();
    }

    private function autorizar(string $permiso): void
    {
        abort_unless(Auth::user()->tienePermiso($permiso), 403, "Sin permiso: {$permiso}");
    }
}
