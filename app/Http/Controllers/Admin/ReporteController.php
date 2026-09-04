<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CentroReportes;
use App\Services\ExportadorReportes;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Centro de Reportes: un hub de accesos por área (Ventas/Compras/General)
 * más los 3 reportes "avanzados" con datos propios (Análisis ABC, Rotación
 * de Inventario, Aging de Cuentas por Cobrar). Solo se listan aquí — como
 * tarjetas o como reportes — cosas con datos reales detrás; no hay tarjetas
 * "próximamente".
 */
class ReporteController extends Controller
{
    public function __construct(
        private readonly CentroReportes $centro,
        private readonly ExportadorReportes $exportador,
    ) {}

    public function index(): View
    {
        $areas = [
            'Ventas' => [
                ['route' => 'admin.ventas.index', 'titulo' => 'Comprobantes', 'desc' => 'Boletas, facturas y notas de venta emitidas.', 'icon' => 'documento'],
                ['route' => 'admin.ventas.index', 'query' => ['tipcomp' => 'COT'], 'titulo' => 'Cotizaciones', 'desc' => 'Historial y seguimiento de cotizaciones.', 'icon' => 'cotizacion'],
                ['route' => 'admin.ventas.index', 'query' => ['tipcomp' => 'NV'], 'titulo' => 'Notas de Venta', 'desc' => 'Notas de venta emitidas, sin comprobante SUNAT.', 'icon' => 'documento'],
                ['route' => 'admin.clientes.index', 'titulo' => 'Clientes', 'desc' => 'Análisis y detalle de tu cartera de clientes.', 'icon' => 'cliente'],
                ['route' => 'admin.reportes.abc', 'titulo' => 'Análisis ABC', 'desc' => 'Productos más vendidos, clasificados A/B/C por ingreso.', 'icon' => 'abc', 'destacado' => true],
            ],
            'Compras' => [
                ['route' => 'admin.ordenes-compra.index', 'titulo' => 'Órdenes de Compra', 'desc' => 'Resumen de todas las compras realizadas.', 'icon' => 'compra'],
            ],
            'General' => [
                ['route' => 'admin.reportes.rotacion', 'titulo' => 'Rotación de Inventario', 'desc' => 'Detecta productos estancados y de alta rotación.', 'icon' => 'rotacion', 'destacado' => true],
                ['route' => 'admin.reportes.aging', 'titulo' => 'Cuentas por Cobrar', 'desc' => 'Saldos pendientes por cliente, en tramos de 30 días.', 'icon' => 'aging', 'destacado' => true],
                ['route' => 'admin.pedidos.index', 'titulo' => 'Pedidos', 'desc' => 'Reporte general de pedidos de clientes.', 'icon' => 'pedido'],
                ['route' => 'admin.guias.index', 'titulo' => 'Guías de Remisión', 'desc' => 'Consolidado de ítems trasladados en guías.', 'icon' => 'guia'],
            ],
        ];

        return view('admin.reportes.index', ['areas' => $areas]);
    }

    public function abc(Request $request): View
    {
        $reporte = $this->centro->analisisAbc(
            $request->query('desde'),
            $request->query('hasta'),
            (string) $request->query('q', '')
        );

        return view('admin.reportes.abc', $reporte);
    }

    public function abcExcel(Request $request): Response
    {
        $reporte = $this->centro->analisisAbc($request->query('desde'), $request->query('hasta'), (string) $request->query('q', ''));

        return $this->respuestaExcel('Analisis ABC', $reporte);
    }

    public function abcPdf(Request $request): Response
    {
        $reporte = $this->centro->analisisAbc($request->query('desde'), $request->query('hasta'), (string) $request->query('q', ''));

        return $this->respuestaPdf('Análisis ABC de Productos', $reporte, [
            'A — '.$reporte['resumen']['A']['n'].' productos' => $reporte['resumen']['A']['etiqueta'],
            'B — '.$reporte['resumen']['B']['n'].' productos' => $reporte['resumen']['B']['etiqueta'],
            'C — '.$reporte['resumen']['C']['n'].' productos' => $reporte['resumen']['C']['etiqueta'],
            'Total' => 'S/ '.number_format($reporte['resumen']['total'], 2),
        ]);
    }

    public function rotacion(Request $request): View
    {
        $reporte = $this->centro->rotacionInventario($request->query('desde'), $request->query('hasta'));

        return view('admin.reportes.rotacion', $reporte);
    }

    public function rotacionExcel(Request $request): Response
    {
        $reporte = $this->centro->rotacionInventario($request->query('desde'), $request->query('hasta'));

        return $this->respuestaExcel('Rotacion de Inventario', $reporte);
    }

    public function rotacionPdf(Request $request): Response
    {
        $reporte = $this->centro->rotacionInventario($request->query('desde'), $request->query('hasta'));

        return $this->respuestaPdf('Rotación de Inventario', $reporte, [
            'Baja rotación' => $reporte['resumen']['baja'],
            'Media' => $reporte['resumen']['media'],
            'Alta' => $reporte['resumen']['alta'],
            'Total productos' => $reporte['resumen']['total'],
        ]);
    }

    public function aging(): View
    {
        return view('admin.reportes.aging', $this->centro->agingCuentasPorCobrar());
    }

    public function agingExcel(): Response
    {
        return $this->respuestaExcel('Aging de Cuentas por Cobrar', $this->centro->agingCuentasPorCobrar());
    }

    public function agingPdf(): Response
    {
        $reporte = $this->centro->agingCuentasPorCobrar();

        return $this->respuestaPdf('Aging de Cuentas por Cobrar', $reporte, [
            'Clientes con saldo' => $reporte['resumen']['clientes'],
            'Total pendiente' => 'S/ '.number_format($reporte['resumen']['total'], 2),
            'Vencido +90 días' => 'S/ '.number_format($reporte['resumen']['vencido90'], 2),
        ]);
    }

    private function respuestaExcel(string $titulo, array $reporte): Response
    {
        $contenido = $this->exportador->excel($titulo, $reporte['columnas'], $reporte['filas']);
        $archivo = $titulo.' '.now()->format('Ymd').'.xlsx';

        return response($contenido, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$archivo.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    private function respuestaPdf(string $titulo, array $reporte, array $resumenPlano): Response
    {
        $archivo = $titulo.' '.now()->format('Ymd').'.pdf';

        return Pdf::loadView('admin.reportes.pdf.tabla', [
            'titulo' => $titulo,
            'resumen' => $resumenPlano,
            'columnas' => $reporte['columnas'],
            'filas' => $reporte['filas'],
        ])->setPaper('a4', 'landscape')->download($archivo);
    }
}
