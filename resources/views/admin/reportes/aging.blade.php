@extends('layouts.admin')

@section('title', 'Cuentas por Cobrar')
@section('crumb', 'Reportes')

@push('styles')
    @vite(['resources/css/modules/reportes.css'])
@endpush

@section('content')

<x-page-header titulo="Aging de Cuentas por Cobrar" subtitulo="Saldos pendientes por cliente, agrupados por antigüedad de vencimiento">
    <x-slot:acciones>
        <a href="{{ route('admin.reportes.index') }}" class="btn btn-secondary btn-sm"><span class="btn-text">← Reportes</span></a>
    </x-slot:acciones>
</x-page-header>

<div class="content-card">
    <div class="rep-filtros-form">
        <div class="rep-exportar" style="margin-left:0;">
            <a href="{{ route('admin.reportes.aging.excel') }}" class="btn btn-secondary btn-sm"><span class="btn-text">⬇ Excel</span></a>
            <a href="{{ route('admin.reportes.aging.pdf') }}" class="btn btn-secondary btn-sm"><span class="btn-text">📄 PDF</span></a>
        </div>
    </div>

    <div class="rep-resumen">
        <div class="rep-kpi">
            <div class="rep-kpi-label">Clientes con saldo</div>
            <div class="rep-kpi-val">{{ $resumen['clientes'] }}</div>
        </div>
        <div class="rep-kpi">
            <div class="rep-kpi-label">Total pendiente</div>
            <div class="rep-kpi-val">S/ {{ number_format($resumen['total'], 2) }}</div>
        </div>
        <div class="rep-kpi">
            <div class="rep-kpi-label">Vencido +90 días</div>
            <div class="rep-kpi-val" style="color:#A8231F;">S/ {{ number_format($resumen['vencido90'], 2) }}</div>
        </div>
    </div>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Cliente</th><th>RUC/DNI</th><th class="num">Docs</th><th class="num">Vigente</th>
                    <th class="num">1-30d</th><th class="num">31-60d</th><th class="num">61-90d</th>
                    <th class="num">+90d</th><th class="num">Total</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($items as $fila)
                <tr>
                    <td>{{ $fila['cliente'] }}</td>
                    <td>{{ $fila['ruc'] }}</td>
                    <td class="num">{{ $fila['docs'] }}</td>
                    <td class="num">{{ $fila['vigente'] > 0 ? number_format($fila['vigente'], 2) : '—' }}</td>
                    <td class="num">{{ $fila['d1_30'] > 0 ? number_format($fila['d1_30'], 2) : '—' }}</td>
                    <td class="num">{{ $fila['d31_60'] > 0 ? number_format($fila['d31_60'], 2) : '—' }}</td>
                    <td class="num">{{ $fila['d61_90'] > 0 ? number_format($fila['d61_90'], 2) : '—' }}</td>
                    <td class="num" style="color:{{ $fila['d90_mas'] > 0 ? '#A8231F' : 'inherit' }};font-weight:{{ $fila['d90_mas'] > 0 ? '700' : '400' }};">
                        {{ $fila['d90_mas'] > 0 ? number_format($fila['d90_mas'], 2) : '—' }}
                    </td>
                    <td class="num" style="font-weight:700;">S/ {{ number_format($fila['total'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--ink-3);">Sin cobranzas pendientes.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
