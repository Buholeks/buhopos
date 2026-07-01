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
    .meta-grid {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 10px;
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
    .notas-box {
        background-color: #fffbeb;
        border: 1px solid #fcd34d;
        border-radius: 3px;
        padding: 6px 10px;
        font-size: 8.5px;
        color: #92400e;
        margin-bottom: 10px;
    }
    .badge {
        display: inline;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 8px;
        font-weight: bold;
    }
    .badge-confirmada   { background:#d1fae5; color:#065f46; }
    .badge-borrador     { background:#f1f5f9; color:#475569; }
    .badge-cancelada    { background:#fee2e2; color:#991b1b; }
    .badge-devuelta     { background:#ede9fe; color:#5b21b6; }
    .badge-devuelta_parcial { background:#fef3c7; color:#92400e; }
    .badge-pendiente    { background:#fef3c7; color:#92400e; }
    .badge-pagado       { background:#d1fae5; color:#065f46; }
    .badge-vencido      { background:#fee2e2; color:#991b1b; }
    .saldo-ok  { color: #16a34a; font-weight: bold; }
    .saldo-mal { color: #dc2626; font-weight: bold; }
</style>

@php
    $c = $compra;
    $estatus = $c->estatus_pago ?? 'pendiente';
    $estadoBadge = 'badge-' . ($c->estado ?? 'borrador');
    $estatusBadge = 'badge-' . $estatus;
@endphp

{{-- ── Datos de la compra ─────────────────────────────────────────── --}}
<table class="meta-grid">
    <tr>
        <td><span class="label">Proveedor</span><span class="valor">{{ $c->proveedor_nombre ?? '—' }}</span></td>
        <td><span class="label">RFC</span><span class="valor">{{ $c->proveedor_rfc ?? '—' }}</span></td>
        <td><span class="label">Fecha compra</span><span class="valor">{{ \Carbon\Carbon::parse($c->fecha)->format('d/m/Y') }}</span></td>
        <td><span class="label">Registró</span><span class="valor">{{ $c->usuario_nombre ?? '—' }}</span></td>
    </tr>
    <tr>
        <td><span class="label">Forma de pago</span><span class="valor">{{ ucfirst(str_replace('_', ' ', $c->forma_pago)) }}</span></td>
        <td><span class="label">Estado</span><span class="valor"><span class="badge {{ $estadoBadge }}">{{ ucfirst(str_replace('_', ' ', $c->estado)) }}</span></span></td>
        <td><span class="label">Vencimiento</span><span class="valor">{{ $c->fecha_vencimiento ? \Carbon\Carbon::parse($c->fecha_vencimiento)->format('d/m/Y') : 'N/A' }}</span></td>
        <td><span class="label">Estatus pago</span><span class="valor"><span class="badge {{ $estatusBadge }}">{{ ucfirst($estatus) }}</span></span></td>
    </tr>
</table>

{{-- ── Totales financieros ─────────────────────────────────────────── --}}
<table class="meta-grid">
    <tr>
        <td><span class="label">Subtotal</span><span class="valor">{{ $fmt($c->subtotal) }}</span></td>
        <td><span class="label">Total</span><span class="valor">{{ $fmt($c->total) }}</span></td>
        <td><span class="label">Pagado</span><span class="valor saldo-ok">{{ $fmt($c->pagado ?? 0) }}</span></td>
        <td><span class="label">Saldo pendiente</span><span class="valor {{ (float)($c->saldo ?? 0) > 0 ? 'saldo-mal' : 'saldo-ok' }}">{{ $fmt($c->saldo ?? 0) }}</span></td>
    </tr>
</table>

@if ($c->notas)
<div class="notas-box"><strong>Notas:</strong> {{ $c->notas }}</div>
@endif

{{-- ── Detalle de productos ─────────────────────────────────────────── --}}
<div class="seccion-titulo">Detalle de productos ({{ count($detalles) }})</div>
@if (count($detalles))
<table class="datos">
    <thead>
        <tr>
            <th>Producto</th>
            <th>SKU</th>
            <th class="text-right">Cantidad</th>
            <th class="text-right">Costo</th>
            <th class="text-right">P. Venta</th>
            <th class="text-right">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($detalles as $d)
        <tr>
            <td>{{ $d->producto }}</td>
            <td>{{ $d->sku ?? '—' }}</td>
            <td class="text-right">{{ $d->cantidad }}</td>
            <td class="text-right">{{ $fmt($d->precio_compra) }}</td>
            <td class="text-right">{{ $fmt($d->precio_venta) }}</td>
            <td class="text-right">{{ $fmt($d->subtotal) }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5">Total</td>
            <td class="text-right">{{ $fmt(collect($detalles)->sum('subtotal')) }}</td>
        </tr>
    </tfoot>
</table>
@else
<p style="text-align:center;color:#888;font-size:9px;padding:10px;">Sin productos registrados.</p>
@endif

{{-- ── Historial de pagos ─────────────────────────────────────────── --}}
@if (count($pagos))
<div class="seccion-titulo">Historial de pagos ({{ count($pagos) }})</div>
<table class="datos">
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Forma</th>
            <th>Referencia</th>
            <th>Registró</th>
            <th>Notas</th>
            <th class="text-right">Monto</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($pagos as $p)
        <tr>
            <td>{{ \Carbon\Carbon::parse($p->fecha_pago)->format('d/m/Y') }}</td>
            <td>{{ ucfirst(str_replace('_', ' ', $p->forma_pago)) }}</td>
            <td>{{ $p->referencia ?? '—' }}</td>
            <td>{{ $p->usuario_nombre ?? '—' }}</td>
            <td>{{ $p->notas ?? '—' }}</td>
            <td class="text-right">{{ $fmt($p->monto) }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5">Total pagado</td>
            <td class="text-right">{{ $fmt(collect($pagos)->sum('monto')) }}</td>
        </tr>
    </tfoot>
</table>
@endif

@endsection
