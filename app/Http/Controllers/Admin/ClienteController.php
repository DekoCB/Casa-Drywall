<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Cobranza;
use App\Models\Venta;
use App\Services\LectorExcel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClienteController extends Controller
{
    /** Etiqueta legible del código SUNAT de comprobante. */
    private const TIPOS_COMPROBANTE = [
        '01' => 'Factura', '03' => 'Boleta', '07' => 'N. Crédito',
        '08' => 'N. Débito', '09' => 'Liquidación',
    ];

    public function index(Request $request): View
    {
        $busqueda = trim((string) $request->query('q', ''));

        $periodo = [
            'mes'   => trim((string) $request->query('mes', '')),   // YYYY-MM
            'desde' => trim((string) $request->query('desde', '')),
            'hasta' => trim((string) $request->query('hasta', '')),
        ];

        $hayPeriodo = array_filter($periodo) !== [];
        $compras    = $this->comprasPorCliente($periodo);
        $deudas     = $this->deudaPorCliente($periodo);

        $clientes = Cliente::query()
            ->when($busqueda !== '', function ($query) use ($busqueda) {
                $query->where(function ($q) use ($busqueda) {
                    $q->where('nombres', 'like', "%{$busqueda}%")
                        ->orWhere('numero_documento', 'like', "%{$busqueda}%")
                        ->orWhere('telefono', 'like', "%{$busqueda}%");
                });
            })
            // Con un periodo elegido interesa quién compró más, no el último dado de alta.
            ->when($hayPeriodo, fn ($q) => $q->whereIn('id', array_keys($compras)))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        if ($hayPeriodo) {
            $clientes->setCollection(
                $clientes->getCollection()
                    ->sortByDesc(fn (Cliente $c) => $compras[$c->id]['monto'] ?? 0)
                    ->values()
            );
        }

        // Las estadísticas siempre reflejan el total, no el filtro aplicado.
        $porTipo = Cliente::selectRaw('tipo_documento, COUNT(*) AS n')
            ->groupBy('tipo_documento')
            ->pluck('n', 'tipo_documento');

        return view('admin.clientes.index', [
            'clientes'      => $clientes,
            'busqueda'      => $busqueda,
            'periodo'       => $periodo,
            'hayPeriodo'    => $hayPeriodo,
            'compras'       => $compras,
            'deudas'        => $deudas,
            'totalComprado' => array_sum(array_column($compras, 'monto')),
            'nCompradores'  => count($compras),
            'totalClientes' => (int) $porTipo->sum(),
            'clientesDni'   => (int) ($porTipo['DNI'] ?? 0),
            'clientesRuc'   => (int) ($porTipo['RUC'] ?? 0),
        ]);
    }

    /**
     * Comprobantes y cobranzas de un cliente, para el panel que se abre desde
     * la lista. Respeta el periodo elegido si viene en la petición.
     */
    public function movimientos(Request $request, Cliente $cliente): JsonResponse
    {
        $periodo = [
            'mes'   => trim((string) $request->query('mes', '')),
            'desde' => trim((string) $request->query('desde', '')),
            'hasta' => trim((string) $request->query('hasta', '')),
        ];

        $ventas = $this->enPeriodo(
            Venta::where('cliente_id', $cliente->id)
                ->where(fn ($q) => $q->whereNull('estado')->orWhereNotIn('estado', ['cancelada', 'eliminada'])),
            'fecha',
            $periodo
        )
            ->orderByDesc('fecha')
            ->get()
            ->map(fn (Venta $v) => [
                'fecha'      => $v->fecha?->format('d/m/Y'),
                'documento'  => trim($v->n_seri.'-'.$v->n_comp, '-') ?: '—',
                'tipo'       => self::TIPOS_COMPROBANTE[$v->tipcomp] ?? $v->tipcomp,
                'base'       => (float) $v->baseimp,
                'igv'        => (float) $v->igv,
                'total'      => (float) $v->total,
            ]);

        $cobranzas = $this->enPeriodo(
            Cobranza::where('cliente_id', $cliente->id),
            'fecha_emision',
            $periodo
        )
            ->orderByDesc('fecha_emision')
            ->get()
            ->map(fn (Cobranza $c) => [
                'fecha'      => $c->fecha_emision?->format('d/m/Y'),
                'vence'      => $c->fecha_vencimiento?->format('d/m/Y'),
                'documento'  => trim($c->tipo.' '.$c->numero),
                'total'      => (float) $c->monto_total,
                'pagado'     => (float) $c->monto_pagado,
                'pendiente'  => (float) $c->monto_pendiente,
                'estado'     => $c->estado,
            ]);

        return response()->json([
            'cliente'   => $cliente->nombres,
            'documento' => $cliente->numero_documento ?: 'sin documento',
            'ventas'    => $ventas,
            'cobranzas' => $cobranzas,
            'resumen'   => [
                'comprado'  => round($ventas->sum('total'), 2),
                'nVentas'   => $ventas->count(),
                'pendiente' => round($cobranzas->sum('pendiente'), 2),
                'nCobranzas' => $cobranzas->count(),
            ],
        ]);
    }

    /** Acota una consulta al periodo elegido sobre el campo de fecha indicado. */
    private function enPeriodo(Builder $consulta, string $campo, array $periodo): Builder
    {
        return $consulta
            ->when($periodo['mes'] !== '', fn ($q) => $q->whereRaw("DATE_FORMAT($campo, '%Y-%m') = ?", [$periodo['mes']]))
            ->when($periodo['desde'] !== '', fn ($q) => $q->whereDate($campo, '>=', $periodo['desde']))
            ->when($periodo['hasta'] !== '', fn ($q) => $q->whereDate($campo, '<=', $periodo['hasta']));
    }

    /** Facturado a cada cliente en el periodo, según los comprobantes de venta. */
    private function comprasPorCliente(array $periodo): array
    {
        return Venta::whereNotNull('cliente_id')
            ->where(fn ($q) => $q->whereNull('estado')->orWhereNotIn('estado', ['cancelada', 'eliminada']))
            ->when($periodo['mes'] !== '', fn ($q) => $q->whereRaw("DATE_FORMAT(fecha, '%Y-%m') = ?", [$periodo['mes']]))
            ->when($periodo['desde'] !== '', fn ($q) => $q->whereDate('fecha', '>=', $periodo['desde']))
            ->when($periodo['hasta'] !== '', fn ($q) => $q->whereDate('fecha', '<=', $periodo['hasta']))
            ->selectRaw('cliente_id, COUNT(*) AS n, COALESCE(SUM(total), 0) AS monto')
            ->groupBy('cliente_id')
            ->get()
            ->mapWithKeys(fn ($f) => [(int) $f->cliente_id => [
                'n'     => (int) $f->n,
                'monto' => (float) $f->monto,
            ]])
            ->all();
    }

    /** Saldo pendiente de cada cliente, por fecha de emisión de la cobranza. */
    private function deudaPorCliente(array $periodo): array
    {
        return Cobranza::whereNotNull('cliente_id')
            ->when($periodo['mes'] !== '', fn ($q) => $q->whereRaw("DATE_FORMAT(fecha_emision, '%Y-%m') = ?", [$periodo['mes']]))
            ->when($periodo['desde'] !== '', fn ($q) => $q->whereDate('fecha_emision', '>=', $periodo['desde']))
            ->when($periodo['hasta'] !== '', fn ($q) => $q->whereDate('fecha_emision', '<=', $periodo['hasta']))
            ->selectRaw('cliente_id, COUNT(*) AS n, COALESCE(SUM(monto_pendiente), 0) AS saldo')
            ->groupBy('cliente_id')
            ->get()
            ->mapWithKeys(fn ($f) => [(int) $f->cliente_id => [
                'n'     => (int) $f->n,
                'saldo' => (float) $f->saldo,
            ]])
            ->all();
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        $existente = Cliente::where('numero_documento', $datos['numero_documento'])->first();

        if ($existente) {
            return back()->with('error', "Ya existe un cliente registrado con el documento {$datos['numero_documento']} ({$existente->nombres}). No se guardó para evitar duplicados.");
        }

        Cliente::create($datos);

        return redirect()->route('admin.clientes.index')->with('mensaje', 'Cliente registrado');
    }

    public function update(Request $request, Cliente $cliente): RedirectResponse
    {
        $cliente->update($this->validar($request));

        return redirect()->route('admin.clientes.index')->with('mensaje', 'Cliente actualizado');
    }

    public function destroy(Cliente $cliente): RedirectResponse
    {
        $cliente->delete();

        return redirect()->route('admin.clientes.index')->with('mensaje', 'Cliente eliminado exitosamente');
    }

    /** Clientes con mayor facturación acumulada. */
    public function destacados(): View
    {
        $destacados = Venta::selectRaw('cliente_nombre, COUNT(*) AS compras, SUM(total) AS total_facturado, SUM(galones_total) AS galones, MAX(fecha) AS ultima_compra')
            ->where('estado', 'completada')
            ->whereNotNull('cliente_nombre')
            ->where('cliente_nombre', '!=', '')
            ->groupBy('cliente_nombre')
            ->orderByDesc('total_facturado')
            ->limit(50)
            ->get();

        return view('admin.clientes.destacados', compact('destacados'));
    }

    /** Autocompletado de clientes usado por ventas. */
    public function buscar(Request $request): JsonResponse
    {
        $termino = trim((string) $request->query('q', ''));

        if ($termino === '') {
            return response()->json([]);
        }

        $clientes = Cliente::where('nombres', 'like', "%{$termino}%")
            ->orWhere('numero_documento', 'like', "%{$termino}%")
            ->orWhere('nombre_empresa', 'like', "%{$termino}%")
            ->limit(15)
            ->get(['id', 'tipo_documento', 'numero_documento', 'nombres', 'nombre_empresa', 'telefono', 'email', 'direccion', 'distrito', 'provincia', 'departamento']);

        return response()->json($clientes);
    }

    /** Importa clientes desde un .xlsx sin depender de librerías externas. */
    public function importar(Request $request, LectorExcel $lector): RedirectResponse
    {
        $request->validate(['archivo' => ['required', 'file', 'mimes:xlsx']]);

        $filas = $lector->leer($request->file('archivo')->getRealPath());

        if (isset($filas['error'])) {
            return back()->with('error', $filas['error']);
        }

        $importados = 0;
        $omitidos = 0;

        foreach ($filas as $fila) {
            $documento = trim((string) ($fila[1] ?? ''));
            $nombres = trim((string) ($fila[2] ?? ''));

            if ($documento === '' || $nombres === '') {
                continue;
            }

            if (Cliente::where('numero_documento', $documento)->exists()) {
                $omitidos++;

                continue;
            }

            Cliente::create([
                'tipo_documento' => trim((string) ($fila[0] ?? 'DNI')) ?: 'DNI',
                'numero_documento' => $documento,
                'nombres' => $nombres,
                'telefono' => trim((string) ($fila[3] ?? '')) ?: null,
                'email' => trim((string) ($fila[4] ?? '')) ?: null,
                'direccion' => trim((string) ($fila[5] ?? '')) ?: null,
                'distrito' => trim((string) ($fila[6] ?? '')) ?: null,
                'provincia' => trim((string) ($fila[7] ?? '')) ?: null,
                'departamento' => trim((string) ($fila[8] ?? '')) ?: null,
            ]);

            $importados++;
        }

        return redirect()->route('admin.clientes.index')
            ->with('mensaje', "Importación completada: {$importados} clientes nuevos, {$omitidos} omitidos por duplicado.");
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'tipo_documento' => ['required', 'string', 'max:20'],
            'numero_documento' => ['required', 'string', 'max:20'],
            'nombres' => ['required', 'string', 'max:255'],
            'nombre_empresa' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'direccion' => ['nullable', 'string'],
            'distrito' => ['nullable', 'string', 'max:100'],
            'provincia' => ['nullable', 'string', 'max:100'],
            'departamento' => ['nullable', 'string', 'max:100'],
            'fecha_cumpleanos' => ['nullable', 'date'],
        ]);
    }
}
