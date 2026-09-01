<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // La paginación por defecto de Laravel trae clases de Tailwind, que este
        // proyecto no usa: se sustituye por una vista con el diseño del panel.
        Paginator::defaultView('vendor.pagination.rentaltech');
        Paginator::defaultSimpleView('vendor.pagination.rentaltech');

        // Nombres de meses y días en español en todas las vistas.
        Carbon::setLocale(config('app.locale'));
    }
}
