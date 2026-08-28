<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductoVarianteGrupo extends Model
{
    protected $fillable = [
        'empresa_id',
        'producto_id',
        'tipo_atributo_id',
        'atributo_id',
        'codigo',
        'es_personalizado',
    ];

    protected $casts = ['es_personalizado' => 'boolean'];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function tipoAtributo()
    {
        return $this->belongsTo(TipoAtributo::class);
    }

    public function atributo()
    {
        return $this->belongsTo(Atributo::class);
    }
}
