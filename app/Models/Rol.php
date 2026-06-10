<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    protected $table = 'roles';

    protected $fillable = ['empresa_id', 'nombre', 'descripcion'];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function permisos()
    {
        return $this->belongsToMany(Permiso::class, 'role_permiso', 'role_id', 'permiso_id');
    }

    /** Reemplaza todos los permisos del rol con los ids dados. */
    public function sincronizarPermisos(array $permisoIds): void
    {
        $this->permisos()->sync($permisoIds);
    }
}
