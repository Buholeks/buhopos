<?php

namespace App\Http\Controllers;

use App\Services\StripeBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Throwable;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeBillingService $billing): JsonResponse
    {
        $secret = config('services.stripe.webhook_secret');
        if (! $secret) {
            return response()->json(['message' => 'Webhook de Stripe no configurado.'], 503);
        }

        try {
            $evento = Webhook::constructEvent($request->getContent(), (string) $request->header('Stripe-Signature'), $secret);
        } catch (UnexpectedValueException|SignatureVerificationException) {
            return response()->json(['message' => 'Firma de Stripe inválida.'], 400);
        }

        $objeto = $evento->data->object;
        DB::table('stripe_eventos')->insertOrIgnore([
            'stripe_event_id' => $evento->id, 'tipo' => $evento->type, 'estado' => 'recibido',
            'objeto_id' => $objeto->id ?? null, 'livemode' => (bool) ($evento->livemode ?? false),
            'recibido_en' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Solo se usa una transacción corta para reclamar el evento (evita duplicados);
        // el procesamiento en sí (que llama a la API de Stripe) corre fuera de la
        // transacción para no sostener el lock de la fila durante una llamada de red.
        $yaProcesado = DB::transaction(function () use ($evento) {
            $registro = DB::table('stripe_eventos')->where('stripe_event_id', $evento->id)->lockForUpdate()->first();
            if ($registro->estado === 'procesado') {
                return true;
            }
            DB::table('stripe_eventos')->where('id', $registro->id)->update([
                'estado' => 'procesando', 'intentos' => DB::raw('intentos + 1'), 'ultimo_error' => null, 'updated_at' => now(),
            ]);

            return false;
        });

        if ($yaProcesado) {
            return response()->json(['recibido' => true, 'duplicado' => true]);
        }

        try {
            match ($evento->type) {
                'checkout.session.completed' => $this->checkoutCompletado($objeto, $billing),
                'customer.subscription.created', 'customer.subscription.updated', 'customer.subscription.deleted' => $billing->sincronizarSuscripcion($objeto),
                'invoice.paid' => $billing->registrarFacturaPagada($objeto),
                'invoice.payment_failed' => $billing->registrarFallo($objeto),
                default => null,
            };
        } catch (Throwable $e) {
            DB::table('stripe_eventos')->where('stripe_event_id', $evento->id)->update([
                'estado' => 'fallido', 'ultimo_error' => mb_substr($e->getMessage(), 0, 4000), 'updated_at' => now(),
            ]);
            report($e);

            return response()->json(['message' => 'No se pudo procesar el evento. Stripe puede reintentarlo.'], 500);
        }

        DB::table('stripe_eventos')->where('stripe_event_id', $evento->id)->update(['estado' => 'procesado', 'procesado_en' => now(), 'updated_at' => now()]);

        return response()->json(['recibido' => true, 'duplicado' => false]);
    }

    private function checkoutCompletado(object $sesion, StripeBillingService $billing): void
    {
        $empresaId = $sesion->metadata?->buhopos_empresa_id ?? $sesion->client_reference_id ?? null;
        if (! $empresaId) {
            return;
        }
        $customer = is_string($sesion->customer) ? $sesion->customer : ($sesion->customer->id ?? null);
        if ($customer) {
            DB::table('empresas')->where('id', $empresaId)->update(['stripe_customer_id' => $customer]);
        }

        $subscriptionId = is_string($sesion->subscription) ? $sesion->subscription : ($sesion->subscription->id ?? null);
        if (! $subscriptionId) {
            return;
        }

        // El estado real (active/trialing/incomplete/...) se deriva de la suscripción en Stripe,
        // no del hecho de que el checkout se haya completado (eso no garantiza que el cobro se confirmó).
        $subscription = $billing->cliente()->subscriptions->retrieve($subscriptionId, [
            'expand' => ['items.data.price'],
        ]);
        $billing->sincronizarSuscripcion($subscription);
    }
}
