<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CentroNotificaciones;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    public function marcarLeidas(Request $request, CentroNotificaciones $centro): RedirectResponse
    {
        $centro->marcarTodasLeidas($request->user());

        return back();
    }
}
