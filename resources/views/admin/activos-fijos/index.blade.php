@extends('layouts.admin')

@section('title', 'Activos Fijos')
@section('crumb', 'Compras')

@section('content')

<x-page-header titulo="Activos Fijos" subtitulo="Equipos, vehículos y maquinaria de la empresa">
    <x-slot:acciones>
        <button type="button" class="btn btn-primary" id="btnNuevoActivo">
            <span class="btn-icon">＋</span><span class="btn-text">Comprar Activo Fijo</span>
        </button>
    </x-slot:acciones>
</x-page-header>

<div class="content-card">
    <form method="GET" class="filtros" style="margin-bottom:18px;">
        <div class="filtro-campo">
            <span>Buscar</span>
            <input type="text" name="q" value="{{ $busqueda }}" placeholder="Nombre o código…">
        </div>
        <div class="filtro-campo">
            <span>Categoría</span>
            <select name="categoria" class="filtro-select">
                <option value="">Todas</option>
                @foreach ($categorias as $cat)
                    <option value="{{ $cat }}" @selected($categoriaSel === $cat)>{{ $cat }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-secondary">Filtrar</button>
        @if ($busqueda !== '' || $categoriaSel !== '')
            <a href="{{ route('admin.activos-fijos.index') }}" class="btn btn-secondary">Limpiar</a>
        @endif
    </form>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Fecha</th><th>Código</th><th>Nombre</th><th>Categoría</th>
                    <th>Proveedor</th><th class="num">Costo (S/)</th><th>Estado</th><th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($activos as $activo)
                <tr>
                    <td>{{ $activo->fecha_compra?->format('d/m/Y') }}</td>
                    <td>{{ $activo->codigo ?: '—' }}</td>
                    <td><strong>{{ $activo->nombre }}</strong></td>
                    <td>{{ $activo->categoria ?: '—' }}</td>
                    <td>{{ $activo->proveedor?->razon_social ?: '—' }}</td>
                    <td class="num">{{ number_format((float) $activo->costo, 2) }}</td>
                    <td><span class="badge {{ $activo->estado === 'activo' ? 'badge-success' : 'badge-danger' }}">{{ $activo->estado === 'activo' ? 'Activo' : 'De baja' }}</span></td>
                    <td style="white-space:nowrap;">
                        <button type="button" class="btn btn-dark btn-sm"
                                data-modal="modalActivo"
                                data-campo-registro_id="{{ $activo->id }}"
                                data-campo-codigo="{{ $activo->codigo }}"
                                data-campo-nombre="{{ $activo->nombre }}"
                                data-campo-categoria="{{ $activo->categoria }}"
                                data-campo-proveedor_id="{{ $activo->proveedor_id }}"
                                data-campo-fecha_compra="{{ $activo->fecha_compra?->format('Y-m-d') }}"
                                data-campo-costo="{{ $activo->costo }}"
                                data-campo-estado="{{ $activo->estado }}"
                                data-campo-ubicacion="{{ $activo->ubicacion }}"
                                data-campo-observaciones="{{ $activo->observaciones }}">
                            Editar
                        </button>
                        <form method="POST" action="{{ route('admin.activos-fijos.destroy', $activo) }}"
                              style="display:inline;" data-confirmar="¿Eliminar el activo {{ $activo->nombre }}? También se borra el egreso ligado.">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--ink-3);">Sin activos fijos registrados</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $activos->links() }}
</div>

<x-modal id="modalActivo" titulo="Comprar Activo Fijo">
    <form method="POST" action="{{ route('admin.activos-fijos.store') }}" id="formActivo">
        @csrf
        <input type="hidden" name="registro_id" value="">

        <div class="form-grid">
            <div class="form-group">
                <label for="af-codigo">Código</label>
                <input type="text" id="af-codigo" name="codigo" maxlength="50">
            </div>
            <div class="form-group">
                <label for="af-nombre">Nombre <span>*</span></label>
                <input type="text" id="af-nombre" name="nombre" required maxlength="255">
            </div>
            <div class="form-group">
                <label for="af-categoria">Categoría</label>
                <select id="af-categoria" name="categoria">
                    <option value="">— Sin especificar —</option>
                    @foreach ($categorias as $cat)
                        <option value="{{ $cat }}">{{ $cat }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="af-proveedor">Proveedor</label>
                <select id="af-proveedor" name="proveedor_id">
                    <option value="">— Sin especificar —</option>
                    @foreach ($proveedores as $proveedor)
                        <option value="{{ $proveedor->id }}">{{ $proveedor->razon_social }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="af-fecha">Fecha de compra <span>*</span></label>
                <input type="date" id="af-fecha" name="fecha_compra" required value="{{ now()->toDateString() }}">
            </div>
            <div class="form-group">
                <label for="af-costo">Costo (S/) <span>*</span></label>
                <input type="number" id="af-costo" name="costo" step="0.01" min="0" required value="0.00">
            </div>
            <div class="form-group">
                <label for="af-estado">Estado <span>*</span></label>
                <select id="af-estado" name="estado" required>
                    <option value="activo">Activo</option>
                    <option value="de_baja">De baja</option>
                </select>
            </div>
            <div class="form-group">
                <label for="af-ubicacion">Ubicación</label>
                <input type="text" id="af-ubicacion" name="ubicacion" maxlength="255">
            </div>
        </div>

        <p style="margin:-4px 0 12px;font-size:12px;color:var(--ink-3);">El costo queda registrado también como un Egreso automático.</p>

        <div class="form-group">
            <label for="af-observaciones">Observaciones</label>
            <textarea id="af-observaciones" name="observaciones" rows="2"></textarea>
        </div>

        <div class="header-btns" style="justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalActivo">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-modal>
@endsection

@push('scripts')
<script>
const formActivo = document.getElementById('formActivo');
const URL_ACTIVOS = '{{ url('admin/activos-fijos') }}';

document.getElementById('btnNuevoActivo').addEventListener('click', () => {
    formActivo.reset();
    formActivo.action = URL_ACTIVOS;
    formActivo.querySelector('[name="_method"]')?.remove();
    formActivo.querySelector('[name="registro_id"]').value = '';
    document.getElementById('af-fecha').value = '{{ now()->toDateString() }}';
    abrirModal('modalActivo');
});

formActivo.addEventListener('submit', function () {
    const id = this.querySelector('[name="registro_id"]').value;
    if (!id) return;

    this.action = URL_ACTIVOS + '/' + id;
    if (!this.querySelector('[name="_method"]')) {
        const metodo = document.createElement('input');
        metodo.type = 'hidden';
        metodo.name = '_method';
        metodo.value = 'PUT';
        this.appendChild(metodo);
    }
});

@if ($abrirCrear)
    document.getElementById('btnNuevoActivo').click();
@endif
</script>
@endpush
