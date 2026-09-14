@extends('layouts.admin')

@section('title', 'Datos de la Empresa')
@section('crumb', 'Configuración')

@push('styles')
    @vite(['resources/css/modules/configuracion.css'])
@endpush

@section('content')

@php
    $iconos = [
        'empresa' => '<path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 9h1"/><path d="M14 9h1"/><path d="M9 13h1"/><path d="M14 13h1"/><path d="M10 21v-4h4v4"/>',
        'local'   => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
        'pagos'   => '<rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>',
    ];
@endphp

<x-page-header titulo="Datos de la Empresa" subtitulo="Mi Empresa, Mi Local y Pagos y Bancos">
    <x-slot:acciones>
        <a href="{{ route('admin.configuracion.index') }}" class="btn btn-secondary btn-sm"><span class="btn-text">← Configuración</span></a>
    </x-slot:acciones>
</x-page-header>

<div class="cfgp-seccion">
    <div class="cfgp-grid">
        @foreach ($items as $item)
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

@endsection
