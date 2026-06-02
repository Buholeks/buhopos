<?php

namespace App\Models;

use App\Support\PublicImageStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Modelo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'modelos';

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'user_id',
        'marca_id',
        'nombre',
        'imagen',
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

    /** Marca a la que pertenece */
    public function marca()
    {
        return $this->belongsTo(Marca::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /** Filtrar por empresa */
    public function scopeDeEmpresa($query, int $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** Ruta pública de la imagen */
    public function imagenUrl(): ?string
    {
        return PublicImageStorage::url($this->imagen);
    }
}
