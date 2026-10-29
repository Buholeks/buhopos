<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarAccesoEmpresa;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EtiquetaPlantillaPerfilTest extends TestCase
{
    public function test_guarda_perfil_y_rechaza_perfiles_ajenos_o_inactivos(): void
    {
        $this->withoutMiddleware(VerificarAccesoEmpresa::class);
        $user = \Mockery::mock(User::class)->makePartial();
        $user->id = 123;
        $user->empresa_id = 1;
        $user->shouldReceive('tienePermiso')->andReturn(true);
        Sanctum::actingAs($user);
        Schema::create('etiqueta_perfiles', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('empresa_id'); $table->boolean('activo');
        });
        Schema::create('etiqueta_plantillas', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('empresa_id');
            $table->string('nombre'); $table->string('tipo');
            $table->float('ancho_mm'); $table->float('alto_mm');
            $table->json('diseno'); $table->boolean('predeterminada')->default(false);
            $table->timestamps();
        });
        (require database_path('migrations/2026_10_09_000001_add_perfil_id_to_etiqueta_plantillas.php'))->up();
        DB::table('etiqueta_perfiles')->insert([
            ['id' => 1, 'empresa_id' => 1, 'activo' => true],
            ['id' => 2, 'empresa_id' => 2, 'activo' => true],
            ['id' => 3, 'empresa_id' => 1, 'activo' => false],
        ]);
        $data = ['nombre' => '62x50', 'tipo' => 'compra', 'ancho_mm' => 62, 'alto_mm' => 50,
            'diseno' => ['elementos' => [['tipo' => 'texto', 'texto' => 'Prueba']]], 'perfil_id' => 1];
        $id = $this->postJson('/api/etiquetas/plantillas', $data)->assertCreated()->assertJsonPath('perfil_id', 1)->json('id');
        $this->assertDatabaseHas('etiqueta_plantillas', ['id' => $id, 'perfil_id' => 1]);
        foreach ([2, 3, 999] as $perfil) {
            $this->putJson("/api/etiquetas/plantillas/$id", array_replace($data, ['perfil_id' => $perfil]))->assertUnprocessable();
        }
        $this->putJson("/api/etiquetas/plantillas/$id", array_replace($data, ['perfil_id' => null]))->assertOk()->assertJsonPath('perfil_id', null);
    }
}
