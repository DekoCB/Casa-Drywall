<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elegir empresa</title>
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
    @vite(['resources/css/auth.css', 'resources/js/glow-cursor.js'])
    <style>
        /* Varios videos apilados; solo el .is-active se ve, con un fundido
           suave al cambiar (hover de tarjeta) en vez de recargar el <video>. */
        .login-video-bg .bg-video { opacity: 0; transition: opacity .5s ease; }
        .login-video-bg .bg-video.is-active { opacity: 1; }

        .empresas-wrapper {
            position: relative; z-index: 1; min-height: 100vh;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 40px 20px; gap: 28px;
        }
        .empresas-heading { text-align: center; }
        .empresas-heading h1 { font-family: var(--font-display, inherit); font-size: 22px; color: #fff; margin-bottom: 6px; text-shadow: 0 1px 4px rgba(0,0,0,.5); }
        .empresas-heading p { font-size: 13.5px; color: rgba(255,255,255,.75); text-shadow: 0 1px 4px rgba(0,0,0,.5); }
        .empresas-grid { display: flex; gap: 20px; flex-wrap: wrap; justify-content: center; max-width: 700px; }

        /* Cada tarjeta usa el color y (si tiene) el logo propio de la
           empresa — ver config/empresas.php > selector. Sin logo (Jitk,
           todavía) se muestra solo el nombre en grande. */
        .empresa-card {
            width: 220px; padding: 28px 20px; border-radius: 16px;
            background: var(--empresa-color, #3d9b8c); border: 1.5px solid transparent;
            text-decoration: none; text-align: center; transition: transform .18s, box-shadow .18s;
            display: flex; flex-direction: column; align-items: center; gap: 12px;
            box-shadow: 0 6px 20px rgba(0,0,0,.25);
        }
        .empresa-card:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0,0,0,.35); }
        .empresa-logo {
            width: 64px; height: 64px; object-fit: contain;
        }
        .empresa-nombre {
            font-size: 15px; font-weight: 700; color: var(--empresa-on-color, #fff);
        }
        .empresa-card--sin-logo .empresa-nombre {
            font-size: 22px; font-family: var(--font-display, inherit);
        }
        .empresa-entrar { font-size: 12px; color: var(--empresa-on-color, #fff); opacity: .8; }
    </style>
</head>
<body>

<div class="login-video-bg" aria-hidden="true">
    <video class="bg-video bg-video-default is-active" autoplay muted loop playsinline preload="auto">
        <source src="{{ asset(config('empresas.selector_video_default')) }}" type="video/mp4">
    </video>
    @foreach ($empresas as $slug => $empresa)
        @if (! empty($empresa['selector']['video']))
            <video class="bg-video" data-bg-empresa="{{ $slug }}" muted loop playsinline preload="auto">
                <source src="{{ asset($empresa['selector']['video']) }}" type="video/mp4">
            </video>
        @endif
    @endforeach
    <div class="login-video-overlay"></div>
</div>

<div class="glow-cursor-layer" data-glow-cursor aria-hidden="true"></div>

<button type="button" class="theme-toggle theme-toggle-login" id="themeToggle" title="Cambiar tema" aria-label="Cambiar tema">
    <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
    <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
</button>

<div class="empresas-wrapper">
    <div class="empresas-heading">
        <h1>¿Con qué empresa querés ingresar?</h1>
        <p>Elegí una para continuar al inicio de sesión</p>
    </div>

    <div class="empresas-grid">
        @foreach ($empresas as $slug => $empresa)
            @php $sel = $empresa['selector'] ?? []; @endphp
            <a href="{{ route('empresas.seleccionar', $slug) }}"
               class="empresa-card @if (empty($sel['logo'])) empresa-card--sin-logo @endif"
               data-empresa="{{ $slug }}"
               style="--empresa-color: {{ $sel['color'] ?? '#3d9b8c' }}; --empresa-on-color: {{ $sel['on_color'] ?? '#fff' }};">
                @if (! empty($sel['logo']))
                    <img src="{{ asset($sel['logo']) }}" alt="{{ $empresa['nombre'] }}" class="empresa-logo">
                @endif
                <div class="empresa-nombre">{{ $empresa['nombre'] }}</div>
                <div class="empresa-entrar">Ingresar →</div>
            </a>
        @endforeach
    </div>
</div>

<script>
document.getElementById('themeToggle').addEventListener('click', () => {
    const actual = document.documentElement.dataset.theme
        || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    const nuevo = actual === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.theme = nuevo;
    localStorage.setItem('tema', nuevo);
});

// Video de fondo por tarjeta: al pasar el mouse por una empresa se
// adelanta su propio video de login; al salir, vuelve al video por
// defecto del selector (ver config/empresas.php > selector_video_default).
(function () {
    const videos = document.querySelectorAll('.bg-video');
    const defaultVideo = document.querySelector('.bg-video-default');

    function activar(video) {
        videos.forEach(v => {
            const activo = v === video;
            v.classList.toggle('is-active', activo);
            if (activo) v.play?.().catch(() => {}); else v.pause?.();
        });
    }

    document.querySelectorAll('.empresa-card').forEach(card => {
        const propio = document.querySelector(`.bg-video[data-bg-empresa="${card.dataset.empresa}"]`);
        if (!propio) return;
        card.addEventListener('mouseenter', () => activar(propio));
        card.addEventListener('mouseleave', () => activar(defaultVideo));
        card.addEventListener('focus', () => activar(propio));
        card.addEventListener('blur', () => activar(defaultVideo));
    });
})();
</script>

</body>
</html>
