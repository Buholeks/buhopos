<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CuentaCobroPlataforma extends Model
{
    protected $table = 'cuentas_cobro_plataforma';
    protected $fillable = ['banco', 'beneficiario', 'clabe', 'numero_cuenta', 'instrucciones', 'activa', 'predeterminada'];
    protected function casts(): array { return ['activa' => 'boolean', 'predeterminada' => 'boolean']; }
}
