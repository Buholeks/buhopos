<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductoCodigoTest extends TestCase
{
    use RefreshDatabase;

    public function test_inicia_la_numeracion_automatica_en_10001(): void
    {
        $empresa = Empresa::create(['nombre' => 'Empresa código', 'activo' => true]);

        $this->assertSame('10001', Producto::generarCodigo($empresa->id));
    }

    public function test_busca_el_siguiente_codigo_numerico_disponible(): void
    {
        $empresa = Empresa::create(['nombre' => 'Empresa secuencia', 'activo' => true]);
        $sucursal = Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Matriz', 'activo' => true]);
        $user = User::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'name' => 'Usuario códigos',
            'email' => 'codigos-' . uniqid() . '@example.com',
            'password' => 'password',
            'activo' => true,
        ]);

        foreach (['CODIGO-MANUAL', '10003'] as $codigo) {
            Producto::create([
                'empresa_id' => $empresa->id,
                'sucursal_id' => $sucursal->id,
                'user_id' => $user->id,
                'nombre' => 'Producto ' . $codigo,
                'codigo' => $codigo,
                'precio_costo' => 0,
                'precio_venta' => 0,
                'activo' => true,
                'tiene_variantes' => false,
                'tiene_series' => false,
                'pedido_generico' => false,
            ]);
        }

        $this->assertSame('10004', Producto::generarCodigo($empresa->id));
    }
}
