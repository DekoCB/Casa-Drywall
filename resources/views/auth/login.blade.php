<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — {{ config('rentaltech.empresa.razon_social') }}</title>
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
    @vite(['resources/css/auth.css'])
</head>
<body>

<button type="button" class="theme-toggle theme-toggle-login" id="themeToggle" title="Cambiar tema" aria-label="Cambiar tema">
    <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
    <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
</button>

<div class="login-wrapper">

    <div class="logo-block">
        <img src="{{ asset('img/Logo-L.png') }}" alt="{{ config('rentaltech.empresa.razon_social') }}" class="login-logo">
        <div class="brand-tagline">Panel de Administración</div>
    </div>

    <div class="login-card">
        <div class="card-heading">Iniciar Sesión</div>
        <div class="card-sub">Bienvenido de vuelta</div>

        @if ($errors->any())
        <div class="error-msg">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            {{ $errors->first() }}
        </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="form-field">
                <label class="field-label" for="username">Usuario</label>
                <div class="input-wrap">
                    <span class="input-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                    </span>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        placeholder="Ingrese su usuario"
                        value="{{ old('username') }}"
                        required
                        autofocus
                    >
                </div>
            </div>

            <div class="form-field">
                <label class="field-label" for="password">Contraseña</label>
                <div class="input-wrap">
                    <span class="input-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                    </span>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Ingrese su contraseña"
                        required
                    >
                    <button type="button" class="toggle-pwd" onclick="togglePassword()" aria-label="Mostrar/ocultar contraseña">
                        <svg id="eye-show" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        <svg id="eye-hide" style="display:none" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                            <line x1="1" y1="1" x2="23" y2="23"/>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-ingresar">
                <span>Ingresar al Panel</span>
            </button>

        </form>

        <div class="divider"><span>o</span></div>

        <div class="register-link">
            <a href="{{ route('register') }}">¿No tienes cuenta? Crear Usuario →</a>
        </div>
    </div>

    <div class="login-footer">
        © {{ date('Y') }} <span>{{ config('rentaltech.empresa.razon_social') }}</span> — Todos los derechos reservados
    </div>

</div>

<script>
    function togglePassword() {
        const input   = document.getElementById('password');
        const eyeShow = document.getElementById('eye-show');
        const eyeHide = document.getElementById('eye-hide');

        if (input.type === 'password') {
            input.type            = 'text';
            eyeShow.style.display = 'none';
            eyeHide.style.display = 'block';
        } else {
            input.type            = 'password';
            eyeShow.style.display = 'block';
            eyeHide.style.display = 'none';
        }
    }

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
