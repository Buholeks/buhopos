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
    $columnas = $columnas ?? ['clave', 'producto', 'categoria', 'proveedor', 'stock', 'comprometido', 'disponible', 'variantes', 'costo', 'precio_venta', 'invertido', 'valor_venta', 'margen', 'alertas', 'series'];
    $ver = fn($columna) => in_array($columna, $columnas, true);
@endphp

{{-- ── Resumen global ──────────────────────────────────────────────────── --}}
<table class="resumen-grid">
    <tr>
        <td>
            <span class="label">Artículos</span>
            <span class="valor">{{ number_format($resumen['articulos']) }}</span>
        </td>
        @if($ver('stock'))<td>
            <span class="label">Existencia total</span>
            <span class="valor">{{ $num($resumen['unidades']) }}</span>
        </td>@endif
        @if($ver('comprometido'))<td>
            <span class="label">Comprometido</span>
            <span class="valor valor-ambar">{{ $num($resumen['comprometidas']) }}</span>
        </td>@endif
        @if($ver('disponible'))<td>
            <span class="label">Disponible</span>
            <span class="valor valor-verde">{{ $num($resumen['disponibles']) }}</span>
        </td>@endif
        @if($ver('invertido'))<td>
            <span class="label">Invertido</span>
            <span class="valor valor-verde">{{ $fmt($resumen['invertido']) }}</span>
        </td>@endif
        @if($ver('valor_venta'))<td>
            <span class="label">Valor venta</span>
            <span class="valor">{{ $fmt($resumen['valor_venta']) }}</span>
        </td>@endif
        @if($ver('margen'))<td>
            <span class="label">Margen potencial</span>
            <span class="valor {{ $colorMargen($resumen['margen_potencial']) }}">{{ $num($resumen['margen_potencial']) }}%</span>
        </td>@endif
        @if($ver('alertas'))<td>
            <span class="label">Sin costo</span>
            <span class="valor {{ $resumen['sin_costo'] > 0 ? 'valor-ambar' : '' }}">{{ $resumen['sin_costo'] }}</span>
        </td>@endif
        @if($ver('alertas'))<td>
            <span class="label">Bajo mínimo</span>
            <span class="valor {{ $resumen['bajo_minimo'] > 0 ? 'valor-ambar' : '' }}">{{ $resumen['bajo_minimo'] }}</span>
        </td>@endif
    </tr>
</table>

@if ($ver('alertas') && $resumen['sin_costo'] > 0)
<div style="background:#fffbeb;border:1px solid #fcd34d;padding:5px 8px;font-size:8px;color:#92400e;margin-bottom:10px;">
    ⚠ {{ $resumen['sin_costo'] }} artículos sin costo capturado; el total invertido puede estar incompleto.
</div>
@endif

{{-- ── Tabla según agrupación ─────────────────────────────────────────── --}}
@if ($agrupar === 'producto')

<table class="datos">
    <thead>
        <tr>
            @if($ver('clave'))<th>Clave</th>@endif
            <th>Producto</th>
            @if($ver('categoria'))<th>Categoría</th>@endif
            @if($ver('proveedor'))<th>Proveedor</th>@endif
            @if($ver('stock'))<th class="text-right">Existencia</th>@endif
            @if($ver('comprometido'))<th class="text-right">Comprometido</th>@endif
            @if($ver('disponible'))<th class="text-right">Disponible</th>@endif
            @if($ver('variantes'))<th class="text-right">Var.</th>@endif
            @if($ver('costo'))<th class="text-right">Costo actual</th>@endif
            @if($ver('precio_venta'))<th class="text-right">P. venta unit.</th>@endif
            @if($ver('invertido'))<th class="text-right">Invertido</th>@endif
            @if($ver('valor_venta'))<th class="text-right">Valor venta</th>@endif
            @if($ver('margen'))<th class="text-right">Margen %</th>@endif
            @if($ver('alertas'))<th>Alertas</th>@endif
        </tr>
    </thead>
    <tbody>
        @foreach ($items as $item)
        <tr>
            @if($ver('clave'))<td>{{ $item['codigo'] ?? '—' }}</td>@endif
            <td>{{ $item['producto'] }}
                @if ($ver('series') && !empty($item['series']))
                    <div style="margin-top:3px;font-size:7px;color:#64748b;word-wrap:break-word;">IMEI / Series: {{ $item['series'] }}</div>
                @endif
            </td>
            @if($ver('categoria'))<td>{{ $item['categoria'] }}</td>@endif
            @if($ver('proveedor'))<td>{{ $item['proveedor'] }}</td>@endif
            @if($ver('stock'))<td class="text-right">{{ $num($item['stock']) }}</td>@endif
            @if($ver('comprometido'))<td class="text-right">{{ $num($item['comprometido']) }}</td>@endif
            @if($ver('disponible'))<td class="text-right verde">{{ $num($item['disponible']) }}</td>@endif
            @if($ver('variantes'))<td class="text-right">{{ $item['variantes'] }}</td>@endif
            @if($ver('costo'))<td class="text-right">{{ $fmt($item['costo']) }}</td>@endif
            @if($ver('precio_venta'))<td class="text-right">{{ $fmt($item['precio_venta']) }}</td>@endif
            @if($ver('invertido'))<td class="text-right">{{ $fmt($item['invertido']) }}</td>@endif
            @if($ver('valor_venta'))<td class="text-right">{{ $fmt($item['valor_venta']) }}</td>@endif
            @if($ver('margen'))<td class="text-right {{ $colorMargen($item['margen']) }}">{{ $num($item['margen']) }}%</td>@endif
            @if($ver('alertas'))<td>
                @if($item['sin_costo'])<span class="badge-alerta">Sin costo</span>@endif
                @if($item['bajo_minimo'])<span class="badge-alerta">Bajo mínimo</span>@endif
                @if(!$item['sin_costo'] && !$item['bajo_minimo'])—@endif
            </td>@endif
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            @if($ver('clave'))<td></td>@endif
            <td>TOTALES</td>
            @if($ver('categoria'))<td></td>@endif
            @if($ver('proveedor'))<td></td>@endif
            @if($ver('stock'))<td class="text-right">{{ $num(collect($items)->sum('stock')) }}</td>@endif
            @if($ver('comprometido'))<td class="text-right">{{ $num(collect($items)->sum('comprometido')) }}</td>@endif
            @if($ver('disponible'))<td class="text-right">{{ $num(collect($items)->sum('disponible')) }}</td>@endif
            @if($ver('variantes'))<td></td>@endif
            @if($ver('costo'))<td></td>@endif
            @if($ver('precio_venta'))<td></td>@endif
            @if($ver('invertido'))<td class="text-right">{{ $fmt($totalInv) }}</td>@endif
            @if($ver('valor_venta'))<td class="text-right">{{ $fmt($totalVV) }}</td>@endif
            @if($ver('margen'))<td class="text-right">{{ $num($margenTotal) }}%</td>@endif
            @if($ver('alertas'))<td></td>@endif
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
