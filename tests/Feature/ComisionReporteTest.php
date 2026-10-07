<?php

namespace Tests\Feature;

use App\Models\ComisionLiquidacion;
use App\Models\ComisionMovimiento;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CreaTablasComisiones;
use Tests\TestCase;

class ComisionReporteTest extends TestCase
{
    use CreaTablasComisiones;

    private User $admin;
    private User $ana;
    private User $beto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearTablasComisiones();
        $this->withoutMiddleware();

        DB::table('sucursales')->insert([
            ['id' => 1, 'empresa_id' => 1, 'nombre' => 'Centro'],
            ['id' => 2, 'empresa_id' => 1, 'nombre' => 'Norte'],
            ['id' => 9, 'empresa_id' => 2, 'nombre' => 'Ajena'],
        ]);

        $this->admin = User::forceCreate(['empresa_id' => 1, 'name' => 'Admin', 'es_super_admin' => true]);
        $this->ana = User::forceCreate(['empresa_id' => 1, 'name' => 'Ana']);
        $this->beto = User::forceCreate(['empresa_id' => 1, 'name' => 'Beto']);

        $liqPagada = ComisionLiquidacion::create(['empresa_id' => 1, 'user_id' => $this->ana->id, 'desde' => '2026-10-01', 'hasta' => '2026-10-15', 'estado' => 'pagada']);
        $liqBorrador = ComisionLiquidacion::create(['empresa_id' => 1, 'user_id' => $this->ana->id, 'desde' => '2026-10-01', 'hasta' => '2026-10-15', 'estado' => 'borrador']);

        // Ana: vendedora en 2 tickets (uno con 2 renglones), cajera en 1, una devolución; Beto: cajero en Norte.
        $this->mov($this->ana, 'vendedor', 'venta', '2026-10-01', 100, 50, ['liquidacion_id' => $liqPagada->id]);
        $this->mov($this->ana, 'vendedor', 'venta', '2026-10-01', 100, 30, ['liquidacion_id' => $liqBorrador->id]);
        $this->mov($this->ana, 'vendedor', 'venta', '2026-10-31', 101, 20);
        $this->mov($this->ana, 'cajero', 'venta', '2026-10-10', 102, 5);
        $this->mov($this->ana, 'vendedor', 'devolucion', '2026-10-12', 100, -10);
        $this->mov($this->beto, 'cajero', 'venta', '2026-10-05', 103, 8, ['sucursal_id' => 2]);
        $this->mov($this->ana, 'vendedor', 'venta', '2026-11-01', 104, 999);        // fuera del periodo
        $this->mov($this->ana, 'vendedor', 'venta', '2026-10-02', 105, 999, ['empresa_id' => 2]); // otra empresa

        $this->actingAs($this->admin);
    }

    private function mov(User $u, string $beneficiario, string $tipo, string $fecha, int $ventaId, float $monto, array $extra = []): void
    {
        ComisionMovimiento::create($extra + [
            'empresa_id' => 1, 'sucursal_id' => 1, 'user_id' => $u->id, 'beneficiario' => $beneficiario,
            'tipo' => $tipo, 'fecha' => $fecha, 'venta_id' => $ventaId, 'monto' => $monto,
        ]);
    }

    private function periodo(array $extra = []): string
    {
        return http_build_query($extra + ['desde' => '2026-10-01', 'hasta' => '2026-10-31']);
    }

    private function usuarioCon(array $permisos, ?User $real = null): User
    {
        $u = \Mockery::mock(User::class)->makePartial();
        $u->id = $real?->id ?? 999;
        $u->empresa_id = 1;
        $u->name = $real?->name ?? 'Sin permiso';
        $u->shouldReceive('tienePermiso')->andReturnUsing(fn($clave) => in_array($clave, $permisos, true));
        return $u;
    }

    public function test_resumen_por_empleado_separa_vendedor_cajero_y_descuentos(): void
    {
        $r = $this->getJson('/api/comisiones/reporte?' . $this->periodo())->assertOk()->json();

        $this->assertCount(2, $r['empleados']);
        $ana = collect($r['empleados'])->firstWhere('nombre', 'Ana');
        $this->assertSame(2, $ana['tickets_vendedor']);   // ventas 100 y 101
        $this->assertSame(1, $ana['tickets_cajero']);
        $this->assertEquals(100, $ana['comision_vendedor']);
        $this->assertEquals(5, $ana['comision_cajero']);
        $this->assertEquals(-10, $ana['descuentos']);
        $this->assertEquals(95, $ana['total']);
        $this->assertEquals(50, $ana['pagado']);          // solo la liquidación pagada
        $this->assertEquals(45, $ana['pendiente']);

        $this->assertEquals(103, $r['totales']['total']);
        $this->assertCount(2, $r['sucursales']);          // no lista sucursales de otra empresa
    }

    public function test_filtra_por_sucursal_e_incluye_el_ultimo_dia(): void
    {
        $this->getJson('/api/comisiones/reporte?' . $this->periodo(['sucursal_id' => 2]))
            ->assertOk()
            ->assertJsonCount(1, 'empleados')
            ->assertJsonPath('empleados.0.nombre', 'Beto');

        $this->getJson('/api/comisiones/reporte?' . $this->periodo(['desde' => '2026-10-31']))
            ->assertOk()
            ->assertJsonPath('totales.total', 20);

        $this->getJson('/api/comisiones/reporte?' . $this->periodo(['sucursal_id' => 9]))->assertJsonValidationErrors('sucursal_id');
    }

    public function test_valida_periodo(): void
    {
        $this->getJson('/api/comisiones/reporte?desde=2026-10-31&hasta=2026-10-01')->assertJsonValidationErrors('hasta');
        $this->getJson('/api/comisiones/reporte?desde=2024-01-01&hasta=2026-10-01')->assertStatus(422);
        $this->getJson('/api/comisiones/reporte')->assertJsonValidationErrors(['desde', 'hasta']);
    }

    public function test_detalle_con_filtros_y_estado_de_pago(): void
    {
        $r = $this->getJson("/api/comisiones/reporte/{$this->ana->id}?" . $this->periodo())->assertOk()->json();

        $this->assertEquals(95, $r['resumen']['total']);
        $this->assertSame(5, $r['movimientos']['total']);
        $this->assertSame('2026-10-31', $r['movimientos']['data'][0]['fecha']);   // más reciente primero
        $this->assertSame(1, collect($r['movimientos']['data'])->where('estado_pago', 'pagada')->count());

        $this->getJson("/api/comisiones/reporte/{$this->ana->id}?" . $this->periodo(['beneficiario' => 'cajero']))
            ->assertJsonPath('resumen.total', 5)
            ->assertJsonPath('movimientos.total', 1);
        $this->getJson("/api/comisiones/reporte/{$this->ana->id}?" . $this->periodo(['tipo' => 'devolucion']))
            ->assertJsonPath('movimientos.data.0.monto', -10);
    }

    public function test_permisos(): void
    {
        // Sin permisos
        $this->actingAs($this->usuarioCon([]));
        $this->getJson('/api/comisiones/reporte?' . $this->periodo())->assertForbidden();
        $this->getJson('/api/comisiones/mias?' . $this->periodo())->assertForbidden();

        // Solo las propias: ve su detalle y "mias", pero no el reporte ni a otros
        $this->actingAs($this->usuarioCon(['comisiones.ver_propias'], $this->ana));
        $this->getJson('/api/comisiones/reporte?' . $this->periodo())->assertForbidden();
        $this->getJson("/api/comisiones/reporte/{$this->beto->id}?" . $this->periodo())->assertForbidden();
        $this->getJson("/api/comisiones/reporte/{$this->ana->id}?" . $this->periodo())->assertOk();
        $this->getJson('/api/comisiones/mias?' . $this->periodo())
            ->assertOk()
            ->assertJsonPath('resumen.total', 95)
            ->assertJsonPath('empleado.name', 'Ana');
    }

    public function test_permiso_local_no_expone_otra_sucursal_ni_permite_configuracion_global(): void
    {
        DB::table('roles')->insert(['id' => 1, 'empresa_id' => 1, 'nombre' => 'Encargado']);
        DB::table('permisos')->insert([
            ['id' => 1, 'clave' => 'comisiones.ver'],
            ['id' => 2, 'clave' => 'comisiones.configurar'],
        ]);
        DB::table('role_permiso')->insert([
            ['role_id' => 1, 'permiso_id' => 1], ['role_id' => 1, 'permiso_id' => 2],
        ]);
        DB::table('sucursal_user')->insert(['sucursal_id' => 1, 'user_id' => $this->ana->id, 'role_id' => 1]);
        $this->ana->update(['sucursal_id' => 1]);
        $this->actingAs($this->ana);
        $this->getJson('/api/comisiones/reporte?' . $this->periodo())
            ->assertOk()->assertJsonCount(1, 'sucursales')->assertJsonCount(1, 'empleados');
        $this->getJson('/api/comisiones/reporte?' . $this->periodo(['sucursal_id' => 2]))->assertForbidden();
        $this->getJson("/api/comisiones/reporte/{$this->beto->id}?" . $this->periodo())
            ->assertOk()->assertJsonPath('movimientos.total', 0);
        $this->getJson('/api/comisiones/esquemas')->assertForbidden();
    }

    public function test_exporta_pdf_y_excel_con_los_mismos_datos_y_permisos(): void
    {
        \Illuminate\Support\Facades\Schema::create('empresas', function ($t) {
            $t->id(); $t->string('nombre'); $t->string('logo')->nullable(); $t->string('direccion')->nullable(); $t->timestamps();
        });
        DB::table('empresas')->insert(['id' => 1, 'nombre' => 'Zapatería Búho']);

        $r = $this->get('/api/comisiones/reporte/exportar?' . $this->periodo(['formato' => 'pdf']))->assertOk();
        $this->assertSame('application/pdf', $r->headers->get('content-type'));
        $this->assertStringContainsString('comisiones_2026-10-01_2026-10-31.pdf', $r->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $r->getContent());

        $excel = $this->get('/api/comisiones/reporte/exportar?' . $this->periodo(['formato' => 'excel']))->assertOk();
        $this->assertStringContainsString('comisiones_2026-10-01_2026-10-31.xlsx', $excel->headers->get('content-disposition'));
        $this->get('/api/comisiones/reporte/exportar?' . $this->periodo(['formato' => 'csv']))->assertStatus(302);

        // Mismos datos que el reporte en pantalla: texto en PDF, números en Excel.
        $resumen = app(\App\Services\ComisionReporte::class)->resumen(1, ['desde' => '2026-10-01', 'hasta' => '2026-10-31']);
        $filtros = ['desde' => '2026-10-01', 'hasta' => '2026-10-31'];
        $enExcel = new \App\Exportaciones\ComisionesExportacion(1, $resumen, $filtros, null, 'excel');
        $this->assertSame(['Ana', 2, 100.0, 1, 5.0, -10.0, 0.0, 95.0, 50.0, 45.0], $enExcel->datos()->firstWhere(0, 'Ana'));
        $export = new \App\Exportaciones\ComisionesExportacion(1, $resumen, $filtros, null, 'pdf');
        $ana = $export->datos()->firstWhere(0, 'Ana');
        $this->assertSame(['Ana', 2, '$100.00', 1, '$5.00', '-$10.00', '$0.00', '$95.00', '$50.00', '$45.00'], $ana);
        $this->assertSame('$103.00', $export->totales()[7]);

        $this->get('/api/comisiones/reporte/exportar?formato=pdf&desde=2026-10-31&hasta=2026-10-01')->assertStatus(302);
        $this->actingAs($this->usuarioCon(['comisiones.ver_propias'], $this->ana));
        $this->get('/api/comisiones/reporte/exportar?' . $this->periodo(['formato' => 'excel']))->assertForbidden();
    }

    public function test_mias_sin_movimientos_regresa_ceros(): void
    {
        $this->actingAs($this->usuarioCon(['comisiones.ver_propias'], $this->beto));

        $this->getJson('/api/comisiones/mias?' . $this->periodo(['sucursal_id' => 1]))
            ->assertOk()
            ->assertJsonPath('resumen.total', 0)
            ->assertJsonPath('movimientos.total', 0);
    }
}
