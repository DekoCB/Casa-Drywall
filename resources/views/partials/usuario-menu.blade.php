{{--
    Menú de cuenta (topbar) + modal de configuración.
    Compartido por layouts/admin.blade.php y layouts/rol.blade.php.
--}}
@php
    $tieneErroresPerfil = $errors->hasAny(['username', 'foto', 'password_actual', 'password']);
@endphp

<div class="topbar-user" id="topbarUser">
    <button type="button" class="tu-trigger" id="tuTrigger" aria-haspopup="true" aria-expanded="false">
        <div class="tu-avatar">
            @if (auth()->user()->foto)
                <img src="{{ auth()->user()->fotoUrl() }}" alt="">
            @else
                {{ Str::upper(Str::substr(auth()->user()->username, 0, 2)) }}
            @endif
        </div>
        <div class="tu-info">
            <span class="tu-name">{{ auth()->user()->username }}</span>
            <span class="tu-role">{{ ucfirst(auth()->user()->rol) }}</span>
        </div>
        <svg class="tu-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
    </button>

    <div class="tu-menu" id="tuMenu" role="menu">
        <button type="button" class="tu-menu-item" id="btnAbrirConfig">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            Configuración
        </button>
        <div class="tu-menu-sep"></div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="tu-menu-item tu-menu-item-danger">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16 17 21 12 16 7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
                Cerrar sesión
            </button>
        </form>
    </div>
</div>

<div class="modal-overlay cfg-overlay @if($tieneErroresPerfil) active @endif" id="cfgOverlay">
    <div class="modal-card cfg-card" id="cfgCard">
        <div class="modal-header">
            <h3>Configuración de cuenta</h3>
            <button type="button" class="modal-close" id="cfgClose" aria-label="Cerrar">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <form method="POST" action="{{ route('perfil.update') }}" enctype="multipart/form-data" class="modal-body">
            @csrf
            @method('PATCH')

            <div class="cfg-avatar-row">
                <div class="cfg-avatar-preview" id="cfgAvatarPreview">
                    @if (auth()->user()->foto)
                        <img src="{{ auth()->user()->fotoUrl() }}" alt="">
                    @else
                        <span>{{ Str::upper(Str::substr(auth()->user()->username, 0, 2)) }}</span>
                    @endif
                </div>
                <div>
                    <label class="cfg-upload-btn" for="cfgFotoInput">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        Cambiar foto
                    </label>
                    <input type="file" name="foto" id="cfgFotoInput" accept="image/png,image/jpeg,image/webp" hidden>
                    <p class="cfg-hint">JPG, PNG o WEBP. Máximo 8MB.</p>
                    <p class="cfg-error" id="cfgFotoError">@error('foto') {{ $message }} @enderror</p>
                </div>
            </div>

            <div class="form-group">
                <label for="cfgUsername">Nombre de usuario</label>
                <input type="text" name="username" id="cfgUsername" value="{{ old('username', auth()->user()->username) }}" required>
                @error('username') <p class="cfg-error">{{ $message }}</p> @enderror
            </div>

            <div class="cfg-divider"><span>Cambiar contraseña · opcional</span></div>

            <div class="form-group">
                <label for="cfgPasswordActual">Contraseña actual</label>
                <input type="password" name="password_actual" id="cfgPasswordActual" autocomplete="current-password">
                @error('password_actual') <p class="cfg-error">{{ $message }}</p> @enderror
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="cfgPassword">Nueva contraseña</label>
                    <input type="password" name="password" id="cfgPassword" autocomplete="new-password">
                    @error('password') <p class="cfg-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label for="cfgPasswordConf">Confirmar contraseña</label>
                    <input type="password" name="password_confirmation" id="cfgPasswordConf" autocomplete="new-password">
                </div>
            </div>

            <div style="display:flex;gap:10px;margin-top:20px;">
                <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center;">Guardar cambios</button>
                <button type="button" class="btn btn-secondary" id="cfgCancel">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const tuBlock   = document.getElementById('topbarUser');
    const tuTrigger = document.getElementById('tuTrigger');
    const cfgOverlay = document.getElementById('cfgOverlay');
    const cfgCard    = document.getElementById('cfgCard');

    // ── Menú de usuario ──────────────────────────────────────────────────
    tuTrigger?.addEventListener('click', (e) => {
        e.stopPropagation();
        const abierto = tuBlock.classList.toggle('abierto');
        tuTrigger.setAttribute('aria-expanded', abierto ? 'true' : 'false');
    });
    document.addEventListener('click', (e) => {
        if (tuBlock && !tuBlock.contains(e.target)) {
            tuBlock.classList.remove('abierto');
            tuTrigger?.setAttribute('aria-expanded', 'false');
        }
    });

    // ── Configuración de cuenta: encendido / apagado ─────────────────────
    function abrirConfig() {
        if (!cfgOverlay) return;
        tuBlock?.classList.remove('abierto');
        cfgCard.classList.remove('cfg-cerrando');
        cfgOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function cerrarConfig() {
        if (!cfgOverlay || !cfgOverlay.classList.contains('active')) return;
        cfgCard.classList.add('cfg-cerrando');
        cfgCard.addEventListener('animationend', function fin() {
            cfgOverlay.classList.remove('active');
            cfgCard.classList.remove('cfg-cerrando');
            document.body.style.overflow = '';
            cfgCard.removeEventListener('animationend', fin);
        }, { once: true });
    }

    document.getElementById('btnAbrirConfig')?.addEventListener('click', abrirConfig);
    document.getElementById('cfgClose')?.addEventListener('click', cerrarConfig);
    document.getElementById('cfgCancel')?.addEventListener('click', cerrarConfig);
    cfgOverlay?.addEventListener('click', (e) => {
        e.stopPropagation();
        if (e.target === cfgOverlay) cerrarConfig();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') cerrarConfig();
    });

    @if ($tieneErroresPerfil)
        abrirConfig();
    @endif

    // ── Vista previa de la foto + validación de 8MB en el cliente ───────
    const fotoInput = document.getElementById('cfgFotoInput');
    fotoInput?.addEventListener('change', () => {
        const errEl = document.getElementById('cfgFotoError');
        const file = fotoInput.files[0];
        if (!file) return;

        if (file.size > 8 * 1024 * 1024) {
            errEl.textContent = 'La imagen supera los 8MB.';
            fotoInput.value = '';
            return;
        }

        errEl.textContent = '';
        const reader = new FileReader();
        reader.onload = (ev) => {
            document.getElementById('cfgAvatarPreview').innerHTML =
                '<img src="' + ev.target.result + '" alt="">';
        };
        reader.readAsDataURL(file);
    });
})();
</script>
