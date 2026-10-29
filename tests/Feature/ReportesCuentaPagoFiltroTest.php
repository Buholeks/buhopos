<?php

namespace Tests\Feature;

use App\Models\CorteCaja;
use App\Models\CuentaBancaria;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\TerminalPago;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\VentaPago;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportesCuentaPagoFiltroTest extends TestCase
{
    use RefreshDatabase;

    private const FECHAS = 'fecha_desde=2026-06-30&fecha_hasta=2026-06-30';

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_reportes_filtran_por_terminal_y_cuenta(): void
    {
        Carbon::setTestNow('2026-06-30 10:00:00');
        [$user, $corte] = $this->crearContexto();
        Sanctum::actingAs($user);

        $catalogo = ['empresa_id' => $user->empresa_id, 'sucursal_id' => $user->sucursal_id, 'user_id' => $user->id, 'activo' => true];
        $terminalA = TerminalPago::create($catalogo + ['nombre' => 'Terminal A']);
        $terminalB = TerminalPago::create($catalogo + ['nombre' => 'Terminal B']);
        $cuentaA = CuentaBancaria::create($catalogo + ['nombre' => 'Cuenta A']);

        $producto = Producto::create([
            'empresa_id' => $user->empresa_id,
            'sucursal_id' => $user->sucursal_id,
            'user_id' => $user->id,
            'nombre' => 'Producto reporte',
            'codigo' => 'REP-001',
            'precio_costo' => 5,
            'precio_venta' => 10,
            'activo' => true,
            'tiene_variantes' => false,
            'tiene_series' => false,
        ]);

        $this->crearVenta($user, $corte, $producto, 'VTA-TA', ['forma_pago' => 'tarjeta', 'terminal_pago_id' => $terminalA->id]);
        $this->crearVenta($user, $corte, $producto, 'VTA-TB', ['forma_pago' => 'tarjeta', 'terminal_pago_id' => $terminalB->id]);
        $this->crearVenta($user, $corte, $producto, 'VTA-CA', ['forma_pago' => 'transferencia', 'cuenta_bancaria_id' => $cuentaA->id]);

        // Reporte de ventas
        $folios = fn(string $params) => collect($this->getJson('/api/reportes/ventas?' . self::FECHAS . "&{$params}")
            ->assertOk()->json('ventas.data'))->pluck('folio')->sort()->values()->all();
        $this->assertSame(['VTA-TA', 'VTA-TB'], $folios('forma_pago=tarjeta'));
        $this->assertSame(['VTA-TA'], $folios("forma_pago=tarjeta&terminal_pago_id={$terminalA->id}"));
        $this->assertSame(['VTA-CA'], $folios("forma_pago=transferencia&cuenta_bancaria_id={$cuentaA->id}"));

        // Ventas de un corte (reporte de caja)
        $this->assertSame(['VTA-TB'], collect($this->getJson("/api/reportes/caja/{$corte->id}/ventas?forma_pago=tarjeta&terminal_pago_id={$terminalB->id}")
            ->assertOk()->json('data'))->pluck('folio')->all());

        // Ventas agrupadas por artículo
        $this->getJson('/api/reportes/ventas-agrupado/articulos?' . self::FECHAS . "&forma_pago=tarjeta&terminal_pago_id={$terminalA->id}")
            ->assertOk()
            ->assertJsonPath('data.0.cantidad_vendida', 1);

        // Utilidades
        $this->getJson('/api/reportes/utilidades?' . self::FECHAS . "&forma_pago=tarjeta&terminal_pago_id={$terminalA->id}")
            ->assertOk()
            ->assertJsonPath('resumen.ventas', 1);
        $this->getJson('/api/reportes/utilidades?' . self::FECHAS . '&forma_pago=tarjeta')
            ->assertOk()
            ->assertJsonPath('resumen.ventas', 2);
    }

    private function crearVenta(User $user, CorteCaja $corte, Producto $producto, string $folio, array $pago): void
    {
        $venta = Venta::create([
            'empresa_id' => $user->empresa_id,
            'sucursal_id' => $user->sucursal_id,
            'user_id' => $user->id,
            'corte_id' => $corte->id,
            'folio' => $folio,
            'fecha' => '2026-06-30',
            'subtotal' => 10,
            'descuento' => 0,
            'total' => 10,
            'estado' => 'confirmada',
        ]);

        VentaDetalle::create([
            'venta_id' => $venta->id,
            'producto_id' => $producto->id,
            'producto_nombre' => $producto->nombre,
            'cantidad' => 1,
            'precio_venta' => 10,
            'precio_costo' => 5,
            'descuento' => 0,
            'subtotal' => 10,
        ]);

        VentaPago::create($pago + ['venta_id' => $venta->id, 'monto' => 10]);
    }

    private function crearContexto(): array
    {
        $empresa = Empresa::create(['nombre' => 'Empresa reportes cuentas', 'activo' => true]);
        $sucursal = Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Matriz', 'activo' => true]);
        $user = User::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'name' => 'Usuario reportes',
            'email' => 'reportes-cuentas-' . uniqid() . '@example.com',
            'password' => 'password',
            'activo' => true,
        ]);
        $user->sucursales()->attach($sucursal->id);

        $corte = CorteCaja::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'user_id' => $user->id,
            'terminal' => 'POS-01',
            'estado' => 'abierto',
            'fecha_apertura' => Carbon::parse('2026-06-30 08:00:00'),
            'fondo_inicial_efectivo' => 0,
        ]);

        return [$user, $corte];
    }
}
