<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Selector de empresa (Casa Drywall / Jitk), previo al login. */
class EmpresaController extends Controller
{
    public function elegir(): View
    {
        return view('empresas.elegir', ['empresas' => config('empresas.lista')]);
    }

    public function seleccionar(Request $request): RedirectResponse
    {
        $slug = $request->route('empresa');

        if (! array_key_exists($slug, config('empresas.lista'))) {
            abort(404);
        }

        $request->session()->put('empresa_activa', $slug);

        return redirect()->route('login');
    }

    /** "Cambiar de empresa": cierra sesión y vuelve al selector. */
    public function cambiar(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->forget('empresa_activa');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('empresas.elegir');
    }
}
