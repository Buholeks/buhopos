<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Inventario;
use App\Models\InventarioConteo;
use App\Models\InventarioConteoDetalle;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Models\Serie;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventarioConteoTest extends TestCase
{
    use DatabaseTransactions;

    public function test_no_captura_series_que_no_estan_disponibles(): void
    {
        [$empresa, $sucursal, $user] = $this->crearContexto();
        $producto = $this->crearProducto($empresa->id, $sucursal->id, $user->id, true);

        $conteo = InventarioConteo::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'user_id' => $user->id,
            'folio' => 'CNT-TEST-0001',
            'modo' => 'ciego',
            'estado' => 'en_conteo',
            'snapshot_at' => now(),
        ]);

        Serie::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'producto_id' => $producto->id,
            'variante_id' => null,
            'imei' => 'IMEI-VENDIDO',
            'estado' => 'vendido',
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/inventario-conteos/{$conteo->id}/escanear?q=IMEI-VENDIDO")
            ->assertOk()
            ->assertJsonPath('tipo', 'no_encontrado');
    }

    public function test_bloquea_ajuste_si_hay_movimientos_posteriores_al_snapshot(): void
    {
        [$empresa, $sucursal, $user] = $this->crearContexto();
        $producto = $this->crearProducto($empresa->id, $sucursal->id, $user->id, false);

        Inventario::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'producto_id' => $producto->id,
            'variante_id' => null,
            'stock' => 5,
            'stock_minimo' => 0,
        ]);

        $conteo = InventarioConteo::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'user_id' => $user->id,
            'folio' => 'CNT-TEST-0002',
            'modo' => 'ciego',
            'estado' => 'en_revision',
            'snapshot_at' => now()->subMinute(),
        ]);

        InventarioConteoDetalle::create([
            'conteo_id' => $conteo->id,
            'producto_id' => $producto->id,
            'variante_id' => null,
            'stock_sistema' => 5,
            'cantidad_fisica' => 3,
            'diferencia' => -2,
            'costo_unitario' => 10,
            'estado' => 'faltante',
        ]);

        InventarioMovimiento::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'producto_id' => $producto->id,
            'variante_id' => null,
            'user_id' => $user->id,
            'tipo' => 'ajuste_positivo',
            'cantidad_anterior' => 5,
            'cantidad_movimiento' => 1,
            'cantidad_nueva' => 6,
            'motivo' => 'Movimiento posterior',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/inventario-conteos/{$conteo->id}/ajustar", [
            'motivo' => 'Conteo fisico',
        ])->assertUnprocessable()
            ->assertJsonFragment(['message' => 'Hay movimientos posteriores al snapshot. Reabre o crea un nuevo conteo antes de aplicar ajustes.']);

        $this->assertSame(5.0, (float) Inventario::where('empresa_id', $empresa->id)
            ->where('sucursal_id', $sucursal->id)
            ->where('producto_id', $producto->id)
            ->whereNull('variante_id')
            ->value('stock'));
    }

    private function crearContexto(): array
    {
        $empresa = Empresa::create(['nombre' => 'Empresa conteos', 'activo' => true]);
        $sucursal = Sucursal::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Sucursal conteos',
            'activo' => true,
        ]);
        $user = User::create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'name' => 'Usuario conteos',
            'email' => 'conteos-' . uniqid() . '@example.com',
            'password' => 'password',
            'activo' => true,
        ]);
        $user->sucursales()->attach($sucursal->id);

        return [$empresa, $sucursal, $user];
    }

    private function crearProducto(int $empresaId, int $sucursalId, int $userId, bool $tieneSeries): Producto
    {
        return Producto::create([
            'empresa_id' => $empresaId,
            'sucursal_id' => $sucursalId,
            'user_id' => $userId,
            'nombre' => 'Producto conteo ' . uniqid(),
            'codigo' => 'CNT-' . uniqid(),
            'precio_costo' => 10,
            'precio_venta' => 20,
            'activo' => true,
            'tiene_variantes' => false,
            'tiene_series' => $tieneSeries,
        ]);
    }
}
