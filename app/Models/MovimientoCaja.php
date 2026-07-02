<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoCaja extends Model
{
    protected $table = 'movimientos_caja';

    protected $fillable = ['corte_id','user_id','tipo','forma_pago','cuenta_bancaria_id','terminal_pago_id','monto','concepto'];

    protected $casts = ['monto' => 'decimal:2'];

    public function corte(): BelongsTo { return $this->belongsTo(CorteCaja::class, 'corte_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function cuentaBancaria(): BelongsTo { return $this->belongsTo(CuentaBancaria::class); }
    public function terminalPago(): BelongsTo { return $this->belongsTo(TerminalPago::class); }
}