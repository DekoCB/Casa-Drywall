@extends('layouts.admin')

@section('title', 'Locales')
@section('crumb', 'Establecimientos')

@section('content')

<x-page-header titulo="Locales / Establecimientos" subtitulo="Sucursales SUNAT de {{ config('rentaltech.empresa.razon_social') }}">
    <x-slot:acciones>
        <button type="button" class="btn btn-primary" id="btnNuevoLocal">
            <span class="btn-icon">＋</span><span class="btn-text">Nuevo Local</span>
        </button>
    </x-slot:acciones>
</x-page-header>

<div class="rep-grid" style="grid-template-columns:repeat(auto-fill,minmax(280px,1fr));">
    @forelse ($locales as $local)
        <div class="content-card" style="text-align:center;">
            <div style="width:56px;height:56px;border-radius:50%;background:var(--brand-bg);color:var(--brand);
                        display:grid;place-items:center;margin:0 auto 10px;font-weight:700;font-size:20px;">
                {{ Str::upper(Str::substr($local['nombre'] ?? '?', 0, 2)) }}
            </div>
            <h3 style="margin-bottom:4px;">{{ $local['nombre'] }}</h3>
            <span class="badge" style="background:var(--surface-2);color:var(--ink-3);">{{ $local['codigo'] }}</span>

            <div style="text-align:left;margin-top:16px;font-size:13px;color:var(--ink-2);display:flex;flex-direction:column;gap:8px;">
                <div>📍 {{ $local['departamento'] }} — {{ $local['provincia'] }} — {{ $local['distrito'] }}</div>
                <div>🏢 {{ $local['direccion'] }}</div>
                @if (! empty($local['telefono']))
                    <div>📞 {{ $local['telefono'] }}</div>
                @endif
                @if (! empty($local['email']))
                    <div>✉ {{ $local['email'] }}</div>
                @endif
                <div>
                    <span class="badge {{ $local['activo'] ? 'badge-success' : 'badge-danger' }}">
                        {{ $local['activo'] ? 'Activo' : 'Inactivo' }}
                    </span>
                </div>
            </div>

            <div class="header-btns" style="justify-content:center;margin-top:16px;">
                <button type="button" class="btn btn-secondary btn-sm"
                        data-modal="modalSeries"
                        data-campo-local_id="{{ $local['id'] }}"
                        data-campo-series_factura="{{ implode("\n", $local['series_factura'] ?? []) }}"
                        data-campo-series_boleta="{{ implode("\n", $local['series_boleta'] ?? []) }}"
                        data-campo-series_nota_credito="{{ implode("\n", $local['series_nota_credito'] ?? []) }}"
                        data-campo-series_nota_debito="{{ implode("\n", $local['series_nota_debito'] ?? []) }}"
                        data-campo-series_guia_remision="{{ implode("\n", $local['series_guia_remision'] ?? []) }}">
                    Series
                </button>
                <button type="button" class="btn btn-dark btn-sm"
                        data-modal="modalLocal"
                        data-campo-local_id="{{ $local['id'] }}"
                        data-campo-codigo="{{ $local['codigo'] }}"
                        data-campo-nombre="{{ $local['nombre'] }}"
                        data-campo-direccion="{{ $local['direccion'] }}"
                        data-campo-ubigeo="{{ $local['ubigeo'] }}"
                        data-campo-distrito="{{ $local['distrito'] }}"
                        data-campo-provincia="{{ $local['provincia'] }}"
                        data-campo-departamento="{{ $local['departamento'] }}"
                        data-campo-telefono="{{ $local['telefono'] }}"
                        data-campo-email="{{ $local['email'] }}">
                    Editar
                </button>
                @if ($local['activo'])
                    <form method="POST" action="{{ route('admin.locales.destroy', $local['id']) }}"
                          data-confirmar="¿Desactivar el local {{ $local['nombre'] }}?">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">Desactivar</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.locales.activar', $local['id']) }}">
                        @csrf
                        <button type="submit" class="btn btn-secondary btn-sm">Activar</button>
                    </form>
                @endif
            </div>
        </div>
    @empty
        <div class="content-card" style="text-align:center;padding:40px;color:var(--ink-3);grid-column:1/-1;">
            Sin locales registrados todavía.
        </div>
    @endforelse
</div>

<x-modal id="modalLocal" titulo="Nuevo Local">
    <form method="POST" action="{{ route('admin.locales.store') }}" id="formLocal">
        @csrf
        <input type="hidden" name="local_id" value="">

        <div class="form-grid">
            <div class="form-group">
                <label for="loc-codigo">Código <span>*</span></label>
                <input type="text" id="loc-codigo" name="codigo" required maxlength="10" placeholder="0001">
            </div>
            <div class="form-group">
                <label for="loc-nombre">Nombre <span>*</span></label>
                <input type="text" id="loc-nombre" name="nombre" required maxlength="255">
            </div>
            <div class="form-group">
                <label for="loc-ubigeo">Ubigeo <span>*</span></label>
                <input type="text" id="loc-ubigeo" name="ubigeo" required maxlength="6" placeholder="150101">
            </div>
            <div class="form-group">
                <label for="loc-departamento">Departamento <span>*</span></label>
                <input type="text" id="loc-departamento" name="departamento" required maxlength="100">
            </div>
            <div class="form-group">
                <label for="loc-provincia">Provincia <span>*</span></label>
                <input type="text" id="loc-provincia" name="provincia" required maxlength="100">
            </div>
            <div class="form-group">
                <label for="loc-distrito">Distrito <span>*</span></label>
                <input type="text" id="loc-distrito" name="distrito" required maxlength="100">
            </div>
            <div class="form-group">
                <label for="loc-telefono">Teléfono</label>
                <input type="text" id="loc-telefono" name="telefono" maxlength="20">
            </div>
            <div class="form-group">
                <label for="loc-email">Email</label>
                <input type="email" id="loc-email" name="email" maxlength="255">
            </div>
        </div>

        <div class="form-group">
            <label for="loc-direccion">Dirección <span>*</span></label>
            <input type="text" id="loc-direccion" name="direccion" required maxlength="255">
        </div>

        <div class="header-btns" style="justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalLocal">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-modal>

<x-modal id="modalSeries" titulo="Series del local">
    <form method="POST" action="" id="formSeries">
        @csrf @method('PUT')
        <input type="hidden" name="local_id" value="">

        <p class="lbl-opcional" style="margin-bottom:14px;">Una serie por línea, ej. F001</p>

        <div class="form-grid">
            <div class="form-group">
                <label>Facturas</label>
                <textarea name="series_factura" style="height:70px;"></textarea>
            </div>
            <div class="form-group">
                <label>Boletas</label>
                <textarea name="series_boleta" style="height:70px;"></textarea>
            </div>
            <div class="form-group">
                <label>Notas de Crédito</label>
                <textarea name="series_nota_credito" style="height:70px;"></textarea>
            </div>
            <div class="form-group">
                <label>Notas de Débito</label>
                <textarea name="series_nota_debito" style="height:70px;"></textarea>
            </div>
        </div>
        <div class="form-group">
            <label>Guías de Remisión</label>
            <textarea name="series_guia_remision" style="height:70px;"></textarea>
        </div>

        <div class="header-btns" style="justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalSeries">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar Series</button>
        </div>
    </form>
</x-modal>

@endsection

@push('scripts')
<script>
const formLocal = document.getElementById('formLocal');
const URL_LOCALES = '{{ url('admin/locales') }}';

document.getElementById('btnNuevoLocal').addEventListener('click', () => {
    formLocal.reset();
    formLocal.action = URL_LOCALES;
    formLocal.querySelector('[name="_method"]')?.remove();
    formLocal.querySelector('[name="local_id"]').value = '';
    abrirModal('modalLocal');
});

formLocal.addEventListener('submit', function () {
    const id = this.querySelector('[name="local_id"]').value;
    if (!id) return;

    this.action = URL_LOCALES + '/' + id;
    if (!this.querySelector('[name="_method"]')) {
        const metodo = document.createElement('input');
        metodo.type = 'hidden';
        metodo.name = '_method';
        metodo.value = 'PUT';
        this.appendChild(metodo);
    }
});

document.getElementById('formSeries').addEventListener('submit', function () {
    const id = this.querySelector('[name="local_id"]')?.value;
    if (id) this.action = URL_LOCALES + '/' + id + '/series';
});
</script>
@endpush
