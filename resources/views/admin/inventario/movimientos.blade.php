@extends('layouts.admin')

@section('title', 'Inventario')
@section('crumb', 'Inventario')

@push('styles')
    @vite(['resources/css/modules/reportes.css'])
@endpush

@section('content')

@php
    $productosJs = \App\Models\Producto::activos()->orderBy('nombre')
        ->with('stockPorAlmacen')
        ->get(['id', 'codigo', 'nombre'])
        ->map(fn ($p) => [
            'id' => $p->id, 'codigo' => $p->codigo, 'nombre' => $p->nombre,
            'stocks' => $p->stockPorAlmacen->pluck('stock', 'almacen_id'),
        ])->values();
@endphp

<x-page-header titulo="Inventario" subtitulo="Stock actual por almacén, con acciones rápidas">
    <x-slot:acciones>
        <a href="{{ route('admin.inventario.reporte') }}" class="btn btn-secondary btn-sm"><span class="btn-text">📊 Reporte</span></a>
        <a href="{{ route('admin.productos.importar') }}" class="btn btn-secondary btn-sm"><span class="btn-text">⬆ Importar</span></a>
        <button type="button" class="btn btn-primary btn-sm" data-abrir-movimiento="entrada"><span class="btn-text">＋ Ingreso</span></button>
        <button type="button" class="btn btn-primary btn-sm" data-abrir-movimiento="salida"><span class="btn-text">− Salida</span></button>
        <a href="{{ route('admin.inventario.movimientos.historial') }}" class="btn btn-secondary btn-sm"><span class="btn-text">🕘 Historial</span></a>
    </x-slot:acciones>
</x-page-header>

<div class="content-card">
    <form method="GET" class="rep-filtros-form">
        <div class="filtro-campo">
            <span>Almacén</span>
            <select name="almacen_id">
                <option value="">Todos</option>
                @foreach ($almacenes as $almacen)
                    <option value="{{ $almacen->id }}" @selected((string) $filtros['almacen'] === (string) $almacen->id)>{{ $almacen->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="filtro-campo" style="flex:1;min-width:220px;">
            <span>Buscar producto</span>
            <input type="text" name="q" value="{{ $filtros['q'] }}" placeholder="Nombre o código…">
        </div>
        <button type="submit" class="btn btn-primary">Filtrar</button>

        <div class="rep-exportar">
            <a href="{{ route('admin.inventario.movimientos.excel', request()->query()) }}" class="btn btn-secondary btn-sm"><span class="btn-text">⬇ Excel</span></a>
            <a href="{{ route('admin.inventario.movimientos.pdf', request()->query()) }}" class="btn btn-secondary btn-sm"><span class="btn-text">📄 PDF</span></a>
        </div>
    </form>

    <div class="rep-resumen">
        <div class="rep-kpi">
            <div class="rep-kpi-label">Productos</div>
            <div class="rep-kpi-val">{{ $resumen['productos'] }}</div>
        </div>
        <div class="rep-kpi">
            <div class="rep-kpi-label">Filas (producto × almacén)</div>
            <div class="rep-kpi-val">{{ $resumen['filas'] }}</div>
        </div>
        <div class="rep-kpi">
            <div class="rep-kpi-label">Unidades en stock</div>
            <div class="rep-kpi-val">{{ number_format($resumen['unidades']) }}</div>
        </div>
    </div>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Producto</th><th>Almacén</th><th class="num">Stock</th><th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($items as $fila)
                <tr>
                    <td>{{ $fila['codigo'] }} — {{ $fila['nombre'] }}</td>
                    <td>{{ $fila['almacen'] }}</td>
                    <td class="num">{{ number_format($fila['stock']) }}</td>
                    <td style="white-space:nowrap;">
                        <button type="button" class="btn btn-secondary btn-sm" title="Trasladar a otro almacén"
                                data-accion="trasladar" data-producto-id="{{ $fila['producto_id'] }}"
                                data-producto-nombre="{{ $fila['codigo'] }} — {{ $fila['nombre'] }}"
                                data-almacen-id="{{ $fila['almacen_id'] }}">↔ Trasladar</button>
                        <button type="button" class="btn btn-secondary btn-sm" title="Registrar salida de stock"
                                data-accion="remover" data-producto-id="{{ $fila['producto_id'] }}"
                                data-producto-nombre="{{ $fila['codigo'] }} — {{ $fila['nombre'] }}"
                                data-almacen-id="{{ $fila['almacen_id'] }}">− Remover</button>
                        <button type="button" class="btn btn-secondary btn-sm" title="Fijar el stock a un valor exacto"
                                data-accion="ajuste" data-producto-id="{{ $fila['producto_id'] }}"
                                data-producto-nombre="{{ $fila['codigo'] }} — {{ $fila['nombre'] }}"
                                data-almacen-id="{{ $fila['almacen_id'] }}">⚙ Ajuste</button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" style="text-align:center;padding:40px;color:var(--ink-3);">Sin productos para el filtro seleccionado.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ══ Movimiento manual (entrada/salida/ajuste) — reusa admin.productos.stock ══ --}}
<x-modal id="modalMovimiento" titulo="Movimiento de stock">
    <form method="POST" action="" id="formMovimiento">
        @csrf
        <div class="form-group" style="margin-bottom:12px;">
            <label>Producto</label>
            <div class="nv-buscador" id="buscador-mov">
                <input type="text" class="nv-buscar-input" id="movBuscarInput" data-buscar-producto autocomplete="off" placeholder="Buscar producto por nombre o código…">
                <div class="nv-dropdown" data-dropdown-producto></div>
            </div>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label>Almacén <span>*</span></label>
                <select name="almacen_id" id="movAlmacenId" data-campo-stock-almacen required>
                    @foreach ($almacenes as $almacen)
                        <option value="{{ $almacen->id }}">{{ $almacen->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Tipo <span>*</span></label>
                <select name="tipo" id="movTipo" required>
                    <option value="entrada">Entrada</option>
                    <option value="salida">Salida</option>
                    <option value="ajuste">Ajuste (fija el total)</option>
                </select>
            </div>
            <div class="form-group">
                <div class="stock-actual-hint" data-stock-actual>Stock actual: —</div>
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
<x-modal id="modalTraslado" titulo="Trasladar entre almacenes">
    <form method="POST" action="{{ route('admin.inventario.traslados.store') }}">
        @csrf
        <input type="hidden" name="producto_id" id="trasladoProductoId" data-campo-producto-id>
        <div class="form-group" style="margin-bottom:12px;">
            <label>Producto</label>
            <div class="nv-buscador" id="buscador-traslado">
                <input type="text" class="nv-buscar-input" id="trasladoBuscarInput" data-buscar-producto autocomplete="off" placeholder="Buscar producto por nombre o código…">
                <div class="nv-dropdown" data-dropdown-producto></div>
            </div>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label>Almacén de origen <span>*</span></label>
                <select name="almacen_origen_id" id="trasladoOrigenId" data-campo-stock-almacen required>
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
                <div class="stock-actual-hint" data-stock-actual>Stock actual (origen): —</div>
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

@endsection

@push('scripts')
<script>
const PRODUCTOS_INV = @json($productosJs);
const URL_STOCK = '{{ url('admin/productos') }}';

// Muestra, encima del campo Cantidad, cuánto hay AHORA en el almacén
// elegido — para no tener que adivinar ni ir a mirar otra pantalla antes
// de escribir cuánto trasladar/remover/ajustar.
function actualizarStockHint(form) {
    const hint = form.querySelector('[data-stock-actual]');
    if (!hint) return;

    const input = form.querySelector('[data-buscar-producto]');
    const stocksRaw = input?.dataset.productoStocks;
    const almacenSelect = form.querySelector('[data-campo-stock-almacen]');

    if (!stocksRaw || !almacenSelect) {
        hint.textContent = hint.dataset.etiqueta + ': —';
        return;
    }

    const stocks = JSON.parse(stocksRaw);
    hint.textContent = hint.dataset.etiqueta + ': ' + (stocks[almacenSelect.value] ?? 0);
}

document.querySelectorAll('[data-stock-actual]').forEach((hint) => {
    hint.dataset.etiqueta = hint.textContent.replace(/:\s*—$/, '');
});

document.querySelectorAll('[data-campo-stock-almacen]').forEach((select) => {
    select.addEventListener('change', () => actualizarStockHint(select.closest('form')));
});

// Un solo buscador reutilizado por los 2 modales (Movimiento/Traslado).
document.querySelectorAll('[data-buscar-producto]').forEach((input) => {
    const contenedor = input.closest('.nv-buscador');
    const dropdown = contenedor.querySelector('[data-dropdown-producto]');
    const form = input.closest('form');

    function elegir(p) {
        input.value = p.nombre;
        input.dataset.productoStocks = JSON.stringify(p.stocks || {});
        dropdown.classList.remove('activo');

        const campoId = form.querySelector('[data-campo-producto-id]');
        if (campoId) {
            campoId.value = p.id;
        } else {
            // El modal de Movimiento reusa admin.productos.stock: la ruta
            // necesita el {producto} en la URL, no como campo del form.
            form.action = URL_STOCK + '/' + p.id + '/stock';
        }

        actualizarStockHint(form);
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

function abrirModal(id) {
    document.getElementById(id).classList.add('active');
    document.body.style.overflow = 'hidden';
}

// ── Botones de cabecera: + Ingreso / − Salida, sin producto preseleccionado ──
document.querySelectorAll('[data-abrir-movimiento]').forEach((boton) => {
    boton.addEventListener('click', () => {
        const input = document.getElementById('movBuscarInput');
        document.getElementById('formMovimiento').action = '';
        input.value = '';
        delete input.dataset.productoStocks;
        document.getElementById('movTipo').value = boton.dataset.abrirMovimiento;
        actualizarStockHint(document.getElementById('formMovimiento'));
        abrirModal('modalMovimiento');
    });
});

// ── Acciones por fila: Trasladar / Remover / Ajuste, ya con el producto y almacén de esa fila ──
document.querySelectorAll('[data-accion]').forEach((boton) => {
    boton.addEventListener('click', () => {
        const { accion, productoId, productoNombre, almacenId } = boton.dataset;
        const stocks = JSON.stringify(PRODUCTOS_INV.find((p) => String(p.id) === productoId)?.stocks || {});

        if (accion === 'trasladar') {
            const formTraslado = document.getElementById('trasladoBuscarInput').closest('form');
            document.getElementById('trasladoProductoId').value = productoId;
            document.getElementById('trasladoBuscarInput').value = productoNombre;
            document.getElementById('trasladoBuscarInput').dataset.productoStocks = stocks;
            document.getElementById('trasladoOrigenId').value = almacenId;
            actualizarStockHint(formTraslado);
            abrirModal('modalTraslado');
            return;
        }

        const formMovimiento = document.getElementById('formMovimiento');
        formMovimiento.action = URL_STOCK + '/' + productoId + '/stock';
        document.getElementById('movBuscarInput').value = productoNombre;
        document.getElementById('movBuscarInput').dataset.productoStocks = stocks;
        document.getElementById('movAlmacenId').value = almacenId;
        document.getElementById('movTipo').value = accion === 'remover' ? 'salida' : 'ajuste';
        actualizarStockHint(formMovimiento);
        abrirModal('modalMovimiento');
    });
});
</script>
@endpush
