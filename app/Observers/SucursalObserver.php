<?php

namespace App\Observers;

use App\Models\Sucursal;
use App\Models\User;

class SucursalObserver
{
    /**
     * Cuando se crea una sucursal nueva, todos los super admins
     * de esa empresa quedan automáticamente asignados a ella.
     */
    public function created(Sucursal $sucursal): void
    {
        User::where('empresa_id', $sucursal->empresa_id)
            ->where('es_super_admin', true)
            ->each(function (User $user) use ($sucursal) {
                // syncWithoutDetaching no toca sucursales ya asignadas
                $user->sucursales()->syncWithoutDetaching([$sucursal->id]);
            });
    }
}
