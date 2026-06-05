<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Venta extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'user_id',
        'cliente_id',
        'vendedor_id',
        'corte_id',
        'folio',
        'fecha',
        'forma_pago',
        'metodo_cobro_detalle',
        'subtotal',
        'descuento',
        'saldo_aplicado',
        'total',
        'monto_recibido',
        'cambio',
        'notas',
        'estado',
        'cancelado_por',
        'cancelado_en',
        'motivo_cancelacion',
    ];

    protected $casts = [
        'fecha'     => 'date',
        'subtotal'  => 'decimal:2',
        'descuento' => 'decimal:2',
        'saldo_aplicado' => 'decimal:2',
        'total'     => 'decimal:2',
        'monto_recibido' => 'decimal:2',
        'cambio'         => 'decimal:2',
        'cancelado_en'   => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function detalles(): HasMany
    {
        return $this->hasMany(VentaDetalle::class);
    }

    public function recalcularTotales(): void
    {
        $subtotal = (float) $this->detalles()->sum('subtotal');
        $descuento = (float) ($this->descuento ?? 0);
        $total = max(0, $subtotal - $descuento);

        $this->update([
            'subtotal' => $subtotal,
            'total'    => $total,
        ]);
    }
    public function vendedor()
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function corte(): BelongsTo
    {
        return $this->belongsTo(CorteCaja::class, 'corte_id');
    }

    public function cancelador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelado_por');
    }

    public function devoluciones(): HasMany
    {
        return $this->hasMany(Devolucion::class);
    }
}
