<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClienteSaldoMovimiento extends Model
{
    public const TIPOS_CREDITO = ['abono', 'devolucion', 'ajuste', 'ajuste_credito', 'reverso_aplicacion'];
    protected $table = 'cliente_saldo_movimientos';

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'cliente_id',
        'pedido_id',
        'venta_id',
        'corte_id',
        'movimiento_caja_id',
        'movimiento_origen_id',
        'user_id',
        'tipo',
        'alcance',
        'forma_pago',
        'monto',
        'saldo_resultante',
        'concepto',
        'metadata',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'saldo_resultante' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function movimientoCaja(): BelongsTo
    {
        return $this->belongsTo(MovimientoCaja::class);
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function origen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'movimiento_origen_id');
    }

    public function getImporteFirmadoAttribute(): float
    {
        return in_array($this->tipo, self::TIPOS_CREDITO, true)
            ? (float) $this->monto
            : -(float) $this->monto;
    }

    public static function expresionSaldo(string $alias = 'cliente_saldo_movimientos'): string
    {
        return "CASE WHEN {$alias}.tipo IN ('abono','devolucion','ajuste','ajuste_credito','reverso_aplicacion') THEN {$alias}.monto ELSE -{$alias}.monto END";
    }
}
