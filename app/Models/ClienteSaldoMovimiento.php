<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClienteSaldoMovimiento extends Model
{
    protected $table = 'cliente_saldo_movimientos';

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'cliente_id',
        'pedido_id',
        'venta_id',
        'corte_id',
        'movimiento_caja_id',
        'user_id',
        'tipo',
        'forma_pago',
        'monto',
        'saldo_resultante',
        'concepto',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'saldo_resultante' => 'decimal:2',
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
}
