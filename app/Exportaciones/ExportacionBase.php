<?php

namespace App\Exportaciones;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

abstract class ExportacionBase implements
    FromCollection,
    WithHeadings,
    WithStyles,
    WithTitle,
    WithColumnWidths,
    WithEvents
{
    // ── Cada subclase define estos métodos ───────────────────────────────────

    /** Título de la hoja / encabezado del PDF */
    abstract public function titulo(): string;

    /** Cabeceras de columnas */
    abstract public function cabeceras(): array;

    /** Datos a exportar como Collection */
    abstract public function datos(): Collection;

    /**
     * Vista Blade usada para el PDF.
     * Por defecto usa pdf.tabla-generica — sobreescribir si se necesita diseño propio.
     */
    public function vistaParaPdf(): string
    {
        return 'pdf.tabla-generica';
    }

    /**
     * Empresa a mostrar en el encabezado del PDF (logo, nombre, dirección).
     * Sobreescribir devolviendo el empresa_id correspondiente.
     */
    public function empresaId(): ?int
    {
        return null;
    }

    /**
     * Sucursal a mostrar en el encabezado del PDF (nombre, dirección).
     * Sobreescribir devolviendo el sucursal_id correspondiente.
     */
    public function sucursalId(): ?int
    {
        return null;
    }

    /**
     * Filtros aplicados, listos para mostrar en el PDF.
     * Formato: ['Etiqueta' => 'valor legible', ...]
     */
    public function filtrosAplicados(): array
    {
        return [];
    }

    /**
     * Fila de totales para el pie de la tabla (Excel y PDF).
     * Debe tener la misma cantidad de elementos que cabeceras().
     * Sobreescribir devolviendo un array; null = sin fila de totales.
     * Nota: solo está disponible después de invocar datos().
     */
    public function totales(): ?array
    {
        return null;
    }

    // ── Implementación de interfaces (no tocar en subclases) ─────────────────

    public function collection(): Collection
    {
        return $this->datos();
    }

    public function headings(): array
    {
        return $this->cabeceras();
    }

    public function title(): string
    {
        return $this->titulo();
    }

    /**
     * Anchos de columna por defecto — sobreescribir en subclase si se necesita.
     * Formato: ['A' => 20, 'B' => 30, ...]
     */
    public function columnWidths(): array
    {
        $anchos = [];
        $letras = range('A', 'Z');
        foreach ($this->cabeceras() as $i => $_) {
            $anchos[$letras[$i]] = 22;
        }
        return $anchos;
    }

    public function styles(Worksheet $hoja): array
    {
        return [
            // Fila de cabeceras en negrita con fondo azul oscuro
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 11],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF1E3A5F'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $hoja      = $event->sheet->getDelegate();
                $totalFils = $hoja->getHighestRow();
                $totalCols = $hoja->getHighestColumn();
                $rango     = "A1:{$totalCols}{$totalFils}";

                // Borde fino en todas las celdas
                $hoja->getStyle($rango)->getBorders()->getAllBorders()->setBorderStyle(
                    Border::BORDER_THIN
                );

                // Altura de la fila de cabeceras
                $hoja->getRowDimension(1)->setRowHeight(22);

                // Filas de datos: fondo alterno gris claro / blanco
                for ($fila = 2; $fila <= $totalFils; $fila++) {
                    $color = ($fila % 2 === 0) ? 'FFF0F4F8' : 'FFFFFFFF';
                    $hoja->getStyle("A{$fila}:{$totalCols}{$fila}")
                        ->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()
                        ->setARGB($color);
                }

                // Congelar primera fila
                $hoja->freezePane('A2');

                // Fila de totales (pie de tabla)
                $totales = $this->totales();
                if ($totales) {
                    $filaTotales = $totalFils + 1;
                    $col = 'A';
                    foreach ($totales as $valor) {
                        $hoja->setCellValue("{$col}{$filaTotales}", $valor);
                        $col++;
                    }
                    $rangoTotales = "A{$filaTotales}:{$totalCols}{$filaTotales}";
                    $hoja->getStyle($rangoTotales)->getFont()->setBold(true);
                    $hoja->getStyle($rangoTotales)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()
                        ->setARGB('FFD9E2EC');
                    $hoja->getStyle($rangoTotales)->getBorders()->getAllBorders()->setBorderStyle(
                        Border::BORDER_THIN
                    );
                }
            },
        ];
    }
}
