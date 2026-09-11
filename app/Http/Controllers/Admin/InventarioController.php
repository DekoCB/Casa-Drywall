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

    /**
     * Stock actual, una fila por Producto × Almacén, con acciones rápidas
     * (Trasladar/Remover/Ajuste) por fila. El historial de movimientos en sí
     * vive en `historial()` — esta pantalla es la que reemplaza a esa como
     * entrada principal de "Movimientos" en el menú.
     */
    public function movimientos(Request $request): View
    {
        $almacenId = (int) $request->query('almacen_id', 0);
        $busqueda = (string) $request->query('q', '');

        return view('admin.inventario.movimientos', $this->centro->stockPorAlmacen(
            $almacenId > 0 ? $almacenId : null,
            $busqueda
        ) + [
            'almacenes' => Almacen::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }

    public function movimientosExcel(Request $request): Response
    {
        $almacenId = (int) $request->query('almacen_id', 0);

        return $this->respuestaExcel('Movimientos de Inventario', $this->centro->stockPorAlmacen(
            $almacenId > 0 ? $almacenId : null,
            (string) $request->query('q', '')
        ));
    }

    public function movimientosPdf(Request $request): Response
    {
        $almacenId = (int) $request->query('almacen_id', 0);
        $reporte = $this->centro->stockPorAlmacen($almacenId > 0 ? $almacenId : null, (string) $request->query('q', ''));

        return $this->respuestaPdf('Movimientos de Inventario', $reporte, [
            'Productos' => $reporte['resumen']['productos'],
            'Filas' => $reporte['resumen']['filas'],
            'Unidades' => $reporte['resumen']['unidades'],
        ]);
    }

    /** Historial de movimientos (`MovimientoAlmacen`): lo que antes vivía en `movimientos()`. */
    public function historial(Request $request): View
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

        return view('admin.inventario.historial', [
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
                    'estado' => 'en_curso',
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
                'estado' => 'en_curso',
            ]);

            $producto->recalcularStock();
        });

        return back()->with('mensaje', 'Devolución registrada.');
    }

    /** Marca un movimiento (y su pareja, si es traslado) como entregado: ya no admite editar la cantidad. */
    public function marcarEntregado(MovimientoAlmacen $movimiento): RedirectResponse
    {
        $this->filasDelMovimiento($movimiento)->each->update(['estado' => 'entregado']);

        return back()->with('mensaje', 'Movimiento marcado como entregado.');
    }

    /**
     * Corrige la cantidad de un movimiento todavía "en_curso", ajustando el
     * stock ACTUAL del almacén (no reconstruye el kardex histórico completo).
     * Un traslado corrige sus dos filas (origen y destino) juntas para no
     * desbalancear el stock entre almacenes. Un ajuste no se edita aquí: al
     * ser un valor absoluto (no un delta), corregirlo es registrar uno nuevo.
     */
    public function actualizarCantidad(Request $request, MovimientoAlmacen $movimiento): RedirectResponse
    {
        if ($movimiento->tipo === 'ajuste') {
            return back()->with('error', 'Un ajuste no se edita: registra uno nuevo con el valor correcto.');
        }

        if ($movimiento->estado !== 'en_curso') {
            return back()->with('error', 'Este movimiento ya fue entregado; no se puede editar la cantidad.');
        }

        $datos = $request->validate([
            'cantidad' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($datos, $movimiento) {
            foreach ($this->filasDelMovimiento($movimiento) as $fila) {
                $disminuye = in_array($fila->tipo, ['salida', 'devolucion'], true)
                    || ($fila->tipo === 'traslado' && $fila->stock_nuevo < $fila->stock_anterior);

                $stockFila = StockAlmacen::lockForUpdate()
                    ->where('producto_id', $fila->producto_id)
                    ->where('almacen_id', $fila->almacen_id)
                    ->firstOrFail();

                // Se retrocede el efecto viejo sobre el stock actual y se
                // aplica el nuevo, en vez de tocar solo `stock_nuevo` de la
                // fila (que puede haber quedado desactualizado por
                // movimientos posteriores).
                $stockSinEsteMovimiento = $stockFila->stock + ($disminuye ? $fila->cantidad : -$fila->cantidad);
                $nuevoStock = $stockSinEsteMovimiento + ($disminuye ? -$datos['cantidad'] : $datos['cantidad']);

                if ($nuevoStock < 0) {
                    throw ValidationException::withMessages([
                        'cantidad' => 'La nueva cantidad dejaría el stock de ese almacén en negativo.',
                    ]);
                }

                $stockFila->update(['stock' => $nuevoStock]);

                $fila->update([
                    'cantidad' => $datos['cantidad'],
                    'stock_anterior' => $stockSinEsteMovimiento,
                    'stock_nuevo' => $nuevoStock,
                ]);

                $fila->producto->recalcularStock();
            }
        });

        return back()->with('mensaje', 'Cantidad actualizada.');
    }

    /** El movimiento solo, o su pareja completa (origen+destino) si es un traslado. */
    private function filasDelMovimiento(MovimientoAlmacen $movimiento): \Illuminate\Support\Collection
    {
        if ($movimiento->tipo === 'traslado' && $movimiento->referencia) {
            return MovimientoAlmacen::where('referencia', $movimiento->referencia)
                ->where('tipo', 'traslado')
                ->get();
        }

        return collect([$movimiento]);
    }

    public function kardex(Request $request): View
    {
        return view('admin.inventario.kardex', $this->datosKardex($request));
    }

    public function kardexExcel(Request $request): Response
    {
        $reporte = $this->datosKardex($request);

        return $this->respuestaExcel('Kardex '.($reporte['producto']?->nombre ?? ''), $reporte);
    }

    public function kardexPdf(Request $request): Response
    {
        $reporte = $this->datosKardex($request);

        return $this->respuestaPdf('Kardex — '.($reporte['producto']?->nombre ?? 'Producto'), $reporte, [
            'Movimientos' => $reporte['resumen']['movimientos'],
            'Stock actual' => $reporte['resumen']['stock_actual'],
        ]);
    }

    /** Kardex Valorizado: una fila por producto (código, costo, stock, valor), no el historial de uno solo. */
    public function kardexValorizado(Request $request): View
    {
        return view('admin.inventario.kardex-valorizado', $this->datosKardexValorizado($request) + [
            'categorias' => Categoria::orderBy('nombre')->get(['id', 'nombre']),
            'marcas' => Marca::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function kardexValorizadoExcel(Request $request): Response
    {
        return $this->respuestaExcel('Kardex Valorizado', $this->datosKardexValorizado($request));
    }

    public function kardexValorizadoPdf(Request $request): Response
    {
        $reporte = $this->datosKardexValorizado($request);

        return $this->respuestaPdf('Kardex Valorizado', $reporte, [
            'Productos' => $reporte['resumen']['productos'],
            'Unidades' => $reporte['resumen']['unidades'],
            'Valor total' => 'S/ '.number_format($reporte['resumen']['valor_total'], 2),
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
            'Utilidad potencial' => 'S/ '.number_format($reporte['resumen']['utilidad_potencial'], 2),
        ]);
    }

    /** @return array{producto: ?Producto, filtros: array, resumen: array, items: \Illuminate\Support\Collection, columnas: array, filas: array} */
    private function datosKardex(Request $request): array
    {
        $productoId = (int) $request->query('producto_id', 0);
        $producto = $productoId > 0 ? Producto::find($productoId) : null;

        if (! $producto) {
            return [
                'producto' => null,
                'filtros' => ['desde' => $request->query('desde'), 'hasta' => $request->query('hasta')],
                'resumen' => ['movimientos' => 0, 'stock_actual' => 0],
                'items' => collect(),
                'columnas' => [],
                'filas' => [],
            ];
        }

        return $this->centro->kardex($producto, $request->query('desde'), $request->query('hasta'));
    }

    private function datosKardexValorizado(Request $request): array
    {
        $categoria = $request->query('categoria');
        $marca = $request->query('marca');

        return $this->centro->kardexValorizadoTodos(
            $categoria ? (int) $categoria : null,
            $marca ? (int) $marca : null,
            (string) $request->query('q', '')
        );
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
