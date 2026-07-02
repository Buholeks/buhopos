<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentaPago extends Model
{
    protected $table = 'venta_pagos';

    protected $fillable = [
        'venta_id',
        'forma_pago',
        'monto',
        'cuenta_bancaria_id',
        'terminal_pago_id',
        'monto_recibido',
        'cambio',
    ];

    protected $casts = [
        'monto'          => 'decimal:2',
        'monto_recibido' => 'decimal:2',
        'cambio'         => 'decimal:2',
    ];

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function cuentaBancaria(): BelongsTo
    {
        return $this->belongsTo(CuentaBancaria::class);
    }

    public function terminalPago(): BelongsTo
    {
        return $this->belongsTo(TerminalPago::class);
    }
}
