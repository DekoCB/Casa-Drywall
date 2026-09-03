@extends('layouts.admin')

@section('title', 'Ventas')
@section('crumb', 'Vista general')

@push('styles')
    @vite(['resources/css/modules/ventas.css'])
@endpush

@section('content')

@php
    // Lista ligera para el buscador de clientes del modal.
    $listaClientes = $clientes->map(fn ($c) => [
        'id'  => $c->id,
        'doc' => $c->numero_documento,
        'n'   => $c->nombres,
    ])->values();
@endphp

<div class="ven-wrapper">

    {{-- ══ Cabecera ══ --}}
    <div class="ven-header">
        <div class="ven-header-left">
            <h2>Registro de Ventas</h2>
            <p>Comprobantes emitidos — {{ config('rentaltech.empresa.razon_social') }}</p>
            @if ($estadoFactura === 'no_enviado')
                <span class="badge badge-warning" style="margin-top:6px;">Filtro: no enviados a SUNAT
                    <a href="{{ route('admin.ventas.index') }}" style="margin-left:6px;color:inherit;">✕</a>
                </span>
            @elseif ($estadoFiltro === 'cancelada')
                <span class="badge badge-danger" style="margin-top:6px;">Filtro: anulaciones
                    <a href="{{ route('admin.ventas.index') }}" style="margin-left:6px;color:inherit;">✕</a>
                </span>
            @endif
        </div>
        <div class="ven-header-right">
            <a href="{{ route('admin.ventas.notas.create') }}" class="btn-add-ven" style="text-decoration:none;background:#fff;color:#3d9b8c;border:1.5px solid #3d9b8c;">
                Nota de Crédito / Débito
            </a>
            <a href="{{ route('admin.ventas.factura.create') }}" class="btn-add-ven" style="text-decoration:none;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Nueva Venta
            </a>
        </div>
    </div>

    {{-- ══ Indicadores ══ --}}
    <div class="ven-kpis">
        <div class="ven-kpi">
            <div class="ven-kpi-label">Comprobantes</div>
            <div class="ven-kpi-val">{{ number_format($nVentas) }}</div>
            <div class="ven-kpi-sub">Registros activos</div>
        </div>
        <div class="ven-kpi">
            <div class="ven-kpi-label">Base Imponible</div>
            <div class="ven-kpi-val">S/ {{ number_format($totalBase, 2) }}</div>
            <div class="ven-kpi-sub">Operaciones gravadas</div>
        </div>
        <div class="ven-kpi">
            <div class="ven-kpi-label">Exonerado + Inafecto</div>
            <div class="ven-kpi-val">S/ {{ number_format($totalSinIgv, 2) }}</div>
            <div class="ven-kpi-sub">Sin afectación IGV</div>
        </div>
        <div class="ven-kpi kpi-igv">
            <div class="ven-kpi-label">IGV ({{ (int) (config('rentaltech.igv') * 100) }}%)</div>
            <div class="ven-kpi-val">S/ {{ number_format($totalIgv, 2) }}</div>
            <div class="ven-kpi-sub">Impuesto acumulado</div>
        </div>
        <div class="ven-kpi kpi-total">
            <div class="ven-kpi-label">Total General</div>
            <div class="ven-kpi-val">S/ {{ number_format($totalGeneral, 2) }}</div>
            <div class="ven-kpi-sub">Suma de todos los comprobantes</div>
        </div>
    </div>

    {{-- ══ Listado ══ --}}
    <div class="ven-card">
        {{-- Los filtros comparten un solo formulario que se envía al cambiar. --}}
        <form method="GET" id="filtrosVentas">
            <div class="ven-card-header">
                <input type="search" class="ven-search" name="q" value="{{ $busqueda }}"
                       placeholder="Buscar N° comp, RUC, razón social...">
            </div>

            <div class="filtro-mes-bar">
                <span class="filtro-mes-label">📅 Mes:</span>
                <a href="{{ route('admin.ventas.index') }}"
                   class="mes-pill @if($mesSel === '' && $desde === '' && $hasta === '') active @endif">Todos</a>
                <input type="month" class="fecha-input @if($mesSel !== '') activo @endif" name="mes" value="{{ $mesSel }}">

                <div class="filtro-sep"></div>

                <span class="filtro-mes-label">📆 Fechas:</span>
                <label class="filtro-mini">Desde</label>
                <input type="date" class="fecha-input @if($desde !== '') activo @endif" name="desde" value="{{ $desde }}">
                <label class="filtro-mini">Hasta</label>
                <input type="date" class="fecha-input @if($hasta !== '') activo @endif" name="hasta" value="{{ $hasta }}">

                @if ($mesSel !== '' || $desde !== '' || $hasta !== '' || $busqueda !== '')
                    <a href="{{ route('admin.ventas.index') }}" class="btn-limpiar-f">✕ Limpiar</a>
                @endif
            </div>
        </form>

        <div class="ven-table-wrap">
            <table class="ven-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo Comp.</th>
                        <th>N° Serie</th>
                        <th>N° Comp.</th>
                        <th>N° RUC / DNI</th>
                        <th>Razón Social</th>
                        <th class="num">Base Imp.</th>
                        <th class="num">Exonerado</th>
                        <th class="num">Inafecto</th>
                        <th class="num">IGV</th>
                        <th class="num">Total</th>
                        <th class="num">T/C</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($grupos as $clave => $grupo)
                    @php $primerDia = \Carbon\Carbon::createFromFormat('Y-m', $clave)->startOfMonth(); @endphp
                    <tr class="grupo-header">
                        <td colspan="13">
                            <strong>{{ Str::upper($primerDia->translatedFormat('F Y')) }}</strong>
                            <span class="gh-conteo">{{ $grupo->count() }} comprobante{{ $grupo->count() > 1 ? 's' : '' }}</span>
                            <span class="gh-total">S/ {{ number_format($grupo->sum('total'), 2) }}</span>
                        </td>
                    </tr>

                    @foreach ($grupo as $venta)
                        @php
                            $base = (float) $venta->baseimp;
                            $exo  = (float) $venta->exonerado;
                            $ina  = (float) $venta->inafecto;
                            $igv  = (float) $venta->igv;
                            $tc   = (float) ($venta->tipcambio ?: 1);
                        @endphp
                        <tr>
                            <td class="celda-fecha">{{ $venta->fecha?->format('d/m/Y') }}</td>
                            <td>
                                <span class="badge-tc tc-{{ $venta->tipcomp ?: '01' }}">
                                    {{ $venta->tipcomp }} — {{ Str::after($tipos[$venta->tipcomp]['nombre'] ?? '', '— ') }}
                                </span>
                            </td>
                            <td class="celda-serie">{{ $venta->n_seri }}</td>
                            <td class="celda-comp">{{ $venta->n_comp ?: $venta->numero_venta }}</td>
                            <td class="celda-ruc">{{ $venta->n_ruc ?: $venta->cliente_ruc }}</td>
                            <td class="celda-razon" title="{{ $venta->razonsocial ?: $venta->cliente_nombre }}">
                                {{ $venta->razonsocial ?: $venta->cliente_nombre ?: '—' }}
                            </td>
                            <td class="num {{ $base > 0 ? 'importe-pos' : 'importe-cero' }}">{{ $base > 0 ? number_format($base, 2) : '-' }}</td>
                            <td class="num {{ $exo  > 0 ? 'importe-pos' : 'importe-cero' }}">{{ $exo  > 0 ? number_format($exo, 2)  : '-' }}</td>
                            <td class="num {{ $ina  > 0 ? 'importe-pos' : 'importe-cero' }}">{{ $ina  > 0 ? number_format($ina, 2)  : '-' }}</td>
                            <td class="num {{ $igv  > 0 ? 'importe-pos' : 'importe-cero' }}">{{ $igv  > 0 ? number_format($igv, 2)  : '-' }}</td>
                            <td class="num total-bold">S/ {{ number_format($venta->total, 2) }}</td>
                            <td class="num celda-tc">{{ $tc != 1 ? number_format($tc, 3) : '1' }}</td>
                            <td class="celda-acciones">
                                <a href="{{ route('admin.ventas.comprobante', $venta) }}" target="_blank"
                                   class="btn-edit-v" title="Ver / imprimir comprobante"
                                   style="text-decoration:none;color:inherit;">🧾</a>
                                <button type="button" class="btn-edit-v btn-editar" title="Editar"
                                        data-venta="{{ $venta->id }}"
                                        data-fecha="{{ $venta->fecha?->format('Y-m-d') }}"
                                        data-tipcomp="{{ $venta->tipcomp }}"
                                        data-n-seri="{{ $venta->n_seri }}"
                                        data-n-comp="{{ $venta->n_comp }}"
                                        data-n-ruc="{{ $venta->n_ruc }}"
                                        data-razonsocial="{{ $venta->razonsocial }}"
                                        data-cliente-id="{{ $venta->cliente_id }}"
                                        data-baseimp="{{ $venta->baseimp }}"
                                        data-exonerado="{{ $venta->exonerado }}"
                                        data-inafecto="{{ $venta->inafecto }}"
                                        data-total="{{ $venta->total }}"
                                        data-tipcambio="{{ $venta->tipcambio }}">✏</button>

                                @if (in_array($venta->tipcomp, ['COT', 'NV'], true) || (in_array($venta->tipcomp, ['01', '03'], true) && $venta->estado_factura === 'pendiente'))
                                    <form method="POST" action="{{ route('admin.ventas.anular', $venta) }}"
                                          data-confirmar="¿Anular el comprobante {{ $venta->n_seri }}-{{ $venta->n_comp }}? Esta acción no se puede deshacer.">
                                        @csrf
                                        <button type="submit" class="btn-edit-v" title="Anular">🚫</button>
                                    </form>
                                @endif

                                <form method="POST" action="{{ route('admin.ventas.destroy', $venta) }}"
                                      class="form-eliminar"
                                      data-confirmar="Se eliminará el comprobante {{ $venta->n_seri }}-{{ $venta->n_comp }}. Esta acción no se puede deshacer.">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn-del-v" title="Eliminar">×</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                @empty
                    <tr>
                        <td colspan="13" class="ven-vacio">
                            <div class="ven-vacio-icono">🔍</div>
                            Sin resultados para el filtro seleccionado.
                        </td>
                    </tr>
                @endforelse
                </tbody>

                @if ($nVentas > 0)
                    <tfoot>
                        <tr>
                            <td colspan="6">
                                <span class="tfoot-label">Total</span>
                                <strong class="tfoot-count">{{ number_format($nVentas) }} registros</strong>
                            </td>
                            <td colspan="3"></td>
                            <td class="num">
                                <div class="tfoot-label">IGV</div>
                                <div class="tfoot-igv">S/ {{ number_format($totalIgv, 2) }}</div>
                            </td>
                            <td class="num" colspan="2">
                                <div class="tfoot-label">Total</div>
                                <div class="tfoot-total">S/ {{ number_format($totalGeneral, 2) }}</div>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>

{{-- ══ Alta / edición ══ --}}
<div class="ven-modal-overlay" id="venModal">
    <div class="ven-modal">
        <div class="ven-modal-header">
            <div>
                <h3 id="venModalTitle">Nueva Venta</h3>
                <p>{{ config('rentaltech.empresa.razon_social') }} — Registro de comprobantes</p>
            </div>
            <button type="button" class="ven-modal-close" id="venCerrar" aria-label="Cerrar">×</button>
        </div>

        <form method="POST" action="{{ route('admin.ventas.store') }}" id="formVenta">
            @csrf
            <input type="hidden" name="venta_id" id="venta_id" value="">
            <input type="hidden" name="tipo_operacion" id="v-tipo-operacion" value="gravada">
            {{-- El importe enviado depende del tipo de operación elegido. --}}
            <input type="hidden" name="monto" id="v-monto">

            <div class="ven-modal-body">
                <div class="ven-grid">

                    <div class="ven-group">
                        <label class="ven-label" for="v-fecha">Fecha <span>*</span></label>
                        <input type="date" class="ven-control" id="v-fecha" name="fecha" required
                               value="{{ now()->toDateString() }}">
                    </div>
                    <div class="ven-group">
                        <label class="ven-label" for="v-tipcomp">Tipo Comprobante <span>*</span></label>
                        <select class="ven-control" id="v-tipcomp" name="tipcomp" required>
                            @foreach (array_intersect_key($tipos, array_flip(['COT', 'NV', '01', '03'])) as $codigo => $tipo)
                                <option value="{{ $codigo }}" data-serie="{{ $tipo['serie'] }}">{{ $tipo['nombre'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="ven-group">
                        <label class="ven-label" for="v-n-seri">N° Serie <span>*</span></label>
                        <input type="text" class="ven-control" id="v-n-seri" name="n_seri" required
                               placeholder="B001" maxlength="4">
                    </div>
                    <div class="ven-group">
                        <label class="ven-label" for="v-n-comp">N° Comprobante <span>*</span></label>
                        <input type="text" class="ven-control" id="v-n-comp" name="n_comp" required
                               placeholder="0000000001" maxlength="20">
                    </div>

                    <input type="hidden" id="v-cliente-id" name="cliente_id" value="">

                    <div class="ven-group ven-busca">
                        <label class="ven-label" for="v-n-ruc">N° RUC / DNI</label>
                        <input type="text" class="ven-control" id="v-n-ruc" name="n_ruc"
                               placeholder="Documento o nombre del cliente…" maxlength="20"
                               autocomplete="off">
                        <div class="ven-sugerencias" id="v-sug-ruc"></div>
                    </div>
                    <div class="ven-group ven-busca">
                        <label class="ven-label" for="v-razonsocial">Razón Social</label>
                        <input type="text" class="ven-control" id="v-razonsocial" name="razonsocial"
                               placeholder="Nombre o empresa..." maxlength="300"
                               autocomplete="off">
                        <div class="ven-sugerencias" id="v-sug-razon"></div>
                    </div>
                    <div class="ven-group">
                        <label class="ven-label">&nbsp;</label>
                        <button type="button" class="btn btn-secondary btn-sm" id="btnVClienteVarios">
                            Usar "Cliente Varios" (sin documento)
                        </button>
                    </div>

                    <div class="ven-group ven-full">
                        <label class="ven-label">Tipo de Operación <span>*</span></label>
                        <div class="tipo-op-tabs">
                            <button type="button" class="tipo-op-tab active" data-op="gravada">⚡ Gravada (con IGV)</button>
                            <button type="button" class="tipo-op-tab" data-op="exonerada">🟡 Exonerada</button>
                            <button type="button" class="tipo-op-tab" data-op="inafecta">⚪ Inafecta</button>
                        </div>
                    </div>

                    {{-- Solo se muestra el importe del tipo de operación elegido. --}}
                    <div class="ven-group" id="grp-gravada">
                        <label class="ven-label" for="v-monto-gravada">Base Imponible (S/) <span>*</span></label>
                        <input type="number" class="ven-control monto-op" id="v-monto-gravada"
                               placeholder="0.00" step="0.01" min="0">
                    </div>
                    <div class="ven-group" id="grp-igv">
                        <label class="ven-label" for="v-igv">IGV {{ (int) (config('rentaltech.igv') * 100) }}% (S/)</label>
                        <input type="number" class="ven-control calc" id="v-igv" placeholder="0.00" step="0.01" readonly>
                    </div>

                    <div class="ven-group" id="grp-exonerada" style="display:none;">
                        <label class="ven-label" for="v-monto-exonerada">Monto Exonerado (S/) <span>*</span></label>
                        <input type="number" class="ven-control monto-op" id="v-monto-exonerada"
                               placeholder="0.00" step="0.01" min="0">
                    </div>
                    <div class="ven-group" id="grp-inafecta" style="display:none;">
                        <label class="ven-label" for="v-monto-inafecta">Monto Inafecto (S/) <span>*</span></label>
                        <input type="number" class="ven-control monto-op" id="v-monto-inafecta"
                               placeholder="0.00" step="0.01" min="0">
                    </div>

                    <div class="ven-group">
                        <label class="ven-label" for="v-vtotal">Total (S/)</label>
                        <input type="number" class="ven-control calc" id="v-vtotal" placeholder="0.00" step="0.01" readonly>
                    </div>
                    <div class="ven-group">
                        <label class="ven-label" for="v-tipcambio">Tipo de Cambio</label>
                        <input type="number" class="ven-control" id="v-tipcambio" name="tipcambio"
                               placeholder="1.000" step="0.001" min="0" value="1">
                    </div>

                </div>
            </div>

            <div class="ven-modal-footer">
                <button type="button" class="btn-cancel-modal" id="venCancelar">Cancelar</button>
                <button type="submit" class="btn-save-modal" id="venBtnGuardar">Guardar</button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
const IGV_VENTAS = {{ config('rentaltech.igv') }};
const URL_VENTAS = '{{ url('admin/ventas') }}';

const modalVenta = document.getElementById('venModal');
const formVenta  = document.getElementById('formVenta');

// ── Filtros del listado ──────────────────────────────────────────────────
// Mes y fechas se aplican al instante; el buscador espera a que se deje de
// escribir para no recargar en cada tecla.
const formFiltros = document.getElementById('filtrosVentas');

formFiltros.querySelectorAll('input[type="month"], input[type="date"]').forEach((campo) => {
    campo.addEventListener('change', () => {
        // Mes y rango de fechas se excluyen entre sí, como en el original.
        const otros = campo.name === 'mes' ? ['desde', 'hasta'] : ['mes'];
        otros.forEach((nombre) => {
            const otro = formFiltros.elements[nombre];
            if (otro) { otro.value = ''; }
        });
        formFiltros.submit();
    });
});

let esperaBusqueda;
const campoBusquedaVentas = formFiltros.querySelector('.ven-search');

campoBusquedaVentas.addEventListener('input', () => {
    clearTimeout(esperaBusqueda);
    esperaBusqueda = setTimeout(() => formFiltros.submit(), 600);
});

// La recarga (envío del formulario) quita el foco del campo; si el usuario
// sigue escribiendo justo en ese momento, las teclas se pierden o se
// mezclan. Al volver a cargar, se devuelve el foco con el cursor al final.
if (campoBusquedaVentas.value) {
    campoBusquedaVentas.focus();
    campoBusquedaVentas.setSelectionRange(campoBusquedaVentas.value.length, campoBusquedaVentas.value.length);
}

let operacionActual = 'gravada';

// ── Serie sugerida según el tipo de comprobante ──────────────────────────
const campoTipo  = document.getElementById('v-tipcomp');
const campoSerie = document.getElementById('v-n-seri');

// Igual que en el formulario de Nueva Venta: solo deja de re-sugerir cuando
// el usuario ya escribió su propia serie a mano (no solo "si está vacío").
let campoSerieEditada = false;
campoSerie.addEventListener('input', () => { campoSerieEditada = true; });

function sugerirSerieVenta() {
    if (!campoSerieEditada) {
        campoSerie.value = campoTipo.selectedOptions[0]?.dataset.serie || '';
    }
}

campoTipo.addEventListener('change', sugerirSerieVenta);

// ── Tipo de operación ────────────────────────────────────────────────────
function fijarOperacion(op, conservarImportes = false) {
    operacionActual = op;
    document.getElementById('v-tipo-operacion').value = op;

    document.querySelectorAll('.tipo-op-tab').forEach((tab) => {
        tab.classList.toggle('active', tab.dataset.op === op);
    });

    document.getElementById('grp-gravada').style.display   = op === 'gravada'   ? '' : 'none';
    document.getElementById('grp-igv').style.display       = op === 'gravada'   ? '' : 'none';
    document.getElementById('grp-exonerada').style.display = op === 'exonerada' ? '' : 'none';
    document.getElementById('grp-inafecta').style.display  = op === 'inafecta'  ? '' : 'none';

    if (!conservarImportes) {
        document.querySelectorAll('.monto-op').forEach((campo) => { campo.value = ''; });
    }

    calcular();
}

document.querySelectorAll('.tipo-op-tab').forEach((tab) => {
    tab.addEventListener('click', () => fijarOperacion(tab.dataset.op));
});

// ── Cálculo del IGV y el total ───────────────────────────────────────────
function calcular() {
    const campo = document.getElementById('v-monto-' + operacionActual);
    const monto = parseFloat(campo?.value) || 0;
    const igv   = operacionActual === 'gravada' ? Math.round(monto * IGV_VENTAS * 100) / 100 : 0;

    document.getElementById('v-igv').value    = igv.toFixed(2);
    document.getElementById('v-vtotal').value = (monto + igv).toFixed(2);
    document.getElementById('v-monto').value  = monto || '';
}

document.querySelectorAll('.monto-op').forEach((campo) => {
    campo.addEventListener('input', calcular);
});

// ── Apertura y cierre ────────────────────────────────────────────────────
function abrirVenta() {
    modalVenta.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function cerrarVenta() {
    modalVenta.classList.remove('active');
    document.body.style.overflow = '';
}

document.getElementById('venCerrar').addEventListener('click', cerrarVenta);
document.getElementById('venCancelar').addEventListener('click', cerrarVenta);

modalVenta.addEventListener('click', (e) => {
    if (e.target === modalVenta) cerrarVenta();
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modalVenta.classList.contains('active')) cerrarVenta();
});

// ── Buscador de clientes en RUC/DNI y razón social ───────────────────────
const CLIENTES = @json($listaClientes);

const campoRuc   = document.getElementById('v-n-ruc');
const campoRazon = document.getElementById('v-razonsocial');
const campoFicha = document.getElementById('v-cliente-id');

/** Deja el comprobante apuntando a la ficha elegida. */
function elegirCliente(cliente) {
    campoFicha.value = cliente.id;
    campoRuc.value   = cliente.doc || '';
    campoRazon.value = cliente.n;

    document.querySelectorAll('.ven-sugerencias').forEach((s) => s.classList.remove('is-visible'));
}

// "Cliente Varios": para ventas a alguien que no dio su DNI o no está
// registrado en el sistema — no queda enlazado a ninguna ficha de Cliente.
document.getElementById('btnVClienteVarios').addEventListener('click', () => {
    campoFicha.value = '';
    campoRuc.value   = '';
    campoRazon.value = 'Cliente Varios';

    document.querySelectorAll('.ven-sugerencias').forEach((s) => s.classList.remove('is-visible'));
});

function buscarClientes(texto) {
    const t = texto.trim().toLowerCase();
    if (t.length < 2) return [];

    return CLIENTES
        .filter((c) => (c.doc || '').toLowerCase().includes(t) || c.n.toLowerCase().includes(t))
        .slice(0, 8);
}

function montarBuscador(campo, panel) {
    campo.addEventListener('input', () => {
        // Al escribir a mano se suelta la ficha: puede ser un cliente nuevo.
        campoFicha.value = '';

        const hallados = buscarClientes(campo.value);

        if (hallados.length === 0) {
            panel.classList.remove('is-visible');

            return;
        }

        panel.innerHTML = hallados.map((c, i) =>
            `<div class="ven-sugerencia" data-i="${i}">
                <b>${c.n}</b><span>${c.doc || 'sin documento'}</span>
            </div>`).join('');

        panel._hallados = hallados;
        panel.classList.add('is-visible');
    });

    panel.addEventListener('mousedown', (e) => {
        const fila = e.target.closest('.ven-sugerencia');
        if (fila) elegirCliente(panel._hallados[fila.dataset.i]);
    });

    campo.addEventListener('blur', () => panel.classList.remove('is-visible'));
}

montarBuscador(campoRuc, document.getElementById('v-sug-ruc'));
montarBuscador(campoRazon, document.getElementById('v-sug-razon'));

// El modal `venModal` (formVenta) ahora solo se abre para Editar; la creación
// es la página aparte "Nueva Venta" (admin.ventas.factura.create).

// ── Edición ──────────────────────────────────────────────────────────────
document.querySelectorAll('.btn-editar').forEach((boton) => {
    boton.addEventListener('click', () => {
        const d = boton.dataset;

        formVenta.action = URL_VENTAS + '/' + d.venta;
        if (!formVenta.querySelector('[name="_method"]')) {
            const metodo = document.createElement('input');
            metodo.type = 'hidden';
            metodo.name = '_method';
            metodo.value = 'PUT';
            formVenta.appendChild(metodo);
        }

        document.getElementById('venModalTitle').textContent = 'Editar Venta';
        document.getElementById('venBtnGuardar').textContent = 'Guardar Cambios';
        document.getElementById('venta_id').value      = d.venta;
        document.getElementById('v-fecha').value       = d.fecha || '';
        document.getElementById('v-tipcomp').value     = d.tipcomp || '01';
        document.getElementById('v-n-seri').value      = d.nSeri || '';
        campoSerieEditada = true; // Serie real del registro — no debe pisarla la sugerencia automática.
        document.getElementById('v-n-comp').value      = d.nComp || '';
        document.getElementById('v-n-ruc').value       = d.nRuc || '';
        document.getElementById('v-razonsocial').value = d.razonsocial || '';
        campoFicha.value = d.clienteId || '';
        document.getElementById('v-tipcambio').value   = d.tipcambio || '1';

        // El tipo de operación se deduce de cuál de los tres importes trae monto.
        const base = parseFloat(d.baseimp) || 0;
        const exo  = parseFloat(d.exonerado) || 0;
        const ina  = parseFloat(d.inafecto) || 0;
        const op   = base > 0 ? 'gravada' : (exo > 0 ? 'exonerada' : 'inafecta');

        fijarOperacion(op, true);
        document.getElementById('v-monto-' + op).value = (base || exo || ina).toFixed(2);
        calcular();

        abrirVenta();
    });
});

fijarOperacion('gravada');
</script>
@endpush
