<?php

namespace App\Services;

use App\Models\ComisionLiquidacion;
use App\Models\ComisionMovimiento;
use App\Models\CorteCaja;
use App\Models\MovimientoCaja;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Liquidaciones (pagos) de comisiones.
 *
 * Generar: toma todos los movimientos pendientes del empleado hasta la fecha de corte
 * (incluye descuentos de periodos ya pagados) y los aparta en una liquidación "borrador".
 * Pagar: la marca "pagada" y, si se pide, registra la salida en el corte de caja abierto.
 * Cancelar: libera los movimientos (vuelven a pendientes) y, si hubo salida de caja,
 * registra el ingreso de reverso en el corte abierto.
 */
class ComisionLiquidacionService
{
    /**
     * Pendiente por empleado hasta la fecha de corte, en las sucursales autorizadas.
     *
     * @param int[] $sucursales
     */
    public function pendientes(int $empresaId, string $hasta, array $sucursales): Collection
    {
        return $this->pendientesQuery($empresaId, $hasta, $sucursales)
            ->join('users', 'users.id', '=', 'comision_movimientos.user_id')
            ->groupBy('comision_movimientos.user_id', 'users.name')
            ->orderBy('users.name')
            ->select([
                'comision_movimientos.user_id',
                'users.name as nombre',
                DB::raw('COUNT(*) as movimientos'),
                DB::raw('MIN(comision_movimientos.fecha) as desde'),
                DB::raw("SUM(CASE WHEN comision_movimientos.monto > 0 THEN comision_movimientos.monto ELSE 0 END) as comisiones"),
                DB::raw("SUM(CASE WHEN comision_movimientos.monto < 0 THEN comision_movimientos.monto ELSE 0 END) as descuentos"),
                DB::raw('SUM(comision_movimientos.monto) as total'),
            ])
            ->get()
            ->map(fn($f) => [
                'user_id'     => (int) $f->user_id,
                'nombre'      => $f->nombre,
                'movimientos' => (int) $f->movimientos,
                'desde'       => Carbon::parse($f->desde)->toDateString(),
                'comisiones'  => round((float) $f->comisiones, 2),
                'descuentos'  => round((float) $f->descuentos, 2),
                'total'       => round((float) $f->total, 2),
            ])
            ->values();
    }

    /** @param int[] $sucursales */
    public function generar(int $empresaId, User $actor, int $userId, string $hasta, array $sucursales, ?string $notas): ComisionLiquidacion
    {
        return DB::transaction(function () use ($empresaId, $actor, $userId, $hasta, $sucursales, $notas) {
            // Bloquea los pendientes para que dos liquidaciones simultáneas no tomen los mismos.
            $movimientos = $this->pendientesQuery($empresaId, $hasta, $sucursales)
                ->where('comision_movimientos.user_id', $userId)
                ->lockForUpdate()
                ->get(['id', 'fecha', 'monto']);

            $total = round((float) $movimientos->sum('monto'), 2);
            if ($movimientos->isEmpty() || $total <= 0) {
                throw ValidationException::withMessages([
                    'user_id' => $movimientos->isEmpty()
                        ? 'El empleado no tiene comisiones pendientes hasta esa fecha.'
                        : 'El saldo pendiente no es positivo; los descuentos se aplicarán en la siguiente liquidación.',
                ]);
            }

            $liquidacion = ComisionLiquidacion::create([
                'empresa_id' => $empresaId,
                'user_id'    => $userId,
                'desde'      => $movimientos->min(fn($m) => $m->fecha->toDateString()),
                'hasta'      => $hasta,
                'total'      => $total,
                'estado'     => ComisionLiquidacion::ESTADO_BORRADOR,
                'creado_por' => $actor->id,
                'notas'      => $notas,
            ]);

            ComisionMovimiento::whereIn('id', $movimientos->pluck('id'))->update(['liquidacion_id' => $liquidacion->id]);

            return $liquidacion;
        });
    }

    /**
     * @param array{forma_pago: string, cuenta_bancaria_id?: ?int}|null $caja null = sin salida de caja
     */
    public function pagar(ComisionLiquidacion $liquidacion, User $actor, ?array $caja, ?CorteCaja $corte): ComisionLiquidacion
    {
        return DB::transaction(function () use ($liquidacion, $actor, $caja, $corte) {
            $liquidacion = ComisionLiquidacion::whereKey($liquidacion->id)->lockForUpdate()->firstOrFail();
            $this->exigirEstado($liquidacion, [ComisionLiquidacion::ESTADO_BORRADOR], 'Solo se puede pagar una liquidación en borrador.');

            $movimientoCaja = null;
            if ($caja !== null) {
                $movimientoCaja = $this->movimientoCaja($corte, $actor, 'egreso', $caja['forma_pago'], $caja['cuenta_bancaria_id'] ?? null,
                    (float) $liquidacion->total, "Pago de comisiones a {$liquidacion->user->name} (liquidación #{$liquidacion->id})");
            }

            $liquidacion->update([
                'estado'             => ComisionLiquidacion::ESTADO_PAGADA,
                'pagado_por'         => $actor->id,
                'pagado_en'          => now(),
                'movimiento_caja_id' => $movimientoCaja?->id,
            ]);

            return $liquidacion;
        });
    }

    public function cancelar(ComisionLiquidacion $liquidacion, User $actor, string $motivo, ?CorteCaja $corte): ComisionLiquidacion
    {
        return DB::transaction(function () use ($liquidacion, $actor, $motivo, $corte) {
            $liquidacion = ComisionLiquidacion::whereKey($liquidacion->id)->lockForUpdate()->firstOrFail();
            $this->exigirEstado($liquidacion, [ComisionLiquidacion::ESTADO_BORRADOR, ComisionLiquidacion::ESTADO_PAGADA],
                'La liquidación ya está cancelada.');

            // Si el pago salió de caja, el dinero regresa con un ingreso en el corte abierto.
            if ($liquidacion->movimiento_caja_id) {
                $original = MovimientoCaja::findOrFail($liquidacion->movimiento_caja_id);
                $this->movimientoCaja($corte, $actor, 'ingreso', $original->forma_pago, $original->cuenta_bancaria_id,
                    (float) $original->monto, "Reverso de pago de comisiones (liquidación #{$liquidacion->id})");
            }

            ComisionMovimiento::where('liquidacion_id', $liquidacion->id)->update(['liquidacion_id' => null]);

            $nota = trim(($liquidacion->notas ? $liquidacion->notas . "\n" : '') . "Cancelada: {$motivo}");
            $liquidacion->update([
                'estado'        => ComisionLiquidacion::ESTADO_CANCELADA,
                'cancelado_por' => $actor->id,
                'cancelado_en'  => now(),
                'notas'         => $nota,
            ]);

            return $liquidacion;
        });
    }

    /** Resumen de lo incluido en una liquidación. */
    public function resumen(ComisionLiquidacion $liquidacion): array
    {
        $movs = $this->movimientosDe($liquidacion);
        $suma = fn(callable $filtro) => round((float) $movs->filter($filtro)->sum('monto'), 2);

        return [
            'comision_vendedor' => $suma(fn($m) => $m->tipo === ComisionMovimiento::TIPO_VENTA && $m->beneficiario === 'vendedor'),
            'comision_cajero'   => $suma(fn($m) => $m->tipo === ComisionMovimiento::TIPO_VENTA && $m->beneficiario === 'cajero'),
            'descuentos'        => $suma(fn($m) => in_array($m->tipo, [ComisionMovimiento::TIPO_CANCELACION, ComisionMovimiento::TIPO_DEVOLUCION], true)),
            'ajustes'           => $suma(fn($m) => $m->tipo === ComisionMovimiento::TIPO_AJUSTE),
            'tickets'           => $movs->where('tipo', ComisionMovimiento::TIPO_VENTA)->pluck('venta_id')->unique()->count(),
            'movimientos'       => $movs->count(),
        ];
    }

    /**
     * Movimientos de la liquidación. Al cancelarse se liberan, así que para una cancelada
     * no hay movimientos que mostrar.
     */
    public function movimientosDe(ComisionLiquidacion $liquidacion): Collection
    {
        return ComisionMovimiento::where('liquidacion_id', $liquidacion->id)
            ->with(ComisionReporte::RELACIONES_DETALLE)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();
    }

    /** Sucursales de los movimientos incluidos (para validar acceso). */
    public function sucursalesDe(ComisionLiquidacion $liquidacion): array
    {
        return ComisionMovimiento::where('liquidacion_id', $liquidacion->id)
            ->distinct()
            ->pluck('sucursal_id')
            ->filter()
            ->map(fn($id) => (int) $id)
            ->values()
            ->all();
    }

    // ── Internos ──────────────────────────────────────────────────────────────

    private function pendientesQuery(int $empresaId, string $hasta, array $sucursales)
    {
        return ComisionMovimiento::query()
            ->where('comision_movimientos.empresa_id', $empresaId)
            ->whereNull('comision_movimientos.liquidacion_id')
            ->where('comision_movimientos.fecha', '<', Carbon::parse($hasta)->addDay()->toDateString())
            ->whereIn('comision_movimientos.sucursal_id', $sucursales);
    }

    private function exigirEstado(ComisionLiquidacion $liquidacion, array $estados, string $mensaje): void
    {
        if (! in_array($liquidacion->estado, $estados, true)) {
            throw ValidationException::withMessages(['estado' => $mensaje]);
        }
    }

    private function movimientoCaja(?CorteCaja $corte, User $actor, string $tipo, string $formaPago, ?int $cuentaId, float $monto, string $concepto): MovimientoCaja
    {
        if (! $corte) {
            throw ValidationException::withMessages(['caja' => 'No hay una caja abierta en esta terminal para registrar el movimiento.']);
        }

        $movimiento = MovimientoCaja::create([
            'corte_id'           => $corte->id,
            'user_id'            => $actor->id,
            'tipo'               => $tipo,
            'forma_pago'         => $formaPago,
            'cuenta_bancaria_id' => $cuentaId,
            'monto'              => $monto,
            'concepto'           => mb_substr($concepto, 0, 255),
        ]);

        $corte->recalcularMovimientos();

        return $movimiento;
    }
}
