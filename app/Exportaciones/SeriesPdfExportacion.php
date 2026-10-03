<?php

namespace App\Exportaciones;

use Illuminate\Support\Collection;

class SeriesPdfExportacion extends ExportacionBase
{
    public function __construct(
        private readonly int $empresa,
        private readonly ?int $sucursal,
        private readonly SeriesExportacion $series,
        private readonly array $filtros,
    ) {}

    public function titulo(): string { return 'Reporte de series'; }
    public function empresaId(): ?int { return $this->empresa; }
    public function sucursalId(): ?int { return $this->sucursal; }
    public function cabeceras(): array { return $this->series->headings(); }
    public function filtrosAplicados(): array { return $this->filtros; }

    public function datos(): Collection
    {
        return $this->series->query()->get()->map(fn ($fila) => array_map(
            fn ($valor) => is_float($valor) ? '$'.number_format($valor, 2, '.', ',') : ($valor === null || $valor === '' ? '—' : $valor),
            $this->series->map($fila),
        ));
    }

    public function anchosParaPdf(): array
    {
        $anchos = ['Serie' => 7, 'IMEI' => 8, 'Producto' => 12, 'Categoría' => 7, 'Fecha de compra' => 6,
            'Folio de compra' => 6, 'Proveedor' => 9, 'Costo de compra' => 6, 'Fecha de venta' => 6,
            'Folio de venta' => 6, 'Cliente' => 8, 'Sucursal de venta' => 8, 'Venta después de descuentos' => 6, 'Estado' => 5];
        return array_map(fn ($cabecera) => $anchos[$cabecera], $this->cabeceras());
    }
}
