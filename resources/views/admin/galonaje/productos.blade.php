@extends('layouts.admin')

@section('title', 'Galonaje Productos')
@section('crumb', 'Análisis')

@section('content')

<x-page-header titulo="Matriz de Galonaje" subtitulo="Factor de galones por código de producto">
    <x-slot:acciones>
        <a href="{{ route('admin.galonaje.dashboard') }}" class="btn btn-secondary btn-sm">← Dashboard</a>
        <button type="button" class="btn btn-primary" data-modal="modalProductoGl">
            <span class="btn-icon">＋</span><span class="btn-text">Nuevo Producto</span>
        </button>
    </x-slot:acciones>
</x-page-header>

<div class="stats-grid">
    <x-stat-card :valor="number_format($productos->count())" etiqueta="Productos en la matriz" />
    <x-stat-card :valor="number_format(count($categorias))" etiqueta="Líneas de producto" />
    <x-stat-card :valor="number_format(count($presentaciones))" etiqueta="Presentaciones" />
</div>

<div class="content-card">
    <form method="GET" class="filtros">
        <label class="filtro-campo" for="q">
            <span>Buscar</span>
            <input type="search" id="q" name="q" value="{{ $busqueda }}" placeholder="Código o nombre…">
        </label>
        <label class="filtro-campo" for="linea">
            <span>Línea</span>
            <select id="linea" name="linea">
                <option value="">Todas</option>
                @foreach ($categorias as $codigo => $datos)
                <option value="{{ $codigo }}" @selected($lineaSel === $codigo)>
                {{ $codigo }} — {{ $datos['descripcion'] ?? '' }}
                </option>
                @endforeach
                </select>
        </label>
        <button type="submit" class="btn btn-primary btn-sm"><span class="btn-text">Filtrar</span></button>
    </form>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr><th>Código</th><th>Producto</th><th>Presentación</th><th>Factor (GL)</th><th>Línea</th><th>Acciones</th></tr>
            </thead>
            <tbody>
            @forelse ($productos as $producto)
                <tr>
                    <td><strong style="font-family:monospace;">{{ $producto->codigo }}</strong></td>
                    <td>{{ $producto->nombre ?: '—' }}</td>
                    <td>{{ $producto->presentacion ?: '—' }}</td>
                    <td><strong>{{ number_format($producto->factor, 4) }}</strong></td>
                    <td>{{ $producto->linea ?: '—' }}</td>
                    <td style="white-space:nowrap;">
                        <button type="button" class="btn btn-dark btn-sm"
                                data-modal="modalProductoGl"
                                data-campo-codigo="{{ $producto->codigo }}"
                                data-campo-nombre="{{ $producto->nombre }}"
                                data-campo-presentacion="{{ $producto->presentacion }}"
                                data-campo-factor="{{ $producto->factor }}"
                                data-campo-linea="{{ $producto->linea }}">
                            Editar
                        </button>
                        <button type="button" class="btn btn-danger btn-sm btn-eliminar" data-codigo="{{ $producto->codigo }}">
                            Eliminar
                        </button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;padding:40px;color:#666;">Sin productos en la matriz</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<x-modal id="modalProductoGl" titulo="Producto de la matriz">
    <form id="formProductoGl">
        @csrf
        <div class="form-grid">
            <div class="form-group">
                <label for="codigo">Código <span>*</span></label>
                <input type="text" id="codigo" name="codigo" required maxlength="50">
            </div>
            <div class="form-group">
                <label for="nombre">Nombre <span>*</span></label>
                <input type="text" id="nombre" name="nombre" required maxlength="255">
            </div>
            <div class="form-group">
                <label for="presentacion">Presentación <span>*</span></label>
                <select id="presentacion" name="presentacion" required>
                    @foreach (['UND', 'GAL', 'BAL', 'CIL'] as $pres)
                        <option value="{{ $pres }}">{{ $pres }}</option>
                    @endforeach
                    @foreach ($presentaciones as $codigo => $datos)
                        <option value="{{ $codigo }}">{{ $codigo }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="factor">Factor de galones <span>*</span></label>
                <input type="number" id="factor" name="factor" step="0.0001" min="0" required>
            </div>
            <div class="form-group">
                <label for="linea">Línea <span>*</span></label>
                <select id="linea" name="linea" required>
                    @foreach ($categorias as $codigo => $datos)
                        <option value="{{ $codigo }}">{{ $codigo }} — {{ $datos['descripcion'] ?? '' }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="header-btns" style="justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalProductoGl">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-modal>
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name="csrf-token"]').content;

document.getElementById('formProductoGl').addEventListener('submit', async function (e) {
    e.preventDefault();

    const respuesta = await fetch('{{ route('admin.galonaje.productos.store') }}', {
        method: 'POST',
        body: new FormData(this),
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
    });

    const datos = await respuesta.json();
    if (datos.ok) window.location.reload();
    else window.alert('No se pudo guardar el producto.');
});

document.querySelectorAll('.btn-eliminar').forEach((boton) => {
    boton.addEventListener('click', async () => {
        if (!await confirmar('¿Eliminar ' + boton.dataset.codigo + ' de la matriz?')) return;

        const respuesta = await fetch('{{ url('admin/galonaje/productos') }}/' + boton.dataset.codigo, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        });

        const datos = await respuesta.json();
        if (datos.ok) window.location.reload();
    });
});
</script>
@endpush
