<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Proveedor;
use App\Models\Producto;
use App\Services\LectorExcel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProveedorController extends Controller
{
    /** Alias de encabezado reconocidos por campo (comparados en mayúsculas, sin tildes). */
    private const ALIAS_COLUMNAS = [
        'codigo' => ['CODIGO', 'COD. INTERNO', 'COD INTERNO', 'COD'],
        'nombre' => ['NOMBRE', 'PRODUCTO', 'DESCRIPCION'],
        'precio_compra' => ['COSTO', 'PRECIO DE COMPRA', 'PRECIO COMPRA'],
        'precio_venta' => ['PRECIO DE VENTA', 'PRECIO VENTA', 'PRECIO'],
    ];
    public function index(Request $request): View
    {
        $busqueda = trim((string) $request->query('q', ''));

        $proveedores = Proveedor::query()
            ->where('estado', 'activo')
            ->when($busqueda !== '', function ($query) use ($busqueda) {
                $query->where(function ($q) use ($busqueda) {
                    $q->where('razon_social', 'like', "%{$busqueda}%")
                        ->orWhere('ruc', 'like', "%{$busqueda}%")
                        ->orWhere('contacto', 'like', "%{$busqueda}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.proveedores.index', [
            'proveedores' => $proveedores,
            'busqueda' => $busqueda,
            'totalActivos' => Proveedor::where('estado', 'activo')->count(),
            'conCredito' => Proveedor::where('estado', 'activo')->where('dias_credito', '>', 0)->count(),
            'promedioCredito' => (float) Proveedor::where('estado', 'activo')->avg('dias_credito'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Proveedor::create($this->validar($request));

        return redirect()->route('admin.proveedores.index')->with('mensaje', 'Proveedor registrado exitosamente');
    }

    public function update(Request $request, Proveedor $proveedor): RedirectResponse
    {
        $proveedor->update($this->validar($request));

        return redirect()->route('admin.proveedores.index')->with('mensaje', 'Proveedor actualizado exitosamente');
    }

    /** Baja lógica: el original solo marca el proveedor como inactivo. */
    public function destroy(Proveedor $proveedor): RedirectResponse
    {
        $proveedor->update(['estado' => 'inactivo']);

        return redirect()->route('admin.proveedores.index')->with('mensaje', 'Proveedor desactivado exitosamente');
    }

    /**
     * Carga masiva de los productos que suministra un proveedor, desde un
     * .xlsx — mismo patrón que `ProductoController::importar()`: columnas
     * identificadas por encabezado, upsert por código (nunca por nombre).
     * Si el código ya existe en el catálogo (de otro proveedor o alta
     * manual), esta carga le pisa el `proveedor_id` al de este proveedor.
     */
    public function importarProductos(Request $request, Proveedor $proveedor, LectorExcel $lector): RedirectResponse
    {
        $request->validate(['archivo' => ['required', 'file', 'mimes:xlsx']]);

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
            $precioCompra = isset($indices['precio_compra'])
                ? (float) str_replace(',', '.', (string) ($fila[$indices['precio_compra']] ?? 0))
                : null;
            $precioVenta = isset($indices['precio_venta'])
                ? (float) str_replace(',', '.', (string) ($fila[$indices['precio_venta']] ?? 0))
                : null;

            $producto = $codigo !== '' ? Producto::where('codigo', $codigo)->first() : null;

            $datos = array_filter([
                'nombre' => $nombre,
                'proveedor_id' => $proveedor->id,
                'precio_compra' => $precioCompra,
                'precio_venta' => $precioVenta,
            ], fn ($valor) => $valor !== null);

            if ($producto) {
                $producto->update($datos);
                $actualizados++;
            } else {
                Producto::create($datos + [
                    'codigo' => $codigo !== '' ? $codigo : null,
                    'precio_compra' => $precioCompra ?? 0,
                    'precio_venta' => $precioVenta ?? 0,
                    'stock_minimo' => 0,
                    'stock' => 0,
                    'estado' => 'activo',
                ]);
                $nuevos++;
            }
        }

        return redirect()->route('admin.proveedores.index')->with(
            'mensaje',
            "Productos de {$proveedor->razon_social}: {$nuevos} nuevos, {$actualizados} actualizados, {$omitidos} omitidos."
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

    private function validar(Request $request): array
    {
        return $request->validate([
            'ruc' => ['required', 'string', 'max:20'],
            'razon_social' => ['required', 'string', 'max:255'],
            'contacto' => ['nullable', 'string', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'direccion' => ['nullable', 'string'],
            'distrito' => ['nullable', 'string', 'max:100'],
            'provincia' => ['nullable', 'string', 'max:100'],
            'departamento' => ['nullable', 'string', 'max:100'],
            'fecha_cumpleanos' => ['nullable', 'date'],
            'productos_suministra' => ['nullable', 'string'],
            'condiciones_pago' => ['required', Rule::in(['Contado', 'Crédito'])],
            'dias_credito' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
