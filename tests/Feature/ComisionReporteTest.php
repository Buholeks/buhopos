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

    public function test_mias_sin_movimientos_regresa_ceros(): void
    {
        $this->actingAs($this->usuarioCon(['comisiones.ver_propias'], $this->beto));

        $this->getJson('/api/comisiones/mias?' . $this->periodo(['sucursal_id' => 1]))
            ->assertOk()
            ->assertJsonPath('resumen.total', 0)
            ->assertJsonPath('movimientos.total', 0);
    }
}
