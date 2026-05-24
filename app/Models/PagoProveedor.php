<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PagoProveedor extends Model
{
    protected $table = 'pagos_proveedor';

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'compra_id',
        'user_id',
        'monto',
        'fecha_pago',
        'forma_pago',
        'referencia',
        'notas',
    ];

    protected $casts = [
        'fecha_pago' => 'date',
        'monto'      => 'decimal:2',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────────

    public function compra()
    {
        return $this->belongsTo(Compra::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}