<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ClienteSaldoMovimiento;
use App\Models\CorteCaja;
use App\Models\CuentaBancaria;
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

    public function test_saldo_pendiente_se_recalcula_al_entregar_solo_parte_del_pedido(): void
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

        // Pedido con anticipo de $100, dos renglones: A ($300, se recogerá pagando aparte)
        // y B ($200, sigue pendiente). Total: $500. saldo_pendiente inicial = 500 - 100 = 400.
        $pedido = $this->postJson('/api/pedidos', [
            'tipo' => 'apartado',
            'cliente_id' => $cliente->id,
            'anticipo' => 100,
            'forma_pago' => 'efectivo',
            'detalles' => [
                ['producto_id' => $producto->id, 'descripcion' => 'Renglon A', 'cantidad' => 1, 'precio_acordado' => 300],
                ['producto_id' => $producto->id, 'descripcion' => 'Renglon B', 'cantidad' => 1, 'precio_acordado' => 200],
            ],
        ])->assertCreated();

        $pedidoId = $pedido->json('id');
        $detalleA = $pedido->json('detalles.0.id');

        $this->assertSame(400.0, (float) Pedido::find($pedidoId)->saldo_pendiente);

        // Se recoge el renglón A pagando su precio completo en efectivo, sin tocar el
        // anticipo (no se aplica saldo_aplicado). El anticipo de $100 sigue intacto.
        $this->postJson('/api/ventas', [
            'fecha' => now()->toDateString(),
            'cliente_id' => $cliente->id,
            'vendedor_id' => $user->id,
            'pagos' => [
                ['forma_pago' => 'efectivo', 'monto' => 300, 'monto_recibido' => 300],
            ],
            'detalles' => [
                ['producto_id' => $producto->id, 'cantidad' => 1, 'precio_venta' => 300, 'pedido_detalle_id' => $detalleA],
            ],
        ])->assertCreated();

        $pedido = Pedido::find($pedidoId);
        $this->assertSame('parcial', $pedido->estado);

        // El renglón A ya se pagó por separado: lo único que debería seguir "debiendo" el
        // pedido es el renglón B ($200) menos el anticipo que sigue disponible ($100) = $100.
        // Antes del fix, esto seguía marcando $400 (el subtotal completo original menos el
        // anticipo), como si el renglón A nunca se hubiera resuelto.
        $this->assertSame(100.0, (float) $pedido->saldo_pendiente);
        $this->assertSame('con_anticipo', $pedido->estado_pago);

        // El tope de abono ahora debe respetar el saldo real ($100), no el viejo ($400).
        $this->postJson("/api/pedidos/{$pedidoId}/abonos", [
            'monto' => 150,
            'forma_pago' => 'efectivo',
        ])->assertStatus(422)
            ->assertJsonFragment(['message' => 'El abono supera el saldo pendiente del pedido.']);

        // Abonar exactamente el saldo real sí debe liquidar el pedido.
        $this->postJson("/api/pedidos/{$pedidoId}/abonos", [
            'monto' => 100,
            'forma_pago' => 'efectivo',
        ])->assertOk();

        $pedido = Pedido::find($pedidoId);
        $this->assertSame(0.0, (float) $pedido->saldo_pendiente);
        $this->assertSame('pagado', $pedido->estado_pago);
    }

    public function test_saldo_cancelacion_refleja_anticipo_ya_consumido_en_otra_venta(): void
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

        // Pedido con anticipo de $250 y dos renglones: A ($300, se recogerá aplicando
        // el anticipo como saldo a favor) y B ($200, sigue pendiente).
        $pedido = $this->postJson('/api/pedidos', [
            'tipo' => 'apartado',
            'cliente_id' => $cliente->id,
            'anticipo' => 250,
            'forma_pago' => 'efectivo',
            'detalles' => [
                ['producto_id' => $producto->id, 'descripcion' => 'Renglon A', 'cantidad' => 1, 'precio_acordado' => 300],
                ['producto_id' => $producto->id, 'descripcion' => 'Renglon B', 'cantidad' => 1, 'precio_acordado' => 200],
            ],
        ])->assertCreated();

        $pedidoId = $pedido->json('id');
        $detalleA = $pedido->json('detalles.0.id');

        // Antes de gastar nada, lo disponible para devolver es el anticipo completo.
        $this->getJson("/api/pedidos/{$pedidoId}/saldo-cancelacion")
            ->assertOk()
            ->assertJson(['anticipo' => 250, 'saldo_disponible_cliente' => 250, 'maximo_devolucion' => 250]);

        // Se recoge el renglón A aplicando los $250 de anticipo como saldo a favor
        // (el resto, $50, se cobra en efectivo). El anticipo del pedido no se decrementa,
        // pero el saldo real del cliente ya quedó en $0.
        $this->postJson('/api/ventas', [
            'fecha' => now()->toDateString(),
            'cliente_id' => $cliente->id,
            'vendedor_id' => $user->id,
            'saldo_aplicado' => 250,
            'pagos' => [
                ['forma_pago' => 'efectivo', 'monto' => 50, 'monto_recibido' => 50],
            ],
            'detalles' => [
                ['producto_id' => $producto->id, 'cantidad' => 1, 'precio_venta' => 300, 'pedido_detalle_id' => $detalleA],
            ],
        ])->assertCreated();

        // Aunque pedido.anticipo sigue mostrando $250, ya no queda nada disponible para
        // devolver al cancelar: esto es justo lo que el modal de cancelación debe mostrar
        // en vez de sugerir devolver $250 y que el backend lo rechace.
        $this->getJson("/api/pedidos/{$pedidoId}/saldo-cancelacion")
            ->assertOk()
            ->assertJson(['anticipo' => 250, 'saldo_disponible_cliente' => 0, 'maximo_devolucion' => 0]);
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

    public function test_cancelar_pedido_manteniendo_saldo_no_duplica_anticipo(): void
    {
        [$user, $cliente, $producto] = $this->crearContexto();
        Sanctum::actingAs($user);

        CorteCaja::create([
            'empresa_id' => $user->empresa_id,
            'sucursal_id' => $user->sucursal_id,
            'user_id' => $user->id,
            'estado' => 'abierto',
            'terminal' => 'POS-01',
            'fecha_apertura' => now(),
            'fondo_inicial_efectivo' => 0,
        ]);

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
            'anticipo' => 250,
            'forma_pago' => 'efectivo',
            'detalles' => [
                ['producto_id' => $producto->id, 'descripcion' => 'Renglon cancelado', 'cantidad' => 1, 'precio_acordado' => 500],
            ],
        ])->assertCreated();

        $this->postJson("/api/pedidos/{$pedido->json('id')}/cancelar", [
            'destino_saldo' => 'mantener_saldo',
        ])->assertOk();

        $this->assertSame(250.0, (float) $this->saldoFavor($cliente->id));
        $this->assertSame(1, ClienteSaldoMovimiento::where('pedido_id', $pedido->json('id'))->where('tipo', 'abono')->count());
        $this->assertSame(0, ClienteSaldoMovimiento::where('pedido_id', $pedido->json('id'))->where('tipo', 'aplicacion')->count());
    }

    public function test_cancelar_pedido_devolviendo_efectivo_consume_saldo_y_registra_egreso(): void
    {
        [$user, $cliente, $producto] = $this->crearContexto();
        Sanctum::actingAs($user);

        $corte = CorteCaja::create([
            'empresa_id' => $user->empresa_id,
            'sucursal_id' => $user->sucursal_id,
            'user_id' => $user->id,
            'estado' => 'abierto',
            'terminal' => 'POS-01',
            'fecha_apertura' => now(),
            'fondo_inicial_efectivo' => 0,
        ]);

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
            'anticipo' => 250,
            'forma_pago' => 'efectivo',
            'detalles' => [
                ['producto_id' => $producto->id, 'descripcion' => 'Renglon cancelado', 'cantidad' => 1, 'precio_acordado' => 500],
            ],
        ])->assertCreated();

        $this->postJson("/api/pedidos/{$pedido->json('id')}/cancelar", [
            'destino_saldo' => 'efectivo',
            'monto_devolucion' => 250,
        ])->assertOk();

        $this->assertSame(0.0, (float) $this->saldoFavor($cliente->id));
        $this->assertDatabaseHas('cliente_saldo_movimientos', [
            'pedido_id' => $pedido->json('id'),
            'tipo' => 'aplicacion',
            'forma_pago' => 'efectivo',
            'monto' => 250,
        ]);
        $this->assertDatabaseHas('movimientos_caja', [
            'corte_id' => $corte->id,
            'tipo' => 'egreso',
            'forma_pago' => 'efectivo',
            'monto' => 250,
        ]);
        $this->assertSame(0.0, (float) $corte->fresh()->movs_efectivo);
    }

    public function test_cancelar_pedido_devolviendo_transferencia_requiere_cuenta_y_registra_egreso(): void
    {
        [$user, $cliente, $producto] = $this->crearContexto();
        Sanctum::actingAs($user);

        $corte = CorteCaja::create([
            'empresa_id' => $user->empresa_id,
            'sucursal_id' => $user->sucursal_id,
            'user_id' => $user->id,
            'estado' => 'abierto',
            'terminal' => 'POS-01',
            'fecha_apertura' => now(),
            'fondo_inicial_efectivo' => 0,
        ]);

        $cuenta = CuentaBancaria::create([
            'empresa_id' => $user->empresa_id,
            'sucursal_id' => $user->sucursal_id,
            'user_id' => $user->id,
            'nombre' => 'Cuenta devoluciones',
            'banco' => 'Banco prueba',
            'activo' => true,
        ]);

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
            'anticipo' => 250,
            'forma_pago' => 'transferencia',
            'cuenta_bancaria_id' => $cuenta->id,
            'detalles' => [
                ['producto_id' => $producto->id, 'descripcion' => 'Renglon cancelado', 'cantidad' => 1, 'precio_acordado' => 500],
            ],
        ])->assertCreated();

        $this->postJson("/api/pedidos/{$pedido->json('id')}/cancelar", [
            'destino_saldo' => 'transferencia',
            'monto_devolucion' => 250,
            'cuenta_bancaria_id' => $cuenta->id,
        ])->assertOk();

        $this->assertSame(0.0, (float) $this->saldoFavor($cliente->id));
        $this->assertDatabaseHas('movimientos_caja', [
            'corte_id' => $corte->id,
            'tipo' => 'egreso',
            'forma_pago' => 'transferencia',
            'cuenta_bancaria_id' => $cuenta->id,
            'monto' => 250,
        ]);
        $this->assertSame(0.0, (float) $corte->fresh()->movs_transferencia);
    }

    private function saldoFavor(int $clienteId): float
    {
        return (float) $this->getJson("/api/clientes/{$clienteId}/pedidos-resumen")
            ->assertOk()
            ->json('saldo_favor');
    }

    public function test_eliminar_abono_registra_egreso_en_el_corte_actual_sin_borrar_el_ingreso_original(): void
    {
        [$user, $cliente, $producto] = $this->crearContexto();
        Sanctum::actingAs($user);

        $corte = CorteCaja::create([
            'empresa_id' => $user->empresa_id,
            'sucursal_id' => $user->sucursal_id,
            'user_id' => $user->id,
            'estado' => 'abierto',
            'terminal' => 'POS-01',
            'fecha_apertura' => now(),
            'fondo_inicial_efectivo' => 0,
        ]);

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
            'anticipo' => 150,
            'forma_pago' => 'efectivo',
            'detalles' => [
                ['producto_id' => $producto->id, 'descripcion' => 'Renglon', 'cantidad' => 1, 'precio_acordado' => 500],
            ],
        ])->assertCreated();

        $pedidoId = $pedido->json('id');
        $abonoId = ClienteSaldoMovimiento::where('pedido_id', $pedidoId)->where('tipo', 'abono')->firstOrFail()->id;

        $this->deleteJson("/api/pedidos/{$pedidoId}/abonos/{$abonoId}")->assertOk();

        // El ingreso original sigue existiendo: no se borra, queda como historial.
        $this->assertDatabaseHas('movimientos_caja', [
            'corte_id' => $corte->id,
            'tipo' => 'ingreso',
            'forma_pago' => 'efectivo',
            'monto' => 150,
        ]);

        // Se registró un egreso por el mismo monto, en el mismo corte (sigue abierto).
        $this->assertDatabaseHas('movimientos_caja', [
            'corte_id' => $corte->id,
            'tipo' => 'egreso',
            'forma_pago' => 'efectivo',
            'monto' => 150,
        ]);

        // Neto en caja: 0 (entró y salió el mismo abono).
        $this->assertSame(0.0, (float) $corte->fresh()->movs_efectivo);
        $this->assertSame(0.0, (float) Pedido::find($pedidoId)->anticipo);
    }

    public function test_eliminar_abono_registra_egreso_en_el_corte_de_hoy_cuando_el_original_ya_cerro(): void
    {
        [$user, $cliente, $producto] = $this->crearContexto();
        Sanctum::actingAs($user);

        $corteOriginal = CorteCaja::create([
            'empresa_id' => $user->empresa_id,
            'sucursal_id' => $user->sucursal_id,
            'user_id' => $user->id,
            'estado' => 'abierto',
            'terminal' => 'POS-01',
            'fecha_apertura' => now()->subDay(),
            'fondo_inicial_efectivo' => 0,
        ]);

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
            'anticipo' => 150,
            'forma_pago' => 'efectivo',
            'detalles' => [
                ['producto_id' => $producto->id, 'descripcion' => 'Renglon', 'cantidad' => 1, 'precio_acordado' => 500],
            ],
        ])->assertCreated();

        $pedidoId = $pedido->json('id');
        $abonoId = ClienteSaldoMovimiento::where('pedido_id', $pedidoId)->where('tipo', 'abono')->firstOrFail()->id;

        // Se cierra el corte donde se cobró el abono y se abre uno nuevo (el de hoy).
        $corteOriginal->update(['estado' => 'cerrado']);
        $corteNuevo = CorteCaja::create([
            'empresa_id' => $user->empresa_id,
            'sucursal_id' => $user->sucursal_id,
            'user_id' => $user->id,
            'estado' => 'abierto',
            'terminal' => 'POS-01',
            'fecha_apertura' => now(),
            'fondo_inicial_efectivo' => 0,
        ]);

        $this->deleteJson("/api/pedidos/{$pedidoId}/abonos/{$abonoId}")->assertOk();

        // El ingreso original queda intacto en el corte ya cerrado (no se toca un corte conciliado).
        $this->assertDatabaseHas('movimientos_caja', [
            'corte_id' => $corteOriginal->id,
            'tipo' => 'ingreso',
            'monto' => 150,
        ]);

        // El egreso se registra en el corte de HOY, no en el ya cerrado.
        $this->assertDatabaseHas('movimientos_caja', [
            'corte_id' => $corteNuevo->id,
            'tipo' => 'egreso',
            'forma_pago' => 'efectivo',
            'monto' => 150,
        ]);
        $this->assertDatabaseMissing('movimientos_caja', [
            'corte_id' => $corteOriginal->id,
            'tipo' => 'egreso',
        ]);

        $this->assertSame(-150.0, (float) $corteNuevo->fresh()->movs_efectivo);
    }

    public function test_eliminar_abono_falla_si_no_hay_caja_abierta(): void
    {
        [$user, $cliente, $producto] = $this->crearContexto();
        Sanctum::actingAs($user);

        $corte = CorteCaja::create([
            'empresa_id' => $user->empresa_id,
            'sucursal_id' => $user->sucursal_id,
            'user_id' => $user->id,
            'estado' => 'abierto',
            'terminal' => 'POS-01',
            'fecha_apertura' => now(),
            'fondo_inicial_efectivo' => 0,
        ]);

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
            'anticipo' => 150,
            'forma_pago' => 'efectivo',
            'detalles' => [
                ['producto_id' => $producto->id, 'descripcion' => 'Renglon', 'cantidad' => 1, 'precio_acordado' => 500],
            ],
        ])->assertCreated();

        $pedidoId = $pedido->json('id');
        $abonoId = ClienteSaldoMovimiento::where('pedido_id', $pedidoId)->where('tipo', 'abono')->firstOrFail()->id;

        $corte->update(['estado' => 'cerrado']);

        $this->deleteJson("/api/pedidos/{$pedidoId}/abonos/{$abonoId}")
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'No hay caja abierta para registrar el egreso del abono eliminado.']);
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
