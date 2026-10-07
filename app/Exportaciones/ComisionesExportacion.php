<?php

namespace App\Exportaciones;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

/**
 * Reporte de comisiones por empleado (mismo contenido que la tabla de la pantalla).
 * En Excel los montos van como números con formato de moneda; en PDF, como texto.
 */
class ComisionesExportacion extends ExportacionBase implements WithColumnFormatting
{
    /**
     * @param array{empleados: array, totales: array} $reporte resultado de ComisionReporte::resumen()
     */
    public function __construct(
        private readonly int $empresa,
        private readonly array $reporte,
        private readonly array $filtros,
        private readonly ?string $sucursalNombre,
        private readonly string $formato = 'pdf',
    ) {}

    public function titulo(): string { return 'Reporte de comisiones'; }
    public function empresaId(): ?int { return $this->empresa; }
    public function sucursalId(): ?int { return $this->filtros['sucursal_id'] ?? null; }

    public function cabeceras(): array
    {
        return ['Empleado', 'Tickets vendedor', 'Como vendedor', 'Tickets cajero', 'Como cajero',
            'Devoluciones / cancelaciones', 'Ajustes', 'Total', 'Pagado', 'Pendiente'];
    }

    public function anchosParaPdf(): array
    {
        return [16, 7, 10, 7, 10, 11, 9, 10, 10, 10];
    }

    public function columnWidths(): array
    {
        return ['A' => 30, 'B' => 12, 'C' => 16, 'D' => 12, 'E' => 16, 'F' => 18, 'G' => 14, 'H' => 16, 'I' => 16, 'J' => 16];
    }

    public function columnFormats(): array
    {
        return array_fill_keys(['C', 'E', 'F', 'G', 'H', 'I', 'J'], '"$"#,##0.00;[Red]-"$"#,##0.00');
    }

    public function filtrosAplicados(): array
    {
        $fecha = fn(string $f) => Carbon::parse($f)->format('d/m/Y');

        return [
            'Periodo'  => $fecha($this->filtros['desde']) . ' – ' . $fecha($this->filtros['hasta']),
            'Sucursal' => $this->sucursalNombre ?? 'Todas las autorizadas',
            'Criterio' => 'Comisiones por fecha de venta; devoluciones y cancelaciones por fecha de registro. Pagado = liquidaciones pagadas.',
        ];
    }

    public function datos(): Collection
    {
        return collect($this->reporte['empleados'])->map(fn(array $e) => $this->fila($e, $e['nombre'], $e['tickets_vendedor'], $e['tickets_cajero']));
    }

    public function totales(): ?array
    {
        $t = $this->reporte['totales'];
        $empleados = collect($this->reporte['empleados']);

        return $this->fila($t, "TOTAL ({$t['empleados']} empleados)", $empleados->sum('tickets_vendedor'), $empleados->sum('tickets_cajero'));
    }

    /** En Excel agrega los filtros debajo de la tabla, como Venta general. */
    public function registerEvents(): array
    {
        $eventos = parent::registerEvents();
        $base = $eventos[AfterSheet::class];

        $eventos[AfterSheet::class] = function (AfterSheet $evento) use ($base) {
            $base($evento);
            $hoja = $evento->sheet->getDelegate();
            $fila = $hoja->getHighestRow() + 2;
            foreach ($this->filtrosAplicados() as $etiqueta => $valor) {
                $hoja->setCellValueExplicit("A{$fila}", "{$etiqueta}: {$valor}", DataType::TYPE_STRING);
                $hoja->mergeCells("A{$fila}:J{$fila}");
                $hoja->getStyle("A{$fila}")->getAlignment()->setWrapText(true);
                $fila++;
            }
        };

        return $eventos;
    }

    private function fila(array $f, string $nombre, int $ticketsVendedor, int $ticketsCajero): array
    {
        $m = $this->formato === 'pdf'
            ? fn(string $k) => ((float) $f[$k] < 0 ? '-$' : '$') . number_format(abs((float) $f[$k]), 2)
            : fn(string $k) => round((float) $f[$k], 2);

        return [$nombre, $ticketsVendedor, $m('comision_vendedor'), $ticketsCajero, $m('comision_cajero'),
            $m('descuentos'), $m('ajustes'), $m('total'), $m('pagado'), $m('pendiente')];
    }
}
