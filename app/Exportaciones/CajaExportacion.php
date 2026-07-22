<?php

namespace App\Exportaciones;

use App\Models\CorteCaja;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CajaExportacion extends ExportacionBase
{
    public function __construct(
        private readonly int $empresaId,
        private readonly int $sucursalId,
        private readonly array $filtros = []
    ) {}

    public function titulo(): string
    {
        return 'Consulta de Caja - Cortes';
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
            'Corte',
            'Cajero',
            'Apertura',
            'Cierre',
            'Estado',
            'Esperado Efectivo',
            'Dif. Efectivo',
            'Contado Efectivo',
            'Cobrado Caja',
            'Total Vendido',
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
                fn ($q) => $q->where(
                    'fecha_apertura',
                    '>=',
                    Carbon::parse($this->filtros['fecha_desde'], 'America/Mexico_City')->startOfDay()->utc()
                )
            )
            ->when(
                !empty($this->filtros['fecha_hasta']),
                fn ($q) => $q->where(
                    'fecha_apertura',
                    '<=',
                    Carbon::parse($this->filtros['fecha_hasta'], 'America/Mexico_City')->endOfDay()->utc()
                )
            )
            ->when(
                !empty($this->filtros['user_id']),
                fn ($q) => $q->where('user_id', $this->filtros['user_id'])
            )
            ->when(
                !empty($this->filtros['estado']),
                fn ($q) => $q->where('estado', $this->filtros['estado'])
            )
            ->orderByDesc('fecha_apertura')
            ->get();

        $coleccion->where('estado', 'abierto')->each(function (CorteCaja $corte) {
            $corte->recalcularVentas();
            $corte->recalcularMovimientos();
            $corte->refresh();
        });

        $totalCobradoCajaGlobal = $coleccion->sum(fn ($corte) => $this->totalCobradoCaja($corte));
        $totalVendidoGlobal = $coleccion->sum(fn ($corte) => $this->totalVendido($corte));

        $this->totalesCache = [
            'Totales',
            '',
            '',
            '',
            '',
            $this->formatoImporte((float) $coleccion->sum('esperado_efectivo')),
            $this->formatoImporte((float) $coleccion->sum('dif_efectivo')),
            $this->formatoImporte((float) $coleccion->sum('contado_efectivo')),
            $this->formatoImporte((float) $totalCobradoCajaGlobal),
            $this->formatoImporte((float) $totalVendidoGlobal),
        ];

        return $coleccion->map(fn ($corte) => [
            $corte->id,
            $corte->user?->name ?? 'Sin cajero',
            $this->formatoFechaHora($this->fechaAperturaExportacion($corte), 'Sin fecha'),
            $this->formatoFechaHora($corte->fecha_cierre, 'Sin cierre'),
            ucfirst($corte->estado),
            $this->formatoImporte((float) $corte->esperado_efectivo),
            $this->formatoImporte((float) $corte->dif_efectivo),
            $this->formatoImporte((float) $corte->contado_efectivo),
            $this->formatoImporte($this->totalCobradoCaja($corte)),
            $this->formatoImporte($this->totalVendido($corte)),
        ]);
    }

    private function fechaAperturaExportacion(CorteCaja $corte): ?Carbon
    {
        if (
            $corte->fecha_apertura &&
            $corte->fecha_cierre &&
            $corte->created_at &&
            $corte->fecha_apertura->equalTo($corte->fecha_cierre) &&
            ! $corte->created_at->equalTo($corte->fecha_cierre)
        ) {
            return $corte->created_at;
        }

        return $corte->fecha_apertura;
    }

    private function formatoFechaHora(?Carbon $fecha, string $vacio): string
    {
        return $fecha
            ? $fecha->copy()->setTimezone('America/Mexico_City')->format('d/m/Y H:i')
            : $vacio;
    }

    private function totalCobradoCaja(CorteCaja $corte): float
    {
        return (float) $corte->ventas_efectivo
            + (float) $corte->ventas_tarjeta
            + (float) $corte->ventas_transferencia;
    }

    private function totalVendido(CorteCaja $corte): float
    {
        return $this->totalCobradoCaja($corte) + (float) $corte->ventas_saldo_favor;
    }

    private function formatoImporte(float $monto): string
    {
        return number_format($monto, 2);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10, // Corte
            'B' => 22, // Cajero
            'C' => 18, // Apertura
            'D' => 18, // Cierre
            'E' => 13, // Estado
            'F' => 18, // Esperado Efectivo
            'G' => 15, // Dif. Efectivo
            'H' => 18, // Contado Efectivo
            'I' => 15, // Cobrado Caja
            'J' => 15, // Total Vendido
        ];
    }
}
