<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LiquidacionCompra;
use App\Models\Proveedor;
use App\Services\GeneradorCorrelativo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Compra a un vendedor informal (sin RUC). Queda como registro interno de
 * contabilidad — NO se envía a SUNAT: el paquete de facturación electrónica
 * instalado no soporta emitir Liquidación de Compra (ver comprobante.blade.php
 * para el aviso que se muestra en pantalla).
 */
class LiquidacionCompraController extends Controller
{
    public function __construct(private readonly GeneradorCorrelativo $correlativo) {}

    public function index(Request $request): View
    {
        $busqueda = trim((string) $request->query('q', ''));

        $liquidaciones = LiquidacionCompra::query()
            ->when($busqueda !== '', fn ($q) => $q->where(
                fn ($qq) => $qq->where('numero', 'like', "%{$busqueda}%")
                    ->orWhere('vendedor_nombre', 'like', "%{$busqueda}%")
            ))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.liquidaciones-compra.index', [
            'liquidaciones' => $liquidaciones,
            'busqueda' => $busqueda,
            'proveedores' => Proveedor::orderBy('razon_social')->get(['id', 'razon_social']),
            'abrirCrear' => $request->boolean('crear'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);
        $datos['numero'] = $this->correlativo->siguiente('liquidaciones_compra', 'numero', 'LC');
        $datos['usuario_id'] = $request->user()->id;

        LiquidacionCompra::create($datos);

        return redirect()->route('admin.liquidaciones-compra.index')->with('mensaje', 'Liquidación de compra registrada.');
    }

    public function update(Request $request, LiquidacionCompra $liquidacionCompra): RedirectResponse
    {
        $liquidacionCompra->update($this->validar($request));

        return redirect()->route('admin.liquidaciones-compra.index')->with('mensaje', 'Liquidación actualizada.');
    }

    public function destroy(LiquidacionCompra $liquidacionCompra): RedirectResponse
    {
        $liquidacionCompra->delete();

        return redirect()->route('admin.liquidaciones-compra.index')->with('mensaje', 'Liquidación eliminada.');
    }

    public function comprobante(LiquidacionCompra $liquidacionCompra): View
    {
        return view('admin.liquidaciones-compra.comprobante', ['liquidacion' => $liquidacionCompra]);
    }

    private function validar(Request $request): array
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date'],
            'vendedor_nombre' => ['required', 'string', 'max:255'],
            'vendedor_documento' => ['nullable', 'string', 'max:20'],
            'proveedor_id' => ['nullable', 'integer', 'exists:proveedores,id'],
            'productos' => ['nullable', 'string'],
            'total' => ['required', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $datos['productos'] = collect(preg_split('/\r?\n/', trim($datos['productos'] ?? '')))
            ->filter()
            ->map(fn ($linea) => ['descripcion' => trim($linea)])
            ->values()
            ->all();

        return $datos;
    }
}
