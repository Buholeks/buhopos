<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TraspasoTest extends TestCase
{
    use DatabaseTransactions;

    public function test_registra_y_recibe_traspaso_actualizando_inventario(): void
    {
        $empresa = Empresa::create([
            'nombre' => 'Empresa traspasos',
            'activo' => true,
        ]);

        $origen = Sucursal::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Origen',
            'activo' => true,
        ]);

        $destino = Sucursal::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Destino',
            'activo' => true,
        ]);

        $user = User::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $origen->id,
            'name' => 'Usuario traspasos',
            'email' => 'traspasos-test-' . uniqid() . '@example.com',
            'password' => 'password',
            'activo' => true,
        ]);
        $user->sucursales()->attach($origen->id);

        $receptor = User::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $destino->id,
            'name' => 'Receptor traspasos',
            'email' => 'receptor-traspasos-test-' . uniqid() . '@example.com',
            'password' => 'password',
            'activo' => true,
        ]);
        $receptor->sucursales()->attach($destino->id);

        $producto = Producto::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $origen->id,
            'user_id' => $user->id,
            'nombre' => 'Producto traspaso',
            'codigo' => 'TRP-TEST',
            'precio_costo' => 10,
            'precio_venta' => 20,
            'activo' => true,
            'tiene_variantes' => false,
            'tiene_series' => false,
        ]);

        Inventario::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $origen->id,
            'producto_id' => $producto->id,
            'variante_id' => null,
            'stock' => 5,
            'stock_minimo' => 0,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/traspasos', [
            'destino_sucursal_id' => $destino->id,
            'detalles' => [
                [
                    'producto_id' => $producto->id,
                    'variante_id' => null,
                    'cantidad' => 2,
                ],
            ],
        ]);

        $response->assertCreated();
        $traspasoId = $response->json('id');

        $this->assertSame(3.0, $this->stock($empresa->id, $origen->id, $producto->id));
        $this->assertSame(0.0, $this->stock($empresa->id, $destino->id, $producto->id));
        $this->assertSame('pendiente', $response->json('estado'));
        $this->assertDatabaseHas('traspaso_detalles', [
            'traspaso_id' => $traspasoId,
            'producto_id' => $producto->id,
            'cantidad' => 2,
        ]);

        Sanctum::actingAs($receptor);

        $this->postJson("/api/traspasos/{$traspasoId}/recibir")->assertOk();

        $this->assertSame(3.0, $this->stock($empresa->id, $origen->id, $producto->id));
        $this->assertSame(2.0, $this->stock($empresa->id, $destino->id, $producto->id));
    }

    public function test_recibe_solo_las_partidas_seleccionadas(): void
    {
        $empresa = Empresa::create([
            'nombre' => 'Empresa traspasos parcial',
            'activo' => true,
        ]);

        $origen = Sucursal::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Origen parcial',
            'activo' => true,
        ]);

        $destino = Sucursal::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Destino parcial',
            'activo' => true,
        ]);

        $user = User::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $origen->id,
            'name' => 'Usuario parcial',
            'email' => 'traspasos-parcial-' . uniqid() . '@example.com',
            'password' => 'password',
            'activo' => true,
        ]);
        $user->sucursales()->attach($origen->id);

        $receptor = User::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $destino->id,
            'name' => 'Receptor parcial',
            'email' => 'receptor-parcial-' . uniqid() . '@example.com',
            'password' => 'password',
            'activo' => true,
        ]);
        $receptor->sucursales()->attach($destino->id);

        $productoA = Producto::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $origen->id,
            'user_id' => $user->id,
            'nombre' => 'Producto parcial A',
            'codigo' => 'TRP-P-A',
            'precio_costo' => 15,
            'precio_venta' => 30,
            'activo' => true,
            'tiene_variantes' => false,
            'tiene_series' => false,
        ]);

        $productoB = Producto::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $origen->id,
            'user_id' => $user->id,
            'nombre' => 'Producto parcial B',
            'codigo' => 'TRP-P-B',
            'precio_costo' => 20,
            'precio_venta' => 45,
            'activo' => true,
            'tiene_variantes' => false,
            'tiene_series' => false,
        ]);

        foreach ([$productoA, $productoB] as $producto) {
            Inventario::create([
                'empresa_id' => $empresa->id,
                'sucursal_id' => $origen->id,
                'producto_id' => $producto->id,
                'variante_id' => null,
                'stock' => 5,
                'stock_minimo' => 0,
            ]);
        }

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/traspasos', [
            'destino_sucursal_id' => $destino->id,
            'detalles' => [
                ['producto_id' => $productoA->id, 'variante_id' => null, 'cantidad' => 1],
                ['producto_id' => $productoB->id, 'variante_id' => null, 'cantidad' => 2],
            ],
        ])->assertCreated();

        $traspasoId = $response->json('id');
        $detalleA = collect($response->json('detalles'))->firstWhere('producto_id', $productoA->id);
        $detalleB = collect($response->json('detalles'))->firstWhere('producto_id', $productoB->id);

        $this->getJson('/api/traspasos?tipo=salida&buscar=Producto%20parcial%20A&estado=con_pendientes&orden=valor_compra_desc')
            ->assertOk()
            ->assertJsonPath('summary.total_traspasos', 1)
            ->assertJsonPath('summary.total_piezas', 3)
            ->assertJsonPath('summary.total_compra', 55)
            ->assertJsonPath('data.0.id', $traspasoId);

        Sanctum::actingAs($receptor);

        $this->postJson("/api/traspasos/{$traspasoId}/recibir", [
            'detalle_ids' => [$detalleA['id']],
        ])->assertOk();

        $this->assertSame(1.0, $this->stock($empresa->id, $destino->id, $productoA->id));
        $this->assertSame(0.0, $this->stock($empresa->id, $destino->id, $productoB->id));
        $this->assertDatabaseHas('traspasos', [
            'id' => $traspasoId,
            'estado' => 'pendiente',
        ]);
        $this->assertDatabaseHas('traspaso_detalles', [
            'id' => $detalleA['id'],
            'estado' => 'recibido',
        ]);
        $this->assertDatabaseHas('traspaso_detalles', [
            'id' => $detalleB['id'],
            'estado' => 'pendiente',
        ]);
    }

    private function stock(int $empresaId, int $sucursalId, int $productoId): float
    {
        return (float) Inventario::where('empresa_id', $empresaId)
            ->where('sucursal_id', $sucursalId)
            ->where('producto_id', $productoId)
            ->whereNull('variante_id')
            ->value('stock');
    }
}
