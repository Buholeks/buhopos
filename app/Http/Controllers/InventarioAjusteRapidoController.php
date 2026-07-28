<?php

namespace App\Http\Controllers;

use App\Models\Inventario;
use App\Models\InventarioAjusteRapido;
use App\Models\InventarioMovimiento;
use App\Models\Serie;
use App\Servicios\KardexServicio;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventarioAjusteRapidoController extends Controller
{
    public function buscar(Request $request): JsonResponse
    {
        $this->autorizar($request);
        $buscar = trim((string) $request->get('q', ''));
        abort_if(mb_strlen($buscar) < 2, 422, 'Escribe al menos 2 caracteres.');
        $user = $request->user();

        $items = Inventario::query()
            ->where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->with([
                'producto:id,nombre,codigo,imagen,tiene_series',
                'variante:id,producto_id,sku,codigo_barras,imagen',
            ])
            ->where(function ($query) use ($buscar) {
                $query->whereHas('producto', fn($q) => $q
                    ->where('nombre', 'like', "%{$buscar}%")
                    ->orWhere('codigo', 'like', "%{$buscar}%"))
                    ->orWhereHas('variante', fn($q) => $q
                        ->where('sku', 'like', "%{$buscar}%")
                        ->orWhere('codigo_barras', 'like', "%{$buscar}%"));
            })
            ->orderByDesc('stock')
            ->limit(20)
            ->get()
            ->map(fn(Inventario $inv) => $this->itemPayload($inv));

        return response()->json($items);
    }

    public function series(Request $request): JsonResponse
    {
        $this->autorizar($request);
        $data = $request->validate([
            'producto_id' => ['required', 'integer'],
            'variante_id' => ['nullable', 'integer'],
        ]);
        $user = $request->user();

        $series = Serie::query()
            ->where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->where('producto_id', $data['producto_id'])
            ->where('variante_id', $data['variante_id'] ?? null)
            ->where('estado', 'disponible')
            ->orderBy('id')
            ->get()
            ->map(fn(Serie $serie) => ['id' => $serie->id, 'identificador' => $serie->identificador]);

        return response()->json($series);
    }

    public function historial(Request $request): JsonResponse
    {
        $this->autorizar($request);
        $user = $request->user();
        $items = InventarioAjusteRapido::query()
            ->where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->with('user:id,name')
            ->withCount('detalles')
            ->latest('id')
            ->limit(10)
            ->get(['id', 'folio', 'tipo', 'motivo', 'user_id', 'created_at']);

        return response()->json($items);
    }

    public function consulta(Request $request): JsonResponse
    {
        $this->autorizar($request);
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'por_pagina' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);
        $user = $request->user();
        $buscar = trim((string) ($data['q'] ?? ''));

        $ajustes = InventarioAjusteRapido::query()
            ->where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->with('user:id,name')
            ->withCount('detalles')
            ->when($data['desde'] ?? null, function ($q, $desde) {
                $inicioUtc = Carbon::createFromFormat('Y-m-d H:i:s', "{$desde} 00:00:00", 'America/Mexico_City')->utc();
                $q->where('created_at', '>=', $inicioUtc);
            })
            ->when($data['hasta'] ?? null, function ($q, $hasta) {
                $finUtc = Carbon::createFromFormat('Y-m-d H:i:s', "{$hasta} 23:59:59", 'America/Mexico_City')->utc();
                $q->where('created_at', '<=', $finUtc);
            })
            ->when($buscar !== '', function ($q) use ($buscar) {
                $q->where(function ($sub) use ($buscar) {
                    $sub->where('folio', 'like', "%{$buscar}%")
                        ->orWhere('motivo', 'like', "%{$buscar}%")
                        ->orWhereHas('detalles', function ($detalle) use ($buscar) {
                            $detalle->whereHas('producto', fn($producto) => $producto
                                ->where('nombre', 'like', "%{$buscar}%")
                                ->orWhere('codigo', 'like', "%{$buscar}%"))
                                ->orWhereHas('variante', fn($variante) => $variante
                                    ->where('sku', 'like', "%{$buscar}%")
                                    ->orWhere('codigo_barras', 'like', "%{$buscar}%"));
                        });
                });
            })
            ->latest('id')
            ->paginate($data['por_pagina'] ?? 20);

        return response()->json($ajustes);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request);
        $user = $request->user();
        $ajuste = InventarioAjusteRapido::query()
            ->where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->with([
                'user:id,name',
                'detalles.producto:id,nombre,codigo,tiene_series',
                'detalles.variante:id,producto_id,sku,codigo_barras',
            ])
            ->findOrFail($id);

        $serieIds = $ajuste->detalles->flatMap(fn($detalle) => $detalle->serie_ids ?? [])->unique()->values();
        $series = Serie::query()
            ->where('empresa_id', $user->empresa_id)
            ->whereIn('id', $serieIds)
            ->get()
            ->keyBy('id');

        return response()->json([
            'id' => $ajuste->id,
            'folio' => $ajuste->folio,
            'tipo' => $ajuste->tipo,
            'motivo' => $ajuste->motivo,
            'usuario' => $ajuste->user?->name,
            'created_at' => $ajuste->created_at,
            'detalles' => $ajuste->detalles->map(fn($detalle) => [
                'id' => $detalle->id,
                'nombre' => $detalle->producto?->nombre,
                'codigo' => $detalle->producto?->codigo,
                'variante' => $detalle->variante?->nombreVariante(),
                'sku' => $detalle->variante?->sku,
                'stock_antes' => (float) $detalle->stock_antes,
                'stock_despues' => (float) $detalle->stock_despues,
                'cantidad' => (float) $detalle->cantidad,
                'tipo' => (float) $detalle->stock_despues > (float) $detalle->stock_antes ? 'entrada' : 'salida',
                'series' => collect($detalle->serie_ids ?? [])
                    ->map(fn($serieId) => [
                        'id' => $serieId,
                        'identificador' => $series->get($serieId)?->identificador ?? "ID#{$serieId}",
                    ])->values(),
            ])->values(),
        ]);
    }

    public function store(Request $request, KardexServicio $kardex): JsonResponse
    {
        $this->autorizar($request);
        $data = $request->validate([
            'motivo' => ['required', 'string', 'max:160'],
            'partidas' => ['required', 'array', 'min:1', 'max:100'],
            'partidas.*.producto_id' => ['required', 'integer'],
            'partidas.*.variante_id' => ['nullable', 'integer'],
            'partidas.*.nueva_existencia' => ['required', 'numeric', 'min:0'],
            'partidas.*.serie_ids' => ['nullable', 'array'],
            'partidas.*.serie_ids.*' => ['integer', 'distinct'],
        ]);
        $user = $request->user();
        $claves = collect($data['partidas'])
            ->map(fn(array $partida) => $partida['producto_id'] . ':' . ($partida['variante_id'] ?? 'null'));
        abort_if($claves->unique()->count() !== $claves->count(), 422, 'No repitas el mismo producto o variante en el ajuste.');

        $ajuste = DB::transaction(function () use ($data, $user, $kardex) {
            $ajuste = InventarioAjusteRapido::create([
                'empresa_id' => $user->empresa_id,
                'sucursal_id' => $user->sucursal_id,
                'user_id' => $user->id,
                'folio' => $this->siguienteFolio((int) $user->empresa_id),
                'tipo' => 'mixto',
                'motivo' => $data['motivo'],
            ]);
            $tiposAplicados = [];

            foreach ($data['partidas'] as $partida) {
                $inv = Inventario::query()
                    ->where('empresa_id', $user->empresa_id)
                    ->where('sucursal_id', $user->sucursal_id)
                    ->where('producto_id', $partida['producto_id'])
                    ->where('variante_id', $partida['variante_id'] ?? null)
                    ->with('producto:id,tiene_series')
                    ->lockForUpdate()
                    ->firstOrFail();

                $antes = (float) $inv->stock;
                $despues = (float) $partida['nueva_existencia'];
                $delta = $despues - $antes;
                abort_if($delta == 0.0, 422, 'La nueva existencia debe ser diferente a la actual.');
                $tipo = $delta > 0 ? 'entrada' : 'salida';
                $cantidad = abs($delta);
                $tiposAplicados[] = $tipo;

                $serieIds = array_values($partida['serie_ids'] ?? []);
                if ($inv->producto?->tiene_series) {
                    abort_if($tipo === 'entrada', 422, 'No se puede aumentar un producto con serie sin capturar sus identificadores.');
                    abort_if(count($serieIds) !== (int) $cantidad || $cantidad !== (float) (int) $cantidad, 422, 'Selecciona una serie por cada unidad de la partida.');
                    $series = Serie::query()
                        ->whereIn('id', $serieIds)
                        ->where('empresa_id', $user->empresa_id)
                        ->where('sucursal_id', $user->sucursal_id)
                        ->where('producto_id', $inv->producto_id)
                        ->where('variante_id', $inv->variante_id)
                        ->where('estado', 'disponible')
                        ->lockForUpdate()
                        ->get();
                    abort_if($series->count() !== count($serieIds), 422, 'Una o más series ya no están disponibles.');
                    Serie::whereIn('id', $serieIds)->update(['estado' => 'baja']);
                } else {
                    abort_if(count($serieIds) > 0, 422, 'Esta partida no maneja series.');
                }

                $inv->update(['stock' => $despues]);
                if ($despues <= 0) {
                    $inv->quitarExhibicion();
                }

                $detalle = $ajuste->detalles()->create([
                    'producto_id' => $inv->producto_id,
                    'variante_id' => $inv->variante_id,
                    'cantidad' => $cantidad,
                    'stock_antes' => $antes,
                    'stock_despues' => $despues,
                    'serie_ids' => $serieIds ?: null,
                ]);
                $tipoMovimiento = $tipo === 'entrada' ? 'ajuste_positivo' : 'ajuste_negativo';

                InventarioMovimiento::create([
                    'empresa_id' => $user->empresa_id,
                    'sucursal_id' => $user->sucursal_id,
                    'producto_id' => $inv->producto_id,
                    'variante_id' => $inv->variante_id,
                    'user_id' => $user->id,
                    'tipo' => $tipoMovimiento,
                    'cantidad_anterior' => $antes,
                    'cantidad_movimiento' => $cantidad,
                    'cantidad_nueva' => $despues,
                    'motivo' => $data['motivo'],
                ]);

                $kardex->registrar([
                    'empresa_id' => $user->empresa_id,
                    'sucursal_id' => $user->sucursal_id,
                    'producto_id' => $inv->producto_id,
                    'variante_id' => $inv->variante_id,
                    'user_id' => $user->id,
                    'tipo' => $tipoMovimiento,
                    'direccion' => $tipo,
                    'cantidad' => $cantidad,
                    'stock_antes' => $antes,
                    'stock_despues' => $despues,
                    'referencia_tipo' => 'inventario_ajuste_rapido',
                    'referencia_id' => $ajuste->id,
                    'referencia_detalle_id' => $detalle->id,
                    'folio' => $ajuste->folio,
                    'motivo' => $data['motivo'],
                    'metadata' => ['serie_ids' => $serieIds],
                ]);
            }

            $ajuste->update([
                'tipo' => count(array_unique($tiposAplicados)) === 1 ? $tiposAplicados[0] : 'mixto',
            ]);
            return $ajuste;
        });

        return response()->json([
            'message' => 'Ajuste aplicado correctamente.',
            'id' => $ajuste->id,
            'folio' => $ajuste->folio,
        ], 201);
    }

    private function autorizar(Request $request): void
    {
        abort_unless($request->user()->tienePermiso('inventario.ajustes.crear'), 403, 'Sin permiso: inventario.ajustes.crear');
    }

    private function itemPayload(Inventario $inv): array
    {
        return [
            'inventario_id' => $inv->id,
            'producto_id' => $inv->producto_id,
            'variante_id' => $inv->variante_id,
            'nombre' => $inv->producto?->nombre,
            'codigo' => $inv->producto?->codigo,
            'sku' => $inv->variante?->sku,
            'variante' => $inv->variante?->nombreVariante(),
            'stock' => (float) $inv->stock,
            'tiene_series' => (bool) $inv->producto?->tiene_series,
            'imagen_url' => $inv->variante?->imagen_url ?? $inv->producto?->imagen_url,
        ];
    }

    private function siguienteFolio(int $empresaId): string
    {
        $numero = InventarioAjusteRapido::where('empresa_id', $empresaId)->lockForUpdate()->count() + 1;
        return 'AJR-' . str_pad((string) $numero, 6, '0', STR_PAD_LEFT);
    }
}
