<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductoVarianteSkuTest extends TestCase
{
    use RefreshDatabase;

    public function test_genera_el_sku_con_el_codigo_del_producto_y_su_consecutivo(): void
    {
        [$producto, $empresa] = $this->crearProducto('TENIS001');

        $this->assertSame('TENIS00101', ProductoVariante::generarSku($producto->id, $empresa->id));

        ProductoVariante::create([
            'producto_id' => $producto->id,
            'empresa_id' => $empresa->id,
            'sku' => 'TENIS00101',
            'activo' => true,
        ]);

        $this->assertSame('TENIS00102', ProductoVariante::generarSku($producto->id, $empresa->id));
    }

    public function test_omite_un_sku_manual_que_ocupa_el_siguiente_consecutivo(): void
    {
        [$producto, $empresa] = $this->crearProducto('PLAYERA15');

        foreach (['SKU-MANUAL', 'PLAYERA1503'] as $sku) {
            ProductoVariante::create([
                'producto_id' => $producto->id,
                'empresa_id' => $empresa->id,
                'sku' => $sku,
                'activo' => true,
            ]);
        }

        $this->assertSame('PLAYERA1504', ProductoVariante::generarSku($producto->id, $empresa->id));
    }

    private function crearProducto(string $codigo): array
    {
        $empresa = Empresa::create(['nombre' => 'Empresa SKU ' . uniqid(), 'activo' => true]);
        $sucursal = Sucursal::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Matriz',
            'activo' => true,
        ]);
        $user = User::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'name' => 'Usuario SKU',
            'email' => 'sku-' . uniqid() . '@example.com',
            'password' => 'password',
            'activo' => true,
        ]);

        $producto = Producto::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'user_id' => $user->id,
            'nombre' => 'Producto de prueba',
            'codigo' => $codigo,
            'precio_costo' => 0,
            'precio_venta' => 0,
            'activo' => true,
            'tiene_variantes' => true,
            'tiene_series' => false,
            'pedido_generico' => false,
        ]);

        return [$producto, $empresa];
    }
}
