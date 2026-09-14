@extends('layouts.admin')

@section('title', 'Configuración avanzada')
@section('crumb', 'Configuración')

@push('styles')
    @vite(['resources/css/modules/configuracion.css'])
@endpush

@section('content')

@php
    // Mismos trazos que config/menu.php, para que los iconos coincidan
    // con los del sidebar al llegar a la sección real.
    $iconos = [
        'bolsa' => '<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>',
        'documento' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
        'impresora' => '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
        'carrito' => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>',
        'pos' => '<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>',
        'dinero' => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
        'usuarios' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'inventario' => '<path d="M21 8V21H3V8"/><path d="M1 3h22v5H1z"/><line x1="10" y1="12" x2="14" y2="12"/>',
        'camion' => '<rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 5v3h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
    ];
@endphp

<x-page-header titulo="Configuración avanzada" subtitulo="Acceso rápido a las áreas del sistema">
    <x-slot:acciones>
        <a href="{{ route('admin.configuracion.index') }}" class="btn btn-secondary btn-sm"><span class="btn-text">← Configuración</span></a>
    </x-slot:acciones>
</x-page-header>

<div class="content-card" style="margin-bottom:20px;">
    <div class="form-group" style="margin:0;">
        <label for="avzBuscar">Buscar opción</label>
        <input type="text" id="avzBuscar" placeholder="Ej: compras, pedidos, inventario…" autocomplete="off">
    </div>
</div>

<div id="avzSecciones">
    @foreach ($secciones as $seccion => $items)
        <div class="cfgp-seccion" data-avz-seccion>
            <div class="cfgp-header">
                <div class="cfgp-titulo">{{ Str::upper($seccion) }}</div>
            </div>
            <div class="cfgp-grid">
                @foreach ($items as $item)
                    <a href="{{ route($item['route'], $item['query'] ?? []) }}" class="cfgp-item" data-avz-item
                       data-avz-texto="{{ Str::lower($seccion.' '.$item['titulo'].' '.$item['desc']) }}">
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

    <div id="avzSinResultados" class="content-card" style="text-align:center;padding:40px;color:var(--ink-3);" hidden>
        Ninguna opción coincide con la búsqueda.
    </div>
</div>

@endsection

@push('scripts')
<script>
document.getElementById('avzBuscar').addEventListener('input', function () {
    const texto = this.value.trim().toLowerCase();
    let algunoVisible = false;

    document.querySelectorAll('[data-avz-seccion]').forEach((seccion) => {
        let seccionVisible = false;
        seccion.querySelectorAll('[data-avz-item]').forEach((item) => {
            const coincide = item.dataset.avzTexto.includes(texto);
            item.hidden = !coincide;
            if (coincide) seccionVisible = true;
        });
        seccion.hidden = !seccionVisible;
        if (seccionVisible) algunoVisible = true;
    });

    document.getElementById('avzSinResultados').hidden = algunoVisible;
});
</script>
@endpush
