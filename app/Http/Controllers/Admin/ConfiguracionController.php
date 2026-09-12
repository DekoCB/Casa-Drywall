<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Hub de Configuración: reemplaza la grilla que antes vivía dentro del
 * modal "Configuración de cuenta" (`usuario-menu.blade.php`). Mismo patrón
 * que `ReporteController::index()` — un array `['Área' => [items]]` con
 * icono/título/descripción/ruta por tarjeta.
 */
class ConfiguracionController extends Controller
{
    public function index(): View
    {
        $secciones = [
            'Mi Negocio' => [
                ['route' => 'admin.configuracion.empresa', 'titulo' => 'Mi Empresa', 'desc' => 'RUC, razón social, dirección y datos de contacto.', 'icon' => 'empresa'],
                ['route' => 'admin.locales.index', 'titulo' => 'Mi Local', 'desc' => 'Locales y establecimientos registrados ante SUNAT.', 'icon' => 'local'],
                ['route' => 'admin.configuracion.pagos', 'titulo' => 'Pagos y Bancos', 'desc' => 'Cuentas bancarias para que te paguen tus clientes.', 'icon' => 'pagos'],
            ],
            'Catálogo' => [
                ['route' => 'admin.categorias.index', 'titulo' => 'Categorías', 'desc' => 'Categorías de productos del catálogo general.', 'icon' => 'categoria'],
                ['route' => 'admin.marcas.index', 'titulo' => 'Marcas', 'desc' => 'Marcas asociadas a los productos.', 'icon' => 'marca'],
                ['route' => 'admin.productos.almacenes', 'titulo' => 'Almacenes', 'desc' => 'Almacenes y su stock por ubicación.', 'icon' => 'almacen'],
            ],
            'Comercial' => [
                ['route' => 'admin.caja.index', 'titulo' => 'Cajas', 'desc' => 'Catálogo de cajas del Punto de Venta y su historial.', 'icon' => 'almacen'],
                ['route' => 'admin.personal.index', 'titulo' => 'Personal', 'desc' => 'Altas, bajas y accesos al sistema del equipo.', 'icon' => 'personal'],
                ['route' => 'admin.cargos.index', 'titulo' => 'Cargos', 'desc' => 'Lista de cargos disponibles al dar de alta a un colaborador.', 'icon' => 'categoria'],
            ],
        ];

        return view('admin.configuracion.index', ['secciones' => $secciones]);
    }

    /** Solo lectura por ahora — los datos de la empresa viven en config/rentaltech.php (.env). */
    public function empresa(): View
    {
        return view('admin.configuracion.empresa', [
            'empresa' => config('rentaltech.empresa'),
        ]);
    }

    /** Solo lectura por ahora — las cuentas bancarias viven en config/rentaltech.php. */
    public function pagos(): View
    {
        return view('admin.configuracion.pagos', [
            'cuentas' => config('rentaltech.cuentas_bancarias', []),
        ]);
    }
}
