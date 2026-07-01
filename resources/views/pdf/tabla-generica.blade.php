@extends('pdf.layout')

@section('contenido')

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

<table>
    <thead>
        <tr>
            @foreach ($cabeceras as $cabecera)
                <th>{{ $cabecera }}</th>
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
