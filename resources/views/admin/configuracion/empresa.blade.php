@extends('layouts.admin')

@section('title', 'Mi Empresa')
@section('crumb', 'Configuración')

@push('styles')
    @vite(['resources/css/modules/configuracion.css'])
@endpush

@php
    $campos = [
        'logo_claro' => ['label' => 'Logo (modo claro)', 'hint' => 'Resolución 700×300 — se usa en el PDF de tus documentos.', 'url' => $perfil->logoClaroUrl()],
        'logo_oscuro' => ['label' => 'Logo (modo oscuro)', 'hint' => 'Resolución 700×300 — se usa en el menú lateral del sistema.', 'url' => $perfil->logoOscuroUrl()],
        'favicon' => ['label' => 'Favicon (ícono web)', 'hint' => 'Resolución 64×64 — aparece en la pestaña del navegador.', 'url' => $perfil->faviconUrl()],
        'logo_app' => ['label' => 'Logo APP', 'hint' => 'Logo cuadrado — se usa cuando el menú lateral está colapsado.', 'url' => $perfil->logoAppUrl()],
    ];
@endphp

@section('content')

<x-page-header titulo="Mi Empresa" subtitulo="Acá configurás el logo, los datos de tu empresa y cómo se ve el sistema">
    <x-slot:acciones>
        <a href="{{ route('admin.configuracion.datos-empresa') }}" class="btn btn-secondary btn-sm"><span class="btn-text">← Datos de la Empresa</span></a>
    </x-slot:acciones>
</x-page-header>

@if (! empty($empresa['ruc']))
    <div style="display:flex;align-items:center;gap:6px;color:var(--ink-3);font-size:13px;margin:-10px 0 18px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
        RUC: {{ $empresa['ruc'] }}
    </div>
@endif

<form method="POST" action="{{ route('admin.configuracion.empresa.store') }}" enctype="multipart/form-data">
    @csrf

    <div class="content-card cfgp-form-seccion">
        <div class="form-grid" style="margin-bottom:0;">
            <div class="form-group">
                <label for="razonSocial">Nombre <span>*</span></label>
                <input type="text" id="razonSocial" name="razon_social" required maxlength="150" value="{{ old('razon_social', $perfil->razon_social) }}">
                <div class="cfgp-form-sub" style="margin:6px 0 0;">Tu razón social tal como figura en la SUNAT. Aparece en todos tus comprobantes.</div>
            </div>
            <div class="form-group">
                <label for="nombreComercial">Nombre comercial <span>*</span></label>
                <input type="text" id="nombreComercial" name="nombre_comercial" required maxlength="150" value="{{ old('nombre_comercial', $perfil->nombre_comercial) }}">
                <div class="cfgp-form-sub" style="margin:6px 0 0;">El nombre de tu tienda o marca que ven tus clientes. Puede ser diferente a la razón social.</div>
            </div>
        </div>

        <div class="form-group" style="margin-top:18px;max-width:420px;">
            <label for="tituloWeb">Título (nombre web)</label>
            <input type="text" id="tituloWeb" name="titulo_web" maxlength="60" value="{{ old('titulo_web', $perfil->titulo_web) }}" placeholder="{{ $perfil->tituloWeb() }}">
            <div class="cfgp-form-sub" style="margin:6px 0 0;">Aparece en la pestaña del navegador cuando tus usuarios ingresan al sistema. Requiere recargar la página.</div>
        </div>
    </div>

    <div class="content-card cfgp-form-seccion">
        <div class="cfgp-form-titulo" style="font-size:15px;margin-bottom:16px;">Logo y Marca</div>

        <div class="cfgp-dropzones">
            @foreach ($campos as $campo => $info)
                <div>
                    <div class="cfgp-form-titulo">{{ $info['label'] }}</div>
                    <label for="input_{{ $campo }}" class="cfgp-dropzone" data-preview="prev_{{ $campo }}">
                        <img id="prev_{{ $campo }}" src="{{ $info['url'] }}" alt="" style="{{ $info['url'] ? '' : 'display:none;' }}">
                        <div class="cfgp-dropzone-vacio" style="{{ $info['url'] ? 'display:none;' : '' }}">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                            <p>Arrastra una imagen aquí o haz clic para seleccionar</p>
                            <span>PNG, JPG, GIF o SVG · Máx. {{ $campo === 'favicon' ? '0.5' : '2' }} MB</span>
                            <span class="cfgp-dropzone-btn">↑ Seleccionar imagen</span>
                        </div>
                        <input type="file" id="input_{{ $campo }}" name="{{ $campo }}" accept="image/*" hidden>
                    </label>
                    <div class="cfgp-form-sub" style="margin-top:6px;">{{ $info['hint'] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="header-btns" style="justify-content:flex-end;margin-bottom:20px;">
        <button type="submit" class="btn btn-primary">Guardar cambios</button>
    </div>
</form>

@if (! empty($empresa['direccion']) || ! empty($empresa['telefono']) || ! empty($empresa['email']))
    <div class="content-card">
        <div class="cfgp-form-titulo" style="margin-bottom:12px;">Otros datos de contacto</div>
        <div class="table-container">
            <table class="table">
                <tbody>
                    <tr><th style="width:220px;">Dirección</th><td>{{ $empresa['direccion'] ?: '—' }}</td></tr>
                    <tr><th>Teléfono</th><td>{{ $empresa['telefono'] ?: '—' }}</td></tr>
                    <tr><th>Correo</th><td>{{ $empresa['email'] ?: '—' }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>
@endif

@endsection

@push('scripts')
<script>
document.querySelectorAll('.cfgp-dropzone[data-preview]').forEach((zona) => {
    const input = zona.querySelector('input[type="file"]');
    const img = document.getElementById(zona.dataset.preview);
    const vacio = zona.querySelector('.cfgp-dropzone-vacio');

    const mostrarArchivo = (archivo) => {
        if (!archivo) return;
        const lector = new FileReader();
        lector.onload = (e) => {
            img.src = e.target.result;
            img.style.display = '';
            vacio.style.display = 'none';
        };
        lector.readAsDataURL(archivo);
    };

    input.addEventListener('change', () => mostrarArchivo(input.files[0]));

    zona.addEventListener('dragover', (e) => { e.preventDefault(); zona.classList.add('is-dragover'); });
    zona.addEventListener('dragleave', () => zona.classList.remove('is-dragover'));
    zona.addEventListener('drop', (e) => {
        e.preventDefault();
        zona.classList.remove('is-dragover');
        if (e.dataTransfer.files.length) {
            input.files = e.dataTransfer.files;
            mostrarArchivo(input.files[0]);
        }
    });
});
</script>
@endpush
