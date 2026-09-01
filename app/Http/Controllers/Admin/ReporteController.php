<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Módulo de Reportes.
 *
 * Vaciado para rehacerlo desde cero: aquí irán las consultas que alimenten
 * cada reporte.
 */
class ReporteController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.reportes.index');
    }
}
