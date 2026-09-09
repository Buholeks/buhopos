<?php

namespace Tests\Feature;

use App\Http\Controllers\ReporteComprasController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class ReporteComprasAgrupacionTest extends TestCase
{
    public function test_agrupa_cantidades_sin_duplicar_series_ni_mezclar_variantes(): void
    {
        Schema::create('series', function (Blueprint $table) {
            $table->id();
            foreach (['empresa_id', 'compra_id', 'producto_id', 'variante_id'] as $campo) {
                $table->integer($campo)->nullable();
            }
            foreach (['imei', 'imei2', 'serie'] as $campo) {
                $table->string($campo)->nullable();
            }
        });
        DB::table('series')->insert([
            ['empresa_id' => 1, 'compra_id' => 10, 'producto_id' => 7, 'variante_id' => null, 'imei' => '111'],
            ['empresa_id' => 1, 'compra_id' => 10, 'producto_id' => 7, 'variante_id' => null, 'imei' => '222'],
            ['empresa_id' => 1, 'compra_id' => 11, 'producto_id' => 7, 'variante_id' => null, 'imei' => '999'],
        ]);
        $detalles = collect([
            (object) ['id' => 1, 'producto_id' => 7, 'variante_id' => null, 'cantidad' => 1, 'subtotal' => 100, 'precio_compra' => 100, 'precio_venta' => 150],
            (object) ['id' => 2, 'producto_id' => 7, 'variante_id' => null, 'cantidad' => 1, 'subtotal' => 120, 'precio_compra' => 120, 'precio_venta' => 150],
            (object) ['id' => 3, 'producto_id' => 7, 'variante_id' => 8, 'cantidad' => 3, 'subtotal' => 270, 'precio_compra' => 90, 'precio_venta' => 140],
        ]);
        $method = new ReflectionMethod(ReporteComprasController::class, 'agregarSeries');
        $rows = $method->invoke(new ReporteComprasController, $detalles, 10, 1);

        $this->assertCount(2, $rows);
        $this->assertEquals(2, $rows[0]->cantidad);
        $this->assertEquals(220, $rows[0]->subtotal);
        $this->assertSame(['111', '222'], $rows[0]->series);
        $this->assertEquals(100, $rows[0]->precio_compra);
        $this->assertEquals(120, $rows[0]->precio_compra_max);
        $this->assertEquals(150, $rows[0]->precio_venta_max);
        $this->assertEquals(3, $rows[1]->cantidad);
        $this->assertSame([], $rows[1]->series);
        $this->assertEquals($detalles->sum('subtotal'), $rows->sum('subtotal'));
    }
}
