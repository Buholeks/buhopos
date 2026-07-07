<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ClienteSaldoMovimiento;
use App\Models\CorteCaja;
use App\Models\Empresa;
use App\Models\Inventario;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PedidoAnticipoSaldoFavorTest extends TestCase
{
    use DatabaseTransactions;

    public function test_cancelar_pedido_parcial_y_su_venta_no_duplica_el_saldo_a_favor(): void
    {
        [$user, $cliente, $producto] = $this->crearContexto();
        Sanctum::actingAs($user);

        Inventario::create([
            'empresa_id' => $user->empresa_id,
            'sucursal_id' => $user->sucursal_id,
            'producto_id' => $producto->id,
            'variante_id' => null,
            'stock' => 5,
        ]);

        CorteCaja::create([
            'empresa_id' => $user->empresa_id,
            'sucursal_id' => $user->sucursal_id,
            'user_id' => $user->id,
            'estado' => 'abierto',
            'terminal' => 'POS-01',
            'fecha_apertura' => now(),
            'fondo_inicial_efectivo' => 0,
        ]);

        // 1) Pedido con anticipo de $300, dos renglones: uno de $681 (se recogerá) y otro de $200 (se cancelará).
        $pedido = $this->postJson('/api/pedidos', [
            'tipo' => 'apartado',
            'cliente_id' => $cliente->id,
            'anticipo' => 300,
            'forma_pago' => 'efectivo',
            'detalles' => [
                ['producto_id' => $producto->id, 'descripcion' => 'Renglon A', 'cantidad' => 1, 'precio_acordado' => 681],
                ['producto_id' => $producto->id, 'descripcion' => 'Renglon B', 'cantidad' => 1, 'precio_acordado' => 200],
            ],
        ])->assertCreated();

        $pedidoId = $pedido->json('id');
        $detalleA = $pedido->json('detalles.0.id');

        $this->assertSame(300.0, (float) $this->saldoFavor($cliente->id));

        // 2) Se recoge el renglón A: venta de $681, aplicando los $300 de anticipo y $381 en efectivo.
        $venta = $this->postJson('/api/ventas', [
            'fecha' => now()->toDateString(),
            'cliente_id' => $cliente->id,
            'vendedor_id' => $user->id,
            'saldo_aplicado' => 300,
            'pagos' => [
                ['forma_pago' => 'efectivo', 'monto' => 381, 'monto_recibido' => 381],
            ],
            'detalles' => [
                ['producto_id' => $producto->id, 'cantidad' => 1, 'precio_venta' => 681, 'pedido_detalle_id' => $detalleA],
            ],
        ])->assertCreated();

        $folioVenta = $venta->json('folio');

        $this->assertSame(0.0, (float) $this->saldoFavor($cliente->id));

        // 3) Se cancela el renglón B pendiente (cancela el resto del pedido).
        $this->postJson("/api/pedidos/{$pedidoId}/cancelar")->assertOk();

        // El anticipo ya se gastó por completo en la venta del paso 2: cancelar el resto
        // del pedido no debe volver a acreditar los $300 (antes duplicaba el saldo).
        $this->assertSame(0.0, (float) $this->saldoFavor($cliente->id));

        // El renglón A ya se vendió, así que el pedido no debe quedar como "cancelado" por
        // completo (perdería el rastro de que sí se vendió parte); debe quedar "parcial".
        $this->assertSame('parcial', Pedido::find($pedidoId)->estado);

        // 4) Se cancela también la venta ya generada: ahora sí se libera el saldo aplicado.
        $this->postJson('/api/cancelaciones-devoluciones/cancelar', [
            'folio' => $folioVenta,
            'motivo' => 'Cliente ya no quiere el producto',
        ])->assertOk();

        // El saldo a favor final debe volver a coincidir con el anticipo original ($300),
        // no con $981 (300 + 681) ni con $600 (300 devuelto dos veces).
        $this->assertSame(300.0, (float) $this->saldoFavor($cliente->id));
    }

    public function test_venta_puede_quedar_totalmente_cubierta_con_saldo_a_favor(): void
    {
        [$user, $cliente, $producto] = $this->crearContexto();
        Sanctum::actingAs($user);

        Inventario::create([
            'empresa_id' => $user->empresa_id,
            'sucursal_id' => $user->sucursal_id,
            'producto_id' => $producto->id,
            'variante_id' => null,
            'stock' => 5,
        ]);

        CorteCaja::create([
            'empresa_id' => $user->empresa_id,
            'sucursal_id' => $user->sucursal_id,
            'user_id' => $user->id,
            'estado' => 'abierto',
            'terminal' => 'POS-01',
            'fecha_apertura' => now(),
            'fondo_inicial_efectivo' => 0,
        ]);

        ClienteSaldoMovimiento::create([
            'empresa_id' => $user->empresa_id,
            'sucursal_id' => $user->sucursal_id,
            'cliente_id' => $cliente->id,
            'user_id' => $user->id,
            'tipo' => 'ajuste',
            'forma_pago' => 'saldo_favor',
            'monto' => 100,
            'saldo_resultante' => 100,
            'concepto' => 'Saldo inicial de prueba',
        ]);

        $venta = $this->postJson('/api/ventas', [
            'fecha' => now()->toDateString(),
            'cliente_id' => $cliente->id,
            'vendedor_id' => $user->id,
            'saldo_aplicado' => 100,
            'pagos' => [
                ['forma_pago' => 'efectivo', 'monto' => 0, 'monto_recibido' => 0],
            ],
            'detalles' => [
                ['producto_id' => $producto->id, 'cantidad' => 1, 'precio_venta' => 100],
            ],
        ])->assertCreated();

        $this->assertSame(0.0, (float) $this->saldoFavor($cliente->id));
        $this->assertDatabaseHas('venta_pagos', [
            'venta_id' => $venta->json('id'),
            'forma_pago' => 'saldo_favor',
            'monto' => 100,
        ]);
        $this->assertDatabaseMissing('venta_pagos', [
            'venta_id' => $venta->json('id'),
            'forma_pago' => 'efectivo',
            'monto' => 0,
        ]);
    }

    private function saldoFavor(int $clienteId): float
    {
        return (float) $this->getJson("/api/clientes/{$clienteId}/pedidos-resumen")
            ->assertOk()
            ->json('saldo_favor');
    }

    private function crearContexto(): array
    {
        $empresa = Empresa::create(['nombre' => 'Empresa anticipos', 'activo' => true]);
        $sucursal = Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Matriz', 'activo' => true]);
        $user = User::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'name' => 'Usuario anticipos',
            'email' => 'anticipos-' . uniqid() . '@example.com',
            'password' => 'password',
            'activo' => true,
        ]);
        $user->sucursales()->attach($sucursal->id);

        $cliente = Cliente::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'user_id' => $user->id,
            'nombre' => 'Cliente anticipos',
            'telefono' => '555' . random_int(1000000, 9999999),
            'activo' => true,
        ]);

        $producto = Producto::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'user_id' => $user->id,
            'nombre' => 'Producto con anticipo',
            'codigo' => 'ANT-PROD-' . uniqid(),
            'precio_costo' => 0,
            'precio_venta' => 0,
            'activo' => true,
            'tiene_variantes' => false,
            'tiene_series' => false,
            'pedido_generico' => false,
        ]);

        return [$user, $cliente, $producto];
    }
}
