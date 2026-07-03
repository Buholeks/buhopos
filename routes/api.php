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
use App\Http\Controllers\CuentaBancariaController;
use App\Http\Controllers\TerminalPagoController;

use App\Http\Controllers\ProductoController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\CancelacionDevolucionController;
use App\Http\Controllers\CorteCajaController;
use App\Http\Controllers\ExhibicionController;
use App\Http\Controllers\InventarioConteoController;
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
use App\Http\Controllers\ReporteInventarioController;
use App\Http\Controllers\ReporteArticuloController;
use App\Http\Controllers\DevolucionProveedorController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\EtiquetaController;
use App\Http\Controllers\MediaController;

/*
|--------------------------------------------------------------------------
| Auth
|--------------------------------------------------------------------------
*/

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Públicos — sin autenticación (cert y firma son operaciones de handshake de QZ Tray)
Route::get('/etiquetas/qztray/cert', [EtiquetaController::class, 'qzCertificado']);
Route::get('/etiquetas/qztray/instalador', [EtiquetaController::class, 'qzInstalador']);
Route::post('/etiquetas/qztray/sign', [EtiquetaController::class, 'qzFirmar']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/perfil', [ProfileController::class, 'show']);
    Route::put('/perfil/usuario', [ProfileController::class, 'updateUser']);
    Route::put('/perfil/empresa', [ProfileController::class, 'updateEmpresa']);
    Route::post('/perfil/empresa/logo', [ProfileController::class, 'uploadLogo']);
    Route::delete('/perfil/empresa/logo', [ProfileController::class, 'deleteLogo']);
    Route::put('/perfil/sucursal', [ProfileController::class, 'updateSucursal']);
    Route::get('/ticket-config', [ProfileController::class, 'getTicketConfig']);
    Route::put('/ticket-config', [ProfileController::class, 'saveTicketConfig']);

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
    Route::get('/users/sucursales-disponibles', [UserController::class, 'sucursalesDisponibles']);
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::put('/users/{user}/super-admin', [UserController::class, 'actualizarSuperAdmin']);
    Route::get('/users/{user}/sucursales', [UserController::class, 'sucursalesDeUsuario']);
    Route::put('/users/{user}/sucursales', [UserController::class, 'sincronizarSucursales']);

    /*
    |--------------------------------------------------------------------------
    | Roles y Permisos
    |--------------------------------------------------------------------------
    */

    Route::get('/permisos', [RolController::class, 'listarPermisos']);
    Route::apiResource('roles', RolController::class);

    /*
    |--------------------------------------------------------------------------
    | Catálogos
    |--------------------------------------------------------------------------
    */

    Route::get('/categorias/buscar', [CategoriaController::class, 'buscar']);
    Route::post('/categorias/{id}/restore', [CategoriaController::class, 'restore']);
    Route::apiResource('categorias', CategoriaController::class);

    Route::get('/marcas/buscar', [MarcaController::class, 'buscar']);
    Route::post('/marcas/{id}/restore', [MarcaController::class, 'restore']);
    Route::apiResource('marcas', MarcaController::class);
    Route::get('/modelos/buscar', [ModeloController::class, 'buscar']);
    Route::post('/modelos/{id}/restore', [ModeloController::class, 'restore']);
    Route::apiResource('modelos', ModeloController::class);

    Route::post('/tipo-atributos/{id}/restore', [TipoAtributoController::class, 'restore']);
    Route::apiResource('tipo-atributos', TipoAtributoController::class);
    Route::post('/atributos/{id}/restore', [AtributoController::class, 'restore']);
    Route::apiResource('atributos', AtributoController::class);

    Route::get('/unidades-medida/tipos', [UnidadMedidaController::class, 'listarTipos']);
    Route::get('/unidades-medida/buscar', [UnidadMedidaController::class, 'buscar']);
    Route::post('/unidades-medida/{id}/restore', [UnidadMedidaController::class, 'restore']);
    Route::apiResource('unidades-medida', UnidadMedidaController::class);

    Route::get('/cuentas-bancarias/buscar', [CuentaBancariaController::class, 'buscar']);
    Route::post('/cuentas-bancarias/{id}/restore', [CuentaBancariaController::class, 'restore']);
    Route::apiResource('cuentas-bancarias', CuentaBancariaController::class);

    Route::get('/terminales-pago/buscar', [TerminalPagoController::class, 'buscar']);
    Route::post('/terminales-pago/{id}/restore', [TerminalPagoController::class, 'restore']);
    Route::apiResource('terminales-pago', TerminalPagoController::class);

    /*
    |--------------------------------------------------------------------------
    | Productos
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | Media (biblioteca de imágenes)
    |--------------------------------------------------------------------------
    */
    Route::get('/media/resumen', [MediaController::class, 'resumen']);
    Route::delete('/media/limpiar-huerfanas', [MediaController::class, 'limpiarHuerfanas']);
    Route::get('/media', [MediaController::class, 'index']);
    Route::post('/media', [MediaController::class, 'store']);
    Route::delete('/media/{id}', [MediaController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | Productos
    |--------------------------------------------------------------------------
    */
    Route::get('/productos/atributos-empresa', [ProductoController::class, 'atributosEmpresa']);
    Route::get('/productos/catalogo-precios', [CatalogoPreciosController::class, 'index']);
    Route::get('/productos/importacion/plantilla', [ProductoController::class, 'plantillaImportacion']);
    Route::post('/productos/importacion/previsualizar', [ProductoController::class, 'previsualizarImportacion']);
    Route::post('/productos/importacion', [ProductoController::class, 'importar']);
    Route::post('/productos/{id}/restore', [ProductoController::class, 'restore']);
    Route::apiResource('productos', ProductoController::class);

    Route::prefix('productos/{id}/variantes')->group(function () {
        Route::get('/', [ProductoController::class, 'variantes']);
        Route::post('/', [ProductoController::class, 'storeVariante']);
        Route::patch('/restablecer-precios', [ProductoController::class, 'restablecerPreciosVariantes']);
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
        Route::get('/pedidos-pendientes', [CompraController::class, 'pedidosPendientes']);
        Route::post('/', [CompraController::class, 'store']);
        Route::get('/{id}', [CompraController::class, 'show']);
        Route::delete('/{id}', [CompraController::class, 'destroy']);
        Route::post('/{compraId}/cancelar', [DevolucionProveedorController::class, 'cancelar']);

        Route::prefix('{compraId}/pagos')->group(function () {
            Route::get('/', [PagoProveedorController::class, 'index']);
            Route::post('/', [PagoProveedorController::class, 'store']);
            Route::delete('/{id}', [PagoProveedorController::class, 'destroy']);
        });
    });

    Route::prefix('etiquetas')->group(function () {
        Route::get('/configuracion', [EtiquetaController::class, 'configuracion']);
        Route::get('/compras/{compraId}', [EtiquetaController::class, 'compra']);
        Route::get('/catalogo', [EtiquetaController::class, 'buscarCatalogo']);
        Route::post('/plantillas', [EtiquetaController::class, 'guardarPlantilla']);
        Route::put('/plantillas/{id}', [EtiquetaController::class, 'guardarPlantilla']);
        Route::delete('/plantillas/{id}', [EtiquetaController::class, 'eliminarPlantilla']);
        Route::post('/perfiles', [EtiquetaController::class, 'guardarPerfil']);
        Route::put('/perfiles/{id}', [EtiquetaController::class, 'guardarPerfil']);
        Route::put('/precios', [EtiquetaController::class, 'actualizarPrecios']);
    });

    Route::prefix('devoluciones-proveedor')->group(function () {
        Route::get('/compras', [DevolucionProveedorController::class, 'buscarCompras']);
        Route::get('/compras/{compraId}', [DevolucionProveedorController::class, 'show']);
        Route::post('/compras/{compraId}/cancelar', [DevolucionProveedorController::class, 'cancelar']);
        Route::post('/', [DevolucionProveedorController::class, 'store']);
        Route::delete('/{devolucionId}', [DevolucionProveedorController::class, 'destroy']);
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
        Route::get('/existencias', [VentaController::class, 'existenciasPorSucursal']);
        Route::post('/', [VentaController::class, 'store']);
        Route::get('/{id}', [VentaController::class, 'show']);
    });

    /*
    |--------------------------------------------------------------------------
    | Pedidos y apartados
    |--------------------------------------------------------------------------
    */

    Route::get('/clientes/{cliente}/pedidos-resumen', [PedidoController::class, 'clienteResumen']);
    Route::get('/pedidos/buscar-catalogo', [PedidoController::class, 'buscarCatalogo']);
    Route::get('/pedidos/pendientes-compra', [PedidoController::class, 'pendientesCompra']);
    Route::post('/pedidos/producto-rapido', [PedidoController::class, 'productoRapido']);
    Route::get('/pedidos/productos/{productoId}/variantes', [PedidoController::class, 'variantesProducto']);
    Route::post('/pedidos/productos/{productoId}/variantes', [PedidoController::class, 'varianteRapida']);
    Route::get('/pedidos', [PedidoController::class, 'index']);
    Route::post('/pedidos', [PedidoController::class, 'store']);
    Route::get('/pedidos/{pedido}', [PedidoController::class, 'show']);
    Route::post('/pedidos/{pedido}/abonos', [PedidoController::class, 'abonar']);
    Route::delete('/pedidos/{pedido}/abonos/{abono}', [PedidoController::class, 'eliminarAbono']);
    Route::post('/pedidos/{pedido}/cancelar', [PedidoController::class, 'cancelar']);

    Route::prefix('cancelaciones-devoluciones')->group(function () {
        Route::get('/buscar', [CancelacionDevolucionController::class, 'buscar']);
        Route::get('/exportar-pdf', [CancelacionDevolucionController::class, 'exportarPdf']);
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
        Route::get('/abiertas', [CorteCajaController::class, 'abiertas']);
        Route::get('/actual', [CorteCajaController::class, 'actual']);
        Route::post('/abrir', [CorteCajaController::class, 'abrir']);
        Route::get('/exportar', [CorteCajaController::class, 'exportarHistorial']);

        Route::get('/{id}', [CorteCajaController::class, 'show']);
        Route::post('/{id}/cerrar', [CorteCajaController::class, 'cerrar']);

        Route::post('/{id}/movimiento', [CorteCajaController::class, 'agregarMovimiento']);
        Route::delete('/{id}/movimiento/{movId}', [CorteCajaController::class, 'eliminarMovimiento']);

        Route::get('/{id}/ventas', [CorteCajaController::class, 'ventas']);
        Route::get('/{id}/exportar-pdf', [CorteCajaController::class, 'exportarDetalle']);
        Route::post('/{id}/desglose', [CorteCajaController::class, 'guardarDesgloseEnVivo']);
    });

    Route::get('/movimientos-caja/usuarios', [CorteCajaController::class, 'usuariosConsulta']);
    Route::get('/movimientos-caja/exportar', [CorteCajaController::class, 'exportarConsulta']);
    Route::get('/movimientos-caja', [CorteCajaController::class, 'consultaMovimientos']);

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

    Route::prefix('inventario-conteos')->group(function () {
        Route::get('/', [InventarioConteoController::class, 'index']);
        Route::post('/', [InventarioConteoController::class, 'store']);
        Route::get('/alcances', [InventarioConteoController::class, 'alcances']);
        Route::get('/{id}/exportar-pdf', [InventarioConteoController::class, 'exportarPdf']);
        Route::get('/{id}', [InventarioConteoController::class, 'show']);
        Route::get('/{id}/escanear', [InventarioConteoController::class, 'escanear']);
        Route::get('/{id}/buscar', [InventarioConteoController::class, 'buscar']);
        Route::post('/{id}/capturar', [InventarioConteoController::class, 'capturar']);
        Route::delete('/{id}/linea', [InventarioConteoController::class, 'eliminarLinea']);
        Route::delete('/{id}/serie', [InventarioConteoController::class, 'quitarSerie']);
        Route::post('/{id}/cerrar', [InventarioConteoController::class, 'cerrar']);
        Route::post('/{id}/reabrir', [InventarioConteoController::class, 'reabrir']);
        Route::post('/{id}/ajustar', [InventarioConteoController::class, 'ajustar']);
        Route::post('/{id}/cancelar', [InventarioConteoController::class, 'cancelar']);
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
        Route::get('/{id}/exportar-pdf', [TraspasoController::class, 'exportarDetalle']);
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
            Route::get('/exportar', [ReporteCajaController::class, 'exportar']);
            Route::get('/{id}', [ReporteCajaController::class, 'show']);
            Route::get('/{id}/ventas', [ReporteCajaController::class, 'ventas']);
        });

        /*
        | Compras
        */
        Route::prefix('compras')->group(function () {
            Route::get('/', [ReporteComprasController::class, 'index']);
            Route::get('/exportar', [ReporteComprasController::class, 'exportar']);
            Route::get('/cuentas-por-pagar', [ReporteComprasController::class, 'cuentasPorPagar']);
            Route::get('/{id}/exportar-pdf', [ReporteComprasController::class, 'exportarDetalle']);
            Route::get('/{id}', [ReporteComprasController::class, 'show']);
        });

        /*
        | Ventas
        */
        Route::prefix('ventas')->group(function () {
            Route::get('/resumen', [ReporteVentasController::class, 'resumen']);
            Route::get('/exportar', [ReporteVentasController::class, 'exportar']);
            Route::get('/', [ReporteVentasController::class, 'index']);

            // Recomendación: mover esto a VentaController@show
            Route::get('/{id}', [ReporteVentasController::class, 'show']);
        });

        /*
        | Ventas agrupadas
        */
        Route::prefix('ventas-agrupado')->group(function () {
            Route::get('/exportar', [ReporteVentasAgrupadoController::class, 'exportar']);
            Route::get('/buscar/{entidad}', [ReporteVentasAgrupadoController::class, 'buscar']);


            Route::get('/articulos/detalle', [ReporteVentasAgrupadoController::class, 'detalleArticulos']);
            Route::get('/articulos', [ReporteVentasAgrupadoController::class, 'porArticulo']);


            Route::get('/clientes', [ReporteVentasAgrupadoController::class, 'porCliente']);
            Route::get('/categorias', [ReporteVentasAgrupadoController::class, 'porCategoria']);
            Route::get('/marcas', [ReporteVentasAgrupadoController::class, 'porMarca']);
            Route::get('/modelos', [ReporteVentasAgrupadoController::class, 'porModelo']);
            Route::get('/proveedores', [ReporteVentasAgrupadoController::class, 'porProveedor']);
        });

        Route::get('/utilidades/exportar', [ReporteUtilidadesController::class, 'exportar']);
        Route::get('/utilidades', [ReporteUtilidadesController::class, 'index']);
        Route::get('/articulo/buscar-productos', [ReporteArticuloController::class, 'buscarProductos']);
        Route::get('/articulo/historial', [ReporteArticuloController::class, 'historial']);
        Route::get('/articulo/exportar', [ReporteArticuloController::class, 'exportar']);
        Route::get('/inventario/exportar', [ReporteInventarioController::class, 'exportar']);
        Route::get('/inventario', [ReporteInventarioController::class, 'index']);
    });
});
