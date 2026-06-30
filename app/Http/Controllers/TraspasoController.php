<?php

namespace App\Http\Controllers;

use App\Models\Inventario;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Serie;
use App\Models\Sucursal;
use App\Models\Traspaso;
use App\Models\TraspasoDetalle;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TraspasoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('inventario.ver'), 403, 'Sin permiso: inventario.ver');
        $user = $request->user();
        $empresaId = (int) $user->empresa_id;

        $query = Traspaso::query()
            ->where('traspasos.empresa_id', $empresaId)
            ->with([
                'origen:id,nombre',
                'destino:id,nombre',
                'user:id,name',
                'cancelador:id,name',
                'receptor:id,name',
                'rechazador:id,name',
            ])
            ->when($request->input('tipo') === 'entrada', fn($q) => $q->where('traspasos.destino_sucursal_id', (int) $user->sucursal_id))
            ->when($request->input('tipo') === 'salida', fn($q) => $q->where('traspasos.origen_sucursal_id', (int) $user->sucursal_id))
            ->when($request->filled('estado'), fn($q) => $this->filtrarEstado($q, (string) $request->input('estado')))
            ->when($request->filled('sucursal_id'), function ($q) use ($request) {
                $sucursalId = (int) $request->sucursal_id;
                if ($request->input('tipo') === 'entrada') {
                    $q->where('traspasos.origen_sucursal_id', $sucursalId);
                    return;
                }

                if ($request->input('tipo') === 'salida') {
                    $q->where('traspasos.destino_sucursal_id', $sucursalId);
                    return;
                }

                $q->where(function ($sub) use ($sucursalId) {
                    $sub->where('traspasos.origen_sucursal_id', $sucursalId)
                        ->orWhere('traspasos.destino_sucursal_id', $sucursalId);
                });
            })
            ->when($request->filled('desde'), fn($q) => $q->where('traspasos.created_at', '>=', Carbon::parse($request->date('desde'), 'America/Mexico_City')->startOfDay()->utc()))
            ->when($request->filled('hasta'), fn($q) => $q->where('traspasos.created_at', '<=', Carbon::parse($request->date('hasta'), 'America/Mexico_City')->endOfDay()->utc()))
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $buscar = trim((string) $request->buscar);
                $q->where(function ($sub) use ($buscar) {
                    $sub->where('traspasos.folio', 'like', "%{$buscar}%")
                        ->orWhereHas('detalles', function ($detalle) use ($buscar) {
                            $detalle->where('producto_nombre', 'like', "%{$buscar}%")
                                ->orWhere('variante_nombre', 'like', "%{$buscar}%")
                                ->orWhere('serie_identificador', 'like', "%{$buscar}%")
                                ->orWhereHas('producto', fn($p) => $p->where('codigo', 'like', "%{$buscar}%"))
                                ->orWhereHas('variante', function ($v) use ($buscar) {
                                    $v->where('sku', 'like', "%{$buscar}%")
                                        ->orWhere('codigo_barras', 'like', "%{$buscar}%");
                                })
                                ->orWhereHas('serie', function ($s) use ($buscar) {
                                    $s->where('imei', 'like', "%{$buscar}%")
                                        ->orWhere('imei2', 'like', "%{$buscar}%")
                                        ->orWhere('serie', 'like', "%{$buscar}%");
                                });
                        });
                });
            });

        $summary = $this->resumenConsulta(clone $query);
        $this->ordenarConsulta($query, (string) $request->input('orden', 'fecha_desc'));

        $traspasos = $query->paginate($request->integer('per_page', 20));
        $payload = $traspasos->toArray();
        $payload['summary'] = $summary;

        return response()->json($payload);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('inventario.ver'), 403, 'Sin permiso: inventario.ver');
        $traspaso = Traspaso::where('empresa_id', (int) $request->user()->empresa_id)
            ->with([
                'origen:id,nombre',
                'destino:id,nombre',
                'user:id,name',
                'cancelador:id,name',
                'receptor:id,name',
                'rechazador:id,name',
                'detalles.producto:id,nombre,codigo,imagen,tiene_series,precio_costo,precio_venta',
                'detalles.variante:id,sku,codigo_barras,imagen,precio_costo,precio_venta',
                'detalles.serie:id,imei,imei2,serie,estado,sucursal_id',
            ])
            ->findOrFail($id);

        return response()->json($traspaso);
    }

    public function sucursales(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('inventario.traspasos'), 403, 'Sin permiso: inventario.traspasos');
        $user = $request->user();

        $sucursales = Sucursal::query()
            ->where('empresa_id', (int) $user->empresa_id)
            ->when($request->boolean('solo_destino'), fn($q) => $q->where('id', '!=', (int) $user->sucursal_id))
            ->where('activo', true)
            ->select('id', 'nombre', 'direccion')
            ->orderBy('nombre')
            ->get();

        return response()->json($sucursales);
    }

    public function resumenPendientes(Request $request): JsonResponse
    {
        $user = $request->user();

        $base = Traspaso::where('empresa_id', (int) $user->empresa_id)
            ->where('destino_sucursal_id', (int) $user->sucursal_id)
            ->where('estado', 'pendiente');

        $porRecibir = (clone $base)->count();
        $ultimo = (clone $base)
            ->with('origen:id,nombre')
            ->latest('id')
            ->first(['id', 'folio', 'origen_sucursal_id', 'created_at']);

        return response()->json([
            'por_recibir' => $porRecibir,
            'ultimo' => $ultimo ? [
                'id' => $ultimo->id,
                'folio' => $ultimo->folio,
                'origen' => $ultimo->origen?->nombre,
                'created_at' => $ultimo->created_at,
            ] : null,
        ]);
    }

    public function inventario(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('inventario.ver'), 403, 'Sin permiso: inventario.ver');
        $user = $request->user();
        $empresaId = (int) $user->empresa_id;
        $sucursalId = (int) $user->sucursal_id;
        $buscar = trim((string) $request->get('buscar', ''));

        $items = Inventario::query()
            ->where('empresa_id', $empresaId)
            ->where('sucursal_id', $sucursalId)
            ->where('stock', '>', 0)
            ->with([
                'producto:id,nombre,codigo,imagen,tiene_series,precio_costo,precio_venta',
                'variante:id,producto_id,sku,codigo_barras,imagen,precio_costo,precio_venta',
            ])
            ->when($buscar !== '', function ($q) use ($buscar) {
                $q->where(function ($sub) use ($buscar) {
                    $sub->whereHas('producto', function ($p) use ($buscar) {
                        $p->where('nombre', 'like', "%{$buscar}%")
                            ->orWhere('codigo', 'like', "%{$buscar}%");
                    })->orWhereHas('variante', function ($v) use ($buscar) {
                        $v->where('sku', 'like', "%{$buscar}%")
                            ->orWhere('codigo_barras', 'like', "%{$buscar}%");
                    });
                });
            })
            ->orderByDesc('stock')
            ->limit(30)
            ->get()
            ->map(fn($inv) => [
                'inventario_id' => $inv->id,
                'producto_id' => $inv->producto_id,
                'variante_id' => $inv->variante_id,
                'nombre' => $inv->producto?->nombre,
                'codigo' => $inv->producto?->codigo,
                'sku' => $inv->variante?->sku,
                'codigo_barras' => $inv->variante?->codigo_barras,
                'variante_nombre' => $inv->variante?->nombreVariante(),
                'precio_costo' => (float) ($inv->variante?->precio_costo ?? $inv->producto?->precio_costo ?? 0),
                'precio_venta' => (float) ($inv->variante?->precio_venta ?? $inv->producto?->precio_venta ?? 0),
                'stock' => (float) $inv->stock,
                'tiene_series' => (bool) $inv->producto?->tiene_series,
                'imagen_url' => $inv->variante?->imagen_url ?? $inv->producto?->imagen_url,
            ]);

        return response()->json($items);
    }

    public function seriesDisponibles(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('inventario.ver'), 403, 'Sin permiso: inventario.ver');
        $data = $request->validate([
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'variante_id' => ['nullable', 'integer', 'exists:producto_variantes,id'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();
        $q = trim((string) ($data['q'] ?? ''));

        $series = Serie::query()
            ->where('empresa_id', (int) $user->empresa_id)
            ->where('sucursal_id', (int) $user->sucursal_id)
            ->where('producto_id', (int) $data['producto_id'])
            ->where('estado', 'disponible')
            ->when(
                array_key_exists('variante_id', $data),
                fn($query) => $data['variante_id'] === null
                    ? $query->whereNull('variante_id')
                    : $query->where('variante_id', $data['variante_id'])
            )
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('imei', 'like', "%{$q}%")
                        ->orWhere('imei2', 'like', "%{$q}%")
                        ->orWhere('serie', 'like', "%{$q}%");
                });
            })
            ->select('id', 'imei', 'imei2', 'serie', 'producto_id', 'variante_id')
            ->orderBy('id')
            ->limit(30)
            ->get()
            ->map(fn($serie) => [
                'id' => $serie->id,
                'identificador' => $serie->identificador,
                'producto_id' => $serie->producto_id,
                'variante_id' => $serie->variante_id,
            ]);

        return response()->json($series);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('inventario.traspasos'), 403, 'Sin permiso: inventario.traspasos');
        $data = $request->validate([
            'destino_sucursal_id' => ['required', 'integer', 'exists:sucursales,id'],
            'notas' => ['nullable', 'string', 'max:1000'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'detalles.*.variante_id' => ['nullable', 'integer', 'exists:producto_variantes,id'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.001'],
            'detalles.*.serie_id' => ['nullable', 'integer', 'exists:series,id'],
        ]);

        $user = $request->user();
        $empresaId = (int) $user->empresa_id;
        $origenId = (int) $user->sucursal_id;
        $destinoId = (int) $data['destino_sucursal_id'];

        if ($origenId === $destinoId) {
            return response()->json(['message' => 'La sucursal destino debe ser diferente a la sucursal actual.'], 422);
        }

        $destino = Sucursal::where('empresa_id', $empresaId)
            ->where('activo', true)
            ->find($destinoId);

        if (! $destino) {
            return response()->json(['message' => 'La sucursal destino no pertenece a esta empresa o está inactiva.'], 422);
        }

        DB::beginTransaction();

        try {
            $traspaso = Traspaso::create([
                'empresa_id' => $empresaId,
                'origen_sucursal_id' => $origenId,
                'destino_sucursal_id' => $destinoId,
                'user_id' => (int) $user->id,
                'folio' => $this->siguienteFolio($empresaId),
                'estado' => 'pendiente',
                'notas' => $data['notas'] ?? null,
                'total_items' => 0,
            ]);

            $total = 0.0;

            foreach ($this->normalizarDetalles($data['detalles']) as $detalle) {
                $cantidad = $this->apartarDetalleOrigen($traspaso, $detalle);
                $total += $cantidad;
            }

            $traspaso->update(['total_items' => $total]);

            DB::commit();

            return response()->json(
                $traspaso->fresh()->load(['origen:id,nombre', 'destino:id,nombre', 'detalles']),
                201
            );
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['message' => 'No se pudo registrar el traspaso.'], 500);
        }
    }

    public function recibir(Request $request, int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('inventario.traspasos'), 403, 'Sin permiso: inventario.traspasos');
        $data = $request->validate([
            'detalle_ids' => ['nullable', 'array'],
            'detalle_ids.*' => ['integer'],
        ]);

        $user = $request->user();

        DB::beginTransaction();

        try {
            $traspaso = $this->traspasoPendiente($request, $id);

            if ((int) $traspaso->destino_sucursal_id !== (int) $user->sucursal_id) {
                throw new \RuntimeException('Solo la sucursal destino puede recibir este traspaso.');
            }

            $traspaso->load('detalles');

            $pendientes = $traspaso->detalles->where('estado', 'pendiente');
            if (array_key_exists('detalle_ids', $data)) {
                $ids = collect($data['detalle_ids'])->map(fn($id) => (int) $id)->unique()->values();
                $pendientes = $pendientes->whereIn('id', $ids);
            }

            if ($pendientes->isEmpty()) {
                throw new \RuntimeException('Selecciona al menos una partida pendiente para recibir.');
            }

            foreach ($pendientes as $detalle) {
                $this->recibirDetalleDestino($traspaso, $detalle);
                $detalle->update([
                    'estado' => 'recibido',
                    'cantidad_recibida' => $detalle->cantidad,
                ]);
            }

            if (! $traspaso->detalles()->where('estado', 'pendiente')->exists()) {
                $traspaso->update([
                    'estado' => 'recibido',
                    'recibido_por' => (int) $user->id,
                    'recibido_at' => now(),
                ]);
            }

            DB::commit();

            return response()->json($traspaso->fresh()->load(['origen:id,nombre', 'destino:id,nombre', 'detalles']));
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['message' => 'No se pudo recibir el traspaso.'], 500);
        }
    }

    public function rechazar(Request $request, int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('inventario.traspasos'), 403, 'Sin permiso: inventario.traspasos');
        $data = $request->validate([
            'motivo_rechazo' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();

        DB::beginTransaction();

        try {
            $traspaso = $this->traspasoPendiente($request, $id);

            if ((int) $traspaso->destino_sucursal_id !== (int) $user->sucursal_id) {
                throw new \RuntimeException('Solo la sucursal destino puede rechazar este traspaso.');
            }

            $this->devolverPendienteAOrigen($traspaso);

            $traspaso->update([
                'estado' => 'rechazado',
                'rechazado_por' => (int) $user->id,
                'rechazado_at' => now(),
                'motivo_rechazo' => $data['motivo_rechazo'] ?? null,
            ]);

            DB::commit();

            return response()->json($traspaso->fresh()->load(['origen:id,nombre', 'destino:id,nombre', 'detalles']));
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['message' => 'No se pudo rechazar el traspaso.'], 500);
        }
    }

    public function cancelar(Request $request, int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('inventario.traspasos'), 403, 'Sin permiso: inventario.traspasos');
        $data = $request->validate([
            'motivo_cancelacion' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();

        DB::beginTransaction();

        try {
            $traspaso = $this->traspasoPendiente($request, $id);

            if ((int) $traspaso->origen_sucursal_id !== (int) $user->sucursal_id) {
                throw new \RuntimeException('Solo la sucursal origen puede cancelar un traspaso pendiente.');
            }

            $this->devolverPendienteAOrigen($traspaso);

            $traspaso->update([
                'estado' => 'cancelado',
                'cancelado_por' => (int) $user->id,
                'cancelado_at' => now(),
                'motivo_cancelacion' => $data['motivo_cancelacion'] ?? null,
            ]);

            DB::commit();

            return response()->json($traspaso->fresh()->load(['origen:id,nombre', 'destino:id,nombre', 'detalles']));
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['message' => 'No se pudo cancelar el traspaso.'], 500);
        }
    }

    private function apartarDetalleOrigen(Traspaso $traspaso, array $detalle): float
    {
        $productoId = (int) $detalle['producto_id'];
        $varianteId = isset($detalle['variante_id']) ? (int) $detalle['variante_id'] : null;
        $serieId = isset($detalle['serie_id']) ? (int) $detalle['serie_id'] : null;

        $producto = Producto::where('empresa_id', $traspaso->empresa_id)
            ->where('id', $productoId)
            ->first();

        if (! $producto) {
            throw new \RuntimeException("El producto #{$productoId} no pertenece a esta empresa.");
        }

        $variante = null;
        if ($varianteId) {
            $variante = ProductoVariante::where('empresa_id', $traspaso->empresa_id)
                ->where('producto_id', $productoId)
                ->where('id', $varianteId)
                ->first();

            if (! $variante) {
                throw new \RuntimeException("La variante #{$varianteId} no pertenece al producto seleccionado.");
            }
        }

        $cantidad = (float) $detalle['cantidad'];
        $serie = null;

        if ($producto->tiene_series) {
            if (! $serieId) {
                throw new \RuntimeException("El producto {$producto->nombre} requiere seleccionar serie/IMEI.");
            }

            $cantidad = 1.0;
        }

        if ($serieId) {
            $serie = Serie::where('id', $serieId)
                ->where('empresa_id', $traspaso->empresa_id)
                ->where('sucursal_id', $traspaso->origen_sucursal_id)
                ->where('producto_id', $productoId)
                ->where('estado', 'disponible')
                ->lockForUpdate()
                ->first();

            if (! $serie || (int) ($serie->variante_id ?? 0) !== (int) ($varianteId ?? 0)) {
                throw new \RuntimeException('La serie/IMEI seleccionada no está disponible en la sucursal origen.');
            }
        }

        $origen = $this->inventarioQuery(
            (int) $traspaso->empresa_id,
            (int) $traspaso->origen_sucursal_id,
            $productoId,
            $varianteId
        )->lockForUpdate()->first();

        $stockOrigen = (float) ($origen?->stock ?? 0);
        if (! $origen || $stockOrigen < $cantidad) {
            throw new \RuntimeException("Stock insuficiente para {$producto->nombre}. Disponible: {$stockOrigen}, solicitado: {$cantidad}.");
        }

        $origen->descontarVenta($cantidad);

        if ($serie) {
            $serie->update(['estado' => 'apartado']);
        }

        TraspasoDetalle::create([
            'traspaso_id' => $traspaso->id,
            'producto_id' => $productoId,
            'variante_id' => $varianteId,
            'serie_id' => $serie?->id,
            'producto_nombre' => $producto->nombre,
            'variante_nombre' => $variante?->nombreVariante(),
            'serie_identificador' => $serie?->identificador,
            'cantidad' => $cantidad,
            'precio_costo' => $this->precioCosto($producto, $variante, $serie),
            'precio_venta' => $this->precioVenta($producto, $variante, $serie),
            'cantidad_recibida' => 0,
            'estado' => 'pendiente',
        ]);

        return $cantidad;
    }

    private function recibirDetalleDestino(Traspaso $traspaso, TraspasoDetalle $detalle): void
    {
        $producto = Producto::where('empresa_id', $traspaso->empresa_id)
            ->where('id', $detalle->producto_id)
            ->firstOrFail();

        $destino = $this->inventarioDestino(
            $traspaso,
            $producto,
            $detalle->variante_id ? (int) $detalle->variante_id : null
        );

        $destino->increment('stock', (float) $detalle->cantidad);

        if ($detalle->serie_id) {
            $serie = Serie::where('id', $detalle->serie_id)
                ->where('empresa_id', $traspaso->empresa_id)
                ->where('sucursal_id', $traspaso->origen_sucursal_id)
                ->where('estado', 'apartado')
                ->lockForUpdate()
                ->first();

            if (! $serie) {
                throw new \RuntimeException("La serie {$detalle->serie_identificador} ya no está disponible para recibir.");
            }

            $serie->update([
                'sucursal_id' => $traspaso->destino_sucursal_id,
                'estado' => 'disponible',
            ]);
        }
    }

    private function devolverPendienteAOrigen(Traspaso $traspaso): void
    {
        $traspaso->loadMissing('detalles');

        if ($traspaso->detalles->where('estado', 'recibido')->isNotEmpty()) {
            throw new \RuntimeException('No se puede rechazar o cancelar un traspaso que ya tiene partidas recibidas.');
        }

        foreach ($traspaso->detalles->where('estado', 'pendiente') as $detalle) {
            $origen = $this->obtenerOCrearInventario(
                (int) $traspaso->empresa_id,
                (int) $traspaso->origen_sucursal_id,
                (int) $detalle->producto_id,
                $detalle->variante_id ? (int) $detalle->variante_id : null,
                0
            );

            $origen->increment('stock', (float) $detalle->cantidad);

            if ($detalle->serie_id) {
                $serie = Serie::where('id', $detalle->serie_id)
                    ->where('empresa_id', $traspaso->empresa_id)
                    ->where('sucursal_id', $traspaso->origen_sucursal_id)
                    ->where('estado', 'apartado')
                    ->lockForUpdate()
                    ->first();

                if (! $serie) {
                    throw new \RuntimeException("La serie {$detalle->serie_identificador} ya no está disponible para devolver a origen.");
                }

                $serie->update(['estado' => 'disponible']);
            }

            $detalle->update(['estado' => 'rechazado']);
        }
    }

    private function precioCosto(Producto $producto, ?ProductoVariante $variante, ?Serie $serie): float
    {
        if ($serie && (float) $serie->precio_costo > 0) {
            return (float) $serie->precio_costo;
        }

        return (float) ($variante?->precio_costo ?? $producto->precio_costo ?? 0);
    }

    private function precioVenta(Producto $producto, ?ProductoVariante $variante, ?Serie $serie): float
    {
        if ($serie && $serie->precio_venta !== null && (float) $serie->precio_venta > 0) {
            return (float) $serie->precio_venta;
        }

        return (float) ($variante?->precio_venta ?? $producto->precio_venta ?? 0);
    }

    private function traspasoPendiente(Request $request, int $id): Traspaso
    {
        $traspaso = Traspaso::where('empresa_id', (int) $request->user()->empresa_id)
            ->where('id', $id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($traspaso->estado !== 'pendiente') {
            throw new \RuntimeException('Solo se puede operar un traspaso pendiente.');
        }

        return $traspaso;
    }

    private function filtrarEstado($query, string $estado): void
    {
        match ($estado) {
            'con_pendientes' => $query->where('traspasos.estado', 'pendiente')
                ->whereHas('detalles', fn($d) => $d->where('estado', 'pendiente')),
            'parcial' => $query->where('traspasos.estado', 'pendiente')
                ->whereHas('detalles', fn($d) => $d->where('estado', 'recibido'))
                ->whereHas('detalles', fn($d) => $d->where('estado', 'pendiente')),
            'completado' => $query->where('traspasos.estado', 'recibido'),
            default => $query->where('traspasos.estado', $estado),
        };
    }

    private function resumenConsulta($query): array
    {
        $row = $query
            ->leftJoin('traspaso_detalles as d', 'd.traspaso_id', '=', 'traspasos.id')
            ->selectRaw('COUNT(DISTINCT traspasos.id) as total_traspasos')
            ->selectRaw('COALESCE(SUM(d.cantidad), 0) as total_piezas')
            ->selectRaw('COALESCE(SUM(d.cantidad * d.precio_costo), 0) as total_compra')
            ->selectRaw('COALESCE(SUM(d.cantidad * d.precio_venta), 0) as total_venta')
            ->first();

        return [
            'total_traspasos' => (int) ($row?->total_traspasos ?? 0),
            'total_piezas' => (float) ($row?->total_piezas ?? 0),
            'total_compra' => (float) ($row?->total_compra ?? 0),
            'total_venta' => (float) ($row?->total_venta ?? 0),
        ];
    }

    private function ordenarConsulta($query, string $orden): void
    {
        $valorCompra = TraspasoDetalle::query()
            ->selectRaw('COALESCE(SUM(cantidad * precio_costo), 0)')
            ->whereColumn('traspaso_id', 'traspasos.id');

        $valorVenta = TraspasoDetalle::query()
            ->selectRaw('COALESCE(SUM(cantidad * precio_venta), 0)')
            ->whereColumn('traspaso_id', 'traspasos.id');

        match ($orden) {
            'fecha_asc' => $query->oldest(),
            'piezas_desc' => $query->orderByDesc('total_items')->latest(),
            'valor_compra_desc' => $query->orderByDesc($valorCompra)->latest(),
            'valor_venta_desc' => $query->orderByDesc($valorVenta)->latest(),
            default => $query->latest(),
        };
    }

    private function normalizarDetalles(array $detalles): array
    {
        $normalizados = [];
        $series = [];

        foreach ($detalles as $detalle) {
            $serieId = isset($detalle['serie_id']) ? (int) $detalle['serie_id'] : null;

            if ($serieId) {
                if (isset($series[$serieId])) {
                    throw new \RuntimeException('No puedes agregar la misma serie/IMEI más de una vez.');
                }

                $series[$serieId] = true;
                $normalizados[] = array_merge($detalle, ['cantidad' => 1]);
                continue;
            }

            $productoId = (int) $detalle['producto_id'];
            $varianteId = isset($detalle['variante_id']) ? (int) $detalle['variante_id'] : null;
            $key = "{$productoId}:{$varianteId}";

            if (! isset($normalizados[$key])) {
                $normalizados[$key] = [
                    'producto_id' => $productoId,
                    'variante_id' => $varianteId,
                    'cantidad' => 0,
                    'serie_id' => null,
                ];
            }

            $normalizados[$key]['cantidad'] += (float) $detalle['cantidad'];
        }

        return array_values($normalizados);
    }

    private function inventarioDestino(Traspaso $traspaso, Producto $producto, ?int $varianteId): Inventario
    {
        $stockMinimo = $varianteId
            ? (float) (ProductoVariante::whereKey($varianteId)->value('stock_minimo') ?? 0)
            : (float) ($producto->stock_minimo ?? 0);

        return $this->obtenerOCrearInventario(
            (int) $traspaso->empresa_id,
            (int) $traspaso->destino_sucursal_id,
            (int) $producto->id,
            $varianteId,
            $stockMinimo
        );
    }

    private function obtenerOCrearInventario(
        int $empresaId,
        int $sucursalId,
        int $productoId,
        ?int $varianteId,
        float $stockMinimo
    ): Inventario {
        $inventario = $this->inventarioQuery($empresaId, $sucursalId, $productoId, $varianteId)
            ->lockForUpdate()
            ->first();

        if ($inventario) {
            return $inventario;
        }

        Inventario::create([
            'empresa_id' => $empresaId,
            'sucursal_id' => $sucursalId,
            'producto_id' => $productoId,
            'variante_id' => $varianteId,
            'stock' => 0,
            'stock_minimo' => $stockMinimo,
        ]);

        return $this->inventarioQuery($empresaId, $sucursalId, $productoId, $varianteId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function inventarioQuery(int $empresaId, int $sucursalId, int $productoId, ?int $varianteId)
    {
        return Inventario::query()
            ->where('empresa_id', $empresaId)
            ->where('sucursal_id', $sucursalId)
            ->where('producto_id', $productoId)
            ->when(
                $varianteId,
                fn($q) => $q->where('variante_id', $varianteId),
                fn($q) => $q->whereNull('variante_id')
            );
    }

    private function siguienteFolio(int $empresaId): string
    {
        $prefijo = 'TRP-' . now()->format('ymd') . '-';
        $conteo = Traspaso::where('empresa_id', $empresaId)
            ->where('folio', 'like', "{$prefijo}%")
            ->lockForUpdate()
            ->count() + 1;

        return $prefijo . str_pad((string) $conteo, 4, '0', STR_PAD_LEFT);
    }
}
