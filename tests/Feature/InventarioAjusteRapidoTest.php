<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Inventario;
use App\Models\InventarioAjusteRapido;
use App\Models\InventarioMovimiento;
use App\Models\KardexMovimiento;
use App\Models\Producto;
use App\Models\Serie;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventarioAjusteRapidoTest extends TestCase
{
    use DatabaseTransactions;

    public function test_registra_salida_de_varios_productos_en_un_solo_ajuste(): void
    {
        [$empresa, $sucursal, $user] = $this->contexto();
        $primero = $this->producto($empresa->id, $sucursal->id, $user->id, false);
        $segundo = $this->producto($empresa->id, $sucursal->id, $user->id, false);
        $this->inventario($empresa->id, $sucursal->id, $primero->id, 10);
        $this->inventario($empresa->id, $sucursal->id, $segundo->id, 8);
        Sanctum::actingAs($user);

        $this->postJson('/api/inventario-ajustes-rapidos', [
            'motivo' => 'Mercancía dañada',
            'partidas' => [
                ['producto_id' => $primero->id, 'variante_id' => null, 'nueva_existencia' => 8],
                ['producto_id' => $segundo->id, 'variante_id' => null, 'nueva_existencia' => 5],
            ],
        ])->assertCreated()->assertJsonPath('message', 'Ajuste aplicado correctamente.');

        $this->assertSame(8.0, (float) Inventario::where('producto_id', $primero->id)->value('stock'));
        $this->assertSame(5.0, (float) Inventario::where('producto_id', $segundo->id)->value('stock'));
        $this->assertSame(1, InventarioAjusteRapido::where('empresa_id', $empresa->id)->count());
        $this->assertSame(2, InventarioMovimiento::where('empresa_id', $empresa->id)->where('motivo', 'Mercancía dañada')->count());
        $this->assertSame(2, KardexMovimiento::where('empresa_id', $empresa->id)->where('referencia_tipo', 'inventario_ajuste_rapido')->count());
    }

    public function test_salida_serializada_exige_y_da_de_baja_las_series(): void
    {
        [$empresa, $sucursal, $user] = $this->contexto();
        $producto = $this->producto($empresa->id, $sucursal->id, $user->id, true);
        $this->inventario($empresa->id, $sucursal->id, $producto->id, 2);
        $serie = Serie::create([
            'empresa_id' => $empresa->id, 'sucursal_id' => $sucursal->id,
            'producto_id' => $producto->id, 'imei' => 'IMEI-AJUSTE-1', 'estado' => 'disponible',
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/inventario-ajustes-rapidos', [
            'motivo' => 'Merma',
            'partidas' => [[
                'producto_id' => $producto->id, 'variante_id' => null,
                'nueva_existencia' => 1, 'serie_ids' => [$serie->id],
            ]],
        ])->assertCreated();

        $this->assertSame('baja', $serie->fresh()->estado);
        $this->assertSame(1.0, (float) Inventario::where('producto_id', $producto->id)->value('stock'));
    }

    public function test_infiere_entrada_y_salida_desde_la_nueva_existencia(): void
    {
        [$empresa, $sucursal, $user] = $this->contexto();
        $entrada = $this->producto($empresa->id, $sucursal->id, $user->id, false);
        $salida = $this->producto($empresa->id, $sucursal->id, $user->id, false);
        $this->inventario($empresa->id, $sucursal->id, $entrada->id, 5);
        $this->inventario($empresa->id, $sucursal->id, $salida->id, 5);
        Sanctum::actingAs($user);

        $this->postJson('/api/inventario-ajustes-rapidos', [
            'motivo' => 'Corrección física',
            'partidas' => [
                ['producto_id' => $entrada->id, 'nueva_existencia' => 8],
                ['producto_id' => $salida->id, 'nueva_existencia' => 2],
            ],
        ])->assertCreated();

        $this->assertSame('mixto', InventarioAjusteRapido::value('tipo'));
        $this->assertDatabaseHas('inventario_movimientos', [
            'producto_id' => $entrada->id, 'tipo' => 'ajuste_positivo',
            'cantidad_anterior' => 5, 'cantidad_movimiento' => 3, 'cantidad_nueva' => 8,
        ]);
        $this->assertDatabaseHas('inventario_movimientos', [
            'producto_id' => $salida->id, 'tipo' => 'ajuste_negativo',
            'cantidad_anterior' => 5, 'cantidad_movimiento' => 3, 'cantidad_nueva' => 2,
        ]);
    }

    public function test_revierte_todo_el_lote_si_una_partida_es_invalida(): void
    {
        [$empresa, $sucursal, $user] = $this->contexto();
        $primero = $this->producto($empresa->id, $sucursal->id, $user->id, false);
        $segundo = $this->producto($empresa->id, $sucursal->id, $user->id, false);
        $this->inventario($empresa->id, $sucursal->id, $primero->id, 5);
        $this->inventario($empresa->id, $sucursal->id, $segundo->id, 1);
        Sanctum::actingAs($user);

        $this->postJson('/api/inventario-ajustes-rapidos', [
            'motivo' => 'Uso interno',
            'partidas' => [
                ['producto_id' => $primero->id, 'nueva_existencia' => 3],
                ['producto_id' => $segundo->id, 'nueva_existencia' => 1],
            ],
        ])->assertUnprocessable();

        $this->assertSame(5.0, (float) Inventario::where('producto_id', $primero->id)->value('stock'));
        $this->assertSame(0, InventarioAjusteRapido::where('empresa_id', $empresa->id)->count());
    }

    public function test_consulta_por_producto_folio_y_abre_el_detalle(): void
    {
        [$empresa, $sucursal, $user] = $this->contexto();
        $producto = $this->producto($empresa->id, $sucursal->id, $user->id, false);
        $this->inventario($empresa->id, $sucursal->id, $producto->id, 5);
        Sanctum::actingAs($user);

        $respuesta = $this->postJson('/api/inventario-ajustes-rapidos', [
            'motivo' => 'Validación de consulta',
            'partidas' => [['producto_id' => $producto->id, 'nueva_existencia' => 3]],
        ])->assertCreated();
        $folio = $respuesta->json('folio');
        $id = $respuesta->json('id');

        $this->getJson('/api/inventario-ajustes-rapidos/consulta?' . http_build_query([
            'q' => $producto->nombre,
            'desde' => now('America/Mexico_City')->toDateString(),
            'hasta' => now('America/Mexico_City')->toDateString(),
        ]))->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.folio', $folio);

        $this->getJson("/api/inventario-ajustes-rapidos/{$id}")
            ->assertOk()
            ->assertJsonPath('folio', $folio)
            ->assertJsonPath('detalles.0.nombre', $producto->nombre)
            ->assertJsonPath('detalles.0.stock_antes', 5)
            ->assertJsonPath('detalles.0.stock_despues', 3);
    }

    private function contexto(): array
    {
        $empresa = Empresa::create(['nombre' => 'Empresa ajustes', 'activo' => true]);
        $sucursal = Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Sucursal ajustes', 'activo' => true]);
        $user = User::create([
            'empresa_id' => $empresa->id, 'sucursal_id' => $sucursal->id,
            'name' => 'Usuario ajustes', 'email' => uniqid('ajustes-') . '@example.com',
            'password' => 'password', 'activo' => true,
        ]);
        $user->sucursales()->attach($sucursal->id);
        return [$empresa, $sucursal, $user];
    }

    private function producto(int $empresaId, int $sucursalId, int $userId, bool $series): Producto
    {
        return Producto::create([
            'empresa_id' => $empresaId, 'sucursal_id' => $sucursalId, 'user_id' => $userId,
            'nombre' => 'Producto ajuste ' . uniqid(), 'codigo' => uniqid('AJ-'),
            'precio_costo' => 10, 'precio_venta' => 20, 'activo' => true,
            'tiene_variantes' => false, 'tiene_series' => $series,
        ]);
    }

    private function inventario(int $empresaId, int $sucursalId, int $productoId, float $stock): void
    {
        Inventario::create([
            'empresa_id' => $empresaId, 'sucursal_id' => $sucursalId,
            'producto_id' => $productoId, 'variante_id' => null,
            'stock' => $stock, 'stock_minimo' => 0,
        ]);
    }
}
