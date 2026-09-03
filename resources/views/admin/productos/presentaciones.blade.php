@extends('layouts.admin')

@section('title', 'Productos')
@section('crumb', 'Vista general')

@push('styles')
    @vite('resources/css/modules/productos.css')
@endpush

@section('content')
<div class="prod-wrapper">

<x-prod-tabs activa="presentaciones" />

<div class="prod-hero prod-hero-oscuro">
    <div class="prod-hero-texto">
        <h2>Presentaciones</h2>
        <p>Define el valor en galones de cada tipo de presentación — se aplica automáticamente al crear productos</p>
    </div>
    <div class="prod-hero-acciones">
        <a href="{{ route('admin.productos.index') }}" class="pbtn pbtn-linea">Ver Productos</a>
        <button type="button" class="pbtn pbtn-verde" data-modal="modalPresentacion"
                data-campo-codigo="" data-campo-descripcion="" data-campo-factor="">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" width="15" height="15">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Nueva Presentación
        </button>
    </div>
</div>

<div class="prod-stats">
    <div class="prod-stat prod-stat-simple">
        <div class="prod-stat-val">{{ number_format($presentaciones->count()) }}</div>
        <div class="prod-stat-lbl">Presentaciones definidas</div>
    </div>
    <div class="prod-stat prod-stat-simple">
        <div class="prod-stat-val">{{ number_format($enUso) }}</div>
        <div class="prod-stat-lbl">En uso en productos</div>
    </div>
    <div class="prod-stat prod-stat-simple">
        <div class="prod-stat-val">{{ number_format($totalMatriz) }}</div>
        <div class="prod-stat-lbl">Productos en matriz</div>
    </div>
</div>

<div class="prod-panel">
    <div class="prod-lista-head">
        <h3>Tabla de Presentaciones</h3>
        <span class="prod-conteo">{{ $presentaciones->count() }} presentaciones</span>
    </div>

    @if ($presentaciones->isEmpty())
        <p class="prod-vacio">Aún no hay presentaciones definidas.</p>
    @else
        <div class="prod-grid">
            @foreach ($presentaciones as $presentacion)
                <div class="prod-card">
                    <div class="prod-card-top">
                        <span class="prod-card-cod">{{ $presentacion->codigo }}</span>
                        <span @class(['prod-card-tag', 'prod-card-tag-cero' => $presentacion->productos === 0])>
                            {{ $presentacion->productos }} {{ $presentacion->productos === 1 ? 'producto' : 'productos' }}
                        </span>
                    </div>

                    <div class="prod-card-num">{{ rtrim(rtrim(number_format($presentacion->factor, 4, '.', ''), '0'), '.') ?: '0' }}</div>
                    <div class="prod-card-unid">Galones por unidad</div>

                    <p @class(['prod-card-desc', 'is-vacia' => $presentacion->descripcion === ''])>
                        {{ $presentacion->descripcion !== '' ? $presentacion->descripcion : 'Sin descripción' }}
                    </p>

                    <div class="prod-card-acciones">
                        <button type="button" class="pbtn pbtn-sm pbtn-editar"
                                data-modal="modalPresentacion"
                                data-campo-codigo="{{ $presentacion->codigo }}"
                                data-campo-descripcion="{{ $presentacion->descripcion }}"
                                data-campo-factor="{{ $presentacion->factor }}">
                            ✎ Editar
                        </button>
                        <button type="button" class="pbtn pbtn-sm pbtn-eliminar"
                                data-eliminar-presentacion="{{ $presentacion->codigo }}"
                                data-en-uso="{{ $presentacion->productos }}">
                            🗑 Eliminar
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

<x-modal id="modalPresentacion" titulo="Presentación de la matriz">
    <form id="formPresentacion">
        <div class="form-grid">
            <div class="form-group">
                <label for="pre_codigo">Código <span>*</span></label>
                <input type="text" id="pre_codigo" name="codigo" required maxlength="20" placeholder="BAL, CIL, GAL…">
            </div>
            <div class="form-group">
                <label for="pre_factor">Galones por unidad <span>*</span></label>
                <input type="number" id="pre_factor" name="factor" step="0.0001" min="0" required>
            </div>
            <div class="form-group">
                <label for="pre_descripcion">Descripción</label>
                <input type="text" id="pre_descripcion" name="descripcion" maxlength="255">
            </div>
        </div>

        <div class="header-btns" style="justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalPresentacion">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-modal>

</div>{{-- /prod-wrapper --}}
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name="csrf-token"]').content;

document.getElementById('formPresentacion').addEventListener('submit', async function (e) {
    e.preventDefault();

    const boton = this.querySelector('[type="submit"]');
    boton.disabled = true;

    try {
        const respuesta = await fetch('{{ route('admin.galonaje.presentaciones.store') }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify(Object.fromEntries(new FormData(this))),
        });

        if (!respuesta.ok) throw new Error('No se pudo guardar');

        window.location.reload();
    } catch (error) {
        alert('No se pudo guardar la presentación: ' + error.message);
        boton.disabled = false;
    }
});

document.addEventListener('click', async (e) => {
    const boton = e.target.closest('[data-eliminar-presentacion]');
    if (!boton) return;

    const codigo = boton.dataset.eliminarPresentacion;
    const enUso  = parseInt(boton.dataset.enUso, 10) || 0;

    const aviso = enUso > 0
        ? `«${codigo}» la usan ${enUso} producto(s) de la matriz. ¿Eliminarla igual?`
        : `¿Eliminar la presentación «${codigo}»?`;

    if (!await confirmar(aviso)) return;

    const respuesta = await fetch('{{ url('admin/galonaje/presentaciones') }}/' + encodeURIComponent(codigo), {
        method: 'DELETE',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
    });

    if (respuesta.ok) {
        window.location.reload();
    } else {
        alert('No se pudo eliminar la presentación.');
    }
});
</script>
@endpush
