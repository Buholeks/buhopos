<?php

namespace Tests\Feature;

use App\Models\CorteCaja;
use App\Models\CuentaBancaria;
use App\Models\Empresa;
use App\Models\MovimientoCaja;
use App\Models\Sucursal;
use App\Models\TerminalPago;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsultaCajaCuentaFiltroTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // La consulta usa DATE_FORMAT de MySQL; SQLite no lo trae.
        DB::connection()->getPdo()->sqliteCreateFunction('DATE_FORMAT', fn($fecha, $formato) => $fecha === null
            ? null
            : Carbon::parse($fecha)->format(strtr($formato, ['%Y' => 'Y', '%m' => 'm', '%d' => 'd', '%H' => 'H', '%i' => 'i', '%S' => 's'])));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_filtra_tarjeta_por_terminal_y_transferencia_por_cuenta(): void
    {
        Carbon::setTestNow('2026-06-30 10:00:00');
        [$user, $corte] = $this->crearContextoCaja();
        Sanctum::actingAs($user);

        $catalogo = ['empresa_id' => $user->empresa_id, 'sucursal_id' => $user->sucursal_id, 'user_id' => $user->id, 'activo' => true];
        $terminalA = TerminalPago::create($catalogo + ['nombre' => 'Terminal A', 'banco' => 'BBVA']);
        $terminalB = TerminalPago::create($catalogo + ['nombre' => 'Terminal B']);
        $cuentaA = CuentaBancaria::create($catalogo + ['nombre' => 'Cuenta A']);
        $cuentaB = CuentaBancaria::create($catalogo + ['nombre' => 'Cuenta B', 'activo' => false]);

        $mov = fn(string $forma, string $concepto, array $extra = []) => MovimientoCaja::create($extra + [
            'corte_id' => $corte->id,
            'user_id' => $user->id,
            'tipo' => 'ingreso',
            'forma_pago' => $forma,
            'monto' => 10,
            'concepto' => $concepto,
        ]);
        $mov('tarjeta', 'Tarjeta A', ['terminal_pago_id' => $terminalA->id]);
        $mov('tarjeta', 'Tarjeta B', ['terminal_pago_id' => $terminalB->id]);
        $mov('transferencia', 'Transf A', ['cuenta_bancaria_id' => $cuentaA->id]);
        $mov('transferencia', 'Transf B', ['cuenta_bancaria_id' => $cuentaB->id]);
        $mov('efectivo', 'Efectivo');

        $conceptos = fn(string $params) => collect($this->getJson("/api/movimientos-caja?desde=2026-06-30&hasta=2026-06-30&origen=movimiento&{$params}")
            ->assertOk()
            ->json('data'))
            ->pluck('concepto')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['Tarjeta A', 'Tarjeta B'], $conceptos('forma_pago=tarjeta'));
        $this->assertSame(['Tarjeta A'], $conceptos("forma_pago=tarjeta&terminal_pago_id={$terminalA->id}"));
        $this->assertSame(['Transf B'], $conceptos("forma_pago=transferencia&cuenta_bancaria_id={$cuentaB->id}"));
        // Una terminal no filtra si la forma de pago no es tarjeta.
        $this->assertSame(['Transf A', 'Transf B'], $conceptos("forma_pago=transferencia&terminal_pago_id={$terminalA->id}"));

        $this->getJson('/api/reportes/cuentas-pago')
            ->assertOk()
            ->assertJsonPath('terminales.0.nombre', 'Terminal A · BBVA')
            ->assertJsonPath('cuentas.1.nombre', 'Cuenta B');
    }

    private function crearContextoCaja(): array
    {
        $empresa = Empresa::create(['nombre' => 'Empresa filtro cuentas', 'activo' => true]);
        $sucursal = Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Matriz', 'activo' => true]);
        $user = User::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'name' => 'Usuario caja',
            'email' => 'filtro-cuentas-' . uniqid() . '@example.com',
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
