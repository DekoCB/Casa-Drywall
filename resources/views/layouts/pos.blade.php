{{--
    Layout mínimo del Punto de Venta: sin sidebar, pensado para mostrador
    a pantalla completa. Comparte tema/tokens/componentes con el resto del
    panel (mismo admin.css + app.js), solo cambia el chrome alrededor.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Punto de Venta') — {{ config('rentaltech.empresa.razon_social') }}</title>
    <link rel="icon" href="{{ asset('img/Logo.png') }}" type="image/png">
    <script>
        (function () {
            var guardado = localStorage.getItem('tema');
            if (guardado === 'light' || guardado === 'dark') {
                document.documentElement.dataset.theme = guardado;
            }
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Audiowide&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/admin.css', 'resources/css/modules/pos.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="pos-body">

<header class="pos-cab">
    <div class="pos-cab-marca">
        <img src="{{ asset('img/Logo.png') }}" alt="{{ config('rentaltech.empresa.razon_social') }}" class="pos-cab-logo">
        <div>
            <div class="pos-cab-empresa">{{ config('rentaltech.empresa.razon_social') }}</div>
            <div class="pos-cab-titulo">Punto de Venta</div>
        </div>
    </div>

    <div class="pos-cab-info">
        @yield('info-caja')
        <span class="pos-reloj" id="posReloj"></span>
        <button type="button" class="theme-toggle" id="themeToggle" title="Cambiar tema" aria-label="Cambiar tema">
            <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
            <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>
        <a href="{{ route('admin.index') }}" class="btn btn-secondary btn-sm">← Volver al panel</a>
    </div>
</header>

<main class="pos-main">
    @include('partials.flash')
    @yield('content')
</main>

<script>
(function () {
    const themeToggle = document.getElementById('themeToggle');

    themeToggle?.addEventListener('click', () => {
        const actual = document.documentElement.dataset.theme
            || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        const nuevo = actual === 'dark' ? 'light' : 'dark';
        document.documentElement.dataset.theme = nuevo;
        localStorage.setItem('tema', nuevo);
    });

    const reloj = document.getElementById('posReloj');
    function actualizarReloj() {
        reloj.textContent = new Date().toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }
    actualizarReloj();
    setInterval(actualizarReloj, 1000);
})();
</script>

@stack('scripts')
</body>
</html>
