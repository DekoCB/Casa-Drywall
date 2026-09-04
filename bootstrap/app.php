<?php

use App\Http\Middleware\SeleccionarEmpresa;
use App\Http\Middleware\VerificarRol;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Auth;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'rol' => VerificarRol::class,
        ]);

        // Multi-empresa: después de StartSession (ya sabe qué sesión es),
        // antes que cualquier ruta protegida — cambia la conexión de BD y
        // los datos de empresa activa según lo elegido en el selector.
        $middleware->web(append: [SeleccionarEmpresa::class]);

        $middleware->redirectGuestsTo(fn () => route('login'));

        // Un usuario ya autenticado que entra al login va a su panel, no a la
        // raíz: si no, raíz y login se redirigen entre sí sin parar.
        $middleware->redirectUsersTo(function () {
            /** @var \App\Models\Usuario|null $usuario */
            $usuario = Auth::user();

            return $usuario?->rutaInicio() ?? '/';
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
