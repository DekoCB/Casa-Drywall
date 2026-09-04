@extends('layouts.admin')

@section('title', 'Rotación de Inventario')
@section('crumb', 'Reportes')

@push('styles')
    @vite(['resources/css/modules/reportes.css'])
@endpush

@section('content')

<x-page-header titulo="Rotación de Inventario" subtitulo="Detecta productos estancados y de alta rotación en el periodo elegido">
    <x-slot:acciones>
        <a href="{{ route('admin.reportes.index') }}" class="btn btn-secondary btn-sm"><span class="btn-text">← Reportes</span></a>
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
        <button type="submit" class="btn btn-primary">Buscar</button>

        <div class="rep-exportar">
            <a href="{{ route('admin.reportes.rotacion.excel', request()->query()) }}" class="btn btn-secondary btn-sm"><span class="btn-text">⬇ Excel</span></a>
            <a href="{{ route('admin.reportes.rotacion.pdf', request()->query()) }}" class="btn btn-secondary btn-sm"><span class="btn-text">📄 PDF</span></a>
        </div>
    </form>

    <div class="rep-resumen">
        <div class="rep-kpi">
            <div class="rep-kpi-label">Baja rotación</div>
            <div class="rep-kpi-val" style="color:#A8231F;">{{ $resumen['baja'] }}</div>
            <div class="rep-kpi-sub">sin ventas en el periodo</div>
        </div>
        <div class="rep-kpi">
            <div class="rep-kpi-label">Media</div>
            <div class="rep-kpi-val" style="color:#8A5A12;">{{ $resumen['media'] }}</div>
        </div>
        <div class="rep-kpi">
            <div class="rep-kpi-label">Alta</div>
            <div class="rep-kpi-val" style="color:#11704A;">{{ $resumen['alta'] }}</div>
        </div>
        <div class="rep-kpi">
            <div class="rep-kpi-label">Total productos</div>
            <div class="rep-kpi-val">{{ $resumen['total'] }}</div>
        </div>
    </div>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Código</th><th>Producto</th><th class="num">Stock</th><th class="num">Mín.</th>
                    <th class="num">Vendido</th><th class="num">Rotación</th><th class="num">Días Stock</th><th>Estado</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($items as $fila)
                <tr>
                    <td>{{ $fila['codigo'] }}</td>
                    <td>{{ $fila['nombre'] }}</td>
                    <td class="num" style="color:{{ $fila['stock'] > 0 ? 'inherit' : '#A8231F' }};">{{ number_format($fila['stock']) }}</td>
                    <td class="num">{{ number_format($fila['minimo']) }}</td>
                    <td class="num">{{ number_format($fila['vendido']) }}</td>
                    <td class="num">{{ $fila['rotacion'] }}x</td>
                    <td class="num">{{ $fila['dias_stock'] ?? '∞' }}</td>
                    <td><span class="rep-badge estado-{{ strtolower($fila['estado']) }}">{{ $fila['estado'] }}</span></td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--ink-3);">No hay productos activos.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
