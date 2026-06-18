<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventarioConteoEvento extends Model
{
    protected $table = 'inventario_conteo_eventos';

    protected $fillable = [
        'conteo_id',
        'user_id',
        'tipo',
        'descripcion',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
