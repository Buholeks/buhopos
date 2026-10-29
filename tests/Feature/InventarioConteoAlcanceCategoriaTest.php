<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Empresa;
use App\Models\InventarioConteo;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventarioConteoAlcanceCategoriaTest extends TestCase
{
    use RefreshDatabase;

    public function test_alcance_por_categoria_incluye_subcategorias(): void
    {
        [$empresa, $sucursal, $user] = $this->crearContexto();

        $celulares = $this->crearCategoria($user, 'CELULARES');
        $libre = $this->crearCategoria($user, 'LIBRE', $celulares->id);
        $accesorios = $this->crearCategoria($user, 'ACCESORIOS');

        $enPadre = $this->crearProducto($user, 'ALCANCE CELULAR GENERICO', $celulares->id);
        $enHija = $this->crearProducto($user, 'ALCANCE IPHONE LIBRE', $libre->id);
        $this->crearProducto($user, 'ALCANCE FUNDA', $accesorios->id);

        $conteo = InventarioConteo::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'user_id' => $user->id,
            'folio' => 'CNT-ALC-0001',
            'modo' => 'ciego',
            'estado' => 'en_conteo',
            'alcance_tipo' => 'categoria',
            'alcance_id' => $celulares->id,
            'snapshot_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $ids = collect($this->getJson("/api/inventario-conteos/{$conteo->id}/buscar?q=ALCANCE")
            ->assertOk()
            ->json())
            ->pluck('producto_id')
            ->all();

        $this->assertEqualsCanonicalizing([$enPadre->id, $enHija->id], $ids);
    }

    private function crearCategoria(User $user, string $nombre, ?int $padreId = null): Categoria
    {
        return Categoria::create([
            'empresa_id' => $user->empresa_id,
            'sucursal_id' => $user->sucursal_id,
            'user_id' => $user->id,
            'categoria_padre_id' => $padreId,
            'nombre' => $nombre,
            'activo' => true,
        ]);
    }

    private function crearProducto(User $user, string $nombre, int $categoriaId): Producto
    {
        return Producto::create([
            'empresa_id' => $user->empresa_id,
            'sucursal_id' => $user->sucursal_id,
            'user_id' => $user->id,
            'categoria_id' => $categoriaId,
            'nombre' => $nombre,
            'codigo' => 'CNT-' . uniqid(),
            'precio_costo' => 10,
            'precio_venta' => 20,
            'activo' => true,
            'tiene_variantes' => false,
            'tiene_series' => false,
        ]);
    }

    private function crearContexto(): array
    {
        $empresa = Empresa::create(['nombre' => 'Empresa alcance', 'activo' => true]);
        $sucursal = Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Matriz', 'activo' => true]);
        $user = User::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'name' => 'Usuario alcance',
            'email' => 'alcance-' . uniqid() . '@example.com',
            'password' => 'password',
            'activo' => true,
        ]);
        $user->sucursales()->attach($sucursal->id);

        return [$empresa, $sucursal, $user];
    }
}
