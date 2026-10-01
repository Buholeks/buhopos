<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Suscripcion;
use App\Services\StripeBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\StripeClient;
use Stripe\StripeObject;
use Tests\TestCase;

class ConfirmarCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function servicio(Empresa $empresa, string $customer = 'cus_test', string $status = 'complete'): StripeBillingService
    {
        $sesion = StripeObject::constructFrom([
            'customer' => $customer, 'client_reference_id' => (string) $empresa->id,
            'mode' => 'subscription', 'status' => $status, 'payment_status' => 'paid', 'subscription' => 'sub_test',
        ]);
        $remota = StripeObject::constructFrom([
            'id' => 'sub_test', 'status' => 'active',
            'metadata' => ['buhopos_empresa_id' => (string) $empresa->id],
            'items' => ['data' => [['current_period_start' => today()->timestamp, 'current_period_end' => today()->addMonth()->timestamp]]],
            'latest_invoice' => [
                'id' => 'in_test', 'status' => 'paid', 'subscription' => 'sub_test', 'customer' => 'cus_test',
                'period_start' => today()->timestamp, 'period_end' => today()->addMonth()->timestamp, 'amount_paid' => 49900,
            ],
        ]);
        $sessions = \Mockery::mock();
        $sessions->shouldReceive('retrieve')->with('cs_test', [])->andReturn($sesion);
        $subscriptions = \Mockery::mock();
        if ($customer === 'cus_test' && $status === 'complete') {
            $subscriptions->shouldReceive('retrieve')->with('sub_test', \Mockery::type('array'))->andReturn($remota);
        }
        $client = \Mockery::mock(StripeClient::class);
        $client->shouldReceive('getService')->with('checkout')->andReturn((object) ['sessions' => $sessions]);
        $client->shouldReceive('getService')->with('subscriptions')->andReturn($subscriptions);
        $service = \Mockery::mock(StripeBillingService::class)->makePartial();
        $service->shouldReceive('cliente')->andReturn($client);

        return $service;
    }

    public function test_confirma_suscripcion_vencida_sin_webhook_y_no_duplica_pago(): void
    {
        $empresa = Empresa::create(['nombre' => 'Stripe', 'activo' => true, 'stripe_customer_id' => 'cus_test']);
        $local = Suscripcion::create(['empresa_id' => $empresa->id, 'estado' => 'vencida', 'fecha_vencimiento' => today()->subDays(10), 'dias_gracia' => 0]);
        $service = $this->servicio($empresa);
        $service->confirmarCheckout($empresa, 'cs_test');
        $service->confirmarCheckout($empresa, 'cs_test');
        $local->refresh();
        $this->assertSame('activa', $local->estado);
        $this->assertSame('sub_test', $local->stripe_subscription_id);
        $this->assertSame(today()->addMonth()->toDateString(), $local->fecha_vencimiento->toDateString());
        $this->assertTrue($local->permiteAcceso());
        $this->assertSame(1, $local->pagos()->count());
    }

    public function test_rechaza_pago_de_otra_empresa(): void
    {
        $empresa = Empresa::create(['nombre' => 'Stripe', 'stripe_customer_id' => 'cus_test']);
        $this->expectException(\RuntimeException::class);
        $this->servicio($empresa, 'cus_ajeno')->confirmarCheckout($empresa, 'cs_test');
    }

    public function test_no_activa_checkout_incompleto(): void
    {
        $empresa = Empresa::create(['nombre' => 'Stripe', 'stripe_customer_id' => 'cus_test']);
        $this->expectException(\RuntimeException::class);
        $this->servicio($empresa, status: 'open')->confirmarCheckout($empresa, 'cs_test');
    }
}
