<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EtiquetaPlantilla extends Model
{
    protected $table = 'etiqueta_plantillas';

    protected $fillable = [
        'empresa_id',
        'perfil_id',
        'nombre',
        'tipo',
        'ancho_mm',
        'alto_mm',
        'diseno',
        'predeterminada',
        'activa',
    ];

    protected $casts = [
        'perfil_id' => 'integer',
        'ancho_mm' => 'float',
        'alto_mm' => 'float',
        'diseno' => 'array',
        'predeterminada' => 'boolean',
        'activa' => 'boolean',
    ];
}
