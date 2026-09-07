@extends('layouts.admin')

@section('title', 'Utilidades')
@section('crumb', 'Ventas')

@push('styles')
    @vite(['resources/css/modules/reportes.css'])
@endpush

@section('content')

<x-page-header titulo="Utilidades por Producto" subtitulo="Ingreso menos costo de compra, por producto vendido en el periodo">
    <x-slot:acciones>
        <a href="{{ route('admin.ventas.index') }}" class="btn btn-secondary btn-sm"><span class="btn-text">← Ventas</span></a>
    </x-slot:acciones>
</x-page-header>

<div class="content-card">
    <form method="GET" class="rep-filtros-form">
        <div class="filtro-campo">
            <span>Fecha inicio</span>
            <input type="date" name="desde" value="{{ $filtros['desde'] }}">
        </div>
        <div class="filtro-campo">
            <span>Fecha fin</span>
            <input type="date" name="hasta" value="{{ $filtros['hasta'] }}">
        </div>
        <div class="filtro-campo" style="flex:1;min-width:200px;">
            <span>Buscar producto</span>
            <input type="text" name="q" value="{{ $filtros['q'] }}" placeholder="Nombre o código…">
        </div>
        <button type="submit" class="btn btn-primary">Buscar</button>

        <div class="rep-exportar">
            <a href="{{ route('admin.reportes.utilidad.excel', request()->query()) }}" class="btn btn-secondary btn-sm"><span class="btn-text">⬇ Excel</span></a>
            <a href="{{ route('admin.reportes.utilidad.pdf', request()->query()) }}" class="btn btn-secondary btn-sm"><span class="btn-text">📄 PDF</span></a>
        </div>
    </form>

    <div class="rep-resumen">
        <div class="rep-kpi">
            <div class="rep-kpi-label">Ingreso</div>
            <div class="rep-kpi-val">S/ {{ number_format($resumen['ingreso'], 2) }}</div>
        </div>
        <div class="rep-kpi">
            <div class="rep-kpi-label">Costo</div>
            <div class="rep-kpi-val">S/ {{ number_format($resumen['costo'], 2) }}</div>
        </div>
        <div class="rep-kpi clase-a">
            <div class="rep-kpi-label">Utilidad</div>
            <div class="rep-kpi-val">S/ {{ number_format($resumen['utilidad'], 2) }}</div>
            <div class="rep-kpi-sub">{{ $resumen['margen_pct'] }}% de margen</div>
        </div>
    </div>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Código</th><th>Producto</th><th class="num">Cant.</th>
                    <th class="num">Ingreso</th><th class="num">Costo</th><th class="num">Utilidad</th><th class="num">Margen %</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($items as $fila)
                <tr>
                    <td>{{ $fila['codigo'] }}</td>
                    <td>{{ $fila['nombre'] }}</td>
                    <td class="num">{{ number_format($fila['cantidad']) }}</td>
                    <td class="num">S/ {{ number_format($fila['ingreso'], 2) }}</td>
                    <td class="num">S/ {{ number_format($fila['costo'], 2) }}</td>
                    <td class="num" style="color:{{ $fila['utilidad'] < 0 ? '#A8231F' : '#1f6b5e' }};font-weight:600;">S/ {{ number_format($fila['utilidad'], 2) }}</td>
                    <td class="num">{{ number_format($fila['margen_pct'], 1) }}%</td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--ink-3);">Sin ventas en el periodo seleccionado.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
