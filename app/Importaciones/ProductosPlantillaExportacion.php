<?php

namespace App\Importaciones;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductosPlantillaExportacion implements FromCollection, WithHeadings, WithStyles, WithTitle, WithColumnWidths
{
    public function collection(): Collection
    {
        return collect([
            [
                'Cargador rapido USB-C 25W',
                'PROD-CARG-25W',
                'Cargador de pared compatible con USB-C',
                'Accesorios > Cargadores > USB-C',
                'Generica',
                '',
                'Pieza',
                'PZA',
                80,
                149,
                '',
                '',
                '',
                '',
                '',
                2,
                0.12,
                1,
                0,
                0,
                10,
            ],
        ]);
    }

    public function headings(): array
    {
        return [
            'nombre',
            'codigo',
            'descripcion',
            'categoria',
            'marca',
            'modelo',
            'unidad_medida',
            'unidad_abreviatura',
            'precio_costo',
            'precio_venta',
            'precio1',
            'precio2',
            'precio3',
            'precio4',
            'precio5',
            'stock_minimo',
            'peso',
            'activo',
            'tiene_series',
            'pedido_generico',
            'stock_inicial',
        ];
    }

    public function title(): string
    {
        return 'productos';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 34,
            'B' => 22,
            'C' => 42,
            'D' => 42,
            'E' => 22,
            'F' => 22,
            'G' => 22,
            'H' => 20,
            'I' => 16,
            'J' => 16,
            'K' => 14,
            'L' => 14,
            'M' => 14,
            'N' => 14,
            'O' => 14,
            'P' => 16,
            'Q' => 12,
            'R' => 12,
            'S' => 14,
            'T' => 18,
            'U' => 16,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF047857'],
                ],
            ],
        ];
    }
}
