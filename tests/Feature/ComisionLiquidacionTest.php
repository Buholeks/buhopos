<?php

namespace Tests\Feature;

use App\Models\ComisionLiquidacion;
use App\Models\ComisionMovimiento;
use App\Models\CorteCaja;
use App\Models\MovimientoCaja;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CreaTablasComisiones;
use Tests\TestCase;

class ComisionLiquidacionTest extends TestCase
{
    use CreaTablasComisiones;

    private User $admin;
    private User $ana;
    private User $beto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearTablasComisiones();
        $this->crearTablasCaja();
        $this->withoutMiddleware();

        DB::table('sucursales')->insert([
            ['id' => 1, 'empresa_id' => 1, 'nombre' => 'Centro'],
            ['id' => 2, 'empresa_id' => 1, 'nombre' => 'Norte'],
        ]);
        DB::table('cuentas_bancarias')->insert(['id' => 5, 'empresa_id' => 1, 'nombre' => 'Nómina', 'activo' => true]);

        $this->admin = User::forceCreate(['empresa_id' => 1, 'sucursal_id' => 1, 'name' => 'Admin', 'es_super_admin' => true]);
        $this->ana = User::forceCreate(['empresa_id' => 1, 'sucursal_id' => 1, 'name' => 'Ana']);
        $this->beto = User::forceCreate(['empresa_id' => 1, 'sucursal_id' => 1, 'name' => 'Beto']);

        // Ana: descuento pendiente de septiembre, ventas y devolución en la 1a quincena, venta en la 2a.
        $this->mov($this->ana, 'devolucion', '2026-09-28', -5);
        $this->mov($this->ana, 'venta', '2026-10-01', 100, 100);
        $this->mov($this->ana, 'venta', '2026-10-10', 50, 101);
        $this->mov($this->ana, 'devolucion', '2026-10-12', -20, 100);
        $this->mov($this->ana, 'venta', '2026-10-20', 40, 102);
        // Beto solo tiene un descuento pendiente.
        $this->mov($this->beto, 'devolucion', '2026-10-03', -30);

        $this->actingAs($this->admin);
    }

    private function mov(User $u, string $tipo, string $fecha, float $monto, ?int $ventaId = null, int $sucursal = 1): ComisionMovimiento
    {
        return ComisionMovimiento::create([
            'empresa_id' => 1, 'sucursal_id' => $sucursal, 'user_id' => $u->id, 'beneficiario' => 'vendedor',
            'tipo' => $tipo, 'fecha' => $fecha, 'venta_id' => $ventaId, 'monto' => $monto,
        ]);
    }

    private function generar(User $u, string $hasta = '2026-10-15')
    {
        return $this->postJson('/api/comisiones/liquidaciones', ['user_id' => $u->id, 'hasta' => $hasta]);
    }

    private function abrirCaja(): CorteCaja
    {
        return CorteCaja::create([
            'empresa_id' => 1, 'sucursal_id' => 1, 'user_id' => $this->admin->id, 'terminal' => 'POS-01',
            'estado' => 'abierto', 'fecha_apertura' => now(), 'fondo_inicial_efectivo' => 500,
        ]);
    }

    public function test_pendientes_hasta_la_fecha_de_corte_incluyen_descuentos_anteriores(): void
    {
        $r = $this->getJson('/api/comisiones/liquidaciones/pendientes?hasta=2026-10-15')->assertOk()->json('empleados');

        $ana = collect($r)->firstWhere('nombre', 'Ana');
        $this->assertEquals(125, $ana['total']);          // -5 + 100 + 50 - 20
        $this->assertEquals(150, $ana['comisiones']);
        $this->assertEquals(-25, $ana['descuentos']);
        $this->assertSame('2026-09-28', $ana['desde']);
        $this->assertEquals(-30, collect($r)->firstWhere('nombre', 'Beto')['total']);
    }

    public function test_generar_aparta_los_movimientos_y_no_se_duplica(): void
    {
        $this->generar($this->ana)->assertCreated()
            ->assertJsonPath('estado', 'borrador')
            ->assertJsonPath('total', 125)
            ->assertJsonPath('desde', '2026-09-28')
            ->assertJsonPath('hasta', '2026-10-15')
            ->assertJsonPath('resumen.movimientos', 4)
            ->assertJsonPath('resumen.tickets', 2)
            ->assertJsonCount(4, 'movimientos');

        $this->generar($this->ana)->assertJsonValidationErrors('user_id');

        // La venta del día 20 sigue pendiente para la siguiente quincena.
        $this->assertNull(ComisionMovimiento::where('fecha', '2026-10-20')->value('liquidacion_id'));
    }

    public function test_no_genera_con_saldo_negativo(): void
    {
        $this->generar($this->beto)->assertJsonValidationErrors('user_id');
        $this->assertSame(0, ComisionLiquidacion::count());
    }

    public function test_pagar_sin_caja_cuenta_como_pagado_en_el_reporte(): void
    {
        $id = $this->generar($this->ana)->json('id');

        $this->getJson('/api/comisiones/reporte?desde=2026-10-01&hasta=2026-10-31')
            ->assertJsonPath('empleados.0.pagado', 0);   // el borrador todavía es pendiente

        $this->postJson("/api/comisiones/liquidaciones/{$id}/pagar", ['registrar_caja' => false])
            ->assertOk()
            ->assertJsonPath('estado', 'pagada')
            ->assertJsonPath('salio_de_caja', false)
            ->assertJsonPath('pagado_por', 'Admin');

        $this->postJson("/api/comisiones/liquidaciones/{$id}/pagar", ['registrar_caja' => false])->assertJsonValidationErrors('estado');

        $ana = collect($this->getJson('/api/comisiones/reporte?desde=2026-10-01&hasta=2026-10-31')->json('empleados'))->firstWhere('nombre', 'Ana');
        $this->assertEquals(130, $ana['pagado']);         // 100 + 50 - 20 de octubre
        $this->assertEquals(40, $ana['pendiente']);
        $this->assertSame(0, MovimientoCaja::count());
    }

    public function test_pagar_con_caja_registra_egreso_y_exige_caja_abierta(): void
    {
        $id = $this->generar($this->ana)->json('id');

        $this->postJson("/api/comisiones/liquidaciones/{$id}/pagar", ['registrar_caja' => true, 'forma_pago' => 'efectivo'])
            ->assertJsonValidationErrors('caja');
        $this->postJson("/api/comisiones/liquidaciones/{$id}/pagar", ['registrar_caja' => true, 'forma_pago' => 'transferencia'])
            ->assertJsonValidationErrors('cuenta_bancaria_id');

        $corte = $this->abrirCaja();
        $this->postJson("/api/comisiones/liquidaciones/{$id}/pagar", ['registrar_caja' => true, 'forma_pago' => 'efectivo'])
            ->assertOk()
            ->assertJsonPath('estado', 'pagada')
            ->assertJsonPath('salio_de_caja', true)
            ->assertJsonPath('forma_pago', 'efectivo');

        $mov = MovimientoCaja::sole();
        $this->assertSame('egreso', $mov->tipo);
        $this->assertEquals(125, $mov->monto);
        $this->assertEquals(-125, $corte->fresh()->movs_efectivo);
        $this->assertEquals(375, $corte->fresh()->esperado_efectivo);
    }

    public function test_pagar_por_transferencia_guarda_la_cuenta(): void
    {
        $id = $this->generar($this->ana)->json('id');
        $this->abrirCaja();

        $this->postJson("/api/comisiones/liquidaciones/{$id}/pagar", ['registrar_caja' => true, 'forma_pago' => 'transferencia', 'cuenta_bancaria_id' => 5])
            ->assertOk();
        $this->assertSame(5, (int) MovimientoCaja::sole()->cuenta_bancaria_id);
    }

    public function test_cancelar_pagada_con_caja_reversa_y_libera_movimientos(): void
    {
        $id = $this->generar($this->ana)->json('id');
        $corte = $this->abrirCaja();
        $this->postJson("/api/comisiones/liquidaciones/{$id}/pagar", ['registrar_caja' => true, 'forma_pago' => 'efectivo'])->assertOk();

        $this->postJson("/api/comisiones/liquidaciones/{$id}/cancelar", ['motivo' => 'Monto equivocado'])
            ->assertOk()
            ->assertJsonPath('estado', 'cancelada')
            ->assertJsonPath('cancelado_por', 'Admin');

        $this->assertSame(['egreso', 'ingreso'], MovimientoCaja::orderBy('id')->pluck('tipo')->all());
        $this->assertEquals(0, $corte->fresh()->movs_efectivo);
        $this->assertSame(0, ComisionMovimiento::whereNotNull('liquidacion_id')->count());
        $this->assertStringContainsString('Monto equivocado', ComisionLiquidacion::find($id)->notas);

        // Se puede volver a liquidar.
        $this->generar($this->ana)->assertCreated()->assertJsonPath('total', 125);
        $this->postJson("/api/comisiones/liquidaciones/{$id}/cancelar", ['motivo' => 'Otra vez'])->assertJsonValidationErrors('estado');
    }

    public function test_cancelar_borrador_no_toca_caja(): void
    {
        $id = $this->generar($this->ana)->json('id');

        $this->postJson("/api/comisiones/liquidaciones/{$id}/cancelar", ['motivo' => 'Error'])->assertOk();

        $this->assertSame(0, MovimientoCaja::count());
        $this->assertSame(0, ComisionMovimiento::whereNotNull('liquidacion_id')->count());
    }

    public function test_listado_filtra_por_estado(): void
    {
        $id = $this->generar($this->ana)->json('id');
        $this->postJson("/api/comisiones/liquidaciones/{$id}/pagar", ['registrar_caja' => false])->assertOk();

        $this->getJson('/api/comisiones/liquidaciones')->assertOk()->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.empleado.name', 'Ana');
        $this->getJson('/api/comisiones/liquidaciones?estado=borrador')->assertJsonPath('total', 0);
    }

    public function test_solo_liquida_sucursales_con_permiso(): void
    {
        // Encargado con permiso de liquidar solo en la sucursal 1.
        DB::table('roles')->insert(['id' => 1, 'empresa_id' => 1, 'nombre' => 'Encargado']);
        DB::table('permisos')->insert(['id' => 1, 'clave' => 'comisiones.liquidar']);
        DB::table('role_permiso')->insert(['role_id' => 1, 'permiso_id' => 1]);
        $encargado = User::forceCreate(['empresa_id' => 1, 'sucursal_id' => 1, 'name' => 'Encargado']);
        DB::table('sucursal_user')->insert(['sucursal_id' => 1, 'user_id' => $encargado->id, 'role_id' => 1]);

        $this->mov($this->ana, 'venta', '2026-10-05', 70, 200, sucursal: 2);

        // El admin ve el pendiente de las dos sucursales; el encargado solo el de la suya.
        $total = fn() => collect($this->getJson('/api/comisiones/liquidaciones/pendientes?hasta=2026-10-15')->json('empleados'))
            ->firstWhere('nombre', 'Ana')['total'];
        $this->assertEquals(195, $total());

        $this->actingAs($encargado);
        $this->assertEquals(125, $total());
        $idPropia = $this->generar($this->ana)->assertCreated()->assertJsonPath('total', 125)->json('id');

        // Una liquidación con movimientos de la sucursal 2 no le es accesible.
        $this->actingAs($this->admin);
        $idNorte = $this->generar($this->ana)->assertCreated()->assertJsonPath('total', 70)->json('id');

        $this->actingAs($encargado);
        $this->getJson("/api/comisiones/liquidaciones/{$idNorte}")->assertForbidden();
        $this->getJson("/api/comisiones/liquidaciones/{$idPropia}")->assertOk();
        $this->getJson('/api/comisiones/liquidaciones')->assertJsonPath('total', 1);
    }

    public function test_usuario_sin_rol_ve_todas_las_sucursales(): void
    {
        // Sin super admin y sin rol en ninguna sucursal: acceso total legacy.
        $legacy = User::forceCreate(['empresa_id' => 1, 'sucursal_id' => 1, 'name' => 'Legacy']);
        DB::table('sucursal_user')->insert(['sucursal_id' => 1, 'user_id' => $legacy->id, 'role_id' => null]);
        $this->mov($this->ana, 'venta', '2026-10-05', 70, 200, sucursal: 2);

        $this->actingAs($legacy);

        $ana = collect($this->getJson('/api/comisiones/liquidaciones/pendientes?hasta=2026-10-15')->json('empleados'))
            ->firstWhere('nombre', 'Ana');
        $this->assertEquals(195, $ana['total']);            // incluye la sucursal 2
        $this->getJson('/api/comisiones/reporte?desde=2026-10-01&hasta=2026-10-31')
            ->assertOk()
            ->assertJsonCount(2, 'sucursales');
        $this->getJson('/api/comisiones/esquemas')->assertOk();   // puede configurar
        $this->generar($this->ana)->assertCreated()->assertJsonPath('total', 195);
    }

    public function test_descarga_pdf_de_la_liquidacion(): void
    {
        \Illuminate\Support\Facades\Schema::create('empresas', function ($t) {
            $t->id(); $t->string('nombre'); $t->string('logo')->nullable(); $t->string('direccion')->nullable(); $t->timestamps();
        });
        DB::table('empresas')->insert(['id' => 1, 'nombre' => 'Zapatería Búho']);

        $id = $this->generar($this->ana)->json('id');
        $this->postJson("/api/comisiones/liquidaciones/{$id}/pagar", ['registrar_caja' => false])->assertOk();

        $respuesta = $this->get("/api/comisiones/liquidaciones/{$id}/pdf")->assertOk();
        $this->assertSame('application/pdf', $respuesta->headers->get('content-type'));
        $this->assertStringContainsString("liquidacion_{$id}_ana.pdf", $respuesta->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $respuesta->getContent());

        // También se puede descargar una cancelada (sin movimientos).
        $this->postJson("/api/comisiones/liquidaciones/{$id}/cancelar", ['motivo' => 'Prueba'])->assertOk();
        $this->assertStringStartsWith('%PDF', $this->get("/api/comisiones/liquidaciones/{$id}/pdf")->assertOk()->getContent());
    }

    public function test_requiere_permiso_de_liquidar(): void
    {
        $sinPermiso = \Mockery::mock(User::class)->makePartial();
        $sinPermiso->id = 999;
        $sinPermiso->empresa_id = 1;
        $sinPermiso->shouldReceive('tienePermiso')->andReturn(false);
        $this->actingAs($sinPermiso);

        $this->getJson('/api/comisiones/liquidaciones/pendientes?hasta=2026-10-15')->assertForbidden();
        $this->postJson('/api/comisiones/liquidaciones', ['user_id' => $this->ana->id, 'hasta' => '2026-10-15'])->assertForbidden();
    }
}
