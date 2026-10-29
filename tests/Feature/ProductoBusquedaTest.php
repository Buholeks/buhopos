<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductoBusquedaTest extends TestCase
{
    use RefreshDatabase;

    public function test_busca_por_palabras_en_cualquier_orden_y_posicion(): void
    {
        $user = $this->crearContexto();
        Sanctum::actingAs($user);

        $cable = $this->crearProducto($user, 'CABLE IPHONE USB C A C CON AGUJA 60W 1M', 'CAB-001');
        $this->crearProducto($user, 'CARGADOR SAMSUNG 25W', 'CAR-001');

        foreach (['con aguja', 'cable con aguja 1m', 'cab agu', 'aguja cable', 'CON AGUJA'] as $busqueda) {
            $ids = $this->buscar($busqueda);
            $this->assertSame([$cable->id], $ids, "Búsqueda: {$busqueda}");
        }

        $this->assertSame([], $this->buscar('con agua'));
        $this->assertSame([], $this->buscar('cable samsung'));
    }

    public function test_codigo_exacto_y_sku_de_variante_siguen_funcionando(): void
    {
        $user = $this->crearContexto();
        Sanctum::actingAs($user);

        $producto = $this->crearProducto($user, 'FUNDA TRANSPARENTE', 'FUN-001');
        $otro = $this->crearProducto($user, 'MICA CRISTAL', 'FUN-0010');
        ProductoVariante::create([
            'producto_id' => $producto->id,
            'empresa_id' => $user->empresa_id,
            'sku' => 'SKU-XYZ-77',
            'activo' => true,
        ]);

        $this->assertSame([$producto->id], $this->buscar('FUN-001'));
        $this->assertSame([$producto->id], $this->buscar('xyz 77'));
        $this->assertSame([$otro->id], $this->buscar('mica'));
    }

    private function buscar(string $texto): array
    {
        return collect($this->getJson('/api/productos?buscar=' . urlencode($texto))
            ->assertOk()
            ->json('data'))
            ->pluck('id')
            ->all();
    }

    private function crearProducto(User $user, string $nombre, string $codigo): Producto
    {
        return Producto::create([
            'empresa_id' => $user->empresa_id,
            'sucursal_id' => $user->sucursal_id,
            'user_id' => $user->id,
            'nombre' => $nombre,
            'codigo' => $codigo,
            'precio_costo' => 0,
            'precio_venta' => 0,
            'activo' => true,
            'tiene_variantes' => false,
            'tiene_series' => false,
            'pedido_generico' => false,
        ]);
    }

    private function crearContexto(): User
    {
        $empresa = Empresa::create(['nombre' => 'Empresa búsqueda', 'activo' => true]);
        $sucursal = Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Matriz', 'activo' => true]);
        $user = User::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'name' => 'Usuario búsqueda',
            'email' => 'busqueda-' . uniqid() . '@example.com',
            'password' => 'password',
            'activo' => true,
        ]);
        $user->sucursales()->attach($sucursal->id);

        return $user;
    }
}
