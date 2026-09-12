@extends('layouts.admin')

@section('title', 'Pagos y Bancos')
@section('crumb', 'Configuración')

@push('styles')
    @vite(['resources/css/modules/reportes.css'])
@endpush

@section('content')

<x-page-header titulo="Pagos y Bancos" subtitulo="Cuentas bancarias para que te paguen tus clientes">
    <x-slot:acciones>
        <a href="{{ route('admin.configuracion.index') }}" class="btn btn-secondary btn-sm"><span class="btn-text">← Configuración</span></a>
    </x-slot:acciones>
</x-page-header>

<div class="content-card">
    <p style="color:var(--ink-3);font-size:13px;margin-bottom:16px;">
        Por ahora esta es una vista de solo lectura de las cuentas configuradas en el sistema — pronto vas a poder agregarlas y editarlas directamente aquí.
    </p>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr><th>Banco</th><th>Moneda</th><th>Titular</th><th>Cuenta</th><th>CCI</th></tr>
            </thead>
            <tbody>
            @forelse ($cuentas as $cuenta)
                <tr>
                    <td>{{ $cuenta['banco'] ?? '—' }}</td>
                    <td>{{ $cuenta['moneda'] ?? '—' }}</td>
                    <td>{{ $cuenta['titular'] ?? '—' }}</td>
                    <td>{{ $cuenta['cuenta'] ?? '—' }}</td>
                    <td>{{ $cuenta['cci'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align:center;padding:40px;color:var(--ink-3);">No hay cuentas bancarias configuradas.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
