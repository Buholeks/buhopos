<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Devolucion extends Model
{
    protected $table = 'devoluciones';

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'venta_id',
        'user_id',
        'corte_id',
        'folio',
        'fecha',
        'forma_devolucion',
        'total_devuelto',
        'regresa_inventario',
        'motivo',
        'estado',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'total_devuelto' => 'decimal:2',
        'regresa_inventario' => 'boolean',
    ];

    public function detalles(): HasMany
    {
        return $this->hasMany(DevolucionDetalle::class);
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function corte(): BelongsTo
    {
        return $this->belongsTo(CorteCaja::class, 'corte_id');
    }
}
