<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Marca extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'marcas';

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'user_id',
        'nombre',
        'logo',
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

    /** Modelos que pertenecen a esta marca */
    public function modelos()
    {
        return $this->hasMany(Modelo::class)->orderBy('nombre');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /** Filtrar por empresa (sucursal es solo referencia, no filtra) */
    public function scopeDeEmpresa($query, int $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** Ruta pública del logo */
    public function logoUrl(): ?string
    {
        return $this->logo ? asset('storage/' . $this->logo) : null;
    }
}