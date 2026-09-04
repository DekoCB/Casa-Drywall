@extends('layouts.admin')

@section('title', 'Reportes')
@section('crumb', 'Centro de Reportes')

@push('styles')
    @vite(['resources/css/modules/reportes.css'])
@endpush

@section('content')

@php
    $iconos = [
        'documento'  => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
        'cotizacion' => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
        'cliente'    => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'abc'        => '<path d="M3 3v18h18"/><rect x="7" y="13" width="3" height="5"/><rect x="12" y="9" width="3" height="9"/><rect x="17" y="5" width="3" height="13"/>',
        'compra'     => '<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>',
        'rotacion'   => '<polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>',
        'aging'      => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'pedido'     => '<path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/>',
        'guia'       => '<rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 5v3h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
    ];
@endphp

<x-page-header titulo="Centro de Reportes" subtitulo="Encuentra y accede a cualquier reporte de tu negocio" />

<div class="rep-tabs" id="repTabs">
    <button type="button" class="rep-tab active" data-rep-tab="todos">Todos</button>
    @foreach ($areas as $area => $items)
        <button type="button" class="rep-tab" data-rep-tab="{{ Str::slug($area) }}">{{ $area }}</button>
    @endforeach
</div>

@foreach ($areas as $area => $items)
    <div class="rep-area" data-rep-area="{{ Str::slug($area) }}">
        <div class="rep-area-titulo">{{ $area }}</div>
        <div class="rep-grid">
            @foreach ($items as $item)
                <a href="{{ route($item['route'], $item['query'] ?? []) }}" class="rep-card @if($item['destacado'] ?? false) destacado @endif">
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
