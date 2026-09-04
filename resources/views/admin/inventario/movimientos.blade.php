@extends('layouts.admin')

@php
    $titulos = ['traslado' => 'Traslados', 'devolucion' => 'Devolución a Proveedor'];
    $tituloPagina = $titulos[$filtros['tipo']] ?? 'Movimientos de Inventario';
@endphp

@section('title', $tituloPagina)
@section('crumb', 'Inventario')

@push('styles')
    @vite(['resources/css/modules/reportes.css'])
@endpush

@section('content')

@php
    $productosJs = \App\Models\Producto::activos()->orderBy('nombre')
        ->get(['id', 'codigo', 'nombre'])
        ->map(fn ($p) => ['id' => $p->id, 'codigo' => $p->codigo, 'nombre' => $p->nombre])->values();
@endphp

<x-page-header :titulo="$tituloPagina" subtitulo="Historial de movimientos de stock por almacén">
    <x-slot:acciones>
        @if ($filtros['tipo'] === 'traslado')
            <button type="button" class="btn btn-primary" data-modal="modalTraslado"><span class="btn-text">＋ Nuevo traslado</span></button>
        @elseif ($filtros['tipo'] === 'devolucion')
            <button type="button" class="btn btn-primary" data-modal="modalDevolucion"><span class="btn-text">＋ Nueva devolución</span></button>
        @else
            <button type="button" class="btn btn-primary" data-modal="modalMovimiento"><span class="btn-text">＋ Nuevo movimiento</span></button>
        @endif
    </x-slot:acciones>
</x-page-header>

<div class="content-card">
    <form method="GET" class="rep-filtros-form">
        <div class="filtro-campo">
            <span>Tipo</span>
            <select name="tipo">
                <option value="">Todos</option>
                <option value="entrada" @selected($filtros['tipo'] === 'entrada')>Entrada</option>
                <option value="salida" @selected($filtros['tipo'] === 'salida')>Salida</option>
                <option value="ajuste" @selected($filtros['tipo'] === 'ajuste')>Ajuste</option>
                <option value="traslado" @selected($filtros['tipo'] === 'traslado')>Traslado</option>
                <option value="devolucion" @selected($filtros['tipo'] === 'devolucion')>Devolución</option>
            </select>
        </div>
        <div class="filtro-campo">
            <span>Almacén</span>
            <select name="almacen_id">
                <option value="">Todos</option>
                @foreach ($almacenes as $almacen)
                    <option value="{{ $almacen->id }}" @selected((int) $filtros['almacen_id'] === $almacen->id)>{{ $almacen->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="filtro-campo">
            <span>Desde</span>
            <input type="date" name="desde" value="{{ $filtros['desde'] }}">
        </div>
        <div class="filtro-campo">
            <span>Hasta</span>
            <input type="date" name="hasta" value="{{ $filtros['hasta'] }}">
        </div>
        <div class="filtro-campo" style="flex:1;min-width:180px;">
            <span>Buscar (motivo/referencia)</span>
            <input type="text" name="q" value="{{ $filtros['q'] }}">
        </div>
        <button type="submit" class="btn btn-primary">Filtrar</button>
        @if ($filtros['tipo'] !== '' || $filtros['almacen_id'] > 0 || $filtros['desde'] !== '' || $filtros['hasta'] !== '' || $filtros['q'] !== '')
            <a href="{{ route('admin.inventario.movimientos') }}" class="btn btn-secondary">Limpiar</a>
        @endif
    </form>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Fecha</th><th>Producto</th><th>Almacén</th><th>Tipo</th>
                    <th class="num">Cantidad</th><th class="num">Stock</th><th>Motivo / Referencia</th><th>Usuario</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($movimientos as $m)
                <tr>
                    <td>{{ $m->created_at?->format('d/m/Y H:i') }}</td>
                    <td>{{ $m->producto?->nombre ?? '—' }}</td>
                    <td>{{ $m->almacen?->nombre ?? '—' }}</td>
                    <td><span class="rep-badge estado-{{ in_array($m->tipo, ['entrada','traslado'], true) ? 'alta' : ($m->tipo === 'ajuste' ? 'media' : 'baja') }}">{{ ucfirst($m->tipo) }}</span></td>
                    <td class="num">{{ number_format($m->cantidad) }}</td>
                    <td class="num">{{ $m->stock_anterior }} → {{ $m->stock_nuevo }}</td>
                    <td>{{ $m->motivo ?: ($m->referencia ?: '—') }}</td>
                    <td>{{ $m->usuario?->username ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--ink-3);">Sin movimientos para el filtro seleccionado.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $movimientos->links() }}
</div>

{{-- ══ Movimiento manual (entrada/salida/ajuste) — reusa admin.productos.stock ══ --}}
<x-modal id="modalMovimiento" titulo="Nuevo movimiento de stock">
    <form method="POST" action="" id="formMovimiento">
        @csrf
        <div class="form-group" style="margin-bottom:12px;">
            <label>Producto</label>
            <div class="nv-buscador" id="buscador-mov">
                <input type="text" class="nv-buscar-input" data-buscar-producto autocomplete="off" placeholder="Buscar producto por nombre o código…">
                <div class="nv-dropdown" data-dropdown-producto></div>
            </div>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label>Almacén <span>*</span></label>
                <select name="almacen_id" required>
                    @foreach ($almacenes as $almacen)
                        <option value="{{ $almacen->id }}">{{ $almacen->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Tipo <span>*</span></label>
                <select name="tipo" required>
                    <option value="entrada">Entrada</option>
                    <option value="salida">Salida</option>
                    <option value="ajuste">Ajuste (fija el total)</option>
                </select>
            </div>
            <div class="form-group">
                <label>Cantidad <span>*</span></label>
                <input type="number" name="cantidad" min="1" required>
            </div>
        </div>
        <div class="form-group">
            <label>Motivo</label>
            <input type="text" name="motivo" maxlength="255">
        </div>
        <div class="header-btns" style="justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalMovimiento">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-modal>

{{-- ══ Traslado entre almacenes ══ --}}
<x-modal id="modalTraslado" titulo="Nuevo traslado entre almacenes">
    <form method="POST" action="{{ route('admin.inventario.traslados.store') }}">
        @csrf
        <input type="hidden" name="producto_id" data-campo-producto-id>
        <div class="form-group" style="margin-bottom:12px;">
            <label>Producto</label>
            <div class="nv-buscador" id="buscador-traslado">
                <input type="text" class="nv-buscar-input" data-buscar-producto autocomplete="off" placeholder="Buscar producto por nombre o código…">
                <div class="nv-dropdown" data-dropdown-producto></div>
            </div>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label>Almacén de origen <span>*</span></label>
                <select name="almacen_origen_id" required>
                    @foreach ($almacenes as $almacen)
                        <option value="{{ $almacen->id }}">{{ $almacen->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Almacén de destino <span>*</span></label>
                <select name="almacen_destino_id" required>
                    @foreach ($almacenes as $almacen)
                        <option value="{{ $almacen->id }}">{{ $almacen->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Cantidad <span>*</span></label>
                <input type="number" name="cantidad" min="1" required>
            </div>
        </div>
        <div class="form-group">
            <label>Motivo</label>
            <input type="text" name="motivo" maxlength="255">
        </div>
        <div class="header-btns" style="justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalTraslado">Cancelar</button>
            <button type="submit" class="btn btn-primary">Trasladar</button>
        </div>
    </form>
</x-modal>

{{-- ══ Devolución a proveedor ══ --}}
<x-modal id="modalDevolucion" titulo="Nueva devolución a proveedor">
    <form method="POST" action="{{ route('admin.inventario.devoluciones.store') }}">
        @csrf
        <input type="hidden" name="producto_id" data-campo-producto-id>
        <div class="form-group" style="margin-bottom:12px;">
            <label>Producto</label>
            <div class="nv-buscador" id="buscador-devolucion">
                <input type="text" class="nv-buscar-input" data-buscar-producto autocomplete="off" placeholder="Buscar producto por nombre o código…">
                <div class="nv-dropdown" data-dropdown-producto></div>
            </div>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label>Almacén <span>*</span></label>
                <select name="almacen_id" required>
                    @foreach ($almacenes as $almacen)
                        <option value="{{ $almacen->id }}">{{ $almacen->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Proveedor</label>
                <select name="proveedor_id">
                    <option value="">— Sin especificar —</option>
                    @foreach ($proveedores as $proveedor)
                        <option value="{{ $proveedor->id }}">{{ $proveedor->razon_social }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Cantidad <span>*</span></label>
                <input type="number" name="cantidad" min="1" required>
            </div>
        </div>
        <div class="form-group">
            <label>Motivo</label>
            <input type="text" name="motivo" maxlength="255" placeholder="Producto defectuoso, exceso de pedido…">
        </div>
        <div class="header-btns" style="justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalDevolucion">Cancelar</button>
            <button type="submit" class="btn btn-primary">Registrar devolución</button>
        </div>
    </form>
</x-modal>

@endsection

@push('scripts')
<script>
const PRODUCTOS_INV = @json($productosJs);
const URL_STOCK = '{{ url('admin/productos') }}';

// Un solo buscador reutilizado por los 3 modales (Movimiento/Traslado/Devolución).
document.querySelectorAll('[data-buscar-producto]').forEach((input) => {
    const contenedor = input.closest('.nv-buscador');
    const dropdown = contenedor.querySelector('[data-dropdown-producto]');
    const form = input.closest('form');

    function elegir(p) {
        input.value = p.nombre;
        dropdown.classList.remove('activo');

        const campoId = form.querySelector('[data-campo-producto-id]');
        if (campoId) {
            campoId.value = p.id;
        } else {
            // El modal de Movimiento reusa admin.productos.stock: la ruta
            // necesita el {producto} en la URL, no como campo del form.
            form.action = URL_STOCK + '/' + p.id + '/stock';
        }
    }

    input.addEventListener('input', () => {
        const termino = input.value.trim().toUpperCase();
        if (termino === '') { dropdown.classList.remove('activo'); return; }

        const resultados = PRODUCTOS_INV.filter((p) =>
            p.nombre.toUpperCase().includes(termino) || (p.codigo || '').toUpperCase().includes(termino)
        ).slice(0, 15);

        if (!resultados.length) {
            dropdown.innerHTML = '<div class="nv-sin-resultados">Sin resultados</div>';
            dropdown.classList.add('activo');
            return;
        }

        dropdown.innerHTML = resultados.map((p, i) =>
            `<div class="nv-item" data-idx="${i}"><div class="nv-item-top"><span class="nv-item-cod">${p.codigo || '—'}</span><span class="nv-item-desc">${p.nombre}</span></div></div>`
        ).join('');
        dropdown.querySelectorAll('.nv-item').forEach((el) => {
            el.addEventListener('click', () => elegir(resultados[Number(el.dataset.idx)]));
        });
        dropdown.classList.add('activo');
    });

    document.addEventListener('click', (e) => {
        if (!contenedor.contains(e.target)) dropdown.classList.remove('activo');
    });
});
</script>
@endpush
