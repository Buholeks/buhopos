<?php

namespace App\Exportaciones;

use App\Models\User;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VentasExportacion extends ExportacionBase
{
    public function __construct(
        private readonly int    $empresaId,
        private readonly int    $sucursalId,
        private readonly array  $filtros = [],
        private readonly bool   $porDia = false,
    ) {}

    public function titulo(): string
    {
        return $this->porDia ? 'Ventas — Resumen por día' : 'Consulta de Ventas';
    }

    public function empresaId(): ?int { return $this->empresaId; }
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
        if (!empty($this->filtros['user_id'])) {
            $r['Cajero'] = User::find($this->filtros['user_id'])?->name ?? "ID {$this->filtros['user_id']}";
        }
        if (!empty($this->filtros['forma_pago'])) {
            $r['Forma de pago'] = ucfirst($this->filtros['forma_pago']);
        }
        if (!empty($this->filtros['estado'])) {
            $r['Estado'] = ucfirst($this->filtros['estado']);
        }
        if (!empty($this->filtros['folio'])) {
            $r['Folio'] = $this->filtros['folio'];
        }
        if (!empty($this->filtros['producto'])) {
            $r['Producto'] = $this->filtros['producto'];
        }
        return $r;
    }

    public function cabeceras(): array
    {
        if ($this->porDia) {
            return ['Fecha', 'Ventas', 'Canceladas', 'Efectivo', 'Tarjeta', 'Transferencia', 'Descuentos', 'Total', 'Ticket prom.'];
        }
        return ['Folio', 'Fecha', 'Hora', 'Cajero', 'Forma pago', 'Estado', 'Subtotal', 'Descuento', 'Total'];
    }

    private ?array $totalesCache = null;

    public function totales(): ?array
    {
        return $this->totalesCache;
    }

    public function datos(): Collection
    {
        $query = $this->baseQuery();

        if ($this->porDia) {
            $ventas = $query->with('pagos')->get();

            $agrupado = $ventas
                ->groupBy(fn($v) => Carbon::parse($v->fecha)->toDateString())
                ->map(function ($g, $fecha) {
                    $confirmadas = $g->where('estado', 'confirmada');
                    $pagosConfirmados = $confirmadas->flatMap(fn($v) => $v->pagos);

                    return [
                        'fecha'         => $fecha,
                        'num_ventas'    => $g->count(),
                        'canceladas'    => $g->where('estado', 'cancelada')->count(),
                        'efectivo'      => round($pagosConfirmados->where('forma_pago', 'efectivo')->sum('monto'), 2),
                        'tarjeta'       => round($pagosConfirmados->where('forma_pago', 'tarjeta')->sum('monto'), 2),
                        'transferencia' => round($pagosConfirmados->where('forma_pago', 'transferencia')->sum('monto'), 2),
                        'descuentos'    => round($confirmadas->sum('descuento'), 2),
                        'total'         => round($confirmadas->sum('total'), 2),
                        'ticket_prom'   => $confirmadas->count() > 0
                            ? round($confirmadas->sum('total') / $confirmadas->count(), 2)
                            : 0,
                    ];
                })
                ->sortKeysDesc()
                ->values();

            $this->totalesCache = [
                'Total',
                $agrupado->sum('num_ventas'),
                $agrupado->sum('canceladas'),
                number_format($agrupado->sum('efectivo'), 2),
                number_format($agrupado->sum('tarjeta'), 2),
                number_format($agrupado->sum('transferencia'), 2),
                number_format($agrupado->sum('descuentos'), 2),
                number_format($agrupado->sum('total'), 2),
                '',
            ];

            return $agrupado->map(fn($d) => [
                Carbon::parse($d['fecha'])->format('d/m/Y'),
                $d['num_ventas'],
                $d['canceladas'] ?: '—',
                number_format($d['efectivo'], 2),
                number_format($d['tarjeta'], 2),
                number_format($d['transferencia'], 2),
                number_format($d['descuentos'], 2),
                number_format($d['total'], 2),
                number_format($d['ticket_prom'], 2),
            ]);
        }

        // Listado individual
        $ventas = $query->with(['user:id,name', 'pagos'])->orderByDesc('fecha')->orderByDesc('id')->get();

        $confirmadas = $ventas->where('estado', 'confirmada');

        $this->totalesCache = [
            '', '', '', '', '', 'Totales',
            number_format($confirmadas->sum('subtotal'), 2),
            number_format($confirmadas->sum('descuento'), 2),
            number_format($confirmadas->sum('total'), 2),
        ];

        return $ventas->map(fn($v) => [
            $v->folio ?? $v->id,
            Carbon::parse($v->fecha)->format('d/m/Y'),
            Carbon::parse($v->created_at)->setTimezone('America/Mexico_City')->format('H:i'),
            $v->user?->name ?? '—',
            $this->etiquetaFormaPago($v->pagos),
            ucfirst($v->estado),
            number_format((float) $v->subtotal, 2),
            number_format((float) $v->descuento, 2),
            number_format((float) $v->total, 2),
        ]);
    }

    private function etiquetaFormaPago(Collection $pagos): string
    {
        $metodos = $pagos->where('forma_pago', '!=', 'saldo_favor')->pluck('forma_pago');

        if ($metodos->isEmpty()) {
            return $pagos->isNotEmpty() ? 'Saldo a favor' : '—';
        }

        return $metodos->count() > 1 ? 'Mixto' : ucfirst($metodos->first());
    }

    public function columnWidths(): array
    {
        if ($this->porDia) {
            return ['A' => 14, 'B' => 10, 'C' => 12, 'D' => 14, 'E' => 14, 'F' => 16, 'G' => 14, 'H' => 14, 'I' => 14];
        }
        return ['A' => 14, 'B' => 12, 'C' => 8, 'D' => 22, 'E' => 14, 'F' => 12, 'G' => 14, 'H' => 14, 'I' => 14];
    }

    private function baseQuery()
    {
        return Venta::where('empresa_id', $this->empresaId)
            ->where('sucursal_id', $this->sucursalId)
            ->when(!empty($this->filtros['fecha_desde']), fn($q) =>
                $q->whereDate('fecha', '>=', $this->filtros['fecha_desde']))
            ->when(!empty($this->filtros['fecha_hasta']), fn($q) =>
                $q->whereDate('fecha', '<=', $this->filtros['fecha_hasta']))
            ->when(!empty($this->filtros['user_id']), fn($q) =>
                $q->where('user_id', $this->filtros['user_id']))
            ->when(!empty($this->filtros['forma_pago']), fn($q) =>
                $q->whereHas('pagos', fn($pq) => $pq->where('forma_pago', $this->filtros['forma_pago'])))
            ->when(!empty($this->filtros['estado']), fn($q) =>
                $q->where('estado', $this->filtros['estado']))
            ->when(!empty($this->filtros['folio']), fn($q) =>
                $q->where('folio', 'like', '%' . $this->filtros['folio'] . '%'))
            ->when(!empty($this->filtros['producto']), fn($q) =>
                $q->whereHas('detalles.producto', fn($pq) =>
                    $pq->where('nombre', 'like', '%' . $this->filtros['producto'] . '%')
                       ->orWhere('codigo', 'like', '%' . $this->filtros['producto'] . '%')
                ));
    }
}
