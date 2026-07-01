<?php

namespace App\Exportaciones;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class VentasAgrupadoExportacion extends ExportacionBase
{
    public function __construct(
        private readonly int        $empresaId,
        private readonly int        $sucursalId,
        private readonly string     $titulo,
        private readonly array      $cabeceras,
        private readonly Collection $coleccion,
        private readonly ?array     $filaTotales = null,
        private readonly array      $filtros = [],
    ) {}

    public function titulo(): string   { return $this->titulo; }
    public function empresaId(): ?int  { return $this->empresaId; }
    public function sucursalId(): ?int { return $this->sucursalId; }

    public function filtrosAplicados(): array
    {
        $r = [];
        if (!empty($this->filtros['fecha_desde'])) {
            $r['Desde'] = Carbon::parse($this->filtros['fecha_desde'])->format('d/m/Y');
        }
        if (!empty($this->filtros['fecha_hasta'])) {
            $r['Hasta'] = Carbon::parse($this->filtros['fecha_hasta'])->format('d/m/Y');
        }
        if (!empty($this->filtros['forma_pago'])) {
            $r['Forma de pago'] = ucfirst($this->filtros['forma_pago']);
        }
        if (!empty($this->filtros['estado'])) {
            $r['Estado'] = ucfirst($this->filtros['estado']);
        }
        return $r;
    }

    public function cabeceras(): array   { return $this->cabeceras; }
    public function totales(): ?array    { return $this->filaTotales; }
    public function datos(): Collection  { return $this->coleccion; }
}
