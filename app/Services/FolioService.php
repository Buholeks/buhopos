<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class FolioService
{
    /**
     * Genera el siguiente folio POS por empresa+sucursal.
     * Formato: TKT-000001
     */
    public static function siguienteTicket(int $empresaId, int $sucursalId, string $serie = 'TKT'): string
    {
        $pad = 6;

        // IMPORTANTE: esto debe ejecutarse dentro de la misma transacción
        $counter = DB::table('folio_counters')
            ->where('empresa_id', $empresaId)
            ->where('sucursal_id', $sucursalId)
            ->where('serie', $serie)
            ->lockForUpdate()
            ->first();

        if (!$counter) {
            DB::table('folio_counters')->insert([
                'empresa_id'    => $empresaId,
                'sucursal_id'   => $sucursalId,
                'serie'         => $serie,
                'ultimo_numero' => 1,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            $numero = 1;
        } else {
            $numero = (int) $counter->ultimo_numero + 1;

            DB::table('folio_counters')
                ->where('id', $counter->id)
                ->update([
                    'ultimo_numero' => $numero,
                    'updated_at'    => now(),
                ]);
        }

        return $serie . '' . str_pad((string) $numero, $pad, '0', STR_PAD_LEFT);
    }
}
