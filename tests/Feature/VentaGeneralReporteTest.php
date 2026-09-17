<?php

namespace Tests\Feature;

use App\Http\Controllers\ReporteVentaGeneralController;
use App\Exportaciones\VentaGeneralExportacion;
use App\Models\User;
use App\Services\VentaGeneralReporte;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VentaGeneralReporteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('sucursales', function (Blueprint $t) { $t->id(); $t->integer('empresa_id'); $t->string('nombre'); $t->boolean('activo')->default(true); });
        Schema::create('ventas', function (Blueprint $t) {
            $t->id(); $t->integer('empresa_id'); $t->integer('sucursal_id'); $t->dateTime('fecha');
            $t->string('estado'); $t->decimal('total', 12, 2); $t->decimal('descuento', 12, 2)->default(0); $t->softDeletes();
        });
        Schema::create('devoluciones', function (Blueprint $t) {
            $t->id(); $t->integer('empresa_id'); $t->integer('sucursal_id'); $t->dateTime('fecha');
            $t->string('estado'); $t->decimal('total_devuelto', 12, 2);
        });
        DB::table('sucursales')->insert([
            ['id' => 1, 'empresa_id' => 1, 'nombre' => 'Centro'], ['id' => 2, 'empresa_id' => 1, 'nombre' => 'Norte'],
            ['id' => 3, 'empresa_id' => 1, 'nombre' => 'Sin ventas'], ['id' => 4, 'empresa_id' => 2, 'nombre' => 'Otra empresa'],
        ]);
        Route::get('/api/test-venta-general', [ReporteVentaGeneralController::class, 'index']);
    }

    private function filtros(): array { return ['fecha_desde' => '2026-09-01', 'fecha_hasta' => '2026-09-10']; }

    private function utc(string $fechaLocal): string
    {
        return \Carbon\Carbon::parse($fechaLocal, 'America/Mexico_City')->utc()->toDateTimeString();
    }

    public function test_totales_multisucursal_fechas_canceladas_y_devoluciones(): void
    {
        foreach ([[1, 1, '2026-09-01', 'confirmada', 100, null], [1, 2, '2026-09-10 23:59:59', 'confirmada', 200, null],
            [1, 1, '2026-09-05', 'cancelada', 90, null], [1, 1, '2026-09-11', 'confirmada', 900, null],
            [2, 4, '2026-09-02', 'confirmada', 999, null], [1, 1, '2026-09-02', 'confirmada', 999, '2026-09-03']] as $v) {
            DB::table('ventas')->insert(array_combine(['empresa_id', 'sucursal_id', 'fecha', 'estado', 'total', 'deleted_at'], $v) + ['descuento' => 10]);
        }
        // Las devoluciones se guardan con hora exacta en UTC (now()); simula el instante UTC real de un evento en hora local de México.
        foreach ([[1, 1, $this->utc('2026-09-10 23:59:59'), 'confirmada', 20], [1, 1, $this->utc('2026-09-02 08:00:00'), 'confirmada', 5],
            [1, 1, $this->utc('2026-09-11 00:00:00'), 'confirmada', 100], [1, 1, $this->utc('2026-09-02 08:00:00'), 'cancelada', 100],
            [2, 4, $this->utc('2026-09-02 08:00:00'), 'confirmada', 100]] as $d) {
            DB::table('devoluciones')->insert(array_combine(['empresa_id', 'sucursal_id', 'fecha', 'estado', 'total_devuelto'], $d));
        }
        $r = app(VentaGeneralReporte::class)->generar(1, $this->filtros());
        $this->assertCount(3, $r['datos']);
        $this->assertEquals(['ventas' => 300, 'descuentos' => 20, 'devoluciones' => 25, 'canceladas' => 90, 'neta' => 275], $r['totales']);
        $this->assertEquals(0, $r['datos']->firstWhere('id', 3)['neta']);
        $this->assertEquals(75, $r['datos']->firstWhere('id', 1)['neta']);
        $export = new VentaGeneralExportacion(1, $r, 'excel');
        $this->assertEquals(275, $export->totales()[4]);
        $this->assertEquals(100, $export->totales()[6]);
        $this->assertCount(3, $export->datos());
        $filtered = app(VentaGeneralReporte::class)->generar(1, $this->filtros() + ['sucursal_ids' => [2]]);
        $this->assertCount(1, $filtered['datos']);
        $this->assertEquals(200, $filtered['totales']['neta']);
    }

    public function test_devoluciones_sin_ventas_pueden_dar_neto_negativo(): void
    {
        DB::table('devoluciones')->insert(['empresa_id' => 1, 'sucursal_id' => 1, 'fecha' => $this->utc('2026-09-02 08:00:00'), 'estado' => 'confirmada', 'total_devuelto' => 20]);
        $r = app(VentaGeneralReporte::class)->generar(1, $this->filtros());
        $this->assertEquals(-20, $r['totales']['neta']);
        $this->assertNull($r['datos']->firstWhere('id', 1)['participacion']);
        $this->assertEquals('—', (new VentaGeneralExportacion(1, $r, 'excel'))->totales()[6]);
    }

    public function test_excluye_sucursales_inactivas(): void
    {
        DB::table('sucursales')->where('id', 3)->update(['activo' => false]);
        $r = app(VentaGeneralReporte::class)->generar(1, $this->filtros());
        $this->assertCount(2, $r['datos']);
        $this->assertNull($r['datos']->firstWhere('id', 3));
    }

    public function test_valida_empresa_fechas_y_permiso(): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->empresa_id = 1; $user->sucursal_id = 1;
        $user->shouldReceive('tienePermiso')->with('reportes.venta_general')->andReturn(true);
        $this->actingAs($user);
        $this->getJson('/api/test-venta-general?'.http_build_query($this->filtros()))->assertOk()->assertJsonCount(3, 'datos');
        $this->getJson('/api/test-venta-general?'.http_build_query($this->filtros() + ['sucursal_ids' => [4]]))->assertUnprocessable();
        $this->getJson('/api/test-venta-general?fecha_desde=2026-09-10&fecha_hasta=2026-09-01')->assertUnprocessable();
        $this->getJson('/api/test-venta-general?fecha_desde=2020-01-01&fecha_hasta=2026-09-10')->assertUnprocessable();
        $denied = \Mockery::mock(User::class)->makePartial();
        $denied->shouldReceive('tienePermiso')->with('reportes.venta_general')->andReturn(false);
        $this->actingAs($denied)->getJson('/api/test-venta-general?'.http_build_query($this->filtros()))->assertForbidden();
    }

    public function test_genera_archivos_excel_y_pdf(): void
    {
        $r = app(VentaGeneralReporte::class)->generar(1, $this->filtros());
        $excel = new VentaGeneralExportacion(0, $r, 'excel');
        $bytes = \Maatwebsite\Excel\Facades\Excel::raw($excel, \Maatwebsite\Excel\Excel::XLSX);
        $this->assertStringStartsWith('PK', $bytes);
        $pdf = app(\App\Exportaciones\ServicioExportacion::class)->exportar(new VentaGeneralExportacion(0, $r, 'pdf'), 'pdf', 'prueba_venta_general');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }
}

