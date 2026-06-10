<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReporteUtilidadesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('reportes.utilidades'), 403, 'Sin permiso: reportes.utilidades');
        $data = $request->validate([
            'fecha_desde'  => ['required', 'date'],
            'fecha_hasta'  => ['required', 'date', 'after_or_equal:fecha_desde'],
            'user_id'      => ['nullable', 'integer'],
            'forma_pago'   => ['nullable', 'in:efectivo,tarjeta,transferencia,credito'],
            'categoria_id' => ['nullable', 'integer'],
            'producto'     => ['nullable', 'string', 'max:120'],
            'por_pagina'   => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $user = Auth::user();
        $base = $this->base($request, $user->empresa_id, $user->sucursal_id);
        $ingresoNeto = $this->ingresoNetoSql();
        $costo = 'COALESCE(vd.precio_costo, 0) * vd.cantidad';

        $resumen = (clone $base)
            ->selectRaw("
                COUNT(DISTINCT v.id) AS ventas,
                COALESCE(SUM(vd.cantidad), 0) AS unidades,
                COALESCE(SUM(CASE WHEN vd.precio_costo IS NULL THEN 1 ELSE 0 END), 0) AS partidas_sin_costo,
                COALESCE(SUM({$ingresoNeto}), 0) AS ingresos,
                COALESCE(SUM({$costo}), 0) AS costo,
                COALESCE(SUM({$ingresoNeto} - {$costo}), 0) AS utilidad
            ")
            ->first();

        $ingresos = (float) $resumen->ingresos;
        $utilidad = (float) $resumen->utilidad;

        $tendencia = (clone $base)
            ->selectRaw("
                DATE(v.fecha) AS fecha,
                COUNT(DISTINCT v.id) AS ventas,
                COALESCE(SUM({$ingresoNeto}), 0) AS ingresos,
                COALESCE(SUM({$costo}), 0) AS costo,
                COALESCE(SUM({$ingresoNeto} - {$costo}), 0) AS utilidad
            ")
            ->groupByRaw('DATE(v.fecha)')
            ->orderBy('fecha')
            ->get()
            ->map(fn($row) => $this->conMargen($row));

        $productos = (clone $base)
            ->leftJoin('categorias as c', 'c.id', '=', 'p.categoria_id')
            ->selectRaw("
                p.id AS producto_id,
                p.codigo,
                p.nombre AS producto,
                COALESCE(c.nombre, 'Sin categoría') AS categoria,
                COUNT(DISTINCT v.id) AS ventas,
                COALESCE(SUM(vd.cantidad), 0) AS unidades,
                COALESCE(SUM({$ingresoNeto}), 0) AS ingresos,
                COALESCE(SUM({$costo}), 0) AS costo,
                COALESCE(SUM({$ingresoNeto} - {$costo}), 0) AS utilidad
            ")
            ->groupBy('p.id', 'p.codigo', 'p.nombre', 'c.nombre')
            ->orderByDesc('utilidad')
            ->paginate((int) ($data['por_pagina'] ?? 20));

        $productos->getCollection()->transform(fn($row) => $this->conMargen($row));

        $cajeros = DB::table('users as u')
            ->join('ventas as v', 'v.user_id', '=', 'u.id')
            ->where('v.empresa_id', $user->empresa_id)
            ->where('v.sucursal_id', $user->sucursal_id)
            ->select('u.id', 'u.name')
            ->distinct()
            ->orderBy('u.name')
            ->get();

        $categorias = DB::table('categorias')
            ->where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->select('id', 'nombre')
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'resumen' => [
                'ventas'         => (int) $resumen->ventas,
                'unidades'       => (float) $resumen->unidades,
                'ingresos'       => $ingresos,
                'costo'          => (float) $resumen->costo,
                'utilidad'       => $utilidad,
                'margen'         => $ingresos > 0 ? round(($utilidad / $ingresos) * 100, 2) : 0,
                'venta_promedio' => (int) $resumen->ventas > 0
                    ? round($ingresos / (int) $resumen->ventas, 2)
                    : 0,
                'partidas_sin_costo' => (int) $resumen->partidas_sin_costo,
            ],
            'tendencia'  => $tendencia,
            'productos'  => $productos,
            'cajeros'    => $cajeros,
            'categorias' => $categorias,
        ]);
    }

    private function base(Request $request, int $empresaId, int $sucursalId)
    {
        return DB::table('ventas as v')
            ->join('venta_detalles as vd', 'vd.venta_id', '=', 'v.id')
            ->join('productos as p', 'p.id', '=', 'vd.producto_id')
            ->where('v.empresa_id', $empresaId)
            ->where('v.sucursal_id', $sucursalId)
            ->where('v.estado', 'confirmada')
            ->whereDate('v.fecha', '>=', $request->fecha_desde)
            ->whereDate('v.fecha', '<=', $request->fecha_hasta)
            ->when($request->filled('user_id'), fn($q) => $q->where('v.user_id', $request->user_id))
            ->when($request->filled('forma_pago'), fn($q) => $q->where('v.forma_pago', $request->forma_pago))
            ->when($request->filled('categoria_id'), fn($q) => $q->where('p.categoria_id', $request->categoria_id))
            ->when($request->filled('producto'), function ($q) use ($request) {
                $texto = trim((string) $request->producto);
                $q->where(fn($sub) => $sub
                    ->where('p.nombre', 'like', "%{$texto}%")
                    ->orWhere('p.codigo', 'like', "%{$texto}%"));
            });
    }

    private function ingresoNetoSql(): string
    {
        return "
            vd.subtotal - CASE
                WHEN COALESCE(v.subtotal, 0) > 0
                THEN COALESCE(v.descuento, 0) * (vd.subtotal / v.subtotal)
                ELSE 0
            END
        ";
    }

    private function conMargen(object $row): array
    {
        $data = (array) $row;
        $ingresos = (float) $data['ingresos'];
        $data['ventas'] = (int) $data['ventas'];
        $data['unidades'] = (float) ($data['unidades'] ?? 0);
        $data['ingresos'] = $ingresos;
        $data['costo'] = (float) $data['costo'];
        $data['utilidad'] = (float) $data['utilidad'];
        $data['margen'] = $ingresos > 0
            ? round(((float) $data['utilidad'] / $ingresos) * 100, 2)
            : 0;

        return $data;
    }
}
