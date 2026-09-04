@extends('layouts.admin')

@section('title', 'Análisis ABC')
@section('crumb', 'Reportes')

@push('styles')
    @vite(['resources/css/modules/reportes.css'])
@endpush

@section('content')

<x-page-header titulo="Análisis ABC de Productos" subtitulo="Clasifica tus productos por el ingreso que generan: A (80%), B (15%) y C (5%)">
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
        <div class="filtro-campo" style="flex:1;min-width:200px;">
            <span>Buscar producto</span>
            <input type="text" name="q" value="{{ $filtros['q'] }}" placeholder="Nombre o código…">
        </div>
        <button type="submit" class="btn btn-primary">Buscar</button>

        <div class="rep-exportar">
            <a href="{{ route('admin.reportes.abc.excel', request()->query()) }}" class="btn btn-secondary btn-sm"><span class="btn-text">⬇ Excel</span></a>
            <a href="{{ route('admin.reportes.abc.pdf', request()->query()) }}" class="btn btn-secondary btn-sm"><span class="btn-text">📄 PDF</span></a>
        </div>
    </form>

    <div class="rep-resumen">
        <div class="rep-kpi clase-a">
            <div class="rep-kpi-label">Clase A</div>
            <div class="rep-kpi-val">{{ $resumen['A']['n'] }} productos</div>
            <div class="rep-kpi-sub">{{ $resumen['A']['etiqueta'] }} del ingreso</div>
        </div>
        <div class="rep-kpi clase-b">
            <div class="rep-kpi-label">Clase B</div>
            <div class="rep-kpi-val">{{ $resumen['B']['n'] }} productos</div>
            <div class="rep-kpi-sub">{{ $resumen['B']['etiqueta'] }} del ingreso</div>
        </div>
        <div class="rep-kpi clase-c">
            <div class="rep-kpi-label">Clase C</div>
            <div class="rep-kpi-val">{{ $resumen['C']['n'] }} productos</div>
            <div class="rep-kpi-sub">{{ $resumen['C']['etiqueta'] }} del ingreso</div>
        </div>
        <div class="rep-kpi">
            <div class="rep-kpi-label">Total</div>
            <div class="rep-kpi-val">S/ {{ number_format($resumen['total'], 2) }}</div>
            <div class="rep-kpi-sub">{{ $items->count() }} productos vendidos</div>
        </div>
    </div>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th><th>Código</th><th>Producto</th><th class="num">Cant.</th>
                    <th class="num">Ingreso</th><th class="num">%</th><th class="num">% Acum.</th><th>Clase</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($items as $fila)
                <tr>
                    <td>{{ $fila['n'] }}</td>
                    <td>{{ $fila['codigo'] }}</td>
                    <td>{{ $fila['nombre'] }}</td>
                    <td class="num">{{ number_format($fila['cantidad']) }}</td>
                    <td class="num">S/ {{ number_format($fila['ingreso'], 2) }}</td>
                    <td class="num">{{ number_format($fila['pct'], 2) }}%</td>
                    <td class="num">{{ number_format($fila['acumulado'], 2) }}%</td>
                    <td><span class="rep-badge clase-{{ strtolower($fila['clase']) }}">{{ $fila['clase'] }}</span></td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--ink-3);">Sin ventas en el periodo seleccionado.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
