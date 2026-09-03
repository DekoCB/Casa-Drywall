@extends('layouts.admin')

@section('title', 'Nueva Venta')
@section('crumb', 'Gestión comercial')

@php
    // Catálogo para el buscador de productos, en el formato que espera el JS.
    $productosJs = $productos->map(fn ($p) => [
        'nombre' => $p->nombre,
        'codigo' => $p->codigo,
        'precio' => (float) $p->precio_venta,
        'categoria' => $p->categoria?->nombre,
        'marca' => $p->marca?->nombre,
        'presentacion' => $p->presentacion,
    ])->values();
@endphp

@push('styles')
<style>
/* Versión compacta de .content-card/.form-grid solo para esta página. */
.nv-form .content-card { padding: 16px 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,.06); }
.nv-form .content-card + .content-card { margin-top: 14px; }
.nv-form h3 { margin: 0 0 12px; font-size: 14px; font-weight: 700; letter-spacing: .03em; color: #1a1714; }
.nv-form .nv-hint { margin: -8px 0 12px; font-size: 12px; color: #6b6f78; }
.nv-form .form-grid { grid-template-columns: repeat(auto-fill, minmax(180px, 240px)); justify-content: start; gap: 12px; margin-bottom: 0; }
.nv-form .form-group label { margin-bottom: 5px; font-size: 10.5px; letter-spacing: .04em; }
.nv-form .form-group input,
.nv-form .form-group select,
.nv-form .form-group textarea { padding: 8px 11px; font-size: 13px; border-radius: 8px; border-width: 1px; }
.nv-form .table th { padding: 8px 10px; font-size: 10px; }
.nv-form .table td { padding: 6px 10px; }
.nv-form .table td input { padding: 6px 8px; font-size: 13px; border-radius: 6px; border: 1px solid #e8e8e8; width: 100%; }
.nv-form .nv-totales { margin-top: 14px; padding-top: 12px; border-top: 1px solid #eee; display: flex; justify-content: flex-end; }
.nv-form .nv-totales-caja { min-width: 220px; font-size: 13px; }
.nv-form .nv-totales-caja > div { display: flex; justify-content: space-between; padding: 4px 0; }
.nv-form .nv-totales-caja .nv-total-final { padding-top: 8px; margin-top: 4px; border-top: 1px solid #eee; font-size: 16px; font-weight: 700; }
.nv-form .nv-productos-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.nv-igv-toggle { display: flex; align-items: center; gap: 8px; margin-bottom: 14px; font-size: 13px; color: #3a362f; cursor: pointer; }
.nv-igv-toggle input { width: 16px; height: 16px; cursor: pointer; }

/* Buscador de productos, al estilo del de Órdenes de Compra. */
.nv-buscador { position: relative; }
.nv-buscador .nv-lupa { position: absolute; top: 50%; left: 12px; transform: translateY(-50%); font-size: 15px; pointer-events: none; }
.nv-buscar-input {
    width: 100%;
    padding: 10px 14px 10px 38px;
    border: 1.5px solid #e5e7eb;
    border-radius: 8px;
    background: #fafafa;
    color: #111827;
    font-family: inherit;
    font-size: 13.5px;
    box-sizing: border-box;
    transition: all .2s ease;
}
.nv-buscar-input:focus { outline: none; border-color: #3d9b8c; background: #fff; box-shadow: 0 0 0 3px rgba(61,155,140,.08); }
.nv-dropdown {
    display: none;
    position: absolute;
    z-index: 999;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    max-height: 300px;
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    background: #fff;
    box-shadow: 0 8px 30px rgba(0,0,0,.14);
    overflow-y: auto;
}
.nv-dropdown.activo { display: block; }
.nv-item { padding: 10px 14px; border-bottom: 1px solid #f3f4f6; cursor: pointer; transition: background .15s; }
.nv-item:last-child { border-bottom: none; }
.nv-item:hover, .nv-item.activo { background: #f0faf8; }
.nv-item-top { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; margin-bottom: 4px; }
.nv-item-cod { padding: 2px 7px; border-radius: 4px; background: rgba(61,155,140,.08); color: #3d9b8c; font-family: 'Courier New', monospace; font-size: 11px; font-weight: 700; }
.nv-item-desc { font-size: 12px; font-weight: 500; color: #111827; }
.nv-item-meta { display: flex; gap: 6px; flex-wrap: wrap; }
.nv-chip { padding: 1px 7px; border-radius: 20px; background: #e8f5f3; color: #2e7a6e; font-size: 10px; font-weight: 700; }
.nv-sin-resultados { padding: 20px; text-align: center; color: #6b7280; font-size: 13px; }
.nv-linea-manual { margin-top: 10px; }
</style>
@endpush

@section('content')
<x-page-header titulo="Nueva Venta" subtitulo="Ingresa un monto único o detalla los productos a facturar">
    <x-slot:acciones>
        <a href="{{ route('admin.ventas.index') }}" class="btn btn-secondary btn-sm">
            <span class="btn-text">← Volver</span>
        </a>
    </x-slot:acciones>
</x-page-header>

<form method="POST" action="{{ route('admin.ventas.factura.store') }}" id="formFactura" class="nv-form">
    @csrf

    <div class="content-card">
        <h3>Comprobante</h3>
        <div class="form-grid">
            <div class="form-group">
                <label for="f-tipcomp">Tipo Comprobante <span>*</span></label>
                <select id="f-tipcomp" name="tipcomp" required>
                    @foreach (array_intersect_key($tipos, array_flip(['COT', 'NV', '01', '03'])) as $codigo => $tipo)
                        <option value="{{ $codigo }}" data-serie="{{ $tipo['serie'] }}" @selected(old('tipcomp', 'NV') === $codigo)>{{ $tipo['nombre'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="f-fecha">Fecha <span>*</span></label>
                <input type="date" id="f-fecha" name="fecha" required value="{{ old('fecha', now()->toDateString()) }}">
            </div>
            <div class="form-group">
                <label for="f-n-seri">N° Serie <span>*</span></label>
                <input type="text" id="f-n-seri" name="n_seri" required maxlength="4"
                       placeholder="F001" value="{{ old('n_seri') }}">
            </div>
            <div class="form-group">
                <label for="f-n-comp">N° Comprobante <span>*</span></label>
                <input type="text" id="f-n-comp" name="n_comp" required maxlength="20"
                       placeholder="0000000001" value="{{ old('n_comp') }}">
            </div>
            <div class="form-group">
                <label for="f-vencimiento">Fecha de vencimiento <span>*</span></label>
                <input type="date" id="f-vencimiento" name="fecha_vencimiento" required
                       value="{{ old('fecha_vencimiento', now()->toDateString()) }}">
            </div>
            <div class="form-group">
                <label for="f-condicion">Condición de pago</label>
                <input type="text" id="f-condicion" name="condicion_pago" maxlength="100"
                       placeholder="Contado, crédito 30 días…" value="{{ old('condicion_pago', 'Contado') }}">
            </div>
        </div>
    </div>

    <div class="content-card">
        <h3>Cliente</h3>
        <div class="form-group" style="margin-bottom:12px;">
            <label for="cliente-buscar">Buscar cliente</label>
            <div class="nv-buscador" id="nv-buscador-cliente">
                <span class="nv-lupa">🔍</span>
                <input type="text" class="nv-buscar-input" id="cliente-buscar" autocomplete="off"
                       placeholder="Escribe el nombre o documento del cliente…">
                <div class="nv-dropdown" id="cliente-dd"></div>
            </div>
            <button type="button" class="btn btn-secondary btn-sm" id="btnClienteVarios" style="margin-top:8px;">
                Usar "Cliente Varios" (sin documento)
            </button>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label for="f-razonsocial">Cliente <span>*</span></label>
                <input type="text" id="f-razonsocial" name="razonsocial" required maxlength="300"
                       placeholder="Nombre o empresa..." value="{{ old('razonsocial') }}">
            </div>
            <div class="form-group">
                <label for="f-n-ruc">N° RUC / DNI</label>
                <div style="display:flex;gap:8px;">
                    <input type="text" id="f-n-ruc" name="n_ruc" maxlength="20" value="{{ old('n_ruc') }}" style="flex:1;">
                    <button type="button" class="btn btn-secondary" id="btnBuscarDocFactura" title="Buscar en SUNAT/RENIEC">Buscar</button>
                </div>
                <small id="docFacturaEstado" style="display:block;margin-top:4px;color:#666;"></small>
            </div>
            <input type="hidden" id="f-cliente-id" name="cliente_id" value="{{ old('cliente_id') }}">
        </div>
    </div>

    <div class="content-card">
        <h3>Monto único</h3>
        <p class="nv-hint">Úsalo si no vas a detallar productos.</p>
        <div class="form-grid">
            <div class="form-group">
                <label for="f-tipo-operacion">Tipo de operación</label>
                <select id="f-tipo-operacion" name="tipo_operacion">
                    <option value="gravada">⚡ Gravada (con IGV)</option>
                    <option value="exonerada">🟡 Exonerada</option>
                    <option value="inafecta">⚪ Inafecta</option>
                </select>
            </div>
            <div class="form-group">
                <label for="f-monto">Monto (S/)</label>
                <input type="number" id="f-monto" name="monto" step="0.01" min="0" placeholder="0.00" value="{{ old('monto') }}">
            </div>
        </div>
    </div>

    <div class="content-card">
        <div class="nv-productos-head">
            <h3 style="margin:0;">Productos</h3>
        </div>
        <p class="nv-hint">Opcional — si agregas productos, se usan en vez del monto único.</p>

        <label class="nv-igv-toggle">
            <input type="checkbox" id="f-incluye-igv" name="precios_incluyen_igv" value="1"
                   @checked(old('precios_incluyen_igv', true))>
            Los precios ya incluyen IGV (no volver a sumarlo)
        </label>

        <div class="nv-buscador" id="nv-buscador-producto">
            <span class="nv-lupa">🔍</span>
            <input type="text" class="nv-buscar-input" id="nv-buscar" autocomplete="off"
                   placeholder="Buscar producto por nombre o código…">
            <div class="nv-dropdown" id="nv-prod-dd"></div>
        </div>

        <div class="nv-linea-manual">
            <button type="button" class="btn btn-dark btn-sm" id="btnAgregarItemFactura">
                <span class="btn-text">＋ Línea manual</span>
            </button>
            <span class="nv-hint" style="display:inline;margin-left:6px;">para un producto o servicio que no esté en el catálogo</span>
        </div>

        <div class="table-container" style="margin-top:14px;">
            <table class="table" id="tablaItemsFactura">
                <thead>
                    <tr>
                        <th style="width:34%;">Producto</th>
                        <th style="width:14%;">Código</th>
                        <th style="width:12%;">Cantidad</th>
                        <th style="width:16%;">P. unitario</th>
                        <th style="width:16%;">Subtotal</th>
                        <th style="width:8%;"></th>
                    </tr>
                </thead>
                <tbody id="facturaItemsBody"></tbody>
            </table>
        </div>

        <div class="nv-totales">
            <div class="nv-totales-caja">
                <div><span>Subtotal</span><strong id="fTotSubtotal">S/ 0.00</strong></div>
                <div><span>IGV ({{ (int) (config('rentaltech.igv') * 100) }}%)</span><strong id="fTotIgv">S/ 0.00</strong></div>
                <div class="nv-total-final"><span>Total</span><strong id="fTotTotal">S/ 0.00</strong></div>
            </div>
        </div>
    </div>

    <div class="header-btns" style="justify-content:flex-end;margin-top:14px;">
        <a href="{{ route('admin.ventas.index') }}" class="btn btn-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">Guardar</button>
    </div>
</form>
@endsection

@push('scripts')
<script>
const IGV_VENTAS = {{ config('rentaltech.igv') }};

// Serie sugerida según el tipo de comprobante.
const fTipcomp = document.getElementById('f-tipcomp');
const fNSeri   = document.getElementById('f-n-seri');

// Se sugiere de nuevo cada vez que cambia el tipo, salvo que el usuario ya
// haya escrito su propia serie a mano (si solo se chequeara "está vacío",
// dejaría de re-sugerir después del primer autocompletado).
let fSerieEditada = false;
fNSeri.addEventListener('input', () => { fSerieEditada = true; });

function sugerirSerieFactura() {
    if (!fSerieEditada) {
        fNSeri.value = fTipcomp.selectedOptions[0]?.dataset.serie || '';
    }
}

fTipcomp.addEventListener('change', sugerirSerieFactura);
sugerirSerieFactura(); // "Nota de Venta" viene preseleccionada: sin esto, nunca se dispara el "change".

// ── Buscador de cliente (mismo estilo que el de productos) ───────────────
const CLIENTES = @json($clientes->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->nombres, 'doc' => $c->numero_documento])->values());

const clienteBuscar = document.getElementById('cliente-buscar');
const clienteDropdown = document.getElementById('cliente-dd');
let clienteResultados = [];
let clienteIndice = -1;

function clienteCerrarBuscador() {
    clienteDropdown.classList.remove('activo');
    clienteIndice = -1;
}

function clienteBuscarFn(termino) {
    termino = termino.trim();

    if (termino === '') { clienteCerrarBuscador(); return; }

    const busqueda = termino.toUpperCase();
    clienteResultados = CLIENTES.filter((c) =>
        c.nombre.toUpperCase().includes(busqueda) || (c.doc || '').toUpperCase().includes(busqueda)
    ).slice(0, 15);

    if (!clienteResultados.length) {
        clienteDropdown.innerHTML = '<div class="nv-sin-resultados">🔍 Sin resultados para "' + termino + '"</div>';
        clienteDropdown.classList.add('activo');
        return;
    }

    clienteDropdown.innerHTML = clienteResultados.map((c, i) =>
        '<div class="nv-item" data-idx="' + i + '">'
        + '<div class="nv-item-top"><span class="nv-item-cod">' + (c.doc || '—') + '</span>'
        + '<span class="nv-item-desc">' + nvResaltar(c.nombre, termino) + '</span></div></div>'
    ).join('');

    clienteDropdown.querySelectorAll('.nv-item').forEach((item) => {
        item.addEventListener('click', () => clienteElegir(Number(item.dataset.idx)));
    });

    clienteIndice = -1;
    clienteDropdown.classList.add('activo');
}

function clienteElegir(i) {
    const c = clienteResultados[i];
    if (!c) return;

    document.getElementById('f-razonsocial').value = c.nombre;
    document.getElementById('f-n-ruc').value        = c.doc || '';
    document.getElementById('f-cliente-id').value   = c.id;
    clienteBuscar.value = c.nombre;
    clienteCerrarBuscador();
}

clienteBuscar.addEventListener('input', (e) => clienteBuscarFn(e.target.value));

clienteBuscar.addEventListener('keydown', (e) => {
    const items = clienteDropdown.querySelectorAll('.nv-item');
    if (!items.length) return;

    if (e.key === 'ArrowDown') { e.preventDefault(); clienteIndice = Math.min(clienteIndice + 1, items.length - 1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); clienteIndice = Math.max(clienteIndice - 1, 0); }
    else if (e.key === 'Enter' && clienteIndice >= 0) { e.preventDefault(); clienteElegir(clienteIndice); return; }
    else if (e.key === 'Escape') { clienteCerrarBuscador(); return; }
    else { return; }

    items.forEach((el, idx) => el.classList.toggle('activo', idx === clienteIndice));
    items[clienteIndice]?.scrollIntoView({ block: 'nearest' });
});

// Si el usuario edita el nombre a mano, se suelta la ficha del cliente elegido.
document.getElementById('f-razonsocial').addEventListener('input', () => {
    document.getElementById('f-cliente-id').value = '';
});

// "Cliente Varios": para ventas a alguien que no dio su DNI o no está
// registrado en el sistema — no queda enlazado a ninguna ficha de Cliente.
document.getElementById('btnClienteVarios').addEventListener('click', () => {
    document.getElementById('f-razonsocial').value = 'Cliente Varios';
    document.getElementById('f-n-ruc').value = '';
    document.getElementById('f-cliente-id').value = '';
    clienteBuscar.value = '';
    clienteCerrarBuscador();
});

// Tabla de productos: filas dinámicas con recálculo en vivo del total.
const facturaBody = document.getElementById('facturaItemsBody');
const PRODUCTOS = @json($productosJs);
let indiceFactura = 0;

function filaFacturaHtml(i, producto) {
    const nombre = producto?.nombre ?? '';
    const codigo = producto?.codigo ?? '';
    const precio = producto?.precio ?? 0;

    return `<tr class="fila-item">
        <td><input type="text" name="items[${i}][producto_nombre]" class="item-nombre-f"
                   value="${nombre.replace(/"/g, '&quot;')}" placeholder="Nombre del producto o servicio…"></td>
        <td><input type="text" name="items[${i}][producto_codigo]" class="item-codigo-f" value="${codigo}"></td>
        <td><input type="number" name="items[${i}][cantidad]" value="1" min="1" step="1" class="item-cantidad-f"></td>
        <td><input type="number" name="items[${i}][precio_unitario]" value="${precio}" step="0.01" min="0" class="item-precio-f"></td>
        <td class="item-subtotal-f">S/ 0.00</td>
        <td><button type="button" class="btn btn-danger btn-sm btn-quitar-f">✕</button></td>
    </tr>`;
}

function agregarFilaFactura(producto = null) {
    facturaBody.insertAdjacentHTML('beforeend', filaFacturaHtml(indiceFactura++, producto));
    recalcularFactura();
}

document.getElementById('btnAgregarItemFactura').addEventListener('click', () => agregarFilaFactura());

facturaBody.addEventListener('click', (e) => {
    if (e.target.classList.contains('btn-quitar-f')) {
        e.target.closest('tr').remove();
        recalcularFactura();
    }
});

// ── Buscador de productos (mismo estilo que Órdenes de Compra) ───────────
const nvBuscar = document.getElementById('nv-buscar');
const nvDropdown = document.getElementById('nv-prod-dd');
let nvResultados = [];
let nvIndice = -1;

function nvResaltar(texto, termino) {
    const i = texto.toUpperCase().indexOf(termino.toUpperCase());
    if (i === -1) return texto;

    return texto.slice(0, i) + '<strong style="color:#3d9b8c">' + texto.slice(i, i + termino.length) + '</strong>' + texto.slice(i + termino.length);
}

function nvCerrarBuscador() {
    nvDropdown.classList.remove('activo');
    nvIndice = -1;
}

function nvBuscarProducto(termino) {
    termino = termino.trim();

    if (termino === '') { nvCerrarBuscador(); return; }

    const busqueda = termino.toUpperCase();
    nvResultados = PRODUCTOS.filter((p) =>
        p.nombre.toUpperCase().includes(busqueda) || (p.codigo || '').toUpperCase().includes(busqueda)
    ).slice(0, 15);

    if (!nvResultados.length) {
        nvDropdown.innerHTML = '<div class="nv-sin-resultados">🔍 Sin resultados para "' + termino + '"</div>';
        nvDropdown.classList.add('activo');
        return;
    }

    nvDropdown.innerHTML = nvResultados.map((p, i) => {
        const chips = [p.categoria, p.marca, p.presentacion].filter(Boolean)
            .map((c) => '<span class="nv-chip">' + c + '</span>').join('');
        const precio = '<span class="nv-chip">S/ ' + p.precio.toFixed(2) + '</span>';

        return '<div class="nv-item" data-idx="' + i + '">'
            + '<div class="nv-item-top"><span class="nv-item-cod">' + (p.codigo || '—') + '</span>'
            + '<span class="nv-item-desc">' + nvResaltar(p.nombre, termino) + '</span></div>'
            + '<div class="nv-item-meta">' + chips + precio + '</div></div>';
    }).join('');

    nvDropdown.querySelectorAll('.nv-item').forEach((item) => {
        item.addEventListener('click', () => nvElegirProducto(Number(item.dataset.idx)));
    });

    nvIndice = -1;
    nvDropdown.classList.add('activo');
}

function nvElegirProducto(i) {
    const p = nvResultados[i];
    if (!p) return;

    agregarFilaFactura(p);
    nvBuscar.value = '';
    nvCerrarBuscador();
    nvBuscar.focus();
}

nvBuscar.addEventListener('input', (e) => nvBuscarProducto(e.target.value));

nvBuscar.addEventListener('keydown', (e) => {
    const items = nvDropdown.querySelectorAll('.nv-item');
    if (!items.length) return;

    if (e.key === 'ArrowDown') { e.preventDefault(); nvIndice = Math.min(nvIndice + 1, items.length - 1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); nvIndice = Math.max(nvIndice - 1, 0); }
    else if (e.key === 'Enter' && nvIndice >= 0) { e.preventDefault(); nvElegirProducto(nvIndice); return; }
    else if (e.key === 'Escape') { nvCerrarBuscador(); return; }
    else { return; }

    items.forEach((el, idx) => el.classList.toggle('activo', idx === nvIndice));
    items[nvIndice]?.scrollIntoView({ block: 'nearest' });
});

document.addEventListener('click', (e) => {
    if (!document.getElementById('nv-buscador-producto').contains(e.target)) nvCerrarBuscador();
    if (!document.getElementById('nv-buscador-cliente').contains(e.target)) clienteCerrarBuscador();
});

facturaBody.addEventListener('input', recalcularFactura);

function itemsValidosFactura() {
    return [...facturaBody.querySelectorAll('.fila-item')].filter((fila) => {
        const nombre   = fila.querySelector('[name$="[producto_nombre]"]')?.value.trim();
        const cantidad = parseFloat(fila.querySelector('.item-cantidad-f')?.value || 0);

        return nombre && cantidad > 0;
    });
}

const fMonto = document.getElementById('f-monto');
const fTipoOperacion = document.getElementById('f-tipo-operacion');
const fIncluyeIgv = document.getElementById('f-incluye-igv');

/** Si el monto ya trae IGV, se extrae en vez de sumarse encima. */
function desglosarIgv(monto, incluyeIgv) {
    if (incluyeIgv) {
        const base = monto / (1 + IGV_VENTAS);
        return { base, igv: monto - base };
    }

    return { base: monto, igv: monto * IGV_VENTAS };
}

function recalcularFactura() {
    let subtotalItems = 0;

    facturaBody.querySelectorAll('.fila-item').forEach((fila) => {
        const cantidad = parseFloat(fila.querySelector('.item-cantidad-f')?.value || 0);
        const precio   = parseFloat(fila.querySelector('.item-precio-f')?.value || 0);
        const linea    = cantidad * precio;
        subtotalItems += linea;
        fila.querySelector('.item-subtotal-f').textContent = 'S/ ' + linea.toFixed(2);
    });

    let subtotal = 0;
    let igv = 0;
    const incluyeIgv = !!fIncluyeIgv?.checked;

    if (itemsValidosFactura().length > 0) {
        ({ base: subtotal, igv } = desglosarIgv(subtotalItems, incluyeIgv));
    } else {
        const monto = parseFloat(fMonto?.value || 0);

        if (fTipoOperacion?.value === 'gravada') {
            ({ base: subtotal, igv } = desglosarIgv(monto, incluyeIgv));
        } else {
            subtotal = monto;
            igv = 0;
        }
    }

    document.getElementById('fTotSubtotal').textContent = 'S/ ' + subtotal.toFixed(2);
    document.getElementById('fTotIgv').textContent      = 'S/ ' + igv.toFixed(2);
    document.getElementById('fTotTotal').textContent    = 'S/ ' + (subtotal + igv).toFixed(2);
}

fMonto?.addEventListener('input', recalcularFactura);
fTipoOperacion?.addEventListener('change', recalcularFactura);
fIncluyeIgv?.addEventListener('change', recalcularFactura);

document.getElementById('formFactura').addEventListener('submit', (e) => {
    const monto = parseFloat(fMonto?.value || 0);

    if (itemsValidosFactura().length === 0 && monto <= 0) {
        e.preventDefault();
        alert('Ingresa un monto o agrega al menos un producto.');
    }
});

recalcularFactura();

// ── Consulta de RUC/DNI (SUNAT/RENIEC) ──────────────────────────────────
const docFacturaEstado = document.getElementById('docFacturaEstado');

document.getElementById('btnBuscarDocFactura').addEventListener('click', async () => {
    const numero = document.getElementById('f-n-ruc').value.trim();
    const tipo   = numero.length === 8 ? 'dni' : numero.length === 11 ? 'ruc' : null;

    if (!tipo) {
        docFacturaEstado.textContent = 'Ingresa un RUC (11 dígitos) o DNI (8 dígitos) válido';
        return;
    }

    docFacturaEstado.textContent = 'Buscando...';

    try {
        const r = await fetch(`{{ url('admin/documentos/buscar') }}/${tipo}/${numero}`, { headers: { Accept: 'application/json' } });
        const j = await r.json();

        if (!j.ok) {
            docFacturaEstado.textContent = j.error || 'No se encontró el documento';
            return;
        }

        document.getElementById('f-razonsocial').value = (tipo === 'dni' ? j.datos.nombre_completo : j.datos.razon_social) || '';
        docFacturaEstado.textContent = j.origen === 'local' ? 'Datos de un registro existente' : 'Datos obtenidos de ' + (tipo === 'dni' ? 'RENIEC' : 'SUNAT');
    } catch (e) {
        docFacturaEstado.textContent = 'Servicio de consulta no disponible';
    }
});
</script>
@endpush
