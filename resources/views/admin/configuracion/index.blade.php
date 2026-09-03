@extends('layouts.admin')

@section('title', 'Configuración')
@section('crumb', 'Sistema')

@section('content')

<x-page-header titulo="Configuración" subtitulo="Datos de tu cuenta y accesos a la configuración del sistema" />

<div class="content-card" style="margin-bottom:24px;">
    <h3 style="font-size:15px;margin-bottom:16px;">Mi cuenta</h3>

    <form method="POST" action="{{ route('perfil.update') }}" enctype="multipart/form-data">
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
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
        </div>
    </form>
</div>

@php
    $secciones = [
        'Catálogo' => [
            ['route' => 'admin.categorias.index', 'titulo' => 'Categorías', 'desc' => 'Categorías de productos del catálogo general.', 'icon' => '<path d="M20 7h-9"/><path d="M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/>'],
            ['route' => 'admin.marcas.index', 'titulo' => 'Marcas', 'desc' => 'Marcas asociadas a los productos.', 'icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>'],
            ['route' => 'admin.productos.almacenes', 'titulo' => 'Almacenes', 'desc' => 'Almacenes y su stock por ubicación.', 'icon' => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>'],
        ],
        'Comercial' => [
            ['route' => 'admin.caja.index', 'titulo' => 'Cajas', 'desc' => 'Catálogo de cajas físicas del Punto de Venta y su historial de sesiones.', 'icon' => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>'],
            ['route' => 'admin.personal.index', 'titulo' => 'Personal', 'desc' => 'Altas, bajas y accesos al sistema del equipo.', 'icon' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
        ],
        'Galonaje' => [
            ['route' => 'admin.galonaje.categorias.index', 'titulo' => 'Líneas de producto', 'desc' => 'Categorías de la matriz de lubricantes Kendall / P66.', 'icon' => '<path d="M20 7h-9"/><path d="M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/>'],
            ['route' => 'admin.galonaje.presentaciones.index', 'titulo' => 'Presentaciones', 'desc' => 'Envases (galones, cuartos, cajas) de la matriz de lubricantes.', 'icon' => '<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>'],
        ],
    ];
@endphp

@foreach ($secciones as $titulo => $items)
    <div class="conf-seccion">
        <div class="conf-seccion-titulo">{{ $titulo }}</div>
        <div class="conf-grid">
            @foreach ($items as $item)
                <a href="{{ route($item['route']) }}" class="conf-card">
                    <div class="conf-card-icono">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">{!! $item['icon'] !!}</svg>
                    </div>
                    <div>
                        <div class="conf-card-titulo">{{ $item['titulo'] }}</div>
                        <div class="conf-card-desc">{{ $item['desc'] }}</div>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
@endforeach

@endsection

@push('scripts')
<script>
document.getElementById('cfgFotoInput')?.addEventListener('change', function () {
    const errEl = document.getElementById('cfgFotoError');
    const file = this.files[0];
    if (!file) return;

    if (file.size > 8 * 1024 * 1024) {
        errEl.textContent = 'La imagen supera los 8MB.';
        this.value = '';
        return;
    }

    errEl.textContent = '';
    const reader = new FileReader();
    reader.onload = (ev) => {
        document.getElementById('cfgAvatarPreview').innerHTML = '<img src="' + ev.target.result + '" alt="">';
    };
    reader.readAsDataURL(file);
});
</script>
@endpush
