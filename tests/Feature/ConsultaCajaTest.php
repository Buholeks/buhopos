<?php

namespace Tests\Feature;

use App\Models\CorteCaja;
use App\Models\Empresa;
use App\Models\MovimientoCaja;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsultaCajaTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_consulta_movimientos_filtra_movimientos_por_fecha_de_movimiento(): void
    {
        Carbon::setTestNow('2026-06-30 10:00:00');
        [$user, $corte] = $this->crearContextoCaja();
        Sanctum::actingAs($user);

        MovimientoCaja::create([
            'corte_id' => $corte->id,
            'user_id' => $user->id,
            'tipo' => 'egreso',
            'forma_pago' => 'efectivo',
            'monto' => 25,
            'concepto' => 'Retiro de prueba',
            'created_at' => Carbon::parse('2026-06-30 09:00:00'),
            'updated_at' => Carbon::parse('2026-06-30 09:00:00'),
        ]);

        MovimientoCaja::create([
            'corte_id' => $corte->id,
            'user_id' => $user->id,
            'tipo' => 'egreso',
            'forma_pago' => 'efectivo',
            'monto' => 99,
            'concepto' => 'Retiro de ayer UTC',
            'created_at' => Carbon::parse('2026-06-30 03:00:00'),
            'updated_at' => Carbon::parse('2026-06-30 03:00:00'),
        ]);

        $this->getJson('/api/movimientos-caja?desde=2026-06-30&hasta=2026-06-30&origen=movimiento')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.concepto', 'Retiro de prueba')
            ->assertJsonPath('summary.egresos', 25);

    }

    public function test_consulta_caja_no_mezcla_registros_de_otra_empresa(): void
    {
        Carbon::setTestNow('2026-06-30 10:00:00');
        [$user, $corte] = $this->crearContextoCaja();
        $otraEmpresa = Empresa::create(['nombre' => 'Empresa ajena consulta caja', 'activo' => true]);
        Sanctum::actingAs($user);

        MovimientoCaja::create([
            'corte_id' => $corte->id,
            'user_id' => $user->id,
            'tipo' => 'ingreso',
            'forma_pago' => 'efectivo',
            'monto' => 50,
            'concepto' => 'Ingreso propio',
            'created_at' => Carbon::parse('2026-06-30 09:00:00'),
            'updated_at' => Carbon::parse('2026-06-30 09:00:00'),
        ]);

        $corteAjeno = CorteCaja::create([
            'empresa_id' => $otraEmpresa->id,
            'sucursal_id' => $user->sucursal_id,
            'user_id' => $user->id,
            'terminal' => 'POS-99',
            'estado' => 'abierto',
            'fecha_apertura' => Carbon::parse('2026-06-30 08:00:00'),
            'fondo_inicial_efectivo' => 0,
        ]);

        MovimientoCaja::create([
            'corte_id' => $corteAjeno->id,
            'user_id' => $user->id,
            'tipo' => 'ingreso',
            'forma_pago' => 'efectivo',
            'monto' => 999,
            'concepto' => 'Ingreso ajeno',
            'created_at' => Carbon::parse('2026-06-30 09:30:00'),
            'updated_at' => Carbon::parse('2026-06-30 09:30:00'),
        ]);

        $this->getJson('/api/movimientos-caja?desde=2026-06-30&hasta=2026-06-30&origen=movimiento')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.concepto', 'Ingreso propio')
            ->assertJsonPath('summary.ingresos', 50);
    }

    public function test_consulta_ventas_reporta_monto_cobrado_sin_saldo_aplicado(): void
    {
        Carbon::setTestNow('2026-06-30 10:00:00');
        [$user, $corte] = $this->crearContextoCaja();
        Sanctum::actingAs($user);

        Venta::create([
            'empresa_id' => $user->empresa_id,
            'sucursal_id' => $user->sucursal_id,
            'user_id' => $user->id,
            'corte_id' => $corte->id,
            'folio' => 'VTA-SALDO',
            'fecha' => '2026-06-30',
            'forma_pago' => 'efectivo',
            'subtotal' => 100,
            'descuento' => 0,
            'saldo_aplicado' => 25,
            'total' => 100,
            'estado' => 'confirmada',
        ]);

        $this->getJson('/api/movimientos-caja?desde=2026-06-30&hasta=2026-06-30&origen=venta')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.monto', '75.00')
            ->assertJsonPath('summary.ingresos', 75);

    }

    public function test_usuarios_consulta_caja_no_requiere_permiso_de_gestionar_usuarios(): void
    {
        [$user] = $this->crearContextoCaja(conRolCaja: true);
        Sanctum::actingAs($user);

        $this->getJson('/api/movimientos-caja/usuarios')
            ->assertOk()
            ->assertJsonFragment(['id' => $user->id, 'name' => $user->name]);

        $this->getJson('/api/users')->assertForbidden();
    }

    private function crearContextoCaja(bool $conRolCaja = false): array
    {
        $empresa = Empresa::create(['nombre' => 'Empresa consulta caja', 'activo' => true]);
        $sucursal = Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Sucursal consulta', 'activo' => true]);
        $user = User::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'name' => 'Usuario caja',
            'email' => 'consulta-caja-' . uniqid() . '@example.com',
            'password' => 'password',
            'activo' => true,
        ]);

        $pivot = [];
        if ($conRolCaja) {
            $permiso = Permiso::firstOrCreate(
                ['clave' => 'caja.historial'],
                ['modulo' => 'caja', 'descripcion' => 'Ver historial de cortes de caja']
            );
            $rol = Rol::create(['empresa_id' => $empresa->id, 'nombre' => 'Caja historial']);
            $rol->permisos()->sync([$permiso->id]);
            $pivot = ['role_id' => $rol->id];
        }

        $user->sucursales()->attach($sucursal->id, $pivot);

        $corte = CorteCaja::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'user_id' => $user->id,
            'terminal' => 'POS-01',
            'estado' => 'abierto',
            'fecha_apertura' => Carbon::parse('2026-06-29 08:00:00'),
            'fondo_inicial_efectivo' => 0,
        ]);

        return [$user, $corte];
    }
}
