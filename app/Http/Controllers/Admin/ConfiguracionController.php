<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CuentaBancaria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Hub de Configuración: reemplaza la grilla que antes vivía dentro del
 * modal "Configuración de cuenta" (`usuario-menu.blade.php`). Mismo patrón
 * que `ReporteController::index()` — un array `['Área' => [items]]` con
 * icono/título/descripción/ruta por tarjeta.
 */
class ConfiguracionController extends Controller
{
    /**
     * Tarjetas de "Mi Negocio" que todavía no tienen página propia — cada
     * una abre `proximamente()` con esta clave. El contenido real de cada
     * una se define más adelante, a pedido.
     */
    private const PROXIMAMENTE = [
        'credenciales' => ['titulo' => 'Credenciales y certificados', 'desc' => 'SOAP SUNAT, certificado digital, PSE y régimen tributario.', 'icon' => 'llave'],
        'tipo-negocio' => ['titulo' => 'Tipo de Negocio', 'desc' => 'Activa funciones por rubro: hoteles, restaurantes, grifo, farmacia, venta de ropa y calzado.', 'icon' => 'maletin'],
        'estilos' => ['titulo' => 'Estilos y temas', 'desc' => 'Personaliza colores y apariencia del sistema.', 'icon' => 'paleta'],
        'avanzada' => ['titulo' => 'Configuración avanzada', 'desc' => 'Opciones generales del sistema.', 'icon' => 'controles'],
        'link-pago' => ['titulo' => 'Link de pago', 'desc' => 'Genera enlaces de cobro para tus clientes.', 'icon' => 'enlace'],
    ];

    public function index(): View
    {
        $secciones = [
            'Mi Negocio' => [
                'icono' => 'empresa', 'color' => '#7c3aed',
                'sub' => 'Información de tu empresa, apariencia y canales de venta',
                'items' => array_merge(
                    [['route' => 'admin.configuracion.datos-empresa', 'titulo' => 'Datos de la Empresa', 'desc' => 'Mi Empresa, Mi Local y Pagos y Bancos.', 'icon' => 'empresa']],
                    collect(self::PROXIMAMENTE)->map(fn ($item, $clave) => [
                        'route' => 'admin.configuracion.proximamente',
                        'query' => ['clave' => $clave],
                        'titulo' => $item['titulo'],
                        'desc' => $item['desc'],
                        'icon' => $item['icon'],
                    ])->values()->all(),
                ),
            ],
            'Catálogo' => [
                'icono' => 'almacen', 'color' => '#1F4A86',
                'sub' => 'Categorías, marcas y almacenes del catálogo general',
                'items' => [
                    ['route' => 'admin.categorias.index', 'titulo' => 'Categorías', 'desc' => 'Categorías de productos del catálogo general.', 'icon' => 'categoria'],
                    ['route' => 'admin.marcas.index', 'titulo' => 'Marcas', 'desc' => 'Marcas asociadas a los productos.', 'icon' => 'marca'],
                    ['route' => 'admin.productos.almacenes', 'titulo' => 'Almacenes', 'desc' => 'Almacenes y su stock por ubicación.', 'icon' => 'almacen'],
                ],
            ],
            'Comercial' => [
                'icono' => 'maletin', 'color' => '#11704A',
                'sub' => 'Cajas del Punto de Venta y accesos del equipo',
                'items' => [
                    ['route' => 'admin.caja.index', 'titulo' => 'Cajas', 'desc' => 'Catálogo de cajas del Punto de Venta y su historial.', 'icon' => 'almacen'],
                    ['route' => 'admin.personal.index', 'titulo' => 'Personal', 'desc' => 'Altas, bajas y accesos al sistema del equipo.', 'icon' => 'personal'],
                    ['route' => 'admin.cargos.index', 'titulo' => 'Cargos', 'desc' => 'Lista de cargos disponibles al dar de alta a un colaborador.', 'icon' => 'categoria'],
                ],
            ],
        ];

        return view('admin.configuracion.index', ['secciones' => $secciones]);
    }

    /** Sub-hub dentro de "Mi Negocio": agrupa Mi Empresa, Mi Local y Pagos y Bancos. */
    public function datosEmpresa(): View
    {
        $items = [
            ['route' => 'admin.configuracion.empresa', 'titulo' => 'Mi Empresa', 'desc' => 'RUC, razón social, dirección y datos de contacto.', 'icon' => 'empresa'],
            ['route' => 'admin.locales.index', 'titulo' => 'Mi Local', 'desc' => 'Locales y establecimientos registrados ante SUNAT.', 'icon' => 'local'],
            ['route' => 'admin.configuracion.pagos', 'titulo' => 'Pagos y Bancos', 'desc' => 'Cuentas bancarias para que te paguen tus clientes.', 'icon' => 'pagos'],
        ];

        return view('admin.configuracion.datos-empresa', ['items' => $items]);
    }

    /** Tarjetas de "Mi Negocio" sin contenido todavía — ver self::PROXIMAMENTE. */
    public function proximamente(Request $request): View
    {
        $clave = $request->query('clave');

        abort_unless(isset(self::PROXIMAMENTE[$clave]), 404);

        return view('admin.configuracion.proximamente', self::PROXIMAMENTE[$clave] + ['clave' => $clave]);
    }

    /** Solo lectura por ahora — los datos de la empresa viven en config/rentaltech.php (.env). */
    public function empresa(): View
    {
        return view('admin.configuracion.empresa', [
            'empresa' => config('rentaltech.empresa'),
        ]);
    }

    public function pagos(): View
    {
        return view('admin.configuracion.pagos', [
            'cuentas' => CuentaBancaria::orderBy('id')->get(),
        ]);
    }

    public function storeCuenta(Request $request): RedirectResponse
    {
        CuentaBancaria::create($request->validate([
            'banco' => ['required', 'string', 'max:60'],
            'abrev' => ['required', 'string', 'max:10'],
            'moneda' => ['required', 'string', 'max:30'],
            'titular' => ['required', 'string', 'max:150'],
            'cuenta' => ['required', 'string', 'max:60'],
            'cci' => ['required', 'string', 'max:60'],
            'color' => ['required', 'string', 'max:9'],
            'bg' => ['required', 'string', 'max:9'],
        ]));

        return back()->with('mensaje', 'Cuenta bancaria registrada correctamente');
    }

    public function destroyCuenta(CuentaBancaria $cuenta): RedirectResponse
    {
        $cuenta->delete();

        return back()->with('mensaje', 'Cuenta bancaria eliminada correctamente');
    }
}
