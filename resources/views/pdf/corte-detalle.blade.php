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
    .grid-resumen {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 10px;
    }
    .grid-resumen td {
        width: 20%;
        padding: 6px 10px;
        border: 1px solid #dde3ec;
        vertical-align: top;
    }
    .grid-resumen .label {
        font-size: 8px;
        color: #64748b;
        display: block;
    }
    .grid-resumen .valor {
        font-size: 11px;
        font-weight: bold;
        color: #1a1a1a;
        display: block;
        margin-top: 2px;
    }
    .arqueo-grid {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 10px;
    }
    .arqueo-grid th {
        background-color: #1E3A5F;
        color: white;
        padding: 5px 8px;
        font-size: 8.5px;
        text-align: left;
        border: 1px solid #163050;
    }
    .arqueo-grid td {
        padding: 5px 8px;
        border: 1px solid #dde3ec;
        font-size: 9px;
    }
    .arqueo-grid tr:nth-child(even) td { background-color: #f0f4f8; }
    .dif-positivo { color: #16a34a; font-weight: bold; }
    .dif-negativo { color: #dc2626; font-weight: bold; }

    table.datos {
        width: 100%;
        border-collapse: collapse;
        font-size: 8.5px;
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
    .text-center { text-align: center; }
    .ingreso { color: #16a34a; }
    .egreso  { color: #dc2626; }
    .meta-info {
        font-size: 8.5px;
        color: #44546a;
        margin-bottom: 10px;
    }
    .meta-info span { margin-right: 18px; }
    .meta-info b { color: #1a1a1a; }
    .sin-datos { text-align: center; padding: 12px; color: #888; font-style: italic; font-size: 9px; }
    .badge {
        display: inline;
        padding: 1px 5px;
        border-radius: 3px;
        font-size: 8px;
        font-weight: bold;
    }
    .badge-abierto { background-color: #d1fae5; color: #065f46; }
    .badge-cerrado { background-color: #f1f5f9; color: #475569; }
</style>

{{-- ── Datos del corte ───────────────────────────────────────────── --}}
<div class="meta-info">
    <span>Cajero: <b>{{ $corte->user?->name ?? '—' }}</b></span>
    <span>Terminal: <b>{{ $corte->terminal }}</b></span>
    <span>Apertura: <b>{{ \Carbon\Carbon::parse($corte->fecha_apertura)->setTimezone('America/Mexico_City')->format('d/m/Y H:i') }}</b></span>
    @if ($corte->fecha_cierre)
        <span>Cierre: <b>{{ \Carbon\Carbon::parse($corte->fecha_cierre)->setTimezone('America/Mexico_City')->format('d/m/Y H:i') }}</b></span>
    @endif
    <span>Estado: <span class="badge {{ $corte->estado === 'abierto' ? 'badge-abierto' : 'badge-cerrado' }}">{{ ucfirst($corte->estado) }}</span></span>
</div>

{{-- ── Resumen de ventas ─────────────────────────────────────────── --}}
<div class="seccion-titulo">Resumen de ventas</div>
<table class="grid-resumen">
    <tr>
        <td><span class="label">Efectivo</span><span class="valor">{{ $fmt($corte->ventas_efectivo) }}</span></td>
        <td><span class="label">Tarjeta</span><span class="valor">{{ $fmt($corte->ventas_tarjeta) }}</span></td>
        <td><span class="label">Transferencia</span><span class="valor">{{ $fmt($corte->ventas_transferencia) }}</span></td>
        <td><span class="label">Crédito</span><span class="valor">{{ $fmt($corte->ventas_credito) }}</span></td>
        <td><span class="label">Saldo a favor</span><span class="valor">{{ $fmt($corte->ventas_saldo_favor) }}</span></td>
    </tr>
</table>

{{-- ── Arqueo final (solo si cerrado) ──────────────────────────── --}}
@if ($corte->estado === 'cerrado')
<div class="seccion-titulo">Arqueo final</div>
<table class="arqueo-grid">
    <thead>
        <tr>
            <th>Forma pago</th>
            <th class="text-right">Esperado</th>
            <th class="text-right">Contado</th>
            <th class="text-right">Diferencia</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Efectivo</td>
            <td class="text-right">{{ $fmt($corte->esperado_efectivo) }}</td>
            <td class="text-right">{{ $fmt($corte->contado_efectivo) }}</td>
            <td class="text-right {{ $corte->dif_efectivo >= 0 ? 'dif-positivo' : 'dif-negativo' }}">{{ $fmt($corte->dif_efectivo) }}</td>
        </tr>
        <tr>
            <td>Tarjeta</td>
            <td class="text-right">{{ $fmt($corte->esperado_tarjeta) }}</td>
            <td class="text-right">{{ $fmt($corte->contado_tarjeta) }}</td>
            <td class="text-right {{ $corte->dif_tarjeta >= 0 ? 'dif-positivo' : 'dif-negativo' }}">{{ $fmt($corte->dif_tarjeta) }}</td>
        </tr>
        <tr>
            <td>Transferencia</td>
            <td class="text-right">{{ $fmt($corte->esperado_transferencia) }}</td>
            <td class="text-right">{{ $fmt($corte->contado_transferencia) }}</td>
            <td class="text-right {{ $corte->dif_transferencia >= 0 ? 'dif-positivo' : 'dif-negativo' }}">{{ $fmt($corte->dif_transferencia) }}</td>
        </tr>
    </tbody>
</table>
@endif

{{-- ── Movimientos de caja ───────────────────────────────────────── --}}
@if ($movimientos->isNotEmpty())
<div class="seccion-titulo">Movimientos de caja ({{ $movimientos->count() }})</div>
<table class="datos">
    <thead>
        <tr>
            <th>Tipo</th>
            <th>Concepto</th>
            <th>Forma pago</th>
            <th>Usuario</th>
            <th class="text-right">Monto</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($movimientos as $m)
        <tr>
            <td class="{{ $m->tipo === 'ingreso' ? 'ingreso' : 'egreso' }}">{{ ucfirst($m->tipo) }}</td>
            <td>{{ $m->concepto }}</td>
            <td>{{ ucfirst($m->forma_pago) }}</td>
            <td>{{ $m->user?->name ?? '—' }}</td>
            <td class="text-right {{ $m->tipo === 'ingreso' ? 'ingreso' : 'egreso' }}">
                {{ $m->tipo === 'egreso' ? '-' : '' }}{{ $fmt($m->monto) }}
            </td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4">Total movimientos efectivo</td>
            <td class="text-right">{{ $fmt($corte->movs_efectivo) }}</td>
        </tr>
    </tfoot>
</table>
@endif

{{-- ── Ventas del turno ──────────────────────────────────────────── --}}
<div class="seccion-titulo">Ventas del turno ({{ $ventas->count() }})</div>
@if ($ventas->isEmpty())
    <p class="sin-datos">Sin ventas en este turno</p>
@else
<table class="datos">
    <thead>
        <tr>
            <th>Folio</th>
            <th>Hora</th>
            <th>Cajero</th>
            <th>Forma pago</th>
            <th>Productos</th>
            <th class="text-right">Descuento</th>
            <th class="text-right">Total</th>
        </tr>
    </thead>
    <tbody>
        @php $totalVentas = 0; @endphp
        @foreach ($ventas as $v)
        @php $totalVentas += (float) $v->total; @endphp
        <tr>
            <td>{{ $v->folio ?? $v->id }}</td>
            <td>{{ \Carbon\Carbon::parse($v->fecha)->setTimezone('America/Mexico_City')->format('H:i') }}</td>
            <td>{{ $v->user?->name ?? '—' }}</td>
            <td>{{ ucfirst(str_replace('_', ' ', $v->forma_pago)) }}</td>
            <td>{{ $v->detalles->map(fn($d) => ($d->producto?->nombre ?? '—') . ' x' . $d->cantidad)->join(', ') }}</td>
            <td class="text-right">{{ $v->descuento > 0 ? $fmt($v->descuento) : '—' }}</td>
            <td class="text-right">{{ $fmt($v->total) }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="6">Total</td>
            <td class="text-right">{{ $fmt($totalVentas) }}</td>
        </tr>
    </tfoot>
</table>
@endif

@endsection
