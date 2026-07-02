<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReporteArticuloController extends Controller
{
    public function buscarProductos(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('reportes.ver'), 403, 'Sin permiso: reportes.ver');

        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        $user = $request->user();
        $texto = trim($data['q']);
        $like = "%{$texto}%";

        $productos = DB::table('productos as p')
            ->leftJoin('categorias as c', 'c.id', '=', 'p.categoria_id')
            ->leftJoin('unidades_medida as u', 'u.id', '=', 'p.unidad_medida_id')
            ->where('p.empresa_id', $user->empresa_id)
            ->where(function ($q) use ($like) {
                $q->where('p.nombre', 'like', $like)
                    ->orWhere('p.codigo', 'like', $like)
                    ->orWhereExists(function ($sub) use ($like) {
                        $sub->from('producto_variantes as pv')
                            ->whereColumn('pv.producto_id', 'p.id')
                            ->where(function ($sq) use ($like) {
                                $sq->where('pv.sku', 'like', $like)
                                    ->orWhere('pv.codigo_barras', 'like', $like);
                            });
                    })
                    ->orWhereExists(function ($sub) use ($like) {
                        $sub->from('series as s')
                            ->whereColumn('s.producto_id', 'p.id')
                            ->where(function ($sq) use ($like) {
                                $sq->where('s.imei', 'like', $like)
                                    ->orWhere('s.imei2', 'like', $like)
                                    ->orWhere('s.serie', 'like', $like);
                            });
                    });
            })
            ->select(
                'p.id as producto_id',
                DB::raw('NULL as variante_id'),
                'p.nombre',
                'p.codigo',
                'p.tiene_variantes',
                'p.tiene_series',
                'c.nombre as categoria',
                'u.nombre as unidad'
            )
            ->orderBy('p.nombre')
            ->limit(10)
            ->get()
            ->map(fn($p) => $this->mapResultadoBusqueda($p));

        $variantes = DB::table('producto_variantes as v')
            ->join('productos as p', 'p.id', '=', 'v.producto_id')
            ->leftJoin('categorias as c', 'c.id', '=', 'p.categoria_id')
            ->leftJoin('unidades_medida as u', 'u.id', '=', 'p.unidad_medida_id')
            ->where('p.empresa_id', $user->empresa_id)
            ->where(function ($q) use ($like) {
                $q->where('p.nombre', 'like', $like)
                    ->orWhere('p.codigo', 'like', $like)
                    ->orWhere('v.sku', 'like', $like)
                    ->orWhere('v.codigo_barras', 'like', $like)
                    ->orWhereExists(function ($sub) use ($like) {
                        $sub->from('series as s')
                            ->whereColumn('s.variante_id', 'v.id')
                            ->where(function ($sq) use ($like) {
                                $sq->where('s.imei', 'like', $like)
                                    ->orWhere('s.imei2', 'like', $like)
                                    ->orWhere('s.serie', 'like', $like);
                            });
                    });
            })
            ->select(
                'p.id as producto_id',
                'v.id as variante_id',
                'p.nombre',
                'p.codigo',
                'p.tiene_variantes',
                'p.tiene_series',
                'v.sku',
                'v.codigo_barras',
                'c.nombre as categoria',
                'u.nombre as unidad'
            )
            ->orderBy('p.nombre')
            ->orderBy('v.sku')
            ->limit(15)
            ->get()
            ->map(fn($p) => $this->mapResultadoBusqueda($p));

        return response()->json(
            $variantes
                ->concat($productos)
                ->unique('selector_id')
                ->take(20)
                ->values()
        );
    }

    public function historial(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('reportes.ver'), 403, 'Sin permiso: reportes.ver');

        $data = $request->validate([
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'variante_id' => ['nullable', 'integer', 'exists:producto_variantes,id'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date'],
            'tipo' => ['nullable', 'string', 'max:40'],
        ]);

        $user = $request->user();
        $productoId = (int) $data['producto_id'];
        $varianteId = isset($data['variante_id']) ? (int) $data['variante_id'] : null;
        $timezone = 'America/Mexico_City';
        $fechaInicio = ! empty($data['fecha_inicio']) ? Carbon::parse($data['fecha_inicio'], $timezone)->startOfDay() : null;
        $fechaHasta = ! empty($data['fecha_hasta']) ? Carbon::parse($data['fecha_hasta'], $timezone)->endOfDay() : now($timezone)->endOfDay();
        $tipo = $data['tipo'] ?? '';

        $producto = $this->producto($user->empresa_id, $productoId, $varianteId);
        abort_if(! $producto, 404, 'Producto no encontrado.');

        $eventos = $this->eventosKardex(
            (int) $user->empresa_id,
            (int) $user->sucursal_id,
            $productoId,
            $varianteId,
            $fechaHasta
        );

        $saldo = 0.0;
        $usaSaldoDirecto = $varianteId !== null || ! ($producto['tiene_variantes'] ?? false);
        $eventos = $eventos->map(function (array $evento) use (&$saldo, $fechaInicio, $fechaHasta, $tipo, $timezone, $usaSaldoDirecto) {
            $entrada = (float) ($evento['entrada'] ?? 0);
            $salida = (float) ($evento['salida'] ?? 0);
            $antes = $usaSaldoDirecto ? (float) $evento['stock_antes'] : $saldo;
            $despues = $usaSaldoDirecto ? (float) $evento['stock_despues'] : ($saldo + $entrada - $salida);

            $saldo = $despues;
            $evento['antes'] = round($antes, 3);
            $evento['despues'] = round($despues, 3);
            $evento['saldo'] = round($saldo, 3);
            $fechaEvento = Carbon::parse($evento['fecha'], $timezone);
            $evento['visible'] = (! $fechaInicio || $fechaEvento->gte($fechaInicio))
                && $fechaEvento->lte($fechaHasta)
                && (! $tipo || $evento['tipo'] === $tipo);

            unset($evento['stock_antes'], $evento['stock_despues']);

            return $evento;
        });

        $visibles = $eventos->where('visible', true)->values();
        $totalEntradas = (float) $visibles->sum('entrada');
        $totalSalidas = (float) $visibles->sum('salida');

        return response()->json([
            'producto' => $producto,
            'filtros' => [
                'fecha_inicio' => $fechaInicio?->toDateString(),
                'fecha_hasta' => $fechaHasta->toDateString(),
            ],
            'resumen' => [
                'existencia_actual' => $this->existenciaActual($user->empresa_id, $user->sucursal_id, $productoId, $varianteId),
                'saldo_al_hasta' => round($saldo, 3),
                'total_entradas' => round($totalEntradas, 3),
                'total_salidas' => round($totalSalidas, 3),
                'diferencia' => round($totalEntradas - $totalSalidas, 3),
                'movimientos' => $visibles->count(),
            ],
            'tipos' => [
                'alta_producto',
                'alta_variante',
                'alta_serie',
                'saldo_inicial',
                'compra',
                'venta',
                'cancelacion_compra',
                'cancelacion_venta',
                'devolucion_cliente',
                'devolucion_proveedor',
                'anulacion_devolucion_proveedor',
                'ajuste_positivo',
                'ajuste_negativo',
                'traspaso_entrada',
                'traspaso_salida',
                'rechazo_traspaso',
                'cancelacion_traspaso',
            ],
            'movimientos' => $visibles->map(fn($e) => collect($e)->except('visible', 'orden')->all())->values(),
        ]);
    }

    private function eventosKardex(int $empresaId, int $sucursalId, int $productoId, ?int $varianteId, Carbon $hasta): Collection
    {
        $query = DB::table('kardex_movimientos as k')
            ->leftJoin('users as u', 'u.id', '=', 'k.user_id')
            ->where('k.empresa_id', $empresaId)
            ->where('k.sucursal_id', $sucursalId)
            ->where('k.producto_id', $productoId)
            ->where('k.fecha', '<=', $hasta->format('Y-m-d H:i:s'))
            ->when($varianteId, fn($q) => $q->where('k.variante_id', $varianteId))
            ->orderBy('k.fecha')
            ->orderBy('k.id')
            ->select(
                'k.id',
                'k.fecha',
                'k.variante_id',
                'k.tipo',
                'k.direccion',
                'k.entrada',
                'k.salida',
                'k.stock_antes',
                'k.stock_despues',
                'k.costo_unitario',
                'k.precio_unitario',
                'k.importe',
                'k.referencia_tipo',
                'k.referencia_id',
                'k.referencia_detalle_id',
                'k.folio',
                'k.motivo',
                'k.notas',
                'k.metadata',
                'u.name as usuario'
            );

        return $query->get()->map(function ($r) {
            return [
                'id' => "kardex:{$r->id}",
                'fecha' => $this->fechaSinConversion($r->fecha),
                'fecha_utc' => false,
                'orden' => $this->ordenKardex($r->tipo),
                'tipo' => $r->tipo,
                'tipo_label' => $this->tipoKardexLabel($r->tipo),
                'entrada' => round((float) $r->entrada, 3),
                'salida' => round((float) $r->salida, 3),
                'stock_antes' => (float) $r->stock_antes,
                'stock_despues' => (float) $r->stock_despues,
                'usuario' => $r->usuario,
                'referencia' => $r->folio ?: $r->referencia_tipo,
                'referencia_id' => $r->referencia_id ? (int) $r->referencia_id : null,
                'referencia_tipo' => $r->referencia_tipo,
                'referencia_detalle_id' => $r->referencia_detalle_id ? (int) $r->referencia_detalle_id : null,
                'precio' => $r->precio_unitario !== null ? round((float) $r->precio_unitario, 2) : null,
                'costo' => $r->costo_unitario !== null ? round((float) $r->costo_unitario, 2) : null,
                'importe' => $r->importe !== null ? round((float) $r->importe, 2) : null,
                'nota' => $this->notaKardex($r),
            ];
        });
    }

    private function mapResultadoBusqueda(object $row): array
    {
        $varianteId = $row->variante_id ? (int) $row->variante_id : null;
        $codigo = $row->sku ?? $row->codigo_barras ?? $row->codigo ?? '';

        return [
            'selector_id' => $varianteId ? "v:{$varianteId}" : "p:{$row->producto_id}",
            'producto_id' => (int) $row->producto_id,
            'variante_id' => $varianteId,
            'nombre' => $row->nombre,
            'codigo' => $row->codigo,
            'sku' => $row->sku ?? null,
            'codigo_barras' => $row->codigo_barras ?? null,
            'categoria' => $row->categoria,
            'unidad' => $row->unidad,
            'tiene_variantes' => (bool) $row->tiene_variantes,
            'tiene_series' => (bool) $row->tiene_series,
            'label' => trim($row->nombre . ($varianteId ? " / {$codigo}" : '')),
            'sub_label' => trim(collect([$row->codigo, $row->categoria, $row->unidad])->filter()->implode(' | ')),
        ];
    }

    private function tipoKardexLabel(string $tipo): string
    {
        return [
            'alta_producto' => 'Alta de articulo',
            'alta_variante' => 'Alta de variante',
            'alta_serie' => 'Alta de serie',
            'saldo_inicial' => 'Saldo inicial',
            'compra' => 'Compra',
            'venta' => 'Venta',
            'cancelacion_compra' => 'Cancelacion compra',
            'cancelacion_venta' => 'Cancelacion venta',
            'devolucion_cliente' => 'Devolucion cliente',
            'devolucion_proveedor' => 'Devolucion proveedor',
            'anulacion_devolucion_proveedor' => 'Anulacion devolucion proveedor',
            'ajuste_positivo' => 'Ajuste positivo',
            'ajuste_negativo' => 'Ajuste negativo',
            'traspaso_entrada' => 'Traspaso entrada',
            'traspaso_salida' => 'Traspaso salida',
            'rechazo_traspaso' => 'Rechazo de traspaso',
            'cancelacion_traspaso' => 'Cancelacion de traspaso',
        ][$tipo] ?? str_replace('_', ' ', ucfirst($tipo));
    }

    private function ordenKardex(string $tipo): int
    {
        return match ($tipo) {
            'alta_producto', 'alta_variante', 'alta_serie' => 0,
            'saldo_inicial' => 1,
            default => 2,
        };
    }

    private function notaKardex(object $row): ?string
    {
        if ($row->motivo) {
            return $row->motivo;
        }

        if ($row->notas) {
            return $row->notas;
        }

        $metadata = json_decode((string) $row->metadata, true);
        if (! is_array($metadata)) {
            return null;
        }

        if (isset($metadata['origen_sucursal_id'], $metadata['destino_sucursal_id'])) {
            return "Sucursal {$metadata['origen_sucursal_id']} -> {$metadata['destino_sucursal_id']}";
        }

        if (! empty($metadata['venta_folio'])) {
            return "Venta {$metadata['venta_folio']}";
        }

        if (! empty($metadata['compra_folio'])) {
            return "Compra {$metadata['compra_folio']}";
        }

        return null;
    }

    private function producto(int $empresaId, int $productoId, ?int $varianteId): ?array
    {
        $query = DB::table('productos as p')
            ->leftJoin('categorias as c', 'c.id', '=', 'p.categoria_id')
            ->leftJoin('marcas as m', 'm.id', '=', 'p.marca_id')
            ->leftJoin('modelos as mo', 'mo.id', '=', 'p.modelo_id')
            ->leftJoin('unidades_medida as u', 'u.id', '=', 'p.unidad_medida_id')
            ->where('p.empresa_id', $empresaId)
            ->where('p.id', $productoId);

        if ($varianteId) {
            $query->join('producto_variantes as v', function ($join) use ($varianteId) {
                $join->on('v.producto_id', '=', 'p.id')
                    ->where('v.id', $varianteId);
            });
        }

        $row = $query->select(
                'p.id as producto_id',
                'p.nombre',
                'p.codigo',
                'p.descripcion',
                'p.tiene_variantes',
                'p.tiene_series',
                'p.created_at',
                DB::raw($varianteId ? 'v.id as variante_id' : 'NULL as variante_id'),
                DB::raw($varianteId ? 'v.sku' : 'NULL as sku'),
                DB::raw($varianteId ? 'v.codigo_barras' : 'NULL as codigo_barras'),
                'c.nombre as categoria',
                'm.nombre as marca',
                'mo.nombre as modelo',
                'u.nombre as unidad'
            )
            ->first();

        if (! $row || ($varianteId && ! $row->variante_id)) {
            return null;
        }

        return [
            'producto_id' => (int) $row->producto_id,
            'variante_id' => $row->variante_id ? (int) $row->variante_id : null,
            'nombre' => $row->nombre,
            'codigo' => $row->codigo,
            'descripcion' => $row->descripcion,
            'sku' => $row->sku,
            'codigo_barras' => $row->codigo_barras,
            'categoria' => $row->categoria,
            'marca' => $row->marca,
            'modelo' => $row->modelo,
            'unidad' => $row->unidad,
            'tiene_variantes' => (bool) $row->tiene_variantes,
            'tiene_series' => (bool) $row->tiene_series,
            'created_at' => $row->created_at,
        ];
    }

    private function existenciaActual(int $empresaId, int $sucursalId, int $productoId, ?int $varianteId): float
    {
        return (float) DB::table('inventario')
            ->where('empresa_id', $empresaId)
            ->where('sucursal_id', $sucursalId)
            ->where('producto_id', $productoId)
            ->when($varianteId, fn($q) => $q->where('variante_id', $varianteId))
            ->when(! $varianteId, fn($q) => $q)
            ->sum('stock');
    }

    private function fechaSinConversion(mixed $fecha): string
    {
        return substr(str_replace('T', ' ', (string) $fecha), 0, 19);
    }
}
