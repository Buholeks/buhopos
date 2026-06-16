<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EtiquetaPerfil extends Model
{
    protected $table = 'etiqueta_perfiles';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'impresora',
        'material',
        'ancho_mm',
        'alto_mm',
        'separacion_mm',
        'offset_x_mm',
        'offset_y_mm',
        'escala',
        'rotacion',
        'corte_automatico',
        'predeterminado',
        'activo',
    ];

    protected $casts = [
        'ancho_mm' => 'float',
        'alto_mm' => 'float',
        'separacion_mm' => 'float',
        'offset_x_mm' => 'float',
        'offset_y_mm' => 'float',
        'escala' => 'float',
        'rotacion' => 'integer',
        'corte_automatico' => 'boolean',
        'predeterminado' => 'boolean',
        'activo' => 'boolean',
    ];
}
