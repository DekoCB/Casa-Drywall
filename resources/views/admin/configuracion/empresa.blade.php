@extends('layouts.admin')

@section('title', 'Mi Empresa')
@section('crumb', 'Configuración')

@push('styles')
    @vite(['resources/css/modules/reportes.css'])
@endpush

@section('content')

<x-page-header titulo="Mi Empresa" subtitulo="RUC, razón social, dirección y datos de contacto">
    <x-slot:acciones>
        <a href="{{ route('admin.configuracion.index') }}" class="btn btn-secondary btn-sm"><span class="btn-text">← Configuración</span></a>
    </x-slot:acciones>
</x-page-header>

<div class="content-card">
    <p style="color:var(--ink-3);font-size:13px;margin-bottom:16px;">
        Por ahora esta es una vista de solo lectura de los datos configurados en el sistema — pronto vas a poder editarlos directamente aquí.
    </p>

    <div class="table-container">
        <table class="table">
            <tbody>
                <tr><th style="width:220px;">Razón social</th><td>{{ $empresa['razon_social'] ?: '—' }}</td></tr>
                <tr><th>RUC</th><td>{{ $empresa['ruc'] ?: '—' }}</td></tr>
                <tr><th>Dirección</th><td>{{ $empresa['direccion'] ?: '—' }}</td></tr>
                <tr><th>Teléfono</th><td>{{ $empresa['telefono'] ?: '—' }}</td></tr>
                <tr><th>Correo</th><td>{{ $empresa['email'] ?: '—' }}</td></tr>
            </tbody>
        </table>
    </div>
</div>

@endsection
