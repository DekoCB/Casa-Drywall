@extends('layouts.admin')

@section('title', 'Órdenes de Compra')
@section('crumb', 'Vista general')

@push('styles')
    @vite(['resources/css/modules/ordenes-compra.css'])
@endpush

@section('content')

<div class="oc-wrapper">
<div class="oc-hoja-wrap">

    <div class="oc-nueva-head">
        <a href="{{ route('admin.ordenes-compra.index') }}" class="btn-volver-oc">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
            Volver
        </a>
        <div>
            <h2>🛒 Nueva Orden de Compra</h2>
            <p>Completa los datos, agrega productos o merch y confirma en el resumen de la derecha</p>
        </div>
    </div>

    {{-- Órdenes ya guardadas en esta tanda: cada una es una hoja del Excel. --}}
    @if ($lote->isNotEmpty())
        <div class="oc-lote">
            <div class="oc-lote-cab">
                <strong>{{ $lote->count() }} {{ $lote->count() === 1 ? 'orden guardada' : 'órdenes guardadas' }} en esta tanda</strong>
                <small>Al terminar se descargan juntas: una hoja de Excel por orden.</small>
            </div>

            <div class="oc-lote-chips">
                @foreach ($lote as $i => $pedido)
                    <span class="oc-lote-chip">
                        <b>Orden {{ $i + 1 }}</b>
                        {{ $pedido->numero_orden }}
                        <small>{{ $pedido->cliente_ref ?: 'Sin cliente' }} · S/ {{ number_format($pedido->total_soles, 2) }}</small>
                    </span>
                @endforeach
                <span class="oc-lote-chip pendiente"><b>Orden {{ $lote->count() + 1 }}</b> en curso</span>
            </div>

            <a href="{{ route('admin.ordenes-compra.index') }}" class="oc-lote-cerrar">
                Terminar y descargar el Excel ({{ $lote->count() }} hoja{{ $lote->count() === 1 ? '' : 's' }})
            </a>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.ordenes-compra.store') }}" id="formOrden" class="oc-hoja">
        @csrf

        <input type="hidden" name="estado" value="Pendiente">
        <input type="hidden" name="gasto_unit" value="0">
        <input type="hidden" name="total_usd" id="oc-total-usd">
        <input type="hidden" name="total_soles" id="oc-total-soles">
        <input type="hidden" name="condicion_pago" id="oc-condicion" value="contado">
        <input type="hidden" name="productos" id="oc-productos-json">

        {{-- ══ Membrete: la orden se ve como el documento que se emite ══ --}}
        <div class="ocd-membrete">
            <div class="ocd-membrete-marca">
                <div class="ocd-logo-chip"><img src="{{ asset('img/Logo-rec.png') }}" alt="{{ config('rentaltech.empresa.razon_social') }}"></div>
                <div>
                    <span class="ocd-tipo">Orden de compra</span>
                    <div class="ocd-emisor-nombre" id="ocr-proveedor">{{ old('proveedor') ?: 'Sin proveedor' }}</div>
                    <div class="ocd-emisor-dato">RUC <span id="ocr-ruc">{{ old('ruc') ?: '—' }}</span></div>
                </div>
            </div>

            <div class="ocd-membrete-der">
                <div class="ocd-caja-doc">
                    <div class="ocd-caja-tipo">Orden de compra</div>
                    <div class="ocd-numero" id="ocr-numero">—</div>
                    <div class="ocd-fecha" id="ocr-fecha">—</div>
                </div>
            </div>

            <div class="ocd-membrete-pie">
                <div class="ocd-pie-dato">Condición de pago <strong id="ocr-pago">Contado</strong></div>
            </div>
        </div>

        <div class="ocd-cuerpo">

        {{-- ══ Los tres tramos del recorrido ══ --}}
        <div class="ocp-pasos">
            <div class="ocp-riel"><i id="ocp-progreso"></i></div>

            <button type="button" class="ocp-paso-btn activo" data-ir="1">
                <span class="ocp-bolita">1</span>
                <span class="ocp-txt"><b>Qué compras</b><small>Productos y merch</small></span>
            </button>
            <button type="button" class="ocp-paso-btn" data-ir="2">
                <span class="ocp-bolita">2</span>
                <span class="ocp-txt"><b>Confirmar</b><small>Datos, costos y total</small></span>
            </button>
        </div>

        <section class="ocp-paso" data-paso="1">
            <h3 class="ocp-titulo">¿Qué vas a comprar?</h3>
            <p class="ocp-ayuda">Busca en el catálogo y agrega las líneas de la orden.</p>

        {{-- ══ Proveedor ══ --}}
        <div class="ocd-seccion">
            <span class="ocd-num">1</span>
            <div>
                <div class="ocd-tit">Proveedor</div>
                <div class="ocd-sub">A quién se le compra; elígelo del listado o escríbelo</div>
            </div>
        </div>

        <div class="oc-form-grid">
            <div class="oc-campo oc-form-full">
                <label class="oc-label" for="proveedor_select">Buscar en proveedores registrados</label>
                <select class="oc-input" id="proveedor_select">
                    <option value="">— Escribir manualmente —</option>
                    @foreach ($proveedores as $prov)
                        <option value="{{ $prov->id }}"
                                data-razon="{{ $prov->razon_social }}"
                                data-ruc="{{ $prov->ruc }}"
                                data-telefono="{{ $prov->telefono }}"
                                data-correo="{{ $prov->email }}"
                                data-direccion="{{ $prov->direccion }}"
                                data-distrito="{{ $prov->distrito }}"
                                data-provincia="{{ $prov->provincia }}"
                                data-departamento="{{ $prov->departamento }}">
                            {{ $prov->razon_social }} — {{ $prov->ruc }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="oc-campo">
                <label class="oc-label" for="proveedor">Razón social <span>*</span></label>
                <input type="text" class="oc-input" id="proveedor" name="proveedor" required maxlength="255"
                       value="{{ old('proveedor') }}">
            </div>
            <div class="oc-campo">
                <label class="oc-label" for="ruc">RUC</label>
                <input type="text" class="oc-input mono" id="ruc" name="ruc" maxlength="20" value="{{ old('ruc') }}">
            </div>
            <div class="oc-campo">
                <label class="oc-label" for="telefono">Teléfono</label>
                <input type="text" class="oc-input" id="telefono" name="telefono" maxlength="50" value="{{ old('telefono') }}">
            </div>
            <div class="oc-campo">
                <label class="oc-label" for="correo">Correo</label>
                <input type="email" class="oc-input" id="correo" name="correo" maxlength="150" value="{{ old('correo') }}">
            </div>
            <div class="oc-campo">
                <label class="oc-label" for="distrito">Distrito</label>
                <input type="text" class="oc-input" id="distrito" name="distrito" maxlength="100" value="{{ old('distrito') }}">
            </div>
            <div class="oc-campo">
                <label class="oc-label" for="provincia">Provincia</label>
                <input type="text" class="oc-input" id="provincia" name="provincia" maxlength="100" value="{{ old('provincia') }}">
            </div>
            <div class="oc-campo">
                <label class="oc-label" for="departamento">Departamento</label>
                <input type="text" class="oc-input" id="departamento" name="departamento" maxlength="100" value="{{ old('departamento') }}">
            </div>
            <div class="oc-campo oc-form-full">
                <label class="oc-label" for="direccion">Dirección</label>
                <textarea class="oc-input" id="direccion" name="direccion" rows="2">{{ old('direccion') }}</textarea>
            </div>
        </div>

        {{-- ══ Productos ══ --}}
        <div class="ocd-seccion">
            <span class="ocd-num">2</span>
            <div>
                <div class="ocd-tit">Productos</div>
                <div class="ocd-sub">Busca en el catálogo y agrega las líneas de la orden</div>
            </div>
            <span class="ocd-etiqueta">Soles</span>
        </div>

        <div class="oc-campo" style="margin-bottom:6px;">
            <label class="oc-label" for="oc-buscar">🔍 Buscar Producto</label>
            <div class="oc-buscador">
                <span class="lupa">🔍</span>
                <input type="text" class="oc-input" id="oc-buscar" autocomplete="off"
                       placeholder="Nombre o código del producto…">
                <div class="oc-dropdown" id="oc-prod-dd"></div>
            </div>
            <p class="oc-hint" id="oc-buscar-hint">
                Busca en el catálogo de productos que la empresa suele comprar — es el mismo para cualquier proveedor.
            </p>
        </div>

        <div class="oc-lista-prods" id="oc-lista-prods"></div>

        <div class="oc-prod-card" id="oc-prod-card">
            <div class="oc-prod-fila">
                <div class="oc-prod-info">
                    <div class="oc-prod-cod" id="oc-pc-codigo"></div>
                    <div class="oc-prod-desc" id="oc-pc-desc"></div>
                    <div class="oc-prod-meta" id="oc-pc-meta"></div>
                </div>
                <div class="oc-prod-acciones">
                    <input type="number" class="oc-qty" id="oc-qty" min="1" placeholder="Cant.">
                    <button type="button" class="btn-agregar-prod" id="oc-btn-agregar">✅ Agregar</button>
                    <button type="button" class="btn-quitar-prod" id="oc-btn-descartar">✕</button>
                </div>
            </div>
        </div>

        <div class="oc-resumen" id="oc-resumen">
            <div>
                <div class="oc-resumen-lbl">Total Orden</div>
                <div class="oc-resumen-sub"><span id="oc-resumen-cant">0</span> producto(s) agregado(s)</div>
            </div>
            <div class="oc-resumen-der">
                <div class="oc-resumen-moneda">SOLES</div>
                <div class="oc-resumen-fila">
                    <span>S/</span>
                    <input type="number" class="oc-total-input" id="oc-total-editable" step="0.01" min="0" value="0" readonly>
                </div>
            </div>
        </div>

        </section>

        <section class="ocp-paso oculto" data-paso="2">
            <h3 class="ocp-titulo">Datos, costos y confirmación</h3>
            <p class="ocp-ayuda">Con qué número y fecha se emite, con qué papeles viaja, el costo y la condición de pago — revisa todo antes de guardar.</p>

        {{-- ══ Datos generales ══ --}}
        <div class="ocd-seccion">
            <span class="ocd-num">3</span>
            <div>
                <div class="ocd-tit">Datos de la orden</div>
                <div class="ocd-sub">Número y fecha del documento</div>
            </div>
        </div>

        <div class="oc-form-grid">
            <div class="oc-campo">
                <label class="oc-label" for="oc-ref-fecha">📅 Ref. Fecha</label>
                <input type="text" class="oc-input mono ref" id="oc-ref-fecha" name="ref_fecha"
                       maxlength="8" placeholder="YYYYMMDD" value="{{ $previos['ref_fecha'] ?? now()->format('Ymd') }}">
                <span class="oc-hint">Formato: YYYYMMDD · Ej: 20260319</span>
            </div>

            <div class="oc-campo">
                <label class="oc-label" for="oc-numero">N° Orden</label>
                <input type="text" class="oc-input mono correlativo" id="oc-numero" name="numero_orden"
                       value="{{ $correlativo }}">
                <div id="oc-aviso-duplicado"></div>
            </div>

            <div class="oc-campo">
                <label class="oc-label" for="oc-fecha">Fecha de emisión</label>
                <input type="date" class="oc-input" id="oc-fecha" name="fecha" value="{{ $previos['fecha'] ?? now()->format('Y-m-d') }}">
            </div>
        </div>

        {{-- ══ Costos ══ --}}
        <div class="ocd-seccion">
            <span class="ocd-num">4</span>
            <div>
                <div class="ocd-tit">Costos</div>
                <div class="ocd-sub">Condición de pago</div>
            </div>
        </div>

        {{-- El tipo de cambio del catálogo Kendall ya no aplica (todo el
             formulario trabaja en soles); se deja fijo en 1 para que el
             resto del cálculo (compartido con órdenes históricas en
             dólares) no necesite ninguna rama nueva. --}}
        <input type="hidden" id="oc-tc" name="tc" value="1">
        {{-- El precio de venta es cosa de Ventas (Cotización/Nota de Venta/
             Boleta/Factura) — una Orden de Compra solo registra el costo de
             compra, así que este formulario ya no lo pide. --}}
        <input type="hidden" name="precio_venta" value="0">

        <div style="margin-top:16px;">
            <div class="oc-label" style="margin-bottom:10px;">💳 Condición de Pago</div>
            <div class="oc-pago">
                <div class="oc-pago-card activa" id="oc-pago-contado" data-tipo="contado">
                    <div class="oc-pago-icono">💵</div>
                    <div class="oc-pago-nombre">CONTADO</div>
                </div>
                <div class="oc-pago-card" id="oc-pago-credito" data-tipo="credito">
                    <div class="oc-pago-icono">🏦</div>
                    <div class="oc-pago-nombre">CRÉDITO</div>
                </div>
            </div>
            <div class="oc-dias" id="oc-dias-wrap">
                <div class="oc-label" style="margin-bottom:8px;">📅 Días de crédito</div>
                <input type="number" class="oc-input mono" id="oc-dias" min="1" max="365" placeholder="Ej: 45" style="width:160px;">
            </div>
        </div>


        {{-- ══ Observaciones ══ --}}
        <div class="ocd-seccion">
            <span class="ocd-num opcional">5</span>
            <div>
                <div class="ocd-tit">Observaciones</div>
                <div class="ocd-sub">Notas para el proveedor o para la secretaria</div>
            </div>
        </div>
        <div class="oc-campo">
            <textarea class="oc-input" name="observaciones" rows="3" placeholder="Condiciones, notas especiales, vendedor..."></textarea>
        </div>


            {{-- ══ Cierre del documento: qué lleva y cuánto suma ══ --}}
            <div class="ocd-cierre">
                <div>
                    <div class="ocd-lleva-tit">Esta orden lleva</div>

                    <div id="ocr-lista-productos"></div>
                </div>

                <div class="ocd-totales">
                    <div class="ocd-tgran">
                        <span>Total en soles</span>
                        <b id="ocr-total-soles">S/ 0.00</b>
                    </div>
                </div>
            </div>

        </section>


        </div>{{-- /ocd-cuerpo --}}

        {{-- ══ Barra fija: el total y el botón siempre a la vista ══ --}}
        <div class="ocd-barra">
            <div class="ocd-barra-info">
                <div class="ocd-barra-lbl">Total de la orden</div>
                <div class="ocd-barra-total"><span id="ocr-barra-total">S/ 0.00</span></div>
                <div class="ocd-barra-det" id="ocr-barra-det">Sin productos</div>
            </div>

            <div class="ocd-barra-acciones">
                <span class="ocd-aviso oculto" id="ocr-aviso">Agrega un producto para continuar</span>
                <a href="{{ route('admin.ordenes-compra.index') }}" class="ocd-cancelar">Cancelar</a>
                <button type="button" class="ocm-btn oculto" id="oc-atras">← Atrás</button>
                <button type="button" class="ocd-guardar" id="oc-siguiente">Siguiente →</button>
                <button type="submit" class="ocd-guardar oculto" id="oc-btn-guardar">Guardar orden</button>
            </div>
        </div>
    </form>

    {{-- ══ Ya guardada: se ofrece seguir con otra o cerrar la tanda ══ --}}
    @php $guardada = session('oc_guardada'); @endphp

    @if ($guardada)
    <div class="oc-modal abierto" id="oc-modal-otra">
        <div class="oc-modal-caja">
            <div class="oc-modal-icono">✅</div>
            <div class="oc-modal-tit">Orden <strong>{{ $guardada['numero'] }}</strong> registrada</div>
            <div class="oc-modal-cifra">S/ {{ number_format($guardada['total_soles'], 2) }}</div>

            <p class="oc-modal-txt">¿Vas a registrar otra orden?</p>

            @if ($guardada['enTanda'] > 1)
                <p class="oc-modal-nota">
                    Llevas {{ $guardada['enTanda'] }} órdenes en esta tanda. Al terminar salen
                    en un mismo Excel, con una hoja cada una.
                </p>
            @else
                <p class="oc-modal-nota">
                    Si sigues, el formulario arranca en blanco conservando fecha, cliente y tipo de cambio.
                    Al terminar se descargan todas juntas, una hoja por orden.
                </p>
            @endif

            <div class="oc-modal-acciones">
                <button type="button" class="oc-modal-si" id="oc-modal-si">Sí, registrar otra</button>
                <a href="{{ route('admin.ordenes-compra.index') }}" class="oc-modal-no">
                    No, terminar y ver el Excel
                </a>
            </div>
        </div>
    </div>
    @endif

</div>
</div>
@endsection

@push('scripts')
<script>
const URL_PRODUCTOS_BUSCAR = '{{ route('admin.productos.buscar') }}';
const URL_VERIFICAR = '{{ route('admin.ordenes-compra.verificar-numero') }}';

let productos   = [];   // líneas ya agregadas a la orden
let paso        = 1;    // tramo del asistente que se está viendo
let seleccionado = null; // producto elegido en el buscador, aún sin cantidad
let condicion   = 'contado';
let dias        = 30;

const $ = (id) => document.getElementById(id);

// ── Proveedor: autocompletar desde el catálogo o escribirlo a mano ───────
$('proveedor_select').addEventListener('change', function () {
    const opcion = this.selectedOptions[0];
    if (! opcion?.dataset.razon) { return; }

    $('proveedor').value      = opcion.dataset.razon || '';
    $('ruc').value            = opcion.dataset.ruc || '';
    $('telefono').value       = opcion.dataset.telefono || '';
    $('correo').value         = opcion.dataset.correo || '';
    $('direccion').value      = opcion.dataset.direccion || '';
    $('distrito').value       = opcion.dataset.distrito || '';
    $('provincia').value      = opcion.dataset.provincia || '';
    $('departamento').value   = opcion.dataset.departamento || '';

    pintarMembreteProveedor();
});

function pintarMembreteProveedor() {
    $('ocr-proveedor').textContent = $('proveedor').value.trim() || 'Sin proveedor';
    $('ocr-ruc').textContent       = $('ruc').value.trim() || '—';
}

['proveedor', 'ruc'].forEach((campo) => {
    $(campo).addEventListener('input', pintarMembreteProveedor);
});

// ── Buscador de productos: AJAX contra el catálogo real, el mismo para
//    cualquier proveedor (mismo patrón de búsqueda con debounce que el
//    autocompletado de cliente, más abajo) ──────────────────────────
let resultados = [];
let indice = -1;
let esperaProducto;

function resaltar(texto, termino) {
    const i = texto.toUpperCase().indexOf(termino.toUpperCase());

    if (i === -1) { return texto; }

    return texto.slice(0, i) + '<strong style="color:#3d9b8c">' + texto.slice(i, i + termino.length) + '</strong>' + texto.slice(i + termino.length);
}

function cerrarBuscador() {
    $('oc-prod-dd').classList.remove('abierto');
    indice = -1;
}

function buscarProducto(termino) {
    clearTimeout(esperaProducto);
    termino = termino.trim();

    if (termino.length < 2) { cerrarBuscador(); return; }

    esperaProducto = setTimeout(async () => {
        // La lista de productos que se compran es una sola, la misma para
        // cualquier proveedor — ya no se acota por el proveedor elegido.
        const url = URL_PRODUCTOS_BUSCAR + '?q=' + encodeURIComponent(termino);

        try {
            const respuesta = await fetch(url, { headers: { Accept: 'application/json' } });
            resultados = await respuesta.json();
        } catch (e) {
            resultados = [];
        }

        pintarResultados(termino);
    }, 220);
}

function pintarResultados(termino) {
    const dd = $('oc-prod-dd');

    if (!resultados.length) {
        dd.innerHTML = '<div class="oc-sin-resultados">🔍 Sin resultados para "<b>' + termino + '</b>"</div>';
        dd.classList.add('abierto');
        return;
    }

    dd.innerHTML = resultados.map((p, i) =>
        '<div class="oc-item" data-idx="' + i + '">' +
            '<div class="oc-item-top"><span class="oc-item-cod">' + (p.codigo || '—') + '</span>' +
            '<span class="oc-item-desc">' + resaltar(p.nombre, termino) + '</span></div>' +
            '<div class="oc-item-meta">' +
                '<span class="oc-chip">' + (p.presentacion || 'Und.') + '</span>' +
                '<span class="oc-chip">Compra S/ ' + (parseFloat(p.precio_compra) || 0).toFixed(2) + '</span>' +
                '<span class="oc-chip">Stock: ' + (p.stock ?? 0) + '</span>' +
            '</div></div>'
    ).join('');

    dd.querySelectorAll('.oc-item').forEach((item) => {
        item.addEventListener('click', () => elegirProducto(Number(item.dataset.idx)));
    });

    indice = -1;
    dd.classList.add('abierto');
}

function elegirProducto(i) {
    const p = resultados[i];

    if (!p) { return; }

    seleccionado = {
        codigo: p.codigo || '',
        descripcion: p.nombre,
        presentacion: p.presentacion || '',
        // Órdenes de Compra siempre sugiere el precio de COMPRA — el de
        // venta es para Ventas (Cotización/Nota de Venta/Boleta/Factura),
        // nunca al revés.
        precio_unit: parseFloat(p.precio_compra) || 0,
    };
    $('oc-buscar').value = '';
    cerrarBuscador();

    $('oc-pc-codigo').textContent = seleccionado.codigo || '—';
    $('oc-pc-desc').textContent   = seleccionado.descripcion;
    $('oc-pc-meta').textContent   = seleccionado.presentacion || 'Sin presentación';
    $('oc-prod-card').classList.add('visible');
    $('oc-qty').value = '';

    setTimeout(() => $('oc-qty').focus(), 80);
}

function descartarProducto() {
    seleccionado = null;
    $('oc-buscar').value = '';
    $('oc-qty').value = '';
    $('oc-prod-card').classList.remove('visible');
    cerrarBuscador();
}

$('oc-buscar').addEventListener('input', (e) => buscarProducto(e.target.value));

$('oc-buscar').addEventListener('keydown', (e) => {
    const items = document.querySelectorAll('#oc-prod-dd .oc-item');

    if (!items.length) { return; }

    if (e.key === 'ArrowDown')      { e.preventDefault(); indice = Math.min(indice + 1, items.length - 1); }
    else if (e.key === 'ArrowUp')   { e.preventDefault(); indice = Math.max(indice - 1, 0); }
    else if (e.key === 'Enter' && indice >= 0) { e.preventDefault(); elegirProducto(indice); return; }
    else if (e.key === 'Escape')    { cerrarBuscador(); return; }
    else { return; }

    items.forEach((el, i) => el.classList.toggle('activo', i === indice));
    items[indice]?.scrollIntoView({ block: 'nearest' });
});

document.addEventListener('click', (e) => {
    if (!e.target.closest('.oc-buscador')) { cerrarBuscador(); }
});

// ── Agregar y editar las líneas ──────────────────────────────────────────
function agregarProducto() {
    if (!seleccionado) { window.alert('Primero selecciona un producto.'); return; }

    const campo = $('oc-qty');
    const cantidad = parseInt(campo.value, 10) || 0;

    if (cantidad <= 0) {
        campo.focus();
        campo.style.borderColor = '#ef4444';
        setTimeout(() => { campo.style.borderColor = ''; }, 1800);
        return;
    }

    productos.push({
        codigo:          seleccionado.codigo,
        descripcion:     seleccionado.descripcion,
        unidad:          seleccionado.presentacion,
        precio_unit_usd: seleccionado.precio_unit,
        precio_editado:  false,
        cantidad:        cantidad,
    });

    pintarLineas();
    recalcular();
    setTimeout(() => { descartarProducto(); $('oc-buscar').focus(); }, 300);
}

function pintarLineas() {
    const lista = $('oc-lista-prods');

    lista.innerHTML = productos.map((p, i) => {
        const editado = p.precio_editado ? ' ✏️' : '';

        return '<div class="oc-linea">' +
            '<div class="oc-linea-num">' + (i + 1) + '</div>' +
            '<div class="oc-linea-info">' +
                '<div class="oc-linea-desc">' + p.descripcion + '</div>' +
                '<div class="oc-linea-meta"><span class="cod">' + p.codigo + '</span><span>' + p.unidad + '</span></div>' +
            '</div>' +
            '<div class="oc-linea-col"><div class="oc-linea-lbl">P.Unit S/' + editado + '</div>' +
                '<input type="number" class="oc-precio-input" data-campo="precio" data-idx="' + i + '" ' +
                'value="' + p.precio_unit_usd.toFixed(2) + '" min="0" step="0.01"></div>' +
            '<div class="oc-linea-col"><div class="oc-linea-lbl">Cant.</div>' +
                '<input type="number" class="oc-cant-input" data-campo="cantidad" data-idx="' + i + '" ' +
                'value="' + p.cantidad + '" min="1" step="1"></div>' +
            '<div class="oc-linea-col oc-linea-total"><div class="oc-linea-lbl">Total S/</div>' +
                '<div>S/ ' + (p.precio_unit_usd * p.cantidad).toFixed(2) + '</div></div>' +
            '<button type="button" class="btn-borrar-linea" data-borrar="' + i + '">✕</button>' +
        '</div>';
    }).join('');

    lista.querySelectorAll('input[data-campo]').forEach((campo) => {
        campo.addEventListener('input', () => {
            const p = productos[Number(campo.dataset.idx)];
            const valor = parseFloat(campo.value);

            if (campo.dataset.campo === 'precio') {
                if (isNaN(valor) || valor < 0) { return; }
                p.precio_unit_usd = valor;
                p.precio_editado = true;
            } else if (campo.dataset.campo === 'cantidad') {
                const entero = parseInt(campo.value, 10);
                if (isNaN(entero) || entero < 1) { campo.value = p.cantidad; return; }
                p.cantidad = entero;
            }

            recalcular();
        });
    });

    lista.querySelectorAll('[data-borrar]').forEach((boton) => {
        boton.addEventListener('click', () => {
            productos.splice(Number(boton.dataset.borrar), 1);
            pintarLineas();
            recalcular();
        });
    });
}

// ── Condición de pago ────────────────────────────────────────────────────
// Solo afecta cuándo se paga (dato del proveedor), ya no hay tarifas
// distintas por condición como en el catálogo Kendall.
function elegirCondicion(tipo) {
    condicion = tipo;

    $('oc-pago-contado').classList.toggle('activa', tipo === 'contado');
    $('oc-pago-credito').classList.toggle('activa', tipo === 'credito');
    $('oc-dias-wrap').classList.toggle('visible', tipo === 'credito');
    $('oc-condicion').value = tipo === 'credito' ? 'credito-' + dias : 'contado';
}

document.querySelectorAll('.oc-pago-card').forEach((card) => {
    card.addEventListener('click', () => elegirCondicion(card.dataset.tipo));
});

$('oc-dias').addEventListener('input', (e) => {
    dias = parseInt(e.target.value, 10) || 30;
    $('oc-condicion').value = 'credito-' + dias;
});

// ── Totales y resumen ────────────────────────────────────────────────────
// El total ya no se puede editar a mano: siempre es la suma real de las
// líneas, para que nunca se guarde un monto que no coincide con lo que
// realmente se está comprando.
function recalcular() {
    const sumaSoles = productos.reduce((a, p) => a + p.precio_unit_usd * p.cantidad, 0);

    const resumen = $('oc-resumen');
    resumen.classList.toggle('visible', productos.length > 0);
    $('oc-resumen-cant').textContent = productos.length;
    $('oc-total-editable').value = sumaSoles.toFixed(2);

    pintarPanel();
}

// ── Panel de resumen ────────────────────────────────────────────────
// Espejo de lo que se guardará. No calcula nada nuevo: lee el mismo estado
// que ya maneja `productos`.
function pintarPanel() {
    const totalSoles = parseFloat($('oc-total-editable').value) || 0;

    // Cabecera
    $('ocr-numero').textContent = $('oc-numero').value || '—';

    const fecha = $('oc-fecha').value;
    $('ocr-fecha').textContent = fecha
        ? new Date(fecha + 'T00:00:00').toLocaleDateString('es-PE', { day: '2-digit', month: 'long', year: 'numeric' })
        : '—';

    $('ocr-pago').textContent = condicion === 'credito' ? 'Crédito · ' + dias + ' días' : 'Contado';

    // Productos: una fila por línea, no solo el total agregado — para que
    // "Esta orden lleva" diga exactamente qué se está comprando.
    const listaLleva = $('ocr-lista-productos');

    listaLleva.innerHTML = productos.length === 0
        ? '<div class="ocd-lleva-item"><span class="ocd-lleva-punto"></span><span class="ocd-lleva-nombre">Sin productos agregados</span></div>'
        : productos.map((p) =>
            '<div class="ocd-lleva-item lleno">' +
                '<span class="ocd-lleva-punto"></span>' +
                '<span class="ocd-lleva-nombre">' + p.descripcion + (p.codigo ? ' <small>(' + p.codigo + ')</small>' : '') + '</span>' +
                '<span class="ocd-lleva-det">' +
                    '<span class="ocd-lleva-monto">S/ ' + (p.precio_unit_usd * p.cantidad).toFixed(2) + '</span>' +
                    '<span class="ocd-lleva-sub">' + p.cantidad + ' x S/ ' + p.precio_unit_usd.toFixed(2) + '</span>' +
                '</span>' +
            '</div>'
        ).join('');

    // Totales
    $('ocr-total-soles').textContent = 'S/ ' + totalSoles.toFixed(2);

    // Barra fija
    $('ocr-barra-total').textContent = 'S/ ' + totalSoles.toFixed(2);
    $('ocr-barra-det').textContent = productos.length === 0
        ? 'Sin productos'
        : productos.length + ' producto(s)';

    // Guardar solo tiene sentido si la orden lleva algo.
    const vacia = productos.length === 0;
    $('ocr-aviso').classList.toggle('oculto', !(vacia && paso === 1));
    $('oc-btn-guardar').disabled = vacia;

    const siguiente = $('oc-siguiente');
    if (siguiente) { siguiente.disabled = vacia && paso === 1; }
}

$('oc-tc').addEventListener('input', recalcular);
$('oc-btn-agregar').addEventListener('click', agregarProducto);
$('oc-btn-descartar').addEventListener('click', descartarProducto);
$('oc-qty').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); agregarProducto(); }
});

// ── Aviso de número de orden repetido ────────────────────────────────────
async function verificarNumero() {
    const numero = $('oc-numero').value.trim();
    const aviso = $('oc-aviso-duplicado');
    aviso.innerHTML = '';

    if (!numero) { return; }

    try {
        const respuesta = await fetch(URL_VERIFICAR + '?numero=' + encodeURIComponent(numero), {
            headers: { 'Accept': 'application/json' },
        });
        const datos = await respuesta.json();

        if (!datos.duplicado) { return; }

        aviso.innerHTML =
            '<div class="oc-duplicado"><span style="font-size:18px;">⚠️</span>' +
            '<div style="flex:1;"><strong>Número repetido</strong>' +
            'Ya existe la orden ' + datos.orden.numero + ' · ' + datos.orden.proveedor +
            ' · ' + datos.orden.fecha + ' · S/ ' + datos.orden.total_usd.toFixed(2) + '</div>' +
            '<button type="button" id="oc-btn-siguiente-libre">Usar el siguiente</button></div>';

        $('oc-btn-siguiente-libre').addEventListener('click', () => {
            $('oc-numero').value = String(parseInt(numero, 10) + 1).padStart(6, '0');
            verificarNumero();
        });
    } catch (e) {
        // Sin conexión no se avisa; el servidor igual acepta el número.
    }
}

$('oc-numero').addEventListener('blur', verificarNumero);
$('oc-numero').addEventListener('input', () => { $('oc-aviso-duplicado').innerHTML = ''; });

['oc-numero', 'oc-fecha'].forEach((campo) => {
    $(campo).addEventListener('input', pintarPanel);
});


// ── Envío ────────────────────────────────────────────────────────────────
$('formOrden').addEventListener('submit', (evento) => {
    if (productos.length === 0) {
        evento.preventDefault();
        window.alert('⚠️ Agrega al menos un producto a la orden.');
        return;
    }

    const totalSoles = parseFloat($('oc-total-editable').value)
        || productos.reduce((a, p) => a + p.precio_unit_usd * p.cantidad, 0);

    // `tc` se guarda fijo en 1 (ver el campo oculto): así el resto del
    // sistema, que todavía sabe leer órdenes históricas reales en dólares,
    // no necesita ninguna rama nueva para las órdenes en soles de ahora.
    $('oc-total-usd').value   = totalSoles.toFixed(2);
    $('oc-total-soles').value = totalSoles.toFixed(2);
    $('oc-productos-json').value = JSON.stringify(productos);

    $('oc-btn-guardar').textContent = 'Guardando...';
    $('oc-btn-guardar').disabled = true;
});

// ── Ya guardé ¿sigo con otra? ────────────────────────────────────
// El aviso solo aparece cuando la orden ya quedó registrada; cerrarlo deja
// el formulario en blanco listo para la siguiente de la tanda.
const modalOtra = $('oc-modal-otra');

if (modalOtra) {
    const cerrarModalOtra = () => modalOtra.classList.remove('abierto');

    $('oc-modal-si').addEventListener('click', cerrarModalOtra);
    modalOtra.addEventListener('click', (evento) => { if (evento.target === modalOtra) { cerrarModalOtra(); } });
    document.addEventListener('keydown', (evento) => { if (evento.key === 'Escape') { cerrarModalOtra(); } });
}

recalcular();

// ── Asistente de tres pasos ───────────────────────────────────────
// Solo se muestra un tramo a la vez. No se puede avanzar del primero sin
// nada que comprar, que es el único dato sin el cual la orden no existe.
const TRAMOS = 2;

function tramoValido(n) {
    if (n === 1 && productos.length === 0) {
        window.alert('\u26a0\ufe0f Agrega al menos un producto para continuar.');
        return false;
    }

    return true;
}

function irAlTramo(n) {
    // Hacia adelante se valida; hacia atrás siempre se deja volver.
    if (n > paso && ! tramoValido(paso)) { return; }

    paso = Math.min(Math.max(n, 1), TRAMOS);

    document.querySelectorAll('.ocp-paso').forEach((tramo) => {
        tramo.classList.toggle('oculto', Number(tramo.dataset.paso) !== paso);
    });

    document.querySelectorAll('.ocp-paso-btn').forEach((boton) => {
        const suyo = Number(boton.dataset.ir);
        boton.classList.toggle('activo', suyo === paso);
        boton.classList.toggle('hecho', suyo < paso);
    });

    $('ocp-progreso').style.width = ((paso - 1) / (TRAMOS - 1) * 100) + '%';
    $('oc-atras').classList.toggle('oculto', paso === 1);
    $('oc-siguiente').classList.toggle('oculto', paso === TRAMOS);
    $('oc-btn-guardar').classList.toggle('oculto', paso !== TRAMOS);

    recalcular();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

$('oc-siguiente').addEventListener('click', () => irAlTramo(paso + 1));
$('oc-atras').addEventListener('click', () => irAlTramo(paso - 1));

// Se puede volver a un tramo ya recorrido tocando su bolita.
document.querySelectorAll('.ocp-paso-btn').forEach((boton) => {
    boton.addEventListener('click', () => irAlTramo(Number(boton.dataset.ir)));
});

irAlTramo(1);
</script>
@endpush
