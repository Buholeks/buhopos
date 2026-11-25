<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\PlataformaAdministrador;
use App\Models\Suscripcion;
use App\Services\EstadoEmpresaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstadoEmpresaTest extends TestCase
{
    use RefreshDatabase;

    public function test_estados_respetan_vencimiento_gracia_promociones_y_suspension(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-25 12:00:00'));
        $empresa = new Empresa(['activo' => true]);
        $empresa->setRelation('suscripcion', null);
        $servicio = new EstadoEmpresaService;
        $this->assertTrue($servicio->obtener($empresa)['acceso_permitido']);
        $this->assertSame('sin_suscripcion', $servicio->obtener($empresa)['suscripcion']);
        $s = new Suscripcion(['estado' => 'activa', 'fecha_vencimiento' => '2026-11-24', 'dias_gracia' => 0]);
        $empresa->setRelation('suscripcion', $s);
        $this->assertSame('vencida', $servicio->obtener($empresa)['suscripcion']);
        $this->assertFalse($servicio->obtener($empresa)['acceso_permitido']);
        $s->dias_gracia = 1;
        $this->assertTrue($servicio->obtener($empresa)['en_gracia']);
        $this->assertTrue($servicio->obtener($empresa)['acceso_permitido']);
        $s->estado = 'suspendida';
        $this->assertFalse($servicio->obtener($empresa)['acceso_permitido']);
        $this->assertSame('suspendida', $servicio->obtener($empresa)['suscripcion']);
        $s->acceso_promocional_hasta = '2026-11-25';
        $this->assertTrue($servicio->obtener($empresa)['acceso_permitido']);
        $empresa->activo = false;
        $this->assertFalse($servicio->obtener($empresa)['acceso_permitido']);
        $empresa->activo = true;
        $s->acceso_promocional_hasta = null;
        $s->estado = 'prueba';
        $s->fecha_vencimiento = '2026-11-25';
        $this->assertSame('Vence hoy', $servicio->obtener($empresa)['vigencia']);
        $this->assertTrue($servicio->obtener($empresa)['por_vencer']);
        $this->assertTrue($servicio->obtener($empresa)['acceso_permitido']);
        $this->travelBack();
    }

    public function test_listado_filtra_cuenta_y_pagina_y_comparte_estado_con_detalle(): void
    {
        $admin = PlataformaAdministrador::create(['nombre' => 'Admin', 'email' => 'estados@example.com', 'password' => 'password-seguro']);
        for ($i = 0; $i < 22; $i++) {
            Empresa::create(['nombre' => 'Cliente '.$i, 'activo' => $i !== 0]);
        }
        $this->actingAs($admin, 'plataforma');
        $this->getJson('/api/plataforma/empresas?page=2')->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonPath('total', 22)
            ->assertJsonPath('contadores.sin_suscripcion', 22)->assertJsonPath('contadores.bloqueadas', 1);
        $respuesta = $this->getJson('/api/plataforma/empresas?estado=bloqueadas')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.estado_resumen.acceso_permitido', false)->json();
        $this->getJson('/api/plataforma/empresas/'.$respuesta['data'][0]['id'])->assertOk()
            ->assertJsonPath('estado_resumen.motivo', $respuesta['data'][0]['estado_resumen']['motivo']);
        $this->getJson('/api/plataforma/empresas?q=Cliente%2021')->assertOk()->assertJsonPath('total', 1);
    }
}
