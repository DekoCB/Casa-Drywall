@extends('layouts.admin')

@section('title', 'Tarjetas')
@section('crumb', 'Configuración')

@push('styles')
    @vite(['resources/css/modules/reportes.css'])
@endpush

@section('content')

<x-page-header titulo="Tarjetas" subtitulo="Tipos de tarjeta aceptadas como medio de pago">
    <x-slot:acciones>
        <a href="{{ route('admin.configuracion.index') }}" class="btn btn-secondary btn-sm"><span class="btn-text">← Configuración</span></a>
        <button type="button" class="btn btn-primary btn-sm" data-modal="modalTarjeta"><span class="btn-text">＋ Nueva tarjeta</span></button>
    </x-slot:acciones>
</x-page-header>

<div class="content-card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr><th>Nombre</th><th>Estado</th><th>Acciones</th></tr>
            </thead>
            <tbody>
            @forelse ($tarjetas as $tarjeta)
                <tr>
                    <td>{{ $tarjeta->nombre }}</td>
                    <td>
                        <span style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;{{ $tarjeta->activo ? 'background:#123a24;color:#4ade80;' : 'background:#3a1f1f;color:#f87171;' }}">
                            {{ $tarjeta->activo ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                    <td style="white-space:nowrap;">
                        <button type="button" class="btn btn-dark btn-sm" data-modal="modalTarjeta"
                                data-campo-registro_id="{{ $tarjeta->id }}" data-campo-nombre="{{ $tarjeta->nombre }}">Editar</button>
                        <form method="POST" action="{{ route('admin.finanzas.tarjetas.estado', $tarjeta) }}" style="display:inline;"
                              data-confirmar="¿{{ $tarjeta->activo ? 'Desactivar' : 'Activar' }} «{{ $tarjeta->nombre }}»?">
                            @csrf
                            <button type="submit" class="btn btn-secondary btn-sm">{{ $tarjeta->activo ? '❙❙ Desactivar' : '▶ Activar' }}</button>
                        </form>
                        <form method="POST" action="{{ route('admin.finanzas.tarjetas.destroy', $tarjeta) }}" style="display:inline;"
                              data-confirmar="¿Eliminar «{{ $tarjeta->nombre }}»?">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" style="text-align:center;padding:40px;color:var(--ink-3);">Sin tarjetas registradas</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<x-modal id="modalTarjeta" titulo="Nueva tarjeta">
    <form method="POST" action="{{ route('admin.finanzas.tarjetas.store') }}" id="formTarjeta">
        @csrf
        <input type="hidden" name="registro_id" value="">
        <div class="form-group">
            <label for="tarjetaNombre">Nombre <span>*</span></label>
            <input type="text" id="tarjetaNombre" name="nombre" required maxlength="60" placeholder="Ej: Visa">
        </div>
        <div class="header-btns" style="justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalTarjeta">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-modal>

@endsection

@push('scripts')
<script>
document.getElementById('formTarjeta').addEventListener('submit', function () {
    const id = this.querySelector('[name="registro_id"]').value;
    if (!id) return;
    this.action = '{{ url('admin/finanzas/tarjetas') }}/' + id;
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
