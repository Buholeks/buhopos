<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CorteCaja;
use App\Models\Empresa;
use App\Models\Inventario;
use App\Models\InventarioReserva;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PedidoVencimientoTest extends TestCase
{
    use DatabaseTransactions;

    public function test_consultar_pedidos_marca_como_vencido_y_libera_inventario(): void
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

        $pedido = $this->postJson('/api/pedidos', [
            'tipo' => 'apartado',
            'cliente_id' => $cliente->id,
            'fecha_promesa' => now()->subDays(3)->toDateString(),
            'detalles' => [
                ['producto_id' => $producto->id, 'descripcion' => 'Renglon vencido', 'cantidad' => 1, 'precio_acordado' => 500],
            ],
        ])->assertCreated();

        $pedidoId = $pedido->json('id');
        $this->assertSame('disponible', Pedido::find($pedidoId)->estado);
        $this->assertSame(1, InventarioReserva::where('pedido_id', $pedidoId)->where('estado', 'activa')->count());

        // Al listar (lo que dispara la marcacion perezosa), el pedido debe pasar a vencido
        // y su reserva de inventario debe liberarse, sin necesidad de ningun job programado.
        $this->getJson('/api/pedidos?tipo=apartado')->assertOk();

        $this->assertSame('vencido', Pedido::find($pedidoId)->estado);
        $this->assertSame(0, InventarioReserva::where('pedido_id', $pedidoId)->where('estado', 'activa')->count());
        $this->assertSame(1, InventarioReserva::where('pedido_id', $pedidoId)->where('estado', 'liberada')->count());
    }

    public function test_pedido_con_fecha_promesa_de_hoy_no_se_marca_vencido(): void
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

        $pedido = $this->postJson('/api/pedidos', [
            'tipo' => 'apartado',
            'cliente_id' => $cliente->id,
            'fecha_promesa' => now()->toDateString(),
            'detalles' => [
                ['producto_id' => $producto->id, 'descripcion' => 'Renglon de hoy', 'cantidad' => 1, 'precio_acordado' => 500],
            ],
        ])->assertCreated();

        $pedidoId = $pedido->json('id');

        $this->getJson('/api/pedidos?tipo=apartado')->assertOk();

        $this->assertSame('disponible', Pedido::find($pedidoId)->estado);
    }

    public function test_pedido_vencido_todavia_se_puede_cancelar_para_resolver_el_anticipo(): void
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

        $corte = CorteCaja::create([
            'empresa_id' => $user->empresa_id,
            'sucursal_id' => $user->sucursal_id,
            'user_id' => $user->id,
            'estado' => 'abierto',
            'terminal' => 'POS-01',
            'fecha_apertura' => now(),
            'fondo_inicial_efectivo' => 0,
        ]);

        $pedido = $this->postJson('/api/pedidos', [
            'tipo' => 'apartado',
            'cliente_id' => $cliente->id,
            'anticipo' => 150,
            'forma_pago' => 'efectivo',
            'fecha_promesa' => now()->subDays(3)->toDateString(),
            'detalles' => [
                ['producto_id' => $producto->id, 'descripcion' => 'Renglon vencido', 'cantidad' => 1, 'precio_acordado' => 500],
            ],
        ])->assertCreated();

        $pedidoId = $pedido->json('id');

        $this->getJson('/api/pedidos?tipo=apartado')->assertOk();
        $this->assertSame('vencido', Pedido::find($pedidoId)->estado);

        $this->postJson("/api/pedidos/{$pedidoId}/cancelar", [
            'destino_saldo' => 'efectivo',
            'monto_devolucion' => 150,
        ])->assertOk();

        $this->assertSame('cancelado', Pedido::find($pedidoId)->estado);
        $this->assertDatabaseHas('movimientos_caja', [
            'corte_id' => $corte->id,
            'tipo' => 'egreso',
            'forma_pago' => 'efectivo',
            'monto' => 150,
        ]);
    }

    public function test_pedido_vencido_no_admite_abonos(): void
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

        $pedido = $this->postJson('/api/pedidos', [
            'tipo' => 'apartado',
            'cliente_id' => $cliente->id,
            'fecha_promesa' => now()->subDays(3)->toDateString(),
            'detalles' => [
                ['producto_id' => $producto->id, 'descripcion' => 'Renglon vencido', 'cantidad' => 1, 'precio_acordado' => 500],
            ],
        ])->assertCreated();

        $pedidoId = $pedido->json('id');

        $this->getJson('/api/pedidos?tipo=apartado')->assertOk();
        $this->assertSame('vencido', Pedido::find($pedidoId)->estado);

        $this->postJson("/api/pedidos/{$pedidoId}/abonos", [
            'monto' => 100,
            'forma_pago' => 'efectivo',
        ])->assertStatus(422)
            ->assertJsonFragment(['message' => 'Este pedido ya está cerrado.']);
    }

    public function test_apartado_sin_fecha_promesa_usa_dias_configurados_por_defecto(): void
    {
        [$user, $cliente, $producto] = $this->crearContexto();
        Sanctum::actingAs($user);

        $user->empresa()->update(['config_pedidos' => ['dias_vigencia_apartado' => 10]]);

        Inventario::create([
            'empresa_id' => $user->empresa_id,
            'sucursal_id' => $user->sucursal_id,
            'producto_id' => $producto->id,
            'variante_id' => null,
            'stock' => 5,
        ]);

        $pedido = $this->postJson('/api/pedidos', [
            'tipo' => 'apartado',
            'cliente_id' => $cliente->id,
            'detalles' => [
                ['producto_id' => $producto->id, 'descripcion' => 'Sin fecha manual', 'cantidad' => 1, 'precio_acordado' => 500],
            ],
        ])->assertCreated();

        $this->assertSame(
            now('America/Mexico_City')->addDays(10)->toDateString(),
            Pedido::find($pedido->json('id'))->fecha_promesa->toDateString()
        );
    }

    public function test_apartado_sin_fecha_promesa_y_sin_configuracion_se_queda_sin_fecha(): void
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

        $pedido = $this->postJson('/api/pedidos', [
            'tipo' => 'apartado',
            'cliente_id' => $cliente->id,
            'detalles' => [
                ['producto_id' => $producto->id, 'descripcion' => 'Sin fecha manual', 'cantidad' => 1, 'precio_acordado' => 500],
            ],
        ])->assertCreated();

        $this->assertNull(Pedido::find($pedido->json('id'))->fecha_promesa);
    }

    public function test_pedido_vence_por_renglon_segun_dias_configurados_desde_que_llego(): void
    {
        [$user, $cliente, $proveedor, $producto] = $this->crearContextoConProveedor();
        Sanctum::actingAs($user);

        $user->empresa()->update(['config_pedidos' => ['dias_vigencia_pedido' => 5]]);

        $detalle = $this->crearPedidoConDetalle($user, $cliente, $producto, 'PED-VEN-A');

        $this->postJson('/api/compras', [
            'proveedor_id' => $proveedor->id,
            'folio' => 'COMPRA-VEN-A',
            'fecha' => now()->toDateString(),
            'forma_pago' => 'efectivo',
            'detalles' => [[
                'producto_id' => $producto->id,
                'cantidad' => 1,
                'precio_compra' => 20,
                'precio_venta' => 50,
            ]],
        ])->assertCreated();

        $this->assertSame('disponible', $detalle->fresh()->estado);
        $this->assertNotNull($detalle->fresh()->disponible_desde);

        // Retrocede la fecha de llegada 6 dias (mas de los 5 configurados) para simular
        // que nadie recogio el articulo a tiempo.
        $detalle->fresh()->update(['disponible_desde' => now()->subDays(6)]);

        $this->getJson('/api/pedidos?tipo=pedido')->assertOk();

        $this->assertSame('vencido', $detalle->pedido->fresh()->estado);
        $this->assertSame(
            0,
            InventarioReserva::where('pedido_detalle_id', $detalle->id)->where('estado', 'activa')->count()
        );
        $this->assertSame(
            1,
            InventarioReserva::where('pedido_detalle_id', $detalle->id)->where('estado', 'liberada')->count()
        );
    }

    public function test_pedido_sin_configuracion_no_vence_aunque_lleve_mucho_disponible(): void
    {
        [$user, $cliente, $proveedor, $producto] = $this->crearContextoConProveedor();
        Sanctum::actingAs($user);

        $detalle = $this->crearPedidoConDetalle($user, $cliente, $producto, 'PED-VEN-B');

        $this->postJson('/api/compras', [
            'proveedor_id' => $proveedor->id,
            'folio' => 'COMPRA-VEN-B',
            'fecha' => now()->toDateString(),
            'forma_pago' => 'efectivo',
            'detalles' => [[
                'producto_id' => $producto->id,
                'cantidad' => 1,
                'precio_compra' => 20,
                'precio_venta' => 50,
            ]],
        ])->assertCreated();

        $detalle->fresh()->update(['disponible_desde' => now()->subDays(90)]);

        // Sin dias_vigencia_pedido configurado, el vencimiento por renglon esta apagado.
        $this->getJson('/api/pedidos?tipo=pedido')->assertOk();

        $this->assertSame('disponible', $detalle->pedido->fresh()->estado);
        $this->assertSame(
            1,
            InventarioReserva::where('pedido_detalle_id', $detalle->id)->where('estado', 'activa')->count()
        );
    }

    public function test_pedido_con_dos_renglones_solo_libera_el_que_ya_llego_y_se_atraso(): void
    {
        [$user, $cliente, $proveedor, $producto] = $this->crearContextoConProveedor();
        Sanctum::actingAs($user);

        $user->empresa()->update(['config_pedidos' => ['dias_vigencia_pedido' => 5]]);

        $pedido = Pedido::create([
            'empresa_id' => $user->empresa_id,
            'sucursal_id' => $user->sucursal_id,
            'user_id' => $user->id,
            'cliente_id' => $cliente->id,
            'folio' => 'PED-VEN-DOBLE-' . uniqid(),
            'tipo' => 'pedido',
            'estado' => 'pendiente',
            'estado_pago' => 'sin_anticipo',
            'subtotal' => 100,
            'anticipo' => 0,
            'saldo_pendiente' => 100,
        ]);

        $renglonLlegoYSeAtraso = PedidoDetalle::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $producto->id,
            'descripcion' => 'Ya llego, nadie lo recogio',
            'cantidad' => 1,
            'precio_acordado' => 50,
            'subtotal' => 50,
            'estado' => 'disponible',
            'disponible_desde' => now()->subDays(10),
        ]);

        $renglonSigueEnCamino = PedidoDetalle::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $producto->id,
            'descripcion' => 'Todavia no llega del proveedor',
            'cantidad' => 1,
            'precio_acordado' => 50,
            'subtotal' => 50,
            'estado' => 'pendiente',
        ]);

        InventarioReserva::create([
            'empresa_id' => $user->empresa_id,
            'sucursal_id' => $user->sucursal_id,
            'pedido_id' => $pedido->id,
            'pedido_detalle_id' => $renglonLlegoYSeAtraso->id,
            'producto_id' => $producto->id,
            'variante_id' => null,
            'cantidad' => 1,
            'estado' => 'activa',
        ]);

        $this->getJson('/api/pedidos?tipo=pedido')->assertOk();

        $this->assertSame('vencido', $pedido->fresh()->estado);
        $this->assertSame('disponible', $renglonLlegoYSeAtraso->fresh()->estado);
        $this->assertSame(
            0,
            InventarioReserva::where('pedido_detalle_id', $renglonLlegoYSeAtraso->id)->where('estado', 'activa')->count()
        );
        // El renglon que todavia no llega no se toca: sigue pendiente, sin reserva alguna.
        $this->assertSame('pendiente', $renglonSigueEnCamino->fresh()->estado);
    }

    public function test_config_pedidos_se_guarda_y_se_lee(): void
    {
        [$user] = $this->crearContexto();
        Sanctum::actingAs($user);

        $this->getJson('/api/config-pedidos')->assertOk()->assertJson([]);

        $this->putJson('/api/config-pedidos', [
            'dias_vigencia_apartado' => 15,
            'dias_vigencia_pedido' => 7,
        ])->assertOk();

        // Sanctum::actingAs reutiliza el mismo objeto User en memoria entre peticiones
        // simuladas; hay que refrescarlo para no leer la relacion "empresa" que ya se
        // habia cacheado (sin config_pedidos) en el primer getJson de arriba.
        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/config-pedidos')
            ->assertOk()
            ->assertJson(['dias_vigencia_apartado' => 15, 'dias_vigencia_pedido' => 7]);
    }

    private function crearContextoConProveedor(): array
    {
        [$user, $cliente, $producto] = $this->crearContexto();

        $proveedor = Proveedor::create([
            'empresa_id' => $user->empresa_id,
            'sucursal_id' => $user->sucursal_id,
            'user_id' => $user->id,
            'nombre_comercial' => 'Proveedor vencimientos',
        ]);

        return [$user, $cliente, $proveedor, $producto];
    }

    private function crearPedidoConDetalle(User $user, Cliente $cliente, Producto $producto, string $folio): PedidoDetalle
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
            'subtotal' => 50,
            'anticipo' => 0,
            'saldo_pendiente' => 50,
        ]);

        return PedidoDetalle::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $producto->id,
            'descripcion' => $producto->nombre,
            'cantidad' => 1,
            'precio_acordado' => 50,
            'subtotal' => 50,
            'estado' => 'pendiente',
        ]);
    }

    private function crearContexto(): array
    {
        $empresa = Empresa::create(['nombre' => 'Empresa vencimientos', 'activo' => true]);
        $sucursal = Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Matriz', 'activo' => true]);
        $user = User::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'name' => 'Usuario vencimientos',
            'email' => 'vencimientos-' . uniqid() . '@example.com',
            'password' => 'password',
            'activo' => true,
        ]);
        $user->sucursales()->attach($sucursal->id);

        $cliente = Cliente::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'user_id' => $user->id,
            'nombre' => 'Cliente vencimientos',
            'telefono' => '555' . random_int(1000000, 9999999),
            'activo' => true,
        ]);

        $producto = Producto::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'user_id' => $user->id,
            'nombre' => 'Producto con vencimiento',
            'codigo' => 'VEN-PROD-' . uniqid(),
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
