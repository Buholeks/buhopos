<?php

namespace App\Exportaciones;

use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SeriesExportacion extends DefaultValueBinder implements FromQuery, WithHeadings, WithMapping, WithCustomValueBinder, WithStyles, ShouldAutoSize, WithStrictNullComparison, WithColumnFormatting
{
    public const COLUMNAS = ['serie' => 'Serie', 'imei' => 'IMEI', 'producto' => 'Producto', 'categoria' => 'Categoría',
        'fecha_compra' => 'Fecha de compra', 'folio_compra' => 'Folio de compra', 'proveedor' => 'Proveedor', 'costo_compra' => 'Costo de compra',
        'fecha_venta' => 'Fecha de venta', 'folio_venta' => 'Folio de venta', 'cliente' => 'Cliente', 'sucursal' => 'Sucursal de venta',
        'importe_venta' => 'Venta después de descuentos', 'estado_operacion' => 'Estado'];

    public function __construct(private Builder $consulta, private array $columnas) {}
    public function query(): Builder { return clone $this->consulta; }
    public function headings(): array { return array_map(fn ($c) => self::COLUMNAS[$c], $this->columnas); }
    public function map($row): array
    {
        return array_map(function ($c) use ($row) {
            $valor = $row->$c ?? null;
            if (in_array($c, ['costo_compra', 'importe_venta'])) return $valor !== null ? (float) $valor : null;
            if (in_array($c, ['fecha_compra', 'fecha_venta'])) return $valor ? \Carbon\Carbon::parse($valor)->format('d/m/Y') : '';
            if ($c === 'estado_operacion') return ['vigente' => 'Vendido', 'cancelada' => 'Cancelada', 'devuelta' => 'Con devolución'][$valor] ?? $valor;
            return $valor ?? '';
        }, $this->columnas);
    }
    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value)) { $cell->setValueExplicit($value, DataType::TYPE_STRING); return true; }
        return parent::bindValue($cell, $value);
    }
    public function columnFormats(): array
    {
        $formatos = [];
        foreach ($this->columnas as $indice => $columna) {
            if (in_array($columna, ['costo_compra', 'importe_venta'])) {
                $formatos[Coordinate::stringFromColumnIndex($indice + 1)] = '"$"#,##0.00';
            }
        }
        return $formatos;
    }
    public function styles(Worksheet $sheet)
    {
        $sheet->freezePane('A2');
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
        return [1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0F766E']]]];
    }
}
