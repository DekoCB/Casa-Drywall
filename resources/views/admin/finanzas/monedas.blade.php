@extends('layouts.admin')

@section('title', 'Monedas')
@section('crumb', 'Configuración')

@push('styles')
    @vite(['resources/css/modules/reportes.css'])
@endpush

@section('content')

<x-page-header titulo="Monedas" subtitulo="Soles, dólares y otras divisas habilitadas">
    <x-slot:acciones>
        <a href="{{ route('admin.configuracion.index') }}" class="btn btn-secondary btn-sm"><span class="btn-text">← Configuración</span></a>
        <button type="button" class="btn btn-primary btn-sm" data-modal="modalMoneda"><span class="btn-text">＋ Nueva moneda</span></button>
    </x-slot:acciones>
</x-page-header>

<div class="content-card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr><th>Nombre</th><th>Código</th><th>Símbolo</th><th>Estado</th><th>Acciones</th></tr>
            </thead>
            <tbody>
            @forelse ($monedas as $moneda)
                <tr>
                    <td>{{ $moneda->nombre }}</td>
                    <td>{{ $moneda->codigo }}</td>
                    <td>{{ $moneda->simbolo }}</td>
                    <td>
                        <span style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;{{ $moneda->activo ? 'background:#123a24;color:#4ade80;' : 'background:#3a1f1f;color:#f87171;' }}">
                            {{ $moneda->activo ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                    <td style="white-space:nowrap;">
                        <button type="button" class="btn btn-dark btn-sm" data-modal="modalMoneda"
                                data-campo-registro_id="{{ $moneda->id }}" data-campo-nombre="{{ $moneda->nombre }}"
                                data-campo-codigo="{{ $moneda->codigo }}" data-campo-simbolo="{{ $moneda->simbolo }}">Editar</button>
                        <form method="POST" action="{{ route('admin.finanzas.monedas.estado', $moneda) }}" style="display:inline;"
                              data-confirmar="¿{{ $moneda->activo ? 'Desactivar' : 'Activar' }} «{{ $moneda->nombre }}»?">
                            @csrf
                            <button type="submit" class="btn btn-secondary btn-sm">{{ $moneda->activo ? '❙❙ Desactivar' : '▶ Activar' }}</button>
                        </form>
                        <form method="POST" action="{{ route('admin.finanzas.monedas.destroy', $moneda) }}" style="display:inline;"
                              data-confirmar="¿Eliminar «{{ $moneda->nombre }}»?">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align:center;padding:40px;color:var(--ink-3);">Sin monedas registradas</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<x-modal id="modalMoneda" titulo="Nueva moneda">
    <form method="POST" action="{{ route('admin.finanzas.monedas.store') }}" id="formMoneda">
        @csrf
        <input type="hidden" name="registro_id" value="">
        <div class="form-group">
            <label for="monedaNombre">Nombre <span>*</span></label>
            <input type="text" id="monedaNombre" name="nombre" required maxlength="60" placeholder="Ej: Soles">
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label for="monedaCodigo">Código <span>*</span></label>
                <input type="text" id="monedaCodigo" name="codigo" required maxlength="10" placeholder="Ej: PEN">
            </div>
            <div class="form-group">
                <label for="monedaSimbolo">Símbolo <span>*</span></label>
                <input type="text" id="monedaSimbolo" name="simbolo" required maxlength="10" placeholder="Ej: S/">
            </div>
        </div>
        <div class="header-btns" style="justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalMoneda">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-modal>

@endsection

@push('scripts')
<script>
document.getElementById('formMoneda').addEventListener('submit', function () {
    const id = this.querySelector('[name="registro_id"]').value;
    if (!id) return;
    this.action = '{{ url('admin/finanzas/monedas') }}/' + id;
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
