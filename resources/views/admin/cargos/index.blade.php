@extends('layouts.admin')

@section('title', 'Cargos')
@section('crumb', 'Personal')

@section('content')

<x-page-header titulo="Cargos" subtitulo="Cargos disponibles para el formulario de Personal">
    <x-slot:acciones>
        <a href="{{ route('admin.personal.index') }}" class="btn btn-secondary btn-sm">
            <span class="btn-text">← Personal</span>
        </a>
        <button type="button" class="btn btn-primary" data-modal="modalCargo">
            <span class="btn-icon">＋</span><span class="btn-text">Nuevo Cargo</span>
        </button>
    </x-slot:acciones>
</x-page-header>

<div class="content-card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($cargos as $cargo)
                <tr>
                    <td>{{ $cargo->nombre }}</td>
                    <td style="white-space:nowrap;">
                        <button type="button" class="btn btn-dark btn-sm"
                                data-modal="modalCargo"
                                data-campo-registro_id="{{ $cargo->id }}"
                                data-campo-nombre="{{ $cargo->nombre }}"
                        >
                            Editar
                        </button>
                        <form method="POST" action="{{ route('admin.cargos.destroy', $cargo) }}"
                              style="display:inline;" data-confirmar="¿Eliminar este registro?">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="2" style="text-align:center;padding:40px;color:#666;">Sin registros</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $cargos->links() }}
</div>

<x-modal id="modalCargo" titulo="Nuevo Cargo">
    <form method="POST" action="{{ route('admin.cargos.store') }}" id="formCargo">
        @csrf
        <input type="hidden" name="registro_id" value="">

        <div class="form-group">
            <label for="nombre">Nombre <span>*</span></label>
            <input type="text" id="nombre" name="nombre" required maxlength="100">
        </div>

        <div class="header-btns" style="justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalCargo">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-modal>
@endsection

@push('scripts')
<script>
document.getElementById('formCargo').addEventListener('submit', function () {
    const id = this.querySelector('[name="registro_id"]').value;
    if (!id) return;

    this.action = '{{ url('admin/cargos') }}/' + id;
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
