<?php

namespace Tests\Feature;

use App\Models\Categoria;
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

    public function test_filtrar_por_categoria_padre_incluye_subcategorias(): void
    {
        $user = $this->crearContexto();
        Sanctum::actingAs($user);

        $celulares = $this->crearCategoria($user, 'CELULARES');
        $libre = $this->crearCategoria($user, 'LIBRE', $celulares->id);
        $reacondicionado = $this->crearCategoria($user, 'REACONDICIONADO', $libre->id);
        $accesorios = $this->crearCategoria($user, 'ACCESORIOS');

        $enPadre = $this->crearProducto($user, 'CELULAR GENERICO', 'CEL-001', $celulares->id);
        $enHija = $this->crearProducto($user, 'IPHONE 13 LIBRE', 'CEL-002', $libre->id);
        $enNieta = $this->crearProducto($user, 'IPHONE 11 REACONDICIONADO', 'CEL-003', $reacondicionado->id);
        $this->crearProducto($user, 'FUNDA', 'ACC-001', $accesorios->id);

        $this->assertEqualsCanonicalizing(
            [$enPadre->id, $enHija->id, $enNieta->id],
            $this->filtrarCategoria($celulares->id)
        );
        $this->assertEqualsCanonicalizing([$enHija->id, $enNieta->id], $this->filtrarCategoria($libre->id));
        $this->assertSame([$enNieta->id], $this->filtrarCategoria($reacondicionado->id));

        $rutas = collect($this->getJson('/api/productos')->assertOk()->json('data'))
            ->pluck('categoria_ruta', 'id');
        $this->assertSame('CELULARES', $rutas[$enPadre->id]);
        $this->assertSame('CELULARES › LIBRE', $rutas[$enHija->id]);
        $this->assertSame('CELULARES › LIBRE › REACONDICIONADO', $rutas[$enNieta->id]);
    }

    public function test_buscador_de_categorias_muestra_y_busca_por_ruta_completa(): void
    {
        $user = $this->crearContexto();
        Sanctum::actingAs($user);

        $celulares = $this->crearCategoria($user, 'CELULARES');
        $this->crearCategoria($user, 'LIBRE', $celulares->id);
        $this->crearCategoria($user, 'TELCEL', $celulares->id);
        $this->crearCategoria($user, 'ACCESORIOS');

        $rutas = fn(string $q) => collect($this->getJson('/api/categorias/buscar?q=' . urlencode($q))
            ->assertOk()
            ->json())
            ->pluck('ruta')
            ->all();

        $this->assertSame(['CELULARES', 'CELULARES › LIBRE', 'CELULARES › TELCEL'], $rutas('celulares'));
        $this->assertSame(['CELULARES › LIBRE'], $rutas('cel libre'));
        $this->assertSame(['ACCESORIOS', 'CELULARES', 'CELULARES › LIBRE', 'CELULARES › TELCEL'], $rutas(''));
    }

    private function filtrarCategoria(int $categoriaId): array
    {
        return collect($this->getJson('/api/productos?categoria_id=' . $categoriaId)
            ->assertOk()
            ->json('data'))
            ->pluck('id')
            ->all();
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

    private function buscar(string $texto): array
    {
        return collect($this->getJson('/api/productos?buscar=' . urlencode($texto))
            ->assertOk()
            ->json('data'))
            ->pluck('id')
            ->all();
    }

    private function crearProducto(User $user, string $nombre, string $codigo, ?int $categoriaId = null): Producto
    {
        return Producto::create([
            'categoria_id' => $categoriaId,
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
