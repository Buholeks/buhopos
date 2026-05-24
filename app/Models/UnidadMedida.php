<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UnidadMedida extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'unidades_medida';

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'user_id',
        'nombre',
        'abreviatura',
        'tipo',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // ── Tipos disponibles (útil para validaciones y Vue) ─────────────────────
    const TIPOS = [
        'cantidad' => 'Cantidad',
        'peso'     => 'Peso',
        'volumen'  => 'Volumen',
        'longitud' => 'Longitud',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────────

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

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeDeEmpresa($query, int $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    public function scopeDeTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Nombre legible del tipo */
    public function tipoLabel(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }
}