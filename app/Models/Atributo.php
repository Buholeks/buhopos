<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Atributo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'atributos';

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'user_id',
        'tipo_atributo_id',
        'valor',
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

    /** Tipo al que pertenece este valor */
    public function tipo()
    {
        return $this->belongsTo(TipoAtributo::class, 'tipo_atributo_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeDeEmpresa($query, int $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function scopeDeEmpresaYTipo($query, int $empresaId, int $tipoId)
    {
        return $query->where('empresa_id', $empresaId)
                     ->where('tipo_atributo_id', $tipoId);
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}