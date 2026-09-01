@extends('layouts.admin')

@section('title', 'Presentaciones')
@section('crumb', 'Análisis')

@section('content')

<x-page-header titulo="Presentaciones" subtitulo="Factores de conversión por tipo de envase">
    <x-slot:acciones>
        <a href="{{ route('admin.galonaje.productos.index') }}" class="btn btn-secondary btn-sm">← Matriz</a>
        <button type="button" class="btn btn-primary" data-modal="modalPresentacion">
            <span class="btn-icon">＋</span><span class="btn-text">Nueva Presentación</span>
        </button>
    </x-slot:acciones>
</x-page-header>

<div class="content-card">
    <div class="table-container">
        <table class="table">
            <thead><tr><th>Código</th><th>Descripción</th><th>Factor (GL)</th><th>Acciones</th></tr></thead>
            <tbody>
            @forelse ($presentaciones as $presentacion)
                <tr>
                    <td><strong style="font-family:monospace;">{{ $presentacion->codigo }}</strong></td>
                    <td>{{ $presentacion->descripcion ?: '—' }}</td>
                    <td><strong>{{ number_format($presentacion->factor, 4) }}</strong></td>
                    <td style="white-space:nowrap;">
                        <button type="button" class="btn btn-dark btn-sm"
                                data-modal="modalPresentacion"
                                data-campo-codigo="{{ $presentacion->codigo }}"
                                data-campo-descripcion="{{ $presentacion->descripcion }}"
                                data-campo-factor="{{ $presentacion->factor }}">
                            Editar
                        </button>
                        <button type="button" class="btn btn-danger btn-sm btn-eliminar" data-codigo="{{ $presentacion->codigo }}">
                            Eliminar
                        </button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" style="text-align:center;padding:40px;color:#666;">Sin presentaciones registradas</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<x-modal id="modalPresentacion" titulo="Presentación">
    <form id="formPresentacion">
        @csrf
        <div class="form-grid">
            <div class="form-group">
                <label for="codigo">Código <span>*</span></label>
                <input type="text" id="codigo" name="codigo" required maxlength="20" style="text-transform:uppercase;">
            </div>
            <div class="form-group">
                <label for="descripcion">Descripción</label>
                <input type="text" id="descripcion" name="descripcion" maxlength="255">
            </div>
            <div class="form-group">
                <label for="factor">Factor de galones <span>*</span></label>
                <input type="number" id="factor" name="factor" step="0.0001" min="0" required>
            </div>
        </div>

        <div class="header-btns" style="justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalPresentacion">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-modal>
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name="csrf-token"]').content;

document.getElementById('formPresentacion').addEventListener('submit', async function (e) {
    e.preventDefault();

    const respuesta = await fetch('{{ route('admin.galonaje.presentaciones.store') }}', {
        method: 'POST',
        body: new FormData(this),
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
    });

    const datos = await respuesta.json();
    if (datos.ok) window.location.reload();
    else window.alert('No se pudo guardar la presentación.');
});

document.querySelectorAll('.btn-eliminar').forEach((boton) => {
    boton.addEventListener('click', async () => {
        if (!window.confirm('¿Eliminar la presentación ' + boton.dataset.codigo + '?')) return;

        const respuesta = await fetch('{{ url('admin/galonaje/presentaciones') }}/' + boton.dataset.codigo, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        });

        const datos = await respuesta.json();
        if (datos.ok) window.location.reload();
    });
});
</script>
@endpush
