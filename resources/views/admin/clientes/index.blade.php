@extends('layouts.admin')

@section('title', 'Clientes')
@section('crumb', 'Cartera de clientes')

@section('content')

<x-page-header titulo="Clientes" subtitulo="Gestión de la cartera de clientes de Rental Tech SAC">
    <x-slot:acciones>
        <a href="{{ route('admin.clientes.destacados') }}" class="btn btn-dark btn-sm">
            <span class="btn-icon">★</span><span class="btn-text">Destacados</span>
        </a>
        <button type="button" class="btn btn-secondary btn-sm" id="btnHistorialCliente">
            <span class="btn-icon">🔎</span><span class="btn-text">Historial de un cliente</span>
        </button>
        <button type="button" class="btn btn-secondary btn-sm" data-modal="modalImportar">
            <span class="btn-icon">⬆</span><span class="btn-text">Importar Excel</span>
        </button>
        <button type="button" class="btn btn-primary" id="btnNuevoCliente">
            <span class="btn-icon">＋</span><span class="btn-text">Nuevo Cliente</span>
        </button>
    </x-slot:acciones>
</x-page-header>

<div class="stats-grid">
    <x-stat-card :valor="number_format($totalClientes)" etiqueta="Total de clientes" />
    <x-stat-card :valor="number_format($clientesDni)" etiqueta="Personas (DNI)" />
    <x-stat-card :valor="number_format($clientesRuc)" etiqueta="Empresas (RUC)" />
</div>

<div class="content-card">
    <div class="lista-header">
        <h3>Lista de Clientes</h3>

        <form method="GET" class="filtros">
            <x-buscador :valor="$busqueda" placeholder="Buscar por nombre, documento o teléfono…" />
            <input type="hidden" name="mes" value="{{ $periodo['mes'] }}">
            <input type="hidden" name="desde" value="{{ $periodo['desde'] }}">
            <input type="hidden" name="hasta" value="{{ $periodo['hasta'] }}">
            <button type="submit" class="btn btn-primary btn-sm"><span class="btn-text">Buscar</span></button>
            @if ($busqueda !== '')
                <a href="{{ route('admin.clientes.index', array_filter($periodo)) }}" class="btn btn-secondary btn-sm">
                    <span class="btn-text">Limpiar</span>
                </a>
            @endif
        </form>
    </div>

    {{-- ── Cuánto compró cada cliente en un periodo ─────────────────────── --}}
    <form method="GET" class="cli-periodo">
        @if ($busqueda !== '')<input type="hidden" name="q" value="{{ $busqueda }}">@endif

        <span class="cli-periodo-tit">📅 Compras del periodo:</span>

        <label>Mes</label>
        <input type="month" name="mes" value="{{ $periodo['mes'] }}">

        <span class="cli-periodo-sep">|</span>

        <label>Desde</label>
        <input type="date" name="desde" value="{{ $periodo['desde'] }}">

        <label>Hasta</label>
        <input type="date" name="hasta" value="{{ $periodo['hasta'] }}">

        <button type="submit" class="btn btn-primary btn-sm"><span class="btn-text">Aplicar</span></button>

        @if ($hayPeriodo)
            <a href="{{ route('admin.clientes.index', array_filter(['q' => $busqueda])) }}"
               class="btn btn-secondary btn-sm"><span class="btn-text">Ver todos</span></a>

            <span class="cli-periodo-total">
                {{ $nCompradores }} cliente(s) compraron
                <b>S/ {{ number_format($totalComprado, 2) }}</b>
            </span>
        @endif
    </form>

    @if ($busqueda !== '')
        <p class="resultado-busqueda">
            {{ $clientes->total() }} resultado(s) para <strong>"{{ $busqueda }}"</strong>
        </p>
    @endif

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Documento</th>
                    <th>Nombre / Razón social</th>
                    <th>{{ $hayPeriodo ? 'Comprado en el periodo' : 'Comprado (histórico)' }}</th>
                    <th>Pendiente de cobro</th>
                    <th>Contacto</th>
                    <th>Ubicación</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($clientes as $cliente)
                <tr>
                    <td>
                        <strong>{{ $cliente->numero_documento }}</strong>
                        <div style="font-size:12px;color:#666;">{{ $cliente->tipo_documento }}</div>
                    </td>
                    <td>
                        {{ $cliente->nombres }}
                        @if ($cliente->nombre_empresa)
                            <div style="font-size:12px;color:#666;">{{ $cliente->nombre_empresa }}</div>
                        @endif
                    </td>
                    @php
                        $compra = $compras[$cliente->id] ?? null;
                        $deuda  = $deudas[$cliente->id] ?? null;
                        $rango  = array_filter($periodo) + ['cliente' => $cliente->id];
                    @endphp
                    <td class="cli-monto">
                        @if ($compra)
                            <button type="button" class="cli-enlace" data-movimientos="{{ $cliente->id }}" data-nombre="{{ $cliente->nombres }}">
                                S/ {{ number_format($compra['monto'], 2) }}
                            </button>
                            <div class="cli-sub">{{ $compra['n'] }} comprobante(s)</div>
                        @else
                            <span class="cli-nada">—</span>
                        @endif
                    </td>
                    <td class="cli-monto">
                        @if ($deuda && $deuda['saldo'] > 0)
                            <button type="button" class="cli-enlace cli-deuda" data-movimientos="{{ $cliente->id }}" data-nombre="{{ $cliente->nombres }}">
                                S/ {{ number_format($deuda['saldo'], 2) }}
                            </button>
                            <div class="cli-sub">{{ $deuda['n'] }} documento(s)</div>
                        @elseif ($deuda)
                            <span class="cli-alcorriente">Al día</span>
                            <div class="cli-sub">{{ $deuda['n'] }} documento(s)</div>
                        @else
                            <span class="cli-nada">—</span>
                        @endif
                    </td>
                    <td>
                        {{ $cliente->telefono ?: '—' }}
                        @if ($cliente->email)
                            <div style="font-size:12px;color:#666;">{{ $cliente->email }}</div>
                        @endif
                    </td>
                    <td>{{ collect([$cliente->distrito, $cliente->provincia, $cliente->departamento])->filter()->implode(', ') ?: '—' }}</td>
                    <td style="white-space:nowrap;">
                        <button type="button" class="btn btn-dark btn-sm btn-editar"
                                data-cliente="{{ $cliente->id }}"
                                data-tipo-documento="{{ $cliente->tipo_documento }}"
                                data-numero-documento="{{ $cliente->numero_documento }}"
                                data-nombres="{{ $cliente->nombres }}"
                                data-nombre-empresa="{{ $cliente->nombre_empresa }}"
                                data-telefono="{{ $cliente->telefono }}"
                                data-email="{{ $cliente->email }}"
                                data-direccion="{{ $cliente->direccion }}"
                                data-distrito="{{ $cliente->distrito }}"
                                data-provincia="{{ $cliente->provincia }}"
                                data-departamento="{{ $cliente->departamento }}"
                                data-cumpleanos="{{ $cliente->fecha_cumpleanos?->format('Y-m-d') }}">
                            Editar
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm" data-movimientos="{{ $cliente->id }}" data-nombre="{{ $cliente->nombres }}">
                            Ver compras
                        </button>
                        <form method="POST" action="{{ route('admin.clientes.destroy', $cliente) }}"
                              style="display:inline;" data-confirmar="¿Eliminar a {{ $cliente->nombres }}?">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center;padding:40px;color:#666;">
                    {{ $hayPeriodo ? 'Ningún cliente compró en ese periodo' : 'Sin clientes registrados' }}
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $clientes->links() }}
</div>

{{-- ── Alta / edición ── --}}
<div class="modal-overlay" id="modalCliente">
    <div class="modal-card">
        <div class="modal-header">
            <h3>
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                <span id="tituloModal">Nuevo Cliente</span>
            </h3>
            <button type="button" class="modal-close" data-cerrar="modalCliente" aria-label="Cerrar">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>

        <div class="modal-body">
            <form method="POST" action="{{ route('admin.clientes.store') }}" id="formCliente">
                @csrf
                <input type="hidden" name="cliente_id" id="cliente_id" value="">

                <div class="form-grid">
                    <div class="form-group">
                        <label for="tipo_documento">Tipo de Documento <span>*</span></label>
                        <select id="tipo_documento" name="tipo_documento" required>
                            <option value="DNI">DNI</option>
                            <option value="RUC">RUC</option>
                            <option value="CE">Carnet de Extranjería</option>
                            <option value="Pasaporte">Pasaporte</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="numero_documento">Número de Documento <span>*</span></label>
                        <div style="display:flex;gap:8px;">
                            <input type="text" id="numero_documento" name="numero_documento" required
                                   maxlength="20" placeholder="12345678" style="flex:1;">
                            <button type="button" class="btn btn-secondary" id="btnBuscarDocumento" title="Buscar en SUNAT/RENIEC">Buscar</button>
                        </div>
                        <small id="documentoEstado" style="display:block;margin-top:4px;color:#666;"></small>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label for="nombres">Nombres / Razón Social <span>*</span></label>
                        <input type="text" id="nombres" name="nombres" required maxlength="255"
                               placeholder="Juan Pérez García o Empresa SAC">
                    </div>
                    <div class="form-group">
                        <label for="telefono">Teléfono</label>
                        <input type="text" id="telefono" name="telefono" maxlength="50" placeholder="987654321">
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" maxlength="150" placeholder="cliente@ejemplo.com">
                    </div>
                </div>

                <div class="form-group" style="margin-top:5px;">
                    <label for="nombre_empresa">Nombre de Empresa <span class="lbl-opcional">(opcional)</span></label>
                    <input type="text" id="nombre_empresa" name="nombre_empresa" maxlength="255" placeholder="Ej: Empresa SAC">
                </div>

                <div class="form-group" style="margin-top:15px;">
                    <label for="fecha_cumpleanos">Fecha de Cumpleaños <span class="lbl-opcional">(opcional)</span></label>
                    <input type="date" id="fecha_cumpleanos" name="fecha_cumpleanos">
                </div>

                <div class="form-group" style="margin-top:15px;">
                    <label class="seccion-direccion">📍 DIRECCIÓN</label>
                </div>

                <div class="form-group">
                    <label for="direccion">Calle / Av. / Jr.</label>
                    <textarea id="direccion" name="direccion" placeholder="Ej: Av. Los Incas 123..." style="height:70px;"></textarea>
                </div>

                <div class="form-grid" style="margin-top:15px;margin-bottom:0;">
                    <div class="form-group">
                        <label for="distrito">Distrito</label>
                        <input type="text" id="distrito" name="distrito" maxlength="100" placeholder="Ej: Miraflores">
                    </div>
                    <div class="form-group">
                        <label for="provincia">Provincia</label>
                        <input type="text" id="provincia" name="provincia" maxlength="100" placeholder="Ej: Lima">
                    </div>
                    <div class="form-group">
                        <label for="departamento">Departamento</label>
                        <input type="text" id="departamento" name="departamento" maxlength="100" placeholder="Ej: Lima">
                    </div>
                </div>

                <div style="display:flex;gap:15px;margin-top:35px;">
                    <button type="submit" class="btn btn-success" style="flex:1;justify-content:center;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Guardar</span>
                    </button>
                    <button type="button" class="btn btn-secondary" data-cerrar="modalCliente" style="flex:.5;justify-content:center;">
                        <span>Cancelar</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Importación desde Excel ── --}}
<x-modal id="modalImportar" titulo="Importar clientes desde Excel" :oscuro="true">
    <form method="POST" action="{{ route('admin.clientes.importar') }}" enctype="multipart/form-data">
        @csrf
        <div class="drop-zone">
            <p style="margin-bottom:12px;color:#666;">
                Sube un archivo <strong>.xlsx</strong>. Se omitirán los documentos ya registrados.
            </p>
            <input type="file" name="archivo" accept=".xlsx" required>
        </div>

        <div class="header-btns" style="justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalImportar">Cancelar</button>
            <button type="submit" class="btn btn-primary">Importar</button>
        </div>
    </form>
</x-modal>
{{-- ── Compras y cobranzas del cliente ──────────────────────────────── --}}
<x-modal id="modalMovimientos" titulo="Historial de compras de un cliente">
    <div class="mov-buscador">
        <div class="mov-buscador-campo mov-buscador-cliente">
            <label for="movBuscarCliente">Cliente</label>
            <input type="text" id="movBuscarCliente" placeholder="Escribe nombre, RUC o DNI…" autocomplete="off">
            <div id="movSugerencias" class="mov-sugerencias"></div>
        </div>
        <div class="mov-buscador-campo">
            <label for="movMes">Mes</label>
            <input type="month" id="movMes">
        </div>
        <div class="mov-buscador-campo">
            <label for="movDesde">Desde</label>
            <input type="date" id="movDesde">
        </div>
        <div class="mov-buscador-campo">
            <label for="movHasta">Hasta</label>
            <input type="date" id="movHasta">
        </div>
        <button type="button" class="btn btn-primary btn-sm" id="movAplicar">Ver historial</button>
    </div>
    <div id="movContenido">
        <p class="cli-cargando">Busca un cliente por nombre, RUC o DNI para ver su historial de compras.</p>
    </div>
</x-modal>

@endsection

@push('scripts')
<script>
// ── Panel de compras por cliente ─────────────────────────────────────────
const PERIODO = @json(array_filter($periodo));
const movCaja         = document.getElementById('movContenido');
const movBuscarInput  = document.getElementById('movBuscarCliente');
const movSugerencias  = document.getElementById('movSugerencias');
const movMes          = document.getElementById('movMes');
const movDesde        = document.getElementById('movDesde');
const movHasta        = document.getElementById('movHasta');
let movClienteId      = null;
let movBuscarTimer    = null;

const soles = (v) => 'S/ ' + Number(v).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function tabla(titulo, cabeceras, filas, vacio) {
    if (filas.length === 0) {
        return `<div class="mov-bloque"><h4>${titulo}</h4><p class="cli-cargando">${vacio}</p></div>`;
    }

    return `<div class="mov-bloque">
        <h4>${titulo} <span>${filas.length}</span></h4>
        <div class="mov-scroll"><table class="mov-tabla">
            <thead><tr>${cabeceras.map((c) => `<th>${c}</th>`).join('')}</tr></thead>
            <tbody>${filas.join('')}</tbody>
        </table></div>
    </div>`;
}

// ── Autocompletado de cliente ──────────────────────────────────────────
function cerrarSugerencias() {
    movSugerencias.innerHTML = '';
    movSugerencias.classList.remove('activa');
}

async function buscarClientes(termino) {
    const url = new URL('{{ route('admin.clientes.buscar') }}', window.location.origin);
    url.searchParams.set('q', termino);

    const r = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!r.ok) return [];

    return r.json();
}

movBuscarInput.addEventListener('input', () => {
    movClienteId = null;
    clearTimeout(movBuscarTimer);

    const termino = movBuscarInput.value.trim();
    if (termino.length < 2) { cerrarSugerencias(); return; }

    movBuscarTimer = setTimeout(async () => {
        const resultados = await buscarClientes(termino);

        if (resultados.length === 0) {
            movSugerencias.innerHTML = '<div class="mov-sug-vacio">Sin coincidencias</div>';
            movSugerencias.classList.add('activa');
            return;
        }

        movSugerencias.innerHTML = resultados.map((c) => `
            <button type="button" class="mov-sug-item" data-id="${c.id}" data-nombre="${c.nombre_empresa || c.nombres}">
                <b>${c.nombre_empresa || c.nombres}</b>
                <span>${c.tipo_documento} ${c.numero_documento}${c.nombre_empresa ? ' · ' + c.nombres : ''}</span>
            </button>`).join('');
        movSugerencias.classList.add('activa');
    }, 250);
});

movSugerencias.addEventListener('click', (e) => {
    const item = e.target.closest('.mov-sug-item');
    if (!item) return;

    movClienteId = item.dataset.id;
    movBuscarInput.value = item.dataset.nombre;
    cerrarSugerencias();
    verMovimientos(movClienteId);
});

document.addEventListener('click', (e) => {
    if (!e.target.closest('.mov-buscador-cliente')) cerrarSugerencias();
});

document.getElementById('movAplicar').addEventListener('click', () => {
    if (!movClienteId) { movBuscarInput.focus(); return; }
    verMovimientos(movClienteId);
});

// ── Carga y pinta el historial ───────────────────────────────────────────
async function verMovimientos(id) {
    movClienteId = id;
    movCaja.innerHTML = '<p class="cli-cargando">Cargando…</p>';

    const url = new URL('{{ url('admin/clientes') }}/' + id + '/movimientos', window.location.origin);
    if (movMes.value) url.searchParams.set('mes', movMes.value);
    if (movDesde.value) url.searchParams.set('desde', movDesde.value);
    if (movHasta.value) url.searchParams.set('hasta', movHasta.value);

    try {
        const r = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!r.ok) throw new Error('No se pudo cargar');

        const d = await r.json();
        movBuscarInput.value = d.cliente;

        const ventas = d.ventas.map((v) => `<tr>
            <td>${v.fecha ?? '—'}</td><td><b>${v.documento}</b><div class="cli-sub">${v.tipo}</div></td>
            <td class="mov-num">${soles(v.base)}</td><td class="mov-num">${soles(v.igv)}</td>
            <td class="mov-num mov-total">${soles(v.total)}</td></tr>`);

        const cobranzas = d.cobranzas.map((c) => `<tr>
            <td>${c.fecha ?? '—'}</td><td><b>${c.documento}</b><div class="cli-sub">vence ${c.vence ?? '—'}</div></td>
            <td class="mov-num">${soles(c.total)}</td><td class="mov-num">${soles(c.pagado)}</td>
            <td class="mov-num ${c.pendiente > 0 ? 'mov-debe' : ''}">${soles(c.pendiente)}</td>
            <td><span class="mov-estado mov-${c.estado}">${c.estado}</span></td></tr>`);

        movCaja.innerHTML = `
            <div class="mov-cabecera">
                <b>${d.cliente}</b>
                <span>${d.documento}</span>
            </div>
            <div class="mov-resumen">
                <div><span>Comprado</span><b>${soles(d.resumen.comprado)}</b><i>${d.resumen.nVentas} comprobante(s)</i></div>
                <div><span>Pendiente de cobro</span><b class="${d.resumen.pendiente > 0 ? 'mov-debe' : ''}">${soles(d.resumen.pendiente)}</b><i>${d.resumen.nCobranzas} documento(s)</i></div>
            </div>
            ${tabla('Comprobantes de venta', ['Fecha', 'Documento', 'Base', 'IGV', 'Total'], ventas, 'Sin comprobantes en este periodo.')}
            ${tabla('Cobranzas', ['Emisión', 'Documento', 'Total', 'Pagado', 'Pendiente', 'Estado'], cobranzas, 'Sin cobranzas en este periodo.')}`;
    } catch (e) {
        movCaja.innerHTML = `<p class="cli-cargando">${e.message}</p>`;
    }
}

// Abrir desde el botón general del encabezado: modal en blanco, listo para buscar.
document.getElementById('btnHistorialCliente').addEventListener('click', () => {
    movClienteId = null;
    movBuscarInput.value = '';
    movMes.value   = PERIODO.mes   ?? '';
    movDesde.value = PERIODO.desde ?? '';
    movHasta.value = PERIODO.hasta ?? '';
    cerrarSugerencias();
    movCaja.innerHTML = '<p class="cli-cargando">Busca un cliente por nombre, RUC o DNI para ver su historial de compras.</p>';

    window.abrirModal('modalMovimientos');
    movBuscarInput.focus();
});

// Abrir desde una fila de la tabla: precarga cliente y periodo actual de la lista.
document.addEventListener('click', (e) => {
    const boton = e.target.closest('[data-movimientos]');
    if (!boton) return;

    movBuscarInput.value = boton.dataset.nombre ?? '';
    movMes.value   = PERIODO.mes   ?? '';
    movDesde.value = PERIODO.desde ?? '';
    movHasta.value = PERIODO.hasta ?? '';
    cerrarSugerencias();

    window.abrirModal('modalMovimientos');
    verMovimientos(boton.dataset.movimientos);
});
</script>
@endpush

@push('scripts')
<script>
const URL_CLIENTES = '{{ url('admin/clientes') }}';
const formCliente  = document.getElementById('formCliente');

// ── Alta ─────────────────────────────────────────────────────────────────
document.getElementById('btnNuevoCliente').addEventListener('click', () => {
    formCliente.reset();
    formCliente.action = URL_CLIENTES;
    formCliente.querySelector('[name="_method"]')?.remove();

    document.getElementById('tituloModal').textContent = 'Nuevo Cliente';
    document.getElementById('cliente_id').value        = '';

    abrirModal('modalCliente');
});

// ── Edición ──────────────────────────────────────────────────────────────
document.querySelectorAll('.btn-editar').forEach((boton) => {
    boton.addEventListener('click', () => {
        const d = boton.dataset;

        formCliente.action = URL_CLIENTES + '/' + d.cliente;
        if (!formCliente.querySelector('[name="_method"]')) {
            const metodo = document.createElement('input');
            metodo.type = 'hidden';
            metodo.name = '_method';
            metodo.value = 'PUT';
            formCliente.appendChild(metodo);
        }

        document.getElementById('tituloModal').textContent   = 'Editar Cliente';
        document.getElementById('cliente_id').value          = d.cliente;
        document.getElementById('tipo_documento').value      = d.tipoDocumento || 'DNI';
        document.getElementById('numero_documento').value    = d.numeroDocumento || '';
        document.getElementById('nombres').value             = d.nombres || '';
        document.getElementById('nombre_empresa').value      = d.nombreEmpresa || '';
        document.getElementById('telefono').value            = d.telefono || '';
        document.getElementById('email').value               = d.email || '';
        document.getElementById('fecha_cumpleanos').value    = d.cumpleanos || '';
        document.getElementById('direccion').value           = d.direccion || '';
        document.getElementById('distrito').value            = d.distrito || '';
        document.getElementById('provincia').value           = d.provincia || '';
        document.getElementById('departamento').value        = d.departamento || '';

        abrirModal('modalCliente');
    });
});

// ── Consulta de RUC/DNI (SUNAT/RENIEC) ──────────────────────────────────
const documentoEstado = document.getElementById('documentoEstado');

document.getElementById('btnBuscarDocumento').addEventListener('click', async () => {
    const tipo    = document.getElementById('tipo_documento').value;
    const numero  = document.getElementById('numero_documento').value.trim();

    if (tipo !== 'DNI' && tipo !== 'RUC') {
        documentoEstado.textContent = 'La búsqueda solo está disponible para DNI o RUC';
        return;
    }

    const patron = tipo === 'DNI' ? /^\d{8}$/ : /^\d{11}$/;

    if (!patron.test(numero)) {
        documentoEstado.textContent = tipo === 'DNI' ? 'El DNI debe tener 8 dígitos' : 'El RUC debe tener 11 dígitos';
        return;
    }

    documentoEstado.textContent = 'Buscando...';

    try {
        const r = await fetch(`{{ url('admin/documentos/buscar') }}/${tipo.toLowerCase()}/${numero}`, { headers: { Accept: 'application/json' } });
        const j = await r.json();

        if (!j.ok) {
            documentoEstado.textContent = j.error || 'No se encontró el documento';
            return;
        }

        if (tipo === 'DNI') {
            document.getElementById('nombres').value = j.datos.nombre_completo || '';
        } else {
            document.getElementById('nombres').value        = j.datos.razon_social || '';
            document.getElementById('direccion').value      = j.datos.direccion || '';
            document.getElementById('distrito').value       = j.datos.distrito || '';
            document.getElementById('provincia').value      = j.datos.provincia || '';
            document.getElementById('departamento').value   = j.datos.departamento || '';
        }

        documentoEstado.textContent = j.origen === 'local' ? 'Datos de un registro existente' : 'Datos obtenidos de ' + (tipo === 'DNI' ? 'RENIEC' : 'SUNAT');
    } catch (e) {
        documentoEstado.textContent = 'Servicio de consulta no disponible';
    }
});
</script>
@endpush
