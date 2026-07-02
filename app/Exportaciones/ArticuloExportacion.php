<?php

namespace App\Exportaciones;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ArticuloExportacion extends ExportacionBase
{
    private ?array $totalesCache = null;

    public function __construct(
        private readonly int $empresaId,
        private readonly int $sucursalId,
        private readonly int $productoId,
        private readonly ?int $varianteId,
        private readonly string $productoNombre,
        private readonly ?string $productoCodigo,
        private readonly bool $usaSaldoDirecto,
        private readonly array $filtros = [],
    ) {}

    public function titulo(): string
    {
        return 'Historial — ' . $this->productoNombre;
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
        $r = [
            'Articulo' => trim($this->productoNombre . ($this->productoCodigo ? " ({$this->productoCodigo})" : '')),
        ];

        if (!empty($this->filtros['fecha_inicio'])) {
            $r['Desde'] = Carbon::parse($this->filtros['fecha_inicio'])->format('d/m/Y');
        }
        if (!empty($this->filtros['fecha_hasta'])) {
            $r['Hasta'] = Carbon::parse($this->filtros['fecha_hasta'])->format('d/m/Y');
        }
        if (!empty($this->filtros['tipo'])) {
            $r['Movimiento'] = $this->tipoKardexLabel($this->filtros['tipo']);
        }

        return $r;
    }

    public function cabeceras(): array
    {
        return ['Fecha', 'Tipo', 'Antes', 'Despues', 'Entrada', 'Salida', 'Saldo', 'Usuario', 'Referencia', 'Nota'];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 16, 'B' => 22, 'C' => 12, 'D' => 12, 'E' => 12,
            'F' => 12, 'G' => 12, 'H' => 20, 'I' => 24, 'J' => 30,
        ];
    }

    public function totales(): ?array
    {
        return $this->totalesCache;
    }

    public function datos(): Collection
    {
        $timezone = 'America/Mexico_City';
        $fechaInicio = !empty($this->filtros['fecha_inicio'])
            ? Carbon::parse($this->filtros['fecha_inicio'], $timezone)->startOfDay()
            : null;
        $fechaHasta = !empty($this->filtros['fecha_hasta'])
            ? Carbon::parse($this->filtros['fecha_hasta'], $timezone)->endOfDay()
            : now($timezone)->endOfDay();
        $tipo = $this->filtros['tipo'] ?? '';

        $saldo = 0.0;
        if ($fechaInicio && ! $this->usaSaldoDirecto) {
            $saldo = $this->saldoAntesDe($fechaInicio);
        }

        $eventos = DB::table('kardex_movimientos as k')
            ->leftJoin('users as u', 'u.id', '=', 'k.user_id')
            ->where('k.empresa_id', $this->empresaId)
            ->where('k.sucursal_id', $this->sucursalId)
            ->where('k.producto_id', $this->productoId)
            ->when($fechaInicio, fn ($q) => $q->where('k.fecha', '>=', $fechaInicio->format('Y-m-d H:i:s')))
            ->where('k.fecha', '<=', $fechaHasta->format('Y-m-d H:i:s'))
            ->when($this->varianteId, fn ($q) => $q->where('k.variante_id', $this->varianteId))
            ->orderBy('k.fecha')
            ->orderBy('k.id')
            ->select(
                'k.fecha', 'k.tipo', 'k.entrada', 'k.salida', 'k.stock_antes', 'k.stock_despues',
                'k.folio', 'k.referencia_tipo', 'k.motivo', 'k.notas', 'k.metadata', 'u.name as usuario'
            )
            ->get();

        $filas = collect();
        $totalEntradas = 0.0;
        $totalSalidas = 0.0;

        foreach ($eventos as $r) {
            $entrada = (float) $r->entrada;
            $salida = (float) $r->salida;
            $antes = $this->usaSaldoDirecto ? (float) $r->stock_antes : $saldo;
            $despues = $this->usaSaldoDirecto ? (float) $r->stock_despues : ($saldo + $entrada - $salida);
            $saldo = $despues;

            if ($tipo && $r->tipo !== $tipo) {
                continue;
            }

            $totalEntradas += $entrada;
            $totalSalidas += $salida;

            $filas->push([
                Carbon::parse($r->fecha)->format('d/m/Y H:i'),
                $this->tipoKardexLabel($r->tipo),
                round($antes, 3),
                round($despues, 3),
                $entrada ? round($entrada, 3) : '',
                $salida ? round($salida, 3) : '',
                round($saldo, 3),
                $r->usuario ?? '—',
                $r->folio ?: ($r->referencia_tipo ?: '—'),
                $this->notaKardex($r) ?? '',
            ]);
        }

        $this->totalesCache = [
            '', 'Totales', '', '',
            round($totalEntradas, 3), round($totalSalidas, 3), '', '', '',
            'Diferencia: ' . round($totalEntradas - $totalSalidas, 3),
        ];

        return $filas;
    }

    private function saldoAntesDe(Carbon $antesDe): float
    {
        $fila = DB::table('kardex_movimientos as k')
            ->where('k.empresa_id', $this->empresaId)
            ->where('k.sucursal_id', $this->sucursalId)
            ->where('k.producto_id', $this->productoId)
            ->where('k.fecha', '<', $antesDe->format('Y-m-d H:i:s'))
            ->when($this->varianteId, fn ($q) => $q->where('k.variante_id', $this->varianteId))
            ->selectRaw('COALESCE(SUM(entrada), 0) - COALESCE(SUM(salida), 0) as saldo')
            ->first();

        return (float) ($fila->saldo ?? 0);
    }

    private function tipoKardexLabel(string $tipo): string
    {
        return [
            'alta_producto' => 'Alta de articulo',
            'alta_variante' => 'Alta de variante',
            'alta_serie' => 'Alta de serie',
            'saldo_inicial' => 'Saldo inicial',
            'compra' => 'Compra',
            'venta' => 'Venta',
            'cancelacion_compra' => 'Cancelacion compra',
            'cancelacion_venta' => 'Cancelacion venta',
            'devolucion_cliente' => 'Devolucion cliente',
            'devolucion_proveedor' => 'Devolucion proveedor',
            'anulacion_devolucion_proveedor' => 'Anulacion devolucion proveedor',
            'ajuste_positivo' => 'Ajuste positivo',
            'ajuste_negativo' => 'Ajuste negativo',
            'traspaso_entrada' => 'Traspaso entrada',
            'traspaso_salida' => 'Traspaso salida',
            'rechazo_traspaso' => 'Rechazo de traspaso',
            'cancelacion_traspaso' => 'Cancelacion de traspaso',
        ][$tipo] ?? str_replace('_', ' ', ucfirst($tipo));
    }

    private function notaKardex(object $row): ?string
    {
        if ($row->motivo) {
            return $row->motivo;
        }

        if ($row->notas) {
            return $row->notas;
        }

        $metadata = json_decode((string) $row->metadata, true);
        if (! is_array($metadata)) {
            return null;
        }

        if (isset($metadata['origen_sucursal_id'], $metadata['destino_sucursal_id'])) {
            return "Sucursal {$metadata['origen_sucursal_id']} -> {$metadata['destino_sucursal_id']}";
        }

        if (! empty($metadata['venta_folio'])) {
            return "Venta {$metadata['venta_folio']}";
        }

        if (! empty($metadata['compra_folio'])) {
            return "Compra {$metadata['compra_folio']}";
        }

        return null;
    }
}
