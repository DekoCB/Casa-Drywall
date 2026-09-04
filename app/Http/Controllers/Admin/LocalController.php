<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ApiGoSucursales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Locales/Establecimientos (sucursales SUNAT). No hay tabla local: la
 * fuente de verdad es el módulo de Sucursales de la API-GO, que ya
 * gestiona lo que un comprobante electrónico necesita (código, ubigeo,
 * series). Este controlador es una UI fina sobre esa API existente.
 */
class LocalController extends Controller
{
    private const CAMPOS_SERIE = ['series_factura', 'series_boleta', 'series_nota_credito', 'series_nota_debito', 'series_guia_remision'];

    public function index(ApiGoSucursales $api): View
    {
        return view('admin.locales.index', ['locales' => $api->listar()]);
    }

    public function store(Request $request, ApiGoSucursales $api): RedirectResponse
    {
        $creado = $api->crear($this->validar($request));

        if ($creado === null) {
            return back()->withInput()->with('error', 'No se pudo crear el local. Revisa que el código no esté repetido y que los datos estén completos.');
        }

        return redirect()->route('admin.locales.index')->with('mensaje', 'Local registrado.');
    }

    public function update(Request $request, int $local, ApiGoSucursales $api): RedirectResponse
    {
        $actualizado = $api->actualizar($local, $this->validar($request));

        if ($actualizado === null) {
            return back()->withInput()->with('error', 'No se pudo actualizar el local.');
        }

        return redirect()->route('admin.locales.index')->with('mensaje', 'Local actualizado.');
    }

    public function destroy(int $local, ApiGoSucursales $api): RedirectResponse
    {
        $api->desactivar($local);

        return redirect()->route('admin.locales.index')->with('mensaje', 'Local desactivado.');
    }

    public function activar(int $local, ApiGoSucursales $api): RedirectResponse
    {
        $api->activar($local);

        return redirect()->route('admin.locales.index')->with('mensaje', 'Local activado.');
    }

    public function actualizarSeries(Request $request, int $local, ApiGoSucursales $api): RedirectResponse
    {
        $datos = $request->validate([
            'series_factura' => ['nullable', 'string'],
            'series_boleta' => ['nullable', 'string'],
            'series_nota_credito' => ['nullable', 'string'],
            'series_nota_debito' => ['nullable', 'string'],
            'series_guia_remision' => ['nullable', 'string'],
        ]);

        $series = [];
        foreach (self::CAMPOS_SERIE as $campo) {
            $series[$campo] = collect(preg_split('/\r?\n/', trim($datos[$campo] ?? '')))
                ->map(fn ($linea) => trim($linea))
                ->filter()
                ->values()
                ->all();
        }

        $actualizado = $api->actualizarSeries($local, $series);

        if ($actualizado === null) {
            return back()->with('error', 'No se pudieron actualizar las series.');
        }

        return redirect()->route('admin.locales.index')->with('mensaje', 'Series actualizadas.');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'codigo' => ['required', 'string', 'max:10'],
            'nombre' => ['required', 'string', 'max:255'],
            'direccion' => ['required', 'string', 'max:255'],
            'ubigeo' => ['required', 'string', 'size:6'],
            'distrito' => ['required', 'string', 'max:100'],
            'provincia' => ['required', 'string', 'max:100'],
            'departamento' => ['required', 'string', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);
    }
}
