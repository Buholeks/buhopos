<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EtiquetaPlantilla extends Model
{
    protected $table = 'etiqueta_plantillas';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'tipo',
        'ancho_mm',
        'alto_mm',
        'diseno',
        'predeterminada',
        'activa',
    ];

    protected $casts = [
        'ancho_mm' => 'float',
        'alto_mm' => 'float',
        'diseno' => 'array',
        'predeterminada' => 'boolean',
        'activa' => 'boolean',
    ];
}
