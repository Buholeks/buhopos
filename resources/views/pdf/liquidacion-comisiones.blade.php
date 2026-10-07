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
        margin-top: 12px;
    }
    .grid {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 8px;
    }
    .grid td {
        padding: 6px 10px;
        border: 1px solid #dde3ec;
        vertical-align: top;
    }
    .grid .label {
        font-size: 8px;
        color: #64748b;
        display: block;
    }
    .grid .valor {
        font-size: 11px;
        font-weight: bold;
        color: #1a1a1a;
        display: block;
        margin-top: 2px;
    }
    .negativo { color: #dc2626; }
    .estado {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 8px;
        font-size: 9px;
        font-weight: bold;
    }
    .estado-borrador { background-color: #fef3c7; color: #92400e; }
    .estado-pagada { background-color: #d1fae5; color: #065f46; }
    .estado-cancelada { background-color: #e2e8f0; color: #475569; }

    table.datos {
        width: 100%;
        border-collapse: collapse;
        font-size: 8.5px;
    }
    table.datos thead { display: table-header-group; }
    table.datos tr { page-break-inside: avoid; }
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
        padding: 6px 7px;
        border: 1px solid #b8c6d6;
    }
    .num { text-align: right; white-space: nowrap; }
    .sub { font-size: 7.5px; color: #64748b; }
    .notas {
        margin-top: 10px;
        padding: 6px 10px;
        background-color: #f0f4f8;
        border: 1px solid #dde3ec;
        font-size: 8.5px;
        color: #44546a;
    }
    .firmas {
        width: 100%;
        margin-top: 50px;
        border-collapse: collapse;
    }
    .firmas td {
        width: 50%;
        padding: 0 24px;
        text-align: center;
        font-size: 9px;
        color: #44546a;
    }
    .firmas .linea {
        border-top: 1px solid #1a1a1a;
        padding-top: 4px;
    }
</style>

{{-- Datos de la liquidación --}}
<table class="grid">
    <tr>
        <td style="width: 34%">
            <span class="label">Empleado</span>
            <span class="valor">{{ $liquidacion->user?->name }}</span>
            @if ($sucursales !== '') <span class="sub">{{ $sucursales }}</span> @endif
        </td>
        <td style="width: 33%">
            <span class="label">Periodo</span>
            <span class="valor">{{ $liquidacion->desde?->format('d/m/Y') }} – {{ $liquidacion->hasta?->format('d/m/Y') }}</span>
        </td>
        <td style="width: 33%">
            <span class="label">Estado</span>
            <span class="valor">
                <span class="estado estado-{{ $liquidacion->estado }}">{{ $estadoTexto }}</span>
            </span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="label">Generada</span>
            <span class="valor">{{ $liquidacion->created_at?->timezone('America/Mexico_City')->format('d/m/Y H:i') }}
                @if ($liquidacion->creador) · {{ $liquidacion->creador->name }} @endif</span>
        </td>
        <td colspan="2">
            @if ($liquidacion->estado === 'pagada')
                <span class="label">Pago</span>
                <span class="valor">
                    {{ $liquidacion->pagado_en?->timezone('America/Mexico_City')->format('d/m/Y H:i') }}
                    @if ($liquidacion->pagador) · {{ $liquidacion->pagador->name }} @endif
                    · {{ $liquidacion->movimientoCaja ? 'Salió de caja (' . $liquidacion->movimientoCaja->forma_pago . ')' : 'Sin movimiento de caja' }}
                </span>
            @elseif ($liquidacion->estado === 'cancelada')
                <span class="label">Cancelación</span>
                <span class="valor">
                    {{ $liquidacion->cancelado_en?->timezone('America/Mexico_City')->format('d/m/Y H:i') }}
                    @if ($liquidacion->cancelador) · {{ $liquidacion->cancelador->name }} @endif
                </span>
            @else
                <span class="label">Pago</span>
                <span class="valor">Pendiente de pagar</span>
            @endif
        </td>
    </tr>
</table>

{{-- Resumen --}}
<div class="seccion-titulo">Resumen</div>
<table class="grid">
    <tr>
        <td style="width: 20%">
            <span class="label">Como vendedor</span>
            <span class="valor">{{ $fmt($resumen['comision_vendedor']) }}</span>
        </td>
        <td style="width: 20%">
            <span class="label">Como cajero</span>
            <span class="valor">{{ $fmt($resumen['comision_cajero']) }}</span>
        </td>
        <td style="width: 20%">
            <span class="label">Devoluciones / cancelaciones</span>
            <span class="valor {{ $resumen['descuentos'] < 0 ? 'negativo' : '' }}">{{ $fmt($resumen['descuentos']) }}</span>
        </td>
        <td style="width: 20%">
            <span class="label">Ajustes</span>
            <span class="valor">{{ $fmt($resumen['ajustes']) }}</span>
        </td>
        <td style="width: 20%; background-color: #d9e2ec">
            <span class="label">Total</span>
            <span class="valor" style="font-size: 13px">{{ $fmt($liquidacion->total) }}</span>
        </td>
    </tr>
</table>

{{-- Detalle --}}
<div class="seccion-titulo">Comisiones incluidas ({{ count($movimientos) }})</div>
<table class="datos">
    <thead>
        <tr>
            <th style="width: 9%">Fecha</th>
            <th style="width: 13%">Ticket</th>
            <th style="width: 11%">Tipo</th>
            <th style="width: 33%">Producto</th>
            <th style="width: 22%">Cálculo</th>
            <th style="width: 12%" class="num">Comisión</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($movimientos as $m)
            <tr>
                <td>{{ \Illuminate\Support\Carbon::parse($m['fecha'])->format('d/m/Y') }}</td>
                <td>
                    {{ $m['folio'] ?? '—' }}
                    @if ($m['sucursal']) <div class="sub">{{ $m['sucursal'] }}</div> @endif
                </td>
                <td>
                    {{ $tipos[$m['tipo']] ?? $m['tipo'] }}
                    <div class="sub">{{ $m['beneficiario'] === 'cajero' ? 'Cajero' : 'Vendedor' }}</div>
                </td>
                <td>
                    {{ $m['producto'] ?? '—' }}
                    @if ($m['precio'] !== null)
                        <div class="sub">{{ rtrim(rtrim(number_format(abs($m['cantidad']), 3), '0'), '.') }} × {{ $fmt($m['precio']) }}@if ($m['lista_precio']) · {{ $m['lista_precio'] === 'MANUAL' ? 'Manual' : $m['lista_precio'] }}@endif</div>
                    @endif
                </td>
                <td>{{ $m['calculo'] }}</td>
                <td class="num {{ $m['monto'] < 0 ? 'negativo' : '' }}">{{ $fmt($m['monto']) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6" style="text-align: center; color: #888; padding: 14px">
                    {{ $liquidacion->estado === 'cancelada' ? 'La liquidación está cancelada; sus comisiones volvieron a quedar pendientes.' : 'Sin movimientos.' }}
                </td>
            </tr>
        @endforelse
    </tbody>
    @if (count($movimientos))
        <tfoot>
            <tr>
                <td colspan="5" class="num">Total</td>
                <td class="num">{{ $fmt($liquidacion->total) }}</td>
            </tr>
        </tfoot>
    @endif
</table>

@if ($liquidacion->notas)
    <div class="notas"><b>Notas:</b> {!! nl2br(e($liquidacion->notas)) !!}</div>
@endif

@if ($liquidacion->estado !== 'cancelada')
    <table class="firmas">
        <tr>
            <td><div class="linea">Entregó</div></td>
            <td><div class="linea">Recibí conforme · {{ $liquidacion->user?->name }}</div></td>
        </tr>
    </table>
@endif
@endsection
