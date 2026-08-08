<?php

namespace App\Http\Controllers\Plataforma;

use App\Http\Controllers\Concerns\EjecutaAccionesStripe;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Plan;
use App\Services\StripeBillingService;
use Illuminate\Http\JsonResponse;

class StripeController extends Controller
{
    use EjecutaAccionesStripe;

    public function estado(): JsonResponse
    {
        return response()->json(['configurado' => (bool) config('services.stripe.secret')]);
    }

    public function sincronizarPlan(Plan $plan, StripeBillingService $stripe): JsonResponse
    {
        abort_if($plan->es_prueba, 422, 'El plan de prueba no se sincroniza con Stripe.');

        return $this->ejecutar(fn () => ['plan' => $stripe->sincronizarPlan($plan)]);
    }

    public function checkout(Empresa $empresa, StripeBillingService $stripe): JsonResponse
    {
        return $this->ejecutar(fn () => ['url' => $stripe->checkout($empresa)]);
    }

    public function portal(Empresa $empresa, StripeBillingService $stripe): JsonResponse
    {
        return $this->ejecutar(fn () => ['url' => $stripe->portal($empresa)]);
    }
}
