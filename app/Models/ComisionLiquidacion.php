<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComisionLiquidacion extends Model
{
    protected $table = 'comision_liquidaciones';

    public const ESTADO_BORRADOR  = 'borrador';
    public const ESTADO_PAGADA    = 'pagada';
    public const ESTADO_CANCELADA = 'cancelada';

    protected $fillable = [
        'empresa_id',
        'user_id',
        'desde',
        'hasta',
        'total',
        'estado',
        'creado_por',
        'pagado_por',
        'pagado_en',
        'movimiento_caja_id',
        'cancelado_por',
        'cancelado_en',
        'notas',
    ];

    protected $casts = [
        'desde'        => 'date',
        'hasta'        => 'date',
        'total'        => 'decimal:2',
        'pagado_en'    => 'datetime',
        'cancelado_en' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(ComisionMovimiento::class, 'liquidacion_id');
    }

    public function movimientoCaja(): BelongsTo
    {
        return $this->belongsTo(MovimientoCaja::class, 'movimiento_caja_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function pagador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pagado_por');
    }

    public function cancelador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelado_por');
    }
}
