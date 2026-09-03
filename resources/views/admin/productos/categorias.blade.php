@extends('layouts.admin')

@section('title', 'Productos')
@section('crumb', 'Vista general')

@push('styles')
    @vite('resources/css/modules/productos.css')
@endpush

@section('content')
<div class="prod-wrapper">

<x-prod-tabs activa="categorias" />

<div class="prod-hero prod-hero-oscuro">
    <div class="prod-hero-texto">
        <h2>Categorías de Productos</h2>
        <p>Gestiona las categorías de la matriz de lubricantes Kendall / P66</p>
    </div>
    <div class="prod-hero-acciones">
        <a href="{{ route('admin.productos.index') }}" class="pbtn pbtn-linea">Ver Productos</a>
        <button type="button" class="pbtn pbtn-verde" data-modal="modalCategoria"
                data-campo-codigo_orig="" data-campo-codigo="" data-campo-descripcion="">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" width="15" height="15">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Nueva Categoría
        </button>
    </div>
</div>

<div class="prod-stats">
    <div class="prod-stat prod-stat-simple">
        <div class="prod-stat-val">{{ number_format($categorias->count()) }}</div>
        <div class="prod-stat-lbl">Categorías</div>
    </div>
    <div class="prod-stat prod-stat-simple">
        <div class="prod-stat-val">{{ number_format($totalMatriz) }}</div>
        <div class="prod-stat-lbl">Productos en matriz</div>
    </div>
    <div class="prod-stat prod-stat-simple">
        <div class="prod-stat-val">{{ number_format($sinProductos) }}</div>
        <div class="prod-stat-lbl">Sin productos aún</div>
    </div>
</div>

<div class="prod-panel">
    <div class="prod-lista-head">
        <h3>Todas las Categorías</h3>
        <span class="prod-conteo">{{ $categorias->count() }} categorías</span>
    </div>

    @if ($categorias->isEmpty())
        <p class="prod-vacio">Aún no hay categorías en la matriz.</p>
    @else
        <div class="prod-grid">
            @foreach ($categorias as $categoria)
                <div class="prod-card">
                    <div class="prod-card-top">
                        <span class="prod-card-cod">{{ $categoria->codigo }}</span>
                        <span @class(['prod-card-tag', 'prod-card-tag-cero' => $categoria->productos === 0])>
                            ○ {{ $categoria->productos }} {{ $categoria->productos === 1 ? 'producto' : 'productos' }}
                        </span>
                    </div>

                    <p @class(['prod-card-desc', 'is-vacia' => $categoria->descripcion === ''])>
                        {{ $categoria->descripcion !== '' ? $categoria->descripcion : 'Sin descripción' }}
                    </p>

                    <div class="prod-card-acciones">
                        <button type="button" class="pbtn pbtn-sm pbtn-editar"
                                data-modal="modalCategoria"
                                data-campo-codigo="{{ $categoria->codigo }}"
                                data-campo-codigo_orig="{{ $categoria->codigo }}"
                                data-campo-descripcion="{{ $categoria->descripcion }}">
                            ✎ Editar
                        </button>
                        <button type="button" class="pbtn pbtn-sm pbtn-eliminar"
                                data-eliminar-categoria="{{ $categoria->codigo }}"
                                data-en-uso="{{ $categoria->productos }}">
                            🗑 Eliminar
                        </button>
                        <a href="{{ route('admin.galonaje.productos.index', ['linea' => $categoria->codigo]) }}"
                           class="pbtn pbtn-sm pbtn-neutro">Ver productos →</a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

<x-modal id="modalCategoria" titulo="Categoría de la matriz">
    <form id="formCategoria">
        <input type="hidden" name="codigo_orig" value="">

        <div class="form-grid">
            <div class="form-group">
                <label for="cat_codigo">Código <span>*</span></label>
                <input type="text" id="cat_codigo" name="codigo" required maxlength="20" placeholder="HDEO, GEAR, ATF…">
            </div>
            <div class="form-group">
                <label for="cat_descripcion">Descripción</label>
                <input type="text" id="cat_descripcion" name="descripcion" maxlength="255">
            </div>
        </div>

        <div class="header-btns" style="justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalCategoria">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-modal>

</div>{{-- /prod-wrapper --}}
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name="csrf-token"]').content;

document.getElementById('formCategoria').addEventListener('submit', async function (e) {
    e.preventDefault();

    const boton = this.querySelector('[type="submit"]');
    boton.disabled = true;

    try {
        const respuesta = await fetch('{{ route('admin.galonaje.categorias.store') }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify(Object.fromEntries(new FormData(this))),
        });

        if (!respuesta.ok) throw new Error('No se pudo guardar');

        window.location.reload();
    } catch (error) {
        alert('No se pudo guardar la categoría: ' + error.message);
        boton.disabled = false;
    }
});

// El backend pide confirmación aparte cuando la categoría tiene productos.
document.addEventListener('click', async (e) => {
    const boton = e.target.closest('[data-eliminar-categoria]');
    if (!boton) return;

    const codigo = boton.dataset.eliminarCategoria;
    const enUso  = parseInt(boton.dataset.enUso, 10) || 0;

    const aviso = enUso > 0
        ? `«${codigo}» tiene ${enUso} producto(s) asignados. ¿Eliminarla igual?`
        : `¿Eliminar la categoría «${codigo}»?`;

    if (!await confirmar(aviso)) return;

    const url = '{{ url('admin/galonaje/categorias') }}/' + encodeURIComponent(codigo)
        + (enUso > 0 ? '?forzar=1' : '');

    const respuesta = await fetch(url, {
        method: 'DELETE',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
    });

    if (respuesta.ok) {
        window.location.reload();
    } else {
        alert('No se pudo eliminar la categoría.');
    }
});
</script>
@endpush
