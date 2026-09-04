@extends('layouts.admin')

@section('title', 'Solicitar Cotización')
@section('crumb', 'Compras')

@section('content')

<x-page-header titulo="Solicitar Cotización a Proveedor" subtitulo="Pedile un precio a un proveedor antes de generar la orden de compra">
    <x-slot:acciones>
        <button type="button" class="btn btn-primary" id="btnNuevaCotizacion">
            <span class="btn-icon">＋</span><span class="btn-text">Solicitar Cotización</span>
        </button>
    </x-slot:acciones>
</x-page-header>

<div class="content-card">
    <form method="GET" class="filtros" style="margin-bottom:18px;">
        <div class="filtro-campo">
            <span>Buscar</span>
            <input type="text" name="q" value="{{ $busqueda }}" placeholder="Número o proveedor…">
        </div>
        <div class="filtro-campo">
            <span>Estado</span>
            <select name="estado" class="filtro-select">
                <option value="">Todos</option>
                @foreach (['enviada' => 'Enviada', 'respondida' => 'Respondida', 'vencida' => 'Vencida'] as $valor => $texto)
                    <option value="{{ $valor }}" @selected($estadoSel === $valor)>{{ $texto }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-secondary">Filtrar</button>
        @if ($busqueda !== '' || $estadoSel !== '')
            <a href="{{ route('admin.cotizaciones-proveedor.index') }}" class="btn btn-secondary">Limpiar</a>
        @endif
    </form>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Número</th><th>Fecha</th><th>Proveedor</th><th>Productos</th><th>Estado</th><th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($cotizaciones as $cotizacion)
                @php
                    $claseEstado = match ($cotizacion->estado) {
                        'respondida' => 'badge-success',
                        'vencida'    => 'badge-danger',
                        default      => 'badge-warning',
                    };
                    $listaProductos = collect($cotizacion->productos)->pluck('descripcion');
                    $textoProductosResumen = $listaProductos->implode(', ');
                    $textoProductosEdicion = $listaProductos->implode("\n");
                @endphp
                <tr>
                    <td><strong>{{ $cotizacion->numero }}</strong></td>
                    <td>{{ $cotizacion->fecha?->format('d/m/Y') }}</td>
                    <td>{{ $cotizacion->proveedor?->razon_social ?: '—' }}</td>
                    <td>{{ Str::limit($textoProductosResumen, 60) ?: '—' }}</td>
                    <td><span class="badge {{ $claseEstado }}">{{ ucfirst($cotizacion->estado) }}</span></td>
                    <td style="white-space:nowrap;">
                        @if ($cotizacion->proveedor?->email)
                            <form method="POST" action="{{ route('admin.cotizaciones-proveedor.enviar', $cotizacion) }}" style="display:inline;">
                                @csrf
                                <button type="submit" class="btn btn-secondary btn-sm" title="Enviar por correo">✉ Enviar</button>
                            </form>
                        @endif
                        <button type="button" class="btn btn-dark btn-sm"
                                data-modal="modalCotizacion"
                                data-campo-registro_id="{{ $cotizacion->id }}"
                                data-campo-fecha="{{ $cotizacion->fecha?->format('Y-m-d') }}"
                                data-campo-proveedor_id="{{ $cotizacion->proveedor_id }}"
                                data-campo-productos="{{ $textoProductosEdicion }}"
                                data-campo-estado="{{ $cotizacion->estado }}"
                                data-campo-observaciones="{{ $cotizacion->observaciones }}">
                            Editar
                        </button>
                        <form method="POST" action="{{ route('admin.cotizaciones-proveedor.destroy', $cotizacion) }}"
                              style="display:inline;" data-confirmar="¿Eliminar la solicitud {{ $cotizacion->numero }}?">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--ink-3);">Sin solicitudes registradas</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $cotizaciones->links() }}
</div>

<x-modal id="modalCotizacion" titulo="Solicitar Cotización">
    <form method="POST" action="{{ route('admin.cotizaciones-proveedor.store') }}" id="formCotizacion">
        @csrf
        <input type="hidden" name="registro_id" value="">

        <div class="form-grid">
            <div class="form-group">
                <label for="cp-fecha">Fecha <span>*</span></label>
                <input type="date" id="cp-fecha" name="fecha" required value="{{ now()->toDateString() }}">
            </div>
            <div class="form-group">
                <label for="cp-proveedor">Proveedor <span>*</span></label>
                <select id="cp-proveedor" name="proveedor_id" required>
                    <option value="">— Elegir —</option>
                    @foreach ($proveedores as $proveedor)
                        <option value="{{ $proveedor->id }}">{{ $proveedor->razon_social }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="cp-estado">Estado <span>*</span></label>
                <select id="cp-estado" name="estado" required>
                    <option value="enviada">Enviada</option>
                    <option value="respondida">Respondida</option>
                    <option value="vencida">Vencida</option>
                </select>
            </div>
        </div>

        <div class="form-group" style="margin-top:5px;">
            <label for="cp-productos">Productos a cotizar <span class="lbl-opcional">(uno por línea)</span></label>
            <textarea id="cp-productos" name="productos" style="height:90px;" placeholder="50 planchas de drywall 1/2&#10;20 parantes galvanizados…"></textarea>
        </div>

        <div class="form-group" style="margin-top:15px;">
            <label for="cp-observaciones">Observaciones</label>
            <textarea id="cp-observaciones" name="observaciones" style="height:60px;"></textarea>
        </div>

        <div class="header-btns" style="justify-content:flex-end;margin-top:20px;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalCotizacion">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-modal>
@endsection

@push('scripts')
<script>
const formCotizacion = document.getElementById('formCotizacion');
const URL_COTIZACIONES = '{{ url('admin/cotizaciones-proveedor') }}';

document.getElementById('btnNuevaCotizacion').addEventListener('click', () => {
    formCotizacion.reset();
    formCotizacion.action = URL_COTIZACIONES;
    formCotizacion.querySelector('[name="_method"]')?.remove();
    formCotizacion.querySelector('[name="registro_id"]').value = '';
    document.getElementById('cp-fecha').value = '{{ now()->toDateString() }}';
    abrirModal('modalCotizacion');
});

formCotizacion.addEventListener('submit', function () {
    const id = this.querySelector('[name="registro_id"]').value;
    if (!id) return;

    this.action = URL_COTIZACIONES + '/' + id;
    if (!this.querySelector('[name="_method"]')) {
        const metodo = document.createElement('input');
        metodo.type = 'hidden';
        metodo.name = '_method';
        metodo.value = 'PUT';
        this.appendChild(metodo);
    }
});

@if ($abrirCrear)
    document.getElementById('btnNuevaCotizacion').click();
@endif
</script>
@endpush
