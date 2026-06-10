<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permiso extends Model
{
    protected $table = 'permisos';

    protected $fillable = ['clave', 'modulo', 'descripcion'];

    public function roles()
    {
        return $this->belongsToMany(Rol::class, 'role_permiso', 'permiso_id', 'role_id');
    }
}
