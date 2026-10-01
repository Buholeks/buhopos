<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Plan;
use App\Models\PlataformaAdministrador;
use App\Models\Suscripcion;
use App\Services\StripeBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\StripeClient;
use Stripe\StripeObject;
use Tests\TestCase;

class PlataformaStripeTest extends TestCase
{
    use RefreshDatabase;

    private function empresa(bool $vinculada = true): Empresa
    {
        $empresa = Empresa::create(['nombre' => 'Cliente', 'activo' => true, 'stripe_customer_id' => 'cus_prueba']);
        Suscripcion::create([
            'empresa_id' => $empresa->id, 'estado' => 'vencida', 'dias_gracia' => 0,
            'stripe_subscription_id' => $vinculada ? 'sub_prueba' : null,
            'stripe_status' => $vinculada ? 'past_due' : null,
            'fecha_vencimiento' => today()->subDays(10),
        ]);

        return $empresa;
    }

    private function admin(): void
    {
        $this->actingAs(PlataformaAdministrador::create([
            'nombre' => 'Admin', 'email' => 'admin@test.test', 'password' => 'password-seguro',
        ]), 'plataforma');
    }

    private function remota(Empresa $empresa, string $estado = 'active'): object
    {
        return StripeObject::constructFrom([
            'id' => 'sub_prueba', 'customer' => 'cus_prueba', 'status' => $estado,
            'metadata' => ['buhopos_empresa_id' => (string) $empresa->id],
            'items' => ['data' => [[
                'price' => ['id' => 'price_nuevo'],
                'current_period_start' => today()->timestamp,
                'current_period_end' => today()->addMonth()->timestamp,
            ]]],
            'latest_invoice' => [
                'id' => 'in_prueba', 'status' => 'paid', 'subscription' => 'sub_prueba', 'customer' => 'cus_prueba',
                'amount_paid' => 49900, 'period_start' => today()->timestamp, 'period_end' => today()->addMonth()->timestamp,
            ],
        ]);
    }

    private function stripe(object $remota, ?array $lista = null): void
    {
        $subscriptions = \Mockery::mock();
        $subscriptions->shouldReceive('retrieve')->with('sub_prueba', \Mockery::type('array'))->andReturn($remota);
        if ($lista !== null) {
            $collection = \Mockery::mock();
            $collection->shouldReceive('autoPagingIterator')->andReturn(new \ArrayIterator($lista));
            $subscriptions->shouldReceive('all')->with(['customer' => 'cus_prueba', 'status' => 'all', 'limit' => 100])->andReturn($collection);
        }
        $client = \Mockery::mock(StripeClient::class);
        $client->shouldReceive('getService')->with('subscriptions')->andReturn($subscriptions);
        $service = \Mockery::mock(StripeBillingService::class)->makePartial();
        $service->shouldReceive('cliente')->andReturn($client);
        $this->app->instance(StripeBillingService::class, $service);
    }

    public function test_sincroniza_vencida_y_pago_sin_duplicarlo(): void
    {
        $empresa = $this->empresa();
        $this->stripe($this->remota($empresa));
        $this->admin();
        $url = "/api/plataforma/empresas/{$empresa->id}/stripe/sincronizar";
        $this->postJson($url)->assertOk()->assertJsonPath('suscripcion.estado', 'activa')->assertJsonStructure(['verificado_en']);
        $this->postJson($url)->assertOk();
        $this->assertTrue($empresa->suscripcion->fresh()->permiteAcceso());
        $this->assertSame(1, $empresa->suscripcion->pagos()->count());
    }

    public function test_recupera_vinculo_faltante(): void
    {
        $empresa = $this->empresa(false);
        $remota = $this->remota($empresa);
        $this->stripe($remota, [$remota]);
        $this->admin();
        $this->postJson("/api/plataforma/empresas/{$empresa->id}/stripe/sincronizar")
            ->assertOk()->assertJsonPath('suscripcion.stripe_subscription_id', 'sub_prueba');
    }

    public function test_no_elige_entre_varias_suscripciones(): void
    {
        $empresa = $this->empresa(false);
        $remota = $this->remota($empresa);
        $this->stripe($remota, [$remota, $remota]);
        $this->admin();
        $this->postJson("/api/plataforma/empresas/{$empresa->id}/stripe/sincronizar")->assertStatus(422);
        $this->assertNull($empresa->suscripcion->fresh()->stripe_subscription_id);
    }

    public function test_factura_antigua_no_reactiva_cancelada_ni_empresa_suspendida(): void
    {
        $empresa = $this->empresa();
        $empresa->update(['activo' => false]);
        $this->stripe($this->remota($empresa, 'canceled'));
        $this->admin();
        $this->postJson("/api/plataforma/empresas/{$empresa->id}/stripe/sincronizar")
            ->assertOk()->assertJsonPath('suscripcion.estado', 'cancelada');
        $this->assertFalse((bool) $empresa->fresh()->activo);
        $this->assertSame(1, $empresa->suscripcion->pagos()->count());
    }

    public function test_rechaza_cliente_ajeno_sin_modificar_suscripcion(): void
    {
        $empresa = $this->empresa();
        $remota = $this->remota($empresa);
        $remota->customer = 'cus_ajeno';
        $this->stripe($remota);
        $this->admin();
        $this->postJson("/api/plataforma/empresas/{$empresa->id}/stripe/sincronizar")->assertStatus(422);
        $this->assertSame('vencida', $empresa->suscripcion->fresh()->estado);
    }

    public function test_plan_del_precio_prevalece_sobre_metadatos_del_portal(): void
    {
        $empresa = $this->empresa();
        $plan = Plan::create(['nombre' => 'Nuevo', 'precio_mensual' => 499, 'stripe_price_id' => 'price_nuevo', 'activo' => true]);
        $this->stripe($this->remota($empresa));
        $this->admin();
        $this->postJson("/api/plataforma/empresas/{$empresa->id}/stripe/sincronizar")
            ->assertOk()->assertJsonPath('suscripcion.plan_id', $plan->id);
    }

    public function test_impide_editar_cobro_pero_permite_notas_y_gracia(): void
    {
        $empresa = $this->empresa();
        $this->admin();
        $url = "/api/plataforma/empresas/{$empresa->id}/suscripcion";
        foreach (['estado' => 'activa', 'fecha_vencimiento' => today()->addYear()->toDateString(), 'precio_acordado' => 1, 'periodicidad' => 'anual'] as $campo => $valor) {
            $this->putJson($url, [$campo => $valor])->assertUnprocessable()->assertJsonValidationErrors($campo);
        }
        $this->putJson($url, ['notas' => 'Contactar al cliente', 'dias_gracia' => 5])->assertOk();
        $this->assertSame('vencida', $empresa->suscripcion->fresh()->estado);
        $this->getJson("/api/plataforma/empresas/{$empresa->id}")->assertOk()->assertJsonPath('suscripcion_gestionada_por_stripe', true);
        $this->postJson("/api/plataforma/empresas/{$empresa->id}/pagos", [])->assertStatus(422);
    }

    public function test_sincronizacion_requiere_autenticacion_de_plataforma(): void
    {
        $empresa = $this->empresa();
        $this->postJson("/api/plataforma/empresas/{$empresa->id}/stripe/sincronizar")->assertUnauthorized();
    }
}
