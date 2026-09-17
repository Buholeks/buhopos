<?php

namespace Tests\Feature;

use App\Models\CorteCaja;
use App\Models\Empresa;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VentaPorUnidadDecimalTest extends TestCase
{
    use RefreshDatabase;

    public function test_se_puede_comprar_1_metro_y_vender_50_centimetros(): void
    {
        $empresa = Empresa::create(['nombre' => 'Empresa cable', 'activo' => true]);
        $sucursal = Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Matriz', 'activo' => true]);
        $user = User::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'name' => 'Cajero',
            'email' => 'cajero-' . uniqid() . '@example.com',
            'password' => 'password',
            'activo' => true,
        ]);
        $user->sucursales()->attach($sucursal->id);

        $unidadMetro = UnidadMedida::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'user_id' => $user->id,
            'nombre' => 'Metro',
            'abreviatura' => 'm',
            'tipo' => 'longitud',
            'activo' => true,
        ]);

        $producto = Producto::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'user_id' => $user->id,
            'nombre' => 'Cable eléctrico',
            'codigo' => 'CABLE-' . uniqid(),
            'unidad_medida_id' => $unidadMetro->id,
            'precio_costo' => 0,
            'precio_venta' => 20,
            'activo' => true,
            'tiene_variantes' => false,
            'tiene_series' => false,
        ]);

        Sanctum::actingAs($user);

        // 1) Búsqueda en el POS debe reportar el tipo de unidad para habilitar decimales en la UI.
        $this->getJson('/api/ventas/buscar-variantes?q=' . urlencode($producto->codigo))
            ->assertOk()
            ->assertJsonPath('0.unidad_tipo', 'longitud')
            ->assertJsonPath('0.unidad_abreviatura', 'm');

        // 2) Comprar 1 metro.
        $proveedorId = \App\Models\Proveedor::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'user_id' => $user->id,
            'nombre_comercial' => 'Proveedor cable',
        ])->id;

        $this->postJson('/api/compras', [
            'proveedor_id' => $proveedorId,
            'folio' => 'COMPRA-CABLE',
            'fecha' => now()->toDateString(),
            'forma_pago' => 'efectivo',
            'detalles' => [[
                'producto_id' => $producto->id,
                'cantidad' => 1,
                'precio_compra' => 10,
                'precio_venta' => 20,
            ]],
        ])->assertCreated();

        $inv = Inventario::where('producto_id', $producto->id)->first();
        $this->assertEquals(1.0, (float) $inv->stock);

        // 3) Vender 0.5 metros (50 cm).
        CorteCaja::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'user_id' => $user->id,
            'estado' => 'abierto',
            'terminal' => 'POS-01',
            'fecha_apertura' => now(),
            'fondo_inicial_efectivo' => 0,
        ]);

        $venta = $this->postJson('/api/ventas', [
            'fecha' => now()->toDateString(),
            'vendedor_id' => $user->id,
            'pagos' => [
                ['forma_pago' => 'efectivo', 'monto' => 10, 'monto_recibido' => 10],
            ],
            'detalles' => [[
                'producto_id' => $producto->id,
                'cantidad' => 0.5,
                'precio_venta' => 20,
            ]],
        ])->assertCreated();

        $this->assertDatabaseHas('venta_detalles', [
            'venta_id' => $venta->json('id'),
            'cantidad' => 0.5,
            'subtotal' => 10,
        ]);

        // 4) El stock debe quedar en 0.5 m, no truncado a 0 o 1.
        $this->assertEquals(0.5, (float) $inv->fresh()->stock);
    }

    public function test_venta_rechaza_cantidad_menor_al_minimo(): void
    {
        $empresa = Empresa::create(['nombre' => 'Empresa mínimo', 'activo' => true]);
        $sucursal = Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Matriz', 'activo' => true]);
        $user = User::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'name' => 'Cajero',
            'email' => 'cajero-' . uniqid() . '@example.com',
            'password' => 'password',
            'activo' => true,
        ]);
        $user->sucursales()->attach($sucursal->id);

        $producto = Producto::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'user_id' => $user->id,
            'nombre' => 'Producto normal',
            'codigo' => 'PROD-' . uniqid(),
            'precio_costo' => 0,
            'precio_venta' => 20,
            'activo' => true,
            'tiene_variantes' => false,
            'tiene_series' => false,
        ]);

        Sanctum::actingAs($user);

        CorteCaja::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'user_id' => $user->id,
            'estado' => 'abierto',
            'terminal' => 'POS-01',
            'fecha_apertura' => now(),
            'fondo_inicial_efectivo' => 0,
        ]);

        $this->postJson('/api/ventas', [
            'fecha' => now()->toDateString(),
            'vendedor_id' => $user->id,
            'pagos' => [
                ['forma_pago' => 'efectivo', 'monto' => 20, 'monto_recibido' => 20],
            ],
            'detalles' => [[
                'producto_id' => $producto->id,
                'cantidad' => 0,
                'precio_venta' => 20,
            ]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('detalles.0.cantidad');
    }
}
