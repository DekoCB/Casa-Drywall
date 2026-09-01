<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Panel de contabilidad.
 *
 * En el proyecto original arranca sin módulos habilitados: el administrador
 * los concede desde el módulo de Personal.
 */
class ContadorController extends Controller
{
    public function index(): View
    {
        return view('contador.index');
    }
}
