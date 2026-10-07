<?php

namespace App\Services;

use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ComisionAcceso
{
    public static function sucursales(User $user, string $permiso): array
    {
        $query = Sucursal::where('empresa_id', $user->empresa_id);
        if ($user->es_super_admin || ! self::tieneAlgunRol($user)) {
            return $query->pluck('id')->map(fn($id) => (int) $id)->all();
        }

        // Con roles asignados, solo cuentan las sucursales donde su rol tiene el permiso
        // (una sucursal sin rol no hereda acceso a toda la empresa).
        return $user->sucursales()->where('sucursales.empresa_id', $user->empresa_id)
            ->get()->filter(fn($s) => $user->rolEnSucursal((int) $s->id)?->permisos->contains('clave', $permiso))
            ->pluck('id')->map(fn($id) => (int) $id)->all();
    }

    /**
     * Usuario sin rol en ninguna sucursal = acceso total legacy (igual que User::tienePermiso),
     * así que en comisiones ve todas las sucursales de la empresa.
     */
    private static function tieneAlgunRol(User $user): bool
    {
        return DB::table('sucursal_user')
            ->where('user_id', $user->id)
            ->whereNotNull('role_id')
            ->exists();
    }

    public static function puedeConfigurar(User $user): bool
    {
        if ($user->es_super_admin) {
            return true;
        }
        $todas = Sucursal::where('empresa_id', $user->empresa_id)->pluck('id')->all();
        return count($todas) > 0 && count(array_diff($todas, self::sucursales($user, 'comisiones.configurar'))) === 0;
    }
}
