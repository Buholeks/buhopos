<?php

namespace App\Servicios;

use App\Models\ClienteSaldoMovimiento;
use App\Models\Venta;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ClienteSaldoServicio
{
    public function saldo(int $empresaId, int $sucursalId, int $clienteId, ?string $alcance = null, ?int $pedidoId = null): float
    {
        return round((float) ClienteSaldoMovimiento::query()
            ->where('empresa_id', $empresaId)
            ->where('sucursal_id', $sucursalId)
            ->where('cliente_id', $clienteId)
            ->when($alcance, fn ($q) => $q->where('alcance', $alcance))
            ->when($pedidoId, fn ($q) => $q->where('pedido_id', $pedidoId))
            ->sum(DB::raw(ClienteSaldoMovimiento::expresionSaldo())), 2);
    }

    public function resumen(int $empresaId, int $sucursalId, int $clienteId): array
    {
        $general = max(0, $this->saldo($empresaId, $sucursalId, $clienteId, 'general'));
        $legacy = max(0, $this->saldo($empresaId, $sucursalId, $clienteId, 'legacy'));
        $reservado = max(0, $this->saldo($empresaId, $sucursalId, $clienteId, 'pedido'));

        return compact('general', 'legacy', 'reservado') + ['total' => round($general + $legacy + $reservado, 2)];
    }

    public function saldosPedidos(int $empresaId, int $sucursalId, int $clienteId, array $pedidoIds = []): Collection
    {
        return ClienteSaldoMovimiento::query()
            ->where('empresa_id', $empresaId)->where('sucursal_id', $sucursalId)->where('cliente_id', $clienteId)
            ->where('alcance', 'pedido')->whereNotNull('pedido_id')
            ->when($pedidoIds, fn ($q) => $q->whereIn('pedido_id', $pedidoIds))
            ->groupBy('pedido_id')->select('pedido_id')
            ->selectRaw('SUM(' . ClienteSaldoMovimiento::expresionSaldo() . ') AS saldo')
            ->pluck('saldo', 'pedido_id')->map(fn ($saldo) => max(0, round((float) $saldo, 2)));
    }

    public function aplicarEnVenta(Venta $venta, float $monto, array $pedidoTopes, bool $permiteLegacy): array
    {
        ClienteSaldoMovimiento::where('empresa_id', $venta->empresa_id)
            ->where('sucursal_id', $venta->sucursal_id)
            ->where('cliente_id', $venta->cliente_id)
            ->lockForUpdate()->get(['id']);
        $restante = round($monto, 2);
        $creados = [];
        foreach ($pedidoTopes as $pedidoId => $tope) {
            if ($restante <= 0) break;
            $disponible = max(0, $this->saldo((int) $venta->empresa_id, (int) $venta->sucursal_id, (int) $venta->cliente_id, 'pedido', (int) $pedidoId));
            $aplicar = round(min($restante, $disponible, (float) $tope), 2);
            if ($aplicar > 0) {
                $creados[] = $this->crearAplicacion($venta, $aplicar, 'pedido', (int) $pedidoId, $disponible - $aplicar);
                $restante -= $aplicar;
            }
        }

        foreach (['general', ...($permiteLegacy ? ['legacy'] : [])] as $alcance) {
            if ($restante <= 0) break;
            $disponible = max(0, $this->saldo((int) $venta->empresa_id, (int) $venta->sucursal_id, (int) $venta->cliente_id, $alcance));
            $aplicar = round(min($restante, $disponible), 2);
            if ($aplicar > 0) {
                $creados[] = $this->crearAplicacion($venta, $aplicar, $alcance, null, $disponible - $aplicar);
                $restante -= $aplicar;
            }
        }

        abort_if($restante > 0.009, 422, 'El saldo seleccionado no está disponible para los productos de esta venta.');
        return $creados;
    }

    private function crearAplicacion(Venta $venta, float $monto, string $alcance, ?int $pedidoId, float $saldo): ClienteSaldoMovimiento
    {
        return ClienteSaldoMovimiento::create([
            'empresa_id' => $venta->empresa_id, 'sucursal_id' => $venta->sucursal_id,
            'cliente_id' => $venta->cliente_id, 'pedido_id' => $pedidoId, 'venta_id' => $venta->id,
            'corte_id' => $venta->corte_id, 'user_id' => $venta->user_id, 'tipo' => 'aplicacion',
            'alcance' => $alcance, 'forma_pago' => 'saldo_favor', 'monto' => $monto,
            'saldo_resultante' => max(0, round($saldo, 2)),
            'concepto' => "Aplicación de saldo en venta {$venta->folio}",
        ]);
    }
}
