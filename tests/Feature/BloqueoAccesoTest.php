<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Sucursal;
use App\Models\Suscripcion;
use App\Models\User;
use App\Services\StripeBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\StripeObject;
use Tests\TestCase;

class BloqueoAccesoTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(array $suscripcion, bool $empresaActiva = true): User
    {
        $empresa = Empresa::create(['nombre' => 'Cliente', 'activo' => $empresaActiva, 'stripe_customer_id' => 'cus_prueba']);
        $sucursal = Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Matriz', 'activo' => true]);
        $user = User::create([
            'empresa_id' => $empresa->id, 'sucursal_id' => $sucursal->id, 'name' => 'Admin',
            'email' => 'bloqueo@example.com', 'password' => 'password-seguro', 'activo' => true, 'es_super_admin' => true,
        ]);
        $user->sucursales()->syncWithoutDetaching([$sucursal->id]);
        Suscripcion::create($suscripcion + ['empresa_id' => $empresa->id, 'dias_gracia' => 0]);

        return $user;
    }

    private function remota(int $empresaId, string $estado): object
    {
        return StripeObject::constructFrom([
            'id' => 'sub_prueba', 'customer' => 'cus_prueba', 'status' => $estado,
            'metadata' => ['buhopos_empresa_id' => (string) $empresaId],
            'items' => ['data' => [[
                'price' => ['id' => 'price_x'],
                'current_period_start' => today()->subDays(5)->timestamp,
                'current_period_end' => today()->addDays(25)->timestamp,
            ]]],
        ]);
    }

    public function test_plan_vencido_bloquea_al_terminar_la_gracia(): void
    {
        $user = $this->usuario(['estado' => 'activa', 'fecha_vencimiento' => today()->subDay()]);
        $this->actingAs($user)->getJson('/api/users')->assertStatus(402)->assertJsonPath('codigo', 'SUSCRIPCION_NO_VIGENTE');
        $this->actingAs($user)->getJson('/api/me')->assertOk()->assertJsonPath('acceso_operativo', false);
    }

    public function test_past_due_no_extiende_acceso_al_periodo_impago(): void
    {
        $user = $this->usuario([
            'estado' => 'activa', 'stripe_subscription_id' => 'sub_prueba', 'stripe_status' => 'active',
            'fecha_vencimiento' => today()->subDays(5),
        ]);
        app(StripeBillingService::class)->sincronizarSuscripcion($this->remota($user->empresa_id, 'past_due'));

        $suscripcion = Suscripcion::first();
        $this->assertSame('vencida', $suscripcion->estado);
        $this->assertSame(today()->subDays(5)->toDateString(), $suscripcion->fecha_vencimiento->toDateString());
        $this->actingAs($user)->getJson('/api/users')->assertStatus(402);
    }

    public function test_fallo_de_renovacion_recorta_vencimiento_ya_adelantado(): void
    {
        // customer.subscription.updated (active, periodo nuevo) llegó antes que invoice.payment_failed.
        $user = $this->usuario([
            'estado' => 'activa', 'stripe_subscription_id' => 'sub_prueba', 'stripe_status' => 'active',
            'fecha_vencimiento' => today()->addDays(25),
        ]);
        app(StripeBillingService::class)->registrarFallo((object) [
            'subscription' => 'sub_prueba', 'billing_reason' => 'subscription_cycle', 'created' => today()->subDays(5)->timestamp,
        ]);

        $suscripcion = Suscripcion::first();
        $this->assertSame('vencida', $suscripcion->estado);
        $this->assertSame(today()->subDays(5)->toDateString(), $suscripcion->fecha_vencimiento->toDateString());
        $this->actingAs($user)->getJson('/api/users')->assertStatus(402);
    }

    public function test_fallo_de_cambio_de_plan_no_recorta_periodo_pagado(): void
    {
        $this->usuario([
            'estado' => 'activa', 'stripe_subscription_id' => 'sub_prueba', 'stripe_status' => 'active',
            'fecha_vencimiento' => today()->addDays(25),
        ]);
        app(StripeBillingService::class)->registrarFallo((object) [
            'subscription' => 'sub_prueba', 'billing_reason' => 'subscription_update', 'created' => today()->timestamp,
        ]);

        $suscripcion = Suscripcion::first();
        $this->assertSame('activa', $suscripcion->estado);
        $this->assertTrue($suscripcion->permiteAcceso());
    }

    public function test_empresa_suspendida_solo_accede_a_facturacion(): void
    {
        $user = $this->usuario(['estado' => 'activa', 'fecha_vencimiento' => today()->addMonth()], empresaActiva: false);

        $this->actingAs($user)->getJson('/api/users')->assertStatus(402)->assertJsonPath('codigo', 'EMPRESA_SUSPENDIDA');
        $this->actingAs($user)->getJson('/api/me')->assertOk()->assertJsonPath('acceso_operativo', false);
        $this->actingAs($user)->getJson('/api/facturacion')->assertOk()->assertJsonPath('empresa.activo', false);
        $this->actingAs($user)->postJson('/api/facturacion/sucursales', ['nombre' => 'Otra'])->assertStatus(402);
    }

    public function test_usuario_inactivo_no_accede_ni_a_facturacion(): void
    {
        $user = $this->usuario(['estado' => 'activa', 'fecha_vencimiento' => today()->addMonth()]);
        $user->update(['activo' => false]);

        $this->actingAs($user)->getJson('/api/facturacion')->assertForbidden();
        $this->actingAs($user)->getJson('/api/me')->assertForbidden();
    }
}
