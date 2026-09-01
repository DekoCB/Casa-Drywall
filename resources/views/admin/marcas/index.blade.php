@extends('layouts.admin')

@section('title', 'Marcas')
@section('crumb', 'Inventario')

@section('content')

<x-page-header titulo="Marcas" subtitulo="Marcas de los productos que comercializa la empresa">
    <x-slot:acciones>
        <a href="{{ route('admin.productos.index') }}" class="btn btn-secondary btn-sm">
            <span class="btn-text">← Productos</span>
        </a>
        <button type="button" class="btn btn-primary" data-modal="modalMarca">
            <span class="btn-icon">＋</span><span class="btn-text">Nueva Marca</span>
        </button>
    </x-slot:acciones>
</x-page-header>

<div class="content-card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Descripción</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($marcas as $marca)
                <tr>
                    <td>{{ $marca->nombre ?: '—' }}</td>
                    <td>{{ $marca->descripcion ?: '—' }}</td>
                    <td style="white-space:nowrap;">
                        <button type="button" class="btn btn-dark btn-sm"
                                data-modal="modalMarca"
                                data-campo-registro_id="{{ $marca->id }}"
                                data-campo-nombre="{{ $marca->nombre }}"
                                data-campo-descripcion="{{ $marca->descripcion }}"
                        >
                            Editar
                        </button>
                        <form method="POST" action="{{ route('admin.marcas.destroy', $marca) }}"
                              style="display:inline;" data-confirmar="¿Eliminar este registro?">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" style="text-align:center;padding:40px;color:#666;">Sin registros</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $marcas->links() }}
</div>

<x-modal id="modalMarca" titulo="Nueva Marca">
    <form method="POST" action="{{ route('admin.marcas.store') }}" id="formMarca">
        @csrf
        <input type="hidden" name="registro_id" value="">

        <div class="form-grid">
            <div class="form-group">
                <label for="nombre">Nombre <span>*</span></label>
                <input type="text" id="nombre" name="nombre" required maxlength="150">
            </div>
            <div class="form-group">
                <label for="descripcion">Descripción</label>
                <input type="text" id="descripcion" name="descripcion" maxlength="255">
            </div>
        </div>

        <div class="header-btns" style="justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalMarca">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-modal>
@endsection

@push('scripts')
<script>
document.getElementById('formMarca').addEventListener('submit', function () {
    const id = this.querySelector('[name="registro_id"]').value;
    if (!id) return;

    this.action = '{{ url('admin/marcas') }}/' + id;
    if (!this.querySelector('[name="_method"]')) {
        const metodo = document.createElement('input');
        metodo.type = 'hidden';
        metodo.name = '_method';
        metodo.value = 'PUT';
        this.appendChild(metodo);
    }
});
</script>
@endpush
