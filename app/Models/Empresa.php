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
        'config_pedidos',
    ];

    protected $casts = [
        'ticket_config' => 'array',
        'config_pedidos' => 'array',
    ];


    public function sucursales()
    {
        return $this->hasMany(Sucursal::class);
    }
}
