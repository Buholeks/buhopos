<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'name',
        'email',
        'password',
        'activo',
        'es_super_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'activo'            => 'boolean',
            'es_super_admin'    => 'boolean',
        ];
    }

    // ── Relaciones ────────────────────────────────────────────────────────────

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function sucursales()
    {
        return $this->belongsToMany(Sucursal::class, 'sucursal_user')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    public function ventasRegistradas()
    {
        return $this->hasMany(Venta::class, 'user_id');
    }

    public function ventasComoVendedor()
    {
        return $this->hasMany(Venta::class, 'vendedor_id');
    }

    // ── Permisos ──────────────────────────────────────────────────────────────

    /**
     * Devuelve el Rol asignado al usuario en la sucursal indicada,
     * o null si no tiene ninguno (acceso legacy total).
     */
    private array $rolCache = [];

    public function rolEnSucursal(int $sucursalId): ?Rol
    {
        if (!array_key_exists($sucursalId, $this->rolCache)) {
            $registro = $this->sucursales()
                ->where('sucursales.id', $sucursalId)
                ->first();
            $roleId = $registro?->pivot?->role_id;
            $this->rolCache[$sucursalId] = $roleId ? Rol::with('permisos')->find($roleId) : null;
        }
        return $this->rolCache[$sucursalId];
    }

    /**
     * Comprueba si el usuario tiene un permiso específico
     * en su sucursal activa (users.sucursal_id).
     *
     * Reglas:
     *  - es_super_admin → siempre true
     *  - sin rol asignado → true (compatibilidad con usuarios existentes)
     *  - con rol → verifica en la tabla role_permiso
     */
    public function tienePermiso(string $clave): bool
    {
        if ($this->es_super_admin) {
            return true;
        }

        $rol = $this->rolEnSucursal((int) $this->sucursal_id);

        // Sin rol = acceso total (legacy)
        if ($rol === null) {
            return true;
        }

        return $rol->permisos->contains('clave', $clave);
    }

    /**
     * Devuelve el array de claves de permisos para la sucursal activa.
     * Devuelve ['*'] para super admins y usuarios sin rol.
     */
    public function permisosActivos(): array
    {
        if ($this->es_super_admin) {
            return ['*'];
        }

        $rol = $this->rolEnSucursal((int) $this->sucursal_id);

        if ($rol === null) {
            return ['*']; // legacy: sin rol = todo
        }

        return $rol->permisos->pluck('clave')->toArray();
    }
}
