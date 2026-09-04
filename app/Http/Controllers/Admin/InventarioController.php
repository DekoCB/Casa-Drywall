<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\MovimientoAlmacen;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\StockAlmacen;
use App\Services\CentroInventario;
use App\Services\ExportadorReportes;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Módulo Inventario: historial de movimientos (`movimientos_almacen`,
 * hasta ahora escrito por POS/ajuste de stock pero nunca listado),
 * traslados entre almacenes, devoluciones a proveedor, y los reportes
 * Kardex/Kardex valorizado/Inventario.
 */
class InventarioController extends Controller
{
    public function __construct(
        private readonly CentroInventario $centro,
        private readonly ExportadorReportes $exportador,
    ) {}

    public function movimientos(Request $request): View
    {
        $filtros = [
            'tipo' => (string) $request->query('tipo', ''),
            'producto_id' => (int) $request->query('producto_id', 0),
            'almacen_id' => (int) $request->query('almacen_id', 0),
            'desde' => (string) $request->query('desde', ''),
            'hasta' => (string) $request->query('hasta', ''),
            'q' => trim((string) $request->query('q', '')),
        ];

        $movimientos = MovimientoAlmacen::query()
            ->with(['producto:id,codigo,nombre', 'almacen:id,nombre', 'usuario:id,username'])
            ->when($filtros['tipo'] !== '', fn ($q) => $q->where('tipo', $filtros['tipo']))
            ->when($filtros['producto_id'] > 0, fn ($q) => $q->where('producto_id', $filtros['producto_id']))
            ->when($filtros['almacen_id'] > 0, fn ($q) => $q->where('almacen_id', $filtros['almacen_id']))
            ->when($filtros['desde'] !== '', fn ($q) => $q->whereDate('created_at', '>=', $filtros['desde']))
            ->when($filtros['hasta'] !== '', fn ($q) => $q->whereDate('created_at', '<=', $filtros['hasta']))
            ->when($filtros['q'] !== '', fn ($q) => $q->where(
                fn ($qq) => $qq->where('motivo', 'like', "%{$filtros['q']}%")->orWhere('referencia', 'like', "%{$filtros['q']}%")
            ))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.inventario.movimientos', [
            'movimientos' => $movimientos,
            'filtros' => $filtros,
            'almacenes' => Almacen::where('activo', true)->orderBy('nombre')->get(),
            'proveedores' => Proveedor::orderBy('razon_social')->get(['id', 'razon_social']),
        ]);
    }

    /** Traslada stock de un almacén a otro: dos movimientos enlazados por `referencia`. */
    public function storeTraslado(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'almacen_origen_id' => ['required', 'integer', 'exists:almacenes,id'],
            'almacen_destino_id' => ['required', 'integer', 'exists:almacenes,id', 'different:almacen_origen_id'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($request, $datos) {
            $producto = Producto::findOrFail($datos['producto_id']);

            $origen = StockAlmacen::lockForUpdate()->firstOrCreate(
                ['producto_id' => $producto->id, 'almacen_id' => $datos['almacen_origen_id']],
                ['stock' => 0]
            );

            if ($origen->stock < $datos['cantidad']) {
                throw ValidationException::withMessages([
                    'cantidad' => "Stock insuficiente en el almacén de origen ({$origen->stock} disponible).",
                ]);
            }

            $destino = StockAlmacen::lockForUpdate()->firstOrCreate(
                ['producto_id' => $producto->id, 'almacen_id' => $datos['almacen_destino_id']],
                ['stock' => 0]
            );

            $anteriorOrigen = $origen->stock;
            $nuevoOrigen = $anteriorOrigen - $datos['cantidad'];
            $anteriorDestino = $destino->stock;
            $nuevoDestino = $anteriorDestino + $datos['cantidad'];

            $origen->update(['stock' => $nuevoOrigen]);
            $destino->update(['stock' => $nuevoDestino]);

            $referencia = 'TRASLADO-'.now()->format('YmdHis').'-'.$producto->id;

            foreach ([
                [$datos['almacen_origen_id'], $anteriorOrigen, $nuevoOrigen],
                [$datos['almacen_destino_id'], $anteriorDestino, $nuevoDestino],
            ] as [$almacenId, $anterior, $nuevo]) {
                MovimientoAlmacen::create([
                    'producto_id' => $producto->id,
                    'almacen_id' => $almacenId,
                    'tipo' => 'traslado',
                    'cantidad' => $datos['cantidad'],
                    'stock_anterior' => $anterior,
                    'stock_nuevo' => $nuevo,
                    'motivo' => $datos['motivo'] ?? null,
                    'referencia' => $referencia,
                    'usuario_id' => $request->user()->id,
                ]);
            }

            $producto->recalcularStock();
        });

        return back()->with('mensaje', 'Traslado registrado.');
    }

    /** Devuelve mercadería a un proveedor: descuenta stock, sin ligarse a una Orden de Compra concreta. */
    public function storeDevolucion(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'almacen_id' => ['required', 'integer', 'exists:almacenes,id'],
            'proveedor_id' => ['nullable', 'integer', 'exists:proveedores,id'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($request, $datos) {
            $producto = Producto::findOrFail($datos['producto_id']);

            $fila = StockAlmacen::lockForUpdate()->firstOrCreate(
                ['producto_id' => $producto->id, 'almacen_id' => $datos['almacen_id']],
                ['stock' => 0]
            );

            if ($fila->stock < $datos['cantidad']) {
                throw ValidationException::withMessages([
                    'cantidad' => "Stock insuficiente en ese almacén ({$fila->stock} disponible).",
                ]);
            }

            $anterior = $fila->stock;
            $nuevo = $anterior - $datos['cantidad'];
            $fila->update(['stock' => $nuevo]);

            $proveedor = ! empty($datos['proveedor_id']) ? Proveedor::find($datos['proveedor_id']) : null;

            MovimientoAlmacen::create([
                'producto_id' => $producto->id,
                'almacen_id' => $datos['almacen_id'],
                'tipo' => 'devolucion',
                'cantidad' => $datos['cantidad'],
                'stock_anterior' => $anterior,
                'stock_nuevo' => $nuevo,
                'motivo' => $datos['motivo'] ?? null,
                'referencia' => $proveedor ? "Proveedor: {$proveedor->razon_social}" : null,
                'usuario_id' => $request->user()->id,
            ]);

            $producto->recalcularStock();
        });

        return back()->with('mensaje', 'Devolución registrada.');
    }

    public function kardex(Request $request): View
    {
        return view('admin.inventario.kardex', $this->datosKardex($request, false) + ['valorizado' => false]);
    }

    public function kardexExcel(Request $request): Response
    {
        $reporte = $this->datosKardex($request, false);

        return $this->respuestaExcel('Kardex '.($reporte['producto']?->nombre ?? ''), $reporte);
    }

    public function kardexPdf(Request $request): Response
    {
        $reporte = $this->datosKardex($request, false);

        return $this->respuestaPdf('Kardex — '.($reporte['producto']?->nombre ?? 'Producto'), $reporte, [
            'Movimientos' => $reporte['resumen']['movimientos'],
            'Stock actual' => $reporte['resumen']['stock_actual'],
        ]);
    }

    public function kardexValorizado(Request $request): View
    {
        return view('admin.inventario.kardex', $this->datosKardex($request, true) + ['valorizado' => true]);
    }

    public function kardexValorizadoExcel(Request $request): Response
    {
        $reporte = $this->datosKardex($request, true);

        return $this->respuestaExcel('Kardex Valorizado '.($reporte['producto']?->nombre ?? ''), $reporte);
    }

    public function kardexValorizadoPdf(Request $request): Response
    {
        $reporte = $this->datosKardex($request, true);

        return $this->respuestaPdf('Kardex Valorizado — '.($reporte['producto']?->nombre ?? 'Producto'), $reporte, [
            'Movimientos' => $reporte['resumen']['movimientos'],
            'Stock actual' => $reporte['resumen']['stock_actual'],
            'Valor actual' => 'S/ '.number_format((float) $reporte['resumen']['valor_actual'], 2),
        ]);
    }

    public function reporte(Request $request): View
    {
        return view('admin.inventario.reporte', $this->datosReporte($request) + [
            'categorias' => Categoria::orderBy('nombre')->get(['id', 'nombre']),
            'marcas' => Marca::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function reporteExcel(Request $request): Response
    {
        return $this->respuestaExcel('Reporte de Inventario', $this->datosReporte($request));
    }

    public function reportePdf(Request $request): Response
    {
        $reporte = $this->datosReporte($request);

        return $this->respuestaPdf('Reporte de Inventario', $reporte, [
            'Productos' => $reporte['resumen']['productos'],
            'Unidades' => $reporte['resumen']['unidades'],
            'Valor total' => 'S/ '.number_format($reporte['resumen']['valor_total'], 2),
        ]);
    }

    /** @return array{producto: ?Producto, filtros: array, resumen: array, items: \Illuminate\Support\Collection, columnas: array, filas: array} */
    private function datosKardex(Request $request, bool $valorizado): array
    {
        $productoId = (int) $request->query('producto_id', 0);
        $producto = $productoId > 0 ? Producto::find($productoId) : null;

        if (! $producto) {
            return [
                'producto' => null,
                'filtros' => ['desde' => $request->query('desde'), 'hasta' => $request->query('hasta')],
                'resumen' => ['movimientos' => 0, 'stock_actual' => 0, 'valor_actual' => 0],
                'items' => collect(),
                'columnas' => [],
                'filas' => [],
            ];
        }

        return $this->centro->kardex($producto, $request->query('desde'), $request->query('hasta'), $valorizado);
    }

    private function datosReporte(Request $request): array
    {
        $categoria = $request->query('categoria');
        $marca = $request->query('marca');

        return $this->centro->reporteInventario(
            $categoria ? (int) $categoria : null,
            $marca ? (int) $marca : null,
            (string) $request->query('q', '')
        );
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
