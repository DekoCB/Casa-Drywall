@extends('layouts.admin')

@section('title', $valorizado ? 'Kardex Valorizado' : 'Reporte Kardex')
@section('crumb', 'Inventario')

@push('styles')
    @vite(['resources/css/modules/reportes.css'])
@endpush

@section('content')

@php
    $productosJs = \App\Models\Producto::activos()->orderBy('nombre')
        ->get(['id', 'codigo', 'nombre'])
        ->map(fn ($p) => ['id' => $p->id, 'codigo' => $p->codigo, 'nombre' => $p->nombre])->values();
    $rutaBase = $valorizado ? 'admin.inventario.kardex-valorizado' : 'admin.inventario.kardex';
@endphp

<x-page-header :titulo="$valorizado ? 'Kardex Valorizado' : 'Reporte Kardex'" subtitulo="Historial de movimientos de un producto, con saldo corrido">
    <x-slot:acciones>
        <a href="{{ route('admin.inventario.movimientos') }}" class="btn btn-secondary btn-sm"><span class="btn-text">← Inventario</span></a>
    </x-slot:acciones>
</x-page-header>

<div class="content-card">
    <form method="GET" action="{{ route($rutaBase) }}" class="rep-filtros-form" id="formKardex">
        <div class="form-group" style="flex:1;min-width:240px;margin-bottom:0;">
            <label>Producto</label>
            <div class="nv-buscador" id="buscador-kardex">
                <input type="text" class="nv-buscar-input" id="kardexBuscar" autocomplete="off"
                       placeholder="Buscar producto por nombre o código…"
                       value="{{ $producto?->nombre }}">
                <div class="nv-dropdown" id="kardexDropdown"></div>
                <input type="hidden" name="producto_id" id="kardexProductoId" value="{{ $producto?->id }}">
            </div>
        </div>
        <div class="filtro-campo">
            <span>Desde</span>
            <input type="date" name="desde" value="{{ $filtros['desde'] }}">
        </div>
        <div class="filtro-campo">
            <span>Hasta</span>
            <input type="date" name="hasta" value="{{ $filtros['hasta'] }}">
        </div>
        <button type="submit" class="btn btn-primary">Buscar</button>

        @if ($producto)
            <div class="rep-exportar">
                <a href="{{ route($rutaBase.'.excel', request()->query()) }}" class="btn btn-secondary btn-sm"><span class="btn-text">⬇ Excel</span></a>
                <a href="{{ route($rutaBase.'.pdf', request()->query()) }}" class="btn btn-secondary btn-sm"><span class="btn-text">📄 PDF</span></a>
            </div>
        @endif
    </form>

    @if (! $producto)
        <div style="text-align:center;padding:50px 20px;color:var(--ink-3);">
            Busca un producto arriba para ver su {{ $valorizado ? 'Kardex valorizado' : 'Kardex' }}.
        </div>
    @else
        <div class="rep-resumen">
            <div class="rep-kpi">
                <div class="rep-kpi-label">Producto</div>
                <div class="rep-kpi-val" style="font-size:15px;">{{ $producto->nombre }}</div>
                <div class="rep-kpi-sub">{{ $producto->codigo ?: '—' }}</div>
            </div>
            <div class="rep-kpi">
                <div class="rep-kpi-label">Movimientos</div>
                <div class="rep-kpi-val">{{ $resumen['movimientos'] }}</div>
            </div>
            <div class="rep-kpi">
                <div class="rep-kpi-label">Stock actual</div>
                <div class="rep-kpi-val">{{ $resumen['stock_actual'] }}</div>
            </div>
            @if ($valorizado)
                <div class="rep-kpi">
                    <div class="rep-kpi-label">Valor actual</div>
                    <div class="rep-kpi-val">S/ {{ number_format($resumen['valor_actual'], 2) }}</div>
                </div>
            @endif
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Fecha</th><th>Almacén</th><th>Tipo</th><th class="num">Cantidad</th>
                        @if ($valorizado)
                            <th class="num">Costo Unit.</th><th class="num">Valor Mov.</th><th class="num">Stock Nuevo</th><th class="num">Saldo Valorizado</th>
                        @else
                            <th class="num">Stock Ant.</th><th class="num">Stock Nuevo</th><th>Motivo</th><th>Usuario</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                @forelse ($items as $fila)
                    <tr>
                        <td>{{ $fila['fecha']->format('d/m/Y H:i') }}</td>
                        <td>{{ $fila['almacen'] }}</td>
                        <td><span class="rep-badge estado-{{ in_array($fila['tipo'], ['entrada','traslado'], true) ? 'alta' : ($fila['tipo'] === 'ajuste' ? 'media' : 'baja') }}">{{ ucfirst($fila['tipo']) }}</span></td>
                        <td class="num">{{ number_format($fila['cantidad']) }}</td>
                        @if ($valorizado)
                            <td class="num">S/ {{ number_format($fila['costo_unitario'], 2) }}</td>
                            <td class="num">S/ {{ number_format($fila['valor_movimiento'], 2) }}</td>
                            <td class="num">{{ $fila['stock_nuevo'] }}</td>
                            <td class="num">S/ {{ number_format($fila['saldo_valorizado'], 2) }}</td>
                        @else
                            <td class="num">{{ $fila['stock_anterior'] }}</td>
                            <td class="num">{{ $fila['stock_nuevo'] }}</td>
                            <td>{{ $fila['motivo'] }}</td>
                            <td>{{ $fila['usuario'] }}</td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--ink-3);">Sin movimientos para el periodo seleccionado.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>

@endsection

@push('scripts')
<script>
const PRODUCTOS_KARDEX = @json($productosJs);
const buscarInput = document.getElementById('kardexBuscar');
const dropdown = document.getElementById('kardexDropdown');
const campoId = document.getElementById('kardexProductoId');
const form = document.getElementById('formKardex');

buscarInput.addEventListener('input', () => {
    const termino = buscarInput.value.trim().toUpperCase();
    campoId.value = '';
    if (termino === '') { dropdown.classList.remove('activo'); return; }

    const resultados = PRODUCTOS_KARDEX.filter((p) =>
        p.nombre.toUpperCase().includes(termino) || (p.codigo || '').toUpperCase().includes(termino)
    ).slice(0, 15);

    if (!resultados.length) {
        dropdown.innerHTML = '<div class="nv-sin-resultados">Sin resultados</div>';
        dropdown.classList.add('activo');
        return;
    }

    dropdown.innerHTML = resultados.map((p, i) =>
        `<div class="nv-item" data-idx="${i}"><div class="nv-item-top"><span class="nv-item-cod">${p.codigo || '—'}</span><span class="nv-item-desc">${p.nombre}</span></div></div>`
    ).join('');
    dropdown.querySelectorAll('.nv-item').forEach((el) => {
        el.addEventListener('click', () => {
            const p = resultados[Number(el.dataset.idx)];
            buscarInput.value = p.nombre;
            campoId.value = p.id;
            dropdown.classList.remove('activo');
            form.submit();
        });
    });
    dropdown.classList.add('activo');
});

document.addEventListener('click', (e) => {
    if (!document.getElementById('buscador-kardex').contains(e.target)) dropdown.classList.remove('activo');
});
</script>
@endpush
