@extends('layouts.admin')

@section('title', 'Credenciales y certificados')
@section('crumb', 'Configuración')

@push('styles')
    @vite(['resources/css/modules/reportes.css'])
@endpush

@section('content')

<x-page-header titulo="Credenciales y certificados" subtitulo="Usuario y certificado SOL, credenciales de Guías Electrónicas">
    <x-slot:acciones>
        <a href="{{ route('admin.configuracion.index') }}" class="btn btn-secondary btn-sm"><span class="btn-text">← Configuración</span></a>
    </x-slot:acciones>
</x-page-header>

<div class="content-card" style="border:1px solid #f0c96a;background:#3a2f10;color:#f0d98a;margin-bottom:20px;">
    <strong>Sección avanzada</strong> — acá vive lo que usa Casa Drywall para facturar de verdad ante SUNAT (a través del sistema de facturación electrónica). Por ahora es de <strong>solo lectura</strong>: un dato mal cargado acá podría cortar la emisión de comprobantes, así que la edición se habilita más adelante, con más cuidado.
</div>

@if (! $empresa)
    <div class="content-card" style="text-align:center;padding:50px 20px;">
        <div style="font-size:15px;font-weight:700;color:var(--ink);margin-bottom:6px;">No se pudo conectar con el sistema de facturación electrónica</div>
        <p style="font-size:13px;color:var(--ink-3);">Revisa que el servicio esté disponible e intenta de nuevo.</p>
    </div>
@else
    <div class="content-card" style="margin-bottom:20px;">
        <h3 style="font-size:14px;margin-bottom:14px;">Certificado y acceso SOL</h3>
        <div class="table-container">
            <table class="table">
                <tbody>
                    <tr><th style="width:260px;">RUC</th><td>{{ $empresa['ruc'] ?? '—' }}</td></tr>
                    <tr><th>Usuario SOL</th><td>{{ $empresa['usuario_sol'] ?? '—' }}</td></tr>
                    <tr><th>Clave SOL</th><td>{{ ($empresa['clave_sol_configurada'] ?? false) ? '🔒 Configurada' : 'No configurada' }}</td></tr>
                    <tr><th>Certificado digital</th><td>{{ ($empresa['certificado_configurado'] ?? false) ? '🔒 Cargado' : 'No cargado' }}</td></tr>
                    <tr><th>Modo</th><td>{{ ($empresa['modo_produccion'] ?? false) ? 'Producción' : 'Pruebas' }}</td></tr>
                    <tr>
                        <th>Logo</th>
                        <td>
                            @if (! empty($empresa['logo_path']))
                                <img src="{{ $empresa['logo_path'] }}" alt="Logo" style="height:36px;">
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                    <tr><th>Favicon</th><td>—</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="content-card">
        <h3 style="font-size:14px;margin-bottom:14px;">Guías Electrónicas</h3>
        <div class="table-container">
            <table class="table">
                <tbody>
                    <tr><th style="width:260px;">RUC del proveedor</th><td>{{ $empresa['gre_ruc_proveedor'] ?? '—' }}</td></tr>
                    <tr><th>Usuario SOL</th><td>{{ $empresa['gre_usuario_sol'] ?? '—' }}</td></tr>
                    <tr><th>Client ID (beta)</th><td>{{ $empresa['gre_client_id_beta'] ?? '—' }}</td></tr>
                    <tr><th>Client ID (producción)</th><td>{{ $empresa['gre_client_id_produccion'] ?? '—' }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>
@endif

@endsection
