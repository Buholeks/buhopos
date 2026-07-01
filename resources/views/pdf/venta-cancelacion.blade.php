@extends('pdf.layout')

@section('contenido')
<style>
    .meta-grid {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 12px;
    }
    .meta-grid td {
        width: 25%;
        padding: 6px 10px;
        border: 1px solid #dde3ec;
        vertical-align: top;
    }
    .meta-grid .label {
        font-size: 8px;
        color: #64748b;
        display: block;
    }
    .meta-grid .valor {
        font-size: 10px;
        font-weight: bold;
        color: #1a1a1a;
        display: block;
        margin-top: 2px;
    }
    table.datos {
        width: 100%;
        border-collapse: collapse;
        font-size: 9px;
        margin-bottom: 10px;
    }
    table.datos thead tr th {
        background-color: #1E3A5F;
        color: white;
        padding: 5px 7px;
        text-align: left;
        border: 1px solid #163050;
        font-weight: bold;
    }
    table.datos tbody tr td {
        padding: 4px 7px;
        border: 1px solid #dde3ec;
    }
    table.datos tbody tr:nth-child(even) td { background-color: #f0f4f8; }
    table.datos tfoot tr td {
        background-color: #d9e2ec;
        color: #1E3A5F;
        font-weight: bold;
        padding: 5px 7px;
        border: 1px solid #b8c6d6;
    }
    .text-right { text-align: right; }
    .badge {
        display: inline;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 8px;
        font-weight: bold;
    }
    .badge-confirmada { background:#d1fae5; color:#065f46; }
    .badge-cancelada  { background:#fee2e2; color:#991b1b; }
    .badge-devuelta   { background:#ede9fe; color:#5b21b6; }
    .notas-box {
        background-color: #fff7ed;
        border: 1px solid #fed7aa;
        border-radius: 3px;
        padding: 6px 10px;
        font-size: 8.5px;
        color: #9a3412;
        margin-bottom: 10px;
    }
    .seccion-titulo {
        font-size: 10px;
        font-weight: bold;
        color: #1E3A5F;
        border-bottom: 2px solid #1E3A5F;
        padding-bottom: 3px;
        margin-bottom: 8px;
        margin-top: 12px;
    }
</style>

@php
    $fmt = fn($v) => '$' . number_format((float)$v, 2, '.', ',');
    $v   = $venta;
    $estadoBadge = 'badge-' . ($v['estado'] ?? 'confirmada');
@endphp

{{-- ── Datos de la venta ───────────────────────────────────────────── --}}
<table class="meta-grid">
    <tr>
        <td>
            <span class="label">Folio</span>
            <span class="valor">{{ $v['folio'] }}</span>
        </td>
        <td>
            <span class="label">Estado</span>
            <span class="valor"><span class="badge {{ $estadoBadge }}">{{ ucfirst($v['estado']) }}</span></span>
        </td>
        <td>
            <span class="label">Fecha venta</span>
            <span class="valor">{{ \Carbon\Carbon::parse($v['fecha'])->setTimezone('America/Mexico_City')->format('d/m/Y H:i') }}</span>
        </td>
        <td>
            <span class="label">Forma de pago</span>
            <span class="valor">{{ ucfirst(str_replace('_', ' ', $v['forma_pago'] ?? '—')) }}</span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="label">Cliente</span>
            <span class="valor">{{ $v['cliente']['nombre'] ?? 'Público general' }}</span>
        </td>
        <td>
            <span class="label">Usuario</span>
            <span class="valor">{{ $v['usuario']['name'] ?? '—' }}</span>
        </td>
        <td>
            <span class="label">Subtotal</span>
            <span class="valor">{{ $fmt($v['subtotal'] ?? 0) }}</span>
        </td>
        <td>
            <span class="label">Total</span>
            <span class="valor">{{ $fmt($v['total'] ?? 0) }}</span>
        </td>
    </tr>
</table>

@if (!empty($v['motivo_cancelacion']))
<div class="notas-box"><strong>Motivo de cancelación:</strong> {{ $v['motivo_cancelacion'] }}</div>
@endif

{{-- ── Partidas ─────────────────────────────────────────────────────── --}}
<div class="seccion-titulo">Partidas ({{ count($v['detalles'] ?? []) }})</div>
<table class="datos">
    <thead>
        <tr>
            <th>Producto</th>
            <th>Variante / Serie</th>
            <th class="text-right">Cantidad</th>
            <th class="text-right">Precio</th>
            <th class="text-right">Devuelto</th>
            <th class="text-right">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($v['detalles'] ?? [] as $d)
        <tr>
            <td>{{ $d['producto_nombre'] }}<br><span style="font-size:7.5px;color:#64748b;">{{ $d['producto_codigo'] ?? '' }}</span></td>
            <td>
                {{ $d['variante_nombre'] ?? '' }}
                @if (!empty($d['serie']))<br><span style="font-size:7.5px;color:#64748b;">Serie: {{ $d['serie'] }}</span>@endif
            </td>
            <td class="text-right">{{ $d['cantidad'] }}</td>
            <td class="text-right">{{ $fmt($d['precio_venta']) }}</td>
            <td class="text-right">{{ $d['cantidad_devuelta'] ?? 0 }}</td>
            <td class="text-right">{{ $fmt($d['subtotal'] ?? ($d['cantidad'] * $d['precio_venta'])) }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5">Total</td>
            <td class="text-right">{{ $fmt($v['total'] ?? 0) }}</td>
        </tr>
    </tfoot>
</table>

{{-- ── Devoluciones registradas ─────────────────────────────────────── --}}
@if (!empty($v['devoluciones']) && count($v['devoluciones']) > 0)
<div class="seccion-titulo">Devoluciones registradas ({{ count($v['devoluciones']) }})</div>
<table class="datos">
    <thead>
        <tr>
            <th>Folio</th>
            <th>Fecha</th>
            <th>Usuario</th>
            <th>Forma</th>
            <th>Motivo</th>
            <th class="text-right">Total devuelto</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($v['devoluciones'] as $dev)
        <tr>
            <td>{{ $dev['folio'] ?? '—' }}</td>
            <td>{{ isset($dev['fecha']) ? \Carbon\Carbon::parse($dev['fecha'])->setTimezone('America/Mexico_City')->format('d/m/Y H:i') : '—' }}</td>
            <td>{{ $dev['usuario']['name'] ?? '—' }}</td>
            <td>{{ ucfirst(str_replace('_', ' ', $dev['forma_devolucion'] ?? '')) }}</td>
            <td>{{ $dev['motivo'] ?? '—' }}</td>
            <td class="text-right">{{ $fmt($dev['total'] ?? 0) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

@endsection
