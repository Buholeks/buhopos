<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sucursal extends Model
{
    protected $table = 'sucursales';
    protected $fillable = [
        'empresa_id',
        'nombre',
        'direccion',
        'telefono',
        'correo',
        'activo',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }
    public function users()
    {
        return $this->belongsToMany(User::class, 'sucursal_user')
            ->withTimestamps();
    }
}
