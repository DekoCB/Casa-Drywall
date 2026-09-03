@extends('layouts.admin')

@section('title', 'Pedidos')
@section('crumb', 'Gestión comercial')

@section('content')

<x-page-header titulo="Pedidos de Clientes" subtitulo="Pedidos que los clientes hacen directamente, antes de convertirse en venta">
    <x-slot:acciones>
        <button type="button" class="btn btn-primary" id="btnNuevoPedido">
            <span class="btn-icon">＋</span><span class="btn-text">Nuevo Pedido</span>
        </button>
    </x-slot:acciones>
</x-page-header>

<div class="content-card">
    <form method="GET" class="filtros" style="margin-bottom:18px;">
        <div class="filtro-campo">
            <span>Buscar</span>
            <input type="text" name="q" value="{{ $busqueda }}" placeholder="Cliente, RUC o destino…">
        </div>
        <div class="filtro-campo">
            <span>Estado</span>
            <select name="estado" class="filtro-select">
                <option value="">Todos</option>
                @foreach (['Pendiente', 'En proceso', 'Entregado', 'Cancelado'] as $estadoOpcion)
                    <option value="{{ $estadoOpcion }}" @selected($estadoSel === $estadoOpcion)>{{ $estadoOpcion }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-secondary">Filtrar</button>
        @if ($busqueda !== '' || $estadoSel !== '')
            <a href="{{ route('admin.pedidos.index') }}" class="btn btn-secondary">Limpiar</a>
        @endif
    </form>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Fecha</th><th>Cliente</th><th>RUC</th><th>Destino</th>
                    <th>Transporte</th><th>Descripción</th>
                    <th class="num">Total (S/)</th><th>Estado</th><th>Archivo</th><th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($pedidos as $pedido)
                @php
                    $claseEstado = match ($pedido->estado) {
                        'En proceso' => 'badge-info',
                        'Entregado'  => 'badge-success',
                        'Cancelado'  => 'badge-danger',
                        default      => 'badge-warning',
                    };
                @endphp
                <tr>
                    <td>{{ $pedido->fecha?->format('d/m/Y') }}</td>
                    <td><strong>{{ $pedido->cliente_nombre }}</strong></td>
                    <td>{{ $pedido->ruc ?: '—' }}</td>
                    <td>{{ $pedido->destino ?: '—' }}</td>
                    <td>{{ $pedido->empresa_transporte ?: '—' }}</td>
                    <td>{{ Str::limit($pedido->productos, 50) ?: '—' }}</td>
                    <td class="num">{{ number_format((float) $pedido->total_soles, 2) }}</td>
                    <td><span class="badge {{ $claseEstado }}">{{ $pedido->estado }}</span></td>
                    <td>
                        @if ($pedido->archivo_pedido)
                            <a href="{{ Storage::disk('public')->url($pedido->archivo_pedido) }}" target="_blank">Ver</a>
                        @else
                            —
                        @endif
                    </td>
                    <td style="white-space:nowrap;">
                        <button type="button" class="btn btn-dark btn-sm"
                                data-modal="modalPedido"
                                data-campo-registro_id="{{ $pedido->id }}"
                                data-campo-fecha="{{ $pedido->fecha?->format('Y-m-d') }}"
                                data-campo-cliente_nombre="{{ $pedido->cliente_nombre }}"
                                data-campo-ruc="{{ $pedido->ruc }}"
                                data-campo-telefono="{{ $pedido->telefono }}"
                                data-campo-destino="{{ $pedido->destino }}"
                                data-campo-empresa_transporte="{{ $pedido->empresa_transporte }}"
                                data-campo-productos="{{ $pedido->productos }}"
                                data-campo-total_soles="{{ $pedido->total_soles }}"
                                data-campo-estado="{{ $pedido->estado }}"
                                data-campo-observaciones="{{ $pedido->observaciones }}">
                            Editar
                        </button>
                        <form method="POST" action="{{ route('admin.pedidos.destroy', $pedido) }}"
                              style="display:inline;" data-confirmar="¿Eliminar el pedido de {{ $pedido->cliente_nombre }}?">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" style="text-align:center;padding:40px;color:var(--ink-3);">Sin pedidos registrados</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $pedidos->links() }}
</div>

<x-modal id="modalPedido" titulo="Nuevo Pedido">
    <form method="POST" action="{{ route('admin.pedidos.store') }}" id="formPedido" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="registro_id" value="">

        <div class="form-grid">
            <div class="form-group">
                <label for="ped-fecha">Fecha <span>*</span></label>
                <input type="date" id="ped-fecha" name="fecha" required value="{{ now()->toDateString() }}">
            </div>
            <div class="form-group">
                <label for="ped-cliente">Cliente <span>*</span></label>
                <input type="text" id="ped-cliente" name="cliente_nombre" required maxlength="200">
            </div>
            <div class="form-group">
                <label for="ped-ruc">RUC / DNI</label>
                <input type="text" id="ped-ruc" name="ruc" maxlength="20">
            </div>
            <div class="form-group">
                <label for="ped-telefono">Teléfono</label>
                <input type="text" id="ped-telefono" name="telefono" maxlength="50">
            </div>
            <div class="form-group">
                <label for="ped-destino">Destino</label>
                <input type="text" id="ped-destino" name="destino" maxlength="150">
            </div>
            <div class="form-group">
                <label for="ped-transporte">Empresa de Transporte</label>
                <input type="text" id="ped-transporte" name="empresa_transporte" maxlength="200">
            </div>
        </div>

        <div class="form-group" style="margin-top:5px;">
            <label for="ped-productos">Productos solicitados</label>
            <textarea id="ped-productos" name="productos" style="height:80px;" placeholder="Descripción de lo que pide el cliente…"></textarea>
        </div>

        <div class="form-grid" style="margin-top:15px;">
            <div class="form-group">
                <label for="ped-total">Total (S/) <span>*</span></label>
                <input type="number" id="ped-total" name="total_soles" step="0.01" min="0" required value="0.00">
            </div>
            <div class="form-group">
                <label for="ped-estado">Estado <span>*</span></label>
                <select id="ped-estado" name="estado" required>
                    <option value="Pendiente">Pendiente</option>
                    <option value="En proceso">En proceso</option>
                    <option value="Entregado">Entregado</option>
                    <option value="Cancelado">Cancelado</option>
                </select>
            </div>
        </div>

        <div class="form-group" style="margin-top:15px;">
            <label for="ped-observaciones">Observaciones</label>
            <textarea id="ped-observaciones" name="observaciones" style="height:60px;"></textarea>
        </div>

        <div class="form-group" style="margin-top:15px;">
            <label for="ped-archivo">Archivo adjunto <span class="lbl-opcional">(opcional — reemplaza al anterior si subes uno nuevo)</span></label>
            <input type="file" id="ped-archivo" name="archivo_pedido">
        </div>

        <div class="header-btns" style="justify-content:flex-end;margin-top:20px;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalPedido">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-modal>
@endsection

@push('scripts')
<script>
const formPedido = document.getElementById('formPedido');
const URL_PEDIDOS = '{{ url('admin/pedidos') }}';

document.getElementById('btnNuevoPedido').addEventListener('click', () => {
    formPedido.reset();
    formPedido.action = URL_PEDIDOS;
    formPedido.querySelector('[name="_method"]')?.remove();
    formPedido.querySelector('[name="registro_id"]').value = '';
    document.getElementById('ped-fecha').value = '{{ now()->toDateString() }}';
    abrirModal('modalPedido');
});

formPedido.addEventListener('submit', function () {
    const id = this.querySelector('[name="registro_id"]').value;
    if (!id) return;

    this.action = URL_PEDIDOS + '/' + id;
    if (!this.querySelector('[name="_method"]')) {
        const metodo = document.createElement('input');
        metodo.type = 'hidden';
        metodo.name = '_method';
        metodo.value = 'PUT';
        this.appendChild(metodo);
    }
});

@if ($abrirCrear)
    document.getElementById('btnNuevoPedido').click();
@endif
</script>
@endpush
