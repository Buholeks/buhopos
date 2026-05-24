<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VarianteAtributo extends Model
{
    protected $table    = 'variante_atributos';
    public    $timestamps = false;

    protected $fillable = [
        'variante_id',
        'tipo_atributo_id',
        'atributo_id',
    ];

    public function variante()
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }

    public function tipoAtributo()
    {
        return $this->belongsTo(TipoAtributo::class, 'tipo_atributo_id');
    }

    public function atributo()
    {
        return $this->belongsTo(Atributo::class, 'atributo_id');
    }
}