@extends('layouts.admin')

@section('title', 'Reporte de Inventario')
@section('crumb', 'Inventario')

@push('styles')
    @vite(['resources/css/modules/reportes.css'])
@endpush

@section('content')

<x-page-header titulo="Reporte de Inventario" subtitulo="Snapshot del stock actual, valorizado a costo de compra">
    <x-slot:acciones>
        <a href="{{ route('admin.inventario.movimientos') }}" class="btn btn-secondary btn-sm"><span class="btn-text">← Inventario</span></a>
    </x-slot:acciones>
</x-page-header>

<div class="content-card">
    <form method="GET" class="rep-filtros-form">
        <div class="filtro-campo">
            <span>Categoría</span>
            <select name="categoria">
                <option value="">Todas</option>
                @foreach ($categorias as $categoria)
                    <option value="{{ $categoria->id }}" @selected((string) $filtros['categoria'] === (string) $categoria->id)>{{ $categoria->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="filtro-campo">
            <span>Marca</span>
            <select name="marca">
                <option value="">Todas</option>
                @foreach ($marcas as $marca)
                    <option value="{{ $marca->id }}" @selected((string) $filtros['marca'] === (string) $marca->id)>{{ $marca->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="filtro-campo" style="flex:1;min-width:200px;">
            <span>Buscar producto</span>
            <input type="text" name="q" value="{{ $filtros['q'] }}" placeholder="Nombre o código…">
        </div>
        <button type="submit" class="btn btn-primary">Filtrar</button>

        <div class="rep-exportar">
            <a href="{{ route('admin.inventario.reporte.excel', request()->query()) }}" class="btn btn-secondary btn-sm"><span class="btn-text">⬇ Excel</span></a>
            <a href="{{ route('admin.inventario.reporte.pdf', request()->query()) }}" class="btn btn-secondary btn-sm"><span class="btn-text">📄 PDF</span></a>
        </div>
    </form>

    <div class="rep-resumen">
        <div class="rep-kpi">
            <div class="rep-kpi-label">Productos</div>
            <div class="rep-kpi-val">{{ $resumen['productos'] }}</div>
        </div>
        <div class="rep-kpi">
            <div class="rep-kpi-label">Unidades en stock</div>
            <div class="rep-kpi-val">{{ number_format($resumen['unidades']) }}</div>
        </div>
        <div class="rep-kpi">
            <div class="rep-kpi-label">Valor total</div>
            <div class="rep-kpi-val">S/ {{ number_format($resumen['valor_total'], 2) }}</div>
        </div>
    </div>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Código</th><th>Producto</th><th>Categoría</th><th>Marca</th>
                    <th class="num">Stock</th><th class="num">Mín.</th><th class="num">Costo Unit.</th><th class="num">Valor</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($items as $fila)
                <tr>
                    <td>{{ $fila['codigo'] }}</td>
                    <td>{{ $fila['nombre'] }}</td>
                    <td>{{ $fila['categoria'] }}</td>
                    <td>{{ $fila['marca'] }}</td>
                    <td class="num" style="color:{{ $fila['stock'] <= $fila['minimo'] ? '#A8231F' : 'inherit' }};font-weight:{{ $fila['stock'] <= $fila['minimo'] ? '700' : '400' }};">{{ number_format($fila['stock']) }}</td>
                    <td class="num">{{ number_format($fila['minimo']) }}</td>
                    <td class="num">S/ {{ number_format($fila['costo'], 2) }}</td>
                    <td class="num">S/ {{ number_format($fila['valor'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--ink-3);">Sin productos para el filtro seleccionado.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
