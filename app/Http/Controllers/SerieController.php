<?php

namespace App\Http\Controllers;

use App\Models\Serie;
use App\Models\Inventario;
use App\Models\Producto;
use App\Servicios\KardexServicio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SerieController extends Controller
{
    /**
     * GET /api/series
     * Listado con filtros: estado, producto_id, variante_id, busqueda
     */
    public function index(Request $request): JsonResponse
    {
        $user       = Auth::user();
        $empresaId  = (int) $user->empresa_id;
        $sucursalId = (int) $user->sucursal_id;

        $query = Serie::with([
            'producto:id,nombre,codigo,imagen',
            'variante:id,sku',
            'proveedor:id,nombre_comercial',
            'venta:id,folio,fecha',
        ])
            ->deEmpresa($empresaId, $sucursalId);

        // Filtros
        if ($request->filled('estado'))      $query->where('estado', $request->estado);
        if ($request->filled('producto_id')) $query->where('producto_id', $request->producto_id);
        if ($request->filled('variante_id')) $query->where('variante_id', $request->variante_id);
        if ($request->filled('compra_id'))   $query->where('compra_id', $request->compra_id);

        if ($request->filled('busqueda')) {
            $b = $request->busqueda;
            $query->where(
                fn($q) =>
                $q->where('imei', 'like', "%{$b}%")
                    ->orWhere('imei2', 'like', "%{$b}%")
                    ->orWhere('serie', 'like', "%{$b}%")
                    ->orWhereHas('producto', fn($p) => $p->where('nombre', 'like', "%{$b}%"))
            );
        }

        $items = $query->orderByRaw("FIELD(estado, 'disponible', 'apartado', 'devuelto', 'vendido')")
            ->orderByDesc('id')
            ->paginate($request->input('per_page', 50));

        // Stats
        $base  = Serie::deEmpresa($empresaId, $sucursalId);
        $stats = [
            'disponible' => (clone $base)->where('estado', 'disponible')->count(),
            'vendido'    => (clone $base)->where('estado', 'vendido')->count(),
            'apartado'   => (clone $base)->where('estado', 'apartado')->count(),
            'devuelto'   => (clone $base)->where('estado', 'devuelto')->count(),
        ];

        return response()->json([
            'items'  => $items,
            'stats'  => $stats,
            'estados' => Serie::ESTADOS,
        ]);
    }

    /**
     * GET /api/series/buscar-imei?q=IMEI
     * Buscar por IMEI/serie para el POS (escaneo directo)
     */
    public function buscarImei(Request $request): JsonResponse
    {
        $user      = Auth::user();
        $empresaId = (int) $user->empresa_id;
        $q         = trim($request->input('q', ''));

        if (strlen($q) < 3) {
            return response()->json(['serie' => null, 'mensaje' => 'Mínimo 3 caracteres.']);
        }

        $serie = Serie::with([
            'producto:id,nombre,codigo,imagen,precio_venta,precio1,precio2,precio3,precio4,precio5',
            'variante:id,sku,precio_venta,precio1,precio2,precio3,precio4,precio5',
        ])
            ->where('empresa_id', $empresaId)
            ->where('estado', 'disponible')
            ->where(
                fn($q2) =>
                $q2->where('imei', $q)
                    ->orWhere('imei2', $q)
                    ->orWhere('serie', $q)
            )
            ->first();

        if (! $serie) {
            return response()->json(['serie' => null, 'mensaje' => 'IMEI/serie no encontrado o no disponible.']);
        }

        return response()->json([
            'serie'   => $this->formatearParaPOS($serie),
            'mensaje' => null,
        ]);
    }

    /**
     * GET /api/series/disponibles?producto_id=X&variante_id=Y
     * Lista de series disponibles para un producto/variante (modal del POS)
     */
    public function disponibles(Request $request): JsonResponse
    {
        $user       = Auth::user();
        $empresaId  = (int) $user->empresa_id;
        $sucursalId = (int) $user->sucursal_id;

        $request->validate([
            'producto_id' => 'required|integer',
        ]);

        $series = Serie::with(['variante:id,sku'])
            ->deEmpresa($empresaId, $sucursalId)
            ->disponibles()
            ->where('producto_id', $request->producto_id)
            ->when(
                $request->filled('variante_id'),
                fn($q) =>
                $q->where('variante_id', $request->variante_id)
            )
            ->orderBy('id')
            ->get()
            ->map(fn($s) => [
                'id'           => $s->id,
                'identificador' => $s->identificador,
                'imei'         => $s->imei,
                'imei2'        => $s->imei2,
                'serie'        => $s->serie,
                'variante_sku' => $s->variante?->sku,
                'precio_venta' => $s->precio_venta,
                'notas'        => $s->notas,
            ]);

        return response()->json(['series' => $series]);
    }

    /**
     * POST /api/series
     * Registrar una o varias series (al recibir compra)
     *
     * Body: { series: [ { imei, imei2, serie, variante_id, precio_costo, notas }, ... ] }
     */
    public function store(Request $request): JsonResponse
    {
        $user       = Auth::user();
        $empresaId  = (int) $user->empresa_id;
        $sucursalId = (int) $user->sucursal_id;

        $datos = $request->validate([
            'producto_id'        => 'required|integer|exists:productos,id',
            'compra_id'          => 'nullable|integer|exists:compras,id',
            'proveedor_id'       => 'nullable|integer|exists:proveedores,id',
            'series'             => 'required|array|min:1',
            'series.*.imei'      => 'nullable|string|max:20',
            'series.*.imei2'     => 'nullable|string|max:20',
            'series.*.serie'     => 'nullable|string|max:100',
            'series.*.variante_id'  => 'nullable|integer|exists:producto_variantes,id',
            'series.*.precio_costo' => 'nullable|numeric|min:0',
            'series.*.precio_venta' => 'nullable|numeric|min:0',
            'series.*.notas'     => 'nullable|string',
        ]);

        $productoId = (int) $datos['producto_id'];
        $errores    = [];
        $creadas    = [];

        DB::beginTransaction();
        try {
            foreach ($datos['series'] as $i => $s) {
                $identificador = $s['imei'] ?? $s['serie'] ?? null;

                if (! $identificador) {
                    $errores[] = "Línea " . ($i + 1) . ": debe tener IMEI o serie.";
                    continue;
                }

                // Verificar duplicado
                if (Serie::existeEnEmpresa($identificador, $empresaId)) {
                    $errores[] = "«{$identificador}» ya existe en el sistema.";
                    continue;
                }

                $serie = Serie::create([
                    'empresa_id'   => $empresaId,
                    'sucursal_id'  => $sucursalId,
                    'producto_id'  => $productoId,
                    'variante_id'  => $s['variante_id'] ?? null,
                    'compra_id'    => $datos['compra_id'] ?? null,
                    'proveedor_id' => $datos['proveedor_id'] ?? null,
                    'imei'         => $s['imei'] ?? null,
                    'imei2'        => $s['imei2'] ?? null,
                    'serie'        => $s['serie'] ?? null,
                    'precio_costo' => $s['precio_costo'] ?? 0,
                    'precio_venta' => $s['precio_venta'] ?? null,
                    'estado'       => 'disponible',
                    'notas'        => $s['notas'] ?? null,
                ]);

                $creadas[] = $serie;

                // Incrementar stock en inventario
                $varianteId = $s['variante_id'] ?? null;
                $inv = Inventario::firstOrCreate(
                    [
                        'empresa_id'  => $empresaId,
                        'sucursal_id' => $sucursalId,
                        'producto_id' => $productoId,
                        'variante_id' => $varianteId,
                    ],
                    ['stock' => 0, 'stock_minimo' => 0]
                );
                $stockAntes = (float) $inv->stock;
                $stockDespues = $stockAntes + 1;
                $inv->stock = $stockDespues;
                $inv->save();

                app(KardexServicio::class)->registrar([
                    'empresa_id' => $empresaId,
                    'sucursal_id' => $sucursalId,
                    'producto_id' => $productoId,
                    'variante_id' => $varianteId,
                    'serie_id' => $serie->id,
                    'user_id' => Auth::id(),
                    'tipo' => 'alta_serie',
                    'direccion' => 'entrada',
                    'cantidad' => 1,
                    'stock_antes' => $stockAntes,
                    'stock_despues' => $stockDespues,
                    'costo_unitario' => (float) ($s['precio_costo'] ?? 0),
                    'precio_unitario' => isset($s['precio_venta']) ? (float) $s['precio_venta'] : null,
                    'referencia_tipo' => 'serie',
                    'referencia_id' => $serie->id,
                    'folio' => $serie->identificador,
                    'fecha' => $serie->created_at ?? now(),
                    'metadata' => [
                        'compra_id' => $serie->compra_id,
                        'proveedor_id' => $serie->proveedor_id,
                    ],
                ]);
            }

            if (! empty($errores) && empty($creadas)) {
                DB::rollBack();
                return response()->json(['errors' => $errores], 422);
            }

            DB::commit();

            return response()->json([
                'creadas' => count($creadas),
                'errores' => $errores,
                'series'  => $creadas,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * PATCH /api/series/{serie}
     * Editar notas, precio_venta, estado (devuelto → disponible, etc.)
     */
    public function update(Request $request, Serie $serie): JsonResponse
    {
        $user = Auth::user();

        if ($serie->empresa_id !== (int) $user->empresa_id) {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        $datos = $request->validate([
            'precio_venta' => 'nullable|numeric|min:0',
            'precio_costo' => 'nullable|numeric|min:0',
            'estado'       => 'nullable|in:disponible,apartado,devuelto',
            'notas'        => 'nullable|string',
        ]);

        // No permitir editar series vendidas
        if ($serie->estado === 'vendido') {
            return response()->json(['error' => 'No se puede editar una serie ya vendida.'], 422);
        }

        $serie->update($datos);

        return response()->json([
            'message' => 'Serie actualizada.',
            'serie'   => $serie->fresh(['producto', 'variante']),
        ]);
    }

    /**
     * GET /api/series/{serie}
     * Detalle de una serie (historial completo)
     */
    public function show(Serie $serie): JsonResponse
    {
        $user = Auth::user();

        if ($serie->empresa_id !== (int) $user->empresa_id) {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        return response()->json(
            $serie->load(['producto', 'variante', 'compra', 'proveedor', 'venta'])
        );
    }

    // ── Helper privado ────────────────────────────────────────────────────────
    private function formatearParaPOS(Serie $s): array
    {
        $producto = $s->producto;
        $variante = $s->variante;

        $resolverPrecio = function (string $campo) use ($s, $producto, $variante): ?float {
            // Precio específico de la serie
            if ($campo === 'precio_venta' && $s->precio_venta) {
                return (float) $s->precio_venta;
            }
            // Variante → producto
            $val = $variante?->{$campo} ?? $producto?->{$campo} ?? null;
            return $val && (float) $val > 0 ? (float) $val : null;
        };

        return [
            'serie_id'       => $s->id,
            'identificador'  => $s->identificador,
            'imei'           => $s->imei,
            'producto_id'    => $s->producto_id,
            'variante_id'    => $s->variante_id,
            'nombre'         => $producto?->nombre,
            'codigo'         => $producto?->codigo,
            'sku'            => $variante?->sku,
            'imagen_url'     => $variante?->imagen_url ?? $producto?->imagen_url,
            'precio_venta'   => $resolverPrecio('precio_venta') ?? 0,
            'precio_costo'   => $resolverPrecio('precio_costo') ?? 0,
            'precio1'        => $resolverPrecio('precio1'),
            'precio2'        => $resolverPrecio('precio2'),
            'precio3'        => $resolverPrecio('precio3'),
            'precio4'        => $resolverPrecio('precio4'),
            'precio5'        => $resolverPrecio('precio5'),
            'tiene_series'   => true,
            'stock'          => 1, // es una unidad específica
            'sin_stock'      => false,
        ];
    }


    public function verificarImei(Request $request): JsonResponse
    {
        $empresaId = (int) Auth::user()->empresa_id;
        $imei      = trim($request->q ?? $request->imei ?? '');

        if (! $imei) {
            return response()->json(['disponible' => false, 'mensaje' => 'IMEI vacío.']);
        }

        $existe = Serie::where('empresa_id', $empresaId)
            ->where('imei', $imei)
            ->whereIn('estado', ['disponible', 'apartado']) // vendido/devuelto se puede reutilizar
            ->exists();

        return response()->json([
            'disponible' => ! $existe,
            'mensaje'    => $existe ? 'Este IMEI ya está registrado en inventario.' : null,
        ]);
    }
}
