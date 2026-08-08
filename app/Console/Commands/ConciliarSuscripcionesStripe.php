<?php

namespace App\Console\Commands;

use App\Models\Suscripcion;
use App\Services\StripeBillingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ConciliarSuscripcionesStripe extends Command
{
    protected $signature = 'stripe:conciliar-suscripciones {--empresa= : ID de una empresa específica}';
    protected $description = 'Compara suscripciones locales con Stripe y repara estados, periodos y último pago';

    public function handle(StripeBillingService $billing): int
    {
        $consulta = Suscripcion::query()->whereNotNull('stripe_subscription_id')->with('empresa:id,nombre');
        if ($this->option('empresa')) $consulta->where('empresa_id', $this->option('empresa'));
        $correctas = 0; $errores = 0;
        $consulta->orderBy('id')->each(function (Suscripcion $suscripcion) use ($billing, &$correctas, &$errores) {
            try {
                $billing->conciliarSuscripcion($suscripcion);
                $correctas++;
            } catch (Throwable $e) {
                $errores++;
                Log::error('Error conciliando suscripción de Stripe', ['empresa_id' => $suscripcion->empresa_id, 'stripe_subscription_id' => $suscripcion->stripe_subscription_id, 'error' => $e->getMessage()]);
                $this->error("Empresa {$suscripcion->empresa_id}: {$e->getMessage()}");
            }
        });
        $this->info("Conciliación Stripe terminada: {$correctas} correctas, {$errores} con error.");
        return $errores ? self::FAILURE : self::SUCCESS;
    }
}
