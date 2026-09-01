@extends('layouts.admin')

@section('title', 'Merch')
@section('crumb', 'Artículos promocionales')

@section('content')

<x-page-header titulo="Merch" subtitulo="Catálogo y existencias del merchandising de la marca">
    <x-slot:acciones>
        <a href="{{ route('admin.merch.movimientos') }}" class="btn btn-secondary">
            <span class="btn-text">Movimientos</span>
        </a>
        <button type="button" class="btn btn-primary" data-modal="modalArticulo">
            <span class="btn-icon">＋</span><span class="btn-text">Nuevo Artículo</span>
        </button>
    </x-slot:acciones>
</x-page-header>

<div class="content-card">
    <div class="lista-header">
        <h3>Catálogo de Merch</h3>

        <form method="GET" class="filtros">
            <x-buscador :valor="$busqueda" placeholder="Buscar por nombre del artículo…" />
            <button type="submit" class="btn btn-primary btn-sm"><span class="btn-text">Filtrar</span></button>
            @if ($busqueda !== '')
                <a href="{{ route('admin.merch.index') }}" class="btn btn-secondary btn-sm">
                    <span class="btn-text">Limpiar</span>
                </a>
            @endif
        </form>
    </div>

    @if ($busqueda !== '')
        <p class="resultado-busqueda">
            {{ $items->total() }} resultado(s) para <strong>"{{ $busqueda }}"</strong>
        </p>
    @endif

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Artículo</th>
                    <th>Categoría</th>
                    <th>Descripción</th>
                    <th>Costo unit.</th>
                    <th>Stock</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($items as $articulo)
                <tr>
                    <td><strong>{{ $articulo->nombre }}</strong></td>
                    <td>{{ $articulo->categoria ?: "—" }}</td>
                    <td>{{ Str::limit($articulo->descripcion, 60) ?: "—" }}</td>
                    <td>S/ {{ number_format($articulo->precio, 2) }}</td>
                    <td>
                        <strong style="color:{{ $articulo->stock > 0 ? '#11704A' : '#A8231F' }}">
                            {{ number_format($articulo->stock) }}
                        </strong>
                        <a href="{{ route('admin.merch.movimientos', ['merch' => $articulo->id]) }}"
                           style="font-size:12px;margin-left:6px;">ver</a>
                    </td>
                    <td style="white-space:nowrap;">
                        <button type="button" class="btn btn-primary btn-sm"
                                data-modal="modalEntrega"
                                data-campo-merch_id="{{ $articulo->id }}"
                                data-campo-articulo="{{ $articulo->nombre }}"
                                data-campo-disponible="{{ $articulo->stock }}"
                                @disabled($articulo->stock < 1)
                        >
                            Entregar
                        </button>
                        <button type="button" class="btn btn-dark btn-sm"
                                data-modal="modalArticulo"
                                data-campo-registro_id="{{ $articulo->id }}"
                                data-campo-nombre="{{ $articulo->nombre }}"
                                data-campo-categoria="{{ $articulo->categoria }}"
                                data-campo-precio="{{ $articulo->precio }}"
                                data-campo-descripcion="{{ $articulo->descripcion }}"
                        >
                            Editar
                        </button>
                        <form method="POST" action="{{ route('admin.merch.destroy', $articulo) }}"
                              style="display:inline;" data-confirmar="¿Eliminar este artículo?">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;padding:40px;color:#666;">Sin registros</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $items->links() }}
</div>

<x-modal id="modalArticulo" titulo="Nuevo Artículo">
    <form method="POST" action="{{ route('admin.merch.store') }}" id="formArticulo">
        @csrf
        <input type="hidden" name="registro_id" value="">

        <div class="form-grid">
            <div class="form-group">
                <label for="nombre">Nombre <span>*</span></label>
                <input type="text" id="nombre" name="nombre" required maxlength="150">
            </div>
            <div class="form-group">
                <label for="categoria">Categoría</label>
                <input type="text" id="categoria" name="categoria" maxlength="100">
            </div>
            <div class="form-group">
                <label for="precio">Costo unitario <span>*</span></label>
                <input type="number" id="precio" name="precio" required step="0.01" min="0">
            </div>
        </div>

        <div class="form-group">
            <label for="descripcion">Descripción</label>
            <textarea id="descripcion" name="descripcion" rows="2"></textarea>
        </div>

        <p style="color:#6B6F78;font-size:12.5px;margin:0 0 14px;">
            El stock no se escribe a mano: entra por las órdenes de compra y sale con las entregas.
        </p>

        <div class="header-btns" style="justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalArticulo">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-modal>

<x-modal id="modalEntrega" titulo="Entregar merch a un cliente">
    <form method="POST" action="{{ route('admin.merch.index') }}" id="formEntrega">
        @csrf
        <input type="hidden" name="merch_id" value="">

        <div class="form-grid">
            <div class="form-group">
                <label for="entrega-articulo">Artículo</label>
                <input type="text" id="entrega-articulo" name="articulo" readonly>
            </div>
            <div class="form-group">
                <label for="entrega-disponible">Disponible</label>
                <input type="text" id="entrega-disponible" name="disponible" readonly>
            </div>
            <div class="form-group">
                <label for="entrega-cantidad">Cantidad a entregar <span>*</span></label>
                <input type="number" id="entrega-cantidad" name="cantidad" required min="1" step="1">
            </div>
            <div class="form-group">
                <label for="entrega-fecha">Fecha <span>*</span></label>
                <input type="date" id="entrega-fecha" name="fecha" required value="{{ now()->format('Y-m-d') }}">
            </div>
        </div>

        <div class="form-group">
            <label for="entrega-cliente">Cliente</label>
            <select id="entrega-cliente" name="cliente_id">
                <option value="">— Escribir el nombre abajo —</option>
                @foreach ($clientes as $cliente)
                    <option value="{{ $cliente->id }}">
                        {{ $cliente->nombre_empresa ?: $cliente->nombres }}
                        @if ($cliente->numero_documento) · {{ $cliente->numero_documento }} @endif
                    </option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label for="entrega-cliente-nombre">Nombre del destinatario</label>
            <input type="text" id="entrega-cliente-nombre" name="cliente_nombre" maxlength="255"
                   placeholder="Solo si no está en el listado de clientes">
        </div>

        <div class="form-group">
            <label for="entrega-obs">Observaciones</label>
            <input type="text" id="entrega-obs" name="observaciones" maxlength="255"
                   placeholder="Motivo, campaña, quién lo entregó…">
        </div>

        <div class="header-btns" style="justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalEntrega">Cancelar</button>
            <button type="submit" class="btn btn-primary">Registrar entrega</button>
        </div>
    </form>
</x-modal>
@endsection

@push('scripts')
<script>
// El mismo modal sirve para alta y edición: si trae ID, se envía como PUT.
document.getElementById('formArticulo').addEventListener('submit', function () {
    const id = this.querySelector('[name="registro_id"]').value;
    if (!id) return;

    this.action = '{{ url('admin/merch') }}/' + id;
    if (!this.querySelector('[name="_method"]')) {
        const metodo = document.createElement('input');
        metodo.type = 'hidden';
        metodo.name = '_method';
        metodo.value = 'PUT';
        this.appendChild(metodo);
    }
});

// La entrega apunta al artículo que abrió el modal.
document.getElementById('formEntrega').addEventListener('submit', function (evento) {
    const id = this.querySelector('[name="merch_id"]').value;

    if (!id) {
        evento.preventDefault();
        return;
    }

    const disponible = parseInt(this.querySelector('[name="disponible"]').value, 10) || 0;
    const cantidad   = parseInt(this.querySelector('[name="cantidad"]').value, 10) || 0;

    if (cantidad > disponible) {
        evento.preventDefault();
        window.alert('Solo hay ' + disponible + ' unidad(es) disponibles.');
        return;
    }

    this.action = '{{ url('admin/merch') }}/' + id + '/entregar';
});
</script>
@endpush
