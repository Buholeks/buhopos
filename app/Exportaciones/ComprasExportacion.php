<?php

namespace App\Exportaciones;

use App\Models\Proveedor;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ComprasExportacion extends ExportacionBase
{
    public function __construct(
        private readonly int    $empresaId,
        private readonly int    $sucursalId,
        private readonly array  $filtros = []
    ) {}

    public function titulo(): string
    {
        return 'Reporte de Compras';
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

        if (!empty($this->filtros['fecha_inicio'])) {
            $aplicados['Desde'] = Carbon::parse($this->filtros['fecha_inicio'])->format('d/m/Y');
        }
        if (!empty($this->filtros['fecha_fin'])) {
            $aplicados['Hasta'] = Carbon::parse($this->filtros['fecha_fin'])->format('d/m/Y');
        }
        if (!empty($this->filtros['proveedor_id'])) {
            $nombre = Proveedor::find($this->filtros['proveedor_id'])?->nombre_comercial;
            $aplicados['Proveedor'] = $nombre ?? "ID {$this->filtros['proveedor_id']}";
        }
        if (!empty($this->filtros['estado'])) {
            $aplicados['Estado'] = ucfirst($this->filtros['estado']);
        }
        if (!empty($this->filtros['forma_pago'])) {
            $aplicados['Forma de pago'] = ucfirst(str_replace('_', ' ', $this->filtros['forma_pago']));
        }

        return $aplicados;
    }

    public function cabeceras(): array
    {
        return [
            'Folio',
            'Fecha',
            'Proveedor',
            'Forma Pago',
            'Subtotal',
            'Total',
            'Pagado',
            'Saldo',
            'Estado',
        ];
    }

    private ?array $totalesCache = null;

    public function totales(): ?array
    {
        return $this->totalesCache;
    }

    public function datos(): Collection
    {
        $coleccion = DB::table('compras')
            ->leftJoin('proveedores', 'compras.proveedor_id', '=', 'proveedores.id')
            ->where('compras.empresa_id', $this->empresaId)
            ->where('compras.sucursal_id', $this->sucursalId)
            ->when(
                !empty($this->filtros['fecha_inicio']),
                fn ($q) => $q->whereDate('compras.fecha', '>=', $this->filtros['fecha_inicio'])
            )
            ->when(
                !empty($this->filtros['fecha_fin']),
                fn ($q) => $q->whereDate('compras.fecha', '<=', $this->filtros['fecha_fin'])
            )
            ->when(
                !empty($this->filtros['proveedor_id']),
                fn ($q) => $q->where('compras.proveedor_id', $this->filtros['proveedor_id'])
            )
            ->when(
                !empty($this->filtros['estado']),
                fn ($q) => $q->where('compras.estado', $this->filtros['estado'])
            )
            ->when(
                !empty($this->filtros['forma_pago']),
                fn ($q) => $q->where('compras.forma_pago', $this->filtros['forma_pago'])
            )
            ->select(
                'compras.folio',
                'compras.fecha',
                DB::raw("COALESCE(proveedores.nombre_comercial, '—') as proveedor"),
                'compras.forma_pago',
                'compras.subtotal',
                'compras.total',
                'compras.pagado',
                'compras.saldo',
                'compras.estado',
            )
            ->orderByDesc('compras.fecha')
            ->orderByDesc('compras.id')
            ->get();

        $this->totalesCache = [
            '',
            'Totales',
            '',
            '',
            number_format($coleccion->sum('subtotal'), 2),
            number_format($coleccion->sum('total'), 2),
            number_format($coleccion->sum('pagado'), 2),
            number_format($coleccion->sum('saldo'), 2),
            '',
        ];

        return $coleccion->map(fn ($c) => [
            $c->folio,
            $c->fecha,
            $c->proveedor,
            ucfirst(str_replace('_', ' ', $c->forma_pago)),
            number_format($c->subtotal, 2),
            number_format($c->total, 2),
            number_format($c->pagado, 2),
            number_format($c->saldo, 2),
            ucfirst($c->estado),
        ]);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12, // Folio
            'B' => 14, // Fecha
            'C' => 30, // Proveedor
            'D' => 16, // Forma Pago
            'E' => 14, // Subtotal
            'F' => 14, // Total
            'G' => 14, // Pagado
            'H' => 14, // Saldo
            'I' => 14, // Estado
        ];
    }
}
