@extends('layouts.admin')

@section('title', 'Almacenes')
@section('crumb', 'Inventario')

@section('content')

<x-page-header titulo="Almacenes" subtitulo="Ubicaciones físicas donde se controla el stock">
    <x-slot:acciones>
        <a href="{{ route('admin.productos.index') }}" class="btn btn-secondary btn-sm">
            <span class="btn-text">← Productos</span>
        </a>
        <button type="button" class="btn btn-primary" data-modal="modalAlmacen">
            <span class="btn-icon">＋</span><span class="btn-text">Nuevo Almacén</span>
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
            @forelse ($almacenes as $almacen)
                <tr>
                    <td>{{ $almacen->nombre ?: '—' }}</td>
                    <td>{{ $almacen->descripcion ?: '—' }}</td>
                    <td style="white-space:nowrap;">
                        <button type="button" class="btn btn-dark btn-sm"
                                data-modal="modalAlmacen"
                                data-campo-registro_id="{{ $almacen->id }}"
                                data-campo-nombre="{{ $almacen->nombre }}"
                                data-campo-descripcion="{{ $almacen->descripcion }}"
                        >
                            Editar
                        </button>
                        <form method="POST" action="{{ route('admin.almacenes.destroy', $almacen) }}"
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

    {{ $almacenes->links() }}
</div>

<x-modal id="modalAlmacen" titulo="Nuevo Almacén">
    <form method="POST" action="{{ route('admin.almacenes.store') }}" id="formAlmacen">
        @csrf
        <input type="hidden" name="registro_id" value="">

        <div class="form-grid">
            <div class="form-group">
                <label for="nombre">Nombre <span>*</span></label>
                <input type="text" id="nombre" name="nombre" required maxlength="100">
            </div>
            <div class="form-group">
                <label for="descripcion">Descripción</label>
                <input type="text" id="descripcion" name="descripcion" maxlength="255">
            </div>
        </div>

        <div class="header-btns" style="justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalAlmacen">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-modal>
@endsection

@push('scripts')
<script>
document.getElementById('formAlmacen').addEventListener('submit', function () {
    const id = this.querySelector('[name="registro_id"]').value;
    if (!id) return;

    this.action = '{{ url('admin/almacenes') }}/' + id;
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
