<?php

namespace App\Console\Commands;

use App\Models\SolicitudPagoSuscripcion;
use Illuminate\Console\Command;

class VencerSolicitudesPagoManual extends Command
{
    protected $signature = 'facturacion:vencer-solicitudes-pago';

    protected $description = 'Marca como vencidas las solicitudes de pago manual sin comprobante que pasaron su fecha límite';

    public function handle(): int
    {
        $vencidas = SolicitudPagoSuscripcion::where('estado', 'pendiente')
            ->where('fecha_limite', '<', now())
            ->update(['estado' => 'vencido']);

        $this->info("Solicitudes marcadas como vencidas: {$vencidas}.");

        return self::SUCCESS;
    }
}
