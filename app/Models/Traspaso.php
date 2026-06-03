<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Traspaso extends Model
{
    protected $fillable = [
        'empresa_id',
        'origen_sucursal_id',
        'destino_sucursal_id',
        'user_id',
        'cancelado_por',
        'recibido_por',
        'rechazado_por',
        'folio',
        'estado',
        'total_items',
        'notas',
        'recibido_at',
        'rechazado_at',
        'cancelado_at',
        'motivo_cancelacion',
        'motivo_rechazo',
    ];

    protected $casts = [
        'total_items' => 'decimal:3',
        'recibido_at' => 'datetime',
        'rechazado_at' => 'datetime',
        'cancelado_at' => 'datetime',
    ];

    public function detalles()
    {
        return $this->hasMany(TraspasoDetalle::class);
    }

    public function origen()
    {
        return $this->belongsTo(Sucursal::class, 'origen_sucursal_id');
    }

    public function destino()
    {
        return $this->belongsTo(Sucursal::class, 'destino_sucursal_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cancelador()
    {
        return $this->belongsTo(User::class, 'cancelado_por');
    }

    public function receptor()
    {
        return $this->belongsTo(User::class, 'recibido_por');
    }

    public function rechazador()
    {
        return $this->belongsTo(User::class, 'rechazado_por');
    }
}
