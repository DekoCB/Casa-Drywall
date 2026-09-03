<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\MovimientoAlmacen;
use App\Models\Producto;
use App\Models\StockAlmacen;
use App\Services\LectorExcel;
use App\Services\MatrizGalonaje;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Catálogo de productos. La sección se navega por pestañas: el inventario
 * (esta clase), las categorías y presentaciones de la matriz Kendall y los
 * almacenes. Las tres últimas viven en métodos aparte para que compartan
 * cabecera y estilo con el listado.
 */
class ProductoController extends Controller
{
    /** Mismo tope que el listado original, antes de deduplicar por código. */
    private const TOPE_LISTADO = 500;

    public function index(Request $request, MatrizGalonaje $matriz): View
    {
        $busqueda  = trim((string) $request->query('q', ''));
        $categoria = $request->query('categoria');
        $marca     = $request->query('marca');

        $almacenes  = Almacen::where('activo', true)->orderBy('id')->get();
        $almacenSel = $this->almacenSeleccionado($request, $almacenes);

        $productos = $this->listado($busqueda, $categoria, $marca, $almacenSel);
        $periodo   = $this->periodoVentas($request);

        return view('admin.productos.index', [
            'productos'       => $productos,
            'busqueda'        => $busqueda,
            'categoriaSel'    => $categoria,
            'marcaSel'        => $marca,
            'categorias'      => Categoria::orderBy('nombre')->get(),
            'marcas'          => Marca::orderBy('nombre')->get(),
            'almacenes'       => $almacenes,
            'almacenSel'      => $almacenSel,
            'resumenAlmacen'  => $this->resumenPorAlmacen(),
            'bajosAlmacen'    => $this->stockBajoPorAlmacen(),
            // Las tarjetas describen lo que se está listando, como en el original.
            'totalProductos'  => $productos->count(),
            'stockAqui'       => (int) $productos->sum('stock_almacen'),
            'stockBajo'       => $productos->filter(fn ($p) => $p->stock_almacen <= $p->stock_minimo)->count(),
            'valorInventario' => (float) $productos->sum(fn ($p) => $p->stock_almacen * (float) $p->precio_venta),
            'factores'        => $matriz->productos(),
            'presentaciones'  => $matriz->presentaciones(),
            'lineas'          => $this->lineasMatriz($matriz),
            'vendidos'        => $this->vendidosPorProducto($periodo),
            'periodo'         => $periodo,
        ]);
    }

    // ── Pestañas ────────────────────────────────────────────────────────────

    /** Categorías de la matriz: las declaradas más las líneas ya en uso. */
    public function categorias(MatrizGalonaje $matriz): View
    {
        $enMatriz  = $matriz->productos();
        $definidas = $matriz->categorias();

        $conteo = [];

        foreach ($enMatriz as $datos) {
            $linea = trim((string) ($datos['l'] ?? ''));

            if ($linea !== '') {
                $conteo[$linea] = ($conteo[$linea] ?? 0) + 1;
            }
        }

        $categorias = collect(array_keys($conteo + $definidas))
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->map(fn (string $codigo) => (object) [
                'codigo'      => $codigo,
                'descripcion' => $definidas[$codigo]['descripcion'] ?? '',
                'productos'   => $conteo[$codigo] ?? 0,
            ])
            ->values();

        return view('admin.productos.categorias', [
            'categorias'    => $categorias,
            'totalMatriz'   => count($enMatriz),
            'sinProductos'  => $categorias->where('productos', 0)->count(),
        ]);
    }

    /** Presentaciones de la matriz con su factor de galones por unidad. */
    public function presentaciones(MatrizGalonaje $matriz): View
    {
        $enMatriz = $matriz->productos();

        $conteo = [];

        foreach ($enMatriz as $datos) {
            $codigo = trim((string) ($datos['p'] ?? ''));

            if ($codigo !== '') {
                $conteo[$codigo] = ($conteo[$codigo] ?? 0) + 1;
            }
        }

        $presentaciones = collect($matriz->presentaciones())
            ->map(fn (array $datos, string $codigo) => (object) [
                'codigo'      => $codigo,
                'descripcion' => $datos['descripcion'] ?? ($datos['d'] ?? ''),
                // El archivo del original guarda el factor bajo la clave `gl`.
                'factor'      => (float) ($datos['gl'] ?? $datos['factor'] ?? $datos['f'] ?? 0),
                'productos'   => $conteo[$codigo] ?? 0,
            ])
            ->sortBy('codigo', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return view('admin.productos.presentaciones', [
            'presentaciones' => $presentaciones,
            'enUso'          => $presentaciones->where('productos', '>', 0)->count(),
            'totalMatriz'    => count($enMatriz),
        ]);
    }

    /** Almacenes con sus unidades, productos y avisos de stock bajo. */
    public function almacenes(): View
    {
        $almacenes = Almacen::orderBy('id')->get();
        $resumen   = $this->resumenPorAlmacen();
        $bajos     = $this->stockBajoPorAlmacen();
        $valores   = $this->valorPorAlmacen();

        return view('admin.productos.almacenes', [
            'almacenes'  => $almacenes,
            'resumen'    => $resumen,
            'bajos'      => $bajos,
            'activos'    => $almacenes->where('activo', true)->count(),
            'unidades'   => (int) collect($resumen)->sum('unidades'),
            'valorTotal' => (float) collect($valores)->sum(),
        ]);
    }

    // ── Importación desde Excel ─────────────────────────────────────────────

    /** Alias de encabezado reconocidos por campo (comparados en mayúsculas, sin tildes). */
    private const ALIAS_COLUMNAS = [
        'codigo'        => ['CODIGO', 'COD. INTERNO', 'COD INTERNO', 'COD'],
        'nombre'        => ['NOMBRE', 'PRODUCTO', 'DESCRIPCION'],
        'precio_compra' => ['COSTO', 'PRECIO DE COMPRA', 'PRECIO COMPRA'],
        'precio_venta'  => ['PRECIO DE VENTA', 'PRECIO VENTA', 'PRECIO'],
        'stock'         => ['STOCK ACTUAL', 'STOCK'],
        'stock_minimo'  => ['STOCK MINIMO', 'STOCK MIN'],
    ];

    public function formImportar(): View
    {
        return view('admin.productos.importar', [
            'almacenes' => Almacen::where('activo', true)->orderBy('id')->get(),
        ]);
    }

    /**
     * Carga masiva de productos desde un .xlsx. Las columnas se identifican
     * por el texto de su encabezado (no por posición fija), porque los
     * inventarios reales del negocio no siempre traen las mismas columnas en
     * el mismo orden. Si el código de un producto ya existe se actualiza
     * (precio y stock); si no, se crea nuevo — nunca se empareja por nombre,
     * solo por código, para no arriesgar mezclar productos parecidos pero
     * distintos (ej. tornillos de tamaños distintos).
     */
    public function importar(Request $request, LectorExcel $lector): RedirectResponse
    {
        $datos = $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx'],
            'almacen_id' => ['required', 'integer', 'exists:almacenes,id'],
        ]);

        $filas = $lector->leer($request->file('archivo')->getRealPath());

        if (isset($filas['error'])) {
            return back()->with('error', $filas['error']);
        }

        if (empty($filas)) {
            return back()->with('error', 'El archivo no tiene filas.');
        }

        $indices = $this->indicesPorEncabezado($filas[0] ?? []);

        if (! isset($indices['nombre'])) {
            return back()->with('error', 'No se encontró una columna de nombre reconocible (ej. "Nombre", "Producto") en la primera fila del Excel.');
        }

        $nuevos = 0;
        $actualizados = 0;
        $omitidos = 0;

        foreach (array_slice($filas, 1) as $fila) {
            $nombre = trim((string) ($fila[$indices['nombre']] ?? ''));

            if ($nombre === '') {
                $omitidos++;

                continue;
            }

            $codigo = trim((string) ($fila[$indices['codigo']] ?? ''));
            $precioCompra = (float) str_replace(',', '.', (string) ($fila[$indices['precio_compra']] ?? 0));
            $precioVenta = (float) str_replace(',', '.', (string) ($fila[$indices['precio_venta']] ?? 0));
            $stockMinimo = isset($indices['stock_minimo'])
                ? (int) ($fila[$indices['stock_minimo']] ?? 0)
                : 0;

            $producto = $codigo !== '' ? Producto::where('codigo', $codigo)->first() : null;

            if ($producto) {
                $producto->update([
                    'nombre' => $nombre,
                    'precio_compra' => $precioCompra,
                    'precio_venta' => $precioVenta,
                    'stock_minimo' => $stockMinimo,
                ]);
                $actualizados++;
            } else {
                $producto = Producto::create([
                    'codigo' => $codigo !== '' ? $codigo : null,
                    'nombre' => $nombre,
                    'categoria_id' => null,
                    'marca_id' => null,
                    'precio_compra' => $precioCompra,
                    'precio_venta' => $precioVenta,
                    'stock_minimo' => $stockMinimo,
                    'stock' => 0,
                    'estado' => 'activo',
                ]);
                $nuevos++;
            }

            if (isset($indices['stock'])) {
                $stockActual = (int) ($fila[$indices['stock']] ?? 0);

                StockAlmacen::updateOrCreate(
                    ['producto_id' => $producto->id, 'almacen_id' => $datos['almacen_id']],
                    ['stock' => $stockActual]
                );
                $producto->recalcularStock();
            }
        }

        return redirect()->route('admin.productos.index')->with(
            'mensaje',
            "Importación completada: {$nuevos} nuevos, {$actualizados} actualizados, {$omitidos} omitidos."
        );
    }

    /** Mapa campo→índice de columna, buscando cada alias en la fila de encabezado. */
    private function indicesPorEncabezado(array $encabezado): array
    {
        $normalizados = array_map(fn ($valor) => $this->normalizarTexto((string) $valor), $encabezado);

        $indices = [];

        foreach (self::ALIAS_COLUMNAS as $campo => $alias) {
            foreach ($alias as $variante) {
                $posicion = array_search($this->normalizarTexto($variante), $normalizados, true);

                if ($posicion !== false) {
                    $indices[$campo] = $posicion;

                    break;
                }
            }
        }

        return $indices;
    }

    /** Mayúsculas y sin tildes, para comparar encabezados sin depender de cómo los tipeó cada quien. */
    private function normalizarTexto(string $valor): string
    {
        $valor = mb_strtoupper(trim($valor));

        return strtr($valor, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N',
        ]);
    }

    // ── Altas, bajas y stock ────────────────────────────────────────────────

    public function store(Request $request, MatrizGalonaje $matriz): RedirectResponse
    {
        $datos = $this->validar($request);

        $producto = DB::transaction(function () use ($request, $datos) {
            $producto = Producto::create($datos + ['stock' => 0]);
            $this->guardarStockPorAlmacen($request, $producto);

            return $producto;
        });

        $this->guardarFactorGalonaje($request, $producto, $matriz);

        return redirect()->route('admin.productos.index')->with('mensaje', 'Producto registrado');
    }

    public function update(Request $request, Producto $producto, MatrizGalonaje $matriz): RedirectResponse
    {
        $datos = $this->validar($request);

        DB::transaction(function () use ($request, $producto, $datos) {
            $producto->update($datos);
            $this->guardarStockPorAlmacen($request, $producto);
        });

        $this->guardarFactorGalonaje($request, $producto, $matriz);

        return redirect()->route('admin.productos.index')->with('mensaje', 'Producto actualizado');
    }

    /** Baja lógica, igual que `productos_lista.php`. */
    public function destroy(Producto $producto): RedirectResponse
    {
        $producto->update(['estado' => 'inactivo']);

        return redirect()->route('admin.productos.index')->with('mensaje', 'Producto eliminado exitosamente');
    }

    /** Entrada, salida o ajuste de stock en un almacén concreto. */
    public function ajustarStock(Request $request, Producto $producto): RedirectResponse
    {
        $datos = $request->validate([
            'almacen_id' => ['required', 'integer', 'exists:almacenes,id'],
            'tipo' => ['required', 'in:entrada,salida,ajuste'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($request, $producto, $datos) {
            $fila = StockAlmacen::firstOrCreate(
                ['producto_id' => $producto->id, 'almacen_id' => $datos['almacen_id']],
                ['stock' => 0]
            );

            $anterior = (int) $fila->stock;

            $nuevo = match ($datos['tipo']) {
                'entrada' => $anterior + $datos['cantidad'],
                'salida' => max(0, $anterior - $datos['cantidad']),
                'ajuste' => $datos['cantidad'],
            };

            $fila->update(['stock' => $nuevo]);

            MovimientoAlmacen::create([
                'producto_id' => $producto->id,
                'almacen_id' => $datos['almacen_id'],
                'tipo' => $datos['tipo'],
                'cantidad' => $datos['cantidad'],
                'stock_anterior' => $anterior,
                'stock_nuevo' => $nuevo,
                'motivo' => $datos['motivo'] ?? null,
                'usuario_id' => $request->user()->id,
            ]);

            $producto->recalcularStock();
        });

        return back()->with('mensaje', 'Stock actualizado');
    }

    /** Autocompletado usado por ventas y guías. */
    public function buscar(Request $request): JsonResponse
    {
        $termino = trim((string) $request->query('q', ''));

        if ($termino === '') {
            return response()->json([]);
        }

        $productos = Producto::activos()
            ->where(function ($q) use ($termino) {
                $q->where('nombre', 'like', "%{$termino}%")
                    ->orWhere('codigo', 'like', "%{$termino}%");
            })
            ->limit(20)
            ->get(['id', 'codigo', 'nombre', 'presentacion', 'precio_venta', 'stock', 'peso']);

        return response()->json($productos);
    }

    // ── Consultas de apoyo ──────────────────────────────────────────────────

    /**
     * Productos del catálogo con el stock del almacén mostrado en
     * `stock_almacen`.
     *
     * Se conserva el comportamiento de `productos_lista.php`: como la carga de
     * la matriz dejó códigos repetidos, sólo sobrevive el primer registro de
     * cada código y las tarjetas de arriba se calculan sobre esta misma lista.
     */
    private function listado(string $busqueda, $categoria, $marca, int $almacenSel): Collection
    {
        $filas = Producto::activos()
            ->with(['categoria', 'marca', 'stockPorAlmacen'])
            ->when($busqueda !== '', function ($q) use ($busqueda) {
                $q->where(function ($sub) use ($busqueda) {
                    $sub->where('nombre', 'like', "%{$busqueda}%")
                        ->orWhere('codigo', 'like', "%{$busqueda}%")
                        ->orWhere('presentacion', 'like', "%{$busqueda}%");
                });
            })
            ->when($categoria, fn ($q) => $q->where('categoria_id', $categoria))
            ->when($marca, fn ($q) => $q->where('marca_id', $marca))
            ->orderByDesc('id')
            ->limit(self::TOPE_LISTADO)
            ->get();

        $vistos = [];

        return $filas
            ->filter(function (Producto $producto) use (&$vistos) {
                $codigo = trim((string) $producto->codigo);

                if ($codigo === '') {
                    return true;
                }

                if (isset($vistos[$codigo])) {
                    return false;
                }

                $vistos[$codigo] = true;

                return true;
            })
            ->each(function (Producto $producto) use ($almacenSel) {
                $producto->stock_almacen = (int) ($producto->stockPorAlmacen
                    ->firstWhere('almacen_id', $almacenSel)->stock ?? 0);
            })
            ->values();
    }

    /** Almacén de la pestaña; el primero activo si el pedido no existe. */
    private function almacenSeleccionado(Request $request, $almacenes): int
    {
        $pedido = (int) $request->query('almacen', 0);

        if ($almacenes->contains('id', $pedido)) {
            return $pedido;
        }

        return (int) ($almacenes->first()->id ?? 0);
    }

    /** Unidades y número de productos guardados en cada almacén. */
    private function resumenPorAlmacen(): array
    {
        return StockAlmacen::selectRaw('almacen_id, COALESCE(SUM(stock), 0) AS unidades, COUNT(*) AS productos')
            ->groupBy('almacen_id')
            ->get()
            ->mapWithKeys(fn ($fila) => [
                (int) $fila->almacen_id => [
                    'unidades'  => (int) $fila->unidades,
                    'productos' => (int) $fila->productos,
                ],
            ])
            ->all();
    }

    /**
     * Aviso de cada almacén: productos activos en el mínimo o por debajo. No
     * deduplica por código, igual que el contador del original.
     */
    private function stockBajoPorAlmacen(): array
    {
        return DB::table('stock_almacen as sa')
            ->join('productos as p', 'sa.producto_id', '=', 'p.id')
            ->where('p.estado', 'activo')
            ->whereColumn('sa.stock', '<=', 'p.stock_minimo')
            ->selectRaw('sa.almacen_id, COUNT(*) AS n')
            ->groupBy('sa.almacen_id')
            ->pluck('n', 'almacen_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /** Valor de venta del stock guardado en cada almacén. */
    private function valorPorAlmacen(): array
    {
        return DB::table('stock_almacen as sa')
            ->join('productos as p', 'sa.producto_id', '=', 'p.id')
            ->selectRaw('sa.almacen_id, COALESCE(SUM(sa.stock * p.precio_venta), 0) AS valor')
            ->groupBy('sa.almacen_id')
            ->pluck('valor', 'almacen_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /** Rango de fechas del filtro «ventas por periodo». */
    private function periodoVentas(Request $request): array
    {
        return [
            'mes'   => trim((string) $request->query('vmes', '')),   // YYYY-MM
            'desde' => trim((string) $request->query('vdesde', '')),
            'hasta' => trim((string) $request->query('vhasta', '')),
        ];
    }

    /**
     * Unidades y galones vendidos dentro del periodo. Sin periodo elegido
     * devuelve el histórico completo.
     *
     * Se agrupa por código y no por `producto_id` porque el catálogo tiene
     * códigos repetidos: la fila que sobrevive a la deduplicación puede no ser
     * la que quedó referenciada en las ventas, y así no se pierde ninguna.
     */
    private function vendidosPorProducto(array $periodo): array
    {
        // Se descartan las canceladas, el mismo criterio del listado original.
        // Se agrupa por `p.id` (la llave primaria) en vez de por la expresión de
        // `clave`: MariaDB, a diferencia de MySQL 5.7+, no siempre reconoce que
        // una expresión del SELECT es la misma que la del GROUP BY aunque sean
        // idénticas, y rechaza la consulta con ONLY_FULL_GROUP_BY. Agrupar por
        // la llave primaria sí está soportado (el resto de columnas de `p`
        // quedan funcionalmente dependientes de ella), y la `clave` se arma en
        // PHP con la misma regla que usa la vista.
        return DB::table('venta_detalle as vd')
            ->join('ventas as v', 'vd.venta_id', '=', 'v.id')
            ->join('productos as p', 'vd.producto_id', '=', 'p.id')
            ->where('v.estado', '!=', 'cancelada')
            ->when($periodo['mes'] !== '', fn ($q) => $q->whereRaw("DATE_FORMAT(v.fecha, '%Y-%m') = ?", [$periodo['mes']]))
            ->when($periodo['desde'] !== '', fn ($q) => $q->whereDate('v.fecha', '>=', $periodo['desde']))
            ->when($periodo['hasta'] !== '', fn ($q) => $q->whereDate('v.fecha', '<=', $periodo['hasta']))
            ->selectRaw('p.id AS id, p.codigo AS codigo')
            ->selectRaw('SUM(vd.cantidad) AS unidades, COALESCE(SUM(vd.galones), 0) AS galones')
            ->groupBy('p.id', 'p.codigo')
            ->get()
            ->mapWithKeys(function ($fila) {
                $codigo = trim((string) $fila->codigo);

                return [
                    ($codigo !== '' ? $codigo : '#'.$fila->id) => [
                        'unidades' => (int) $fila->unidades,
                        'galones'  => (float) $fila->galones,
                    ],
                ];
            })
            ->all();
    }

    /** Líneas disponibles para clasificar un producto en la matriz. */
    private function lineasMatriz(MatrizGalonaje $matriz): array
    {
        $lineas = array_keys($matriz->categorias());

        foreach ($matriz->productos() as $datos) {
            $linea = trim((string) ($datos['l'] ?? ''));

            if ($linea !== '' && ! in_array($linea, $lineas, true)) {
                $lineas[] = $linea;
            }
        }

        sort($lineas, SORT_NATURAL | SORT_FLAG_CASE);

        return $lineas;
    }

    /** Guarda el stock por almacén enviado como `stock[almacen_id]`. */
    private function guardarStockPorAlmacen(Request $request, Producto $producto): void
    {
        $porAlmacen = $request->input('stock', []);

        if (! is_array($porAlmacen)) {
            return;
        }

        foreach ($porAlmacen as $almacenId => $cantidad) {
            StockAlmacen::updateOrCreate(
                ['producto_id' => $producto->id, 'almacen_id' => (int) $almacenId],
                ['stock' => max(0, (int) $cantidad)]
            );
        }

        $producto->recalcularStock();
    }

    /** Mismos obligatorios que el formulario del original. */
    private function validar(Request $request): array
    {
        return $request->validate([
            'codigo' => ['required', 'string', 'max:50'],
            'nombre' => ['required', 'string', 'max:255'],
            'categoria_id' => ['required', 'integer', 'exists:categorias,id'],
            'marca_id' => ['nullable', 'integer', 'exists:marcas,id'],
            'presentacion' => ['nullable', 'string', 'max:100'],
            'viscosidad' => ['nullable', 'string', 'max:50'],
            'descripcion' => ['nullable', 'string'],
            'especificaciones' => ['nullable', 'string'],
            'precio_compra' => ['required', 'numeric', 'min:0'],
            'precio_venta' => ['required', 'numeric', 'min:0'],
            'precio_alquiler' => ['nullable', 'numeric', 'min:0'],
            'stock_minimo' => ['required', 'integer', 'min:0'],
            'peso' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    /**
     * Registra en la matriz el factor de galones que trae el modal. De un
     * código ya conocido sólo se refrescan factor y presentación, para no
     * perder el nombre ni la línea con que se cargó la matriz.
     */
    private function guardarFactorGalonaje(Request $request, Producto $producto, MatrizGalonaje $matriz): void
    {
        $factor = (float) str_replace(',', '.', (string) $request->input('factor_gl', '0'));
        $codigo = trim((string) $producto->codigo);

        if ($factor <= 0 || $codigo === '') {
            return;
        }

        $actual = $matriz->productos()[$codigo] ?? null;

        $matriz->guardarProducto($codigo, [
            'f' => $factor,
            'p' => (string) ($producto->presentacion ?: ($actual['p'] ?? '')),
            'n' => (string) ($actual['n'] ?? $producto->nombre),
            'l' => (string) ($actual['l'] ?? ''),
        ]);
    }
}
