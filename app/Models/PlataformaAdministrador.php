<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class PlataformaAdministrador extends Authenticatable
{
    use Notifiable;

    protected $table = 'plataforma_administradores';

    protected $fillable = ['nombre', 'email', 'password', 'activo', 'ultimo_acceso_en'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'activo' => 'boolean',
            'ultimo_acceso_en' => 'datetime',
        ];
    }
}
