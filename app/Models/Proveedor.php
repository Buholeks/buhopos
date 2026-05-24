<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    protected $table = 'proveedores';

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'user_id',

        'nombre_comercial',
        'razon_social',
        'rfc',
        'telefono',
        'email',
        'contacto',

        'calle',
        'numero',
        'colonia',
        'ciudad',
        'estado',
        'cp',
        'sitio_web',
        'activo',

        'credito_activo',
        'dias_credito_default',
        'limite_credito',
        'saldo_credito_cache',
        'total_credito_cache',
        'total_abonos_cache',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'credito_activo' => 'boolean',
        'limite_credito' => 'decimal:2',
        'saldo_credito_cache' => 'decimal:2',
        'total_credito_cache' => 'decimal:2',
        'total_abonos_cache' => 'decimal:2',
    ];
}
