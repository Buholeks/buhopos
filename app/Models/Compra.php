<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Compra extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'user_id',
        'proveedor_id',
        'folio',
        'fecha',
        'forma_pago',
        'fecha_vencimiento',
        'notas',
        'subtotal',
        'total',
        'saldo_favor_aplicado',
        'estado',
    ];

    protected $casts = [
        'fecha'             => 'date',
        'fecha_vencimiento' => 'date',
        'subtotal'          => 'decimal:2',
        'total'             => 'decimal:2',
        'saldo_favor_aplicado' => 'decimal:2',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function detalles(): HasMany
    {
        return $this->hasMany(CompraDetalle::class);
    }

    /** Recalcula subtotal y total a partir de los detalles */
    public function recalcularTotales(): void
    {
        $subtotal = $this->detalles()->sum('subtotal');
        $this->update(['subtotal' => $subtotal, 'total' => $subtotal]);
    }
}
