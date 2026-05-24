<?php

namespace App\Http\Controllers;

use App\Models\Inventario;
use App\Models\ProductoVariante;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExhibicionController extends Controller
{
    /**
     * GET /api/exhibicion
     */
    public function index(Request $request)
    {
        $user       = Auth::user();
        $empresaId  = $user->empresa_id;
        $sucursalId = $user->sucursal_id;

        $query = Inventario::with([
            'producto:id,nombre,codigo,imagen,tiene_variantes,categoria_id',
            'producto.categoria:id,nombre',
            'variante:id,sku,codigo_barras,imagen',
            'varianteExhibida:id,sku',
        ])
            ->deSucursal($empresaId, $sucursalId);

        // Filtro de estado
        match ($request->input('filtro')) {
            'exhibidos'     => $query->exhibidos(),
            'sinExhibicion' => $query->sinExhibicion(),
            default         => null,
        };

        // Búsqueda
        if ($busqueda = $request->input('busqueda')) {
            $query->where(function ($q) use ($busqueda) {
                $q->whereHas(
                    'producto',
                    fn($p) =>
                    $p->where('nombre', 'like', "%{$busqueda}%")
                        ->orWhere('codigo', 'like', "%{$busqueda}%")
                )
                    ->orWhereHas(
                        'variante',
                        fn($v) =>
                        $v->where('sku', 'like', "%{$busqueda}%")
                            ->orWhere('codigo_barras', 'like', "%{$busqueda}%")
                    );
            });
        }

        // Solo mostrar ítems con stock O que estén exhibidos (el exhibido siempre aparece)
        $query->where(function ($q) {
            $q->where('stock', '>', 0)->orWhere('exhibido', true);
        });

        $items = $query->orderBy('exhibido', 'desc')
            ->orderBy('stock', 'desc')
            ->paginate($request->input('per_page', 50));

        // Contadores (solo los relevantes para control de exhibición)
        $base  = Inventario::deSucursal($empresaId, $sucursalId);
        $stats = [
            'exhibidos'     => (clone $base)->exhibidos()->count(),
            'sinExhibicion' => (clone $base)->sinExhibicion()->count(),
        ];

        // Catálogo de estados para el frontend
        $estadosExhibicion = Inventario::ESTADOS_EXHIBICION;

        return response()->json([
            'items'            => $items,
            'stats'            => $stats,
            'estadosExhibicion' => $estadosExhibicion,
        ]);
    }

    /**
     * GET /api/exhibicion/{inventario}/variantes
     * Devuelve las variantes con stock disponible para elegir cuál exhibir.
     * Solo aplica a productos con variantes.
     */
    public function variantes(Inventario $inventario)
    {
        $user = Auth::user();

        if (
            $inventario->empresa_id  !== $user->empresa_id ||
            $inventario->sucursal_id !== $user->sucursal_id
        ) {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        if (! $inventario->producto->tiene_variantes) {
            return response()->json(['variantes' => []]);
        }

        // Obtener inventario de cada variante de este producto en esta sucursal
        $variantes = Inventario::with('variante:id,sku,codigo_barras,imagen')
            ->deSucursal($inventario->empresa_id, $inventario->sucursal_id)
            ->where('producto_id', $inventario->producto_id)
            ->whereNotNull('variante_id')
            ->where('stock', '>', 0)
            ->get()
            ->map(fn($inv) => [
                'inventario_id' => $inv->id,
                'variante_id'   => $inv->variante_id,
                'sku'           => $inv->variante?->sku ?? "#{$inv->variante_id}",
                'stock'         => (float) $inv->stock,
            ]);

        return response()->json(['variantes' => $variantes]);
    }

    /**
     * PATCH /api/exhibicion/{inventario}/exhibir
     *
     * Body esperado:
     * {
     *   "estado_exhibicion":   "perfecto" | "caja_abierta" | "con_detalles",
     *   "variante_exhibida_id": 12  (opcional, solo si tiene variantes)
     * }
     */
    public function exhibir(Request $request, Inventario $inventario)
    {
        $user = Auth::user();

        if (
            $inventario->empresa_id  !== $user->empresa_id ||
            $inventario->sucursal_id !== $user->sucursal_id
        ) {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        $request->validate([
            'estado_exhibicion'    => 'required|in:perfecto,caja_abierta,con_detalles',
            'variante_exhibida_id' => 'nullable|integer|exists:producto_variantes,id',
        ]);

        try {
            $inventario->marcarExhibido(
                $request->input('estado_exhibicion'),
                $request->input('variante_exhibida_id')
            );
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Producto marcado como exhibido.',
            'item'    => $inventario->fresh(['producto', 'variante', 'varianteExhibida']),
        ]);
    }

    /**
     * PATCH /api/exhibicion/{inventario}/quitar
     */
    public function quitar(Inventario $inventario)
    {
        $user = Auth::user();

        if (
            $inventario->empresa_id  !== $user->empresa_id ||
            $inventario->sucursal_id !== $user->sucursal_id
        ) {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        $inventario->quitarExhibicion();

        return response()->json([
            'message' => 'Producto quitado de exhibición.',
            'item'    => $inventario->fresh(['producto', 'variante', 'varianteExhibida']),
        ]);
    }
}
