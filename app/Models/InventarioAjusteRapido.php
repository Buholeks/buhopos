<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventarioAjusteRapido extends Model
{
    protected $table = 'inventario_ajustes_rapidos';

    protected $fillable = [
        'empresa_id', 'sucursal_id', 'user_id', 'folio', 'tipo', 'motivo', 'notas',
    ];

    public function detalles()
    {
        return $this->hasMany(InventarioAjusteRapidoDetalle::class, 'ajuste_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
