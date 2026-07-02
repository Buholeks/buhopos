<?php

namespace App\Exportaciones;

use App\Models\CorteCaja;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CajaExportacion extends ExportacionBase
{
    public function __construct(
        private readonly int   $empresaId,
        private readonly int   $sucursalId,
        private readonly array $filtros = []
    ) {}

    public function titulo(): string
    {
        return 'Consulta de Caja — Cortes';
    }

    public function empresaId(): ?int
    {
        return $this->empresaId;
    }

    public function sucursalId(): ?int
    {
        return $this->sucursalId;
    }

    public function filtrosAplicados(): array
    {
        $aplicados = [];

        if (!empty($this->filtros['fecha_desde'])) {
            $aplicados['Desde'] = Carbon::parse($this->filtros['fecha_desde'])->format('d/m/Y');
        }
        if (!empty($this->filtros['fecha_hasta'])) {
            $aplicados['Hasta'] = Carbon::parse($this->filtros['fecha_hasta'])->format('d/m/Y');
        }
        if (!empty($this->filtros['user_id'])) {
            $nombre = User::find($this->filtros['user_id'])?->name;
            $aplicados['Cajero'] = $nombre ?? "ID {$this->filtros['user_id']}";
        }
        if (!empty($this->filtros['estado'])) {
            $aplicados['Estado'] = ucfirst($this->filtros['estado']);
        }

        return $aplicados;
    }

    public function cabeceras(): array
    {
        return [
            'Fecha Apertura',
            'Cajero',
            'Estado',
            'Ventas',
            'Efectivo',
            'Tarjeta',
            'Transferencia',
            'Total Ventas',
            'Movs. Efectivo',
            'Dif. Efectivo',
            'Dif. Tarjeta',
            'Dif. Transferencia',
        ];
    }

    private ?array $totalesCache = null;

    public function totales(): ?array
    {
        return $this->totalesCache;
    }

    public function datos(): Collection
    {
        $coleccion = CorteCaja::query()
            ->where('empresa_id', $this->empresaId)
            ->where('sucursal_id', $this->sucursalId)
            ->with(['user:id,name'])
            ->when(
                !empty($this->filtros['fecha_desde']),
                fn($q) => $q->where(
                    'fecha_apertura',
                    '>=',
                    Carbon::parse($this->filtros['fecha_desde'], 'America/Mexico_City')->startOfDay()->utc()
                )
            )
            ->when(
                !empty($this->filtros['fecha_hasta']),
                fn($q) => $q->where(
                    'fecha_apertura',
                    '<=',
                    Carbon::parse($this->filtros['fecha_hasta'], 'America/Mexico_City')->endOfDay()->utc()
                )
            )
            ->when(
                !empty($this->filtros['user_id']),
                fn($q) => $q->where('user_id', $this->filtros['user_id'])
            )
            ->when(
                !empty($this->filtros['estado']),
                fn($q) => $q->where('estado', $this->filtros['estado'])
            )
            ->orderByDesc('fecha_apertura')
            ->get();

        $totalVentasGlobal = $coleccion->sum(
            fn($c) =>
            (float) $c->ventas_efectivo + (float) $c->ventas_tarjeta +
                (float) $c->ventas_transferencia
        );

        $this->totalesCache = [
            'Totales',
            '',
            '',
            (int) $coleccion->sum('num_ventas'),
            number_format((float) $coleccion->sum('ventas_efectivo'), 2),
            number_format((float) $coleccion->sum('ventas_tarjeta'), 2),
            number_format((float) $coleccion->sum('ventas_transferencia'), 2),
            number_format($totalVentasGlobal, 2),
            number_format((float) $coleccion->sum('movs_efectivo'), 2),
            number_format((float) $coleccion->sum('dif_efectivo'), 2),
            number_format((float) $coleccion->sum('dif_tarjeta'), 2),
            number_format((float) $coleccion->sum('dif_transferencia'), 2),
        ];

        return $coleccion->map(fn($c) => [
            $c->fecha_apertura->copy()->setTimezone('America/Mexico_City')->format('d/m/Y H:i'),
            $c->user?->name ?? '—',
            ucfirst($c->estado),
            (int) $c->num_ventas,
            number_format((float) $c->ventas_efectivo, 2),
            number_format((float) $c->ventas_tarjeta, 2),
            number_format((float) $c->ventas_transferencia, 2),
            number_format(
                (float) $c->ventas_efectivo + (float) $c->ventas_tarjeta +
                    (float) $c->ventas_transferencia,
                2
            ),
            number_format((float) $c->movs_efectivo, 2),
            number_format((float) $c->dif_efectivo, 2),
            number_format((float) $c->dif_tarjeta, 2),
            number_format((float) $c->dif_transferencia, 2),
        ]);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18, // Fecha Apertura
            'B' => 22, // Cajero
            'C' => 13, // Estado
            'D' => 10, // Ventas
            'E' => 14, // Efectivo
            'F' => 14, // Tarjeta
            'G' => 16, // Transferencia
            'H' => 15, // Total Ventas
            'I' => 16, // Movs. Efectivo
            'J' => 15, // Dif. Efectivo
            'K' => 13, // Dif. Tarjeta
            'L' => 18, // Dif. Transferencia
        ];
    }
}
