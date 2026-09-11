@extends('layouts.admin')

@section('title', 'Órdenes de Compra')
@section('crumb', 'Vista general')

@push('styles')
    @vite(['resources/css/modules/ordenes-compra.css'])
@endpush

@section('content')

<div class="oc-wrapper">

    {{-- Botón flotante, siempre visible sobre el listado --}}
    <a href="{{ route('admin.ordenes-compra.create') }}" class="fab-nueva-oc">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Nueva Orden de Compra
    </a>

    {{-- ══ Cabecera ══ --}}
    <div class="oc-header">
        <div class="oc-header-left">
            <h2>🛒 Órdenes de Compra</h2>
            <p>Gestión de compras, costos, rentabilidad y análisis financiero</p>
        </div>
        <div class="oc-header-right">
            <button type="button" class="btn-oc btn-outline-oc btn-excel-menu" data-ids=""
                    title="Descargar todas las órdenes en PDF o Excel">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                📄 Descargar todo
            </button>
        </div>
    </div>

    {{-- ══ Indicadores ══ --}}
    <div class="oc-kpis">
        <div class="kpi-card">
            <div class="kpi-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                </svg>
            </div>
            <div class="kpi-label">Órdenes del Mes</div>
            <div class="kpi-val">{{ number_format($nOrdenes) }}</div>
            <div class="kpi-sub">{{ $nOrdenes > 0 ? $porDia->count().' día(s) con registros' : 'Sin datos aún' }}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                </svg>
            </div>
            <div class="kpi-label">Costo Total</div>
            <div class="kpi-val">S/ {{ number_format($costoSoles, 2) }}</div>
            <div class="kpi-sub">{{ $nOrdenes > 0 ? $nOrdenes.' orden(es) del mes' : 'Sin datos aún' }}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>
                </svg>
            </div>
            <div class="kpi-label">Margen Bruto</div>
            <div class="kpi-val">S/ {{ number_format($margenBruto, 2) }}</div>
            <div class="kpi-sub">
                {{ $ventasSoles > 0 ? number_format($margenBruto / $ventasSoles * 100, 1) : '0' }}% sobre ventas
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
            </div>
            <div class="kpi-label">Gastos Operativos</div>
            <div class="kpi-val">S/ {{ number_format($gastosOp, 2) }}</div>
            <div class="kpi-sub">{{ $gastosOp > 0 ? 'Gasto unitario acumulado' : 'Sin registros' }}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                </svg>
            </div>
            <div class="kpi-label">Rentabilidad Neta</div>
            <div class="kpi-val">S/ {{ number_format($rentNeta, 2) }}</div>
            <div class="kpi-sub">{{ $rentNeta != 0 ? 'Margen menos gastos' : 'Sin datos aún' }}</div>
        </div>
    </div>

    {{-- ══ Pestañas ══ --}}
    <div class="oc-tabs">
        <button type="button" class="oc-tab active" data-tab="tab-lista">📋 Lista de Órdenes</button>
        <button type="button" class="oc-tab oc-tab-pedidos" data-tab="tab-pedidos">📦 Pedidos de Clientes</button>
    </div>

    {{-- ══ Lista de órdenes ══ --}}
    <div id="tab-lista">
        <form method="GET" class="oc-filters" id="filtrosOc">
            <input type="search" class="search-oc" name="q" value="{{ $busqueda }}"
                   placeholder="Buscar orden, proveedor, producto...">

            <select class="filter-select" name="estado">
                <option value="">Todos los estados</option>
                @foreach ($estados as $est)
                    <option value="{{ $est }}" @selected(Str::lower($estadoSel) === Str::lower($est))>{{ $est }}</option>
                @endforeach
            </select>

            <select class="filter-select" name="mes">
                <option value="">Todos los meses</option>
                @foreach ($meses as $num => $nombre)
                    <option value="{{ $num }}" @selected($mesSel === $num)>{{ $nombre }}</option>
                @endforeach
            </select>

            @if ($busqueda !== '' || $estadoSel !== '' || $mesSel > 0)
                <a href="{{ route('admin.ordenes-compra.index') }}" class="btn-limpiar-oc">✕ Limpiar</a>
            @endif
        </form>

        <div class="oc-card">
            <div class="oc-card-header">
                <div class="oc-card-title"><span class="dot"></span>Órdenes de Compra Registradas</div>
                <span class="oc-contador">{{ $nOrdenes }} {{ $nOrdenes === 1 ? 'orden' : 'órdenes' }}</span>
            </div>

            @forelse ($porDia as $fecha => $grupo)
                @php
                    $dia = \Carbon\Carbon::createFromFormat('Y-m-d', $fecha);
                    $grupoId = 'dia-'.str_replace('-', '', $fecha);
                    $totalUsdDia = $grupo->sum('total_usd');
                    $totalSolesDia = $grupo->sum('total_soles');
                    $nDia = $grupo->count();
                    $idsDia = $grupo->pluck('id')->implode(',');
                    // El catálogo Kendall (en dólares) ya no existe: si ninguna orden del
                    // día es una orden histórica real en USD, no hay nada que convertir.
                    $diaSolesNativo = $grupo->every(fn ($o) => abs((float) $o->tc - 1.0) < 0.0001);
                @endphp

                <div class="dia-grupo">
                    <div class="dia-header" data-grupo="{{ $grupoId }}">
                        <div class="dia-header-left">
                            <div class="dia-fecha-badge {{ $dia->isToday() ? 'hoy' : ($dia->isYesterday() ? 'ayer' : '') }}">
                                @if ($dia->isToday()) HOY
                                @elseif ($dia->isYesterday()) AYER
                                @else {{ $dia->format('d').' '.Str::upper(Str::substr($dia->translatedFormat('F'), 0, 3)) }}
                                @endif
                            </div>
                            <div>
                                <div class="dia-label">{{ $dia->translatedFormat('l, d \d\e F Y') }}</div>
                                <div class="dia-sublabel">{{ $nDia }} orden{{ $nDia > 1 ? 'es' : '' }} registrada{{ $nDia > 1 ? 's' : '' }}</div>
                            </div>
                        </div>

                        <div class="dia-stats">
                            @unless ($diaSolesNativo)
                                <div class="dia-stat">
                                    <div class="dia-stat-val">$ {{ number_format($totalUsdDia, 2) }}</div>
                                    <div class="dia-stat-lbl">Total USD</div>
                                </div>
                            @endunless
                            <div class="dia-stat">
                                <div class="dia-stat-val soles">S/ {{ number_format($totalSolesDia, 2) }}</div>
                                <div class="dia-stat-lbl">Total S/</div>
                            </div>
                            <button type="button" class="btn-excel-dia btn-excel-menu" data-sin-toggle
                                    data-ids="{{ $idsDia }}"
                                    title="Descargar las {{ $nDia }} orden{{ $nDia > 1 ? 'es' : '' }} de este día">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                    <polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                                </svg>
                                📄 Descargar ({{ $nDia }})
                            </button>
                        </div>

                        <div class="dia-toggle">▼</div>
                    </div>

                    <div class="dia-body" id="{{ $grupoId }}">
                        <table class="oc-table">
                            <thead>
                                <tr>
                                    <th class="oc-th-check">
                                        <input type="checkbox" class="oc-check-dia" data-grupo="{{ $grupoId }}"
                                               title="Seleccionar las {{ $nDia }} órdenes de este día">
                                    </th>
                                    <th># Orden</th><th>Cliente</th><th>SR (ES):</th><th>Productos</th>
                                    <th>Total Compra</th><th>Total S/</th><th>P.Venta S/</th>
                                    <th>Rentabilidad</th><th>N° Factura</th><th>N° Guía</th><th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach ($grupo as $orden)
                                @php
                                    $pventa = (float) $orden->precio_venta;
                                    // Rentabilidad sobre el precio de venta, igual que en el original.
                                    $rent = $pventa > 0 ? ($pventa - (float) $orden->total_soles) / $pventa * 100 : 0;
                                    // El catálogo Kendall (en dólares) ya no existe: solo las órdenes
                                    // históricas reales muestran esta columna en USD.
                                    $filaSolesNativo = abs((float) $orden->tc - 1.0) < 0.0001;
                                @endphp
                                <tr data-fila="{{ $orden->id }}">
                                    <td class="oc-td-check">
                                        <input type="checkbox" class="oc-check" value="{{ $orden->id }}"
                                               data-grupo="{{ $grupoId }}" data-numero="{{ $orden->numero_orden }}"
                                               title="Incluir esta orden en la descarga">
                                    </td>
                                    <td><strong class="oc-num-orden">{{ $orden->numero_orden }}</strong></td>
                                    <td>
                                        @if ($orden->cliente_ref)
                                            <span class="cliente-tag">{{ $orden->cliente_ref }}</span>
                                        @else
                                            <span class="cliente-vacio">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $orden->proveedor }}</td>
                                    <td>{{ count($orden->productos ?? []) }} prod.</td>
                                    <td>{{ $filaSolesNativo ? 'S/' : '$' }} {{ number_format($orden->total_usd, 2) }}</td>
                                    <td>S/ {{ number_format($orden->total_soles, 2) }}</td>
                                    <td>S/ {{ number_format($pventa, 2) }}</td>
                                    <td>
                                        <span class="rent-pill {{ $rent >= 0 ? 'rent-pill-up' : 'rent-pill-down' }}">
                                            {{ $rent >= 0 ? '▲' : '▼' }} {{ number_format($rent, 1) }}%
                                        </span>
                                    </td>

                                    @foreach (['nro_factura' => '🧾', 'nro_guia' => '🚚'] as $campo => $icono)
                                        <td>
                                            <div class="factura-cell" data-orden="{{ $orden->id }}" data-campo="{{ $campo }}">
                                                <div class="doc-view">
                                                    @if ($orden->{$campo})
                                                        <span class="factura-badge-ok">{{ $icono }} {{ $orden->{$campo} }}</span>
                                                    @else
                                                        <span class="factura-badge-pendiente">⏳ Pendiente</span>
                                                    @endif
                                                    <button type="button" class="btn-factura-edit" title="Editar">✏️</button>
                                                </div>
                                                <div class="doc-edit">
                                                    <input type="text" class="factura-edit-input"
                                                           value="{{ $orden->{$campo} }}"
                                                           placeholder="{{ $campo === 'nro_factura' ? 'F001-000363' : 'T001-000123' }}">
                                                    <button type="button" class="btn-factura-ok" title="Guardar">✓</button>
                                                    <button type="button" class="btn-factura-cancel" title="Cancelar">✕</button>
                                                </div>
                                            </div>
                                        </td>
                                    @endforeach

                                    <td>
                                        <div class="oc-acciones">
                                            <button type="button" class="btn-excel-oc btn-excel-menu"
                                                    data-ids="{{ $orden->id }}" title="Descargar PDF o Excel">📄 Descargar ▾</button>
                                            <button type="button" class="btn-email-oc btn-enviar-oc"
                                                    data-orden="{{ $orden->id }}"
                                                    data-numero="{{ $orden->numero_orden }}"
                                                    data-proveedor="{{ $orden->proveedor }}">✉ Email</button>
                                            <form method="POST" action="{{ route('admin.ordenes-compra.destroy', $orden) }}"
                                                  class="form-eliminar-oc"
                                                  data-confirmar="Se eliminará la orden {{ $orden->numero_orden }}. Esta acción no se puede deshacer.">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn-del-oc" title="Eliminar">🗑️</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="dia-divider"></div>
                </div>
            @empty
                <div class="oc-empty">
                    <div class="oc-empty-icon">🛒</div>
                    <h3>Sin órdenes de compra</h3>
                    <p>
                        @if ($busqueda !== '' || $estadoSel !== '' || $mesSel > 0)
                            Ninguna orden coincide con el filtro seleccionado.
                        @else
                            Aún no hay órdenes registradas.<br>Haz clic en "Nueva Orden" para comenzar.
                        @endif
                    </p>
                    <a href="{{ route('admin.ordenes-compra.create') }}" class="btn-oc btn-primary-oc">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Nueva Orden de Compra
                    </a>
                </div>
            @endforelse
        </div>
    </div>

    {{-- ══ Pedidos de clientes ══ --}}
    <div id="tab-pedidos" style="display:none;">
        <div class="oc-card">
            <div class="oc-card-body">
                <div class="ped-header">
                    <div class="ped-title">
                        📦 Pedidos de Clientes
                        <span class="ped-title-badge">Clientes → {{ config('rentaltech.empresa.razon_social') }}</span>
                    </div>
                </div>

                <div class="ped-table-wrap">
                    @if ($pedidos->isEmpty())
                        <div class="ped-empty">
                            <div class="ped-empty-icon">📦</div>
                            <h3>Sin pedidos registrados</h3>
                            <p>Los pedidos que los clientes hacen a {{ config('rentaltech.empresa.razon_social') }} aparecerán aquí.</p>
                        </div>
                    @else
                        <table class="ped-table">
                            <thead>
                                <tr>
                                    <th>Fecha</th><th>Cliente</th><th>RUC</th><th>Destino</th>
                                    <th>Empresa Transporte</th><th>Descripción</th>
                                    <th>Total (S/)</th><th>Estado</th><th>Archivo</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach ($pedidos as $pedido)
                                @php
                                    $clase = match (Str::lower($pedido->estado)) {
                                        'en proceso' => 'ped-badge-proceso',
                                        'entregado'  => 'ped-badge-entregado',
                                        'cancelado'  => 'ped-badge-cancelado',
                                        default      => 'ped-badge-pendiente',
                                    };
                                @endphp
                                <tr>
                                    <td>{{ $pedido->fecha?->format('d/m/Y') }}</td>
                                    <td><strong>{{ $pedido->cliente_nombre }}</strong></td>
                                    <td>{{ $pedido->ruc ?: '—' }}</td>
                                    <td>{{ $pedido->destino ?: '—' }}</td>
                                    <td>{{ $pedido->empresa_transporte ?: '—' }}</td>
                                    <td>{{ Str::limit($pedido->productos, 60) ?: '—' }}</td>
                                    <td>S/ {{ number_format($pedido->total_soles, 2) }}</td>
                                    <td><span class="ped-badge {{ $clase }}">{{ $pedido->estado }}</span></td>
                                    <td>{{ $pedido->archivo_pedido ?: '—' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ══ Barra de selección: varias órdenes en un mismo libro de Excel ══ --}}
    <div class="oc-seleccion" id="oc-seleccion">
        <div class="oc-seleccion-info">
            <strong id="oc-sel-count">0 órdenes seleccionadas</strong>
            <small id="oc-sel-det">En PDF cada orden ocupa su propia página; en Excel, su propia hoja.</small>
        </div>
        <button type="button" class="btn-excel-dia btn-excel-menu" id="oc-sel-excel" data-ids="">
            📄 Descargar (0)
        </button>
        <button type="button" class="oc-sel-limpiar" id="oc-sel-limpiar">Limpiar</button>
    </div>
</div>

<x-modal id="modalEnviarOc" titulo="Enviar orden por correo">
    <form id="formEnviarOc">
        <p class="prod-mudo" id="ocEnviarDetalle" style="margin:0 0 14px;font-size:13.5px;"></p>
        <div class="form-group">
            <label for="ocEmailDestino">Correo del destinatario</label>
            <input type="email" id="ocEmailDestino" required placeholder="proveedor@ejemplo.com">
        </div>
        <div class="header-btns" style="justify-content:flex-end;margin-top:16px;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalEnviarOc">Cancelar</button>
            <button type="submit" class="btn btn-primary">Enviar</button>
        </div>
    </form>
</x-modal>
@endsection

@push('scripts')
<script>
const CSRF_OC = document.querySelector('meta[name="csrf-token"]').content;
const URL_OC  = '{{ url('admin/ordenes-compra') }}';

// ── Pestañas ─────────────────────────────────────────────────────────────
document.querySelectorAll('.oc-tab').forEach((tab) => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.oc-tab').forEach((t) => t.classList.remove('active'));
        tab.classList.add('active');

        document.getElementById('tab-lista').style.display   = tab.dataset.tab === 'tab-lista'   ? '' : 'none';
        document.getElementById('tab-pedidos').style.display = tab.dataset.tab === 'tab-pedidos' ? '' : 'none';
    });
});

// ── Filtros: se aplican al cambiar; el buscador espera a que se deje de escribir
const filtrosOc = document.getElementById('filtrosOc');

filtrosOc.querySelectorAll('select').forEach((s) => s.addEventListener('change', () => filtrosOc.submit()));

let esperaOc;
filtrosOc.querySelector('.search-oc').addEventListener('input', () => {
    clearTimeout(esperaOc);
    esperaOc = setTimeout(() => filtrosOc.submit(), 450);
});

// ── Plegar y desplegar cada día ──────────────────────────────────────────
document.querySelectorAll('.dia-header').forEach((cabecera) => {
    const cuerpo = document.getElementById(cabecera.dataset.grupo);
    const flecha = cabecera.querySelector('.dia-toggle');

    cuerpo.style.maxHeight = cuerpo.scrollHeight + 'px';

    cabecera.addEventListener('click', (evento) => {
        // El botón de Excel vive dentro de la cabecera y no debe plegarla.
        if (evento.target.closest('[data-sin-toggle]')) { return; }

        if (cuerpo.classList.contains('collapsed')) {
            cuerpo.classList.remove('collapsed');
            flecha.classList.remove('collapsed');
            cuerpo.style.maxHeight = cuerpo.scrollHeight + 'px';
        } else {
            cuerpo.style.maxHeight = cuerpo.scrollHeight + 'px';
            requestAnimationFrame(() => {
                cuerpo.style.maxHeight = '0';
                cuerpo.classList.add('collapsed');
                flecha.classList.add('collapsed');
            });
        }
    });
});

// ── Factura y guía editables sobre la propia tabla ───────────────────────
document.querySelectorAll('.factura-cell').forEach((celda) => {
    const vista  = celda.querySelector('.doc-view');
    const edicion = celda.querySelector('.doc-edit');
    const campo  = celda.querySelector('.factura-edit-input');
    const icono  = celda.dataset.campo === 'nro_factura' ? '🧾' : '🚚';

    const abrir = () => {
        vista.style.display = 'none';
        edicion.style.display = 'flex';
        campo.focus();
        campo.select();
    };

    const cerrar = () => {
        edicion.style.display = 'none';
        vista.style.display = 'flex';
    };

    const guardar = async () => {
        const valor = campo.value.trim();

        try {
            const respuesta = await fetch(URL_OC + '/' + celda.dataset.orden + '/documento', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_OC,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ campo: celda.dataset.campo, valor: valor }),
            });

            const datos = await respuesta.json();
            if (!datos.ok) { throw new Error('respuesta inválida'); }

            const insignia = vista.querySelector('span');
            if (datos.valor) {
                insignia.className = 'factura-badge-ok';
                insignia.textContent = icono + ' ' + datos.valor;
            } else {
                insignia.className = 'factura-badge-pendiente';
                insignia.textContent = '⏳ Pendiente';
            }
            cerrar();
        } catch (e) {
            window.alert('No se pudo guardar: ' + e.message);
        }
    };

    celda.querySelector('.btn-factura-edit').addEventListener('click', abrir);
    celda.querySelector('.btn-factura-ok').addEventListener('click', guardar);
    celda.querySelector('.btn-factura-cancel').addEventListener('click', cerrar);

    campo.addEventListener('keydown', (e) => {
        if (e.key === 'Enter')  { e.preventDefault(); guardar(); }
        if (e.key === 'Escape') { cerrar(); }
    });
});

// ── Elegir el formato de descarga ────────────────────────────────────────
// Cada botón abre el mismo menú; `data-ids` decide qué órdenes entran
// (vacío = todas). El PDF es la opción principal; el Excel (proveedor y
// secretaria) se mantiene disponible para el archivo interno de costos.
const URL_EXCEL = '{{ route('admin.ordenes-compra.excel') }}';
const URL_PDF   = '{{ route('admin.ordenes-compra.pdf') }}';

function cerrarMenuExcel() {
    document.getElementById('menu-excel-oc')?.remove();
}

function abrirMenuExcel(ids, origen) {
    cerrarMenuExcel();

    const menu = document.createElement('div');
    menu.id = 'menu-excel-oc';
    menu.className = 'menu-excel';
    menu.innerHTML =
        '<div class="menu-excel-titulo">Descargar orden</div>' +
        '<button type="button" data-formato="pdf">' +
            '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#a12b2b" stroke-width="2.5">' +
            '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>' +
            '<span><strong>PDF de la orden</strong><small>Una página por orden, para el proveedor</small></span>' +
        '</button>' +
        '<div class="menu-excel-titulo" style="margin-top:6px;">Otros formatos</div>' +
        '<button type="button" data-formato="excel-proveedor">' +
            '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#217346" stroke-width="2.5">' +
            '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>' +
            '<span><strong>Excel para Proveedor</strong><small>Formato oficial RT-PV-F-01</small></span>' +
        '</button>' +
        '<button type="button" data-formato="excel-secretaria">' +
            '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#5b21b6" stroke-width="2.5">' +
            '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>' +
            '<line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>' +
            '<span><strong>Excel para Secretaria</strong><small>Formato interno con costos y márgenes</small></span>' +
        '</button>';

    document.body.appendChild(menu);

    menu.querySelectorAll('button').forEach((opcion) => {
        opcion.addEventListener('click', () => {
            cerrarMenuExcel();

            if (opcion.dataset.formato === 'pdf') {
                window.location.href = URL_PDF + (ids ? '?ids=' + encodeURIComponent(ids) : '');
                return;
            }

            const tipo = opcion.dataset.formato === 'excel-secretaria' ? 'secretaria' : 'proveedor';
            window.location.href = URL_EXCEL + '?tipo=' + tipo + (ids ? '&ids=' + encodeURIComponent(ids) : '');
        });
    });

    // Se coloca bajo el botón, sin salirse de la ventana.
    const caja = origen.getBoundingClientRect();
    let arriba = caja.bottom + 6;
    let izquierda = caja.left;

    if (izquierda + 240 > window.innerWidth) { izquierda = window.innerWidth - 246; }
    if (arriba + 140 > window.innerHeight)   { arriba = caja.top - 146; }

    menu.style.top  = arriba + 'px';
    menu.style.left = izquierda + 'px';

    setTimeout(() => document.addEventListener('click', cerrarMenuExcel, { once: true }), 10);
}

document.querySelectorAll('.btn-excel-menu').forEach((boton) => {
    boton.addEventListener('click', (evento) => {
        evento.stopPropagation();

        if (!boton.dataset.ids && boton.id === 'oc-sel-excel') { return; }

        abrirMenuExcel(boton.dataset.ids || '', boton);
    });
});

// ── Selección libre de órdenes ───────────────────────────────────────────
// El generador ya arma una hoja por orden; aquí solo se junta la lista de
// ids para pedir varias de una sola vez, sin importar de qué día sean.
const seleccionOc = new Set();
const barraSel    = document.getElementById('oc-seleccion');
const btnSelExcel = document.getElementById('oc-sel-excel');

function pintarSeleccion(aviso) {
    const total = seleccionOc.size;
    const ids   = [...seleccionOc].join(',');

    btnSelExcel.dataset.ids   = ids;
    btnSelExcel.textContent   = '📄 Descargar (' + total + ')';
    btnSelExcel.disabled      = total === 0;

    document.getElementById('oc-sel-count').textContent =
        total + ' orden' + (total === 1 ? '' : 'es') + ' seleccionada' + (total === 1 ? '' : 's');

    if (aviso) { document.getElementById('oc-sel-det').textContent = aviso; }

    barraSel.classList.toggle('visible', total > 0);
    document.body.classList.toggle('oc-seleccionando', total > 0);

    // Marca visual de la fila y estado de las casillas "todo el día".
    document.querySelectorAll('.oc-check').forEach((casilla) => {
        casilla.closest('tr').classList.toggle('fila-elegida', casilla.checked);
    });

    document.querySelectorAll('.oc-check-dia').forEach((maestra) => {
        const hijas   = [...document.querySelectorAll('.oc-check[data-grupo="' + maestra.dataset.grupo + '"]')];
        const marcadas = hijas.filter((c) => c.checked).length;

        maestra.checked = marcadas > 0 && marcadas === hijas.length;
        maestra.indeterminate = marcadas > 0 && marcadas < hijas.length;
    });
}

function alternarOrden(casilla) {
    if (casilla.checked) { seleccionOc.add(casilla.value); }
    else { seleccionOc.delete(casilla.value); }
}

document.querySelectorAll('.oc-check').forEach((casilla) => {
    casilla.addEventListener('change', () => {
        alternarOrden(casilla);
        pintarSeleccion('Cada orden entra como una hoja del mismo archivo.');
    });
});

document.querySelectorAll('.oc-check-dia').forEach((maestra) => {
    maestra.addEventListener('click', (evento) => evento.stopPropagation());
    maestra.addEventListener('change', () => {
        document.querySelectorAll('.oc-check[data-grupo="' + maestra.dataset.grupo + '"]').forEach((casilla) => {
            casilla.checked = maestra.checked;
            alternarOrden(casilla);
        });

        pintarSeleccion('Cada orden entra como una hoja del mismo archivo.');
    });
});

document.getElementById('oc-sel-limpiar').addEventListener('click', () => {
    seleccionOc.clear();
    document.querySelectorAll('.oc-check').forEach((casilla) => { casilla.checked = false; });
    pintarSeleccion('Cada orden entra como una hoja del mismo archivo.');
});

pintarSeleccion();

// Las órdenes recién registradas quedan marcadas: si la tanda llevó varias,
// salen todas en el mismo libro, una hoja por orden.
@if ($preseleccion->isNotEmpty())
    window.addEventListener('DOMContentLoaded', () => {
        const recien = @json($preseleccion);
        let primera = null;

        recien.forEach((id) => {
            const casilla = document.querySelector('.oc-check[value="' + id + '"]');

            if (!casilla) { return; }

            casilla.checked = true;
            alternarOrden(casilla);
            primera = primera || casilla;
        });

        if (!primera) { return; }

        pintarSeleccion(recien.length > 1
            ? 'Las ' + recien.length + ' órdenes que acabas de registrar.'
            : 'Marca más órdenes para descargarlas juntas, una por página/hoja.');

        primera.closest('tr').scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
@endif

// ── Envío por correo ─────────────────────────────────────────────────────
let ocEnviarOrdenId = null;

document.querySelectorAll('.btn-enviar-oc').forEach((boton) => {
    boton.addEventListener('click', () => {
        ocEnviarOrdenId = boton.dataset.orden;
        document.getElementById('ocEnviarDetalle').textContent =
            'Orden ' + boton.dataset.numero + ' (' + boton.dataset.proveedor + ')';
        document.getElementById('ocEmailDestino').value = '';
        abrirModal('modalEnviarOc');
        setTimeout(() => document.getElementById('ocEmailDestino').focus(), 50);
    });
});

document.getElementById('formEnviarOc').addEventListener('submit', (e) => {
    e.preventDefault();

    const destino = document.getElementById('ocEmailDestino').value.trim();
    if (!destino || !ocEnviarOrdenId) { return; }

    const formulario = document.createElement('form');
    formulario.method = 'POST';
    formulario.action = URL_OC + '/' + ocEnviarOrdenId + '/enviar';
    formulario.innerHTML =
        '<input type="hidden" name="_token" value="' + CSRF_OC + '">' +
        '<input type="hidden" name="destinatarios[]">';
    formulario.querySelector('input[name="destinatarios[]"]').value = destino;

    document.body.appendChild(formulario);
    formulario.submit();
});
</script>
@endpush
