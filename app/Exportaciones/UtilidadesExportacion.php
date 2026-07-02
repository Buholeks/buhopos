<?php

namespace App\Exportaciones;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UtilidadesExportacion extends ExportacionBase
{
    private ?Collection $cache = null;

    public function __construct(
        private readonly int   $empresaId,
        private readonly int   $sucursalId,
        private readonly array $filtros,
    ) {}

    public function titulo(): string   { return 'Reporte de Utilidades'; }
    public function empresaId(): ?int  { return $this->empresaId; }
    public function sucursalId(): ?int { return $this->sucursalId; }

    public function filtrosAplicados(): array
    {
        $r = [];
        if (!empty($this->filtros['fecha_desde'])) {
            $r['Desde'] = Carbon::parse($this->filtros['fecha_desde'])->format('d/m/Y');
        }
        if (!empty($this->filtros['fecha_hasta'])) {
            $r['Hasta'] = Carbon::parse($this->filtros['fecha_hasta'])->format('d/m/Y');
        }
        if (!empty($this->filtros['forma_pago'])) {
            $r['Forma de pago'] = ucfirst($this->filtros['forma_pago']);
        }
        if (!empty($this->filtros['categoria'])) {
            $r['Categoría'] = $this->filtros['categoria'];
        }
        if (!empty($this->filtros['producto'])) {
            $r['Producto'] = $this->filtros['producto'];
        }
        return $r;
    }

    public function cabeceras(): array
    {
        return ['Código', 'Producto', 'Categoría', 'Ventas', 'Unidades', 'Ingreso neto', 'Costo', 'Utilidad', 'Margen %'];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14, 'B' => 38, 'C' => 22,
            'D' => 10, 'E' => 12,
            'F' => 16, 'G' => 16, 'H' => 16, 'I' => 12,
        ];
    }

    public function datos(): Collection
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $ingresoNeto = "
            vd.subtotal - CASE
                WHEN COALESCE(v.subtotal, 0) > 0
                THEN COALESCE(v.descuento, 0) * (vd.subtotal / v.subtotal)
                ELSE 0
            END
        ";
        $costo = 'COALESCE(vd.precio_costo, 0) * vd.cantidad';

        $rows = DB::table('ventas as v')
            ->join('venta_detalles as vd', 'vd.venta_id', '=', 'v.id')
            ->join('productos as p', 'p.id', '=', 'vd.producto_id')
            ->leftJoin('categorias as c', 'c.id', '=', 'p.categoria_id')
            ->where('v.empresa_id', $this->empresaId)
            ->where('v.sucursal_id', $this->sucursalId)
            ->where('v.estado', 'confirmada')
            ->whereDate('v.fecha', '>=', $this->filtros['fecha_desde'])
            ->whereDate('v.fecha', '<=', $this->filtros['fecha_hasta'])
            ->when(!empty($this->filtros['user_id']), fn($q) => $q->where('v.user_id', $this->filtros['user_id']))
            ->when(!empty($this->filtros['forma_pago']), fn($q) => $q->whereExists(
                fn($sub) => $sub->select(DB::raw(1))
                    ->from('venta_pagos')
                    ->whereColumn('venta_pagos.venta_id', 'v.id')
                    ->where('venta_pagos.forma_pago', $this->filtros['forma_pago'])
            ))
            ->when(!empty($this->filtros['categoria_id']), fn($q) => $q->where('p.categoria_id', $this->filtros['categoria_id']))
            ->when(!empty($this->filtros['producto']), function ($q) {
                $texto = trim((string) $this->filtros['producto']);
                $q->where(fn($sub) => $sub
                    ->where('p.nombre', 'like', "%{$texto}%")
                    ->orWhere('p.codigo', 'like', "%{$texto}%"));
            })
            ->selectRaw("
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
            ->get();

        $this->cache = $rows->map(function ($row) {
            $ingresos = (float) $row->ingresos;
            $utilidad = (float) $row->utilidad;
            $margen   = $ingresos > 0 ? round(($utilidad / $ingresos) * 100, 2) : 0;
            return [
                $row->codigo ?? '—',
                $row->producto,
                $row->categoria,
                (int) $row->ventas,
                round((float) $row->unidades, 2),
                round($ingresos, 2),
                round((float) $row->costo, 2),
                round($utilidad, 2),
                $margen,
            ];
        });

        return $this->cache;
    }

    public function totales(): ?array
    {
        $filas = $this->datos();
        if ($filas->isEmpty()) return null;

        $ingresos = $filas->sum(fn($f) => $f[5]);
        $costo    = $filas->sum(fn($f) => $f[6]);
        $utilidad = $filas->sum(fn($f) => $f[7]);
        $margen   = $ingresos > 0 ? round(($utilidad / $ingresos) * 100, 2) : 0;

        return [
            '', 'TOTALES', '',
            $filas->sum(fn($f) => $f[3]),
            round($filas->sum(fn($f) => $f[4]), 2),
            round($ingresos, 2),
            round($costo, 2),
            round($utilidad, 2),
            $margen,
        ];
    }
}
