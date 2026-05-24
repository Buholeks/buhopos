<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TipoAtributo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tipo_atributos';

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'user_id',
        'nombre',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Valores que pertenecen a este tipo */
    public function atributos()
    {
        return $this->hasMany(Atributo::class, 'tipo_atributo_id')
                    ->orderBy('valor');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeDeEmpresa($query, int $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}