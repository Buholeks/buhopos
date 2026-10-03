<?php

namespace Tests\Feature;

use App\Exportaciones\SeriesExportacion;
use App\Http\Controllers\ConsultaSeriesController;
use App\Models\User;
use App\Servicios\ConsultaSeriesServicio;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ConsultaSeriesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Esquema aislado: no usa ni modifica la base de datos de la aplicación.
        $tablas = [
            'empresas' => ['nombre'],
            'sucursales' => ['empresa_id', 'nombre'], 'sucursal_user' => ['user_id', 'sucursal_id'],
            'productos' => ['empresa_id', 'nombre', 'codigo', 'categoria_id', 'deleted_at'], 'categorias' => ['empresa_id', 'nombre'],
            'producto_variantes' => ['sku', 'deleted_at'], 'users' => ['name'], 'clientes' => ['nombre'], 'proveedores' => ['nombre_comercial'],
            'series' => ['empresa_id', 'sucursal_id', 'producto_id', 'variante_id', 'compra_id', 'proveedor_id', 'venta_id', 'venta_detalle_id', 'serie', 'imei', 'imei2', 'estado', 'precio_costo', 'precio_venta', 'notas', 'created_at', 'updated_at'],
            'inventario' => ['empresa_id', 'sucursal_id', 'producto_id', 'variante_id', 'stock'],
            'compras' => ['empresa_id', 'sucursal_id', 'proveedor_id', 'user_id', 'fecha', 'folio', 'estado', 'notas'],
            'ventas' => ['empresa_id', 'sucursal_id', 'cliente_id', 'user_id', 'vendedor_id', 'fecha', 'folio', 'estado', 'subtotal', 'descuento', 'cancelado_en', 'cancelado_por', 'motivo_cancelacion', 'deleted_at'],
            'venta_detalles' => ['venta_id', 'serie_id', 'subtotal', 'precio_costo', 'cantidad'],
            'devoluciones' => ['empresa_id', 'sucursal_id', 'estado', 'folio', 'fecha', 'motivo', 'regresa_inventario'],
            'devolucion_detalles' => ['devolucion_id', 'venta_detalle_id', 'serie_id', 'importe', 'cantidad'],
            'traspasos' => ['empresa_id', 'origen_sucursal_id', 'destino_sucursal_id', 'estado', 'folio', 'created_at', 'recibido_at', 'cancelado_at', 'rechazado_at', 'motivo_cancelacion', 'motivo_rechazo', 'notas'],
            'traspaso_detalles' => ['traspaso_id', 'serie_id', 'estado'],
            'devoluciones_proveedor' => ['empresa_id', 'sucursal_id', 'fecha', 'referencia', 'estado', 'motivo'],
            'devolucion_proveedor_detalles' => ['devolucion_proveedor_id'], 'devolucion_proveedor_series' => ['devolucion_proveedor_detalle_id', 'serie_id'],
            'kardex_movimientos' => ['empresa_id', 'sucursal_id', 'producto_id', 'variante_id', 'serie_id', 'user_id', 'tipo', 'fecha', 'folio', 'referencia_tipo', 'referencia_id', 'referencia_detalle_id', 'motivo', 'notas', 'metadata', 'direccion', 'cantidad', 'entrada', 'salida', 'stock_antes', 'stock_despues', 'created_at', 'updated_at'],
        ];
        foreach ($tablas as $tabla => $columnas) Schema::create($tabla, function (Blueprint $t) use ($columnas) {
            $t->id(); foreach ($columnas as $c) {
                if (str_ends_with($c, '_id') || in_array($c, ['user_id', 'cancelado_por'])) $t->integer($c)->nullable();
                elseif (in_array($c, ['subtotal', 'descuento', 'precio_costo', 'precio_venta', 'importe', 'cantidad'])) $t->decimal($c, 14, 2)->nullable();
                else $t->text($c)->nullable();
            }
        });
        DB::table('empresas')->insert(['id' => 1, 'nombre' => 'Empresa de prueba']);
        DB::table('sucursales')->insert([['id' => 1, 'empresa_id' => 1, 'nombre' => 'Centro'], ['id' => 2, 'empresa_id' => 1, 'nombre' => 'Norte'], ['id' => 3, 'empresa_id' => 2, 'nombre' => 'Ajena']]);
        DB::table('categorias')->insert(['id' => 1, 'empresa_id' => 1, 'nombre' => 'Teléfonos']);
        DB::table('productos')->insert(['id' => 1, 'empresa_id' => 1, 'nombre' => 'Teléfono', 'codigo' => 'P1', 'categoria_id' => 1]);
        DB::table('users')->insert(['id' => 1, 'name' => 'Vendedor']);
        DB::table('proveedores')->insert(['id' => 1, 'nombre_comercial' => 'Proveedor']);
        DB::table('clientes')->insert(['id' => 1, 'nombre' => 'Cliente']);
        DB::table('compras')->insert(['id' => 1, 'empresa_id' => 1, 'sucursal_id' => 1, 'proveedor_id' => 1, 'user_id' => 1, 'fecha' => '2026-09-01', 'folio' => 'C001', 'estado' => 'confirmada']);
        DB::table('series')->insert(['id' => 1, 'empresa_id' => 1, 'sucursal_id' => 2, 'producto_id' => 1, 'compra_id' => 1, 'serie' => '00001234', 'imei' => '001234567890123', 'imei2' => '001234567890124', 'estado' => 'vendido', 'precio_costo' => 50, 'precio_venta' => 999, 'venta_id' => 3]);
        foreach ([[1, '2026-09-03', 'cancelada'], [2, '2026-09-10', 'confirmada'], [3, '2026-09-30 23:59:59', 'confirmada']] as [$id, $fecha, $estado]) {
            DB::table('ventas')->insert(['id' => $id, 'empresa_id' => 1, 'sucursal_id' => 1, 'cliente_id' => 1, 'vendedor_id' => 1, 'user_id' => 1, 'fecha' => $fecha, 'folio' => 'V00'.$id, 'estado' => $estado, 'subtotal' => 200, 'descuento' => 20, 'cancelado_en' => $id === 1 ? '2026-09-04 12:00:00' : null]);
            DB::table('venta_detalles')->insert(['id' => $id, 'venta_id' => $id, 'serie_id' => 1, 'subtotal' => 100, 'precio_costo' => 50, 'cantidad' => 1]);
        }
        DB::table('devoluciones')->insert(['id' => 1, 'empresa_id' => 1, 'sucursal_id' => 1, 'estado' => 'confirmada', 'folio' => 'D001', 'fecha' => '2026-09-11 12:00:00', 'motivo' => 'Cambio', 'regresa_inventario' => 1]);
        DB::table('devolucion_detalles')->insert(['id' => 1, 'devolucion_id' => 1, 'venta_detalle_id' => 2, 'serie_id' => 1, 'importe' => 90, 'cantidad' => 1]);
        Route::get('/api/test-series/catalogos', [ConsultaSeriesController::class, 'catalogos']);
        Route::get('/api/test-series/reporte', [ConsultaSeriesController::class, 'reporte']);
        Route::get('/api/test-series/exportar', [ConsultaSeriesController::class, 'exportar']);
        Route::get('/api/test-series/{id}', [ConsultaSeriesController::class, 'show']);
        Route::patch('/api/test-series/editar/{serie}', [\App\Http\Controllers\SerieController::class, 'update'])->middleware(\Illuminate\Routing\Middleware\SubstituteBindings::class);
    }

    private function filtros(): array { return ['fecha_desde' => '2026-09-01', 'fecha_hasta' => '2026-09-30', 'estado' => 'todas']; }

    public function test_conserva_cancelacion_devolucion_y_reventa_con_descuento_global(): void
    {
        $s = app(ConsultaSeriesServicio::class);
        $filas = $s->ventas(1, [1], $this->filtros())->orderBy('id')->get();
        $this->assertCount(3, $filas);
        $this->assertEquals(['cancelada', 'devuelta', 'vigente'], $filas->pluck('estado_operacion')->all());
        $this->assertEquals([90, 90, 90], $filas->pluck('importe_venta')->all());
        $this->assertEquals([0, 0, 90], $filas->pluck('importe_neto')->all());
        $this->assertEquals(3, $s->ventas(1, [1], array_replace($this->filtros(), ['estado' => 'vigente']))->first()->id);
        $this->assertSame(0, $s->ventas(2, [3], $this->filtros())->count());
        $this->assertSame(0, $s->ventas(1, [2], $this->filtros())->count());
        $this->assertSame(0, $s->ventas(1, [1], $this->filtros() + ['categoria_ids' => [0]])->count());
        $this->assertSame(3, $s->ventas(1, [1], $this->filtros() + ['categoria_ids' => [1]])->count());
    }

    public function test_historial_no_duplica_kardex_y_no_depende_de_venta_actual(): void
    {
        DB::table('kardex_movimientos')->insert(['empresa_id' => 1, 'sucursal_id' => 1, 'serie_id' => 1, 'tipo' => 'venta', 'fecha' => '2026-09-03 10:00:00', 'referencia_tipo' => 'venta', 'referencia_id' => 1, 'folio' => 'V001']);
        DB::table('series')->where('id', 1)->update(['venta_id' => null]);
        $r = app(ConsultaSeriesServicio::class)->expediente(1, [1], 1, false);
        $this->assertCount(3, $r['ventas']);
        $this->assertCount(3, $r['eventos']->where('tipo', 'venta'));
        $this->assertCount(1, $r['eventos']->where('tipo', 'cancelacion_venta'));
        $this->assertCount(1, $r['eventos']->where('tipo', 'devolucion_cliente'));
        $this->assertFalse(isset($r['serie']->precio_costo));
        $this->assertFalse(isset($r['serie']->precio_venta));
        $this->assertFalse(isset($r['ventas'][0]->costo));
        $this->assertSame('Otra sucursal', $r['serie']->sucursal);
        $this->assertSame(1, app(ConsultaSeriesServicio::class)->buscar(1, [1], '001234567890124')->total());
        $this->assertSame(0, app(ConsultaSeriesServicio::class)->buscar(1, [1], '%_')->total());
    }

    public function test_incluye_traspasos_ajustes_y_devolucion_proveedor_sin_mezclar_series(): void
    {
        DB::table('traspasos')->insert(['id' => 1, 'empresa_id' => 1, 'origen_sucursal_id' => 1, 'destino_sucursal_id' => 2, 'estado' => 'pendiente', 'folio' => 'T001', 'created_at' => '2026-09-02 12:00:00']);
        DB::table('traspaso_detalles')->insert(['traspaso_id' => 1, 'serie_id' => 1, 'estado' => 'pendiente']);
        DB::table('devoluciones_proveedor')->insert(['id' => 1, 'empresa_id' => 1, 'sucursal_id' => 1, 'fecha' => '2026-09-15', 'referencia' => 'DP001', 'estado' => 'confirmada', 'motivo' => 'Falla']);
        DB::table('devolucion_proveedor_detalles')->insert(['id' => 1, 'devolucion_proveedor_id' => 1]);
        DB::table('devolucion_proveedor_series')->insert(['devolucion_proveedor_detalle_id' => 1, 'serie_id' => 1]);
        foreach ([['ajuste_negativo', 'inventario_ajuste_rapido', 5, null, json_encode(['serie_ids' => [1]])], ['ajuste_negativo', 'inventario_ajuste_rapido', 6, null, json_encode(['serie_ids' => [99]])], ['devolucion_proveedor', 'devolucion_proveedor', 1, 1, null]] as [$tipo, $ref, $rid, $detalle, $metadata]) {
            DB::table('kardex_movimientos')->insert(['empresa_id' => 1, 'sucursal_id' => 1, 'tipo' => $tipo, 'fecha' => '2026-09-15 12:00:00', 'referencia_tipo' => $ref, 'referencia_id' => $rid, 'referencia_detalle_id' => $detalle, 'metadata' => $metadata]);
        }
        $r = app(ConsultaSeriesServicio::class)->expediente(1, [1], 1, true);
        $this->assertTrue($r['en_transito']);
        $this->assertCount(1, $r['eventos']->where('tipo', 'traspaso_salida'));
        $this->assertCount(1, $r['eventos']->where('tipo', 'ajuste_negativo'));
        $this->assertCount(1, $r['eventos']->where('tipo', 'devolucion_proveedor'));
        $this->assertCount(1, $r['documentos']->where('tipo', 'traspaso'));
        $this->assertSame(50, $r['serie']->precio_costo);
    }

    public function test_registra_ediciones_de_serie_sin_cambiar_existencias(): void
    {
        DB::table('series')->where('id', 1)->update(['estado' => 'disponible', 'sucursal_id' => 1, 'venta_id' => null]);
        DB::table('inventario')->insert(['empresa_id' => 1, 'sucursal_id' => 1, 'producto_id' => 1, 'stock' => 7]);
        $this->actingAs($this->usuario())->patchJson('/api/test-series/editar/1', ['estado' => 'apartado', 'notas' => 'Reservado'])->assertOk();
        $movimiento = DB::table('kardex_movimientos')->where('serie_id', 1)->first();
        $this->assertSame('actualizacion_serie', $movimiento->tipo);
        $this->assertSame('neutro', $movimiento->direccion);
        $this->assertEquals(7, $movimiento->stock_antes);
        $this->assertEquals(7, $movimiento->stock_despues);
        $this->assertEquals(0, $movimiento->cantidad);
        $this->assertEquals(7, DB::table('inventario')->value('stock'));
        $this->patchJson('/api/test-series/editar/1', ['estado' => 'apartado', 'notas' => 'Reservado'])->assertOk();
        $this->assertSame(1, DB::table('kardex_movimientos')->count());
    }

    private function usuario(bool $permiso = true, bool $costos = false): User
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->id = 1; $user->empresa_id = 1; $user->sucursal_id = 1;
        $user->shouldReceive('tienePermiso')->with('reportes.ver')->andReturn($permiso);
        $rol = new \App\Models\Rol;
        $rol->setRelation('permisos', collect([['clave' => 'reportes.ver'], ...($costos ? [['clave' => 'reportes.utilidades']] : [])])->map(fn ($p) => (object) $p));
        $user->shouldReceive('rolEnSucursal')->andReturn($rol);
        return $user;
    }

    public function test_api_valida_alcance_fechas_permisos_y_oculta_costos(): void
    {
        $this->actingAs($this->usuario());
        $url = '/api/test-series/reporte?'.http_build_query($this->filtros());
        $r = $this->getJson($url)->assertOk()->assertJsonPath('totales.operaciones', 3)->assertJsonPath('ver_costos', false);
        $this->assertArrayNotHasKey('costo', $r->json('items.data.0'));
        $this->assertArrayNotHasKey('costo_compra', $r->json('items.data.0'));
        $this->getJson($url.'&sucursal_id=2')->assertUnprocessable();
        $this->getJson($url.'&categoria_ids[]=999')->assertUnprocessable();
        $this->getJson('/api/test-series/reporte?fecha_desde=2026-10-01&fecha_hasta=2026-09-01&estado=todas')->assertUnprocessable();
        $this->getJson('/api/test-series/999')->assertNotFound();
        $this->getJson('/api/test-series/1')->assertOk()->assertJsonPath('ver_costos', false);
        $this->actingAs($this->usuario(false))->getJson($url)->assertForbidden();
    }

    public function test_excel_preserva_ceros_y_texto_sin_formulas_y_exporta_todas_las_operaciones(): void
    {
        DB::table('productos')->where('id', 1)->update(['nombre' => '=1+1']);
        $query = app(ConsultaSeriesServicio::class)->ventas(1, [1], $this->filtros())->orderBy('id');
        $export = new SeriesExportacion($query, ['serie', 'imei', 'producto', 'importe_venta']);
        $bytes = \Maatwebsite\Excel\Facades\Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);
        $path = tempnam(sys_get_temp_dir(), 'series-test-');
        try {
            file_put_contents($path, $bytes);
            $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
            $this->assertSame('00001234', $sheet->getCell('A2')->getValue());
            $this->assertSame('001234567890123', $sheet->getCell('B2')->getValue());
            $this->assertSame('s', $sheet->getCell('C2')->getDataType());
            $this->assertEquals(90, $sheet->getCell('D2')->getValue());
            $this->assertSame(4, $sheet->getHighestRow());
        } finally { @unlink($path); }
        $this->actingAs($this->usuario())->getJson('/api/test-series/exportar?'.http_build_query($this->filtros() + ['formato' => 'otro']))->assertUnprocessable();
    }

    public function test_exportaciones_excel_y_pdf_tienen_las_columnas_solicitadas_y_respetan_costos(): void
    {
        $esperadas = ['Serie', 'IMEI', 'Producto', 'Categoría', 'Fecha de compra', 'Folio de compra', 'Proveedor', 'Costo de compra', 'Fecha de venta', 'Folio de venta', 'Cliente', 'Sucursal de venta', 'Venta después de descuentos', 'Estado'];
        DB::table('series')->where('id', 1)->update(['precio_costo' => 45]);
        $this->assertSame($esperadas, array_values(SeriesExportacion::COLUMNAS));
        foreach ([true, false] as $costos) {
            $this->actingAs($this->usuario(true, $costos));
            $filtros = $this->filtros() + ['formato' => 'excel'];
            $excel = $this->get('/api/test-series/exportar?'.http_build_query($filtros))->assertOk()->assertDownload('series_2026-09-01_2026-09-30.xlsx');
            $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($excel->baseResponse->getFile()->getPathname())->getActiveSheet();
            $cabeceras = $sheet->rangeToArray('A1:'.$sheet->getHighestColumn().'1')[0];
            $this->assertSame($costos ? $esperadas : array_values(array_diff($esperadas, ['Costo de compra'])), $cabeceras);
            if ($costos) {
                $this->assertEquals(45, $sheet->getCell('H2')->getValue());
            }
            $this->assertSame(4, $sheet->getHighestRow());
            $pdf = $this->get('/api/test-series/exportar?'.http_build_query(array_replace($filtros, ['formato' => 'pdf'])))
                ->assertOk()->assertHeader('content-type', 'application/pdf')->assertDownload('series_2026-09-01_2026-09-30.pdf');
            $this->assertStringStartsWith('%PDF', $pdf->getContent());
        }
    }
}
