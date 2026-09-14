@extends('layouts.admin')

@php
    $titulo = $tipo === 'pdf' ? 'Plantillas PDF' : 'Tickets de venta';
    $subtitulo = $tipo === 'pdf' ? 'Diseño de tus facturas y boletas en formato PDF' : 'Diseño de tu ticket de venta en formato 80mm';
    $activa = $plantillas->$tipo;
@endphp

@section('title', $titulo)
@section('crumb', 'Configuración')

@push('styles')
    @vite(['resources/css/modules/configuracion.css'])
@endpush

@section('content')

<x-page-header :titulo="$titulo" :subtitulo="$subtitulo">
    <x-slot:acciones>
        <a href="{{ route('admin.configuracion.index') }}" class="btn btn-secondary btn-sm"><span class="btn-text">← Configuración</span></a>
    </x-slot:acciones>
</x-page-header>

<div class="content-card">
    <div class="cfgp-form-sub" style="margin-bottom:18px;">
        Plantilla actual: <strong style="color:var(--ink);">{{ \App\Models\PlantillaImpresion::PLANTILLAS[$activa]['nombre'] ?? $activa }}</strong>
        — {{ $tipo === 'pdf' ? 'se aplica al imprimir en A4 desde el comprobante de venta.' : 'se aplica al imprimir el ticket de 80mm desde el POS o el comprobante de venta.' }}
    </div>

    <div class="cfgp-dropzones">
        @foreach (\App\Models\PlantillaImpresion::PLANTILLAS as $clave => $info)
            <div class="content-card" style="border:2px solid {{ $activa === $clave ? 'var(--brand)' : 'var(--line)' }};background:var(--surface-2);">
                <div class="cfgp-form-titulo">{{ $info['nombre'] }}</div>
                <div class="cfgp-form-sub" style="margin:6px 0 16px;">{{ $info['desc'] }}</div>

                @if ($activa === $clave)
                    <span class="btn btn-secondary btn-sm" style="pointer-events:none;">✓ Plantilla activa</span>
                @else
                    <form method="POST" action="{{ route('admin.configuracion.plantillas.activar', $tipo) }}">
                        @csrf
                        <input type="hidden" name="clave" value="{{ $clave }}">
                        <button type="submit" class="btn btn-primary btn-sm">Activar plantilla</button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
</div>

@endsection
