<?php

namespace Tests\Feature;

use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\CorteCaja;
use App\Models\Empresa;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DevolucionProveedorTest extends TestCase
{
    use DatabaseTransactions;

    public function test_cancelacion_pagada_crea_saldo_favor_y_sigue_localizable(): void
    {
        [$user, $compra] = $this->crearCompraPagada();
        Sanctum::actingAs($user);

        $this->postJson("/api/compras/{$compra->id}/cancelar", [
            'fecha' => now()->toDateString(),
            'motivo' => 'Cancelacion solicitada al proveedor',
            'destino_excedente' => 'saldo_favor',
        ])->assertCreated();

        $this->assertDatabaseHas('compras', ['id' => $compra->id, 'estado' => 'cancelada']);
        $this->assertDatabaseHas('proveedor_saldo_movimientos', [
            'compra_id' => null,
            'proveedor_id' => $compra->proveedor_id,
            'tipo' => 'credito',
            'monto' => 100,
        ]);
        $this->assertSame(0.0, (float) Inventario::where('producto_id', $compra->detalles->first()->producto_id)->value('stock'));

        $this->getJson('/api/devoluciones-proveedor/compras?q=DEV-TEST')
            ->assertOk()
            ->assertJsonPath('0.id', $compra->id)
            ->assertJsonPath('0.estado', 'cancelada');
    }

    public function test_cancelacion_con_reembolso_registra_ingreso_en_caja(): void
    {
        [$user, $compra] = $this->crearCompraPagada();
        Sanctum::actingAs($user);

        $corte = CorteCaja::create([
            'empresa_id' => $user->empresa_id,
            'sucursal_id' => $user->sucursal_id,
            'user_id' => $user->id,
            'estado' => 'abierto',
            'fecha_apertura' => now(),
            'fondo_inicial_efectivo' => 0,
        ]);

        $this->postJson("/api/compras/{$compra->id}/cancelar", [
            'fecha' => now()->toDateString(),
            'motivo' => 'Reembolso recibido del proveedor',
            'destino_excedente' => 'caja',
            'forma_reembolso' => 'efectivo',
        ])->assertCreated();

        $this->assertDatabaseHas('movimientos_caja', [
            'corte_id' => $corte->id,
            'tipo' => 'ingreso',
            'forma_pago' => 'efectivo',
            'monto' => 100,
        ]);
        $this->assertSame(100.0, (float) $corte->fresh()->movs_efectivo);
    }

    public function test_permite_dos_devoluciones_parciales_de_la_misma_compra(): void
    {
        [$user, $compra] = $this->crearCompraPagada();
        Sanctum::actingAs($user);
        $detalle = $compra->detalles->first();

        $payload = [
            'compra_id' => $compra->id,
            'fecha' => now()->toDateString(),
            'motivo' => 'Devolucion parcial',
            'destino_excedente' => 'saldo_favor',
            'detalles' => [
                ['compra_detalle_id' => $detalle->id, 'cantidad' => 1, 'serie_ids' => []],
            ],
        ];

        $this->postJson('/api/devoluciones-proveedor', $payload)->assertCreated();
        $this->assertDatabaseHas('compras', ['id' => $compra->id, 'estado' => 'devuelta_parcial']);

        $this->getJson("/api/devoluciones-proveedor/compras/{$compra->id}")
            ->assertOk()
            ->assertJsonPath('detalles.0.cantidad_devuelta', 1)
            ->assertJsonPath('detalles.0.cantidad_devolvible', 1);

        $this->postJson('/api/devoluciones-proveedor', $payload)->assertCreated();
        $this->assertDatabaseHas('compras', ['id' => $compra->id, 'estado' => 'devuelta']);
        $this->assertSame(0.0, (float) Inventario::where('producto_id', $detalle->producto_id)->value('stock'));
    }

    public function test_aplica_saldo_favor_en_una_nueva_compra(): void
    {
        [$user, $compraOriginal] = $this->crearCompraPagada();
        Sanctum::actingAs($user);

        $this->postJson("/api/compras/{$compraOriginal->id}/cancelar", [
            'fecha' => now()->toDateString(),
            'motivo' => 'Generar saldo para siguiente compra',
            'destino_excedente' => 'saldo_favor',
        ])->assertCreated();

        $this->getJson('/api/proveedores')
            ->assertOk()
            ->assertJsonPath('data.0.saldo_favor', '100.00');

        $producto = Producto::where('empresa_id', $user->empresa_id)->firstOrFail();
        $response = $this->postJson('/api/compras', [
            'proveedor_id' => $compraOriginal->proveedor_id,
            'folio' => 'COMPRA-CON-SALDO',
            'fecha' => now()->toDateString(),
            'forma_pago' => 'credito',
            'fecha_vencimiento' => now()->addDays(15)->toDateString(),
            'aplicar_saldo_favor' => true,
            'detalles' => [
                [
                    'producto_id' => $producto->id,
                    'cantidad' => 3,
                    'precio_compra' => 50,
                    'precio_venta' => 80,
                ],
            ],
        ])->assertCreated();

        $response->assertJsonPath('total', '150.00')
            ->assertJsonPath('saldo_favor_aplicado', '100.00')
            ->assertJsonPath('saldo', '50.00');
        $this->assertDatabaseHas('proveedor_saldo_movimientos', [
            'compra_id' => $response->json('id'),
            'tipo' => 'aplicacion',
            'monto' => 100,
            'saldo_resultante' => 0,
        ]);
    }

    private function crearCompraPagada(): array
    {
        $empresa = Empresa::create(['nombre' => 'Empresa devoluciones', 'activo' => true]);
        $sucursal = Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Matriz', 'activo' => true]);
        $user = User::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'name' => 'Usuario devoluciones',
            'email' => 'devoluciones-' . uniqid() . '@example.com',
            'password' => 'password',
            'activo' => true,
        ]);
        $user->sucursales()->attach($sucursal->id);

        $proveedor = Proveedor::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'user_id' => $user->id,
            'nombre_comercial' => 'Proveedor devoluciones',
        ]);
        $producto = Producto::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'user_id' => $user->id,
            'nombre' => 'Producto devolucion',
            'codigo' => 'DEV-PROD',
            'precio_costo' => 50,
            'precio_venta' => 80,
            'activo' => true,
            'tiene_variantes' => false,
            'tiene_series' => false,
        ]);
        $compra = Compra::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'user_id' => $user->id,
            'proveedor_id' => $proveedor->id,
            'folio' => 'DEV-TEST-' . uniqid(),
            'fecha' => now()->toDateString(),
            'forma_pago' => 'efectivo',
            'subtotal' => 100,
            'total' => 100,
            'estado' => 'confirmada',
        ]);
        $compra->forceFill(['pagado' => 100, 'saldo' => 0])->save();
        CompraDetalle::create([
            'compra_id' => $compra->id,
            'producto_id' => $producto->id,
            'cantidad' => 2,
            'precio_compra' => 50,
            'subtotal' => 100,
        ]);
        Inventario::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'producto_id' => $producto->id,
            'stock' => 2,
            'stock_minimo' => 0,
        ]);

        return [$user, $compra->load('detalles')];
    }
}
