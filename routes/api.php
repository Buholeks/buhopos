<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SucursalActivaController;

use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\UserController;

use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\CatalogoPreciosController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\ModeloController;
use App\Http\Controllers\TipoAtributoController;
use App\Http\Controllers\AtributoController;
use App\Http\Controllers\UnidadMedidaController;

use App\Http\Controllers\ProductoController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\CancelacionDevolucionController;
use App\Http\Controllers\CorteCajaController;
use App\Http\Controllers\ExhibicionController;
use App\Http\Controllers\SerieController;
use App\Http\Controllers\TraspasoController;

use App\Http\Controllers\CompraProveedorController;
use App\Http\Controllers\AbonoProveedorController;
use App\Http\Controllers\PagoProveedorController;

use App\Http\Controllers\ReporteCajaController;
use App\Http\Controllers\ReporteComprasController;
use App\Http\Controllers\ReporteVentasController;
use App\Http\Controllers\ReporteVentasAgrupadoController;
use App\Http\Controllers\ReporteUtilidadesController;

/*
|--------------------------------------------------------------------------
| Auth
|--------------------------------------------------------------------------
*/

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/me', function (Request $request) {
        $user = $request->user();

        if (!$user->activo) {
            Auth::logout();
            abort(403, 'Tu cuenta está pendiente de activación. Contacta al administrador.');
        }

        if (!$user->empresa_id || !$user->sucursal_id) {
            Auth::logout();
            abort(403, 'Usuario sin empresa o sucursal asignada');
        }

        return $user->load(['empresa:id,nombre', 'sucursal:id,nombre']);
    });

    /*
    |--------------------------------------------------------------------------
    | Sucursal activa
    |--------------------------------------------------------------------------
    */

    Route::get('/mis-sucursales', [SucursalActivaController::class, 'misSucursales']);
    Route::post('/cambiar-sucursal', [SucursalActivaController::class, 'cambiarSucursal']);

    /*
    |--------------------------------------------------------------------------
    | Personas
    |--------------------------------------------------------------------------
    */

    Route::get('/clientes/buscar', [ClienteController::class, 'buscar']);
    Route::apiResource('clientes', ClienteController::class);

    Route::apiResource('proveedores', ProveedorController::class);

    Route::get('/users/vendedores', [UserController::class, 'buscarVendedores']);

    /*
    |--------------------------------------------------------------------------
    | Catálogos
    |--------------------------------------------------------------------------
    */

    Route::apiResource('categorias', CategoriaController::class);

    Route::apiResource('marcas', MarcaController::class);
    Route::apiResource('modelos', ModeloController::class);

    Route::apiResource('tipo-atributos', TipoAtributoController::class);
    Route::apiResource('atributos', AtributoController::class);

    Route::get('/unidades-medida/tipos', [UnidadMedidaController::class, 'listarTipos']);
    Route::apiResource('unidades-medida', UnidadMedidaController::class);

    /*
    |--------------------------------------------------------------------------
    | Productos
    |--------------------------------------------------------------------------
    */

    Route::get('/productos/atributos-empresa', [ProductoController::class, 'atributosEmpresa']);
    Route::get('/productos/catalogo-precios', [CatalogoPreciosController::class, 'index']);
    Route::apiResource('productos', ProductoController::class);

    Route::prefix('productos/{id}/variantes')->group(function () {
        Route::get('/', [ProductoController::class, 'variantes']);
        Route::post('/', [ProductoController::class, 'storeVariante']);
        Route::put('/{varianteId}', [ProductoController::class, 'updateVariante']);
        Route::delete('/{varianteId}', [ProductoController::class, 'destroyVariante']);
    });

    /*
    |--------------------------------------------------------------------------
    | Compras
    |--------------------------------------------------------------------------
    */

    Route::prefix('compras')->group(function () {
        Route::get('/', [CompraController::class, 'index']);
        Route::get('/buscar-variantes', [CompraController::class, 'buscarVariantes']);
        Route::post('/', [CompraController::class, 'store']);
        Route::get('/{id}', [CompraController::class, 'show']);
        Route::delete('/{id}', [CompraController::class, 'destroy']);
        Route::post('/{id}/cancelar', [CompraController::class, 'cancelar']);

        Route::prefix('{compraId}/pagos')->group(function () {
            Route::get('/', [PagoProveedorController::class, 'index']);
            Route::post('/', [PagoProveedorController::class, 'store']);
            Route::delete('/{id}', [PagoProveedorController::class, 'destroy']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Compras proveedor / Abonos proveedor
    | OJO: Si ya usas CompraController + pagos, estos podrían sobrar después.
    |--------------------------------------------------------------------------
    */

    Route::get('/compras-proveedor', [CompraProveedorController::class, 'index']);
    Route::post('/compras-proveedor', [CompraProveedorController::class, 'store']);
    Route::post('/abonos-proveedor', [AbonoProveedorController::class, 'store']);

    /*
    |--------------------------------------------------------------------------
    | Ventas
    |--------------------------------------------------------------------------
    */

    Route::prefix('ventas')->group(function () {
        Route::get('/', [VentaController::class, 'index']);
        Route::get('/buscar-variantes', [VentaController::class, 'buscarVariantes']);
        Route::post('/', [VentaController::class, 'store']);
        Route::get('/{id}', [VentaController::class, 'show']);
        Route::delete('/{id}', [VentaController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | Pedidos y apartados
    |--------------------------------------------------------------------------
    */

    Route::get('/clientes/{cliente}/pedidos-resumen', [PedidoController::class, 'clienteResumen']);
    Route::get('/pedidos/buscar-catalogo', [PedidoController::class, 'buscarCatalogo']);
    Route::get('/pedidos', [PedidoController::class, 'index']);
    Route::post('/pedidos', [PedidoController::class, 'store']);
    Route::get('/pedidos/{pedido}', [PedidoController::class, 'show']);
    Route::post('/pedidos/{pedido}/abonos', [PedidoController::class, 'abonar']);
    Route::post('/pedidos/{pedido}/cancelar', [PedidoController::class, 'cancelar']);

    Route::prefix('cancelaciones-devoluciones')->group(function () {
        Route::get('/buscar', [CancelacionDevolucionController::class, 'buscar']);
        Route::post('/cancelar', [CancelacionDevolucionController::class, 'cancelar']);
        Route::post('/devolver', [CancelacionDevolucionController::class, 'devolver']);
    });

    /*
    |--------------------------------------------------------------------------
    | Caja / Cortes
    |--------------------------------------------------------------------------
    */

    Route::prefix('cortes-caja')->group(function () {
        Route::get('/', [CorteCajaController::class, 'index']);
        Route::get('/actual', [CorteCajaController::class, 'actual']);
        Route::post('/abrir', [CorteCajaController::class, 'abrir']);

        Route::get('/{id}', [CorteCajaController::class, 'show']);
        Route::post('/{id}/cerrar', [CorteCajaController::class, 'cerrar']);

        Route::post('/{id}/movimiento', [CorteCajaController::class, 'agregarMovimiento']);
        Route::delete('/{id}/movimiento/{movId}', [CorteCajaController::class, 'eliminarMovimiento']);

        Route::get('/{id}/ventas', [CorteCajaController::class, 'ventas']);
        Route::post('/{id}/desglose', [CorteCajaController::class, 'guardarDesgloseEnVivo']);
    });

    /*
    |--------------------------------------------------------------------------
    | Inventario / Exhibición
    |--------------------------------------------------------------------------
    */

    Route::prefix('exhibicion')->group(function () {
        Route::get('/', [ExhibicionController::class, 'index']);
        Route::get('/{inventario}/variantes', [ExhibicionController::class, 'variantes']);
        Route::patch('/{inventario}/exhibir', [ExhibicionController::class, 'exhibir']);
        Route::patch('/{inventario}/quitar', [ExhibicionController::class, 'quitar']);
    });

    /*
    |--------------------------------------------------------------------------
    | Traspasos de mercancía
    |--------------------------------------------------------------------------
    */

    Route::prefix('traspasos')->group(function () {
        Route::get('/', [TraspasoController::class, 'index']);
        Route::post('/', [TraspasoController::class, 'store']);
        Route::get('/sucursales', [TraspasoController::class, 'sucursales']);
        Route::get('/resumen-pendientes', [TraspasoController::class, 'resumenPendientes']);
        Route::get('/inventario', [TraspasoController::class, 'inventario']);
        Route::get('/series-disponibles', [TraspasoController::class, 'seriesDisponibles']);
        Route::get('/{id}', [TraspasoController::class, 'show']);
        Route::post('/{id}/recibir', [TraspasoController::class, 'recibir']);
        Route::post('/{id}/rechazar', [TraspasoController::class, 'rechazar']);
        Route::post('/{id}/cancelar', [TraspasoController::class, 'cancelar']);
    });

    /*
    |--------------------------------------------------------------------------
    | Series / IMEI
    |--------------------------------------------------------------------------
    */

    Route::prefix('series')->group(function () {
        Route::get('/', [SerieController::class, 'index']);
        Route::get('/verificar-imei', [SerieController::class, 'verificarImei']);
        Route::get('/buscar-imei', [SerieController::class, 'buscarImei']);
        Route::get('/disponibles', [SerieController::class, 'disponibles']);
        Route::get('/{serie}', [SerieController::class, 'show']);
        Route::post('/', [SerieController::class, 'store']);
        Route::patch('/{serie}', [SerieController::class, 'update']);
    });

    /*
    |--------------------------------------------------------------------------
    | Reportes
    |--------------------------------------------------------------------------
    */

    Route::prefix('reportes')->group(function () {
        /*
        | Caja
        */
        Route::prefix('caja')->group(function () {
            Route::get('/', [ReporteCajaController::class, 'index']);
            Route::get('/{id}', [ReporteCajaController::class, 'show']);
            Route::get('/{id}/ventas', [ReporteCajaController::class, 'ventas']);
        });

        /*
        | Compras
        */
        Route::prefix('compras')->group(function () {
            Route::get('/', [ReporteComprasController::class, 'index']);
            Route::get('/cuentas-por-pagar', [ReporteComprasController::class, 'cuentasPorPagar']);
            Route::get('/{id}', [ReporteComprasController::class, 'show']);
        });

        /*
        | Ventas
        */
        Route::prefix('ventas')->group(function () {
            Route::get('/resumen', [ReporteVentasController::class, 'resumen']);
            Route::get('/', [ReporteVentasController::class, 'index']);

            // Recomendación: mover esto a VentaController@show
            Route::get('/{id}', [ReporteVentasController::class, 'show']);
        });

        /*
        | Ventas agrupadas
        */
        Route::prefix('ventas-agrupado')->group(function () {
            Route::get('/buscar/{entidad}', [ReporteVentasAgrupadoController::class, 'buscar']);


            Route::get('/articulos/detalle', [ReporteVentasAgrupadoController::class, 'detalleArticulos']);
            Route::get('/articulos', [ReporteVentasAgrupadoController::class, 'porArticulo']);


            Route::get('/clientes', [ReporteVentasAgrupadoController::class, 'porCliente']);
            Route::get('/categorias', [ReporteVentasAgrupadoController::class, 'porCategoria']);
            Route::get('/marcas', [ReporteVentasAgrupadoController::class, 'porMarca']);
            Route::get('/modelos', [ReporteVentasAgrupadoController::class, 'porModelo']);
            Route::get('/proveedores', [ReporteVentasAgrupadoController::class, 'porProveedor']);
        });

        Route::get('/utilidades', [ReporteUtilidadesController::class, 'index']);
    });
});
