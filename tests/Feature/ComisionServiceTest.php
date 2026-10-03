<?php

namespace Tests\Feature;

use App\Models\ComisionEsquema;
use App\Models\ComisionMovimiento;
use App\Models\ComisionRegla;
use App\Models\Devolucion;
use App\Models\Venta;
use App\Services\ComisionService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CreaTablasComisiones;
use Tests\TestCase;

class ComisionServiceTest extends TestCase
{
    use CreaTablasComisiones;

    private const VENDEDOR = 10;
    private const CAJERO = 20;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTablasComisiones();

        // Categorías: 1 = Ropa (padre), 2 = Calzado (hija de Ropa), 3 = Accesorios
        DB::table('categorias')->insert([
            ['id' => 1, 'categoria_padre_id' => null], ['id' => 2, 'categoria_padre_id' => 1], ['id' => 3, 'categoria_padre_id' => null],
        ]);
        // Productos: 100 = tenis (Calzado), 200 = cinturón (Accesorios), 300 = playera (sin categoría)
        DB::table('productos')->insert([
            ['id' => 100, 'categoria_id' => 2], ['id' => 200, 'categoria_id' => 3], ['id' => 300, 'categoria_id' => null],
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function esquema(string $beneficiario = 'vendedor', bool $activo = true): ComisionEsquema
    {
        return ComisionEsquema::create([
            'empresa_id' => 1, 'nombre' => uniqid('Esquema '), 'beneficiario' => $beneficiario, 'activo' => $activo,
        ]);
    }

    private function regla(ComisionEsquema $esquema, array $datos, array $precios = []): ComisionRegla
    {
        $regla = ComisionRegla::create($datos + [
            'empresa_id' => 1, 'esquema_id' => $esquema->id, 'aplica_a' => 'general',
            'base' => 'venta', 'tipo' => 'porcentaje', 'valor' => 0,
        ]);
        foreach ($precios as $lista => [$activo, $tipo, $valor]) {
            $regla->precios()->create(['lista' => $lista, 'activo' => $activo, 'tipo' => $tipo, 'valor' => $valor]);
        }
        return $regla;
    }

    private function usuarios(?ComisionEsquema $vendedor, ?ComisionEsquema $cajero = null): void
    {
        DB::table('users')->insert([
            ['id' => self::VENDEDOR, 'empresa_id' => 1, 'name' => 'Vendedor', 'esquema_vendedor_id' => $vendedor?->id, 'esquema_cajero_id' => null],
            ['id' => self::CAJERO, 'empresa_id' => 1, 'name' => 'Cajero', 'esquema_vendedor_id' => $vendedor?->id, 'esquema_cajero_id' => $cajero?->id],
        ]);
    }

    /** @param array<int, array{0:int,1:float,2:float,3:?string,4?:?float}> $lineas producto, cantidad, precio, lista, costo */
    private function venta(array $lineas, float $descuento = 0, int $cajero = self::CAJERO, int $vendedor = self::VENDEDOR): Venta
    {
        $venta = Venta::create([
            'empresa_id' => 1, 'sucursal_id' => 1, 'user_id' => $cajero, 'vendedor_id' => $vendedor,
            'fecha' => '2026-10-01', 'estado' => 'confirmada', 'descuento' => $descuento,
        ]);
        foreach ($lineas as $l) {
            $venta->detalles()->create([
                'producto_id' => $l[0], 'cantidad' => $l[1], 'precio_venta' => $l[2],
                'subtotal' => $l[1] * $l[2], 'lista_precio_usada' => $l[3], 'precio_costo' => $l[4] ?? null,
            ]);
        }
        return $venta;
    }

    private function servicio(): ComisionService
    {
        return new ComisionService();
    }

    private function total(string $beneficiario = 'vendedor'): float
    {
        return round((float) ComisionMovimiento::where('beneficiario', $beneficiario)->sum('monto'), 2);
    }

    // ── Resolución de reglas ──────────────────────────────────────────────────

    public function test_aplica_regla_mas_especifica_y_cae_a_general(): void
    {
        $e = $this->esquema();
        $this->regla($e, ['aplica_a' => 'categoria', 'referencia_id' => 2, 'valor' => 5]);           // Calzado 5%
        $this->regla($e, ['aplica_a' => 'categoria', 'referencia_id' => 3, 'tipo' => 'fijo_por_pieza', 'valor' => 10]); // Accesorios $10/pza
        $this->regla($e, ['aplica_a' => 'general', 'valor' => 2]);                                   // General 2%
        $this->usuarios($e);

        $this->servicio()->registrarVenta($this->venta([
            [100, 1, 1000, 'P1'],   // 5% de 1000 = 50
            [200, 2, 150, 'P1'],    // 2 × $10 = 20
            [300, 1, 500, 'P1'],    // 2% de 500 = 10
        ]));

        $montos = ComisionMovimiento::orderBy('venta_detalle_id')->pluck('monto')->map(fn($m) => (float) $m)->all();
        $this->assertSame([50.0, 20.0, 10.0], $montos);
    }

    public function test_regla_de_producto_gana_y_categoria_padre_aplica_a_hija(): void
    {
        $e = $this->esquema();
        $this->regla($e, ['aplica_a' => 'categoria', 'referencia_id' => 1, 'valor' => 3]); // Ropa (padre de Calzado)
        $this->regla($e, ['aplica_a' => 'general', 'valor' => 1]);
        $this->usuarios($e);

        $this->servicio()->registrarVenta($this->venta([[100, 1, 1000, 'P1']]));
        $this->assertSame(30.0, $this->total());

        $this->regla($e, ['aplica_a' => 'producto', 'referencia_id' => 100, 'valor' => 7]);
        $this->servicio()->registrarVenta($this->venta([[100, 1, 1000, 'P1']]));
        $this->assertSame(100.0, $this->total()); // 30 + 70
    }

    public function test_sin_reglas_no_genera_movimientos(): void
    {
        $e = $this->esquema();
        $this->regla($e, ['aplica_a' => 'categoria', 'referencia_id' => 3, 'valor' => 5]);
        $this->usuarios($e);

        $this->servicio()->registrarVenta($this->venta([[300, 1, 500, 'P1']]));

        $this->assertSame(0, ComisionMovimiento::count());
    }

    // ── Tabla de precios ──────────────────────────────────────────────────────

    public function test_tabla_de_precios_por_lista_y_precio_apagado_da_cero(): void
    {
        $e = $this->esquema();
        $this->regla($e, ['aplica_a' => 'categoria', 'referencia_id' => 2, 'valor' => 5], [
            'P1'     => [true, 'porcentaje', 4],
            'P2'     => [true, 'fijo_por_pieza', 15],
            'P3'     => [false, 'porcentaje', 0],
            'MANUAL' => [false, 'porcentaje', 0],
        ]);
        $this->regla($e, ['aplica_a' => 'general', 'valor' => 2]); // no debe usarse para P3 apagado
        $this->usuarios($e);

        $this->servicio()->registrarVenta($this->venta([
            [100, 1, 1000, 'P1'],       // 4% = 40
            [100, 2, 900, 'P2'],        // 2 × 15 = 30
            [100, 1, 800, 'P3'],        // apagado = 0
            [100, 1, 777, null],        // manual apagado = 0
            [100, 1, 1000, 'P.Venta'],  // sin fila ⇒ default de la regla 5% = 50
        ]));

        $this->assertSame(120.0, $this->total());
        $this->assertSame(3, ComisionMovimiento::count());
    }

    public function test_lista_desconocida_se_trata_como_manual(): void
    {
        $e = $this->esquema();
        $this->regla($e, ['aplica_a' => 'general', 'valor' => 2], ['MANUAL' => [false, 'porcentaje', 0]]);
        $this->usuarios($e);

        $this->servicio()->registrarVenta($this->venta([[300, 1, 500, 'Mayoreo raro']]));

        $this->assertSame(0, ComisionMovimiento::count());
    }

    // ── Rangos de precio ──────────────────────────────────────────────────────

    public function test_fuera_de_rango_usar_siguiente_cae_a_general(): void
    {
        $e = $this->esquema();
        $this->regla($e, ['aplica_a' => 'categoria', 'referencia_id' => 2, 'valor' => 4, 'precio_min' => 500, 'precio_max' => 1999.99]);
        $this->regla($e, ['aplica_a' => 'general', 'valor' => 2]);
        $this->usuarios($e);

        $this->servicio()->registrarVenta($this->venta([
            [100, 1, 800, 'P1'],    // dentro: 4% = 32
            [100, 1, 300, 'P1'],    // fuera ⇒ general 2% = 6
            [100, 1, 2500, 'P1'],   // fuera ⇒ general 2% = 50
        ]));

        $this->assertSame(88.0, $this->total());
    }

    public function test_fuera_de_rango_sin_comision_da_cero(): void
    {
        $e = $this->esquema();
        $this->regla($e, ['aplica_a' => 'categoria', 'referencia_id' => 2, 'valor' => 4,
            'precio_min' => 500, 'precio_max' => 1999.99, 'fuera_de_rango' => 'sin_comision']);
        $this->regla($e, ['aplica_a' => 'general', 'valor' => 2]);
        $this->usuarios($e);

        $this->servicio()->registrarVenta($this->venta([[100, 1, 800, 'P1'], [100, 1, 300, 'P1']]));

        $this->assertSame(32.0, $this->total());
    }

    public function test_varios_rangos_en_misma_categoria_y_conflicto_gana_sin_comision(): void
    {
        $e = $this->esquema();
        $this->regla($e, ['aplica_a' => 'categoria', 'referencia_id' => 2, 'tipo' => 'fijo_por_pieza', 'valor' => 10, 'precio_max' => 499.99]);
        $this->regla($e, ['aplica_a' => 'categoria', 'referencia_id' => 2, 'valor' => 4, 'precio_min' => 500, 'precio_max' => 1999.99,
            'fuera_de_rango' => 'sin_comision']);
        $this->regla($e, ['aplica_a' => 'general', 'valor' => 2]);
        $this->usuarios($e);

        $this->servicio()->registrarVenta($this->venta([
            [100, 1, 300, 'P1'],    // primer rango: $10
            [100, 1, 1000, 'P1'],   // segundo rango: 4% = 40
            [100, 1, 3000, 'P1'],   // ninguno cubre; conflicto ⇒ sin_comision = 0
        ]));

        $this->assertSame(50.0, $this->total());
    }

    public function test_regla_con_rango_gana_a_regla_sin_rango_del_mismo_nivel(): void
    {
        $e = $this->esquema();
        $this->regla($e, ['aplica_a' => 'categoria', 'referencia_id' => 2, 'valor' => 3]);
        $this->regla($e, ['aplica_a' => 'categoria', 'referencia_id' => 2, 'valor' => 6, 'precio_min' => 1000]);
        $this->usuarios($e);

        $this->servicio()->registrarVenta($this->venta([[100, 1, 1500, 'P1'], [100, 1, 500, 'P1']]));

        $this->assertSame(105.0, $this->total()); // 6% de 1500 + 3% de 500
    }

    // ── Base y descuentos ─────────────────────────────────────────────────────

    public function test_descuento_global_se_reparte_y_base_utilidad(): void
    {
        $e = $this->esquema();
        $this->regla($e, ['aplica_a' => 'categoria', 'referencia_id' => 2, 'valor' => 10, 'base' => 'utilidad']);
        $this->regla($e, ['aplica_a' => 'general', 'valor' => 10]);
        $this->usuarios($e);

        // Subtotal 1000 (600 + 400), descuento global 100 ⇒ netos 540 y 360
        $this->servicio()->registrarVenta($this->venta([
            [100, 1, 600, 'P1', 400],   // utilidad = 540 - 400 = 140 ⇒ 14
            [300, 1, 400, 'P1'],        // venta 360 ⇒ 36
        ], 100));

        $movs = ComisionMovimiento::orderBy('venta_detalle_id')->get();
        $this->assertSame(140.0, (float) $movs[0]->base_calculo);
        $this->assertSame(14.0, (float) $movs[0]->monto);
        $this->assertSame(360.0, (float) $movs[1]->base_calculo);
        $this->assertSame(36.0, (float) $movs[1]->monto);
    }

    public function test_utilidad_negativa_no_genera_comision(): void
    {
        $e = $this->esquema();
        $this->regla($e, ['aplica_a' => 'general', 'valor' => 10, 'base' => 'utilidad']);
        $this->usuarios($e);

        $this->servicio()->registrarVenta($this->venta([[300, 1, 100, 'P1', 150]]));

        $this->assertSame(0, ComisionMovimiento::count());
    }

    // ── Beneficiarios ─────────────────────────────────────────────────────────

    public function test_vendedor_y_cajero_distintos_cobran_ambos(): void
    {
        $ev = $this->esquema('vendedor');
        $ec = $this->esquema('cajero');
        $this->regla($ev, ['valor' => 3]);
        $this->regla($ec, ['valor' => 1]);
        $this->usuarios($ev, $ec);

        $this->servicio()->registrarVenta($this->venta([[300, 1, 1000, 'P1']]));

        $this->assertSame(30.0, $this->total('vendedor'));
        $this->assertSame(10.0, $this->total('cajero'));
        $this->assertSame(self::CAJERO, (int) ComisionMovimiento::where('beneficiario', 'cajero')->value('user_id'));
    }

    public function test_misma_persona_solo_cobra_como_vendedor(): void
    {
        $ev = $this->esquema('vendedor');
        $ec = $this->esquema('cajero');
        $this->regla($ev, ['valor' => 3]);
        $this->regla($ec, ['valor' => 1]);
        $this->usuarios($ev, $ec);

        $this->servicio()->registrarVenta($this->venta([[300, 1, 1000, 'P1']], 0, self::CAJERO, self::CAJERO));

        $this->assertSame(30.0, $this->total('vendedor'));
        $this->assertSame(0.0, $this->total('cajero'));
    }

    public function test_ignora_esquema_inactivo_o_de_otro_tipo(): void
    {
        $inactivo = $this->esquema('vendedor', false);
        $this->regla($inactivo, ['valor' => 3]);
        $cajero = $this->esquema('cajero');
        $this->regla($cajero, ['valor' => 1]);
        // El vendedor tiene asignado un esquema inactivo; el cajero un esquema de vendedor (tipo incorrecto).
        DB::table('users')->insert([
            ['id' => self::VENDEDOR, 'empresa_id' => 1, 'name' => 'V', 'esquema_vendedor_id' => $inactivo->id, 'esquema_cajero_id' => null],
            ['id' => self::CAJERO, 'empresa_id' => 1, 'name' => 'C', 'esquema_vendedor_id' => null, 'esquema_cajero_id' => $inactivo->id],
        ]);

        $this->servicio()->registrarVenta($this->venta([[300, 1, 1000, 'P1']]));

        $this->assertSame(0, ComisionMovimiento::count());
    }

    public function test_registrar_venta_es_idempotente(): void
    {
        $e = $this->esquema();
        $this->regla($e, ['valor' => 2]);
        $this->usuarios($e);
        $venta = $this->venta([[300, 1, 1000, 'P1']]);

        $this->servicio()->registrarVenta($venta);
        $this->servicio()->registrarVenta($venta);

        $this->assertSame(1, ComisionMovimiento::count());
    }

    // ── Cancelaciones y devoluciones ──────────────────────────────────────────

    public function test_cancelacion_revierte_todo(): void
    {
        $ev = $this->esquema('vendedor');
        $ec = $this->esquema('cajero');
        $this->regla($ev, ['valor' => 3]);
        $this->regla($ec, ['valor' => 1]);
        $this->usuarios($ev, $ec);
        $venta = $this->venta([[300, 2, 500, 'P1'], [200, 1, 200, 'P1']]);
        $this->servicio()->registrarVenta($venta);

        $venta->update(['estado' => 'cancelada']);
        $this->servicio()->revertirCancelacion($venta);
        $this->servicio()->revertirCancelacion($venta); // idempotente

        $this->assertSame(0.0, $this->total('vendedor'));
        $this->assertSame(0.0, $this->total('cajero'));
        $this->assertSame(4, ComisionMovimiento::where('tipo', 'cancelacion')->count());
    }

    public function test_devolucion_parcial_descuenta_proporcional_y_cancelacion_posterior_cuadra(): void
    {
        $e = $this->esquema();
        $this->regla($e, ['valor' => 10]);
        $this->usuarios($e);
        $venta = $this->venta([[300, 4, 250, 'P1']]); // 1000 ⇒ 100
        $this->servicio()->registrarVenta($venta);
        $detalle = $venta->detalles()->first();

        $devolucion = Devolucion::create(['venta_id' => $venta->id, 'estado' => 'confirmada']);
        $devolucion->detalles()->create(['venta_detalle_id' => $detalle->id, 'cantidad' => 1]);
        $this->servicio()->registrarDevolucion($devolucion);
        $this->servicio()->registrarDevolucion($devolucion); // idempotente

        $this->assertSame(75.0, $this->total());
        $this->assertSame(-25.0, (float) ComisionMovimiento::where('tipo', 'devolucion')->value('monto'));

        $this->servicio()->revertirCancelacion($venta);
        $this->assertSame(0.0, $this->total());
        $this->assertSame(-75.0, (float) ComisionMovimiento::where('tipo', 'cancelacion')->value('monto'));
    }

    // ── Integración con ventas ────────────────────────────────────────────────

    public function test_sin_bloquear_deshace_solo_la_comision_y_no_lanza(): void
    {
        $e = $this->esquema();
        $this->regla($e, ['valor' => 2]);
        $this->usuarios($e);
        $venta = $this->venta([[300, 1, 1000, 'P1']]);

        DB::beginTransaction();
        $this->servicio()->sinBloquear(function (ComisionService $s) use ($venta) {
            $s->registrarVenta($venta);
            throw new \RuntimeException('falla simulada');
        });
        $this->assertTrue(Venta::whereKey($venta->id)->exists()); // la venta sigue en la transacción externa
        DB::commit();

        $this->assertSame(0, ComisionMovimiento::count());
    }

    public function test_lista_de_precio_se_valida_contra_el_precio_cobrado(): void
    {
        $producto = new \App\Models\Producto();
        $producto->forceFill(['precio_venta' => 500, 'precio1' => 450, 'precio2' => 450, 'precio3' => 400]);

        $controller = app(\App\Http\Controllers\VentaController::class);
        $resolver = fn(float $precio, ?string $preferida) => (fn() => $this->resolverListaPrecioUsada($precio, null, $producto, $preferida))
            ->call($controller);

        $this->assertSame('P.Venta', $resolver(500, 'P1'));  // etiqueta incorrecta del POS ⇒ se corrige
        $this->assertSame('P2', $resolver(450, 'P2'));       // empate de precios ⇒ respeta la etiqueta
        $this->assertSame('P1', $resolver(450, null));
        $this->assertSame('P3', $resolver(400, 'otra'));
        $this->assertNull($resolver(333, 'P1'));             // precio manual
    }

    // ── Probar regla (sin guardar) ────────────────────────────────────────────

    public function test_calcular_renglon_explica_el_motivo(): void
    {
        $e = $this->esquema();
        $this->regla($e, ['aplica_a' => 'categoria', 'referencia_id' => 2, 'valor' => 5, 'precio_max' => 999,
            'fuera_de_rango' => 'sin_comision'], ['P3' => [false, 'porcentaje', 0]]);

        $s = $this->servicio();
        $this->assertSame('aplicada', $s->calcularRenglon($e, 100, 2, 500, 'P1', 1, 500, null)['motivo']);
        $this->assertSame('precio_apagado', $s->calcularRenglon($e, 100, 2, 500, 'P3', 1, 500, null)['motivo']);
        $this->assertSame('fuera_de_rango', $s->calcularRenglon($e, 100, 2, 1500, 'P1', 1, 1500, null)['motivo']);
        $this->assertSame('sin_regla', $s->calcularRenglon($e, 300, null, 500, 'P1', 1, 500, null)['motivo']);
        $this->assertSame(25.0, $s->calcularRenglon($e, 100, 2, 500, 'P1', 1, 500, null)['monto']);
    }
}
