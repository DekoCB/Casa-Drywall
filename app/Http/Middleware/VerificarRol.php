<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe una ruta a determinados roles. Si el usuario tiene otro rol se le
 * envía a su propio panel, replicando los redirects del index.php original.
 */
class VerificarRol
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $usuario = Auth::user();

        if (! $usuario) {
            return redirect()->route('login');
        }

        if (! in_array($usuario->rol, $roles, true)) {
            return redirect($usuario->rutaInicio());
        }

        return $next($request);
    }
}
