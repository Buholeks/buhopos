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
        'ticket_config',
    ];

    protected $casts = [
        'ticket_config' => 'array',
    ];


    public function sucursales()
    {
        return $this->hasMany(Sucursal::class);
    }
}
