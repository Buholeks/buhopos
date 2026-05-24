<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoCaja extends Model
{
    protected $table = 'movimientos_caja';

    protected $fillable = ['corte_id','user_id','tipo','forma_pago','monto','concepto'];

    protected $casts = ['monto' => 'decimal:2'];

    public function corte(): BelongsTo { return $this->belongsTo(CorteCaja::class, 'corte_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}