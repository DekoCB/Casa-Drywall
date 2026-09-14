<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CuentaBancaria;
use App\Models\EstiloSistema;
use App\Models\PerfilNegocio;
use App\Services\ApiGoCompanies;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
        'plantillas-pdf' => ['seccion' => 'Plantillas de Impresión', 'titulo' => 'Plantillas PDF', 'desc' => 'Diseño de tus facturas y boletas en formato PDF.', 'icon' => 'documento'],
        'tickets-venta' => ['seccion' => 'Plantillas de Impresión', 'titulo' => 'Tickets de venta', 'desc' => 'Diseño de tu ticket de venta en formato 80mm.', 'icon' => 'documento'],
    ];

    /**
     * Accesos rápidos de "Configuración avanzada": solo las áreas que ya
     * existen de verdad en el sistema (ninguna es una página nueva — cada
     * una abre la sección real donde ya vive esa función). Se dejaron
     * afuera "Servicios", "Visual", "S.Puntos" y "Farmacia" porque no
     * tienen ninguna base real en el código (no hay sistema de puntos ni
     * rubro farmacia en Casa Drywall), y "Datos" por ser demasiado
     * angosto (solo existe "importar productos").
     */
    private const AVANZADA = [
        'Principal' => [
            ['route' => 'admin.ordenes-compra.index', 'titulo' => 'Compras', 'desc' => 'Órdenes de compra, proveedores y activos fijos.', 'icon' => 'bolsa'],
        ],
        'Documentos' => [
            ['route' => 'admin.historial-pagos.index', 'titulo' => 'Contable', 'desc' => 'Historial de pagos y cobranzas registradas.', 'icon' => 'documento'],
            ['route' => 'admin.configuracion.proximamente', 'query' => ['clave' => 'plantillas-pdf'], 'titulo' => 'PDF y Tickets', 'desc' => 'Diseño de facturas, boletas y tickets de venta.', 'icon' => 'impresora'],
        ],
        'Ventas' => [
            ['route' => 'admin.pedidos.index', 'titulo' => 'Pedidos', 'desc' => 'Pedidos de clientes y su seguimiento.', 'icon' => 'carrito'],
            ['route' => 'admin.pos.index', 'titulo' => 'POS', 'desc' => 'Punto de Venta y cajas.', 'icon' => 'pos'],
        ],
        'Finanzas' => [
            ['route' => 'admin.cobranzas.index', 'titulo' => 'Finanzas', 'desc' => 'Cobranzas, ingresos y egresos.', 'icon' => 'dinero'],
        ],
        'Sistema' => [
            ['route' => 'admin.index', 'titulo' => 'Dashboard', 'desc' => 'Resumen general del negocio.', 'icon' => 'dashboard'],
            ['route' => 'admin.personal.index', 'titulo' => 'Usuario', 'desc' => 'Altas, bajas y accesos del equipo.', 'icon' => 'usuarios'],
            ['route' => 'admin.inventario.movimientos', 'titulo' => 'Inventario', 'desc' => 'Movimientos, kardex y almacenes.', 'icon' => 'inventario'],
        ],
        'Módulos' => [
            ['route' => 'admin.transporte.index', 'titulo' => 'Envíos', 'desc' => 'Empresas de transporte y tarifas.', 'icon' => 'camion'],
        ],
    ];

    public function index(): View
    {
        $secciones = [
            'Mi Negocio' => [
                'icono' => 'empresa', 'color' => '#7c3aed',
                'sub' => 'Información de tu empresa, apariencia y canales de venta',
                'items' => array_merge(
                    [
                        ['route' => 'admin.configuracion.datos-empresa', 'titulo' => 'Datos de la Empresa', 'desc' => 'Mi Empresa, Mi Local y Pagos y Bancos.', 'icon' => 'empresa'],
                        ['route' => 'admin.configuracion.credenciales', 'titulo' => 'Credenciales y certificados', 'desc' => 'Usuario y certificado SOL, credenciales de Guías Electrónicas.', 'icon' => 'llave'],
                        ['route' => 'admin.configuracion.estilos', 'titulo' => 'Estilos y temas', 'desc' => 'Color de acento y tipografía del sistema.', 'icon' => 'paleta'],
                        ['route' => 'admin.configuracion.avanzada', 'titulo' => 'Configuración avanzada', 'desc' => 'Acceso rápido a compras, ventas, finanzas e inventario.', 'icon' => 'controles'],
                    ],
                    $this->itemsProximamente('Mi Negocio'),
                ),
            ],
            'Plantillas de Impresión' => [
                'icono' => 'impresora', 'color' => '#C2410C',
                'sub' => 'Diseño de tus facturas, boletas y tickets de venta',
                'items' => $this->itemsProximamente('Plantillas de Impresión'),
            ],
            'Finanzas y Pagos' => [
                'icono' => 'banco', 'color' => '#0F766E',
                'sub' => 'Bancos, monedas, métodos de pago e ingresos y egresos',
                'items' => [
                    ['route' => 'admin.finanzas.bancos.index', 'titulo' => 'Bancos', 'desc' => 'Entidades bancarias disponibles.', 'icon' => 'banco'],
                    ['route' => 'admin.configuracion.pagos', 'titulo' => 'Cuentas bancarias', 'desc' => 'Cuentas donde recibes pagos de clientes.', 'icon' => 'pagos'],
                    ['route' => 'admin.finanzas.monedas.index', 'titulo' => 'Monedas', 'desc' => 'Soles, dólares y otras divisas habilitadas.', 'icon' => 'moneda'],
                    ['route' => 'admin.finanzas.tarjetas.index', 'titulo' => 'Tarjetas', 'desc' => 'Tipos de tarjeta aceptadas como medio de pago.', 'icon' => 'tarjeta'],
                    ['route' => 'admin.finanzas.plataformas.index', 'titulo' => 'Plataformas', 'desc' => 'Canales de venta y plataformas de cobro.', 'icon' => 'globo'],
                    ['route' => 'admin.finanzas.metodos-pago.index', 'titulo' => 'Métodos de pago', 'desc' => 'Formas de pago para ingresos y gastos.', 'icon' => 'billete'],
                ],
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

    /**
     * Solo lectura — trae la empresa real desde API-GO (el servicio que
     * factura ante SUNAT de verdad). Ningún valor sensible sale de
     * ApiGoCompanies::obtener() en texto plano, solo si está configurado.
     */
    public function credenciales(ApiGoCompanies $apiGo): View
    {
        return view('admin.configuracion.credenciales', [
            'empresa' => $apiGo->obtener((int) config('services.api_go.company_id')),
        ]);
    }

    /** Color de acento y tipografía de todo el sistema — ver App\Models\EstiloSistema. */
    public function estilos(): View
    {
        return view('admin.configuracion.estilos', [
            'estilo' => EstiloSistema::actual(),
        ]);
    }

    public function storeEstilos(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'color' => ['required', Rule::in(array_keys(EstiloSistema::PALETAS))],
            'fuente' => ['required', Rule::in(array_keys(EstiloSistema::FUENTES))],
        ]);

        EstiloSistema::updateOrCreate(['id' => 1], $datos);

        return back()->with('mensaje', 'Estilos actualizados correctamente');
    }

    /** Buscador de accesos rápidos a áreas del sistema que ya existen — ver self::AVANZADA. */
    public function avanzada(): View
    {
        return view('admin.configuracion.avanzada', ['secciones' => self::AVANZADA]);
    }

    /** Tarjetas de self::PROXIMAMENTE que pertenecen a una sección dada, en formato de item de grilla. */
    private function itemsProximamente(string $seccion): array
    {
        return collect(self::PROXIMAMENTE)
            ->filter(fn ($item) => $item['seccion'] === $seccion)
            ->map(fn ($item, $clave) => [
                'route' => 'admin.configuracion.proximamente',
                'query' => ['clave' => $clave],
                'titulo' => $item['titulo'],
                'desc' => $item['desc'],
                'icon' => $item['icon'],
            ])
            ->values()
            ->all();
    }

    /** Tarjetas sin contenido todavía — ver self::PROXIMAMENTE. */
    public function proximamente(Request $request): View
    {
        $clave = $request->query('clave');

        abort_unless(isset(self::PROXIMAMENTE[$clave]), 404);

        return view('admin.configuracion.proximamente', self::PROXIMAMENTE[$clave] + ['clave' => $clave]);
    }

    /**
     * RUC/dirección/teléfono/correo siguen viniendo de config/rentaltech.php
     * (.env) — no se editan acá, igual que el RUC en la imagen de
     * referencia. Razón social, nombre comercial, título web y logos sí
     * son editables — ver App\Models\PerfilNegocio.
     */
    public function empresa(): View
    {
        return view('admin.configuracion.empresa', [
            'perfil' => PerfilNegocio::actual(),
            'empresa' => config('rentaltech.empresa'),
        ]);
    }

    public function storeEmpresa(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'razon_social' => ['required', 'string', 'max:150'],
            'nombre_comercial' => ['required', 'string', 'max:150'],
            'titulo_web' => ['nullable', 'string', 'max:60'],
            'logo_claro' => ['nullable', 'image', 'max:2048'],
            'logo_oscuro' => ['nullable', 'image', 'max:2048'],
            'favicon' => ['nullable', 'image', 'max:512'],
            'logo_app' => ['nullable', 'image', 'max:2048'],
        ]);

        $perfil = PerfilNegocio::actual();

        foreach (['logo_claro', 'logo_oscuro', 'favicon', 'logo_app'] as $campo) {
            if (! $request->hasFile($campo)) {
                unset($datos[$campo]);

                continue;
            }

            if ($perfil->$campo) {
                Storage::disk('public')->delete($perfil->$campo);
            }

            $datos[$campo] = $request->file($campo)->store('empresa', 'public');
        }

        PerfilNegocio::updateOrCreate(['id' => 1], $datos);

        return back()->with('mensaje', 'Datos de la empresa actualizados correctamente');
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
