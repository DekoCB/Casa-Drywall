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
        .empresas-wrapper {
            position: relative; z-index: 1; min-height: 100vh;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 40px 20px; gap: 28px;
        }
        .empresas-heading { text-align: center; }
        .empresas-heading h1 { font-family: var(--font-display, inherit); font-size: 22px; color: var(--ink, #fff); margin-bottom: 6px; }
        .empresas-heading p { font-size: 13.5px; color: var(--ink-3, #9aa); }
        .empresas-grid { display: flex; gap: 20px; flex-wrap: wrap; justify-content: center; max-width: 700px; }
        .empresa-card {
            width: 220px; padding: 28px 20px; border-radius: 16px;
            background: var(--surface, #fff); border: 1.5px solid var(--line, #e2e2e2);
            text-decoration: none; text-align: center; transition: transform .18s, border-color .18s, box-shadow .18s;
            display: flex; flex-direction: column; align-items: center; gap: 12px;
        }
        .empresa-card:hover { transform: translateY(-4px); border-color: var(--empresa-color, #3d9b8c); box-shadow: 0 10px 30px rgba(0,0,0,.15); }
        .empresa-avatar {
            width: 64px; height: 64px; border-radius: 50%; display: grid; place-items: center;
            background: var(--empresa-color, #3d9b8c); color: #fff; font-size: 22px; font-weight: 800;
            font-family: var(--font-display, inherit);
        }
        .empresa-nombre { font-size: 15px; font-weight: 700; color: var(--ink, #111); }
        .empresa-entrar { font-size: 12px; color: var(--ink-3, #888); }
    </style>
</head>
<body>

<div class="login-video-bg" aria-hidden="true">
    <video autoplay muted loop playsinline preload="auto">
        <source src="{{ asset('videos/login-bg.mp4') }}" type="video/mp4">
    </video>
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
            <a href="{{ route('empresas.seleccionar', $slug) }}" class="empresa-card" style="--empresa-color: {{ $empresa['color'] ?? '#3d9b8c' }};">
                <div class="empresa-avatar">{{ Str::upper(Str::substr($empresa['nombre'], 0, 2)) }}</div>
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
</script>

</body>
</html>
