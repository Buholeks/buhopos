<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_crea_usuario_en_empresa_activa_y_sucursal_seleccionada(): void
    {
        [$empresa, $sucursalActiva, $otraSucursal, $actor] = $this->crearContexto();
        Sanctum::actingAs($actor);

        $response = $this->postJson('/api/users', [
            'name' => 'Usuario nueva sucursal',
            'email' => 'nuevo-usuario-' . uniqid() . '@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'sucursal_id' => $otraSucursal->id,
            'empresa_id' => 999999,
        ])->assertCreated();

        $nuevo = User::findOrFail($response->json('id'));

        $this->assertSame($empresa->id, $nuevo->empresa_id);
        $this->assertSame($otraSucursal->id, $nuevo->sucursal_id);
        $this->assertTrue($nuevo->sucursales()->whereKey($otraSucursal->id)->exists());
        $this->assertFalse($nuevo->sucursales()->whereKey($sucursalActiva->id)->exists());
    }

    public function test_no_permite_asignar_sucursal_de_otra_empresa(): void
    {
        [, , , $actor] = $this->crearContexto();
        $otraEmpresa = Empresa::create(['nombre' => 'Otra empresa', 'activo' => true]);
        $sucursalAjena = Sucursal::create([
            'empresa_id' => $otraEmpresa->id,
            'nombre' => 'Sucursal ajena',
            'activo' => true,
        ]);

        Sanctum::actingAs($actor);

        $this->postJson('/api/users', [
            'name' => 'Usuario inválido',
            'email' => 'usuario-invalido-' . uniqid() . '@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'sucursal_id' => $sucursalAjena->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('sucursal_id');
    }

    public function test_permite_promover_primer_super_admin_cuando_no_existe_ninguno(): void
    {
        [, , , $actor] = $this->crearContexto();
        Sanctum::actingAs($actor);

        $this->putJson("/api/users/{$actor->id}/super-admin", [
            'es_super_admin' => true,
        ])->assertOk()
            ->assertJsonPath('es_super_admin', true);

        $this->assertTrue($actor->fresh()->es_super_admin);
    }

    public function test_solo_super_admin_puede_promover_despues_del_primero(): void
    {
        [$empresa, $sucursalActiva, , $actor] = $this->crearContexto();
        $primerSuperAdmin = User::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursalActiva->id,
            'name' => 'Super administrador',
            'email' => 'super-admin-' . uniqid() . '@example.com',
            'password' => 'password123',
            'activo' => true,
            'es_super_admin' => true,
        ]);
        $primerSuperAdmin->sucursales()->attach($sucursalActiva->id);

        Sanctum::actingAs($actor);

        $this->putJson("/api/users/{$actor->id}/super-admin", [
            'es_super_admin' => true,
        ])->assertForbidden();

        Sanctum::actingAs($primerSuperAdmin);

        $this->putJson("/api/users/{$actor->id}/super-admin", [
            'es_super_admin' => true,
        ])->assertOk();
    }

    public function test_no_permite_retirar_al_unico_super_admin_activo(): void
    {
        [, , , $actor] = $this->crearContexto();
        $actor->update(['es_super_admin' => true]);
        Sanctum::actingAs($actor);

        $this->putJson("/api/users/{$actor->id}/super-admin", [
            'es_super_admin' => false,
        ])->assertUnprocessable();

        $this->assertTrue($actor->fresh()->es_super_admin);
    }

    private function crearContexto(): array
    {
        $empresa = Empresa::create(['nombre' => 'Empresa usuarios', 'activo' => true]);
        $sucursalActiva = Sucursal::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Sucursal activa',
            'activo' => true,
        ]);
        $otraSucursal = Sucursal::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Sucursal seleccionable',
            'activo' => true,
        ]);
        $actor = User::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursalActiva->id,
            'name' => 'Administrador',
            'email' => 'admin-usuarios-' . uniqid() . '@example.com',
            'password' => 'password123',
            'activo' => true,
        ]);
        $actor->sucursales()->attach($sucursalActiva->id);

        return [$empresa, $sucursalActiva, $otraSucursal, $actor];
    }
}
