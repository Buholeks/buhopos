<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CorteCaja;
use App\Models\Empresa;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PedidoAsignacionCompraVentaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_producto_normal_vincula_automaticamente_el_pedido_mas_antiguo(): void
    {
        [$user, $cliente, $proveedor, $producto] = $this->crearContexto();
        Sanctum::actingAs($user);

        $primero = $this->crearPedido($user, $cliente, $producto, 'PED-NORMAL-A', 40);
        $segundo = $this->crearPedido($user, $cliente, $producto, 'PED-NORMAL-B', 45);

        $this->postJson('/api/compras', [
            'proveedor_id' => $proveedor->id,
            'folio' => 'COMPRA-NORMAL',
            'fecha' => now()->toDateString(),
            'forma_pago' => 'efectivo',
            'detalles' => [[
                'producto_id' => $producto->id,
                'cantidad' => 1,
                'precio_compra' => 20,
                'precio_venta' => 40,
            ]],
        ])->assertCreated();

        $this->assertSame('disponible', $primero->fresh()->estado);
        $this->assertNotNull($primero->fresh()->compra_detalle_id);
        $this->assertSame('pendiente', $segundo->fresh()->estado);
        $this->assertNull($segundo->fresh()->compra_detalle_id);
    }

    public function test_bandeja_de_compra_masiva_muestra_pedidos_genericos_y_no_genericos_pendientes(): void
    {
        [$user, $cliente, , $producto] = $this->crearContexto();
        Sanctum::actingAs($user);

        $generico = $producto->replicate();
        $generico->codigo = 'PED-GENERICO-' . uniqid();
        $generico->nombre = 'Pedido genérico';
        $generico->pedido_generico = true;
        $generico->save();

        $detalleNormal = $this->crearPedido($user, $cliente, $producto, 'PED-NORMAL', 40);
        $detalleGenerico = $this->crearPedido($user, $cliente, $generico, 'PED-GENERICO', 75);

        $this->getJson('/api/pedidos/pendientes-compra')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['id' => $detalleNormal->id, 'producto_id' => $producto->id])
            ->assertJsonFragment(['id' => $detalleGenerico->id, 'producto_id' => $generico->id]);
    }

    public function test_compra_y_venta_conservan_renglones_de_pedido_seleccionados(): void
    {
        [$user, $cliente, $proveedor, $producto] = $this->crearContexto();
        $producto->update(['pedido_generico' => true]);
        Sanctum::actingAs($user);

        $pendiente = $this->crearPedido($user, $cliente, $producto, 'PED-A', 50);
        $seleccionadoA = $this->crearPedido($user, $cliente, $producto, 'PED-B', 20);
        $seleccionadoB = $this->crearPedido($user, $cliente, $producto, 'PED-C', 30);

        $compra = $this->postJson('/api/compras', [
            'proveedor_id' => $proveedor->id,
            'folio' => 'COMPRA-PEDIDOS',
            'fecha' => now()->toDateString(),
            'forma_pago' => 'efectivo',
            'detalles' => [
                [
                    'producto_id' => $producto->id,
                    'cantidad' => 1,
                    'precio_compra' => 10,
                    'precio_venta' => 20,
                    'pedido_detalle_ids' => [$seleccionadoA->id],
                ],
                [
                    'producto_id' => $producto->id,
                    'cantidad' => 1,
                    'precio_compra' => 15,
                    'precio_venta' => 30,
                    'pedido_detalle_ids' => [$seleccionadoB->id],
                ],
            ],
        ])->assertCreated();

        $this->assertNull($pendiente->fresh()->compra_detalle_id);
        $this->assertSame('pendiente', $pendiente->fresh()->estado);
        $this->assertSame('disponible', $seleccionadoA->fresh()->estado);
        $this->assertSame('disponible', $seleccionadoB->fresh()->estado);
        $this->assertNotSame(
            $seleccionadoA->fresh()->compra_detalle_id,
            $seleccionadoB->fresh()->compra_detalle_id
        );

        $this->getJson('/api/ventas/buscar-variantes?q=' . urlencode($producto->codigo))
            ->assertOk()
            ->assertJsonPath('0.sin_stock', true);

        $this->getJson('/api/ventas/buscar-variantes?' . http_build_query([
            'q' => $producto->codigo,
            'pedido_detalle_id' => $seleccionadoA->id,
        ]))
            ->assertOk()
            ->assertJsonPath('0.sin_stock', false)
            ->assertJsonPath('0.stock', 1);

        CorteCaja::create([
            'empresa_id' => $user->empresa_id,
            'sucursal_id' => $user->sucursal_id,
            'user_id' => $user->id,
            'estado' => 'abierto',
            'terminal' => 'POS-01',
            'fecha_apertura' => now(),
            'fondo_inicial_efectivo' => 0,
        ]);

        $venta = $this->postJson('/api/ventas', [
            'fecha' => now()->toDateString(),
            'cliente_id' => $cliente->id,
            'vendedor_id' => $user->id,
            'pagos' => [
                ['forma_pago' => 'efectivo', 'monto' => 50, 'monto_recibido' => 50],
            ],
            'detalles' => [
                [
                    'producto_id' => $producto->id,
                    'cantidad' => 1,
                    'precio_venta' => 999,
                    'pedido_detalle_id' => $seleccionadoA->id,
                ],
                [
                    'producto_id' => $producto->id,
                    'cantidad' => 1,
                    'precio_venta' => 999,
                    'pedido_detalle_id' => $seleccionadoB->id,
                ],
            ],
        ])->assertCreated();

        $ventaId = $venta->json('id');
        $this->assertDatabaseHas('venta_detalles', [
            'venta_id' => $ventaId,
            'pedido_detalle_id' => $seleccionadoA->id,
            'precio_venta' => 20,
            'precio_costo' => 10,
        ]);
        $this->assertDatabaseHas('venta_detalles', [
            'venta_id' => $ventaId,
            'pedido_detalle_id' => $seleccionadoB->id,
            'precio_venta' => 30,
            'precio_costo' => 15,
        ]);
        $this->assertCount(2, $compra->json('detalles'));
        $this->assertSame('pendiente', $pendiente->fresh()->estado);
        $this->assertSame('entregado', $seleccionadoA->fresh()->estado);
        $this->assertSame('entregado', $seleccionadoB->fresh()->estado);
    }

    public function test_producto_generico_con_pedidos_pendientes_exige_seleccion(): void
    {
        [$user, $cliente, $proveedor, $producto] = $this->crearContexto();
        $producto->update(['pedido_generico' => true]);
        Sanctum::actingAs($user);

        $pendiente = $this->crearPedido($user, $cliente, $producto, 'PED-SIN-SELECCION', 50);

        $this->postJson('/api/compras', [
            'proveedor_id' => $proveedor->id,
            'folio' => 'COMPRA-SIN-SELECCION',
            'fecha' => now()->toDateString(),
            'forma_pago' => 'efectivo',
            'detalles' => [[
                'producto_id' => $producto->id,
                'cantidad' => 1,
                'precio_compra' => 20,
                'precio_venta' => 50,
            ]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('detalles');

        $this->assertSame('pendiente', $pendiente->fresh()->estado);
        $this->assertNull($pendiente->fresh()->compra_detalle_id);
        $this->assertDatabaseMissing('compras', ['folio' => 'COMPRA-SIN-SELECCION']);
    }

    public function test_buscador_de_compras_prioriza_producto_generico_duplicado(): void
    {
        [$user, , , $producto] = $this->crearContexto();
        $producto->update(['nombre' => 'PEDIDOS SHEIN', 'pedido_generico' => false]);

        $generico = $producto->replicate();
        $generico->codigo = 'PED-SHEIN-GENERICO-' . uniqid();
        $generico->pedido_generico = true;
        $generico->save();

        Sanctum::actingAs($user);

        $this->getJson('/api/compras/buscar-variantes?q=PEDIDOS%20SHEIN')
            ->assertOk()
            ->assertJsonPath('0.producto_id', $generico->id)
            ->assertJsonPath('0.pedido_generico', true);
    }

    private function crearContexto(): array
    {
        $empresa = Empresa::create(['nombre' => 'Empresa pedidos', 'activo' => true]);
        $sucursal = Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Matriz', 'activo' => true]);
        $user = User::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'name' => 'Usuario pedidos',
            'email' => 'pedidos-' . uniqid() . '@example.com',
            'password' => 'password',
            'activo' => true,
        ]);
        $user->sucursales()->attach($sucursal->id);

        $cliente = Cliente::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'user_id' => $user->id,
            'nombre' => 'Cliente pedidos',
            'telefono' => '555' . random_int(1000000, 9999999),
            'activo' => true,
        ]);
        $proveedor = Proveedor::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'user_id' => $user->id,
            'nombre_comercial' => 'Proveedor pedidos',
        ]);
        $producto = Producto::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'user_id' => $user->id,
            'nombre' => 'Producto por pedido',
            'codigo' => 'PED-PROD-' . uniqid(),
            'precio_costo' => 0,
            'precio_venta' => 0,
            'activo' => true,
            'tiene_variantes' => false,
            'tiene_series' => false,
            'pedido_generico' => false,
        ]);

        return [$user, $cliente, $proveedor, $producto];
    }

    private function crearPedido(User $user, Cliente $cliente, Producto $producto, string $folio, float $precio): PedidoDetalle
    {
        $pedido = Pedido::create([
            'empresa_id' => $user->empresa_id,
            'sucursal_id' => $user->sucursal_id,
            'user_id' => $user->id,
            'cliente_id' => $cliente->id,
            'folio' => $folio . '-' . uniqid(),
            'tipo' => 'pedido',
            'estado' => 'pendiente',
            'estado_pago' => 'sin_anticipo',
            'subtotal' => $precio,
            'anticipo' => 0,
            'saldo_pendiente' => $precio,
        ]);

        return PedidoDetalle::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $producto->id,
            'descripcion' => $producto->nombre,
            'cantidad' => 1,
            'precio_acordado' => $precio,
            'subtotal' => $precio,
            'estado' => 'pendiente',
        ]);
    }
}
