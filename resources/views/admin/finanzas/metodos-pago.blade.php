@extends('layouts.admin')

@section('title', 'Métodos de pago')
@section('crumb', 'Configuración')

@push('styles')
    @vite(['resources/css/modules/reportes.css'])
@endpush

@section('content')

<x-page-header titulo="Métodos de pago" subtitulo="Formas de pago para ingresos y gastos">
    <x-slot:acciones>
        <a href="{{ route('admin.configuracion.index') }}" class="btn btn-secondary btn-sm"><span class="btn-text">← Configuración</span></a>
        <button type="button" class="btn btn-primary btn-sm" data-modal="modalMetodo"><span class="btn-text">＋ Nuevo método</span></button>
    </x-slot:acciones>
</x-page-header>

<div class="content-card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr><th>Nombre</th><th>Estado</th><th>Acciones</th></tr>
            </thead>
            <tbody>
            @forelse ($metodos as $metodo)
                <tr>
                    <td>{{ $metodo->nombre }}</td>
                    <td>
                        <span style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;{{ $metodo->activo ? 'background:#123a24;color:#4ade80;' : 'background:#3a1f1f;color:#f87171;' }}">
                            {{ $metodo->activo ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                    <td style="white-space:nowrap;">
                        <button type="button" class="btn btn-dark btn-sm" data-modal="modalMetodo"
                                data-campo-registro_id="{{ $metodo->id }}" data-campo-nombre="{{ $metodo->nombre }}">Editar</button>
                        <form method="POST" action="{{ route('admin.finanzas.metodos-pago.estado', $metodo) }}" style="display:inline;"
                              data-confirmar="¿{{ $metodo->activo ? 'Desactivar' : 'Activar' }} «{{ $metodo->nombre }}»?">
                            @csrf
                            <button type="submit" class="btn btn-secondary btn-sm">{{ $metodo->activo ? '❙❙ Desactivar' : '▶ Activar' }}</button>
                        </form>
                        <form method="POST" action="{{ route('admin.finanzas.metodos-pago.destroy', $metodo) }}" style="display:inline;"
                              data-confirmar="¿Eliminar «{{ $metodo->nombre }}»?">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" style="text-align:center;padding:40px;color:var(--ink-3);">Sin métodos de pago registrados</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<x-modal id="modalMetodo" titulo="Nuevo método de pago">
    <form method="POST" action="{{ route('admin.finanzas.metodos-pago.store') }}" id="formMetodo">
        @csrf
        <input type="hidden" name="registro_id" value="">
        <div class="form-group">
            <label for="metodoNombre">Nombre <span>*</span></label>
            <input type="text" id="metodoNombre" name="nombre" required maxlength="60" placeholder="Ej: Efectivo">
        </div>
        <div class="header-btns" style="justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalMetodo">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-modal>

@endsection

@push('scripts')
<script>
document.getElementById('formMetodo').addEventListener('submit', function () {
    const id = this.querySelector('[name="registro_id"]').value;
    if (!id) return;
    this.action = '{{ url('admin/finanzas/metodos-pago') }}/' + id;
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
