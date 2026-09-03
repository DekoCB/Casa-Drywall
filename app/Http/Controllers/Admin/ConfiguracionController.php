<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Panel central de Configuración: reúne en un solo lugar accesos que ya
 * existían dispersos en el sistema (catálogo, cajas, accesos de personal,
 * Galonaje) más los datos de la propia cuenta — antes solo alcanzables
 * desde el modal del menú de usuario.
 */
class ConfiguracionController extends Controller
{
    public function index(): View
    {
        return view('admin.configuracion.index');
    }
}
