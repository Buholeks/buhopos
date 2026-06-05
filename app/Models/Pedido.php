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
}
