@extends('pdf.layout')

@section('contenido')
<style>
    .seccion-titulo {
        font-size: 10px;
        font-weight: bold;
        color: #1E3A5F;
        border-bottom: 2px solid #1E3A5F;
        padding-bottom: 3px;
        margin-bottom: 8px;
        margin-top: 14px;
    }
    .resumen-grid {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 12px;
    }
    .resumen-grid td {
        width: 14.28%;
        padding: 6px 10px;
        border: 1px solid #dde3ec;
        vertical-align: top;
        text-align: center;
    }
    .resumen-grid .label {
        font-size: 8px;
        color: #64748b;
        display: block;
    }
    .resumen-grid .valor {
        font-size: 11px;
        font-weight: bold;
        color: #1a1a1a;
        display: block;
        margin-top: 2px;
    }
    .valor-verde { color: #16a34a; }
    .valor-rojo  { color: #dc2626; }
    .valor-ambar { color: #d97706; }
    table.datos {
        width: 100%;
        border-collapse: collapse;
        font-size: 8.5px;
        margin-bottom: 10px;
    }
    table.datos thead tr th {
        background-color: #1E3A5F;
        color: white;
        padding: 5px 6px;
        text-align: left;
        border: 1px solid #163050;
        font-weight: bold;
    }
    table.datos tbody tr td {
        padding: 3px 6px;
        border: 1px solid #dde3ec;
    }
    table.datos tbody tr:nth-child(even) td { background-color: #f0f4f8; }
    table.datos tfoot tr td {
        background-color: #d9e2ec;
        color: #1E3A5F;
        font-weight: bold;
        padding: 4px 6px;
        border: 1px solid #b8c6d6;
    }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .verde { color: #16a34a; font-weight: bold; }
    .rojo  { color: #dc2626; font-weight: bold; }
    .ambar { color: #d97706; font-weight: bold; }
</style>

@php
    $fmt = fn($v) => '$' . number_format((float)$v, 2, '.', ',');
    $num = fn($v) => number_format((float)$v, 2, '.', ',');

    $totalIngresos  = collect($productos)->sum('ingresos');
    $totalCosto     = collect($productos)->sum('costo');
    $totalUtilidad  = collect($productos)->sum('utilidad');
    $margenGlobal   = $totalIngresos > 0 ? round(($totalUtilidad / $totalIngresos) * 100, 2) : 0;

    $colorMargen = fn($m) => $m < 0 ? 'rojo' : ($m < 15 ? 'ambar' : 'verde');
    $colorUtilidad = fn($u) => (float)$u < 0 ? 'rojo' : 'verde';
@endphp

{{-- ── Resumen global ─────────────────────────────────────────────────── --}}
<div class="seccion-titulo">Resumen del período</div>
<table class="resumen-grid">
    <tr>
        <td>
            <span class="label">Ventas</span>
            <span class="valor">{{ number_format($resumen['ventas']) }}</span>
        </td>
        <td>
            <span class="label">Unidades</span>
            <span class="valor">{{ $num($resumen['unidades']) }}</span>
        </td>
        <td>
            <span class="label">Ingreso neto</span>
            <span class="valor">{{ $fmt($resumen['ingresos']) }}</span>
        </td>
        <td>
            <span class="label">Costo vendido</span>
            <span class="valor">{{ $fmt($resumen['costo']) }}</span>
        </td>
        <td>
            <span class="label">Utilidad bruta</span>
            <span class="valor {{ $colorUtilidad($resumen['utilidad']) }}">{{ $fmt($resumen['utilidad']) }}</span>
        </td>
        <td>
            <span class="label">Margen</span>
            <span class="valor {{ $colorMargen($resumen['margen']) }}">{{ $num($resumen['margen']) }}%</span>
        </td>
        <td>
            <span class="label">Venta promedio</span>
            <span class="valor">{{ $fmt($resumen['venta_promedio']) }}</span>
        </td>
    </tr>
</table>

@if ($resumen['partidas_sin_costo'] > 0)
<div style="background:#fffbeb;border:1px solid #fcd34d;padding:5px 8px;font-size:8px;color:#92400e;margin-bottom:10px;">
    ⚠ {{ $resumen['partidas_sin_costo'] }} partidas sin costo histórico; la utilidad puede estar sobreestimada.
</div>
@endif

{{-- ── Tendencia por día ───────────────────────────────────────────────── --}}
@if (count($tendencia))
<div class="seccion-titulo">Utilidad por día ({{ count($tendencia) }} días)</div>
<table class="datos">
    <thead>
        <tr>
            <th>Fecha</th>
            <th class="text-right">Ventas</th>
            <th class="text-right">Ingreso neto</th>
            <th class="text-right">Costo</th>
            <th class="text-right">Utilidad</th>
            <th class="text-right">Margen %</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($tendencia as $dia)
        <tr>
            <td>{{ \Carbon\Carbon::parse($dia['fecha'])->format('d/m/Y') }}</td>
            <td class="text-right">{{ $dia['ventas'] }}</td>
            <td class="text-right">{{ $fmt($dia['ingresos']) }}</td>
            <td class="text-right">{{ $fmt($dia['costo']) }}</td>
            <td class="text-right {{ $colorUtilidad($dia['utilidad']) }}">{{ $fmt($dia['utilidad']) }}</td>
            <td class="text-right {{ $colorMargen($dia['margen']) }}">{{ $num($dia['margen']) }}%</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td>TOTAL</td>
            <td class="text-right">{{ collect($tendencia)->sum('ventas') }}</td>
            <td class="text-right">{{ $fmt(collect($tendencia)->sum('ingresos')) }}</td>
            <td class="text-right">{{ $fmt(collect($tendencia)->sum('costo')) }}</td>
            <td class="text-right">{{ $fmt(collect($tendencia)->sum('utilidad')) }}</td>
            <td class="text-right">{{ $num($margenGlobal) }}%</td>
        </tr>
    </tfoot>
</table>
@endif

{{-- ── Utilidad por producto ───────────────────────────────────────────── --}}
@if (count($productos))
<div class="seccion-titulo">Utilidad por producto ({{ count($productos) }})</div>
<table class="datos">
    <thead>
        <tr>
            <th>Código</th>
            <th>Producto</th>
            <th>Categoría</th>
            <th class="text-right">Ventas</th>
            <th class="text-right">Unidades</th>
            <th class="text-right">Ingreso neto</th>
            <th class="text-right">Costo</th>
            <th class="text-right">Utilidad</th>
            <th class="text-right">Margen %</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($productos as $item)
        <tr>
            <td>{{ $item['codigo'] ?? '—' }}</td>
            <td>{{ $item['producto'] }}</td>
            <td>{{ $item['categoria'] }}</td>
            <td class="text-right">{{ $item['ventas'] }}</td>
            <td class="text-right">{{ $num($item['unidades']) }}</td>
            <td class="text-right">{{ $fmt($item['ingresos']) }}</td>
            <td class="text-right">{{ $fmt($item['costo']) }}</td>
            <td class="text-right {{ $colorUtilidad($item['utilidad']) }}">{{ $fmt($item['utilidad']) }}</td>
            <td class="text-right {{ $colorMargen($item['margen']) }}">{{ $num($item['margen']) }}%</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5">TOTALES</td>
            <td class="text-right">{{ $fmt($totalIngresos) }}</td>
            <td class="text-right">{{ $fmt($totalCosto) }}</td>
            <td class="text-right">{{ $fmt($totalUtilidad) }}</td>
            <td class="text-right">{{ $num($margenGlobal) }}%</td>
        </tr>
    </tfoot>
</table>
@endif

@endsection
