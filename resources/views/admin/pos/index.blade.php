@extends('layouts.pos')

@section('title', 'Punto de Venta')

@section('info-caja')
    @if ($sesion)
        <div class="pos-cab-caja">
            <span class="cdot"></span>
            {{ $sesion->caja->nombre }} · {{ auth()->user()->username }}
        </div>
        <button type="button" class="btn btn-secondary btn-sm" id="btnAbrirCerrarCaja">Cerrar caja</button>
    @endif
@endsection

@section('content')
@if (! $sesion)
    <div class="pos-panel-caja-cerrada">
        <h2>No tienes una caja abierta</h2>
        <p>Selecciona una caja y el monto inicial en efectivo para empezar a vender.</p>
        <form id="formAbrirCaja">
            @csrf
            <div class="form-group">
                <label for="posCajaId">Caja</label>
                <select id="posCajaId" required>
                    @forelse ($cajas as $caja)
                        <option value="{{ $caja->id }}">{{ $caja->nombre }}</option>
                    @empty
                        <option value="" disabled>No hay cajas registradas</option>
                    @endforelse
                </select>
            </div>
            <div class="form-group">
                <label for="posMontoInicial">Monto inicial (S/)</label>
                <input type="number" id="posMontoInicial" step="0.01" min="0" value="0.00" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;" @disabled($cajas->isEmpty())>Abrir caja</button>
        </form>
        @if ($cajas->isEmpty())
            <p style="margin-top:14px;font-size:12px;">
                <a href="{{ route('admin.caja.index') }}">Crea una caja primero →</a>
            </p>
        @endif
    </div>
@else
    <div class="pos-layout">
        {{-- Columna izquierda: buscador + grid de productos --}}
        <div>
            <div class="pos-buscador-caja">
                <div class="pos-buscador-fila">
                    <div class="form-group">
                        <label for="posAlmacenId">Almacén</label>
                        <select id="posAlmacenId">
                            @foreach ($almacenes as $almacen)
                                <option value="{{ $almacen->id }}">{{ $almacen->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group pos-buscador-icono" style="flex:2;">
                        <label for="posBuscar">Buscar producto (F2)</label>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" class="pos-buscar-input" id="posBuscar" placeholder="Nombre o código…" autocomplete="off">
                    </div>
                    <div class="form-group" style="justify-content:flex-end;">
                        <label>&nbsp;</label>
                        <button type="button" class="btn btn-secondary pos-suspendidas-toggle" data-modal="modalSuspendidas">
                            Suspendidas
                            <span class="pos-suspendidas-badge" id="posSuspendidasBadge" style="{{ $suspendidas->isEmpty() ? 'display:none;' : '' }}">{{ $suspendidas->count() }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="pos-grid-productos" id="posGridProductos">
                <div class="pos-sin-resultados">Busca un producto por nombre o código para empezar.</div>
            </div>
        </div>

        {{-- Columna derecha: carrito --}}
        <div class="pos-carrito">
            <div class="pos-carrito-cab">
                <h3>Carrito</h3>
                <button type="button" class="btn btn-secondary btn-sm" id="btnVaciarCarrito">Vaciar</button>
            </div>

            <div class="pos-carrito-lista" id="posCarritoLista">
                <div class="pos-carrito-vacio">Carrito vacío</div>
            </div>

            <div class="pos-carrito-pie">
                <div class="form-group" style="margin-bottom:10px;">
                    <label for="posClienteBuscar">Cliente (F4)</label>
                    <input type="text" id="posClienteBuscar" placeholder="Buscar por nombre o documento…" autocomplete="off">
                    <div class="nv-dropdown" id="posClienteDropdown"></div>
                    <button type="button" class="btn btn-secondary btn-sm" id="btnClienteVarios" style="margin-top:6px;width:100%;">Usar "Cliente Varios"</button>
                    <div id="posClienteSeleccionado" style="font-size:12px;color:var(--ink-3);margin-top:6px;">Cliente Varios</div>
                </div>

                <div class="form-group" style="margin-bottom:10px;">
                    <label for="posTipcomp">Comprobante</label>
                    <select id="posTipcomp">
                        @foreach ($tipos as $codigo => $tipo)
                            <option value="{{ $codigo }}">{{ $tipo['nombre'] }}</option>
                        @endforeach
                    </select>
                </div>

                @if ($esAdmin)
                    <div class="pos-descuento-fila">
                        <label for="posDescuentoPct" style="font-size:12px;color:var(--ink-3);">Descuento % (F6)</label>
                        <input type="number" id="posDescuentoPct" min="0" max="100" step="0.1" value="0">
                    </div>
                @endif

                <div class="pos-totales-fila"><span>Subtotal</span><span id="posSubtotal">S/ 0.00</span></div>
                <div class="pos-totales-fila"><span>Descuento</span><span id="posDescuentoMonto">S/ 0.00</span></div>
                <div class="pos-totales-fila"><span>IGV ({{ number_format(config('rentaltech.igv') * 100, 0) }}%)</span><span id="posIgv">S/ 0.00</span></div>
                <div class="pos-totales-fila total"><span>Total</span><span id="posTotal">S/ 0.00</span></div>

                <div class="pos-acciones-carrito">
                    <button type="button" class="btn btn-secondary" id="btnSuspender">Suspender (F8)</button>
                </div>
                <button type="button" class="btn btn-primary pos-btn-cobrar" id="btnCobrar">Cobrar (F10)</button>
            </div>
        </div>
    </div>

    {{-- Modal: cobro --}}
    <div class="modal-overlay" id="modalCobro">
        <div class="modal-card modal-card-sm">
            <div class="modal-header">
                <h3>Cobrar venta</h3>
                <button type="button" class="modal-close" data-cerrar="modalCobro">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="modal-body">
                <div class="pos-pago-total"><div class="monto" id="posModalTotal">S/ 0.00</div></div>

                <div class="pos-metodos-grid" id="posMetodosGrid">
                    @forelse ($metodosPago as $metodo)
                        <button type="button" class="pos-metodo-btn" data-metodo="{{ $metodo['label'] }}">{{ $metodo['label'] }}</button>
                    @empty
                        <button type="button" class="pos-metodo-btn" data-metodo="Efectivo">Efectivo</button>
                        <button type="button" class="pos-metodo-btn" data-metodo="Yape">Yape</button>
                        <button type="button" class="pos-metodo-btn" data-metodo="Plin">Plin</button>
                    @endforelse
                </div>

                <div class="form-group">
                    <label for="posMontoPago">Monto</label>
                    <input type="number" id="posMontoPago" step="0.01" min="0">
                </div>
                <div class="form-group" style="margin-top:10px;">
                    <label for="posReferenciaPago">N° de operación (opcional)</label>
                    <input type="text" id="posReferenciaPago" maxlength="100">
                </div>

                <button type="button" class="btn btn-secondary" id="btnAgregarPago" style="width:100%;margin:14px 0 10px;">+ Agregar pago</button>

                <div class="pos-pagos-lista" id="posPagosLista"></div>

                <div class="pos-pago-resumen"><span>Pagado</span><span id="posPagadoResumen">S/ 0.00</span></div>
                <div class="pos-pago-resumen falta" id="posFaltaResumen" style="display:none;"><span>Falta</span><span id="posFaltaMonto">S/ 0.00</span></div>
                <div class="pos-pago-resumen vuelto" id="posVueltoResumen" style="display:none;"><span>Vuelto</span><span id="posVueltoMonto">S/ 0.00</span></div>

                <button type="button" class="btn btn-primary pos-btn-cobrar" id="btnConfirmarCobro" disabled>Confirmar cobro</button>
            </div>
        </div>
    </div>

    {{-- Modal: ventas suspendidas --}}
    <div class="modal-overlay" id="modalSuspendidas">
        <div class="modal-card modal-card-sm">
            <div class="modal-header">
                <h3>Ventas suspendidas</h3>
                <button type="button" class="modal-close" data-cerrar="modalSuspendidas">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="modal-body" id="posSuspendidasLista">
                @forelse ($suspendidas as $s)
                    <div class="pos-suspendida-item" data-id="{{ $s->id }}">
                        <div class="pos-suspendida-info">
                            {{ $s->cliente_etiqueta ?: 'Cliente Varios' }}
                            <small>S/ {{ number_format((float) $s->total_referencial, 2) }} · {{ $s->created_at?->diffForHumans() }}</small>
                        </div>
                        <div style="display:flex;gap:6px;">
                            <button type="button" class="btn btn-secondary btn-sm" data-recuperar="{{ $s->id }}">Recuperar</button>
                            <button type="button" class="btn btn-danger btn-sm" data-eliminar-suspendida="{{ $s->id }}">Descartar</button>
                        </div>
                    </div>
                @empty
                    <p style="color:var(--ink-3);font-size:13px;" id="posSuspendidasVacio">No hay ventas suspendidas.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Modal: cerrar caja --}}
    <div class="modal-overlay" id="modalCerrarCaja">
        <div class="modal-card modal-card-sm">
            <div class="modal-header">
                <h3>Cerrar caja</h3>
                <button type="button" class="modal-close" data-cerrar="modalCerrarCaja">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="posMontoContado">Efectivo contado en caja (S/)</label>
                    <input type="number" id="posMontoContado" step="0.01" min="0">
                </div>
                <button type="button" class="btn btn-primary" id="btnConfirmarCierre" style="width:100%;margin-top:10px;">Cerrar caja</button>
            </div>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name="csrf-token"]').content;
const IGV_PCT = {{ (float) config('rentaltech.igv') }};
const ES_ADMIN = @json($esAdmin);

@if ($sesion)
let carrito = [];
let clienteSeleccionado = null; // { id, nombre } o null = "Cliente Varios"
let pagos = [];
let ventaSuspendidaId = null;
let posToken = crypto.randomUUID ? crypto.randomUUID() : String(Date.now()) + Math.random();

function pad2(n) { return String(n).padStart(2, '0'); }
function money(n) { return 'S/ ' + (Math.round(n * 100) / 100).toFixed(2); }

async function peticionJson(url, opciones = {}) {
    const respuesta = await fetch(url, {
        ...opciones,
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json', ...(opciones.headers || {}) },
    });
    const datos = await respuesta.json().catch(() => ({}));
    if (!respuesta.ok) {
        const mensaje = datos.errors ? Object.values(datos.errors).flat().join('\n') : (datos.message || 'Ocurrió un error.');
        throw new Error(mensaje);
    }
    return datos;
}

// ── Búsqueda de productos ──────────────────────────────────────────────
const posBuscar = document.getElementById('posBuscar');
const posAlmacenId = document.getElementById('posAlmacenId');
const posGrid = document.getElementById('posGridProductos');
let temporizadorBusqueda = null;

async function buscarProductos() {
    const q = posBuscar.value.trim();
    const almacenId = posAlmacenId.value;

    if (q === '') {
        posGrid.innerHTML = '<div class="pos-sin-resultados">Busca un producto por nombre o código para empezar.</div>';
        return;
    }

    const url = '{{ route('admin.pos.productos.buscar') }}?q=' + encodeURIComponent(q) + '&almacen_id=' + almacenId;
    const productos = await fetch(url).then((r) => r.json());

    if (productos.length === 0) {
        posGrid.innerHTML = '<div class="pos-sin-resultados">🔍 Sin resultados para "' + q + '"</div>';
        return;
    }

    posGrid.innerHTML = productos.map((p) => {
        let claseStock = '';
        if (p.stock <= 0) claseStock = 'agotado';
        else if (p.stock <= 5) claseStock = 'bajo';

        return '<div class="pos-prod-card" data-producto=\'' + JSON.stringify(p).replace(/'/g, '&apos;') + '\'>'
            + '<span class="pos-prod-cod">' + (p.codigo || '—') + '</span>'
            + '<span class="pos-prod-nombre">' + p.nombre + '</span>'
            + '<span class="pos-prod-precio">' + money(p.precio_venta) + '</span>'
            + '<span class="pos-prod-stock ' + claseStock + '">Stock: ' + p.stock + '</span>'
            + '</div>';
    }).join('');
}

posBuscar.addEventListener('input', () => {
    clearTimeout(temporizadorBusqueda);
    temporizadorBusqueda = setTimeout(buscarProductos, 220);
});
posAlmacenId.addEventListener('change', buscarProductos);

posGrid.addEventListener('click', (e) => {
    const card = e.target.closest('.pos-prod-card');
    if (!card) return;
    const producto = JSON.parse(card.dataset.producto.replace(/&apos;/g, "'"));
    agregarAlCarrito(producto);
});

// ── Carrito ─────────────────────────────────────────────────────────────
function agregarAlCarrito(producto) {
    const existente = carrito.find((i) => i.producto_id === producto.id);
    if (existente) {
        existente.cantidad += 1;
    } else {
        carrito.push({
            producto_id: producto.id, codigo: producto.codigo, nombre: producto.nombre,
            precio_unitario: producto.precio_venta, cantidad: 1,
        });
    }
    renderCarrito();
}

function cambiarCantidad(productoId, delta) {
    const item = carrito.find((i) => i.producto_id === productoId);
    if (!item) return;
    item.cantidad += delta;
    if (item.cantidad <= 0) {
        carrito = carrito.filter((i) => i.producto_id !== productoId);
    }
    renderCarrito();
}

function quitarDelCarrito(productoId) {
    carrito = carrito.filter((i) => i.producto_id !== productoId);
    renderCarrito();
}

const posCarritoLista = document.getElementById('posCarritoLista');

function descuentoPctActual() {
    if (!ES_ADMIN) return 0;
    const input = document.getElementById('posDescuentoPct');
    return input ? Math.max(0, Math.min(100, parseFloat(input.value) || 0)) : 0;
}

function renderCarrito() {
    if (carrito.length === 0) {
        posCarritoLista.innerHTML = '<div class="pos-carrito-vacio">Carrito vacío</div>';
    } else {
        posCarritoLista.innerHTML = carrito.map((item) => `
            <div class="pos-carrito-item">
                <div class="pos-ci-info">
                    <div class="pos-ci-nombre">${item.nombre}</div>
                    <div class="pos-ci-precio">${money(item.precio_unitario)} c/u</div>
                </div>
                <div class="pos-ci-qty">
                    <button type="button" data-menos="${item.producto_id}">−</button>
                    <span>${item.cantidad}</span>
                    <button type="button" data-mas="${item.producto_id}">+</button>
                </div>
                <div class="pos-ci-subtotal">${money(item.precio_unitario * item.cantidad)}</div>
                <button type="button" class="pos-ci-quitar" data-quitar="${item.producto_id}" title="Quitar">✕</button>
            </div>
        `).join('');
    }
    calcularTotales();
}

posCarritoLista.addEventListener('click', (e) => {
    const menos = e.target.closest('[data-menos]');
    const mas = e.target.closest('[data-mas]');
    const quitar = e.target.closest('[data-quitar]');
    if (menos) cambiarCantidad(parseInt(menos.dataset.menos, 10), -1);
    if (mas) cambiarCantidad(parseInt(mas.dataset.mas, 10), 1);
    if (quitar) quitarDelCarrito(parseInt(quitar.dataset.quitar, 10));
});

document.getElementById('posDescuentoPct')?.addEventListener('input', calcularTotales);

function calcularTotales() {
    const descuentoPct = descuentoPctActual();
    let bruto = 0, descuento = 0;

    carrito.forEach((item) => {
        const b = item.precio_unitario * item.cantidad;
        bruto += b;
        descuento += b * (descuentoPct / 100);
    });

    const neto = bruto - descuento;
    const base = neto / (1 + IGV_PCT);
    const igv = neto - base;

    document.getElementById('posSubtotal').textContent = money(bruto);
    document.getElementById('posDescuentoMonto').textContent = money(descuento);
    document.getElementById('posIgv').textContent = money(igv);
    document.getElementById('posTotal').textContent = money(neto);

    return neto;
}

document.getElementById('btnVaciarCarrito').addEventListener('click', async () => {
    if (carrito.length === 0) return;
    if (!await confirmar('¿Vaciar el carrito?')) return;
    carrito = [];
    ventaSuspendidaId = null;
    renderCarrito();
});

// ── Cliente ──────────────────────────────────────────────────────────────
const posClienteBuscar = document.getElementById('posClienteBuscar');
const posClienteDropdown = document.getElementById('posClienteDropdown');
const posClienteSeleccionado = document.getElementById('posClienteSeleccionado');
let temporizadorCliente = null;

posClienteBuscar.addEventListener('input', () => {
    clienteSeleccionado = null;
    clearTimeout(temporizadorCliente);
    const termino = posClienteBuscar.value.trim();
    if (termino === '') { posClienteDropdown.classList.remove('activo'); return; }

    temporizadorCliente = setTimeout(async () => {
        const clientes = await fetch('{{ route('admin.clientes.buscar') }}?q=' + encodeURIComponent(termino)).then((r) => r.json());
        if (clientes.length === 0) {
            posClienteDropdown.innerHTML = '<div class="nv-sin-resultados">Sin resultados</div>';
        } else {
            posClienteDropdown.innerHTML = clientes.map((c) => `
                <div class="nv-item" data-id="${c.id}" data-nombre="${(c.nombres || c.nombre_empresa || '').replace(/"/g, '&quot;')}">
                    <div class="nv-item-top"><span class="nv-item-cod">${c.numero_documento || '—'}</span>
                    <span class="nv-item-desc">${c.nombres || c.nombre_empresa || ''}</span></div>
                </div>
            `).join('');
        }
        posClienteDropdown.classList.add('activo');
    }, 220);
});

posClienteDropdown.addEventListener('click', (e) => {
    const item = e.target.closest('.nv-item');
    if (!item) return;
    clienteSeleccionado = { id: parseInt(item.dataset.id, 10), nombre: item.dataset.nombre };
    posClienteBuscar.value = item.dataset.nombre;
    posClienteSeleccionado.textContent = item.dataset.nombre;
    posClienteDropdown.classList.remove('activo');
});

document.getElementById('btnClienteVarios').addEventListener('click', () => {
    clienteSeleccionado = null;
    posClienteBuscar.value = '';
    posClienteSeleccionado.textContent = 'Cliente Varios';
    posClienteDropdown.classList.remove('activo');
});

// ── Payload común (venta y suspender) ──────────────────────────────────
// Los items llevan codigo/nombre/precio_unitario además de producto_id y
// cantidad: al cobrar el servidor los ignora y recalcula todo desde el
// catálogo real (nunca confía en el cliente) — pero al suspender sirven
// para poder repintar el carrito recuperado sin volver a buscar cada
// producto uno por uno.
function payloadCarrito() {
    return {
        almacen_id: posAlmacenId.value,
        tipcomp: document.getElementById('posTipcomp').value,
        cliente_id: clienteSeleccionado?.id || null,
        razonsocial: clienteSeleccionado?.nombre || 'Cliente Varios',
        descuento_pct: descuentoPctActual(),
        items: carrito.map((i) => ({
            producto_id: i.producto_id, codigo: i.codigo, nombre: i.nombre,
            precio_unitario: i.precio_unitario, cantidad: i.cantidad,
        })),
        venta_suspendida_id: ventaSuspendidaId,
    };
}

// ── Suspender ────────────────────────────────────────────────────────────
document.getElementById('btnSuspender').addEventListener('click', async () => {
    if (carrito.length === 0) { window.alert('El carrito está vacío.'); return; }
    try {
        await peticionJson('{{ route('admin.pos.suspender') }}', { method: 'POST', body: JSON.stringify(payloadCarrito()) });
        window.location.reload();
    } catch (e) {
        window.alert(e.message);
    }
});

function actualizarBadgeSuspendidas(delta) {
    const badge = document.getElementById('posSuspendidasBadge');
    const actual = parseInt(badge.textContent || '0', 10) + delta;
    badge.textContent = actual;
    badge.style.display = actual > 0 ? '' : 'none';
}

document.getElementById('posSuspendidasLista')?.addEventListener('click', async (e) => {
    const recuperar = e.target.closest('[data-recuperar]');
    const eliminar = e.target.closest('[data-eliminar-suspendida]');

    if (recuperar) {
        const id = recuperar.dataset.recuperar;
        const suspendida = await fetch('{{ url('admin/pos/suspendidas') }}/' + id).then((r) => r.json());
        const datos = suspendida.datos;

        carrito = (datos.items || []).map((i) => ({
            producto_id: i.producto_id, codigo: i.codigo || '', nombre: i.nombre || '(producto)',
            precio_unitario: parseFloat(i.precio_unitario) || 0, cantidad: i.cantidad,
        }));

        document.getElementById('posTipcomp').value = datos.tipcomp || '03';
        if (datos.almacen_id) posAlmacenId.value = datos.almacen_id;
        clienteSeleccionado = datos.cliente_id ? { id: datos.cliente_id, nombre: datos.razonsocial } : null;
        posClienteSeleccionado.textContent = datos.razonsocial || 'Cliente Varios';
        if (ES_ADMIN && document.getElementById('posDescuentoPct')) {
            document.getElementById('posDescuentoPct').value = datos.descuento_pct || 0;
        }
        ventaSuspendidaId = suspendida.id;

        renderCarrito();
        cerrarModal('modalSuspendidas');
    }

    if (eliminar) {
        const id = eliminar.dataset.eliminarSuspendida;
        if (!await confirmar('¿Descartar esta venta suspendida?')) return;
        await fetch('{{ url('admin/pos/suspendidas') }}/' + id, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } });
        eliminar.closest('.pos-suspendida-item').remove();
        actualizarBadgeSuspendidas(-1);
    }
});

// ── Modal de cobro ──────────────────────────────────────────────────────
const btnCobrar = document.getElementById('btnCobrar');
btnCobrar.addEventListener('click', () => {
    if (carrito.length === 0) { window.alert('Agrega al menos un producto.'); return; }
    pagos = [];
    renderPagos();
    document.getElementById('posModalTotal').textContent = money(calcularTotales());
    document.getElementById('posMontoPago').value = calcularTotales().toFixed(2);
    document.querySelectorAll('.pos-metodo-btn').forEach((b) => b.classList.remove('activo'));
    abrirModal('modalCobro');
});

document.getElementById('posMetodosGrid').addEventListener('click', (e) => {
    const boton = e.target.closest('.pos-metodo-btn');
    if (!boton) return;
    document.querySelectorAll('.pos-metodo-btn').forEach((b) => b.classList.remove('activo'));
    boton.classList.add('activo');
});

document.getElementById('btnAgregarPago').addEventListener('click', () => {
    const metodoBtn = document.querySelector('.pos-metodo-btn.activo');
    const monto = parseFloat(document.getElementById('posMontoPago').value) || 0;

    if (!metodoBtn) { window.alert('Elige un método de pago.'); return; }
    if (monto <= 0) { window.alert('El monto debe ser mayor a 0.'); return; }

    pagos.push({ metodo_pago: metodoBtn.dataset.metodo, monto, referencia: document.getElementById('posReferenciaPago').value.trim() });
    document.getElementById('posReferenciaPago').value = '';

    const total = calcularTotales();
    const pagado = pagos.reduce((s, p) => s + p.monto, 0);
    document.getElementById('posMontoPago').value = Math.max(0, total - pagado).toFixed(2);

    renderPagos();
});

function renderPagos() {
    const lista = document.getElementById('posPagosLista');
    lista.innerHTML = pagos.map((p, idx) => `
        <div class="pos-pago-fila">
            <span>${p.metodo_pago}${p.referencia ? ' (' + p.referencia + ')' : ''}</span>
            <span>${money(p.monto)}</span>
            <button type="button" data-quitar-pago="${idx}">✕</button>
        </div>
    `).join('');

    const total = calcularTotales();
    const pagado = pagos.reduce((s, p) => s + p.monto, 0);
    const diferencia = pagado - total;

    document.getElementById('posPagadoResumen').textContent = money(pagado);
    document.getElementById('posFaltaResumen').style.display = diferencia < -0.009 ? '' : 'none';
    document.getElementById('posVueltoResumen').style.display = diferencia > 0.009 ? '' : 'none';
    if (diferencia < -0.009) document.getElementById('posFaltaMonto').textContent = money(-diferencia);
    if (diferencia > 0.009) document.getElementById('posVueltoMonto').textContent = money(diferencia);

    document.getElementById('btnConfirmarCobro').disabled = diferencia < -0.009;
}

document.getElementById('posPagosLista').addEventListener('click', (e) => {
    const boton = e.target.closest('[data-quitar-pago]');
    if (!boton) return;
    pagos.splice(parseInt(boton.dataset.quitarPago, 10), 1);
    renderPagos();
});

document.getElementById('btnConfirmarCobro').addEventListener('click', async function () {
    this.disabled = true;
    try {
        const payload = { ...payloadCarrito(), pagos, pos_token: posToken };
        const resultado = await peticionJson('{{ route('admin.pos.venta') }}', { method: 'POST', body: JSON.stringify(payload) });

        carrito = [];
        ventaSuspendidaId = null;
        posToken = crypto.randomUUID ? crypto.randomUUID() : String(Date.now()) + Math.random();
        renderCarrito();
        cerrarModal('modalCobro');
        window.open(resultado.comprobante_url, '_blank');
    } catch (e) {
        window.alert(e.message);
        this.disabled = false;
    }
});

// ── Caja: cerrar ──────────────────────────────────────────────────────────
document.getElementById('btnAbrirCerrarCaja')?.addEventListener('click', () => abrirModal('modalCerrarCaja'));

document.getElementById('btnConfirmarCierre')?.addEventListener('click', async function () {
    const monto = parseFloat(document.getElementById('posMontoContado').value);
    if (isNaN(monto) || monto < 0) { window.alert('Ingresa el efectivo contado.'); return; }

    try {
        await peticionJson('{{ url('admin/caja/sesiones') }}/{{ $sesion->id }}/cerrar', {
            method: 'POST', body: JSON.stringify({ monto_final_contado: monto }),
        });
        window.location.reload();
    } catch (e) {
        window.alert(e.message);
    }
});

// ── Atajos de teclado ────────────────────────────────────────────────────
document.addEventListener('keydown', (e) => {
    if (document.querySelector('.modal-overlay.active')) return;

    if (e.key === 'F2') { e.preventDefault(); posBuscar.focus(); }
    if (e.key === 'F4') { e.preventDefault(); posClienteBuscar.focus(); }
    if (e.key === 'F6' && ES_ADMIN) { e.preventDefault(); document.getElementById('posDescuentoPct')?.focus(); }
    if (e.key === 'F8') { e.preventDefault(); document.getElementById('btnSuspender').click(); }
    if (e.key === 'F9') { e.preventDefault(); abrirModal('modalSuspendidas'); }
    if (e.key === 'F10') { e.preventDefault(); btnCobrar.click(); }
    if (e.key === 'F12') { e.preventDefault(); document.getElementById('btnVaciarCarrito').click(); }
});

renderCarrito();
@endif

// ── Abrir caja ──────────────────────────────────────────────────────────
document.getElementById('formAbrirCaja')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const cajaId = document.getElementById('posCajaId').value;
    const monto = parseFloat(document.getElementById('posMontoInicial').value) || 0;

    try {
        const respuesta = await fetch('{{ url('admin/caja') }}/' + cajaId + '/abrir', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ monto_inicial: monto }),
        });
        const datos = await respuesta.json();
        if (!respuesta.ok) throw new Error(datos.errors ? Object.values(datos.errors).flat().join('\n') : 'No se pudo abrir la caja.');
        window.location.reload();
    } catch (e) {
        window.alert(e.message);
    }
});
</script>
@endpush
