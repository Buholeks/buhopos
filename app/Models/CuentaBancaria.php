<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CuentaBancaria extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cuentas_bancarias';

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'user_id',
        'nombre',
        'banco',
        'numero_cuenta',
        'clabe',
        'titular',
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
}
