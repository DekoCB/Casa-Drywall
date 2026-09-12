@extends('layouts.admin')

@section('title', 'Configuración')
@section('crumb', 'Configuración')

@push('styles')
    @vite(['resources/css/modules/reportes.css'])
@endpush

@section('content')

@php
    $iconos = [
        'empresa'   => '<path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 9h1"/><path d="M14 9h1"/><path d="M9 13h1"/><path d="M14 13h1"/><path d="M10 21v-4h4v4"/>',
        'local'     => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
        'pagos'     => '<rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>',
        'categoria' => '<path d="M20 7h-9"/><path d="M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/>',
        'marca'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',
        'almacen'   => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
        'personal'  => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    ];
@endphp

<x-page-header titulo="Configuración" subtitulo="Datos del negocio, catálogo y accesos del equipo" />

<div class="rep-tabs" id="repTabs">
    <button type="button" class="rep-tab active" data-rep-tab="todos">Todos</button>
    @foreach ($secciones as $seccion => $items)
        <button type="button" class="rep-tab" data-rep-tab="{{ Str::slug($seccion) }}">{{ $seccion }}</button>
    @endforeach
</div>

@foreach ($secciones as $seccion => $items)
    <div class="rep-area" data-rep-area="{{ Str::slug($seccion) }}">
        <div class="rep-area-titulo">{{ $seccion }}</div>
        <div class="rep-grid">
            @foreach ($items as $item)
                <a href="{{ route($item['route'], $item['query'] ?? []) }}" class="rep-card">
                    <div class="rep-card-icono">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $iconos[$item['icon']] !!}</svg>
                    </div>
                    <div>
                        <div class="rep-card-titulo">{{ $item['titulo'] }}</div>
                        <div class="rep-card-desc">{{ $item['desc'] }}</div>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
@endforeach

@endsection

@push('scripts')
<script>
(function () {
    const tabs = document.querySelectorAll('#repTabs .rep-tab');
    const areas = document.querySelectorAll('[data-rep-area]');

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            tabs.forEach((t) => t.classList.remove('active'));
            tab.classList.add('active');

            const destino = tab.dataset.repTab;
            areas.forEach((area) => {
                area.style.display = (destino === 'todos' || area.dataset.repArea === destino) ? '' : 'none';
            });
        });
    });
})();
</script>
@endpush
