@extends('layouts.admin')

@section('title', 'Configuración')
@section('crumb', 'Configuración')

@push('styles')
    @vite(['resources/css/modules/configuracion.css'])
@endpush

@section('content')

@php
    $iconos = [
        'empresa'   => '<path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 9h1"/><path d="M14 9h1"/><path d="M9 13h1"/><path d="M14 13h1"/><path d="M10 21v-4h4v4"/>',
        'categoria' => '<path d="M20 7h-9"/><path d="M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/>',
        'marca'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',
        'almacen'   => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
        'personal'  => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'maletin'   => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
    ];
@endphp

<x-page-header titulo="Configuración" subtitulo="Datos del negocio, catálogo y accesos del equipo" />

@foreach ($secciones as $seccion => $datos)
    <div class="cfgp-seccion">
        <div class="cfgp-header">
            <div class="cfgp-badge" style="background:{{ $datos['color'] }};">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $iconos[$datos['icono']] !!}</svg>
            </div>
            <div>
                <div class="cfgp-titulo">{{ $seccion }}</div>
                <div class="cfgp-sub">{{ $datos['sub'] }}</div>
            </div>
        </div>

        <div class="cfgp-grid">
            @foreach ($datos['items'] as $item)
                <a href="{{ route($item['route'], $item['query'] ?? []) }}" class="cfgp-item">
                    <div class="cfgp-item-icono">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $iconos[$item['icon']] !!}</svg>
                    </div>
                    <div>
                        <div class="cfgp-item-titulo">{{ $item['titulo'] }}</div>
                        <div class="cfgp-item-desc">{{ $item['desc'] }}</div>
                    </div>
                    <svg class="cfgp-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
            @endforeach
        </div>
    </div>
@endforeach

@endsection
