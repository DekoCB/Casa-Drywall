<?php

namespace App\Providers;

use App\Services\CentroNotificaciones;
use App\Services\LostConnectionDetector;
use Illuminate\Contracts\Database\LostConnectionDetector as LostConnectionDetectorContract;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Ver App\Services\LostConnectionDetector: agrega un mensaje de
        // error real de este hosting a la lista que Laravel ya reconoce
        // para reconectar y reintentar solo, sin perder ninguno de sus
        // patrones por defecto.
        $this->app->bind(LostConnectionDetectorContract::class, LostConnectionDetector::class);
    }

    public function boot(): void
    {
        // La paginación por defecto de Laravel trae clases de Tailwind, que este
        // proyecto no usa: se sustituye por una vista con el diseño del panel.
        Paginator::defaultView('vendor.pagination.rentaltech');
        Paginator::defaultSimpleView('vendor.pagination.rentaltech');

        // Nombres de meses y días en español en todas las vistas.
        Carbon::setLocale(config('app.locale'));

        // Campanita del topbar: se calcula una sola vez por request y se
        // comparte con el layout, en vez de que cada controlador la arme.
        View::composer('layouts.admin', function ($view) {
            $usuario = Auth::user();

            $view->with('notificaciones', $usuario
                ? app(CentroNotificaciones::class)->paraUsuario($usuario)
                : ['todas' => collect(), 'porCategoria' => [], 'noLeidas' => 0]);
        });
    }
}
