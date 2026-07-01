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
    .resumen-grid {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 12px;
    }
    .resumen-grid td {
        width: 25%;
        padding: 6px 10px;
        border: 1px solid #dde3ec;
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
    table.datos tbody tr.recibido td { background-color: #f0fdf4; }
    table.datos tbody tr:nth-child(even):not(.recibido) td { background-color: #f0f4f8; }
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
        padding: 2px 5px;
        border-radius: 3px;
        font-size: 7.5px;
        font-weight: bold;
    }
    .badge-pendiente  { background:#fef3c7; color:#92400e; }
    .badge-recibido   { background:#d1fae5; color:#065f46; }
    .badge-rechazado  { background:#f1f5f9; color:#475569; }
    .badge-cancelado  { background:#fee2e2; color:#991b1b; }
    .seccion-titulo {
        font-size: 10px;
        font-weight: bold;
        color: #1E3A5F;
        border-bottom: 2px solid #1E3A5F;
        padding-bottom: 3px;
        margin-bottom: 8px;
        margin-top: 14px;
    }
    .notas-box {
        background-color: #fffbeb;
        border: 1px solid #fcd34d;
        border-radius: 3px;
        padding: 6px 10px;
        font-size: 8.5px;
        color: #92400e;
        margin-bottom: 10px;
    }
</style>

@php
    $fmt = fn($v) => '$' . number_format((float)$v, 2, '.', ',');
    $num = fn($v) => number_format((float)$v, 4, '.', ',');
    $t   = $traspaso;

    $totalCompra = collect($t->detalles)->sum(fn($d) => $d->cantidad * $d->precio_costo);
    $totalVenta  = collect($t->detalles)->sum(fn($d) => $d->cantidad * $d->precio_venta);
    $pendientes  = collect($t->detalles)->sum(fn($d) => $d->estado === 'pendiente' ? $d->cantidad : 0);

    $estadoBadge = 'badge-' . ($t->estado ?? 'pendiente');
@endphp

{{-- ── Encabezado del traspaso ─────────────────────────────────────── --}}
<table class="meta-grid">
    <tr>
        <td>
            <span class="label">Origen</span>
            <span class="valor">{{ $t->origen?->nombre ?? '—' }}</span>
        </td>
        <td>
            <span class="label">Destino</span>
            <span class="valor">{{ $t->destino?->nombre ?? '—' }}</span>
        </td>
        <td>
            <span class="label">Estado</span>
            <span class="valor"><span class="badge {{ $estadoBadge }}">{{ ucfirst($t->estado) }}</span></span>
        </td>
        <td>
            <span class="label">Fecha</span>
            <span class="valor">{{ \Carbon\Carbon::parse($t->created_at)->setTimezone('America/Mexico_City')->format('d/m/Y H:i') }}</span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="label">Envió</span>
            <span class="valor">{{ $t->user?->name ?? '—' }}</span>
        </td>
        <td>
            <span class="label">Recibió</span>
            <span class="valor">{{ $t->receptor?->name ?? '—' }}</span>
        </td>
        <td>
            <span class="label">{{ $t->estado === 'rechazado' ? 'Rechazó' : ($t->estado === 'cancelado' ? 'Canceló' : 'Acción') }}</span>
            <span class="valor">{{ $t->rechazador?->name ?? $t->cancelador?->name ?? '—' }}</span>
        </td>
        <td>
            <span class="label">Fecha recepción</span>
            <span class="valor">{{ $t->fecha_recepcion ? \Carbon\Carbon::parse($t->fecha_recepcion)->setTimezone('America/Mexico_City')->format('d/m/Y H:i') : '—' }}</span>
        </td>
    </tr>
</table>

{{-- ── Resumen financiero ──────────────────────────────────────────── --}}
<table class="resumen-grid">
    <tr>
        <td>
            <span class="label">Partidas</span>
            <span class="valor">{{ count($t->detalles) }}</span>
        </td>
        <td>
            <span class="label">Piezas totales</span>
            <span class="valor">{{ $num($t->total_items) }}</span>
        </td>
        <td>
            <span class="label">Valor compra</span>
            <span class="valor">{{ $fmt($totalCompra) }}</span>
        </td>
        <td>
            <span class="label">Valor venta</span>
            <span class="valor">{{ $fmt($totalVenta) }}</span>
        </td>
    </tr>
</table>

@if ($t->notas)
<div class="notas-box"><strong>Notas:</strong> {{ $t->notas }}</div>
@endif

@if ($t->motivo_rechazo)
<div class="notas-box" style="background:#fee2e2;border-color:#fca5a5;color:#991b1b;"><strong>Motivo rechazo:</strong> {{ $t->motivo_rechazo }}</div>
@endif

@if ($t->motivo_cancelacion)
<div class="notas-box" style="background:#fee2e2;border-color:#fca5a5;color:#991b1b;"><strong>Motivo cancelación:</strong> {{ $t->motivo_cancelacion }}</div>
@endif

{{-- ── Detalle de partidas ─────────────────────────────────────────── --}}
<div class="seccion-titulo">Partidas ({{ count($t->detalles) }})</div>
<table class="datos">
    <thead>
        <tr>
            <th>Estado</th>
            <th>Cantidad</th>
            <th>Producto</th>
            <th>Variante</th>
            <th>Serie / IMEI</th>
            <th class="text-right">Costo</th>
            <th class="text-right">Venta</th>
            <th class="text-right">Total costo</th>
            <th class="text-right">Total venta</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($t->detalles as $d)
        <tr class="{{ $d->estado === 'recibido' ? 'recibido' : '' }}">
            <td><span class="badge badge-{{ $d->estado }}">{{ ucfirst($d->estado) }}</span></td>
            <td>{{ $num($d->cantidad) }}</td>
            <td>{{ $d->producto_nombre }}</td>
            <td>{{ $d->variante_nombre ?: '—' }}</td>
            <td>{{ $d->serie_identificador ?: '—' }}</td>
            <td class="text-right">{{ $fmt($d->precio_costo) }}</td>
            <td class="text-right">{{ $fmt($d->precio_venta) }}</td>
            <td class="text-right">{{ $fmt($d->cantidad * $d->precio_costo) }}</td>
            <td class="text-right">{{ $fmt($d->cantidad * $d->precio_venta) }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="7">TOTALES</td>
            <td class="text-right">{{ $fmt($totalCompra) }}</td>
            <td class="text-right">{{ $fmt($totalVenta) }}</td>
        </tr>
    </tfoot>
</table>

@endsection
