<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\Serie;
use App\Models\CompraDetalle;
use App\Models\Inventario;
use App\Models\ProductoVariante;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CompraController extends Controller
{
    // ── GET /api/compras ────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $empresaId = Auth::user()->empresa_id;

        $compras = Compra::where('empresa_id', $empresaId)
            ->with(['proveedor:id,nombre', 'user:id,name'])
            ->when(
                $request->buscar,
                fn($q, $b) =>
                $q->where(
                    fn($q) => $q
                        ->where('folio', 'like', "%{$b}%")
                        ->orWhereHas('proveedor', fn($q) => $q->where('nombre', 'like', "%{$b}%"))
                )
            )
            ->when($request->estado, fn($q, $e) => $q->where('estado', $e))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate($request->por_pagina ?? 20);

        return response()->json($compras);
    }

    // ── GET /api/compras/{id} ───────────────────────────────────────────────
    public function show(int $id): JsonResponse
    {
        $empresaId = Auth::user()->empresa_id;

        $compra = Compra::where('empresa_id', $empresaId)
            ->with([
                'proveedor:id,nombre',
                'user:id,name',
                'detalles.producto:id,nombre,codigo',
                'detalles.variante:id,sku',
            ])
            ->findOrFail($id);

        return response()->json($compra);
    }

    // ── POST /api/compras ───────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $user       = Auth::user();
        $empresaId  = (int) $user->empresa_id;
        $sucursalId = (int) $user->sucursal_id;

        $datos = $request->validate([
            'proveedor_id'      => ['required', 'exists:proveedores,id'],
            'folio'             => ['nullable', 'string', 'max:100'],
            'fecha'             => ['required', 'date'],
            'forma_pago'        => ['required', 'in:efectivo,credito,transferencia,tarjeta_debito,tarjeta_credito'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha'],
            'notas'             => ['nullable', 'string'],

            'detalles'                 => ['required', 'array', 'min:1'],
            'detalles.*.producto_id'   => ['required', 'exists:productos,id'],
            'detalles.*.variante_id'   => ['nullable', 'exists:producto_variantes,id'],
            'detalles.*.cantidad'      => ['required', 'numeric', 'min:0.001'],
            'detalles.*.precio_compra' => ['required', 'numeric', 'min:0'],
            'detalles.*.precio_venta'  => ['nullable', 'numeric', 'min:0'],
            'detalles.*.imeis'         => ['nullable', 'array'],
            'detalles.*.imeis.*'       => ['nullable', 'string', 'max:20'],
        ]);

        DB::beginTransaction();
        try {
            $compra = Compra::create([
                'empresa_id'        => $empresaId,
                'sucursal_id'       => $sucursalId,
                'user_id'           => $user->id,
                'proveedor_id'      => $datos['proveedor_id']      ?? null,
                'folio'             => $datos['folio']             ?? null,
                'fecha'             => $datos['fecha'],
                'forma_pago'        => $datos['forma_pago'],
                'fecha_vencimiento' => $datos['fecha_vencimiento'] ?? null,
                'notas'             => $datos['notas']             ?? null,
                'estado'            => 'confirmada',
                'subtotal'          => 0,
                'total'             => 0,
            ]);

            foreach ($datos['detalles'] as $det) {
                $productoId   = (int) $det['producto_id'];
                $varianteId   = isset($det['variante_id']) ? (int) $det['variante_id'] : null;
                $cantidad     = (float) $det['cantidad'];
                $precioCompra = (float) $det['precio_compra'];
                $precioVenta  = isset($det['precio_venta']) ? (float) $det['precio_venta'] : null;
                $subtotal     = $cantidad * $precioCompra;

                CompraDetalle::create([
                    'compra_id'     => $compra->id,
                    'producto_id'   => $productoId,
                    'variante_id'   => $varianteId,
                    'cantidad'      => $cantidad,
                    'precio_compra' => $precioCompra,
                    'precio_venta'  => $precioVenta,
                    'subtotal'      => $subtotal,
                ]);

                // ── Incrementar inventario ─────────────────────────────────
                $inv = Inventario::firstOrCreate(
                    [
                        'empresa_id'  => $empresaId,
                        'sucursal_id' => $sucursalId,
                        'producto_id' => $productoId,
                        'variante_id' => $varianteId,
                    ],
                    ['stock' => 0]
                );

                $inv = Inventario::where([
                    'empresa_id'  => $empresaId,
                    'sucursal_id' => $sucursalId,
                    'producto_id' => $productoId,
                    'variante_id' => $varianteId,
                ])
                    ->lockForUpdate()
                    ->first();

                $inv->stock = (float) $inv->stock + $cantidad;
                $inv->save();

                // ── Actualizar precios ─────────────────────────────────────
                if ($varianteId) {
                    $updateVariante = ['precio_costo' => $precioCompra];
                    if ($precioVenta) $updateVariante['precio_venta'] = $precioVenta;
                    ProductoVariante::where('id', $varianteId)->update($updateVariante);
                } else {
                    $updateProducto = ['precio_costo' => $precioCompra];
                    if ($precioVenta) $updateProducto['precio_venta'] = $precioVenta;
                    Producto::where('id', $productoId)->update($updateProducto);
                }

                // ── Registrar series / IMEIs ───────────────────────────────
                $imeis = $det['imeis'] ?? [];
                foreach ($imeis as $imei) {
                    if (! $imei) continue;
                    Serie::create([
                        'empresa_id'   => $empresaId,
                        'sucursal_id'  => $sucursalId,
                        'producto_id'  => $productoId,
                        'variante_id'  => $varianteId,
                        'compra_id'    => $compra->id,
                        'proveedor_id' => $datos['proveedor_id'] ?? null,
                        'imei'         => $imei,
                        'precio_costo' => $precioCompra,
                        'precio_venta' => $precioVenta,
                        'estado'       => 'disponible',
                    ]);
                }
            }

            $compra->recalcularTotales();

            // ── Update final: folio + pagado + saldo en un solo query ─────
            $liquidaAlMomento = in_array($datos['forma_pago'], ['efectivo', 'transferencia', 'tarjeta_debito']);

            $updateFinal = [
                'pagado' => $liquidaAlMomento ? $compra->total : 0,
                'saldo'  => $liquidaAlMomento ? 0 : $compra->total,
            ];

            if (empty($datos['folio'])) {
                $fechaHoy = now()->format('dmy');
                $countHoy = DB::table('compras')
                    ->where('empresa_id',  $empresaId)
                    ->where('sucursal_id', $sucursalId)
                    ->whereDate('fecha', now()->toDateString())
                    ->count();

                $updateFinal['folio'] = $fechaHoy . str_pad($countHoy, 2, '0', STR_PAD_LEFT);
            }

            DB::table('compras')
                ->where('id', $compra->id)
                ->update($updateFinal);

            DB::commit();

            return response()->json(
                $compra->load(['proveedor', 'detalles.producto', 'detalles.variante', 'user:id,name']),
                201
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // ── POST /api/compras/{id}/cancelar ────────────────────────────────────
    public function cancelar(int $id): JsonResponse
    {
        $user      = Auth::user();
        $empresaId = (int) $user->empresa_id;

        $compra = Compra::where('empresa_id', $empresaId)
            ->with('detalles')
            ->findOrFail($id);

        if ($compra->estado === 'cancelada') {
            return response()->json(['message' => 'La compra ya está cancelada.'], 422);
        }
        if ($compra->estado !== 'confirmada') {
            return response()->json(['message' => 'Solo se puede cancelar una compra confirmada.'], 422);
        }

        DB::beginTransaction();
        try {
            foreach ($compra->detalles as $det) {
                $productoId = (int) $det->producto_id;
                $varianteId = $det->variante_id ? (int) $det->variante_id : null;
                $cantidad   = (float) $det->cantidad;

                $inv = Inventario::where([
                    'empresa_id'  => $empresaId,
                    'sucursal_id' => (int) $compra->sucursal_id,
                    'producto_id' => $productoId,
                    'variante_id' => $varianteId,
                ])
                    ->lockForUpdate()
                    ->first();

                if (! $inv) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "Inventario no encontrado para revertir (producto {$productoId}).",
                    ], 422);
                }

                $nuevo = (float) $inv->stock - $cantidad;
                if ($nuevo < 0) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "No se puede cancelar: el stock quedaría negativo (producto {$productoId}).",
                    ], 422);
                }

                $inv->stock = $nuevo;
                $inv->save();
            }

            $compra->update(['estado' => 'cancelada']);
            DB::commit();

            return response()->json(['mensaje' => 'Compra cancelada y stock revertido.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // ── DELETE /api/compras/{id} ────────────────────────────────────────────
    public function destroy(int $id): JsonResponse
    {
        $empresaId = (int) Auth::user()->empresa_id;
        $compra    = Compra::where('empresa_id', $empresaId)->findOrFail($id);

        if ($compra->estado === 'confirmada') {
            return response()->json([
                'message' => 'No se puede eliminar una compra confirmada. Cancélala primero.',
            ], 422);
        }

        $compra->delete();
        return response()->json(['mensaje' => 'Compra eliminada.']);
    }

    // ── GET /api/compras/buscar-variantes ───────────────────────────────────
    //
    // Reglas:
    //   1. Productos SIN variantes → aparece el producto como ítem directo
    //      (variante_id = null, id = null para que el buscador lo identifique como producto)
    //   2. Productos CON variantes → aparecen solo las variantes, nunca el producto padre
    // ─────────────────────────────────────────────────────────────────────────
    public function buscarVariantes(Request $request): JsonResponse
    {
        $empresaId = (int) Auth::user()->empresa_id;
        $q         = trim($request->q ?? '');

        if (strlen($q) < 1) return response()->json([]);

        $resultados = collect();

        // ── 1. Productos SIN variantes ────────────────────────────────────
        $productos = Producto::where('empresa_id', $empresaId)
            ->where('activo', true)
            ->where('tiene_variantes', false)        // solo los que no tienen variantes
            ->where(
                fn($pq) => $pq
                    ->where('nombre', 'like', "%{$q}%")
                    ->orWhere('codigo', 'like', "%{$q}%")
            )
            ->limit(10)
            ->get()
            ->map(fn($p) => [
                'id'              => null,           // sin variante_id
                'producto_id'     => $p->id,
                'nombre'          => $p->nombre,
                'codigo'          => $p->codigo,
                'sku'             => null,
                'codigo_barras'   => null,
                'nombre_variante' => null,
                'precio_compra'   => (float) ($p->precio_costo ?? 0),
                'precio_venta'    => (float) ($p->precio_venta ?? 0),
                'imagen_url'      => $p->imagen_url,
                'tiene_variantes' => false,
                'tiene_series'    => (bool) $p->tiene_series, // ← nuevo
            ]);

        $resultados = $resultados->merge($productos);

        // ── 2. Variantes de productos CON variantes ───────────────────────
        $variantes = ProductoVariante::whereHas(
            'producto',
            fn($pq) =>
            $pq->where('empresa_id', $empresaId)
                ->where('activo', true)
                ->where('tiene_variantes', true)  // solo de productos con variantes
        )
            ->with([
                'producto:id,nombre,codigo,precio_costo,precio_venta,imagen,tiene_series',
                'atributos.tipoAtributo:id,nombre',
                'atributos.atributo:id,valor',
            ])
            ->where(
                fn($q2) => $q2
                    ->where('sku', 'like', "%{$q}%")
                    ->orWhere('codigo_barras', 'like', "%{$q}%")
                    ->orWhereHas(
                        'producto',
                        fn($pq) => $pq
                            ->where('nombre', 'like', "%{$q}%")
                            ->orWhere('codigo', 'like', "%{$q}%")
                    )
            )
            ->limit(15)
            ->get()
            ->map(fn($v) => [
                'id'              => $v->id,          // este es el variante_id
                'producto_id'     => $v->producto_id,
                'nombre'          => $v->producto->nombre,
                'codigo'          => $v->producto->codigo,
                'sku'             => $v->sku,
                'codigo_barras'   => $v->codigo_barras,
                'nombre_variante' => $v->nombreVariante(),
                'precio_compra'   => (float) ($v->precio_costo ?? $v->producto->precio_costo ?? 0),
                'precio_venta'    => (float) ($v->precio_venta ?? $v->producto->precio_venta ?? 0),
                'imagen_url'      => $v->imagen_url,
                'tiene_variantes' => true,
                'tiene_series'    => (bool) $v->producto->tiene_series, // ← nuevo
            ]);

        $resultados = $resultados->merge($variantes)->values();

        return response()->json($resultados);
    }
}
