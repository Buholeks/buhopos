@extends('pdf.layout')

@section('contenido')
<style>
    .resumen-grid {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 12px;
    }
    .resumen-grid td {
        padding: 6px 10px;
        border: 1px solid #dde3ec;
        text-align: center;
        vertical-align: top;
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
    .text-right  { text-align: right; }
    .text-center { text-align: center; }
    .verde { color: #16a34a; font-weight: bold; }
    .ambar { color: #d97706; font-weight: bold; }
    .rojo  { color: #dc2626; font-weight: bold; }
    .badge-alerta {
        display: inline;
        background: #fef3c7;
        color: #92400e;
        padding: 1px 4px;
        border-radius: 2px;
        font-size: 7.5px;
        font-weight: bold;
        margin-right: 2px;
    }
</style>

@php
    $fmt = fn($v) => '$' . number_format((float)$v, 2, '.', ',');
    $num = fn($v) => number_format((float)$v, 2, '.', ',');

    $colorMargen = fn($m) => $m < 0 ? 'rojo' : ($m < 15 ? 'ambar' : 'verde');

    $totalInv = collect($items)->sum(fn($i) => $i['invertido'] ?? 0);
    $totalVV  = collect($items)->sum(fn($i) => $i['valor_venta'] ?? 0);
    $margenTotal = $totalVV > 0 ? round((($totalVV - $totalInv) / $totalVV) * 100, 2) : 0;
@endphp

{{-- ── Resumen global ──────────────────────────────────────────────────── --}}
<table class="resumen-grid">
    <tr>
        <td>
            <span class="label">Artículos</span>
            <span class="valor">{{ number_format($resumen['articulos']) }}</span>
        </td>
        <td>
            <span class="label">Existencia total</span>
            <span class="valor">{{ $num($resumen['unidades']) }}</span>
        </td>
        <td>
            <span class="label">Invertido</span>
            <span class="valor valor-verde">{{ $fmt($resumen['invertido']) }}</span>
        </td>
        <td>
            <span class="label">Valor venta</span>
            <span class="valor">{{ $fmt($resumen['valor_venta']) }}</span>
        </td>
        <td>
            <span class="label">Margen potencial</span>
            <span class="valor {{ $colorMargen($resumen['margen_potencial']) }}">{{ $num($resumen['margen_potencial']) }}%</span>
        </td>
        <td>
            <span class="label">Sin costo</span>
            <span class="valor {{ $resumen['sin_costo'] > 0 ? 'valor-ambar' : '' }}">{{ $resumen['sin_costo'] }}</span>
        </td>
        <td>
            <span class="label">Bajo mínimo</span>
            <span class="valor {{ $resumen['bajo_minimo'] > 0 ? 'valor-ambar' : '' }}">{{ $resumen['bajo_minimo'] }}</span>
        </td>
    </tr>
</table>

@if ($resumen['sin_costo'] > 0)
<div style="background:#fffbeb;border:1px solid #fcd34d;padding:5px 8px;font-size:8px;color:#92400e;margin-bottom:10px;">
    ⚠ {{ $resumen['sin_costo'] }} artículos sin costo capturado; el total invertido puede estar incompleto.
</div>
@endif

{{-- ── Tabla según agrupación ─────────────────────────────────────────── --}}
@if ($agrupar === 'producto')

<table class="datos">
    <thead>
        <tr>
            <th>Clave</th>
            <th>Producto</th>
            <th>Categoría</th>
            <th>Proveedor</th>
            <th class="text-right">Existencia</th>
            <th class="text-right">Var.</th>
            <th class="text-right">Costo prom.</th>
            <th class="text-right">Invertido</th>
            <th class="text-right">Valor venta</th>
            <th class="text-right">Margen %</th>
            <th>Alertas</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($items as $item)
        <tr>
            <td>{{ $item['codigo'] ?? '—' }}</td>
            <td>{{ $item['producto'] }}</td>
            <td>{{ $item['categoria'] }}</td>
            <td>{{ $item['proveedor'] }}</td>
            <td class="text-right">{{ $num($item['stock']) }}</td>
            <td class="text-right">{{ $item['variantes'] }}</td>
            <td class="text-right">{{ $fmt($item['costo']) }}</td>
            <td class="text-right">{{ $fmt($item['invertido']) }}</td>
            <td class="text-right">{{ $fmt($item['valor_venta']) }}</td>
            <td class="text-right {{ $colorMargen($item['margen']) }}">{{ $num($item['margen']) }}%</td>
            <td>
                @if($item['sin_costo'])<span class="badge-alerta">Sin costo</span>@endif
                @if($item['bajo_minimo'])<span class="badge-alerta">Bajo mínimo</span>@endif
                @if(!$item['sin_costo'] && !$item['bajo_minimo'])—@endif
            </td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4">TOTALES</td>
            <td class="text-right">{{ $num(collect($items)->sum('stock')) }}</td>
            <td></td>
            <td></td>
            <td class="text-right">{{ $fmt($totalInv) }}</td>
            <td class="text-right">{{ $fmt($totalVV) }}</td>
            <td class="text-right">{{ $num($margenTotal) }}%</td>
            <td></td>
        </tr>
    </tfoot>
</table>

@else

@php $etiqueta = $agrupar === 'categoria' ? 'Categoría' : 'Proveedor'; @endphp
<table class="datos">
    <thead>
        <tr>
            <th>{{ $etiqueta }}</th>
            <th class="text-right">Artículos</th>
            <th class="text-right">Existencia</th>
            <th class="text-right">Invertido</th>
            <th class="text-right">Valor venta</th>
            <th class="text-right">Margen %</th>
            <th class="text-right">Sin costo</th>
            <th class="text-right">Bajo mínimo</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($items as $item)
        <tr>
            <td>{{ $item['nombre'] }}</td>
            <td class="text-right">{{ $item['articulos'] }}</td>
            <td class="text-right">{{ $num($item['unidades']) }}</td>
            <td class="text-right">{{ $fmt($item['invertido']) }}</td>
            <td class="text-right">{{ $fmt($item['valor_venta']) }}</td>
            <td class="text-right {{ $colorMargen($item['margen']) }}">{{ $num($item['margen']) }}%</td>
            <td class="text-right">{{ $item['sin_costo'] }}</td>
            <td class="text-right">{{ $item['bajo_minimo'] }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td>TOTALES</td>
            <td class="text-right">{{ collect($items)->sum('articulos') }}</td>
            <td class="text-right">{{ $num(collect($items)->sum('unidades')) }}</td>
            <td class="text-right">{{ $fmt($totalInv) }}</td>
            <td class="text-right">{{ $fmt($totalVV) }}</td>
            <td class="text-right">{{ $num($margenTotal) }}%</td>
            <td class="text-right">{{ collect($items)->sum('sin_costo') }}</td>
            <td class="text-right">{{ collect($items)->sum('bajo_minimo') }}</td>
        </tr>
    </tfoot>
</table>

@endif

@endsection
