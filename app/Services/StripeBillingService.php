<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\PagoSuscripcion;
use App\Models\Plan;
use App\Models\Suscripcion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Stripe\StripeClient;

class StripeBillingService
{
    public function cambiarACobroManual(Suscripcion $suscripcion): void
    {
        if ($suscripcion->stripe_subscription_id && in_array($suscripcion->stripe_status, ['active', 'trialing', 'past_due'], true)) {
            $this->cliente()->subscriptions->update($suscripcion->stripe_subscription_id, [
                'cancel_at_period_end' => true,
            ]);
        }

        $suscripcion->update([
            'modalidad_cobro' => 'manual',
            'cancelar_al_final' => (bool) $suscripcion->stripe_subscription_id,
        ]);
    }

    public function cambiarACobroStripe(Suscripcion $suscripcion): array
    {
        if ($suscripcion->stripe_subscription_id && in_array($suscripcion->stripe_status, ['active', 'trialing', 'past_due'], true)) {
            $remota = $this->cliente()->subscriptions->update($suscripcion->stripe_subscription_id, [
                'cancel_at_period_end' => false,
            ]);
            $suscripcion->update(['modalidad_cobro' => 'stripe', 'cancelar_al_final' => false]);
            $this->sincronizarSuscripcion($remota);

            return ['requiere_checkout' => false, 'mensaje' => 'La renovación automática con tarjeta quedó activa nuevamente.'];
        }

        $suscripcion->update(['modalidad_cobro' => 'stripe', 'cancelar_al_final' => false]);

        return ['requiere_checkout' => true, 'mensaje' => 'Selecciona una tarjeta para reactivar la renovación automática.'];
    }

    public function cliente(): StripeClient
    {
        $secret = config('services.stripe.secret');
        if (! $secret) {
            throw new RuntimeException('Stripe no está configurado. Agrega STRIPE_SECRET.');
        }

        return new StripeClient($secret);
    }

    public function sincronizarPlan(Plan $plan): Plan
    {
        $stripe = $this->cliente();
        $metadata = ['buhopos_plan_id' => (string) $plan->id];
        $producto = [
            'name' => $plan->nombre,
            'active' => $plan->activo,
            'metadata' => $metadata,
        ];
        if (filled($plan->descripcion)) {
            $producto['description'] = trim($plan->descripcion);
        }

        if ($plan->stripe_product_id) {
            $stripe->products->update($plan->stripe_product_id, $producto);
            $productId = $plan->stripe_product_id;
        } else {
            $productId = $stripe->products->create($producto)->id;
        }

        $centavos = (int) round(((float) $plan->precio_mensual) * 100);
        $currency = strtolower(config('services.stripe.currency', 'mxn'));
        $crearPrecio = true;

        if ($plan->stripe_price_id) {
            $actual = $stripe->prices->retrieve($plan->stripe_price_id, []);
            $crearPrecio = $actual->unit_amount !== $centavos || $actual->currency !== $currency || $actual->recurring?->interval !== 'month';
        }

        $priceId = $plan->stripe_price_id;
        if ($crearPrecio) {
            $priceId = $stripe->prices->create([
                'product' => $productId, 'currency' => $currency, 'unit_amount' => $centavos,
                'recurring' => ['interval' => 'month'], 'metadata' => $metadata,
            ])->id;
            if ($plan->stripe_price_id) {
                $stripe->prices->update($plan->stripe_price_id, ['active' => false]);
            }
        }

        $plan->update(['stripe_product_id' => $productId, 'stripe_price_id' => $priceId, 'stripe_sincronizado_en' => now()]);

        return $plan->fresh();
    }

    public function asegurarCliente(Empresa $empresa): string
    {
        $stripe = $this->cliente();
        $datos = [
            'name' => $empresa->nombre, 'email' => $empresa->correo ?: null,
            'phone' => $empresa->telefono ?: null, 'metadata' => ['buhopos_empresa_id' => (string) $empresa->id],
        ];
        if ($empresa->stripe_customer_id) {
            $stripe->customers->update($empresa->stripe_customer_id, $datos);

            return $empresa->stripe_customer_id;
        }
        $id = $stripe->customers->create($datos)->id;
        $empresa->update(['stripe_customer_id' => $id]);

        return $id;
    }

    public function checkout(Empresa $empresa): string
    {
        $suscripcion = $this->prepararSuscripcionParaCheckout(
            $empresa,
            mensajeSinPlan: 'Asigna un plan antes de crear el enlace de pago.',
            mensajeYaActiva: 'La empresa ya tiene una suscripción en Stripe. Usa el portal de facturación.',
            mensajeSinSuscripcion: 'Configura y guarda la suscripción de la empresa antes de crear el enlace de pago.',
        );

        $base = rtrim(config('app.url'), '/');
        $session = $this->cliente()->checkout->sessions->create($this->datosBaseCheckout($empresa, $suscripcion) + [
            'success_url' => "{$base}/plataforma/empresas/{$empresa->id}?stripe=success",
            'cancel_url' => "{$base}/plataforma/empresas/{$empresa->id}?stripe=cancel",
        ]);

        return $session->url;
    }

    public function portal(Empresa $empresa): string
    {
        if (! $empresa->stripe_customer_id) {
            throw new RuntimeException('La empresa todavía no tiene cliente en Stripe.');
        }

        return $this->cliente()->billingPortal->sessions->create([
            'customer' => $empresa->stripe_customer_id,
            'return_url' => rtrim(config('app.url'), '/')."/plataforma/empresas/{$empresa->id}",
            'locale' => 'es',
        ])->url;
    }

    public function iniciarActualizacionTarjeta(Empresa $empresa): string
    {
        $customer = $this->asegurarCliente($empresa);
        $intent = $this->cliente()->setupIntents->create([
            'customer' => $customer,
            'payment_method_types' => ['card'],
            'usage' => 'off_session',
            'metadata' => ['buhopos_empresa_id' => (string) $empresa->id],
        ]);

        return $intent->client_secret;
    }

    public function guardarTarjeta(Empresa $empresa, string $setupIntentId): array
    {
        if (! $empresa->stripe_customer_id) {
            throw new RuntimeException('La empresa todavía no tiene un perfil de cobro.');
        }

        $stripe = $this->cliente();
        $intent = $stripe->setupIntents->retrieve($setupIntentId, []);
        if (($intent->customer ?? null) !== $empresa->stripe_customer_id || ($intent->status ?? null) !== 'succeeded') {
            throw new RuntimeException('No fue posible validar la tarjeta. Inténtalo nuevamente.');
        }

        $paymentMethodId = is_string($intent->payment_method)
            ? $intent->payment_method
            : ($intent->payment_method->id ?? null);
        if (! $paymentMethodId) {
            throw new RuntimeException('No se recibió la tarjeta registrada.');
        }

        $stripe->customers->update($empresa->stripe_customer_id, [
            'invoice_settings' => ['default_payment_method' => $paymentMethodId],
        ]);
        $suscripcionId = $empresa->suscripcion?->stripe_subscription_id;
        if ($suscripcionId) {
            $stripe->subscriptions->update($suscripcionId, [
                'default_payment_method' => $paymentMethodId,
            ]);
        }

        $paymentMethod = $stripe->paymentMethods->retrieve($paymentMethodId, []);

        return [
            'marca' => ucfirst((string) ($paymentMethod->card?->brand ?? 'tarjeta')),
            'ultimos_digitos' => $paymentMethod->card?->last4,
            'expira_mes' => $paymentMethod->card?->exp_month,
            'expira_anio' => $paymentMethod->card?->exp_year,
        ];
    }

    public function checkoutEmbebido(Empresa $empresa): string
    {
        $suscripcion = $this->prepararSuscripcionParaCheckout(
            $empresa,
            mensajeSinPlan: 'Tu empresa todavía no tiene un plan asignado. Contacta a soporte.',
            mensajeYaActiva: 'Ya existe una suscripción en Stripe. Usa el portal de facturación para actualizar el método de pago.',
        );

        $session = $this->cliente()->checkout->sessions->create($this->datosBaseCheckout($empresa, $suscripcion) + [
            'ui_mode' => 'embedded_page',
            'return_url' => rtrim(config('app.url'), '/').'/facturacion?checkout=retorno&session_id={CHECKOUT_SESSION_ID}',
        ]);

        return $session->client_secret;
    }

    private function prepararSuscripcionParaCheckout(Empresa $empresa, string $mensajeSinPlan, string $mensajeYaActiva, ?string $mensajeSinSuscripcion = null): Suscripcion
    {
        $suscripcion = $empresa->suscripcion()->with('plan')->first();
        if (! $suscripcion) {
            throw new RuntimeException($mensajeSinSuscripcion ?? $mensajeSinPlan);
        }
        if (! $suscripcion->plan) {
            throw new RuntimeException($mensajeSinPlan);
        }
        if (! $suscripcion->plan->stripe_price_id) {
            $this->sincronizarPlan($suscripcion->plan);
        }
        if ($suscripcion->stripe_subscription_id && in_array($suscripcion->stripe_status, ['active', 'trialing', 'past_due'], true)) {
            throw new RuntimeException($mensajeYaActiva);
        }

        return $suscripcion;
    }

    private function datosBaseCheckout(Empresa $empresa, Suscripcion $suscripcion): array
    {
        return [
            'mode' => 'subscription',
            'customer' => $this->asegurarCliente($empresa),
            // Solo tarjeta: sin esto, Stripe ofrece "Link" (su guardado/autocompletado
            // propio) en el Checkout, igual que ya se desactiva con disableLink en el
            // formulario de actualizar tarjeta.
            'payment_method_types' => ['card'],
            'line_items' => [['price' => $suscripcion->plan->fresh()->stripe_price_id, 'quantity' => 1]],
            'client_reference_id' => (string) $empresa->id,
            'metadata' => ['buhopos_empresa_id' => (string) $empresa->id],
            'subscription_data' => ['metadata' => [
                'buhopos_empresa_id' => (string) $empresa->id,
                'buhopos_plan_id' => (string) $suscripcion->plan_id,
            ]],
            'locale' => 'es',
            'tax_id_collection' => ['enabled' => true],
            'customer_update' => ['name' => 'auto', 'address' => 'auto'],
        ];
    }

    public function cambiarPlan(Empresa $empresa, Plan $nuevoPlan): array
    {
        $suscripcion = $empresa->suscripcion()->with('plan')->first();
        if (! $suscripcion?->stripe_subscription_id || ! $suscripcion->plan) {
            throw new RuntimeException('La empresa no tiene una suscripción activa en Stripe.');
        }
        if ($suscripcion->plan_id === $nuevoPlan->id) {
            throw new RuntimeException('Ese ya es tu plan actual.');
        }
        if (! $nuevoPlan->activo) {
            throw new RuntimeException('El plan seleccionado no está disponible.');
        }
        if (! $nuevoPlan->stripe_price_id) {
            $this->sincronizarPlan($nuevoPlan);
        }

        $stripe = $this->cliente();
        $subscription = $stripe->subscriptions->retrieve($suscripcion->stripe_subscription_id, [
            'expand' => ['items.data.price'],
        ]);
        $item = $subscription->items->data[0] ?? null;
        if (! $item) {
            throw new RuntimeException('Stripe no devolvió el concepto de la suscripción.');
        }

        $esMejora = (float) $nuevoPlan->precio_mensual > (float) $suscripcion->plan->precio_mensual;
        if ($esMejora) {
            if ($suscripcion->stripe_schedule_id) {
                $stripe->subscriptionSchedules->release($suscripcion->stripe_schedule_id, []);
            }
            $actualizada = $stripe->subscriptions->update($subscription->id, [
                'items' => [['id' => $item->id, 'price' => $nuevoPlan->stripe_price_id, 'quantity' => 1]],
                'proration_behavior' => 'always_invoice',
                'payment_behavior' => 'pending_if_incomplete',
                'metadata' => [
                    'buhopos_empresa_id' => (string) $empresa->id,
                    'buhopos_plan_id' => (string) $nuevoPlan->id,
                ],
            ]);
            $suscripcion->update([
                'plan_pendiente_id' => $actualizada->pending_update ? $nuevoPlan->id : null,
                'cambio_plan_en' => $actualizada->pending_update ? today() : null,
                'stripe_schedule_id' => null,
            ]);
            $this->sincronizarSuscripcion($actualizada);

            return $actualizada->pending_update
                ? ['tipo' => 'pendiente_pago', 'mensaje' => 'Stripe no pudo completar el prorrateo. Actualiza el método de pago en el portal para aplicar el cambio.']
                : ['tipo' => 'mejora', 'mensaje' => 'El cambio se aplicó y Stripe cobró el prorrateo correspondiente.'];
        }

        $periodEnd = $item->current_period_end ?? null;
        $periodStart = $item->current_period_start ?? null;
        if (! $periodEnd || ! $periodStart) {
            throw new RuntimeException('Stripe no devolvió el periodo vigente de la suscripción.');
        }

        $scheduleId = $suscripcion->stripe_schedule_id;
        if (! $scheduleId) {
            $scheduleId = $stripe->subscriptionSchedules->create([
                'from_subscription' => $subscription->id,
                'metadata' => ['buhopos_empresa_id' => (string) $empresa->id],
            ])->id;
        }
        $stripe->subscriptionSchedules->update($scheduleId, [
            'end_behavior' => 'release',
            'proration_behavior' => 'none',
            'phases' => [
                [
                    'start_date' => $periodStart,
                    'end_date' => $periodEnd,
                    'items' => [['price' => $item->price->id, 'quantity' => 1]],
                    'proration_behavior' => 'none',
                    'metadata' => ['buhopos_empresa_id' => (string) $empresa->id, 'buhopos_plan_id' => (string) $suscripcion->plan_id],
                ],
                [
                    'start_date' => $periodEnd,
                    'iterations' => 1,
                    'items' => [['price' => $nuevoPlan->stripe_price_id, 'quantity' => 1]],
                    'proration_behavior' => 'none',
                    'metadata' => ['buhopos_empresa_id' => (string) $empresa->id, 'buhopos_plan_id' => (string) $nuevoPlan->id],
                ],
            ],
        ]);
        $suscripcion->update([
            'stripe_schedule_id' => $scheduleId,
            'plan_pendiente_id' => $nuevoPlan->id,
            'cambio_plan_en' => Carbon::createFromTimestamp($periodEnd)->toDateString(),
        ]);

        return ['tipo' => 'reduccion', 'mensaje' => 'El plan cambiará al finalizar el periodo actual.'];
    }

    public function aplicarAccesoPromocional(Suscripcion $suscripcion, Carbon $hasta): void
    {
        if (! $suscripcion->stripe_subscription_id || ! in_array($suscripcion->stripe_status, ['active', 'trialing', 'past_due'], true)) {
            return;
        }
        if ($suscripcion->stripe_schedule_id) {
            throw new RuntimeException('Hay un cambio de plan programado. Cancélalo o espera a que se aplique antes de usar el código promocional.');
        }

        // La fecha local es inclusiva; Stripe reanuda el cobro al iniciar el día siguiente.
        $trialEnd = $hasta->copy()->addDay()->startOfDay();
        if ($trialEnd->greaterThan(now()->addDays(730))) {
            throw new RuntimeException('Stripe permite aplazar esta suscripción un máximo de 730 días.');
        }

        $actualizada = $this->cliente()->subscriptions->update($suscripcion->stripe_subscription_id, [
            'trial_end' => $trialEnd->timestamp,
            'proration_behavior' => 'none',
        ]);
        $this->sincronizarSuscripcion($actualizada);
    }

    public function conciliarSuscripcion(Suscripcion $suscripcion): Suscripcion
    {
        if (! $suscripcion->stripe_subscription_id) {
            return $suscripcion;
        }
        $remota = $this->cliente()->subscriptions->retrieve($suscripcion->stripe_subscription_id, [
            'expand' => ['items.data.price', 'latest_invoice'],
        ]);
        $sincronizada = $this->sincronizarSuscripcion($remota) ?? $suscripcion;
        $factura = $remota->latest_invoice ?? null;
        if (is_object($factura) && ($factura->status ?? null) === 'paid') {
            $this->registrarFacturaPagada($factura);
        }
        if (is_object($factura) && in_array($factura->status ?? null, ['open', 'uncollectible'], true) && ($remota->status ?? null) === 'past_due') {
            $this->registrarFallo($factura);
        }

        return $sincronizada->fresh();
    }

    public function sincronizarSuscripcion(object $objeto): ?Suscripcion
    {
        $empresaId = $objeto->metadata?->buhopos_empresa_id ?? null;
        $suscripcion = Suscripcion::where('stripe_subscription_id', $objeto->id)->first()
            ?: ($empresaId ? Suscripcion::where('empresa_id', $empresaId)->first() : null);
        if (! $suscripcion) {
            return null;
        }

        $item = $objeto->items?->data[0] ?? null;
        $inicio = $item?->current_period_start ?? $objeto->current_period_start ?? null;
        $fin = $item?->current_period_end ?? $objeto->current_period_end ?? null;
        $estado = match ($objeto->status) {
            'active' => 'activa', 'trialing' => 'prueba',
            'past_due', 'unpaid' => 'vencida',
            'canceled', 'incomplete_expired' => 'cancelada',
            'paused' => 'suspendida', default => 'pendiente',
        };
        $planStripeId = $objeto->metadata?->buhopos_plan_id ?? null;
        $tieneCambioPendiente = filled($objeto->pending_update ?? null);
        $planId = ! $tieneCambioPendiente && $planStripeId && Plan::whereKey($planStripeId)->exists()
            ? (int) $planStripeId
            : $suscripcion->plan_id;
        $suscripcion->update([
            'stripe_subscription_id' => $objeto->id, 'stripe_status' => $objeto->status,
            'estado' => $estado, 'cancelar_al_final' => (bool) ($objeto->cancel_at_period_end ?? false),
            'modalidad_cobro' => ($objeto->cancel_at_period_end ?? false) ? $suscripcion->modalidad_cobro : 'stripe',
            'fecha_inicio' => $inicio ? Carbon::createFromTimestamp($inicio)->toDateString() : $suscripcion->fecha_inicio,
            'fecha_vencimiento' => $fin ? Carbon::createFromTimestamp($fin)->toDateString() : $suscripcion->fecha_vencimiento,
            'cancelada_en' => $estado === 'cancelada' ? now() : null,
            'plan_id' => $planId,
            'plan_pendiente_id' => $planId !== $suscripcion->plan_id ? null : $suscripcion->plan_pendiente_id,
            'cambio_plan_en' => $planId !== $suscripcion->plan_id ? null : $suscripcion->cambio_plan_en,
        ]);

        return $suscripcion->fresh();
    }

    public function registrarFacturaPagada(object $factura): ?PagoSuscripcion
    {
        $subscriptionId = $this->subscriptionIdDeFactura($factura);
        $suscripcion = $subscriptionId ? Suscripcion::where('stripe_subscription_id', $subscriptionId)->first() : null;

        if (! $suscripcion) {
            // Stripe no garantiza el orden de entrega de webhooks: invoice.paid puede llegar
            // antes que checkout.session.completed/customer.subscription.created, es decir,
            // antes de que la suscripción local tenga stripe_subscription_id. El customer_id
            // sí queda vinculado a la empresa desde que se crea la sesión de checkout, así que
            // sirve como respaldo para no perder el pago.
            $customerId = is_string($factura->customer ?? null) ? $factura->customer : ($factura->customer->id ?? null);
            $suscripcion = $customerId
                ? Suscripcion::whereHas('empresa', fn ($query) => $query->where('stripe_customer_id', $customerId))->first()
                : null;
        }

        if (! $suscripcion) {
            return null;
        }
        $inicio = $factura->period_start ? Carbon::createFromTimestamp($factura->period_start)->toDateString() : today()->toDateString();
        $fin = $factura->period_end ? Carbon::createFromTimestamp($factura->period_end)->toDateString() : today()->toDateString();

        if ($inicio === $fin && $subscriptionId) {
            // La primera factura de una suscripción nueva a veces reporta un período de
            // un solo instante; se usa el período real de la suscripción en su lugar.
            try {
                $item = $this->cliente()->subscriptions->retrieve($subscriptionId, [])->items->data[0] ?? null;
                if ($item) {
                    $inicio = Carbon::createFromTimestamp($item->current_period_start)->toDateString();
                    $fin = Carbon::createFromTimestamp($item->current_period_end)->toDateString();
                }
            } catch (\Stripe\Exception\ApiErrorException $e) {
                report($e);
            }
        }

        $paymentIntent = is_string($factura->payment_intent ?? null) ? $factura->payment_intent : ($factura->payment_intent->id ?? null);

        return DB::transaction(function () use ($suscripcion, $factura, $inicio, $fin, $paymentIntent) {
            $pago = $suscripcion->pagos()->updateOrCreate(['stripe_invoice_id' => $factura->id], [
                'stripe_payment_intent_id' => $paymentIntent, 'importe' => ((int) $factura->amount_paid) / 100,
                'fecha_pago' => today(), 'periodo_inicio' => $inicio, 'periodo_fin' => $fin,
                'metodo' => 'tarjeta', 'referencia' => $factura->number ?? $factura->id,
                'estado' => 'confirmado', 'notas' => 'Pago recurrente confirmado por Stripe.',
            ]);
            // La primera factura de una suscripción nueva a veces trae period_end == period_start
            // (sin relación con el current_period_end real). No dejar que eso recorte un
            // vencimiento ya fijado correctamente por customer.subscription.* u otro pago.
            $vencimiento = $suscripcion->fecha_vencimiento && $suscripcion->fecha_vencimiento->greaterThan($fin)
                ? $suscripcion->fecha_vencimiento
                : $fin;
            $suscripcion->update(['estado' => 'activa', 'fecha_vencimiento' => $vencimiento, 'stripe_status' => 'active']);
            $suscripcion->empresa()->update(['activo' => true]);

            return $pago;
        });
    }

    public function registrarFallo(object $factura): void
    {
        $subscriptionId = $this->subscriptionIdDeFactura($factura);
        if ($subscriptionId) {
            Suscripcion::where('stripe_subscription_id', $subscriptionId)->update(['estado' => 'vencida', 'stripe_status' => 'past_due']);
        }
    }

    private function subscriptionIdDeFactura(object $factura): ?string
    {
        $valor = $factura->subscription ?? $factura->parent?->subscription_details?->subscription ?? null;

        return is_string($valor) ? $valor : ($valor->id ?? null);
    }
}
