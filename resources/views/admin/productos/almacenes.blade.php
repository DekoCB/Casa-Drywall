@extends('layouts.admin')

@section('title', 'Productos')
@section('crumb', 'Vista general')

@push('styles')
    @vite('resources/css/modules/productos.css')
@endpush

@section('content')
<div class="prod-wrapper">

<x-prod-tabs activa="almacenes" />

<div class="prod-hero prod-hero-verde">
    <div class="prod-hero-texto">
        <h2>🏠 Almacenes</h2>
        <p>Registra y administra los almacenes donde se distribuye el stock</p>
    </div>
    <div class="prod-hero-acciones">
        <button type="button" class="pbtn pbtn-verde" data-modal="modalAlmacen"
                data-campo-registro_id="" data-campo-nombre="" data-campo-descripcion="">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" width="15" height="15">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Nuevo Almacén
        </button>
    </div>
</div>

<div class="prod-stats">
    <div class="prod-stat prod-stat-simple">
        <div class="prod-stat-val">{{ number_format($almacenes->count()) }}</div>
        <div class="prod-stat-lbl">Almacenes registrados</div>
    </div>
    <div class="prod-stat prod-stat-simple">
        <div class="prod-stat-val" style="color:var(--pv-texto);">{{ number_format($activos) }}</div>
        <div class="prod-stat-lbl">Activos</div>
    </div>
    <div class="prod-stat prod-stat-simple">
        <div class="prod-stat-val">{{ number_format($unidades) }}</div>
        <div class="prod-stat-lbl">Unidades en stock</div>
    </div>
    <div class="prod-stat prod-stat-simple">
        <div class="prod-stat-val">S/ {{ number_format($valorTotal, 2) }}</div>
        <div class="prod-stat-lbl">Valor del inventario</div>
    </div>
</div>

@if ($almacenes->isEmpty())
    <div class="prod-panel"><p class="prod-vacio">Sin almacenes registrados.</p></div>
@else
    <div class="prod-grid">
        @foreach ($almacenes as $almacen)
            @php $datos = $resumen[$almacen->id] ?? ['unidades' => 0, 'productos' => 0]; @endphp
            <div class="prod-card">
                <div class="prod-alm-card-top">
                    <span class="prod-alm-ico">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 9.5 12 3l9 6.5V21H3z"/><path d="M9 21v-7h6v7"/>
                        </svg>
                    </span>
                    <span>
                        <span class="prod-alm-card-nom">{{ $almacen->nombre }}</span>
                        <span class="prod-alm-card-desc">{{ $almacen->descripcion ?: 'Sin descripción' }}</span>
                    </span>
                    <span @class(['prod-estado', 'prod-estado-on' => $almacen->activo, 'prod-estado-off' => ! $almacen->activo])>
                        {{ $almacen->activo ? 'Activo' : 'Inactivo' }}
                    </span>
                </div>

                <div class="prod-alm-cifras">
                    <div class="prod-alm-cifra">
                        <b>{{ number_format($datos['unidades']) }}</b>
                        <span>Unidades</span>
                    </div>
                    <div class="prod-alm-cifra">
                        <b>{{ number_format($datos['productos']) }}</b>
                        <span>Productos</span>
                    </div>
                </div>

                @if (($bajos[$almacen->id] ?? 0) > 0)
                    <div class="prod-aviso-bajo">
                        ⚠ {{ $bajos[$almacen->id] }} producto(s) con stock bajo
                    </div>
                @endif

                <div class="prod-card-acciones">
                    <button type="button" class="pbtn pbtn-sm pbtn-editar"
                            data-modal="modalAlmacen"
                            data-campo-registro_id="{{ $almacen->id }}"
                            data-campo-nombre="{{ $almacen->nombre }}"
                            data-campo-descripcion="{{ $almacen->descripcion }}">
                        ✎ Editar
                    </button>
                    <form method="POST" action="{{ route('admin.almacenes.estado', $almacen) }}"
                          data-confirmar="¿{{ $almacen->activo ? 'Desactivar' : 'Activar' }} «{{ $almacen->nombre }}»?">
                        @csrf
                        <button type="submit" class="pbtn pbtn-sm pbtn-neutro">
                            {{ $almacen->activo ? '❙❙ Desactivar' : '▶ Activar' }}
                        </button>
                    </form>
                    <a href="{{ route('admin.productos.index', ['almacen' => $almacen->id]) }}"
                       class="pbtn pbtn-sm pbtn-neutro">Ver stock →</a>
                </div>
            </div>
        @endforeach
    </div>
@endif

<x-modal id="modalAlmacen" titulo="Datos del almacén">
    <form method="POST" action="{{ route('admin.almacenes.store') }}" id="formAlmacen">
        @csrf
        <input type="hidden" name="registro_id" value="">

        <div class="form-grid">
            <div class="form-group">
                <label for="nombre">Nombre <span>*</span></label>
                <input type="text" id="nombre" name="nombre" required maxlength="100">
            </div>
            <div class="form-group">
                <label for="descripcion">Descripción</label>
                <input type="text" id="descripcion" name="descripcion" maxlength="255">
            </div>
        </div>

        <div class="header-btns" style="justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalAlmacen">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-modal>

</div>{{-- /prod-wrapper --}}
@endsection

@push('scripts')
<script>
document.getElementById('formAlmacen').addEventListener('submit', function () {
    const id = this.querySelector('[name="registro_id"]').value;
    if (!id) return;

    this.action = '{{ url('admin/almacenes') }}/' + id;
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
