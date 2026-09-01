@extends('layouts.admin')

@section('title', 'Categorías de Galonaje')
@section('crumb', 'Análisis')

@section('content')

<x-page-header titulo="Líneas de Producto" subtitulo="Categorías que agrupan los productos de la matriz de galonaje">
    <x-slot:acciones>
        <a href="{{ route('admin.galonaje.productos.index') }}" class="btn btn-secondary btn-sm">← Matriz</a>
        <button type="button" class="btn btn-primary" data-modal="modalCategoriaGl">
            <span class="btn-icon">＋</span><span class="btn-text">Nueva Línea</span>
        </button>
    </x-slot:acciones>
</x-page-header>

<div class="content-card">
    <div class="table-container">
        <table class="table">
            <thead><tr><th>Código</th><th>Descripción</th><th>Productos</th><th>Acciones</th></tr></thead>
            <tbody>
            @forelse ($categorias as $categoria)
                <tr>
                    <td><strong style="font-family:monospace;">{{ $categoria->codigo }}</strong></td>
                    <td>{{ $categoria->descripcion ?: '—' }}</td>
                    <td>{{ number_format($categoria->en_uso) }}</td>
                    <td style="white-space:nowrap;">
                        <button type="button" class="btn btn-dark btn-sm"
                                data-modal="modalCategoriaGl"
                                data-campo-codigo="{{ $categoria->codigo }}"
                                data-campo-codigo_orig="{{ $categoria->codigo }}"
                                data-campo-descripcion="{{ $categoria->descripcion }}">
                            Editar
                        </button>
                        <button type="button" class="btn btn-danger btn-sm btn-eliminar"
                                data-codigo="{{ $categoria->codigo }}" data-uso="{{ $categoria->en_uso }}">
                            Eliminar
                        </button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" style="text-align:center;padding:40px;color:#666;">Sin líneas registradas</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<x-modal id="modalCategoriaGl" titulo="Línea de producto">
    <form id="formCategoriaGl">
        @csrf
        <input type="hidden" name="codigo_orig" value="">

        <div class="form-grid">
            <div class="form-group">
                <label for="codigo">Código <span>*</span></label>
                <input type="text" id="codigo" name="codigo" required maxlength="20" style="text-transform:uppercase;">
            </div>
            <div class="form-group">
                <label for="descripcion">Descripción</label>
                <input type="text" id="descripcion" name="descripcion" maxlength="255">
            </div>
        </div>

        <div class="header-btns" style="justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalCategoriaGl">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-modal>
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name="csrf-token"]').content;

document.getElementById('formCategoriaGl').addEventListener('submit', async function (e) {
    e.preventDefault();

    const respuesta = await fetch('{{ route('admin.galonaje.categorias.store') }}', {
        method: 'POST',
        body: new FormData(this),
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
    });

    const datos = await respuesta.json();
    if (datos.ok) window.location.reload();
    else window.alert('No se pudo guardar la línea.');
});

document.querySelectorAll('.btn-eliminar').forEach((boton) => {
    boton.addEventListener('click', async () => {
        const enUso = parseInt(boton.dataset.uso, 10);

        const mensaje = enUso > 0
            ? `La línea ${boton.dataset.codigo} está usada por ${enUso} producto(s). ¿Eliminarla de todos modos?`
            : `¿Eliminar la línea ${boton.dataset.codigo}?`;

        if (!window.confirm(mensaje)) return;

        const cuerpo = new FormData();
        cuerpo.append('forzar', enUso > 0 ? '1' : '0');

        const respuesta = await fetch('{{ url('admin/galonaje/categorias') }}/' + boton.dataset.codigo, {
            method: 'POST',
            body: (cuerpo.append('_method', 'DELETE'), cuerpo),
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        });

        const datos = await respuesta.json();
        if (datos.ok) window.location.reload();
        else window.alert('No se pudo eliminar la línea.');
    });
});
</script>
@endpush
