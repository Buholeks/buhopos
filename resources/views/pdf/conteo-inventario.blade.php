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
    .faltante    { color: #dc2626; font-weight: bold; }
    .sobrante    { color: #d97706; font-weight: bold; }
    .completo    { color: #16a34a; }
    .badge {
        display: inline;
        padding: 2px 5px;
        border-radius: 3px;
        font-size: 7.5px;
        font-weight: bold;
    }
    .badge-completo        { background:#d1fae5; color:#065f46; }
    .badge-faltante        { background:#fee2e2; color:#991b1b; }
    .badge-sobrante        { background:#fef3c7; color:#92400e; }
    .badge-no_contado      { background:#f1f5f9; color:#475569; }
    .badge-nuevo_encontrado{ background:#ede9fe; color:#5b21b6; }
    .notas-box {
        background:#fffbeb;border:1px solid #fcd34d;
        padding:5px 8px;font-size:8px;color:#92400e;margin-bottom:10px;
    }
    .seccion-titulo {
        font-size:10px;font-weight:bold;color:#1E3A5F;
        border-bottom:2px solid #1E3A5F;padding-bottom:3px;
        margin-bottom:8px;margin-top:14px;
    }
    .firma-box {
        margin-top: 24px;
        width: 100%;
        border-collapse: collapse;
    }
    .firma-box td {
        width: 33.33%;
        padding: 40px 16px 6px;
        border-top: 1px solid #1a1a1a;
        text-align: center;
        font-size: 8px;
        color: #475569;
    }
</style>

@php
    $fmt = fn($v) => '$' . number_format((float)$v, 2, '.', ',');
    $num = fn($v) => number_format((float)$v, 2, '.', ',');
    $c   = $conteo;

    $estadoLabel = [
        'en_conteo'   => 'En captura',
        'en_revision' => 'En revisión',
        'ajustado'    => 'Ajustado',
        'cancelado'   => 'Cancelado',
    ][$c['estado']] ?? $c['estado'];

    $estadoColor = [
        'en_conteo'   => '#0369a1',
        'en_revision' => '#d97706',
        'ajustado'    => '#16a34a',
        'cancelado'   => '#dc2626',
    ][$c['estado']] ?? '#475569';

    $alcance = $c['alcance_tipo'] === 'total'
        ? 'Inventario completo'
        : (($c['alcance_tipo'] === 'categoria' ? 'Categoría' : 'Marca') . ': ' . ($c['alcance_nombre'] ?? $c['alcance_id']));

    $mostrarDiferencias = in_array($c['estado'], ['en_revision', 'ajustado']);
    $r = $c['resumen'];
@endphp

{{-- ── Encabezado del conteo ──────────────────────────────────────────── --}}
<table class="meta-grid">
    <tr>
        <td>
            <span class="label">Folio</span>
            <span class="valor">{{ $c['folio'] }}</span>
        </td>
        <td>
            <span class="label">Estado</span>
            <span class="valor" style="color:{{ $estadoColor }}">{{ $estadoLabel }}</span>
        </td>
        <td>
            <span class="label">Alcance</span>
            <span class="valor">{{ $alcance }}</span>
        </td>
        <td>
            <span class="label">Snapshot</span>
            <span class="valor">{{ $c['snapshot_at'] ? \Carbon\Carbon::parse($c['snapshot_at'])->setTimezone('America/Mexico_City')->format('d/m/Y H:i') : '—' }}</span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="label">Responsable</span>
            <span class="valor">{{ $c['responsable'] ?? '—' }}</span>
        </td>
        <td>
            <span class="label">Sucursal</span>
            <span class="valor">{{ $c['sucursal'] ?? '—' }}</span>
        </td>
        <td>
            <span class="label">Cerrado</span>
            <span class="valor">{{ $c['cerrado_at'] ? \Carbon\Carbon::parse($c['cerrado_at'])->setTimezone('America/Mexico_City')->format('d/m/Y H:i') : '—' }}</span>
        </td>
        <td>
            <span class="label">Ajustado</span>
            <span class="valor">{{ $c['ajustado_at'] ? \Carbon\Carbon::parse($c['ajustado_at'])->setTimezone('America/Mexico_City')->format('d/m/Y H:i') : '—' }}</span>
        </td>
    </tr>
</table>

@if ($c['notas'])
<div class="notas-box"><strong>Notas:</strong> {{ $c['notas'] }}</div>
@endif

{{-- ── Resumen ─────────────────────────────────────────────────────────── --}}
<table class="resumen-grid">
    <tr>
        <td><span class="label">Partidas contadas</span><span class="valor">{{ $r['contadas'] ?? '—' }}</span></td>
        <td><span class="label">Piezas físicas</span><span class="valor">{{ $num($r['piezas_fisicas'] ?? 0) }}</span></td>
        @if ($mostrarDiferencias)
        <td><span class="label">Piezas sistema</span><span class="valor">{{ $num($r['piezas_sistema'] ?? 0) }}</span></td>
        <td><span class="label">Diferencias</span><span class="valor {{ ($r['diferencias'] ?? 0) > 0 ? 'faltante' : '' }}">{{ $r['diferencias'] ?? 0 }}</span></td>
        <td><span class="label">Faltantes</span><span class="valor faltante">{{ $r['faltantes'] ?? 0 }}</span></td>
        <td><span class="label">Sobrantes</span><span class="valor sobrante">{{ $r['sobrantes'] ?? 0 }}</span></td>
        <td><span class="label">Valor diferencia</span><span class="valor">{{ $fmt($r['valor_diferencia'] ?? 0) }}</span></td>
        @else
        <td colspan="5"><span class="label">Total en snapshot</span><span class="valor">{{ $r['total_snapshot'] ?? '—' }}</span></td>
        @endif
    </tr>
</table>

{{-- ── Tabla de partidas ───────────────────────────────────────────────── --}}
<div class="seccion-titulo">
    {{ $mostrarDiferencias ? 'Revisión de diferencias' : 'Partidas capturadas' }}
    ({{ count($c['detalles']) }})
</div>

@if ($mostrarDiferencias)
<table class="datos">
    <thead>
        <tr>
            <th>Producto</th>
            <th>Variante / SKU</th>
            <th class="text-right">Físico</th>
            <th class="text-right">Sistema</th>
            <th class="text-right">Diferencia</th>
            <th>Estado</th>
            <th class="text-right">Costo</th>
            <th class="text-right">Valor dif.</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($c['detalles'] as $d)
        <tr>
            <td>{{ $d['nombre'] }}<br><span style="font-size:7.5px;color:#64748b;">{{ $d['codigo'] ?? '' }}</span></td>
            <td>{{ $d['nombre_variante'] ?: 'Base' }}@if($d['sku'])<br><span style="font-size:7.5px;color:#64748b;">{{ $d['sku'] }}</span>@endif</td>
            <td class="text-right">{{ $num($d['cantidad_fisica']) }}</td>
            <td class="text-right">{{ $num($d['stock_sistema']) }}</td>
            @php $dif = (float)$d['diferencia']; @endphp
            <td class="text-right {{ $dif > 0 ? 'sobrante' : ($dif < 0 ? 'faltante' : 'completo') }}">
                {{ $dif > 0 ? '+' : '' }}{{ $num($dif) }}
            </td>
            <td><span class="badge badge-{{ $d['estado'] }}">{{ ['completo'=>'Completo','faltante'=>'Faltante','sobrante'=>'Sobrante','no_contado'=>'No contado','nuevo_encontrado'=>'Nuevo'][$d['estado']] ?? $d['estado'] }}</span></td>
            <td class="text-right">{{ isset($d['costo_unitario']) ? $fmt($d['costo_unitario']) : '—' }}</td>
            <td class="text-right {{ $dif < 0 ? 'faltante' : ($dif > 0 ? 'sobrante' : '') }}">
                {{ isset($d['costo_unitario']) ? $fmt(abs($dif) * (float)$d['costo_unitario']) : '—' }}
            </td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="2">TOTALES</td>
            <td class="text-right">{{ $num($r['piezas_fisicas'] ?? 0) }}</td>
            <td class="text-right">{{ $num($r['piezas_sistema'] ?? 0) }}</td>
            <td></td>
            <td></td>
            <td></td>
            <td class="text-right">{{ $fmt($r['valor_diferencia'] ?? 0) }}</td>
        </tr>
    </tfoot>
</table>
@else
<table class="datos">
    <thead>
        <tr>
            <th>Producto</th>
            <th>Variante / SKU</th>
            <th class="text-right">Cantidad física</th>
            <th>Series / IMEI</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($c['detalles'] as $d)
        <tr>
            <td>{{ $d['nombre'] }}<br><span style="font-size:7.5px;color:#64748b;">{{ $d['codigo'] ?? '' }}</span></td>
            <td>{{ $d['nombre_variante'] ?: 'Base' }}@if($d['sku'])<br><span style="font-size:7.5px;color:#64748b;">{{ $d['sku'] }}</span>@endif</td>
            <td class="text-right">{{ $num($d['cantidad_fisica']) }}</td>
            <td>{{ implode(', ', $d['series_contadas'] ?? []) ?: '—' }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="2">TOTAL</td>
            <td class="text-right">{{ $num($r['piezas_fisicas'] ?? 0) }}</td>
            <td></td>
        </tr>
    </tfoot>
</table>
@endif

{{-- ── Firmas ──────────────────────────────────────────────────────────── --}}
<table class="firma-box">
    <tr>
        <td>Elaboró<br>{{ $conteo['responsable'] ?? '________________' }}</td>
        <td>Supervisó<br>________________</td>
        <td>Autorizó<br>________________</td>
    </tr>
</table>

@endsection
