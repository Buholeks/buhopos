<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\CodigoPromocional;
use App\Models\CuentaCobroPlataforma;
use App\Models\Plan;
use App\Models\PlataformaAdministrador;
use App\Models\Sucursal;
use App\Models\Suscripcion;
use App\Models\User;
use App\Services\StripeBillingService;
use App\Services\LimitesSuscripcionService;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlataformaAdministracionTest extends TestCase
{
    use RefreshDatabase;

    public function test_registro_inicia_sesion_con_prueba_de_quince_dias(): void
    {
        $this->postJson('/api/register', [
            'empresa_nombre' => 'Nueva empresa',
            'empresa_correo' => 'contacto@nueva.test',
            'sucursal_nombre' => 'Matriz',
            'usuario_nombre' => 'Propietario',
            'email' => 'propietario@nueva.test',
            'password' => 'password-seguro',
            'password_confirmation' => 'password-seguro',
        ])->assertCreated()
            ->assertJsonPath('redirect_to', '/facturacion');

        $user = User::where('email', 'propietario@nueva.test')->firstOrFail();
        $this->assertTrue($user->activo);
        $this->assertTrue($user->es_super_admin);
        $this->assertNotNull($user->sucursal_id);
        $this->assertTrue((bool) $user->sucursal?->activo);
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('suscripciones', [
            'empresa_id' => $user->empresa_id,
            'estado' => 'prueba',
            'dias_gracia' => 0,
        ]);
        $this->assertSame(today()->addDays(15)->toDateString(), $user->empresa->suscripcion->fecha_vencimiento->toDateString());
        $this->assertSame('Prueba', $user->empresa->suscripcion->plan->nombre);
        $this->assertSame([
            'sucursales_activas' => 1,
            'usuarios_activos' => 1,
            'sucursales_limite' => 1,
            'usuarios_limite' => 1,
        ], app(LimitesSuscripcionService::class)->uso($user->empresa));

        $this->actingAs($user);
        $this->getJson('/api/me')->assertOk()->assertJsonPath('acceso_operativo', true);
        $this->getJson('/api/facturacion')->assertOk();
        $this->getJson('/api/users')->assertOk();

        $plan = Plan::create(['nombre' => 'Elegido en prueba', 'precio_mensual' => 499, 'sucursales_incluidas' => 1, 'usuarios_incluidos' => 2, 'precio_sucursal_adicional' => 0, 'activo' => true]);
        $this->postJson('/api/facturacion/plan', ['plan_id' => $plan->id])->assertOk();
        $this->assertDatabaseHas('suscripciones', ['empresa_id' => $user->empresa_id, 'plan_id' => $plan->id, 'estado' => 'prueba']);
        $this->assertSame(1, app(LimitesSuscripcionService::class)->uso($user->empresa->fresh())['usuarios_limite']);

        $this->travel(16)->days();
        $this->getJson('/api/users')->assertStatus(402)->assertJsonPath('codigo', 'SUSCRIPCION_NO_VIGENTE');
    }

    public function test_cliente_reporta_transferencia_y_plataforma_confirma_suscripcion(): void
    {
        Storage::fake('local');
        $admin = PlataformaAdministrador::create(['nombre' => 'Dueño', 'email' => 'revision@example.com', 'password' => 'password-seguro']);
        $empresa = Empresa::create(['nombre' => 'Cliente transferencia', 'activo' => false]);
        $sucursal = Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Matriz', 'activo' => true]);
        $user = User::create(['empresa_id' => $empresa->id, 'sucursal_id' => $sucursal->id, 'name' => 'Admin', 'email' => 'transferencia@example.com', 'password' => 'password-seguro', 'activo' => true, 'es_super_admin' => true]);
        $plan = Plan::create(['nombre' => 'Manual', 'precio_mensual' => 599, 'sucursales_incluidas' => 1, 'usuarios_incluidos' => 2, 'precio_sucursal_adicional' => 0, 'activo' => true]);
        Suscripcion::create(['empresa_id' => $empresa->id, 'plan_id' => $plan->id, 'estado' => 'pendiente', 'precio_acordado' => 599]);
        CuentaCobroPlataforma::create(['banco' => 'Banco Prueba', 'beneficiario' => 'BuhoPOS', 'clabe' => '012345678901234567', 'activa' => true, 'predeterminada' => true]);

        $solicitud = $this->actingAs($user)->postJson('/api/facturacion/pago-manual', ['metodo' => 'transferencia'])
            ->assertCreated()->assertJsonPath('importe', '599.00')->json();

        $this->actingAs($user)->post("/api/facturacion/pago-manual/{$solicitud['id']}/comprobante", [
            'fecha_reportada' => today()->toDateString(),
            'numero_operacion' => 'SPEI-123',
            'comprobante' => UploadedFile::fake()->image('comprobante.jpg'),
        ])->assertOk()->assertJsonPath('estado', 'en_revision');

        $this->actingAs($admin, 'plataforma')->getJson('/api/plataforma/solicitudes-pago?estado=en_revision')
            ->assertOk()->assertJsonPath('data.0.id', $solicitud['id']);

        $this->actingAs($admin, 'plataforma')->postJson("/api/plataforma/solicitudes-pago/{$solicitud['id']}/revisar", ['accion' => 'confirmar'])
            ->assertOk()->assertJsonPath('estado', 'confirmado');

        $this->actingAs($admin, 'plataforma')->postJson("/api/plataforma/solicitudes-pago/{$solicitud['id']}/revisar", ['accion' => 'confirmar'])
            ->assertUnprocessable();

        $this->assertDatabaseHas('pagos_suscripcion', ['metodo' => 'transferencia', 'referencia' => 'SPEI-123', 'estado' => 'confirmado']);
        $this->assertDatabaseHas('suscripciones', ['empresa_id' => $empresa->id, 'estado' => 'activa', 'modalidad_cobro' => 'manual']);
        $this->assertDatabaseHas('empresas', ['id' => $empresa->id, 'activo' => true]);
        $this->assertSame(1, $empresa->suscripcion->pagos()->count());
    }

    public function test_plan_anual_cobra_precio_con_descuento_y_cubre_un_anio(): void
    {
        $empresa = Empresa::create(['nombre' => 'Cliente anual', 'activo' => true]);
        $sucursal = Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Matriz', 'activo' => true]);
        $user = User::create(['empresa_id' => $empresa->id, 'sucursal_id' => $sucursal->id, 'name' => 'Dueño', 'email' => 'anual@example.com', 'password' => 'password-seguro', 'activo' => true, 'es_super_admin' => true]);
        $plan = Plan::create(['nombre' => 'Básico', 'precio_mensual' => 100, 'precio_anual' => 1000, 'sucursales_incluidas' => 1, 'usuarios_incluidos' => 2, 'precio_sucursal_adicional' => 0, 'activo' => true]);
        Suscripcion::create(['empresa_id' => $empresa->id, 'estado' => 'pendiente']);
        CuentaCobroPlataforma::create(['banco' => 'Banco', 'beneficiario' => 'BuhoPOS', 'clabe' => '012345678901234567', 'activa' => true, 'predeterminada' => true]);

        $this->actingAs($user)->postJson('/api/facturacion/plan', ['plan_id' => $plan->id, 'periodicidad' => 'anual'])
            ->assertOk();

        $solicitud = $this->postJson('/api/facturacion/pago-manual', ['metodo' => 'transferencia'])
            ->assertCreated()
            ->assertJsonPath('importe', '1000.00')
            ->json();

        $this->assertDatabaseHas('suscripciones', [
            'empresa_id' => $empresa->id,
            'plan_id' => $plan->id,
            'periodicidad' => 'anual',
            'precio_acordado' => 1000,
        ]);
        $this->assertSame(today()->toDateString(), substr($solicitud['periodo_inicio'], 0, 10));
        $this->assertSame(today()->addYear()->subDay()->toDateString(), substr($solicitud['periodo_fin'], 0, 10));
    }

    public function test_cliente_cancela_solicitud_manual_y_regresa_a_tarjeta(): void
    {
        $empresa = Empresa::create(['nombre' => 'Cambio método', 'activo' => true]);
        $sucursal = Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Matriz', 'activo' => true]);
        $user = User::create(['empresa_id' => $empresa->id, 'sucursal_id' => $sucursal->id, 'name' => 'Admin', 'email' => 'cambiometodo@example.com', 'password' => 'password-seguro', 'activo' => true, 'es_super_admin' => true]);
        $plan = Plan::create(['nombre' => 'Base manual', 'precio_mensual' => 399, 'sucursales_incluidas' => 1, 'usuarios_incluidos' => 2, 'precio_sucursal_adicional' => 0, 'activo' => true]);
        Suscripcion::create(['empresa_id' => $empresa->id, 'plan_id' => $plan->id, 'estado' => 'activa', 'modalidad_cobro' => 'manual']);
        CuentaCobroPlataforma::create(['banco' => 'Banco', 'beneficiario' => 'BuhoPOS', 'clabe' => '012345678901234567', 'activa' => true, 'predeterminada' => true]);

        $id = $this->actingAs($user)->postJson('/api/facturacion/pago-manual', ['metodo' => 'deposito'])->assertCreated()->json('id');
        $this->actingAs($user)->deleteJson("/api/facturacion/pago-manual/{$id}")->assertOk();
        $this->assertDatabaseHas('solicitudes_pago_suscripcion', ['id' => $id, 'estado' => 'cancelado']);

        $this->actingAs($user)->postJson('/api/facturacion/volver-tarjeta')
            ->assertOk()->assertJsonPath('requiere_checkout', true);
        $this->assertDatabaseHas('suscripciones', ['empresa_id' => $empresa->id, 'modalidad_cobro' => 'stripe']);
    }

    public function test_administrador_de_plataforma_inicia_sesion_y_consulta_dashboard(): void
    {
        $admin = PlataformaAdministrador::create([
            'nombre' => 'Dueño BuhoPOS',
            'email' => 'dueno@example.com',
            'password' => 'password-seguro',
        ]);

        $this->postJson('/api/plataforma/login', [
            'email' => 'dueno@example.com',
            'password' => 'password-seguro',
        ])->assertOk()->assertJsonPath('email', 'dueno@example.com');

        $this->actingAs($admin, 'plataforma')->getJson('/api/plataforma/dashboard')->assertOk()->assertJsonStructure([
            'empresas', 'empresas_activas', 'registros_pendientes', 'ingresos_mes',
        ]);
    }

    public function test_suscripcion_suspendida_bloquea_api_del_cliente(): void
    {
        $empresa = Empresa::create(['nombre' => 'Cliente', 'activo' => true]);
        $sucursal = Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Matriz', 'activo' => true]);
        $user = User::create([
            'empresa_id' => $empresa->id, 'sucursal_id' => $sucursal->id,
            'name' => 'Admin cliente', 'email' => 'cliente@example.com',
            'password' => 'password-seguro', 'activo' => true, 'es_super_admin' => true,
        ]);
        $user->sucursales()->attach($sucursal->id);
        Suscripcion::create(['empresa_id' => $empresa->id, 'estado' => 'suspendida']);

        $this->actingAs($user)->getJson('/api/users')
            ->assertStatus(402)->assertJsonPath('codigo', 'SUSCRIPCION_NO_VIGENTE');

        $this->actingAs($user)->getJson('/api/me')
            ->assertOk()->assertJsonPath('acceso_operativo', false);

        $this->actingAs($user)->getJson('/api/facturacion')
            ->assertOk()->assertJsonPath('puede_gestionar', true);
    }

    public function test_pago_confirmado_activa_y_extiende_suscripcion(): void
    {
        $admin = PlataformaAdministrador::create(['nombre' => 'Dueño', 'email' => 'admin@example.com', 'password' => 'password-seguro']);
        $empresa = Empresa::create(['nombre' => 'Cliente', 'activo' => false]);
        $plan = Plan::create(['nombre' => 'Base', 'precio_mensual' => 499, 'sucursales_incluidas' => 1, 'precio_sucursal_adicional' => 0, 'activo' => true]);
        Suscripcion::create(['empresa_id' => $empresa->id, 'plan_id' => $plan->id, 'estado' => 'pendiente']);

        $this->actingAs($admin, 'plataforma')->postJson("/api/plataforma/empresas/{$empresa->id}/pagos", [
            'importe' => 499, 'fecha_pago' => '2026-08-05', 'periodo_inicio' => '2026-08-05',
            'periodo_fin' => '2026-09-04', 'metodo' => 'transferencia', 'estado' => 'confirmado',
        ])->assertCreated();

        $this->assertDatabaseHas('suscripciones', ['empresa_id' => $empresa->id, 'estado' => 'activa']);
        $this->assertSame('2026-09-04', $empresa->suscripcion()->first()->fecha_vencimiento->toDateString());
        $this->assertDatabaseHas('empresas', ['id' => $empresa->id, 'activo' => true]);
    }

    public function test_pago_manual_duplicado_con_misma_clave_no_se_repite(): void
    {
        $admin = PlataformaAdministrador::create(['nombre' => 'Dueño', 'email' => 'idem@example.com', 'password' => 'password-seguro']);
        $empresa = Empresa::create(['nombre' => 'Cliente idempotente', 'activo' => false]);
        $plan = Plan::create(['nombre' => 'Base', 'precio_mensual' => 499, 'sucursales_incluidas' => 1, 'precio_sucursal_adicional' => 0, 'activo' => true]);
        Suscripcion::create(['empresa_id' => $empresa->id, 'plan_id' => $plan->id, 'estado' => 'pendiente']);

        $payload = [
            'importe' => 499, 'fecha_pago' => '2026-08-05', 'periodo_inicio' => '2026-08-05',
            'periodo_fin' => '2026-09-04', 'metodo' => 'transferencia', 'estado' => 'confirmado',
            'idempotency_key' => 'clave-prueba-123',
        ];

        $primera = $this->actingAs($admin, 'plataforma')->postJson("/api/plataforma/empresas/{$empresa->id}/pagos", $payload)
            ->assertCreated()->json();

        $segunda = $this->actingAs($admin, 'plataforma')->postJson("/api/plataforma/empresas/{$empresa->id}/pagos", $payload)
            ->assertCreated()->json();

        $this->assertSame($primera['id'], $segunda['id']);
        $this->assertSame(1, $empresa->suscripcion()->first()->pagos()->count());
    }

    public function test_evento_de_suscripcion_stripe_sincroniza_periodo_y_estado(): void
    {
        $empresa = Empresa::create(['nombre' => 'Cliente Stripe', 'activo' => true]);
        Suscripcion::create(['empresa_id' => $empresa->id, 'estado' => 'pendiente']);
        $inicio = now()->startOfDay()->timestamp;
        $fin = now()->addMonth()->startOfDay()->timestamp;
        $objeto = \Stripe\StripeObject::constructFrom([
            'id' => 'sub_prueba_123', 'status' => 'active', 'cancel_at_period_end' => false,
            'metadata' => ['buhopos_empresa_id' => (string) $empresa->id],
            'items' => ['data' => [['current_period_start' => $inicio, 'current_period_end' => $fin]]],
        ]);

        app(StripeBillingService::class)->sincronizarSuscripcion($objeto);

        $this->assertDatabaseHas('suscripciones', [
            'empresa_id' => $empresa->id, 'stripe_subscription_id' => 'sub_prueba_123',
            'stripe_status' => 'active', 'estado' => 'activa',
        ]);
        $this->assertSame(
            now()->addMonth()->startOfDay()->toDateString(),
            $empresa->suscripcion()->first()->fecha_vencimiento->toDateString()
        );
    }

    public function test_webhook_rechaza_firma_invalida(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_prueba']);
        $this->withHeader('Stripe-Signature', 'firma-invalida')
            ->postJson('/api/stripe/webhook', ['id' => 'evt_falso'])
            ->assertBadRequest();
    }

    public function test_plan_impide_exceder_sucursales_y_usuarios_activos(): void
    {
        $empresa = Empresa::create(['nombre' => 'Cliente limitado', 'activo' => true]);
        $plan = Plan::create(['nombre' => 'Uno', 'precio_mensual' => 299, 'sucursales_incluidas' => 1, 'usuarios_incluidos' => 1, 'precio_sucursal_adicional' => 0, 'activo' => true]);
        Suscripcion::create(['empresa_id' => $empresa->id, 'plan_id' => $plan->id, 'estado' => 'activa']);
        Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Matriz', 'activo' => true]);
        User::create(['empresa_id' => $empresa->id, 'name' => 'Único', 'email' => 'unico@example.com', 'password' => 'password-seguro', 'activo' => true]);

        $servicio = app(LimitesSuscripcionService::class);
        try {
            $servicio->validarNuevaSucursal($empresa);
            $this->fail('Debió rechazar una sucursal adicional.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('sucursales', $e->errors());
        }
        $this->expectException(ValidationException::class);
        $servicio->validarNuevoUsuario($empresa);
    }

    public function test_facturacion_muestra_planes_uso_y_sucursales(): void
    {
        $empresa = Empresa::create(['nombre' => 'Cliente', 'activo' => true]);
        $plan = Plan::create(['nombre' => 'Base', 'precio_mensual' => 499, 'sucursales_incluidas' => 2, 'usuarios_incluidos' => 3, 'precio_sucursal_adicional' => 0, 'activo' => true]);
        $sucursal = Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Matriz', 'activo' => true]);
        $user = User::create(['empresa_id' => $empresa->id, 'sucursal_id' => $sucursal->id, 'name' => 'Admin', 'email' => 'admincliente@example.com', 'password' => 'password-seguro', 'activo' => true, 'es_super_admin' => true]);
        Suscripcion::create(['empresa_id' => $empresa->id, 'plan_id' => $plan->id, 'estado' => 'activa']);

        $this->actingAs($user)->getJson('/api/facturacion')->assertOk()
            ->assertJsonPath('uso.sucursales_activas', 1)
            ->assertJsonPath('uso.usuarios_activos', 1)
            ->assertJsonPath('planes.0.id', $plan->id)
            ->assertJsonPath('sucursales.0.id', $sucursal->id);
    }

    public function test_desactivar_sucursal_reasigna_usuario_a_otra_sucursal_activa(): void
    {
        $empresa = Empresa::create(['nombre' => 'Cliente', 'activo' => true]);
        $plan = Plan::create(['nombre' => 'Dos sucursales', 'precio_mensual' => 699, 'sucursales_incluidas' => 2, 'usuarios_incluidos' => 3, 'precio_sucursal_adicional' => 0, 'activo' => true]);
        Suscripcion::create(['empresa_id' => $empresa->id, 'plan_id' => $plan->id, 'estado' => 'activa', 'fecha_vencimiento' => today()->addMonth()]);
        $principal = Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Principal', 'activo' => true]);
        $alternativa = Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Alternativa', 'activo' => true]);
        $user = User::create(['empresa_id' => $empresa->id, 'sucursal_id' => $principal->id, 'name' => 'Admin', 'email' => 'multi@example.com', 'password' => 'password-seguro', 'activo' => true, 'es_super_admin' => true]);
        $user->sucursales()->attach([$principal->id, $alternativa->id]);

        $this->actingAs($user)->putJson("/api/facturacion/sucursales/{$principal->id}", ['activo' => false])->assertOk();

        $this->assertSame($alternativa->id, $user->fresh()->sucursal_id);
        $this->assertFalse($user->fresh()->sucursales()->whereKey($principal->id)->exists());
        $this->assertDatabaseHas('sucursales', ['id' => $principal->id, 'activo' => false]);
    }

    public function test_superadministrador_crea_sucursales_hasta_el_limite_del_plan(): void
    {
        $empresa = Empresa::create(['nombre' => 'Empresa multisucursal', 'activo' => true]);
        $plan = Plan::create(['nombre' => 'Plan dos', 'precio_mensual' => 799, 'sucursales_incluidas' => 2, 'usuarios_incluidos' => 3, 'precio_sucursal_adicional' => 0, 'activo' => true]);
        $principal = Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Principal', 'activo' => true]);
        $user = User::create(['empresa_id' => $empresa->id, 'sucursal_id' => $principal->id, 'name' => 'Dueño', 'email' => 'sucursales@example.com', 'password' => 'password-seguro', 'activo' => true, 'es_super_admin' => true]);
        $user->sucursales()->attach($principal->id);
        Suscripcion::create(['empresa_id' => $empresa->id, 'plan_id' => $plan->id, 'estado' => 'activa', 'fecha_vencimiento' => today()->addMonth()]);

        $this->actingAs($user)->postJson('/api/facturacion/sucursales', [
            'nombre' => 'Norte', 'direccion' => 'Zona norte',
        ])->assertCreated()->assertJsonPath('nombre', 'Norte');

        $this->postJson('/api/facturacion/sucursales', ['nombre' => 'Sur'])
            ->assertUnprocessable()->assertJsonPath('errors.sucursales.0', 'Tu plan ya alcanzó el límite de sucursales activas.');
        $this->assertSame(2, $empresa->sucursales()->where('activo', true)->count());
    }

    public function test_empresa_sin_plan_puede_seleccionarlo_antes_de_pagar(): void
    {
        $empresa = Empresa::create(['nombre' => 'Empresa nueva', 'activo' => true]);
        $sucursal = Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Matriz', 'activo' => true]);
        $user = User::create(['empresa_id' => $empresa->id, 'sucursal_id' => $sucursal->id, 'name' => 'Dueño', 'email' => 'nuevoplan@example.com', 'password' => 'password-seguro', 'activo' => true, 'es_super_admin' => true]);
        $plan = Plan::create(['nombre' => 'Inicial', 'precio_mensual' => 399, 'sucursales_incluidas' => 1, 'usuarios_incluidos' => 2, 'precio_sucursal_adicional' => 0, 'activo' => true]);
        Suscripcion::create(['empresa_id' => $empresa->id, 'estado' => 'pendiente']);

        $this->actingAs($user)->postJson('/api/facturacion/plan', ['plan_id' => $plan->id])
            ->assertOk()->assertJsonPath('tipo', 'seleccion');

        $this->assertDatabaseHas('suscripciones', ['empresa_id' => $empresa->id, 'plan_id' => $plan->id, 'estado' => 'pendiente']);
    }

    public function test_codigo_promocional_se_canjea_una_sola_vez_y_otorga_acceso(): void
    {
        $empresa = Empresa::create(['nombre' => 'Promoción', 'activo' => false]);
        $sucursal = Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Matriz', 'activo' => true]);
        $user = User::create(['empresa_id' => $empresa->id, 'sucursal_id' => $sucursal->id, 'name' => 'Dueño', 'email' => 'promo@example.com', 'password' => 'password-seguro', 'activo' => true, 'es_super_admin' => true]);
        $plan = Plan::create(['nombre' => 'Regalo', 'precio_mensual' => 499, 'sucursales_incluidas' => 1, 'usuarios_incluidos' => 1, 'precio_sucursal_adicional' => 0, 'activo' => true]);
        $codigo = CodigoPromocional::create(['codigo' => 'BUHO-REGALO30', 'plan_id' => $plan->id, 'duracion_tipo' => 'dias', 'duracion_cantidad' => 30, 'activo' => true]);

        $this->actingAs($user)->postJson('/api/facturacion/codigo-promocional', ['codigo' => 'buho-regalo30'])
            ->assertOk()->assertJsonPath('mensaje', 'Código aplicado correctamente.');
        $this->assertSame(today()->addDays(30)->toDateString(), $empresa->suscripcion()->first()->acceso_promocional_hasta->toDateString());
        $this->assertSame('activa', $empresa->suscripcion()->first()->estado);
        $this->assertSame($plan->id, $empresa->suscripcion()->first()->plan_id);
        $this->assertTrue($empresa->suscripcion()->first()->permiteAcceso());
        $this->assertDatabaseHas('codigos_promocionales', ['id' => $codigo->id, 'canjeado_por_empresa_id' => $empresa->id, 'activo' => false]);

        $this->actingAs($user)->postJson('/api/facturacion/codigo-promocional', ['codigo' => 'BUHO-REGALO30'])
            ->assertUnprocessable()->assertJsonPath('errors.codigo.0', 'El código ya fue canjeado.');
    }

    public function test_promocion_en_stripe_comienza_despues_del_periodo_ya_pagado(): void
    {
        $empresa = Empresa::create(['nombre' => 'Cliente Stripe promo', 'activo' => true]);
        $sucursal = Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Matriz', 'activo' => true]);
        $user = User::create(['empresa_id' => $empresa->id, 'sucursal_id' => $sucursal->id, 'name' => 'Dueño', 'email' => 'stripepromo@example.com', 'password' => 'password-seguro', 'activo' => true, 'es_super_admin' => true]);
        $plan = Plan::create(['nombre' => 'Stripe', 'precio_mensual' => 499, 'sucursales_incluidas' => 1, 'usuarios_incluidos' => 1, 'precio_sucursal_adicional' => 0, 'activo' => true]);
        Suscripcion::create(['empresa_id' => $empresa->id, 'plan_id' => $plan->id, 'estado' => 'activa', 'stripe_subscription_id' => 'sub_promo', 'stripe_status' => 'active', 'fecha_vencimiento' => today()->addDays(10)]);
        CodigoPromocional::create(['codigo' => 'BUHO-STRIPE30', 'plan_id' => $plan->id, 'duracion_tipo' => 'dias', 'duracion_cantidad' => 30, 'activo' => true]);

        $stripe = \Mockery::mock(StripeBillingService::class)->makePartial();
        $stripe->shouldReceive('aplicarAccesoPromocional')->once()->withArgs(fn ($suscripcion, $hasta) =>
            $suscripcion->stripe_subscription_id === 'sub_promo' && $hasta->toDateString() === today()->addDays(40)->toDateString()
        );
        $this->app->instance(StripeBillingService::class, $stripe);

        $this->actingAs($user)->postJson('/api/facturacion/codigo-promocional', ['codigo' => 'BUHO-STRIPE30'])->assertOk();
        $this->assertSame(today()->addDays(40)->toDateString(), $empresa->suscripcion()->first()->acceso_promocional_hasta->toDateString());
    }

    public function test_webhook_valido_se_registra_y_duplicado_no_se_reprocesa(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_prueba']);
        $payload = json_encode(['id' => 'evt_unico_123', 'object' => 'event', 'type' => 'ping.prueba', 'livemode' => false, 'data' => ['object' => ['id' => 'obj_123', 'object' => 'test']]], JSON_UNESCAPED_SLASHES);
        $timestamp = time();
        $firma = hash_hmac('sha256', "{$timestamp}.{$payload}", 'whsec_prueba');
        $server = ['HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$firma}", 'CONTENT_TYPE' => 'application/json'];

        $this->call('POST', '/api/stripe/webhook', [], [], [], $server, $payload)
            ->assertOk()->assertJsonPath('duplicado', false);
        $this->call('POST', '/api/stripe/webhook', [], [], [], $server, $payload)
            ->assertOk()->assertJsonPath('duplicado', true);

        $this->assertDatabaseHas('stripe_eventos', ['stripe_event_id' => 'evt_unico_123', 'estado' => 'procesado', 'intentos' => 1]);
        $this->assertSame(1, \DB::table('stripe_eventos')->where('stripe_event_id', 'evt_unico_123')->count());
    }

    public function test_comando_concilia_suscripciones_vinculadas_a_stripe(): void
    {
        $empresa = Empresa::create(['nombre' => 'Conciliación', 'activo' => true]);
        $suscripcion = Suscripcion::create(['empresa_id' => $empresa->id, 'estado' => 'activa', 'stripe_subscription_id' => 'sub_conciliar']);
        $billing = \Mockery::mock(StripeBillingService::class);
        $billing->shouldReceive('conciliarSuscripcion')->once()->withArgs(fn ($item) => $item->id === $suscripcion->id)->andReturn($suscripcion);
        $this->app->instance(StripeBillingService::class, $billing);

        $this->artisan('stripe:conciliar-suscripciones')->assertSuccessful()
            ->expectsOutputToContain('1 correctas, 0 con error');
    }
}
