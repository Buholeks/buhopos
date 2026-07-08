<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Venta;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pedido extends Model
{
    use SoftDeletes;

    protected $table = 'pedidos';

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'user_id',
        'cliente_id',
        'venta_id',
        'folio',
        'tipo',
        'estado',
        'estado_pago',
        'fecha_promesa',
        'subtotal',
        'anticipo',
        'saldo_pendiente',
        'notas',
    ];

    protected $casts = [
        'fecha_promesa' => 'date',
        'subtotal' => 'decimal:2',
        'anticipo' => 'decimal:2',
        'saldo_pendiente' => 'decimal:2',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(PedidoDetalle::class);
    }

    public function saldos(): HasMany
    {
        return $this->hasMany(ClienteSaldoMovimiento::class);
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    /**
     * Recalcula el estado de cabecera del pedido a partir del estado de sus renglones.
     * Un pedido con algunos renglones ya entregados y el resto cancelado queda 'parcial'
     * (no 'cancelado'), para no perder el rastro de que parte sí se vendió.
     */
    public function actualizarEstadoPorDetalles(): void
    {
        $estados = $this->detalles()->pluck('estado')->all();
        $activos = array_values(array_filter($estados, fn($estado) => $estado !== 'cancelado'));

        if ($activos === []) {
            $this->update(['estado' => 'cancelado']);
            return;
        }

        if (count(array_unique($activos)) === 1 && $activos[0] === 'devuelto') {
            $this->update(['estado' => 'devuelto']);
            return;
        }

        if (in_array('entregado', $activos, true) || in_array('devuelto', $activos, true)) {
            $this->update(['estado' => 'parcial']);
            return;
        }

        if (in_array('disponible', $activos, true) || in_array('reservado', $activos, true)) {
            $this->update(['estado' => 'disponible']);
            return;
        }

        $this->update(['estado' => 'pendiente']);
    }

    /**
     * Recalcula saldo_pendiente y estado_pago a partir de los renglones que aun
     * siguen pendientes (no entregados/devueltos/cancelados). El subtotal fijo del
     * pedido no basta una vez que se entrega/devuelve parte por separado: usar ese
     * total completo dejaba "Debe" inflado y permitia abonos por mas de lo real.
     */
    public function recalcularSaldoPendiente(): void
    {
        $subtotalPendiente = round((float) $this->detalles()
            ->whereNotIn('estado', ['entregado', 'devuelto', 'cancelado'])
            ->sum('subtotal'), 2);

        $anticipo = (float) $this->anticipo;
        $saldo = max(0, round($subtotalPendiente - $anticipo, 2));

        $this->update([
            'saldo_pendiente' => $saldo,
            'estado_pago' => match (true) {
                $saldo <= 0 => 'pagado',
                $anticipo <= 0 => 'sin_anticipo',
                default => 'con_anticipo',
            },
        ]);
    }
}
