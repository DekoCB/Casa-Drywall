@extends('layouts.admin')

@section('title', 'Estilos y temas')
@section('crumb', 'Configuración')

@push('styles')
    @vite(['resources/css/modules/configuracion.css'])
@endpush

@php
    // Vista previa: acá sí se cargan las 6 familias de una, para que cada
    // tarjeta de tipografía se vea con su fuente real antes de elegir.
    $googlePreview = collect(\App\Models\EstiloSistema::FUENTES)->pluck('google')->implode('&');
@endphp

@push('styles')
    <link href="https://fonts.googleapis.com/css2?{{ $googlePreview }}&display=swap" rel="stylesheet">
@endpush

@section('content')

<x-page-header titulo="Estilos y temas" subtitulo="Color de acento y tipografía del sistema">
    <x-slot:acciones>
        <a href="{{ route('admin.configuracion.index') }}" class="btn btn-secondary btn-sm"><span class="btn-text">← Configuración</span></a>
    </x-slot:acciones>
</x-page-header>

<form method="POST" action="{{ route('admin.configuracion.estilos.store') }}">
    @csrf

    <div class="content-card cfgp-form-seccion">
        <div class="cfgp-form-titulo">Color de acento</div>
        <div class="cfgp-form-sub">El color que usan los botones principales, encabezados y resaltados en todo el sistema.</div>

        <div class="cfgp-paletas">
            @foreach (\App\Models\EstiloSistema::PALETAS as $clave => $paleta)
                <label class="cfgp-paleta" style="--pal-brand: {{ $paleta['brand'] }}; --pal-brand-2: {{ $paleta['brand2'] }};">
                    <input type="radio" name="color" value="{{ $clave }}" @checked($estilo->color === $clave)>
                    <span class="cfgp-paleta-card">
                        <span class="cfgp-paleta-dot"></span>
                        <span class="cfgp-paleta-nombre">{{ $paleta['nombre'] }}</span>
                    </span>
                </label>
            @endforeach
        </div>
    </div>

    <div class="content-card cfgp-form-seccion">
        <div class="cfgp-form-titulo">Tipografía</div>
        <div class="cfgp-form-sub">La fuente de los títulos y del texto general.</div>

        <div class="cfgp-fuentes">
            @foreach (\App\Models\EstiloSistema::FUENTES as $clave => $fuente)
                <label class="cfgp-fuente">
                    <input type="radio" name="fuente" value="{{ $clave }}" @checked($estilo->fuente === $clave)>
                    <span class="cfgp-fuente-card">
                        <span class="cfgp-fuente-preview" style="font-family: '{{ $fuente['display'] }}', sans-serif;">
                            Casa Drywall
                            <span style="font-family: '{{ $fuente['sans'] }}', sans-serif;">Aa Bb Cc — 0123456789</span>
                        </span>
                        <span class="cfgp-fuente-nombre">{{ $fuente['nombre'] }}</span>
                    </span>
                </label>
            @endforeach
        </div>
    </div>

    <div class="header-btns" style="justify-content:flex-end;">
        <button type="submit" class="btn btn-primary">Guardar cambios</button>
    </div>
</form>

@endsection
