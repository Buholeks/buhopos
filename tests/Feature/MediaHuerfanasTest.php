<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Marca;
use App\Models\Media;
use App\Models\Mediable;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MediaHuerfanasTest extends TestCase
{
    use DatabaseTransactions;

    public function test_eliminar_producto_limpia_la_referencia_de_su_imagen(): void
    {
        Storage::fake('public');
        [$user] = $this->crearContexto();
        Sanctum::actingAs($user);

        $mediaId = $this->subirImagen();

        $producto = $this->postJson('/api/productos', [
            'nombre' => 'Producto con imagen',
            'precio_costo' => 10,
            'precio_venta' => 20,
            'imagen_media_id' => $mediaId,
        ])->assertCreated()->json('data.id');

        $this->assertSame(1, Mediable::where('media_id', $mediaId)->where('mediable_type', Producto::class)->count());
        $this->assertSame(0, Media::where('id', $mediaId)->whereDoesntHave('mediables')->count());

        $this->deleteJson("/api/productos/{$producto}")->assertOk();

        $this->assertSame(0, Mediable::where('media_id', $mediaId)->where('mediable_type', Producto::class)->count());
        $this->assertSame(1, Media::where('id', $mediaId)->whereDoesntHave('mediables')->count());
    }

    public function test_eliminar_variante_limpia_la_referencia_de_su_imagen(): void
    {
        Storage::fake('public');
        [$user] = $this->crearContexto();
        Sanctum::actingAs($user);

        $mediaId = $this->subirImagen();

        $producto = Producto::create([
            'empresa_id' => $user->empresa_id,
            'sucursal_id' => $user->sucursal_id,
            'user_id' => $user->id,
            'nombre' => 'Producto con variante',
            'codigo' => 'MED-PROD-' . uniqid(),
            'precio_costo' => 0,
            'precio_venta' => 0,
            'activo' => true,
            'tiene_variantes' => true,
            'tiene_series' => false,
            'pedido_generico' => false,
        ]);

        $variante = ProductoVariante::create([
            'producto_id' => $producto->id,
            'empresa_id' => $user->empresa_id,
            'sku' => 'SKU-' . uniqid(),
            'activo' => true,
        ]);

        Mediable::create([
            'media_id' => $mediaId,
            'mediable_type' => ProductoVariante::class,
            'mediable_id' => $variante->id,
            'role' => 'imagen',
        ]);

        $this->assertSame(0, Media::where('id', $mediaId)->whereDoesntHave('mediables')->count());

        $this->deleteJson("/api/productos/{$producto->id}/variantes/{$variante->id}")->assertOk();

        $this->assertSame(0, Mediable::where('media_id', $mediaId)->where('mediable_type', ProductoVariante::class)->count());
        $this->assertSame(1, Media::where('id', $mediaId)->whereDoesntHave('mediables')->count());
    }

    public function test_eliminar_marca_limpia_la_referencia_de_su_logo(): void
    {
        Storage::fake('public');
        [$user] = $this->crearContexto();
        Sanctum::actingAs($user);

        $mediaId = $this->subirImagen();

        $marca = $this->postJson('/api/marcas', [
            'nombre' => 'Marca con logo',
            'logo_media_id' => $mediaId,
        ])->assertCreated()->json('id');

        $this->assertSame(1, Mediable::where('media_id', $mediaId)->where('mediable_type', Marca::class)->count());

        $this->deleteJson("/api/marcas/{$marca}")->assertOk();

        $this->assertSame(0, Mediable::where('media_id', $mediaId)->where('mediable_type', Marca::class)->count());
        $this->assertSame(1, Media::where('id', $mediaId)->whereDoesntHave('mediables')->count());
    }

    public function test_eliminar_modelo_limpia_la_referencia_de_su_imagen(): void
    {
        Storage::fake('public');
        [$user] = $this->crearContexto();
        Sanctum::actingAs($user);

        $marca = Marca::create([
            'empresa_id' => $user->empresa_id,
            'sucursal_id' => $user->sucursal_id,
            'user_id' => $user->id,
            'nombre' => 'Marca para modelo',
            'activo' => true,
        ]);

        $mediaId = $this->subirImagen();

        $modelo = $this->postJson('/api/modelos', [
            'marca_id' => $marca->id,
            'nombre' => 'Modelo con imagen',
            'imagen_media_id' => $mediaId,
        ])->assertCreated()->json('id');

        $this->assertSame(1, Mediable::where('media_id', $mediaId)->where('mediable_type', \App\Models\Modelo::class)->count());

        $this->deleteJson("/api/modelos/{$modelo}")->assertOk();

        $this->assertSame(0, Mediable::where('media_id', $mediaId)->where('mediable_type', \App\Models\Modelo::class)->count());
        $this->assertSame(1, Media::where('id', $mediaId)->whereDoesntHave('mediables')->count());
    }

    public function test_eliminar_un_producto_no_borra_la_imagen_compartida_con_otra_entidad(): void
    {
        Storage::fake('public');
        [$user] = $this->crearContexto();
        Sanctum::actingAs($user);

        $mediaId = $this->subirImagen();
        $ruta = Media::find($mediaId)->ruta;

        // Dos entidades distintas reutilizan la MISMA imagen de la biblioteca.
        $producto = $this->postJson('/api/productos', [
            'nombre' => 'Producto que comparte imagen',
            'precio_costo' => 10,
            'precio_venta' => 20,
            'imagen_media_id' => $mediaId,
        ])->assertCreated()->json('data.id');

        $marca = $this->postJson('/api/marcas', [
            'nombre' => 'Marca que comparte imagen',
            'logo_media_id' => $mediaId,
        ])->assertCreated()->json('id');

        Storage::disk('public')->assertExists($ruta);

        // Al borrar el producto, la marca sigue usando la misma imagen: el archivo
        // fisico y la fila media NO deben desaparecer (antes se borraba con
        // Storage::delete() directo, rompiendo la imagen para quien la compartia).
        $this->deleteJson("/api/productos/{$producto}")->assertOk();

        Storage::disk('public')->assertExists($ruta);
        $this->assertNotNull(Media::find($mediaId));
        $this->assertSame(1, Mediable::where('media_id', $mediaId)->where('mediable_type', Marca::class)->count());
    }

    private function subirImagen(): int
    {
        $archivo = UploadedFile::fake()->image('foto.jpg', 400, 400);

        return $this->postJson('/api/media', [
            'archivo' => $archivo,
            'tipo' => 'producto',
        ])->assertCreated()->json('data.id');
    }

    private function crearContexto(): array
    {
        $empresa = Empresa::create(['nombre' => 'Empresa media', 'activo' => true]);
        $sucursal = Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Matriz', 'activo' => true]);
        $user = User::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'name' => 'Usuario media',
            'email' => 'media-' . uniqid() . '@example.com',
            'password' => 'password',
            'activo' => true,
        ]);
        $user->sucursales()->attach($sucursal->id);

        return [$user];
    }
}
