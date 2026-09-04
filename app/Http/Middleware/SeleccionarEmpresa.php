<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Multi-empresa: antes de cualquier página protegida, hay que saber con
 * cuál empresa se está trabajando (Casa Drywall / Jitk, ver
 * `config/empresas.php`). La sesión vive siempre en la conexión por
 * defecto (así el selector funciona incluso sin haber elegido empresa
 * todavía); este middleware, ya con la sesión abierta, cambia la conexión
 * de base de datos y los datos de la empresa activa para el resto del
 * request — el resto del código (controladores, vistas) no necesita
 * saber que existe una segunda empresa: simplemente consulta contra la
 * conexión que ya quedó activa.
 */
class SeleccionarEmpresa
{
    /** Rutas alcanzables sin haber elegido empresa todavía. */
    private const RUTAS_LIBRES = ['empresas.elegir', 'empresas.seleccionar'];

    public function handle(Request $request, Closure $next): Response
    {
        $activa = $request->session()->get('empresa_activa');

        if (! $activa) {
            if (Auth::check()) {
                // Sesión de antes de que existiera el selector: la única
                // empresa que había era Casa Drywall, se asume esa sin
                // cerrarle la sesión a nadie.
                $activa = config('empresas.default');
                $request->session()->put('empresa_activa', $activa);
            } elseif (! in_array($request->route()?->getName(), self::RUTAS_LIBRES, true)) {
                return redirect()->route('empresas.elegir');
            }
        }

        $empresa = config('empresas.lista.'.($activa ?: config('empresas.default')));

        if ($empresa) {
            if (! empty($empresa['conexion'])) {
                config(['database.default' => $empresa['conexion']]);
            }

            if (! empty($empresa['empresa'])) {
                config(['rentaltech.empresa' => $empresa['empresa']]);
            }

            config(['empresas.activa' => $empresa + ['slug' => $activa]]);
        }

        return $next($request);
    }
}
