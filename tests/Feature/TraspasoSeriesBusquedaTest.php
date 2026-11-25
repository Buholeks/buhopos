<?php

namespace Tests\Feature;

use App\Http\Controllers\TraspasoController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class TraspasoSeriesBusquedaTest extends TestCase
{
    public function test_busca_mas_de_treinta_series_y_respeta_disponibilidad_y_exclusiones(): void
    {
        Schema::create('productos', fn (Blueprint $table) => $table->id());
        Schema::create('producto_variantes', fn (Blueprint $table) => $table->id());
        Schema::create('series', function (Blueprint $table) {
            $table->id();
            foreach (['empresa_id', 'sucursal_id', 'producto_id'] as $campo) $table->integer($campo);
            $table->integer('variante_id')->nullable();
            foreach (['imei', 'imei2', 'serie', 'estado'] as $campo) $table->string($campo)->nullable();
        });
        DB::table('productos')->insert(['id' => 1]);
        $base = ['empresa_id' => 1, 'sucursal_id' => 1, 'producto_id' => 1, 'variante_id' => null, 'estado' => 'disponible'];
        for ($i = 1; $i <= 45; $i++) {
            DB::table('series')->insert($base + ['id' => $i, 'imei' => 'EQUIPO-'.$i, 'imei2' => 'SECUNDARIO-'.$i]);
        }
        foreach ([['sucursal_id' => 2], ['empresa_id' => 2], ['variante_id' => 9], ['estado' => 'vendido']] as $cambio) {
            DB::table('series')->insert(array_merge($base, $cambio, ['imei' => 'EQUIPO-AJENO']));
        }
        $user = Mockery::mock(User::class)->makePartial();
        $user->empresa_id = 1;
        $user->sucursal_id = 1;
        $user->shouldReceive('tienePermiso')->with('inventario.ver')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);
        Auth::shouldReceive('userResolver')->andReturn(fn () => $user);
        $consultar = function (array $params) use ($user) {
            $request = Request::create('/api/traspasos/series-disponibles', 'GET', $params + ['producto_id' => 1, 'paginado' => 1]);
            $request->setUserResolver(fn () => $user);
            $this->app->instance('request', $request);
            return app(TraspasoController::class)->seriesDisponibles($request)->getData(true);
        };
        $primera = $consultar(['q' => 'EQUIPO', 'excluir' => [1]]);
        $this->assertCount(20, $primera['data']);
        $this->assertSame(2, $primera['data'][0]['id']);
        $this->assertNotNull($primera['next_page_url']);
        $tercera = $consultar(['q' => 'EQUIPO', 'excluir' => [1], 'page' => 3]);
        $this->assertCount(4, $tercera['data']);
        $this->assertNull($tercera['next_page_url']);
        $exacta = $consultar(['q' => 'SECUNDARIO-45']);
        $this->assertCount(1, $exacta['data']);
        $this->assertSame(45, $exacta['data'][0]['id']);
        $this->assertSame('EQUIPO-45', $exacta['data'][0]['identificador']);
    }
}
