<?php

namespace App\Exportaciones;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConsultaCajaExportacion extends ExportacionBase
{
    public function __construct(
        private readonly int    $empresaId,
        private readonly int    $sucursalId,
        private readonly array  $filtros = []
    ) {}

    public function titulo(): string
    {
        return 'Consulta de Caja — Movimientos y Ventas';
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

        if (!empty($this->filtros['desde'])) {
            $aplicados['Desde'] = Carbon::parse($this->filtros['desde'])->format('d/m/Y');
        }
        if (!empty($this->filtros['hasta'])) {
            $aplicados['Hasta'] = Carbon::parse($this->filtros['hasta'])->format('d/m/Y');
        }
        if (!empty($this->filtros['origen'])) {
            $aplicados['Origen'] = match ($this->filtros['origen']) {
                'venta'      => 'Ventas',
                'movimiento' => 'Movimientos manuales',
                default      => $this->filtros['origen'],
            };
        }
        if (!empty($this->filtros['tipo'])) {
            $aplicados['Tipo'] = $this->filtros['tipo'] === 'ingreso' ? 'Ingreso' : 'Egreso';
        }
        if (!empty($this->filtros['forma_pago'])) {
            $aplicados['Forma de pago'] = match ($this->filtros['forma_pago']) {
                'efectivo'      => 'Efectivo',
                'tarjeta'       => 'Tarjeta',
                'transferencia' => 'Transferencia',
                'credito'       => 'Crédito',
                default         => $this->filtros['forma_pago'],
            };
        }
        if (!empty($this->filtros['user_id'])) {
            $nombre = User::find($this->filtros['user_id'])?->name;
            $aplicados['Usuario'] = $nombre ?? "ID {$this->filtros['user_id']}";
        }
        if (!empty($this->filtros['concepto'])) {
            $aplicados['Concepto / Folio'] = $this->filtros['concepto'];
        }

        return $aplicados;
    }

    public function cabeceras(): array
    {
        return [
            'Fecha / Hora',
            'Terminal',
            'Usuario',
            'Origen',
            'Tipo',
            'Forma de Pago',
            'Concepto / Folio',
            'Monto',
        ];
    }

    private ?array $totalesCache = null;

    public function totales(): ?array
    {
        return $this->totalesCache;
    }

    public function datos(): Collection
    {
        $timezone = 'America/Mexico_City';
        $hoy      = now($timezone)->toDateString();

        $desde = $this->filtros['desde'] ?? $hoy;
        $hasta = $this->filtros['hasta'] ?? $hoy;

        $desdeTs = Carbon::parse($desde, $timezone)->startOfDay()->utc()->format('Y-m-d H:i:s');
        $hastaTs = Carbon::parse($hasta, $timezone)->endOfDay()->utc()->format('Y-m-d H:i:s');

        $origen    = $this->filtros['origen']     ?? '';
        $tipo      = $this->filtros['tipo']        ?? '';
        $formaPago = $this->filtros['forma_pago']  ?? '';
        $userId    = $this->filtros['user_id']     ?? '';
        $concepto  = trim((string) ($this->filtros['concepto'] ?? ''));

        // ── Rama 1: movimientos manuales ──────────────────────────────────────
        $qMov = DB::table('movimientos_caja as m')
            ->join('cortes_caja as c', 'm.corte_id', '=', 'c.id')
            ->join('users as u', 'm.user_id', '=', 'u.id')
            ->where('c.empresa_id', $this->empresaId)
            ->where('c.sucursal_id', $this->sucursalId)
            ->whereBetween('m.created_at', [$desdeTs, $hastaTs])
            ->select([
                'm.id',
                DB::raw("'movimiento' as origen"),
                DB::raw("DATE_FORMAT(m.created_at, '%Y-%m-%dT%H:%i:%S+00:00') as fecha_hora"),
                'u.name as usuario',
                'm.tipo',
                'm.forma_pago',
                DB::raw('CAST(m.monto AS DECIMAL(14,2)) as monto'),
                'm.concepto',
                'c.terminal',
                DB::raw('NULL as folio'),
            ]);

        if ($tipo)      $qMov->where('m.tipo', $tipo);
        if ($formaPago) $qMov->where('m.forma_pago', $formaPago);
        if ($userId)    $qMov->where('m.user_id', $userId);
        if ($concepto)  $qMov->where('m.concepto', 'like', "%{$concepto}%");

        // ── Rama 2: ventas confirmadas ────────────────────────────────────────
        $qVentas = DB::table('ventas as v')
            ->join('users as u', 'v.user_id', '=', 'u.id')
            ->join('cortes_caja as c', 'v.corte_id', '=', 'c.id')
            ->where('v.empresa_id', $this->empresaId)
            ->where('v.sucursal_id', $this->sucursalId)
            ->where('c.empresa_id', $this->empresaId)
            ->where('v.estado', 'confirmada')
            ->whereNotNull('v.corte_id')
            ->whereBetween('v.created_at', [$desdeTs, $hastaTs])
            ->select([
                'v.id',
                DB::raw("'venta' as origen"),
                DB::raw("DATE_FORMAT(v.created_at, '%Y-%m-%dT%H:%i:%S+00:00') as fecha_hora"),
                'u.name as usuario',
                DB::raw("'ingreso' as tipo"),
                'v.forma_pago',
                DB::raw('CAST(GREATEST(v.total - COALESCE(v.saldo_aplicado, 0), 0) AS DECIMAL(14,2)) as monto'),
                DB::raw("CONCAT('Venta ', COALESCE(v.folio, v.id)) as concepto"),
                'c.terminal',
                'v.folio',
            ]);

        if ($formaPago) $qVentas->where('v.forma_pago', $formaPago);
        if ($userId)    $qVentas->where('v.user_id', $userId);
        if ($concepto)  $qVentas->where(DB::raw("CONCAT('Venta ', COALESCE(v.folio, v.id))"), 'like', "%{$concepto}%");
        if ($tipo === 'egreso') $qVentas->whereRaw('1=0');

        // ── UNION ─────────────────────────────────────────────────────────────
        $query = match ($origen) {
            'movimiento' => $qMov,
            'venta'      => $qVentas,
            default      => $qMov->unionAll($qVentas),
        };

        $etiquetaFormaPago = fn ($f) => match ($f) {
            'efectivo'      => 'Efectivo',
            'tarjeta'       => 'Tarjeta',
            'transferencia' => 'Transferencia',
            'credito'       => 'Crédito',
            default         => ucfirst((string) $f),
        };

        $coleccion = DB::table(DB::raw("({$query->toSql()}) as t"))
            ->mergeBindings($query)
            ->orderByDesc('fecha_hora')
            ->get();

        $this->totalesCache = [
            '', '', '', '', '', '',
            'Totales',
            number_format($coleccion->sum('monto'), 2),
        ];

        return $coleccion->map(fn ($r) => [
            $this->fmtFecha($r->fecha_hora),
            $r->terminal ?? '—',
            $r->usuario ?? '—',
            $r->origen === 'venta' ? 'Venta' : 'Manual',
            ucfirst($r->tipo),
            $etiquetaFormaPago($r->forma_pago),
            $r->concepto ?? '—',
            number_format((float) $r->monto, 2),
        ]);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18, // Fecha / Hora
            'B' => 14, // Terminal
            'C' => 22, // Usuario
            'D' => 12, // Origen
            'E' => 12, // Tipo
            'F' => 16, // Forma de Pago
            'G' => 30, // Concepto
            'H' => 14, // Monto
        ];
    }

    private function fmtFecha(?string $f): string
    {
        if (!$f) return '—';
        try {
            return Carbon::parse($f)->setTimezone('America/Mexico_City')->format('d/m/Y H:i');
        } catch (\Throwable) {
            return $f;
        }
    }
}
