<?php

namespace Tests\Feature;

use App\Models\Atributo;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Sucursal;
use App\Models\TipoAtributo;
use App\Models\User;
use App\Models\VarianteAtributo;
use App\Servicios\VarianteGrupoServicio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductoVarianteGrupoTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_un_grupo_por_color_y_conserva_su_codigo_personalizado(): void
    {
        $empresa = Empresa::create(['nombre' => 'Empresa grupos', 'activo' => true]);
        $sucursal = Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Matriz', 'activo' => true]);
        $user = User::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'name' => 'Usuario grupos',
            'email' => 'grupos@example.com',
            'password' => 'password',
            'activo' => true,
        ]);
        $producto = Producto::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'user_id' => $user->id,
            'nombre' => 'Tenis',
            'codigo' => '10001',
            'precio_costo' => 0,
            'precio_venta' => 0,
            'activo' => true,
            'tiene_variantes' => true,
        ]);
        $color = TipoAtributo::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'user_id' => $user->id,
            'nombre' => 'Color',
            'activo' => true,
        ]);
        $blancoRojo = Atributo::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'user_id' => $user->id,
            'tipo_atributo_id' => $color->id,
            'valor' => 'Blanco rojo',
            'activo' => true,
        ]);
        $talla = TipoAtributo::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'user_id' => $user->id,
            'nombre' => 'Talla',
            'activo' => true,
        ]);
        $talla22 = Atributo::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'user_id' => $user->id,
            'tipo_atributo_id' => $talla->id,
            'valor' => '22',
            'activo' => true,
        ]);

        foreach (['1000101', '1000102'] as $sku) {
            $variante = ProductoVariante::create([
                'producto_id' => $producto->id,
                'empresa_id' => $empresa->id,
                'sku' => $sku,
                'activo' => true,
            ]);
            VarianteAtributo::create([
                'variante_id' => $variante->id,
                'tipo_atributo_id' => $color->id,
                'atributo_id' => $blancoRojo->id,
            ]);
            VarianteAtributo::create([
                'variante_id' => $variante->id,
                'tipo_atributo_id' => $talla->id,
                'atributo_id' => $talla22->id,
            ]);
        }

        $servicio = app(VarianteGrupoServicio::class);
        $grupo = $servicio->sincronizar($producto)->sole();

        $this->assertSame('10001G' . $blancoRojo->id, $grupo->codigo);

        $grupo->update(['codigo' => 'BR-2044', 'es_personalizado' => true]);
        $grupoSincronizado = $servicio->sincronizar($producto)->sole();

        $this->assertSame($grupo->id, $grupoSincronizado->id);
        $this->assertSame('BR-2044', $grupoSincronizado->codigo);
        $this->assertTrue($grupoSincronizado->es_personalizado);

        VarianteAtributo::where('tipo_atributo_id', $talla->id)->delete();

        $this->assertFalse($servicio->productoEsAgrupable($producto->id));
        $this->assertTrue($servicio->sincronizar($producto)->isEmpty());
        $this->assertDatabaseHas('producto_variante_grupos', [
            'id' => $grupo->id,
            'codigo' => 'BR-2044',
        ]);
    }
}
