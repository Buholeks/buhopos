<?php

namespace Tests\Feature;

use App\Models\ComisionEsquema;
use App\Models\ComisionMovimiento;
use App\Models\ComisionRegla;
use App\Models\User;
use App\Models\Venta;
use App\Services\ComisionService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CreaTablasComisiones;
use Tests\TestCase;

class ComisionEsquemaControllerTest extends TestCase
{
    use CreaTablasComisiones;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearTablasComisiones();
        $this->withoutMiddleware();

        DB::table('categorias')->insert([
            ['id' => 1, 'empresa_id' => 1, 'nombre' => 'Calzado'],
            ['id' => 2, 'empresa_id' => 1, 'nombre' => 'Accesorios'],
            ['id' => 9, 'empresa_id' => 2, 'nombre' => 'Ajena'],
        ]);
        DB::table('productos')->insert([
            ['id' => 100, 'empresa_id' => 1, 'nombre' => 'Tenis', 'codigo' => 'TN1', 'categoria_id' => 1,
                'precio_venta' => 1000, 'precio1' => 900, 'precio_costo' => 500],
            ['id' => 200, 'empresa_id' => 1, 'nombre' => 'Playera', 'codigo' => 'PL1', 'categoria_id' => null,
                'precio_venta' => 300, 'precio1' => 280, 'precio_costo' => 100],
        ]);

        $this->admin = User::forceCreate(['empresa_id' => 1, 'name' => 'Admin', 'es_super_admin' => true]);
        $this->actingAs($this->admin);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function crearEsquema(string $modo = 'categoria', string $nombre = 'Vendedor piso', string $beneficiario = 'vendedor'): array
    {
        return $this->postJson('/api/comisiones/esquemas', ['nombre' => $nombre, 'beneficiario' => $beneficiario, 'modo' => $modo])
            ->assertCreated()
            ->json();
    }

    private function unico(array $extra = []): array
    {
        return $extra + [
            'aplica_a' => 'categoria', 'referencia_id' => 1, 'base' => 'venta', 'tipo' => 'porcentaje',
            'forma' => 'unico', 'valor' => 5,
            'precios' => [
                ['lista' => 'P1', 'activo' => true, 'tipo' => 'porcentaje', 'valor' => 4],
                ['lista' => 'P3', 'activo' => false],
            ],
        ];
    }

    private function rangos(array $extra = []): array
    {
        return $extra + [
            'aplica_a' => 'categoria', 'referencia_id' => 1, 'base' => 'venta', 'tipo' => 'fijo_por_pieza',
            'forma' => 'rangos', 'fuera_de_rango' => 'usar_siguiente',
            'rangos' => [['desde' => 300, 'valor' => 10], ['desde' => 1000, 'valor' => 15], ['desde' => 2000, 'valor' => 50]],
            'hasta_final' => 9999,
            'precios' => [['lista' => 'P3', 'activo' => false], ['lista' => 'MANUAL', 'activo' => false]],
        ];
    }

    private function general(float $valor = 2): array
    {
        return ['aplica_a' => 'general', 'base' => 'venta', 'tipo' => 'porcentaje', 'forma' => 'unico', 'valor' => $valor];
    }

    private function grupo(int $esquemaId, array $payload)
    {
        return $this->putJson("/api/comisiones/esquemas/{$esquemaId}/grupos", $payload);
    }

    // ── Esquemas ──────────────────────────────────────────────────────────────

    public function test_crea_esquema_con_modo_y_no_permite_cambiarlo(): void
    {
        $e = $this->crearEsquema('producto');
        $this->assertSame('producto', $e['modo']);

        $this->putJson("/api/comisiones/esquemas/{$e['id']}", ['nombre' => 'Otro', 'modo' => 'categoria'])->assertOk();
        $this->assertSame('producto', ComisionEsquema::find($e['id'])->modo);

        $this->postJson('/api/comisiones/esquemas', ['nombre' => 'X', 'beneficiario' => 'vendedor', 'modo' => 'marca'])
            ->assertJsonValidationErrors('modo');
    }

    // ── Grupos: valor único ───────────────────────────────────────────────────

    public function test_grupo_unico_con_tabla_de_precios_y_lo_demas(): void
    {
        $id = $this->crearEsquema()['id'];

        $this->grupo($id, $this->unico())->assertOk()
            ->assertJsonPath('grupos.0.referencia_nombre', 'Calzado')
            ->assertJsonPath('grupos.0.forma', 'unico')
            ->assertJsonPath('grupos.0.precios.1.activo', false);

        $this->grupo($id, $this->general())->assertOk()
            ->assertJsonPath('general.referencia_nombre', 'Lo demás')
            ->assertJsonPath('general.valor', 2);

        $this->assertSame(2, ComisionRegla::count());
    }

    public function test_esquema_solo_admite_reglas_de_su_modo(): void
    {
        $id = $this->crearEsquema('general')['id'];

        $this->grupo($id, $this->unico())->assertJsonValidationErrors('aplica_a');
        $this->grupo($id, $this->general())->assertOk();
    }

    public function test_no_duplica_grupo_al_crear_pero_si_reemplaza_al_editar(): void
    {
        $id = $this->crearEsquema()['id'];
        $this->grupo($id, $this->unico())->assertOk();

        $this->grupo($id, $this->unico(['valor' => 7]))->assertJsonValidationErrors('referencia_id');

        $this->grupo($id, $this->unico(['valor' => 7, 'anterior_referencia_id' => 1, 'precios' => []]))
            ->assertOk()
            ->assertJsonPath('grupos.0.valor', 7)
            ->assertJsonCount(0, 'grupos.0.precios');
        $this->assertSame(1, ComisionRegla::count());
    }

    public function test_editar_cambiando_categoria_retira_la_anterior(): void
    {
        $id = $this->crearEsquema()['id'];
        $this->grupo($id, $this->unico())->assertOk();

        $this->grupo($id, $this->unico(['referencia_id' => 2, 'anterior_referencia_id' => 1]))
            ->assertOk()
            ->assertJsonCount(1, 'grupos')
            ->assertJsonPath('grupos.0.referencia_nombre', 'Accesorios');
    }

    public function test_valida_grupos(): void
    {
        $id = $this->crearEsquema()['id'];

        $this->grupo($id, $this->unico(['referencia_id' => null]))->assertJsonValidationErrors('referencia_id');
        $this->grupo($id, $this->unico(['referencia_id' => 9]))->assertJsonValidationErrors('referencia_id');
        $this->grupo($id, $this->unico(['valor' => 150]))->assertJsonValidationErrors('valor');
        $this->grupo($id, $this->rangos(['rangos' => [['desde' => 1000, 'valor' => 1], ['desde' => 500, 'valor' => 2]]]))
            ->assertJsonValidationErrors('rangos.1.desde');
        $this->grupo($id, $this->rangos(['hasta_final' => 100]))->assertJsonValidationErrors('hasta_final');
        $this->grupo($id, $this->rangos(['rangos' => []]))->assertJsonValidationErrors('rangos');

        $this->assertSame(0, ComisionRegla::count());
    }

    // ── Grupos: rangos ────────────────────────────────────────────────────────

    public function test_rangos_se_encadenan_sin_huecos_y_se_reconstruyen(): void
    {
        $id = $this->crearEsquema()['id'];

        $r = $this->grupo($id, $this->rangos())->assertOk()
            ->assertJsonPath('grupos.0.forma', 'rangos')
            ->assertJsonCount(3, 'grupos.0.rangos')
            ->assertJsonPath('grupos.0.rangos.1.desde', 1000)
            ->assertJsonPath('grupos.0.rangos.2.valor', 50)
            ->assertJsonPath('grupos.0.hasta_final', 9999)
            ->assertJsonCount(2, 'grupos.0.precios');

        $reglas = ComisionRegla::orderBy('precio_min')->get();
        $this->assertSame([[300.0, 999.99], [1000.0, 1999.99], [2000.0, 9999.0]],
            $reglas->map(fn($x) => [(float) $x->precio_min, (float) $x->precio_max])->all());
        $this->assertTrue($reglas->every(fn($x) => $x->precios->where('activo', false)->count() === 2));
    }

    public function test_rangos_calculan_la_comision_correcta(): void
    {
        $id = $this->crearEsquema()['id'];
        $this->grupo($id, $this->rangos())->assertOk();
        $this->grupo($id, $this->general(2))->assertOk();
        $probar = fn(int $prod, float $precio, string $lista = 'P1') => $this->postJson("/api/comisiones/esquemas/{$id}/probar",
            ['producto_id' => $prod, 'precio' => $precio, 'lista' => $lista, 'cantidad' => 1])->json();

        $this->assertSame(10, $probar(100, 999.50)['monto']);          // sin hueco entre 999 y 1000
        $this->assertSame(15, $probar(100, 1000)['monto']);
        $this->assertSame(50, $probar(100, 2500)['monto']);
        $this->assertSame('precio_apagado', $probar(100, 2500, 'P3')['motivo']);
        $this->assertSame(5, $probar(100, 250)['monto']);              // debajo del primer rango ⇒ lo demás 2%
        $this->assertSame(6, $probar(200, 300)['monto']);              // sin categoría ⇒ lo demás 2%
    }

    public function test_rangos_sin_comision_fuera_de_rango(): void
    {
        $id = $this->crearEsquema()['id'];
        $this->grupo($id, $this->rangos(['fuera_de_rango' => 'sin_comision']))->assertOk();
        $this->grupo($id, $this->general(2))->assertOk();

        $this->postJson("/api/comisiones/esquemas/{$id}/probar", ['producto_id' => 100, 'precio' => 250, 'lista' => 'P1', 'cantidad' => 1])
            ->assertJsonPath('motivo', 'fuera_de_rango')
            ->assertJsonPath('monto', 0);
    }

    // ── Eliminar y duplicar ───────────────────────────────────────────────────

    public function test_eliminar_grupo_usado_desactiva_y_el_resto_se_borra(): void
    {
        $id = $this->crearEsquema()['id'];
        $this->grupo($id, $this->rangos())->assertOk();
        $usada = ComisionRegla::orderBy('id')->first();
        ComisionMovimiento::create([
            'empresa_id' => 1, 'user_id' => $this->admin->id, 'beneficiario' => 'vendedor', 'tipo' => 'venta',
            'fecha' => '2026-10-01', 'regla_id' => $usada->id, 'esquema_id' => $id, 'monto' => 10,
        ]);

        $this->deleteJson("/api/comisiones/esquemas/{$id}/grupos", ['aplica_a' => 'categoria', 'referencia_id' => 1])
            ->assertOk()
            ->assertJsonCount(0, 'esquema.grupos');

        $this->assertSame(1, ComisionRegla::count());
        $this->assertFalse((bool) $usada->fresh()->activo);
        $this->deleteJson("/api/comisiones/esquemas/{$id}")->assertStatus(422);
    }

    public function test_duplica_esquema_con_modo_rangos_y_precios(): void
    {
        $id = $this->crearEsquema()['id'];
        $this->grupo($id, $this->rangos())->assertOk();
        $this->grupo($id, $this->general())->assertOk();

        $this->postJson("/api/comisiones/esquemas/{$id}/duplicar", ['nombre' => 'Vendedor piso'])->assertJsonValidationErrors('nombre');

        $this->postJson("/api/comisiones/esquemas/{$id}/duplicar", ['nombre' => 'Vendedor piso - Luis'])
            ->assertCreated()
            ->assertJsonPath('modo', 'categoria')
            ->assertJsonCount(3, 'grupos.0.rangos')
            ->assertJsonCount(2, 'grupos.0.precios')
            ->assertJsonPath('general.valor', 2);

        $this->assertSame(8, ComisionRegla::count());
    }

    // ── Listado, asignación y permisos ────────────────────────────────────────

    public function test_listado_cuenta_grupos_y_usuarios(): void
    {
        $id = $this->crearEsquema()['id'];
        $this->grupo($id, $this->rangos())->assertOk();
        $this->grupo($id, $this->general())->assertOk();
        User::forceCreate(['empresa_id' => 1, 'name' => 'Ana', 'esquema_vendedor_id' => $id]);

        $this->getJson('/api/comisiones/esquemas')->assertOk()
            ->assertJsonPath('0.grupos_count', 1)
            ->assertJsonPath('0.tiene_general', true)
            ->assertJsonPath('0.usuarios_count', 1);
    }

    public function test_asigna_esquemas_validando_tipo(): void
    {
        $vendedor = $this->crearEsquema('general', 'Vendedor', 'vendedor')['id'];
        $cajero = $this->crearEsquema('general', 'Cajero', 'cajero')['id'];
        $empleado = User::forceCreate(['empresa_id' => 1, 'name' => 'Ana']);
        $ajeno = User::forceCreate(['empresa_id' => 2, 'name' => 'Ajeno']);

        $this->putJson("/api/comisiones/usuarios/{$empleado->id}", ['esquema_vendedor_id' => $cajero])
            ->assertJsonValidationErrors('esquema_vendedor_id');

        $this->putJson("/api/comisiones/usuarios/{$empleado->id}", ['esquema_vendedor_id' => $vendedor, 'esquema_cajero_id' => $cajero])
            ->assertOk();
        $this->assertSame($vendedor, (int) $empleado->fresh()->esquema_vendedor_id);
        $this->assertSame($cajero, (int) $empleado->fresh()->esquema_cajero_id);

        $this->putJson("/api/comisiones/usuarios/{$ajeno->id}", ['esquema_vendedor_id' => $vendedor])->assertNotFound();
    }

    public function test_no_ve_esquemas_de_otra_empresa_y_requiere_permiso(): void
    {
        $ajeno = ComisionEsquema::create(['empresa_id' => 2, 'nombre' => 'Otro', 'beneficiario' => 'vendedor', 'modo' => 'general']);
        $this->getJson("/api/comisiones/esquemas/{$ajeno->id}")->assertNotFound();

        $sinPermiso = \Mockery::mock(User::class)->makePartial();
        $sinPermiso->empresa_id = 1;
        $sinPermiso->shouldReceive('tienePermiso')->with('comisiones.configurar')->andReturn(false);
        $this->actingAs($sinPermiso)->getJson('/api/comisiones/esquemas')->assertForbidden();
    }

    // ── De la configuración a la venta ────────────────────────────────────────

    public function test_configuracion_por_rangos_genera_comision_en_venta(): void
    {
        $id = $this->crearEsquema()['id'];
        $this->grupo($id, $this->rangos())->assertOk();
        $this->grupo($id, $this->general(2))->assertOk();
        $vendedor = User::forceCreate(['empresa_id' => 1, 'name' => 'Ana', 'esquema_vendedor_id' => $id]);

        $venta = Venta::create([
            'empresa_id' => 1, 'sucursal_id' => 1, 'user_id' => $vendedor->id, 'vendedor_id' => $vendedor->id,
            'fecha' => '2026-10-01', 'estado' => 'confirmada',
        ]);
        foreach ([[100, 2, 1500, 'P1'], [100, 1, 2500, 'P3'], [200, 1, 300, 'P.Venta']] as [$prod, $cant, $precio, $lista]) {
            $venta->detalles()->create(['producto_id' => $prod, 'cantidad' => $cant, 'precio_venta' => $precio,
                'subtotal' => $cant * $precio, 'lista_precio_usada' => $lista]);
        }

        (new ComisionService())->registrarVenta($venta);

        // 2 pzas × $15 + P3 apagado + 2% de 300
        $this->assertSame(36.0, round((float) ComisionMovimiento::sum('monto'), 2));
    }
}
