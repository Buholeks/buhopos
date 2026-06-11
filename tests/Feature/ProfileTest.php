<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use DatabaseTransactions;

    public function test_usuario_actualiza_sus_datos_y_contrasena(): void
    {
        [, , $user] = $this->contexto(false);
        Sanctum::actingAs($user);

        $this->putJson('/api/perfil/usuario', [
            'name' => 'Nombre actualizado',
            'email' => 'perfil-' . uniqid() . '@example.com',
            'current_password' => 'password123',
            'password' => 'nueva-password',
            'password_confirmation' => 'nueva-password',
        ])->assertOk()->assertJsonPath('name', 'Nombre actualizado');

        $this->assertTrue(Hash::check('nueva-password', $user->fresh()->password));
    }

    public function test_usuario_normal_no_puede_editar_empresa_o_sucursal(): void
    {
        [$empresa, $sucursal, $user] = $this->contexto(false);
        $rolSinPermisos = Rol::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Sin permisos de organización',
        ]);
        $user->sucursales()->updateExistingPivot($sucursal->id, ['role_id' => $rolSinPermisos->id]);
        Sanctum::actingAs($user);

        $this->putJson('/api/perfil/empresa', ['nombre' => 'Empresa cambiada'])->assertForbidden();
        $this->putJson('/api/perfil/sucursal', ['nombre' => 'Sucursal cambiada'])->assertForbidden();
    }

    public function test_no_cambia_contrasena_si_la_actual_es_incorrecta(): void
    {
        [, , $user] = $this->contexto(false);
        Sanctum::actingAs($user);

        $this->putJson('/api/perfil/usuario', [
            'name' => $user->name,
            'email' => $user->email,
            'current_password' => 'incorrecta',
            'password' => 'nueva-password',
            'password_confirmation' => 'nueva-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('password123', $user->fresh()->password));
    }

    public function test_super_admin_edita_empresa_y_sucursal_activa(): void
    {
        [$empresa, $sucursal, $user] = $this->contexto(true);
        Sanctum::actingAs($user);

        $this->putJson('/api/perfil/empresa', [
            'nombre' => 'Empresa actualizada',
            'rfc' => 'XAXX010101000',
        ])->assertOk();

        $this->putJson('/api/perfil/sucursal', [
            'nombre' => 'Sucursal actualizada',
            'telefono' => '5551234567',
        ])->assertOk();

        $this->assertSame('Empresa actualizada', $empresa->fresh()->nombre);
        $this->assertSame('Sucursal actualizada', $sucursal->fresh()->nombre);
    }

    public function test_rol_con_permisos_edita_empresa_y_sucursal(): void
    {
        [$empresa, $sucursal, $user] = $this->contexto(false);
        $rol = Rol::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Administrador de organización',
        ]);
        $permisos = collect([
            ['clave' => 'empresa.editar', 'modulo' => 'empresa'],
            ['clave' => 'sucursales.editar', 'modulo' => 'sucursales'],
        ])->map(fn ($data) => Permiso::firstOrCreate(
            ['clave' => $data['clave']],
            ['modulo' => $data['modulo'], 'descripcion' => $data['clave']]
        ));
        $rol->permisos()->sync($permisos->pluck('id'));
        $user->sucursales()->updateExistingPivot($sucursal->id, ['role_id' => $rol->id]);

        Sanctum::actingAs($user);

        $this->putJson('/api/perfil/empresa', ['nombre' => 'Empresa por rol'])->assertOk();
        $this->putJson('/api/perfil/sucursal', ['nombre' => 'Sucursal por rol'])->assertOk();
    }

    private function contexto(bool $superAdmin): array
    {
        $empresa = Empresa::create(['nombre' => 'Empresa perfil', 'activo' => true]);
        $sucursal = Sucursal::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Sucursal perfil',
            'activo' => true,
        ]);
        $user = User::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'name' => 'Usuario perfil',
            'email' => 'usuario-perfil-' . uniqid() . '@example.com',
            'password' => 'password123',
            'activo' => true,
            'es_super_admin' => $superAdmin,
        ]);
        $user->sucursales()->syncWithoutDetaching([$sucursal->id]);

        return [$empresa, $sucursal, $user];
    }
}
