<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #1a1a1a;
        }

        /* ── Encabezado ────────────────────────────────────────── */
        .encabezado {
            background-color: #1E3A5F;
            color: white;
            padding: 12px 16px;
            margin-bottom: 10px;
        }
        .encabezado table {
            width: 100%;
            border-collapse: collapse;
            border: none;
            background-color: transparent;
        }
        .encabezado td {
            border: none;
            background-color: transparent;
        }
        .encabezado .celda-logo {
            width: 60px;
            vertical-align: middle;
            background-color: transparent;
        }
        .encabezado .celda-logo img {
            max-width: 50px;
            max-height: 50px;
        }
        .encabezado .celda-datos {
            vertical-align: middle;
            background-color: transparent;
        }
        .encabezado .empresa {
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 0.5px;
            color: #ffffff;
        }
        .encabezado .sucursal {
            font-size: 10px;
            margin-top: 2px;
            color: #dce6f0;
        }
        .encabezado .direccion {
            font-size: 8px;
            margin-top: 1px;
            color: #b7c8db;
        }
        .encabezado .celda-titulo {
            text-align: right;
            vertical-align: middle;
            background-color: transparent;
        }
        .encabezado .titulo-reporte {
            font-size: 12px;
            font-weight: bold;
            color: #ffffff;
        }
        .encabezado .meta {
            font-size: 8px;
            margin-top: 4px;
            color: #b7c8db;
        }

        /* ── Filtros aplicados ─────────────────────────────────── */
        .filtros {
            background-color: #f0f4f8;
            border: 1px solid #dde3ec;
            border-radius: 3px;
            padding: 6px 12px;
            margin: 0 16px 12px 16px;
            font-size: 8.5px;
            color: #44546a;
        }
        .filtros .etiqueta-filtros {
            font-weight: bold;
            color: #1E3A5F;
            margin-right: 6px;
        }
        .filtros .filtro-item {
            display: inline;
            margin-right: 14px;
        }
        .filtros .filtro-item b {
            color: #1a1a1a;
        }

        /* ── Contenido ─────────────────────────────────────────── */
        .contenido {
            padding: 0 16px;
        }

        /* ── Pie de página ─────────────────────────────────────── */
        .pie {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 6px 16px;
            font-size: 8px;
            color: #999;
            border-top: 1px solid #e0e0e0;
            text-align: right;
        }
    </style>
</head>
<body>

    <div class="encabezado">
        <table>
            <tr>
                @if ($empresaLogoB64)
                    <td class="celda-logo">
                        <img src="{{ $empresaLogoB64 }}" alt="Logo">
                    </td>
                @endif
                <td class="celda-datos">
                    <div class="empresa">{{ $empresaNombre }}</div>
                    @if ($sucursalNombre)
                        <div class="sucursal">Sucursal: {{ $sucursalNombre }}</div>
                    @endif
                    @if ($sucursalDireccion ?? $empresaDireccion)
                        <div class="direccion">{{ $sucursalDireccion ?? $empresaDireccion }}</div>
                    @endif
                </td>
                <td class="celda-titulo">
                    <div class="titulo-reporte">{{ $titulo }}</div>
                    <div class="meta">Generado el {{ $fecha }}</div>
                </td>
            </tr>
        </table>
    </div>

    @if (!empty($filtrosAplicados))
        <div class="filtros">
            <span class="etiqueta-filtros">Filtros aplicados:</span>
            @foreach ($filtrosAplicados as $etiqueta => $valor)
                <span class="filtro-item">{{ $etiqueta }}: <b>{{ $valor }}</b></span>
            @endforeach
        </div>
    @endif

    <div class="contenido">
        @yield('contenido')
    </div>

    <div class="pie">
        {{ $empresaNombre }} &mdash; {{ $titulo }} &mdash; {{ $fecha }}
    </div>

</body>
</html>
