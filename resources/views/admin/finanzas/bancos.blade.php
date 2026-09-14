@extends('layouts.admin')

@section('title', 'Bancos')
@section('crumb', 'Configuración')

@push('styles')
    @vite(['resources/css/modules/reportes.css'])
@endpush

@section('content')

<x-page-header titulo="Bancos" subtitulo="Entidades bancarias disponibles">
    <x-slot:acciones>
        <a href="{{ route('admin.configuracion.index') }}" class="btn btn-secondary btn-sm"><span class="btn-text">← Configuración</span></a>
        <button type="button" class="btn btn-primary btn-sm" data-modal="modalBanco"><span class="btn-text">＋ Nuevo banco</span></button>
    </x-slot:acciones>
</x-page-header>

<div class="content-card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr><th>Nombre</th><th>Estado</th><th>Acciones</th></tr>
            </thead>
            <tbody>
            @forelse ($bancos as $banco)
                <tr>
                    <td>{{ $banco->nombre }}</td>
                    <td>
                        <span style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;{{ $banco->activo ? 'background:#123a24;color:#4ade80;' : 'background:#3a1f1f;color:#f87171;' }}">
                            {{ $banco->activo ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                    <td style="white-space:nowrap;">
                        <button type="button" class="btn btn-dark btn-sm" data-modal="modalBanco"
                                data-campo-registro_id="{{ $banco->id }}" data-campo-nombre="{{ $banco->nombre }}">Editar</button>
                        <form method="POST" action="{{ route('admin.finanzas.bancos.estado', $banco) }}" style="display:inline;"
                              data-confirmar="¿{{ $banco->activo ? 'Desactivar' : 'Activar' }} «{{ $banco->nombre }}»?">
                            @csrf
                            <button type="submit" class="btn btn-secondary btn-sm">{{ $banco->activo ? '❙❙ Desactivar' : '▶ Activar' }}</button>
                        </form>
                        <form method="POST" action="{{ route('admin.finanzas.bancos.destroy', $banco) }}" style="display:inline;"
                              data-confirmar="¿Eliminar «{{ $banco->nombre }}»?">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" style="text-align:center;padding:40px;color:var(--ink-3);">Sin bancos registrados</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<x-modal id="modalBanco" titulo="Nuevo banco">
    <form method="POST" action="{{ route('admin.finanzas.bancos.store') }}" id="formBanco">
        @csrf
        <input type="hidden" name="registro_id" value="">
        <div class="form-group">
            <label for="bancoNombre">Nombre <span>*</span></label>
            <input type="text" id="bancoNombre" name="nombre" required maxlength="80" placeholder="Ej: BCP">
        </div>
        <div class="header-btns" style="justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalBanco">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-modal>

@endsection

@push('scripts')
<script>
document.getElementById('formBanco').addEventListener('submit', function () {
    const id = this.querySelector('[name="registro_id"]').value;
    if (!id) return;
    this.action = '{{ url('admin/finanzas/bancos') }}/' + id;
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
