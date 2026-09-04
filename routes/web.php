<?php

use App\Http\Controllers\Admin\AlmacenController;
use App\Http\Controllers\Admin\CajaController;
use App\Http\Controllers\Admin\CategoriaController;
use App\Http\Controllers\Admin\ClienteController;
use App\Http\Controllers\Admin\CobranzaController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DocumentoController;
use App\Http\Controllers\Admin\EgresoController;
use App\Http\Controllers\Admin\FacturaController;
use App\Http\Controllers\Admin\GalonajeController;
use App\Http\Controllers\Admin\GuiaRemisionController;
use App\Http\Controllers\Admin\HistorialPagoController;
use App\Http\Controllers\Admin\IngresoController;
use App\Http\Controllers\Admin\InventarioController;
use App\Http\Controllers\Admin\MarcaController;
use App\Http\Controllers\Admin\MerchController;
use App\Http\Controllers\Admin\NotificacionController;
use App\Http\Controllers\Admin\OrdenCompraController;
use App\Http\Controllers\Admin\PedidoController;
use App\Http\Controllers\Admin\PersonalController;
use App\Http\Controllers\Admin\PosController;
use App\Http\Controllers\Admin\ProductoController;
use App\Http\Controllers\Admin\ProveedorController;
use App\Http\Controllers\Admin\ReporteController;
use App\Http\Controllers\Admin\TransporteController;
use App\Http\Controllers\Admin\VentaController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ContadorController;
use App\Http\Controllers\SecretariaController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Autenticación
|--------------------------------------------------------------------------
*/
// La raíz manda a cada quien a su sitio: al panel si ya inició sesión, al
// login si no. Redirigir siempre al login hacía rebotar a los autenticados.
Route::get('/', function () {
    /** @var \App\Models\Usuario|null $usuario */
    $usuario = Auth::user();

    return $usuario
        ? redirect($usuario->rutaInicio())
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);

    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::patch('/perfil', [PerfilController::class, 'update'])
    ->middleware('auth')
    ->name('perfil.update');

/*
|--------------------------------------------------------------------------
| Panel administrativo
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'rol:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/', [DashboardController::class, 'index'])->name('index');

        Route::post('notificaciones/marcar-leidas', [NotificacionController::class, 'marcarLeidas'])->name('notificaciones.marcar-leidas');

        // ── Gestión comercial ───────────────────────────────────────────────
        Route::get('clientes/destacados', [ClienteController::class, 'destacados'])->name('clientes.destacados');
        Route::get('clientes/buscar', [ClienteController::class, 'buscar'])->name('clientes.buscar');
        Route::post('clientes/importar', [ClienteController::class, 'importar'])->name('clientes.importar');
        Route::get('clientes/{cliente}/movimientos', [ClienteController::class, 'movimientos'])->name('clientes.movimientos');
        Route::resource('clientes', ClienteController::class)->except(['show', 'create', 'edit']);

        Route::get('documentos/buscar/{tipo}/{numero}', [DocumentoController::class, 'buscar'])
            ->whereIn('tipo', ['ruc', 'dni'])
            ->name('documentos.buscar');
        Route::resource('proveedores', ProveedorController::class)->except(['show', 'create', 'edit'])
            ->parameters(['proveedores' => 'proveedor']);

        // La edición se hace desde el modal del listado; el alta es una página aparte.
        Route::get('ventas/{venta}/comprobante', [VentaController::class, 'comprobante'])->name('ventas.comprobante');
        Route::get('ventas/factura/crear', [VentaController::class, 'createFactura'])->name('ventas.factura.create');
        Route::post('ventas/factura', [VentaController::class, 'storeFactura'])->name('ventas.factura.store');
        Route::get('ventas/notas/crear/{origen?}', [VentaController::class, 'createNota'])->name('ventas.notas.create');
        Route::post('ventas/notas', [VentaController::class, 'storeNota'])->name('ventas.notas.store');
        Route::post('ventas/{venta}/enviar-sunat', [VentaController::class, 'enviarSunat'])->name('ventas.enviar-sunat');
        Route::post('ventas/{venta}/anular', [VentaController::class, 'anular'])->name('ventas.anular');
        Route::get('ventas/{venta}/pdf-sunat', [VentaController::class, 'pdfSunat'])->name('ventas.pdf-sunat');
        Route::resource('ventas', VentaController::class)
            ->except(['create', 'edit'])
            ->parameters(['ventas' => 'venta']);

        // Pedidos que hacen los clientes directamente (antes de convertirse en
        // venta) — el modelo ya existía y se mostraba de solo lectura dentro
        // de Órdenes de Compra; esto agrega el alta/edición real.
        Route::resource('pedidos', PedidoController::class)
            ->except(['show', 'create', 'edit'])
            ->parameters(['pedidos' => 'pedido']);

        // ── Compras & documentos ────────────────────────────────────────────
        Route::get('ordenes-compra/excel', [OrdenCompraController::class, 'excel'])->name('ordenes-compra.excel');
        Route::get('ordenes-compra/verificar-numero', [OrdenCompraController::class, 'verificarNumero'])->name('ordenes-compra.verificar-numero');
        Route::post('ordenes-compra/{orden}/enviar', [OrdenCompraController::class, 'enviarCorreo'])->name('ordenes-compra.enviar');
        Route::post('ordenes-compra/{orden}/token', [OrdenCompraController::class, 'generarToken'])->name('ordenes-compra.token');
        Route::post('ordenes-compra/{orden}/documento', [OrdenCompraController::class, 'actualizarDocumento'])->name('ordenes-compra.documento');
        Route::resource('ordenes-compra', OrdenCompraController::class)->parameters(['ordenes-compra' => 'orden']);

        Route::get('facturas/estadisticas', [FacturaController::class, 'estadisticas'])->name('facturas.estadisticas');
        Route::post('facturas/analizar', [FacturaController::class, 'analizar'])->name('facturas.analizar');
        Route::get('facturas', [FacturaController::class, 'index'])->name('facturas.index');
        Route::post('facturas', [FacturaController::class, 'store'])->name('facturas.store');
        Route::get('facturas/{factura}', [FacturaController::class, 'show'])->name('facturas.show');
        Route::put('facturas/{factura}', [FacturaController::class, 'update'])->name('facturas.update');
        Route::delete('facturas/{factura}', [FacturaController::class, 'destroy'])->name('facturas.destroy');
        Route::post('facturas/{factura}/cancelar', [FacturaController::class, 'alternarCancelado'])->name('facturas.cancelar');
        Route::post('facturas/{factura}/pagada', [FacturaController::class, 'alternarPagada'])->name('facturas.pagada');
        Route::post('facturas/{factura}/pdf', [FacturaController::class, 'subirPdf'])->name('facturas.pdf');
        Route::delete('facturas/{factura}/pdf', [FacturaController::class, 'eliminarPdf'])->name('facturas.pdf-eliminar');

        // ── Finanzas ────────────────────────────────────────────────────────
        Route::get('cobranzas/importar', [CobranzaController::class, 'formImportar'])->name('cobranzas.importar');
        Route::post('cobranzas/importar', [CobranzaController::class, 'importar']);
        Route::post('cobranzas/{cobranza}/pago', [CobranzaController::class, 'registrarPago'])->name('cobranzas.pago');
        Route::resource('cobranzas', CobranzaController::class)->except(['show', 'create', 'edit'])
            ->parameters(['cobranzas' => 'cobranza']);

        Route::get('historial-pagos', [HistorialPagoController::class, 'index'])->name('historial-pagos.index');

        Route::resource('ingresos', IngresoController::class)->except(['show', 'create', 'edit'])
            ->parameters(['ingresos' => 'ingreso']);

        Route::post('egresos/sincronizar', [EgresoController::class, 'sincronizar'])->name('egresos.sincronizar');
        Route::resource('egresos', EgresoController::class)->except(['show', 'create', 'edit'])
            ->parameters(['egresos' => 'egreso']);

        // ── Inventario ──────────────────────────────────────────────────────
        Route::get('productos/buscar', [ProductoController::class, 'buscar'])->name('productos.buscar');
        // Pestañas del catálogo; van antes del resource para no chocar con {producto}.
        Route::get('productos/categorias', [ProductoController::class, 'categorias'])->name('productos.categorias');
        Route::get('productos/presentaciones', [ProductoController::class, 'presentaciones'])->name('productos.presentaciones');
        Route::get('productos/almacenes', [ProductoController::class, 'almacenes'])->name('productos.almacenes');
        Route::get('productos/importar', [ProductoController::class, 'formImportar'])->name('productos.importar');
        Route::post('productos/importar', [ProductoController::class, 'importar']);
        Route::post('productos/{producto}/stock', [ProductoController::class, 'ajustarStock'])->name('productos.stock');
        Route::resource('productos', ProductoController::class)->except(['show', 'create', 'edit'])
            ->parameters(['productos' => 'producto']);

        Route::resource('categorias', CategoriaController::class)
            ->except(['show', 'create', 'edit'])->parameters(['categorias' => 'categoria']);
        Route::resource('marcas', MarcaController::class)
            ->except(['show', 'create', 'edit'])->parameters(['marcas' => 'marca']);
        Route::post('almacenes/{almacen}/estado', [AlmacenController::class, 'alternarEstado'])->name('almacenes.estado');
        Route::resource('almacenes', AlmacenController::class)
            ->except(['show', 'create', 'edit'])->parameters(['almacenes' => 'almacen']);

        Route::get('inventario/movimientos', [InventarioController::class, 'movimientos'])->name('inventario.movimientos');
        Route::post('inventario/traslados', [InventarioController::class, 'storeTraslado'])->name('inventario.traslados.store');
        Route::post('inventario/devoluciones', [InventarioController::class, 'storeDevolucion'])->name('inventario.devoluciones.store');
        Route::get('inventario/kardex', [InventarioController::class, 'kardex'])->name('inventario.kardex');
        Route::get('inventario/kardex/excel', [InventarioController::class, 'kardexExcel'])->name('inventario.kardex.excel');
        Route::get('inventario/kardex/pdf', [InventarioController::class, 'kardexPdf'])->name('inventario.kardex.pdf');
        Route::get('inventario/kardex-valorizado', [InventarioController::class, 'kardexValorizado'])->name('inventario.kardex-valorizado');
        Route::get('inventario/kardex-valorizado/excel', [InventarioController::class, 'kardexValorizadoExcel'])->name('inventario.kardex-valorizado.excel');
        Route::get('inventario/kardex-valorizado/pdf', [InventarioController::class, 'kardexValorizadoPdf'])->name('inventario.kardex-valorizado.pdf');
        Route::get('inventario/reporte', [InventarioController::class, 'reporte'])->name('inventario.reporte');
        Route::get('inventario/reporte/excel', [InventarioController::class, 'reporteExcel'])->name('inventario.reporte.excel');
        Route::get('inventario/reporte/pdf', [InventarioController::class, 'reportePdf'])->name('inventario.reporte.pdf');

        Route::get('merch/movimientos', [MerchController::class, 'movimientos'])->name('merch.movimientos');
        Route::post('merch/{merch}/entregar', [MerchController::class, 'entregar'])->name('merch.entregar');
        Route::delete('merch/movimientos/{movimiento}', [MerchController::class, 'anularMovimiento'])->name('merch.movimientos.anular');
        Route::resource('merch', MerchController::class)->except(['show', 'create', 'edit'])
            ->parameters(['merch' => 'merch']);

        // ── Logística ───────────────────────────────────────────────────────
        Route::get('transporte', [TransporteController::class, 'index'])->name('transporte.index');
        Route::post('transporte/empresas', [TransporteController::class, 'storeEmpresa'])->name('transporte.empresas.store');
        Route::put('transporte/empresas/{empresa}', [TransporteController::class, 'updateEmpresa'])->name('transporte.empresas.update');
        Route::delete('transporte/empresas/{empresa}', [TransporteController::class, 'destroyEmpresa'])->name('transporte.empresas.destroy');
        Route::post('transporte/tarifas', [TransporteController::class, 'storeTarifa'])->name('transporte.tarifas.store');
        Route::put('transporte/tarifas/{tarifa}', [TransporteController::class, 'updateTarifa'])->name('transporte.tarifas.update');
        Route::delete('transporte/tarifas/{tarifa}', [TransporteController::class, 'destroyTarifa'])->name('transporte.tarifas.destroy');

        Route::get('guias/{guia}/excel', [GuiaRemisionController::class, 'excel'])->name('guias.excel');
        Route::resource('guias', GuiaRemisionController::class)->parameters(['guias' => 'guia']);

        // ── Recursos humanos ────────────────────────────────────────────────
        Route::resource('personal', PersonalController::class)->except(['show', 'create', 'edit'])
            ->parameters(['personal' => 'personal']);

        // ── Análisis ────────────────────────────────────────────────────────
        Route::get('reportes', [ReporteController::class, 'index'])->name('reportes.index');
        Route::get('reportes/abc', [ReporteController::class, 'abc'])->name('reportes.abc');
        Route::get('reportes/abc/excel', [ReporteController::class, 'abcExcel'])->name('reportes.abc.excel');
        Route::get('reportes/abc/pdf', [ReporteController::class, 'abcPdf'])->name('reportes.abc.pdf');
        Route::get('reportes/rotacion', [ReporteController::class, 'rotacion'])->name('reportes.rotacion');
        Route::get('reportes/rotacion/excel', [ReporteController::class, 'rotacionExcel'])->name('reportes.rotacion.excel');
        Route::get('reportes/rotacion/pdf', [ReporteController::class, 'rotacionPdf'])->name('reportes.rotacion.pdf');
        Route::get('reportes/aging', [ReporteController::class, 'aging'])->name('reportes.aging');
        Route::get('reportes/aging/excel', [ReporteController::class, 'agingExcel'])->name('reportes.aging.excel');
        Route::get('reportes/aging/pdf', [ReporteController::class, 'agingPdf'])->name('reportes.aging.pdf');

        Route::prefix('galonaje')->name('galonaje.')->group(function () {
            Route::get('/', [GalonajeController::class, 'dashboard'])->name('dashboard');
            Route::get('productos', [GalonajeController::class, 'productos'])->name('productos.index');
            Route::post('productos', [GalonajeController::class, 'guardarProducto'])->name('productos.store');
            Route::delete('productos/{codigo}', [GalonajeController::class, 'eliminarProducto'])->name('productos.destroy');
            Route::get('categorias', [GalonajeController::class, 'categorias'])->name('categorias.index');
            Route::post('categorias', [GalonajeController::class, 'guardarCategoria'])->name('categorias.store');
            Route::delete('categorias/{codigo}', [GalonajeController::class, 'eliminarCategoria'])->name('categorias.destroy');
            Route::get('presentaciones', [GalonajeController::class, 'presentaciones'])->name('presentaciones.index');
            Route::post('presentaciones', [GalonajeController::class, 'guardarPresentacion'])->name('presentaciones.store');
            Route::delete('presentaciones/{codigo}', [GalonajeController::class, 'eliminarPresentacion'])->name('presentaciones.destroy');
            Route::post('metas', [GalonajeController::class, 'guardarMeta'])->name('metas.store');
            Route::post('metas/anio', [GalonajeController::class, 'guardarMetas'])->name('metas.anio');
        });

        // Catálogo de cajas físicas (crear "Caja 01", etc.) — solo admin.
        // Abrir/cerrar sesión de una caja ya existente es de admin y secretaria
        // por igual, ver el grupo aparte más abajo.
        Route::post('caja', [CajaController::class, 'store'])->name('caja.store');
    });

/*
|--------------------------------------------------------------------------
| Punto de Venta y Caja — admin y secretaria
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'rol:admin,secretaria'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::prefix('pos')->name('pos.')->group(function () {
            Route::get('/', [PosController::class, 'index'])->name('index');
            Route::get('productos/buscar', [PosController::class, 'buscarProductos'])->name('productos.buscar');
            Route::post('venta', [PosController::class, 'store'])->name('venta');
            Route::post('suspender', [PosController::class, 'suspender'])->name('suspender');
            Route::get('suspendidas', [PosController::class, 'listaSuspendidas'])->name('suspendidas.index');
            Route::get('suspendidas/{ventaSuspendida}', [PosController::class, 'recuperar'])->name('suspendidas.recuperar');
            Route::delete('suspendidas/{ventaSuspendida}', [PosController::class, 'eliminarSuspendida'])->name('suspendidas.destroy');
        });

        Route::prefix('caja')->name('caja.')->group(function () {
            Route::get('/', [CajaController::class, 'index'])->name('index');
            Route::post('{caja}/abrir', [CajaController::class, 'abrir'])->name('abrir');
            Route::post('sesiones/{sesionCaja}/cerrar', [CajaController::class, 'cerrar'])->name('cerrar');
        });
    });

/*
|--------------------------------------------------------------------------
| Panel de secretaría
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'rol:secretaria'])
    ->prefix('secretaria')
    ->name('secretaria.')
    ->group(function () {
        Route::get('/', [SecretariaController::class, 'index'])->name('index');
    });

/*
|--------------------------------------------------------------------------
| Panel de contabilidad
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'rol:contador'])
    ->prefix('contador')
    ->name('contador.')
    ->group(function () {
        Route::get('/', [ContadorController::class, 'index'])->name('index');
    });

/*
|--------------------------------------------------------------------------
| Acceso público por token (edición de órdenes de compra por el proveedor)
|--------------------------------------------------------------------------
*/
Route::get('orden/{token}', [OrdenCompraController::class, 'editablePublica'])->name('orden.publica');
Route::post('orden/{token}', [OrdenCompraController::class, 'guardarPublica']);
