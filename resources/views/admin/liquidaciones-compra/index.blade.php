@extends('layouts.admin')

@section('title', 'Liquidación de Compra')
@section('crumb', 'Compras')

@section('content')

<x-page-header titulo="Liquidación de Compra" subtitulo="Compras a vendedores informales, sin RUC">
    <x-slot:acciones>
        <button type="button" class="btn btn-primary" id="btnNuevaLiquidacion">
            <span class="btn-icon">＋</span><span class="btn-text">Nueva Liquidación</span>
        </button>
    </x-slot:acciones>
</x-page-header>

<div class="alert alert-error" style="margin-bottom:18px;">
    ⚠ Este registro es solo para uso interno/contable. <strong>No se envía a SUNAT electrónicamente</strong> —
    el sistema de facturación instalado todavía no soporta emitir Liquidación de Compra como comprobante válido.
</div>

<div class="content-card">
    <form method="GET" class="filtros" style="margin-bottom:18px;">
        <div class="filtro-campo">
            <span>Buscar</span>
            <input type="text" name="q" value="{{ $busqueda }}" placeholder="Número o vendedor…">
        </div>
        <button type="submit" class="btn btn-secondary">Filtrar</button>
        @if ($busqueda !== '')
            <a href="{{ route('admin.liquidaciones-compra.index') }}" class="btn btn-secondary">Limpiar</a>
        @endif
    </form>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Número</th><th>Fecha</th><th>Vendedor</th><th>Documento</th>
                    <th class="num">Total (S/)</th><th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($liquidaciones as $liquidacion)
                @php
                    $textoProductosEdicion = collect($liquidacion->productos)->pluck('descripcion')->implode("\n");
                @endphp
                <tr>
                    <td><strong>{{ $liquidacion->numero }}</strong></td>
                    <td>{{ $liquidacion->fecha?->format('d/m/Y') }}</td>
                    <td>{{ $liquidacion->vendedor_nombre }}</td>
                    <td>{{ $liquidacion->vendedor_documento ?: '—' }}</td>
                    <td class="num">{{ number_format((float) $liquidacion->total, 2) }}</td>
                    <td style="white-space:nowrap;">
                        <a href="{{ route('admin.liquidaciones-compra.comprobante', $liquidacion) }}" target="_blank"
                           class="btn btn-secondary btn-sm" title="Ver comprobante interno">🧾</a>
                        <button type="button" class="btn btn-dark btn-sm"
                                data-modal="modalLiquidacion"
                                data-campo-registro_id="{{ $liquidacion->id }}"
                                data-campo-fecha="{{ $liquidacion->fecha?->format('Y-m-d') }}"
                                data-campo-vendedor_nombre="{{ $liquidacion->vendedor_nombre }}"
                                data-campo-vendedor_documento="{{ $liquidacion->vendedor_documento }}"
                                data-campo-proveedor_id="{{ $liquidacion->proveedor_id }}"
                                data-campo-productos="{{ $textoProductosEdicion }}"
                                data-campo-total="{{ $liquidacion->total }}"
                                data-campo-observaciones="{{ $liquidacion->observaciones }}">
                            Editar
                        </button>
                        <form method="POST" action="{{ route('admin.liquidaciones-compra.destroy', $liquidacion) }}"
                              style="display:inline;" data-confirmar="¿Eliminar la liquidación {{ $liquidacion->numero }}?">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--ink-3);">Sin liquidaciones registradas</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $liquidaciones->links() }}
</div>

<x-modal id="modalLiquidacion" titulo="Nueva Liquidación de Compra">
    <form method="POST" action="{{ route('admin.liquidaciones-compra.store') }}" id="formLiquidacion">
        @csrf
        <input type="hidden" name="registro_id" value="">

        <div class="form-grid">
            <div class="form-group">
                <label for="lc-fecha">Fecha <span>*</span></label>
                <input type="date" id="lc-fecha" name="fecha" required value="{{ now()->toDateString() }}">
            </div>
            <div class="form-group">
                <label for="lc-vendedor">Nombre del vendedor <span>*</span></label>
                <input type="text" id="lc-vendedor" name="vendedor_nombre" required maxlength="255">
            </div>
            <div class="form-group">
                <label for="lc-documento">DNI del vendedor</label>
                <input type="text" id="lc-documento" name="vendedor_documento" maxlength="20">
            </div>
            <div class="form-group">
                <label for="lc-proveedor">Ficha de proveedor <span class="lbl-opcional">(si ya está registrado)</span></label>
                <select id="lc-proveedor" name="proveedor_id">
                    <option value="">— Sin especificar —</option>
                    @foreach ($proveedores as $proveedor)
                        <option value="{{ $proveedor->id }}">{{ $proveedor->razon_social }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="lc-total">Total (S/) <span>*</span></label>
                <input type="number" id="lc-total" name="total" step="0.01" min="0" required value="0.00">
            </div>
        </div>

        <div class="form-group" style="margin-top:5px;">
            <label for="lc-productos">Productos comprados <span class="lbl-opcional">(uno por línea)</span></label>
            <textarea id="lc-productos" name="productos" style="height:80px;"></textarea>
        </div>

        <div class="form-group" style="margin-top:15px;">
            <label for="lc-observaciones">Observaciones</label>
            <textarea id="lc-observaciones" name="observaciones" style="height:60px;"></textarea>
        </div>

        <div class="header-btns" style="justify-content:flex-end;margin-top:20px;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalLiquidacion">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-modal>
@endsection

@push('scripts')
<script>
const formLiquidacion = document.getElementById('formLiquidacion');
const URL_LIQUIDACIONES = '{{ url('admin/liquidaciones-compra') }}';

document.getElementById('btnNuevaLiquidacion').addEventListener('click', () => {
    formLiquidacion.reset();
    formLiquidacion.action = URL_LIQUIDACIONES;
    formLiquidacion.querySelector('[name="_method"]')?.remove();
    formLiquidacion.querySelector('[name="registro_id"]').value = '';
    document.getElementById('lc-fecha').value = '{{ now()->toDateString() }}';
    abrirModal('modalLiquidacion');
});

formLiquidacion.addEventListener('submit', function () {
    const id = this.querySelector('[name="registro_id"]').value;
    if (!id) return;

    this.action = URL_LIQUIDACIONES + '/' + id;
    if (!this.querySelector('[name="_method"]')) {
        const metodo = document.createElement('input');
        metodo.type = 'hidden';
        metodo.name = '_method';
        metodo.value = 'PUT';
        this.appendChild(metodo);
    }
});

@if ($abrirCrear)
    document.getElementById('btnNuevaLiquidacion').click();
@endif
</script>
@endpush
