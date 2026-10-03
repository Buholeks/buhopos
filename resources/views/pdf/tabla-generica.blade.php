@extends('pdf.layout')

@section('contenido')

@php
    $anchosPdf = $anchosPdf ?? [];
    $compacta = !empty($anchosPdf);
@endphp

<style>
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 9px;
    }
    thead tr th {
        background-color: #1E3A5F;
        color: white;
        padding: 6px 8px;
        text-align: left;
        font-weight: bold;
        border: 1px solid #163050;
    }
    tbody tr td {
        padding: 5px 8px;
        border: 1px solid #dde3ec;
    }
    tbody tr:nth-child(even) td {
        background-color: #f0f4f8;
    }
    tbody tr:nth-child(odd) td {
        background-color: #ffffff;
    }
    .sin-datos {
        text-align: center;
        padding: 20px;
        color: #888;
        font-style: italic;
    }
    tfoot tr td {
        background-color: #d9e2ec;
        color: #1E3A5F;
        font-weight: bold;
        padding: 6px 8px;
        border: 1px solid #b8c6d6;
    }
</style>

@if ($compacta)
<style>
    .tabla-reporte { table-layout: fixed; }
    .tabla-reporte thead { display: table-header-group; }
    .tabla-reporte tr { page-break-inside: avoid; }
    .tabla-reporte thead tr th { font-size: 7px; padding: 5px 3px; word-wrap: break-word; }
    .tabla-reporte tbody tr td { font-size: 7px; padding: 4px 3px; line-height: 1.4; word-wrap: break-word; }
</style>
@endif

<table class="tabla-reporte">
    <thead>
        <tr>
            @foreach ($cabeceras as $indice => $cabecera)
                <th @if ($compacta) style="width: {{ $anchosPdf[$indice] / array_sum($anchosPdf) * 100 }}%" @endif>{{ $cabecera }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $fila)
            <tr>
                @foreach ((array) $fila as $celda)
                    <td>{{ $celda }}</td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td class="sin-datos" colspan="{{ count($cabeceras) }}">
                    Sin datos para mostrar
                </td>
            </tr>
        @endforelse
    </tbody>
    @if (!empty($totales))
        <tfoot>
            <tr>
                @foreach ($totales as $valor)
                    <td>{{ $valor }}</td>
                @endforeach
            </tr>
        </tfoot>
    @endif
</table>

@endsection
