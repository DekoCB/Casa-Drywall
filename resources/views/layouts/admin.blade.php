<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel Administrativo') — {{ config('rentaltech.empresa.razon_social') }}</title>
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
    @vite(['resources/css/admin.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body>
<div class="app @if(request()->cookie('sidebarColapsada') === '1') sidebar-collapsed @endif" id="appShell">

<div class="sb-overlay" id="sbOverlay"></div>

<button class="sb-toggle" id="sbToggle" aria-label="Menú">
    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8">
        <line x1="3" y1="12" x2="21" y2="12"/>
        <line x1="3" y1="6"  x2="21" y2="6"/>
        <line x1="3" y1="18" x2="21" y2="18"/>
    </svg>
</button>

<aside class="sidebar" id="sidebar">
    <div class="sb-head">
        <div class="sb-brand">
            <div class="sb-logo">
                <img src="{{ asset('img/Logo.png') }}" alt="{{ config('rentaltech.empresa.razon_social') }}" class="sb-logo-mark">
                <img src="{{ asset('img/Logo-L.png') }}" alt="{{ config('rentaltech.empresa.razon_social') }}" class="sb-logo-full">
            </div>
        </div>
    </div>

    <nav class="sb-nav">
        @foreach (config('menu.admin') as $seccion => $items)
            <div class="sb-section">{{ $seccion }}</div>
            @foreach ($items as $item)
                <a href="{{ route($item['route']) }}"
                   class="mi @if(in_array(Route::currentRouteName(), $item['active'] ?? [$item['route']])) active @endif"
                   title="{{ $item['label'] }}">
                    {!! $item['icon'] !!}
                    <span>{{ $item['label'] }}</span>
                    @isset($item['badge'])
                        <span class="badge {{ $item['badge_class'] }}">{{ $item['badge'] }}</span>
                    @endisset
                </a>
            @endforeach
        @endforeach
    </nav>

</aside>

<button type="button" class="sb-collapse-btn" id="sbCollapseBtn" title="Colapsar menú" aria-label="Colapsar menú">
    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
</button>

<div class="main">
    <header class="topbar">
        <div style="display:flex;align-items:baseline;gap:10px;">
            <h1 class="topbar-title">@yield('title', 'Dashboard')</h1>
            <span class="topbar-sep">›</span>
            <span class="topbar-crumb">@yield('crumb', 'Vista general')</span>
        </div>
        <div class="topbar-right">
            <div class="chip"><span class="cdot"></span> Sistema activo</div>
            <div class="chip">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                {{ now()->translatedFormat('d M Y') }}
            </div>
            <button type="button" class="theme-toggle" id="themeToggle" title="Cambiar tema" aria-label="Cambiar tema">
                <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
                <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </button>
            @include('partials.usuario-menu')
        </div>
    </header>

    <main class="content">
        @include('partials.flash')
        @yield('content')
    </main>
</div>

</div><!-- /app -->

<script>
(function() {
    const sidebar     = document.getElementById('sidebar');
    const sbToggle    = document.getElementById('sbToggle');
    const overlay     = document.getElementById('sbOverlay');
    const app         = document.getElementById('appShell');
    const collapseBtn = document.getElementById('sbCollapseBtn');
    const themeToggle = document.getElementById('themeToggle');

    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
    }

    sbToggle.addEventListener('click', () => {
        sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
    });

    overlay.addEventListener('click', closeSidebar);

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeSidebar();
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 1024) closeSidebar();
    });

    // ── Colapso de sidebar (solo desktop) ───────────────────────────────
    collapseBtn?.addEventListener('click', () => {
        const colapsada = app.classList.toggle('sidebar-collapsed');
        localStorage.setItem('sidebarColapsada', colapsada ? '1' : '0');
        document.cookie = 'sidebarColapsada=' + (colapsada ? '1' : '0') + ';path=/;max-age=31536000';
    });

    // ── Tema claro/oscuro ────────────────────────────────────────────────
    themeToggle?.addEventListener('click', () => {
        const actual = document.documentElement.dataset.theme
            || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        const nuevo = actual === 'dark' ? 'light' : 'dark';
        document.documentElement.dataset.theme = nuevo;
        localStorage.setItem('tema', nuevo);
    });
})();
</script>

@stack('scripts')
</body>
</html>
