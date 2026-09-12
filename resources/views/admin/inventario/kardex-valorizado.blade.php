@extends('layouts.admin')

@section('title', 'Kardex Valorizado')
@section('crumb', 'Inventario')

@push('styles')
    @vite(['resources/css/modules/reportes.css'])
@endpush

@section('content')

<x-page-header titulo="Kardex Valorizado" subtitulo="Costo y valor de stock por producto, a costo de compra actual">
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
            <a href="{{ route('admin.inventario.kardex-valorizado.excel', request()->query()) }}" class="btn btn-secondary btn-sm"><span class="btn-text">⬇ Excel</span></a>
            <a href="{{ route('admin.inventario.kardex-valorizado.pdf', request()->query()) }}" class="btn btn-secondary btn-sm"><span class="btn-text">📄 PDF</span></a>
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
            <div class="rep-kpi-sub">A costo de compra actual</div>
        </div>
    </div>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Código</th><th>Producto</th><th>Categoría</th><th>Marca</th><th>Unidad</th>
                    <th class="num">Stock</th><th class="num">Costo Ponderado</th><th class="num">Costo de Producto</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($items as $fila)
                <tr>
                    <td>{{ $fila['codigo'] }}</td>
                    <td>{{ $fila['nombre'] }}</td>
                    <td>{{ $fila['categoria'] }}</td>
                    <td>{{ $fila['marca'] }}</td>
                    <td>{{ $fila['unidad'] }}</td>
                    <td class="num">{{ number_format($fila['stock']) }}</td>
                    <td class="num">S/ {{ number_format($fila['costo_ponderado'], 2) }}</td>
                    <td class="num">S/ {{ number_format($fila['costo_producto'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--ink-3);">Sin productos para el filtro seleccionado.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
