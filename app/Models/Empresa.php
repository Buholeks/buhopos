<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    protected $fillable = [
        'nombre',
        'propietario',
        'direccion',
        'correo',
        'telefono',
        'rfc',
        'logo',
        'activo',
    ];


    public function sucursales()
    {
        return $this->hasMany(Sucursal::class);
    }
}
