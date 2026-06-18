<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventarioConteo extends Model
{
    protected $table = 'inventario_conteos';

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'user_id',
        'revisado_por',
        'ajustado_por',
        'folio',
        'modo',
        'alcance_tipo',
        'alcance_id',
        'alcance_nombre',
        'estado',
        'snapshot_at',
        'cerrado_at',
        'ajustado_at',
        'notas',
    ];

    protected $casts = [
        'snapshot_at' => 'datetime',
        'cerrado_at' => 'datetime',
        'ajustado_at' => 'datetime',
    ];

    public function detalles()
    {
        return $this->hasMany(InventarioConteoDetalle::class, 'conteo_id');
    }

    public function eventos()
    {
        return $this->hasMany(InventarioConteoEvento::class, 'conteo_id');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
