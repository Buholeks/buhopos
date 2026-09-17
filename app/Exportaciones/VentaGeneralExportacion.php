<?php

namespace App\Exportaciones;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;

class VentaGeneralExportacion extends ExportacionBase implements WithColumnFormatting
{
    public function __construct(private int $empresa, private array $reporte, private string $formato) {}
    public function titulo(): string { return 'Venta general'; }
    public function empresaId(): ?int { return $this->empresa; }
    public function cabeceras(): array { return ['Sucursal', 'Ventas ($)', 'Descuentos incluidos ($)', 'Devoluciones ($)', 'Venta neta ($)', 'Canceladas ($)', 'Participación (%)']; }
    public function filtrosAplicados(): array
    {
        return ['Desde' => $this->reporte['filtros']['fecha_desde'], 'Hasta' => $this->reporte['filtros']['fecha_hasta'],
            'Sucursales' => empty($this->reporte['filtros']['sucursal_ids']) ? 'Todas' : $this->reporte['datos']->pluck('sucursal')->implode(', '),
            'Criterio' => 'Ventas confirmadas después de descuentos. Devoluciones por fecha de devolución. Canceladas excluidas, por fecha de venta.'];
    }
    private function fila(array $f, string $nombre, $participacion): array
    {
        $montos = array_map(fn ($k) => $this->formato === 'pdf' ? '$'.number_format($f[$k], 2) : $f[$k], ['ventas', 'descuentos', 'devoluciones', 'neta', 'canceladas']);
        return [$nombre, ...$montos, $participacion ?? '—'];
    }
    public function datos(): Collection
    {
        return $this->reporte['datos']->map(fn ($f) => $this->fila($f, $f['sucursal'], $f['participacion']));
    }
    public function totales(): ?array
    {
        $valida = $this->reporte['datos']->contains(fn ($f) => $f['participacion'] !== null);
        return $this->fila($this->reporte['totales'], 'TOTAL GENERAL', $valida ? 100 : null);
    }
    public function columnFormats(): array { return array_fill_keys(['B', 'C', 'D', 'E', 'F'], '"$"#,##0.00;[Red]-"$"#,##0.00'); }
    public function registerEvents(): array
    {
        $events = parent::registerEvents();
        $key = \Maatwebsite\Excel\Events\AfterSheet::class;
        $base = $events[$key];
        $events[$key] = function ($event) use ($base) {
            $base($event);
            $sheet = $event->sheet->getDelegate();
            $row = $sheet->getHighestRow() + 2;
            foreach ($this->filtrosAplicados() as $label => $value) {
                $sheet->setCellValueExplicit('A'.$row, $label.': '.$value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->mergeCells('A'.$row.':G'.$row);
                $sheet->getStyle('A'.$row)->getAlignment()->setWrapText(true);
                $sheet->getRowDimension($row)->setRowHeight(32);
                $row++;
            }
        };
        return $events;
    }
}
