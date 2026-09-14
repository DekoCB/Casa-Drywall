@extends('layouts.admin')

@section('title', 'Plataformas')
@section('crumb', 'Configuración')

@push('styles')
    @vite(['resources/css/modules/reportes.css'])
@endpush

@section('content')

<x-page-header titulo="Plataformas" subtitulo="Canales de venta y plataformas de cobro">
    <x-slot:acciones>
        <a href="{{ route('admin.configuracion.index') }}" class="btn btn-secondary btn-sm"><span class="btn-text">← Configuración</span></a>
        <button type="button" class="btn btn-primary btn-sm" data-modal="modalPlataforma"><span class="btn-text">＋ Nueva plataforma</span></button>
    </x-slot:acciones>
</x-page-header>

<div class="content-card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr><th>Nombre</th><th>Estado</th><th>Acciones</th></tr>
            </thead>
            <tbody>
            @forelse ($plataformas as $plataforma)
                <tr>
                    <td>{{ $plataforma->nombre }}</td>
                    <td>
                        <span style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;{{ $plataforma->activo ? 'background:#123a24;color:#4ade80;' : 'background:#3a1f1f;color:#f87171;' }}">
                            {{ $plataforma->activo ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                    <td style="white-space:nowrap;">
                        <button type="button" class="btn btn-dark btn-sm" data-modal="modalPlataforma"
                                data-campo-registro_id="{{ $plataforma->id }}" data-campo-nombre="{{ $plataforma->nombre }}">Editar</button>
                        <form method="POST" action="{{ route('admin.finanzas.plataformas.estado', $plataforma) }}" style="display:inline;"
                              data-confirmar="¿{{ $plataforma->activo ? 'Desactivar' : 'Activar' }} «{{ $plataforma->nombre }}»?">
                            @csrf
                            <button type="submit" class="btn btn-secondary btn-sm">{{ $plataforma->activo ? '❙❙ Desactivar' : '▶ Activar' }}</button>
                        </form>
                        <form method="POST" action="{{ route('admin.finanzas.plataformas.destroy', $plataforma) }}" style="display:inline;"
                              data-confirmar="¿Eliminar «{{ $plataforma->nombre }}»?">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" style="text-align:center;padding:40px;color:var(--ink-3);">Sin plataformas registradas</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<x-modal id="modalPlataforma" titulo="Nueva plataforma">
    <form method="POST" action="{{ route('admin.finanzas.plataformas.store') }}" id="formPlataforma">
        @csrf
        <input type="hidden" name="registro_id" value="">
        <div class="form-group">
            <label for="plataformaNombre">Nombre <span>*</span></label>
            <input type="text" id="plataformaNombre" name="nombre" required maxlength="80" placeholder="Ej: Marketplace">
        </div>
        <div class="header-btns" style="justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalPlataforma">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-modal>

@endsection

@push('scripts')
<script>
document.getElementById('formPlataforma').addEventListener('submit', function () {
    const id = this.querySelector('[name="registro_id"]').value;
    if (!id) return;
    this.action = '{{ url('admin/finanzas/plataformas') }}/' + id;
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
