@extends('layouts.admin')

@section('title', 'Nota de Crédito / Débito')
@section('crumb', 'Gestión comercial')

@php
    $productosJs = $productos->map(fn ($p) => [
        'nombre' => $p->nombre,
        'codigo' => $p->codigo,
        'precio' => (float) $p->precio_venta,
        'presentacion' => $p->presentacion,
    ])->values();

    $comprobantesJs = $comprobantes->map(fn ($v) => [
        'id' => $v->id,
        'numero' => "{$v->n_seri}-{$v->n_comp}",
        'serie' => $v->n_seri,
        'tipo' => $v->tipcomp,
        'cliente' => $v->razonsocial ?: $v->cliente_nombre,
    ])->values();

    // Se pasan como arreglo (no objeto): las claves "01".."13" son números para
    // JS, y un objeto reordena automáticamente las claves numéricas antes que
    // las demás — un arreglo conserva el orden real del catálogo SUNAT.
    $motivosCreditoJs = collect($motivosCredito)->map(fn ($nombre, $codigo) => ['codigo' => $codigo, 'nombre' => $nombre])->values();
    $motivosDebitoJs = collect($motivosDebito)->map(fn ($nombre, $codigo) => ['codigo' => $codigo, 'nombre' => $nombre])->values();
@endphp

@push('styles')
<style>
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
.nv-buscador { position: relative; }
.nv-buscador .nv-lupa { position: absolute; top: 50%; left: 12px; transform: translateY(-50%); font-size: 15px; pointer-events: none; }
.nv-buscar-input { width: 100%; padding: 10px 14px 10px 38px; border: 1.5px solid #e5e7eb; border-radius: 8px; background: #fafafa; color: #111827; font-family: inherit; font-size: 13.5px; box-sizing: border-box; transition: all .2s ease; }
.nv-buscar-input:focus { outline: none; border-color: #3d9b8c; background: #fff; box-shadow: 0 0 0 3px rgba(61,155,140,.08); }
.nv-dropdown { display: none; position: absolute; z-index: 999; top: calc(100% + 4px); left: 0; right: 0; max-height: 300px; border: 1.5px solid #e5e7eb; border-radius: 10px; background: #fff; box-shadow: 0 8px 30px rgba(0,0,0,.14); overflow-y: auto; }
.nv-dropdown.activo { display: block; }
.nv-item { padding: 10px 14px; border-bottom: 1px solid #f3f4f6; cursor: pointer; transition: background .15s; }
.nv-item:last-child { border-bottom: none; }
.nv-item:hover, .nv-item.activo { background: #f0faf8; }
.nv-item-top { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; margin-bottom: 4px; }
.nv-item-cod { padding: 2px 7px; border-radius: 4px; background: rgba(61,155,140,.08); color: #3d9b8c; font-family: 'Courier New', monospace; font-size: 11px; font-weight: 700; }
.nv-item-desc { font-size: 12px; font-weight: 500; color: #111827; }
.nv-sin-resultados { padding: 20px; text-align: center; color: #6b7280; font-size: 13px; }
.nv-linea-manual { margin-top: 10px; }
.nv-origen-panel { padding: 12px 14px; border-radius: 8px; background: #f7f7f5; border: 1px solid #eee; font-size: 13px; }
.nv-origen-panel .vacio { color: #9ca3af; }
.nv-origen-panel dl { display: grid; grid-template-columns: auto 1fr; gap: 4px 12px; margin: 0; }
.nv-origen-panel dt { font-weight: 700; color: #6b6f78; }
.nv-origen-panel dd { margin: 0; }
</style>
@endpush

@section('content')
<x-page-header titulo="Nota de Crédito / Débito" subtitulo="Corrige un comprobante ya aceptado por SUNAT">
    <x-slot:acciones>
        <a href="{{ route('admin.ventas.index') }}" class="btn btn-secondary btn-sm">
            <span class="btn-text">← Volver</span>
        </a>
    </x-slot:acciones>
</x-page-header>

<form method="POST" action="{{ route('admin.ventas.notas.store') }}" id="formNota" class="nv-form">
    @csrf

    <div class="content-card">
        <h3>Tipo y comprobante a corregir</h3>
        <div class="form-grid">
            <div class="form-group">
                <label for="n-tipo-nota">Tipo de Nota <span>*</span></label>
                <select id="n-tipo-nota" name="tipcomp" required>
                    <option value="07" @selected(old('tipcomp', '07') === '07')>07 — Nota de Crédito</option>
                    <option value="08" @selected(old('tipcomp') === '08')>08 — Nota de Débito</option>
                </select>
            </div>
            <div class="form-group" style="grid-column: span 2;">
                <label for="n-origen">Comprobante a corregir <span>*</span></label>
                <select id="n-origen" name="venta_origen_id" required>
                    <option value="">Selecciona una Boleta o Factura ya aceptada por SUNAT…</option>
                    @foreach ($comprobantes as $c)
                        <option value="{{ $c->id }}" @selected(old('venta_origen_id', $origen?->id) == $c->id)>
                            {{ $c->n_seri }}-{{ $c->n_comp }} — {{ $c->razonsocial ?: $c->cliente_nombre }} (S/ {{ number_format((float) $c->total, 2) }})
                        </option>
                    @endforeach
                </select>
                @if ($comprobantes->isEmpty())
                    <small style="display:block;margin-top:4px;color:#a12b2b;">
                        Todavía no hay ninguna Boleta o Factura aceptada por SUNAT para corregir.
                    </small>
                @endif
            </div>
            <div class="form-group">
                <label for="n-fecha">Fecha <span>*</span></label>
                <input type="date" id="n-fecha" name="fecha" required value="{{ old('fecha', now()->toDateString()) }}">
            </div>
            <div class="form-group">
                <label for="n-seri">N° Serie <span>*</span></label>
                <input type="text" id="n-seri" name="n_seri" required maxlength="4"
                       placeholder="FC01" value="{{ old('n_seri') }}">
            </div>
            <div class="form-group">
                <label for="n-comp">N° Comprobante <span>*</span></label>
                <input type="text" id="n-comp" name="n_comp" required maxlength="20"
                       placeholder="00000001" value="{{ old('n_comp') }}">
            </div>
            <div class="form-group" style="grid-column: span 2;">
                <label for="n-motivo">Motivo <span>*</span></label>
                <select id="n-motivo" name="cod_motivo" required>
                    <option value="">Selecciona un motivo…</option>
                </select>
            </div>
        </div>
    </div>

    <div class="content-card">
        <h3>Cliente</h3>
        <p class="nv-hint">Se toma directamente del comprobante seleccionado — es el dato que ya validó SUNAT.</p>
        <div class="nv-origen-panel" id="n-cliente-panel">
            <span class="vacio">Selecciona un comprobante arriba para ver los datos del cliente.</span>
        </div>
    </div>

    <div class="content-card">
        <h3>Monto único</h3>
        <p class="nv-hint">Úsalo si no vas a detallar productos.</p>
        <div class="form-grid">
            <div class="form-group">
                <label for="n-tipo-operacion">Tipo de operación</label>
                <select id="n-tipo-operacion" name="tipo_operacion">
                    <option value="gravada">⚡ Gravada (con IGV)</option>
                    <option value="exonerada">🟡 Exonerada</option>
                    <option value="inafecta">⚪ Inafecta</option>
                </select>
            </div>
            <div class="form-group">
                <label for="n-monto">Monto (S/)</label>
                <input type="number" id="n-monto" name="monto" step="0.01" min="0" placeholder="0.00" value="{{ old('monto') }}">
            </div>
        </div>
    </div>

    <div class="content-card">
        <div class="nv-productos-head">
            <h3 style="margin:0;">Productos</h3>
        </div>
        <p class="nv-hint">Opcional — si agregas productos, se usan en vez del monto único.</p>

        <label class="nv-igv-toggle">
            <input type="checkbox" id="n-incluye-igv" name="precios_incluyen_igv" value="1"
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
            <button type="button" class="btn btn-dark btn-sm" id="btnAgregarItemNota">
                <span class="btn-text">＋ Línea manual</span>
            </button>
        </div>

        <div class="table-container" style="margin-top:14px;">
            <table class="table" id="tablaItemsNota">
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
                <tbody id="notaItemsBody"></tbody>
            </table>
        </div>

        <div class="nv-totales">
            <div class="nv-totales-caja">
                <div><span>Subtotal</span><strong id="nTotSubtotal">S/ 0.00</strong></div>
                <div><span>IGV ({{ (int) (config('rentaltech.igv') * 100) }}%)</span><strong id="nTotIgv">S/ 0.00</strong></div>
                <div class="nv-total-final"><span>Total</span><strong id="nTotTotal">S/ 0.00</strong></div>
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
const COMPROBANTES = @json($comprobantesJs);
const MOTIVOS_CREDITO = @json($motivosCreditoJs);
const MOTIVOS_DEBITO = @json($motivosDebitoJs);

const nTipoNota = document.getElementById('n-tipo-nota');
const nOrigen = document.getElementById('n-origen');
const nSeri = document.getElementById('n-seri');
const nMotivo = document.getElementById('n-motivo');
const nClientePanel = document.getElementById('n-cliente-panel');

function poblarMotivos() {
    const motivos = nTipoNota.value === '08' ? MOTIVOS_DEBITO : MOTIVOS_CREDITO;
    const actual = nMotivo.value;

    nMotivo.innerHTML = '<option value="">Selecciona un motivo…</option>' + motivos
        .map((m) => `<option value="${m.codigo}">${m.codigo} — ${m.nombre}</option>`).join('');

    if (motivos.some((m) => m.codigo === actual)) nMotivo.value = actual;
}

function actualizarSegunOrigen() {
    const comp = COMPROBANTES.find((c) => String(c.id) === nOrigen.value);

    if (!comp) {
        nClientePanel.innerHTML = '<span class="vacio">Selecciona un comprobante arriba para ver los datos del cliente.</span>';
        return;
    }

    nClientePanel.innerHTML = `<dl>
        <dt>Cliente:</dt><dd>${comp.cliente}</dd>
        <dt>Comprobante:</dt><dd>${comp.numero}</dd>
    </dl>`;

    // Serie sugerida: primera letra del comprobante origen (F/B) + C01/D01 según el tipo de nota.
    if (!nSeri.value) {
        const sufijo = nTipoNota.value === '08' ? 'D01' : 'C01';
        nSeri.value = comp.serie.charAt(0) + sufijo;
    }
}

nTipoNota.addEventListener('change', () => {
    poblarMotivos();
    nSeri.value = '';
    actualizarSegunOrigen();
});

nOrigen.addEventListener('change', actualizarSegunOrigen);

poblarMotivos();
actualizarSegunOrigen();

// ── Tabla de productos (mismo patrón que el formulario de Factura) ───────
const notaBody = document.getElementById('notaItemsBody');
const PRODUCTOS = @json($productosJs);
let indiceNota = 0;

function filaNotaHtml(i, producto) {
    const nombre = producto?.nombre ?? '';
    const codigo = producto?.codigo ?? '';
    const precio = producto?.precio ?? 0;

    return `<tr class="fila-item">
        <td><input type="text" name="items[${i}][producto_nombre]" class="item-nombre-n"
                   value="${nombre.replace(/"/g, '&quot;')}" placeholder="Nombre del producto o servicio…"></td>
        <td><input type="text" name="items[${i}][producto_codigo]" class="item-codigo-n" value="${codigo}"></td>
        <td><input type="number" name="items[${i}][cantidad]" value="1" min="1" step="1" class="item-cantidad-n"></td>
        <td><input type="number" name="items[${i}][precio_unitario]" value="${precio}" step="0.01" min="0" class="item-precio-n"></td>
        <td class="item-subtotal-n">S/ 0.00</td>
        <td><button type="button" class="btn btn-danger btn-sm btn-quitar-n">✕</button></td>
    </tr>`;
}

function agregarFilaNota(producto = null) {
    notaBody.insertAdjacentHTML('beforeend', filaNotaHtml(indiceNota++, producto));
    recalcularNota();
}

document.getElementById('btnAgregarItemNota').addEventListener('click', () => agregarFilaNota());

notaBody.addEventListener('click', (e) => {
    if (e.target.classList.contains('btn-quitar-n')) {
        e.target.closest('tr').remove();
        recalcularNota();
    }
});

notaBody.addEventListener('input', recalcularNota);

const nMonto = document.getElementById('n-monto');
const nTipoOperacion = document.getElementById('n-tipo-operacion');
const nIncluyeIgv = document.getElementById('n-incluye-igv');

function itemsValidosNota() {
    return [...notaBody.querySelectorAll('.fila-item')].filter((fila) => {
        const nombre   = fila.querySelector('[name$="[producto_nombre]"]')?.value.trim();
        const cantidad = parseFloat(fila.querySelector('.item-cantidad-n')?.value || 0);

        return nombre && cantidad > 0;
    });
}

/** Si el monto ya trae IGV, se extrae en vez de sumarse encima. */
function desglosarIgv(monto, incluyeIgv) {
    if (incluyeIgv) {
        const base = monto / (1 + IGV_VENTAS);
        return { base, igv: monto - base };
    }

    return { base: monto, igv: monto * IGV_VENTAS };
}

function recalcularNota() {
    let subtotalItems = 0;

    notaBody.querySelectorAll('.fila-item').forEach((fila) => {
        const cantidad = parseFloat(fila.querySelector('.item-cantidad-n')?.value || 0);
        const precio   = parseFloat(fila.querySelector('.item-precio-n')?.value || 0);
        const linea    = cantidad * precio;
        subtotalItems += linea;
        fila.querySelector('.item-subtotal-n').textContent = 'S/ ' + linea.toFixed(2);
    });

    let subtotal = 0;
    let igv = 0;
    const incluyeIgv = !!nIncluyeIgv?.checked;

    if (itemsValidosNota().length > 0) {
        ({ base: subtotal, igv } = desglosarIgv(subtotalItems, incluyeIgv));
    } else {
        const monto = parseFloat(nMonto?.value || 0);

        if (nTipoOperacion?.value === 'gravada') {
            ({ base: subtotal, igv } = desglosarIgv(monto, incluyeIgv));
        } else {
            subtotal = monto;
            igv = 0;
        }
    }

    document.getElementById('nTotSubtotal').textContent = 'S/ ' + subtotal.toFixed(2);
    document.getElementById('nTotIgv').textContent      = 'S/ ' + igv.toFixed(2);
    document.getElementById('nTotTotal').textContent    = 'S/ ' + (subtotal + igv).toFixed(2);
}

nMonto?.addEventListener('input', recalcularNota);
nTipoOperacion?.addEventListener('change', recalcularNota);
// Al desmarcar, se advierte: de ahí en más el sistema SUMA el IGV encima del
// precio en vez de asumir que ya lo trae incluido.
nIncluyeIgv?.addEventListener('change', async () => {
    if (!nIncluyeIgv.checked) {
        const confirmado = await confirmar(
            'Vas a desmarcar "Los precios ya incluyen IGV".\n\n' +
            'Desde ahora el sistema SUMARÁ el IGV (18%) encima de los precios ingresados, ' +
            'en vez de asumir que ya lo traen incluido.\n\n' +
            '¿Confirmas que estos precios NO incluyen IGV?'
        );

        if (!confirmado) {
            nIncluyeIgv.checked = true;
            return;
        }
    }

    recalcularNota();
});

// ── Buscador de productos ─────────────────────────────────────────────────
const nvBuscar = document.getElementById('nv-buscar');
const nvDropdown = document.getElementById('nv-prod-dd');
let nvResultados = [];

function nvResaltar(texto, termino) {
    const i = texto.toUpperCase().indexOf(termino.toUpperCase());
    if (i === -1) return texto;
    return texto.slice(0, i) + '<strong style="color:#3d9b8c">' + texto.slice(i, i + termino.length) + '</strong>' + texto.slice(i + termino.length);
}

function nvCerrarBuscador() {
    nvDropdown.classList.remove('activo');
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

    nvDropdown.innerHTML = nvResultados.map((p, i) =>
        '<div class="nv-item" data-idx="' + i + '">'
        + '<div class="nv-item-top"><span class="nv-item-cod">' + (p.codigo || '—') + '</span>'
        + '<span class="nv-item-desc">' + nvResaltar(p.nombre, termino) + '</span></div></div>'
    ).join('');

    nvDropdown.querySelectorAll('.nv-item').forEach((item) => {
        item.addEventListener('click', () => {
            const p = nvResultados[Number(item.dataset.idx)];
            if (!p) return;
            agregarFilaNota(p);
            nvBuscar.value = '';
            nvCerrarBuscador();
            nvBuscar.focus();
        });
    });

    nvDropdown.classList.add('activo');
}

nvBuscar.addEventListener('input', (e) => nvBuscarProducto(e.target.value));

document.addEventListener('click', (e) => {
    if (!document.getElementById('nv-buscador-producto').contains(e.target)) nvCerrarBuscador();
});

document.getElementById('formNota').addEventListener('submit', (e) => {
    const monto = parseFloat(nMonto?.value || 0);

    if (itemsValidosNota().length === 0 && monto <= 0) {
        e.preventDefault();
        alert('Ingresa un monto o agrega al menos un producto.');
    }
});

recalcularNota();
</script>
@endpush
