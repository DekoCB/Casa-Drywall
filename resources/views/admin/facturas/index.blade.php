@extends('layouts.admin')

@section('title', 'Facturas')
@section('crumb', 'Vista general')

@push('styles')
    @vite(['resources/css/modules/facturas.css'])
@endpush

@section('content')

<div class="fac-wrapper">

    {{-- ══ Cabecera ══ --}}
    <div class="fac-header">
        <div class="fac-header-left">
            <h2>Facturas Pendientes</h2>
            <p>Documentos por cobrar — GP Maquinarias SAC</p>
        </div>
        <div class="fac-header-right">
            <button type="button" class="btn-upload-pdf" id="btnSubirPdf">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/><path d="M9 13h6M9 17h3"/>
                </svg>
                Subir PDF
            </button>
            <button type="button" class="btn-add-fac" id="btnFacturaManual">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Manual
            </button>
        </div>
    </div>

    {{-- ══ Indicadores ══ --}}
    <div class="fac-kpis">
        <a href="{{ route('admin.facturas.estadisticas') }}" class="fac-kpi" title="Ver estadísticas USD">
            <div class="fac-kpi-label">Total (USD)</div>
            <div class="fac-kpi-val">$ {{ number_format($totalUsd, 2) }}</div>
            <div class="fac-kpi-sub">Saldo moneda extranjera · <span>Ver estadísticas →</span></div>
        </a>
        <a href="{{ route('admin.facturas.estadisticas') }}" class="fac-kpi" title="Ver estadísticas">
            <div class="fac-kpi-label">Total (Soles)</div>
            <div class="fac-kpi-val">S/ {{ number_format($totalSoles, 2) }}</div>
            <div class="fac-kpi-sub">Al tipo de cambio aplicado · <span>Ver estadísticas →</span></div>
        </a>
        <a href="{{ route('admin.galonaje.dashboard') }}" class="fac-kpi kpi-galones" title="Ver dashboard de galonaje">
            <div class="fac-kpi-label">Total Galones</div>
            <div class="fac-kpi-val">{{ number_format($totalGal, 2) }} GL</div>
            <div class="fac-kpi-sub">Galonaje total acumulado · <span>Ver dashboard →</span></div>
        </a>
    </div>

    {{-- ══ Listado ══ --}}
    <div class="fac-card">
        <form method="GET" id="filtrosFacturas">
            <div class="fac-card-header">
                <input type="search" class="fac-search" name="q" value="{{ $busqueda }}"
                       placeholder="Buscar N° factura, doc...">
            </div>

            <div class="filtro-mes-bar">
                <span class="filtro-mes-label">📅 Mes:</span>
                <a href="{{ route('admin.facturas.index') }}"
                   class="mes-pill @if($mesSel === '' && $desde === '' && $hasta === '') active @endif">Todos</a>
                <input type="month" class="fecha-input @if($mesSel !== '') activo @endif" name="mes" value="{{ $mesSel }}">

                <div class="filtro-sep"></div>

                <span class="filtro-mes-label">📆 Fechas:</span>
                <label class="filtro-mini">Desde</label>
                <input type="date" class="fecha-input @if($desde !== '') activo @endif" name="desde" value="{{ $desde }}">
                <label class="filtro-mini">Hasta</label>
                <input type="date" class="fecha-input @if($hasta !== '') activo @endif" name="hasta" value="{{ $hasta }}">

                @if ($mesSel !== '' || $desde !== '' || $hasta !== '' || $busqueda !== '')
                    <a href="{{ route('admin.facturas.index') }}" class="btn-limpiar-fecha">✕ Limpiar</a>
                @endif
            </div>
        </form>

        {{-- La tabla es ancha; estos botones la desplazan sin usar el ratón. --}}
        <div class="scroll-nav-bar">
            <span class="scroll-hint-txt">← Desliza para ver más columnas →</span>
            <div style="display:flex;gap:4px;">
                <button type="button" class="scroll-nav-btn" data-scroll="-400" title="Desplazar izquierda">&#8592;</button>
                <button type="button" class="scroll-nav-btn" data-scroll="-150">&#8592;&#8592;</button>
                <button type="button" class="scroll-nav-btn" data-scroll="150">&#8594;&#8594;</button>
                <button type="button" class="scroll-nav-btn" data-scroll="400" title="Desplazar derecha">&#8594;</button>
            </div>
        </div>

        <div class="tabla-outer">
            <div class="scroll-shadow-left" id="sombraIzq"></div>
            <div class="scroll-shadow-right" id="sombraDer"></div>

            <div class="fac-table-wrap" id="tablaScroll">
                <table class="fac-table">
                    <thead>
                        <tr>
                            <th>N° Factura</th>
                            <th>N° Doc</th>
                            <th>Guía Remisión</th>
                            <th>Emisión</th>
                            <th>Vencimiento</th>
                            <th>Mora</th>
                            <th>Galones</th>
                            <th>Producto</th>
                            <th>T/C</th>
                            <th>Importe USD</th>
                            <th>Importe S/</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($grupos as $clave => $grupo)
                        @php $primerDia = \Carbon\Carbon::createFromFormat('Y-m', $clave)->startOfMonth(); @endphp
                        <tr class="grupo-header">
                            <td colspan="13">
                                <strong class="gh-mes">{{ Str::upper($primerDia->translatedFormat('F Y')) }}</strong>
                                <span class="gh-conteo">{{ $grupo->count() }} factura{{ $grupo->count() > 1 ? 's' : '' }}</span>
                            </td>
                        </tr>

                        @foreach ($grupo as $factura)
                            @php
                                $estado = $factura->estado();
                                $mora = $factura->diasMora();
                                $clase = $factura->cancelado ? 'fila-cancelada' : ($estado === 'pagada' ? 'fila-pagada' : '');
                                $detalle = $factura->productos_lista ?? [];
                            @endphp
                            <tr class="{{ $clase }} fila-factura" data-detalle="det-{{ $factura->id }}" style="cursor:pointer;">
                                <td>
                                    <span class="num-factura">{{ $factura->numero }}</span>
                                    <span class="toggle-icon">▼</span>
                                </td>
                                <td><span class="num-doc">{{ $factura->doc ?: '—' }}</span></td>
                                <td class="celda-guia">{{ $factura->guia_remision ?: '—' }}</td>
                                <td>{{ $factura->emision->format('d/m/Y') }}</td>
                                <td class="celda-venc">{{ $factura->vencimiento->format('d/m/Y') }}</td>
                                <td>
                                    <span class="mora-badge mora-{{ $factura->nivelMora() }}">
                                        {{ Str::ucfirst($factura->nivelMora()) }} ({{ $mora }}d)
                                    </span>
                                </td>
                                <td>
                                    @if ((float) $factura->galones > 0)
                                        <span class="galones-cell">{{ number_format($factura->galones, 2) }} GL</span>
                                    @else
                                        <span class="galones-cell vacio">—</span>
                                    @endif
                                </td>
                                <td class="celda-producto" title="{{ $factura->producto }}">{{ $factura->producto ?: '—' }}</td>
                                <td class="celda-tc">{{ number_format($factura->tc, 2) }}</td>
                                <td><div class="importe-me">$ {{ number_format($factura->importe, 2) }}</div></td>
                                <td class="celda-soles">S/ {{ number_format($factura->importeSoles(), 2) }}</td>
                                <td>
                                    <span class="fac-badge badge-{{ $estado }}">
                                        {{ Str::upper(str_replace('_', ' ', $estado)) }}
                                    </span>
                                </td>
                                <td onclick="event.stopPropagation();">
                                    <div class="fac-acciones">
                                        <button type="button" class="btn-cancelar {{ $factura->cancelado ? 'activo' : '' }}"
                                                data-cancelar="{{ $factura->id }}"
                                                title="{{ $factura->cancelado ? 'Reactivar' : 'Cancelar' }}">
                                            {{ $factura->cancelado ? '↩ Reactivar' : '✕ Cancelar' }}
                                        </button>

                                        <button type="button" class="btn-pagada {{ $estado === 'pagada' ? 'activo' : '' }}"
                                                data-pagada="{{ $factura->id }}"
                                                title="{{ $estado === 'pagada' ? 'Marcar como pendiente' : 'Marcar como pagada' }}">✓</button>

                                        <button type="button" class="btn-edit btn-editar-fac" title="Editar"
                                                data-factura="{{ $factura->id }}"
                                                data-numero="{{ $factura->numero }}"
                                                data-doc="{{ $factura->doc }}"
                                                data-guia="{{ $factura->guia_remision }}"
                                                data-emision="{{ $factura->emision->format('Y-m-d') }}"
                                                data-vencimiento="{{ $factura->vencimiento->format('Y-m-d') }}"
                                                data-importe="{{ $factura->importe }}"
                                                data-tc="{{ $factura->tc }}"
                                                data-galones="{{ $factura->galones }}"
                                                data-producto="{{ $factura->producto }}"
                                                data-cliente="{{ $factura->cliente }}"
                                                data-estado="{{ $factura->estado_manual }}"
                                                data-lista="{{ json_encode($detalle) }}">✏</button>

                                        <form method="POST" action="{{ route('admin.facturas.destroy', $factura) }}"
                                              class="form-inline-fac"
                                              data-confirmar="Se eliminará la factura {{ $factura->numero }}. Esta acción no se puede deshacer.">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn-del" title="Eliminar">×</button>
                                        </form>

                                        @if ($factura->pdf)
                                            <a href="{{ Storage::url($factura->pdf) }}" target="_blank" class="btn-pdf-ver" title="Ver PDF">📄</a>
                                            <form method="POST" action="{{ route('admin.facturas.pdf-eliminar', $factura) }}"
                                                  class="form-inline-fac"
                                                  data-confirmar="Se quitará el PDF de la factura {{ $factura->numero }}.">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn-pdf-del" title="Quitar PDF">✕</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('admin.facturas.pdf', $factura) }}"
                                                  enctype="multipart/form-data" class="form-inline-fac">
                                                @csrf
                                                <input type="file" name="pdf" accept="application/pdf" hidden
                                                       id="pdf-{{ $factura->id }}" onchange="this.form.submit()">
                                                <button type="button" class="btn-pdf-up" title="Adjuntar PDF"
                                                        onclick="document.getElementById('pdf-{{ $factura->id }}').click()">📎</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>

                            {{-- Desglose de productos con su galonaje --}}
                            <tr class="fila-detalle" id="det-{{ $factura->id }}" style="display:none;">
                                <td colspan="13">
                                    <div class="detalle-caja">
                                        @if (count($detalle) > 0)
                                            <table class="detalle-tabla">
                                                <thead>
                                                    <tr>
                                                        <th>Código</th><th>Producto</th>
                                                        <th class="cen">Cant.</th><th class="cen">Pres.</th>
                                                        <th class="cen">Factor</th><th class="num">Galones</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                @foreach ($detalle as $item)
                                                    <tr>
                                                        <td class="det-codigo">{{ $item['codigo'] ?? '' }}</td>
                                                        <td class="det-nombre">{{ $item['nombre'] ?? $item['codigo'] ?? '' }}</td>
                                                        <td class="cen">{{ $item['cantidad'] ?? 0 }}</td>
                                                        <td class="cen"><span class="det-pres">{{ $item['pres'] ?? '' }}</span></td>
                                                        <td class="cen det-factor">×{{ $item['factor'] ?? 0 }}</td>
                                                        <td class="num">{{ number_format((float) ($item['galones'] ?? 0), 2) }} GL</td>
                                                    </tr>
                                                @endforeach
                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <td colspan="5">Total Galones</td>
                                                        <td class="num">{{ number_format($factura->galones, 2) }} GL</td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        @else
                                            <div class="detalle-simple">
                                                <span style="font-size:20px;">🛢</span>
                                                <div>
                                                    <strong>{{ $factura->producto ?: 'Sin detalle de productos' }}</strong>
                                                    {{ (float) $factura->galones > 0
                                                        ? number_format($factura->galones, 2).' GL totales'
                                                        : 'Sin galonaje registrado' }}
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="13" class="fac-vacio">
                                <div class="fac-vacio-icono">🔍</div>
                                <strong>Sin resultados</strong>
                                No hay facturas para el filtro seleccionado.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>

                    @if ($nTotal > 0)
                        <tfoot>
                            <tr>
                                <td colspan="5">
                                    <span class="total-label">Total activo</span>
                                    <strong class="total-count">{{ $nActivas }} facturas</strong>
                                </td>
                                <td></td><td></td><td></td>
                                <td>
                                    <div class="tfoot-lbl">Total USD</div>
                                    <div class="tfoot-usd">$ {{ number_format($totalUsd, 2) }}</div>
                                </td>
                                <td>
                                    <div class="tfoot-lbl">Total Soles</div>
                                    <div class="tfoot-soles">S/ {{ number_format($totalSoles, 2) }}</div>
                                </td>
                                <td><span class="fac-badge badge-pendiente">PENDIENTE</span></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>

{{-- ══ Modal: alta y edición ══ --}}
<div class="fac-modal-overlay" id="facModal">
    <div class="fac-modal">
        <form method="POST" action="{{ route('admin.facturas.store') }}" id="formFactura">
            @csrf
            <input type="hidden" name="_method" id="fac-metodo" value="POST">
            <input type="hidden" name="productos_lista" id="fac-lista">

            {{-- El galonaje y el nombre del producto salen de la lista de abajo. --}}
            <input type="hidden" name="galones" id="fac-galones">
            <input type="hidden" name="producto" id="fac-producto">

            <div class="fac-modal-header">
                <div>
                    <h3 id="fac-modal-titulo">Agregar Factura</h3>
                    <p>GP Maquinarias SAC — RUC 20612189651</p>
                </div>
                <button type="button" class="fac-modal-cerrar" id="facCerrar">×</button>
            </div>

            <div class="fac-modal-body">
                <div class="modal-tabs">
                    <button type="button" class="modal-tab" id="tab-pdf" data-tab="pdf">📄 Subir PDF</button>
                    <button type="button" class="modal-tab active" id="tab-manual" data-tab="manual">✏️ Ingresar Manual</button>
                </div>

                {{-- ── Pestaña: subir el PDF y dejar que la IA lo lea ── --}}
                <div class="tab-content" id="content-pdf">
                    <div class="pdf-drop-zone" id="pdfZona">
                        <input type="file" id="pdfArchivo" accept="application/pdf">
                        <div class="pdf-drop-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="12" y1="12" x2="12" y2="18"/><polyline points="9 15 12 12 15 15"/>
                            </svg>
                        </div>
                        <div class="pdf-drop-title">Arrastra el PDF o haz clic aquí</div>
                        <div class="pdf-drop-sub">La IA extrae automáticamente: N° factura, fechas, importe, galones y producto</div>
                    </div>

                    <div class="pdf-status" id="pdfEstado">
                        <div class="pdf-status-spinner"></div>
                        <span id="pdfEstadoTexto">Analizando factura con IA…</span>
                    </div>
                </div>

                {{-- Lo que devolvió la IA queda a la vista en las dos pestañas. --}}
                <div class="pdf-resumen" id="pdfResumen"></div>

                <div class="ia-aviso" id="pdfAviso" style="display:none;">
                    <span class="ia-tag">IA</span>
                    Datos extraídos automáticamente — revisa y confirma antes de guardar
                </div>

                {{-- ── Pestaña: escribir los datos a mano ── --}}
                <div class="tab-content active" id="content-manual">
                    <div class="fac-grid">
                        <div class="fac-campo full">
                            <label class="fac-label" for="fac-numero">N° Factura <span>*</span></label>
                            <input type="text" class="fac-control" id="fac-numero" name="numero" placeholder="F0050009350" required>
                        </div>

                        <div class="fac-campo">
                            <label class="fac-label" for="fac-doc">N° Documento</label>
                            <input type="text" class="fac-control" id="fac-doc" name="doc" placeholder="0046900">
                        </div>
                        <div class="fac-campo">
                            <label class="fac-label" for="fac-guia">N° Guía de Remisión</label>
                            <input type="text" class="fac-control" id="fac-guia" name="guia_remision" placeholder="T001-0001234">
                        </div>

                        <div class="fac-campo">
                            <label class="fac-label" for="fac-importe">Importe (USD) <span>*</span></label>
                            <input type="number" class="fac-control" id="fac-importe" name="importe" step="0.01" min="0" placeholder="0.00" required>
                        </div>
                        <div class="fac-campo">
                            <label class="fac-label" for="fac-emision">Fecha Emisión <span>*</span></label>
                            <input type="date" class="fac-control" id="fac-emision" name="emision" value="{{ now()->format('Y-m-d') }}" required>
                        </div>

                        <div class="fac-campo">
                            <label class="fac-label" for="fac-vencimiento">Fecha Vencimiento <span>*</span></label>
                            <input type="date" class="fac-control" id="fac-vencimiento" name="vencimiento" required>
                        </div>
                        <div class="fac-campo">
                            <label class="fac-label" for="fac-tc">Tipo de Cambio</label>
                            <input type="number" class="fac-control" id="fac-tc" name="tc" step="0.01" min="0"
                                   value="{{ number_format(config('rentaltech.tipo_cambio_facturas'), 2, '.', '') }}" required>
                            <span class="fac-hint" id="fac-equivalencia">S/ 0.00</span>
                        </div>

                        <div class="fac-campo full">
                            <div class="mprod-cabecera">
                                <label class="fac-label">Productos</label>
                                <button type="button" class="btn-agregar-prod-fac" id="btnAgregarProd">+ Agregar producto</button>
                            </div>
                            <div id="lista-prods"></div>
                            <div class="mprod-total" id="mprodTotal">
                                Total galones: <strong><span id="mprodTotalVal">0.00</span> GL</strong>
                            </div>
                        </div>

                        <div class="fac-campo full">
                            <label class="fac-label" for="fac-cliente">Cliente</label>
                            <input type="text" class="fac-control" id="fac-cliente" name="cliente" placeholder="Nombre del cliente...">
                        </div>

                        <div class="fac-campo full">
                            <label class="fac-label" for="fac-estado">Estado</label>
                            <select class="fac-control" id="fac-estado" name="estado_manual">
                                <option value="">Automático (según fechas)</option>
                                <option value="pagada">PAGADA</option>
                                <option value="vencida">VENCIDA</option>
                                <option value="por_vencer">POR VENCER</option>
                                <option value="vigente">VIGENTE</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="fac-modal-footer">
                <button type="button" class="btn-cancel-fac" id="facCancelar">Cancelar</button>
                <button type="submit" class="btn-save-fac" id="facGuardar">Guardar Factura</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Todo el módulo vive dentro de esta función para que sus nombres no acaben
// en `window`: app.js publica ahí sus propios `abrirModal`/`cerrarModal` y,
// al cargarse como módulo diferido, pisaría a los de esta página.
(function () {
const URL_FACTURAS = '{{ url('admin/facturas') }}';
const URL_ANALIZAR = '{{ route('admin.facturas.analizar') }}';
const CSRF_FAC = document.querySelector('meta[name="csrf-token"]').content;

const $f = (id) => document.getElementById(id);

// ── Filtros ──────────────────────────────────────────────────────────────
const filtrosFacturas = $f('filtrosFacturas');

filtrosFacturas.querySelectorAll('input[type="month"], input[type="date"]').forEach((campo) => {
    campo.addEventListener('change', () => {
        // Mes y rango de fechas se excluyen entre sí.
        const otros = campo.name === 'mes' ? ['desde', 'hasta'] : ['mes'];
        otros.forEach((nombre) => {
            const otro = filtrosFacturas.elements[nombre];
            if (otro) { otro.value = ''; }
        });
        filtrosFacturas.submit();
    });
});

let esperaFac;
filtrosFacturas.querySelector('.fac-search').addEventListener('input', () => {
    clearTimeout(esperaFac);
    esperaFac = setTimeout(() => filtrosFacturas.submit(), 450);
});

// ── Desplazamiento horizontal y sus sombras ──────────────────────────────
const tabla = $f('tablaScroll');

function pintarSombras() {
    $f('sombraIzq').classList.toggle('vis', tabla.scrollLeft > 8);
    $f('sombraDer').classList.toggle('vis', tabla.scrollLeft + tabla.clientWidth < tabla.scrollWidth - 8);
}

tabla.addEventListener('scroll', pintarSombras);
window.addEventListener('resize', pintarSombras);
pintarSombras();

document.querySelectorAll('.scroll-nav-btn').forEach((boton) => {
    boton.addEventListener('click', () => tabla.scrollBy({ left: Number(boton.dataset.scroll), behavior: 'smooth' }));
});

// ── Detalle de cada factura ──────────────────────────────────────────────
document.querySelectorAll('.fila-factura').forEach((fila) => {
    fila.addEventListener('click', () => {
        const detalle = $f(fila.dataset.detalle);
        const abierto = detalle.style.display !== 'none';

        detalle.style.display = abierto ? 'none' : '';
        fila.querySelector('.toggle-icon').textContent = abierto ? '▼' : '▲';
    });
});

// ── Cancelar / reactivar y marcar como pagada ────────────────────────────
async function alternar(id, accion) {
    try {
        const respuesta = await fetch(URL_FACTURAS + '/' + id + '/' + accion, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF_FAC, 'Accept': 'application/json' },
        });

        if (!(await respuesta.json()).ok) { throw new Error('respuesta inválida'); }

        // El estado tiñe toda la fila, así que se recarga el listado.
        window.location.reload();
    } catch (e) {
        window.alert('No se pudo actualizar la factura: ' + e.message);
    }
}

document.querySelectorAll('[data-cancelar]').forEach((b) => {
    b.addEventListener('click', () => alternar(b.dataset.cancelar, 'cancelar'));
});
document.querySelectorAll('[data-pagada]').forEach((b) => {
    b.addEventListener('click', () => alternar(b.dataset.pagada, 'pagada'));
});

// ── Modal ────────────────────────────────────────────────────────────────
const modalFac = $f('facModal');
const formFac  = $f('formFactura');

// Las dos pestañas comparten el mismo formulario; la de PDF sólo lo rellena.
function activarPestana(cual) {
    $f('tab-pdf').classList.toggle('active', cual === 'pdf');
    $f('tab-manual').classList.toggle('active', cual === 'manual');
    $f('content-pdf').classList.toggle('active', cual === 'pdf');
    $f('content-manual').classList.toggle('active', cual === 'manual');
}

document.querySelectorAll('.modal-tab').forEach((tab) => {
    tab.addEventListener('click', () => activarPestana(tab.dataset.tab));
});

function abrirModalFactura(pestana) {
    formFac.reset();
    formFac.action = URL_FACTURAS;
    $f('fac-metodo').value = 'POST';
    $f('fac-tc').value = '{{ number_format(config('rentaltech.tipo_cambio_facturas'), 2, '.', '') }}';
    $f('fac-emision').value = '{{ now()->format('Y-m-d') }}';
    $f('fac-vencimiento').value = '';
    $f('fac-modal-titulo').textContent = 'Agregar Factura';
    $f('facGuardar').textContent = 'Guardar Factura';

    productosFac = [];
    pintarProductos();

    $f('pdfEstado').classList.remove('visible');
    $f('pdfResumen').classList.remove('visible');
    $f('pdfResumen').innerHTML = '';
    $f('pdfAviso').style.display = 'none';
    document.querySelectorAll('.fac-control.ia-lleno').forEach((c) => c.classList.remove('ia-lleno'));

    activarPestana(pestana);
    modalFac.classList.add('activo');
    actualizarEquivalencia();
}

function cerrarModalFactura() { modalFac.classList.remove('activo'); }

$f('btnFacturaManual').addEventListener('click', () => abrirModalFactura('manual'));
$f('btnSubirPdf').addEventListener('click', () => abrirModalFactura('pdf'));
$f('facCerrar').addEventListener('click', cerrarModalFactura);
$f('facCancelar').addEventListener('click', cerrarModalFactura);
modalFac.addEventListener('click', (e) => { if (e.target === modalFac) { cerrarModalFactura(); } });

document.querySelectorAll('.btn-editar-fac').forEach((boton) => {
    boton.addEventListener('click', () => {
        abrirModalFactura('manual');

        const d = boton.dataset;
        formFac.action = URL_FACTURAS + '/' + d.factura;
        $f('fac-metodo').value = 'PUT';
        $f('fac-modal-titulo').textContent = 'Editar factura ' + d.numero;

        $f('fac-numero').value      = d.numero;
        $f('fac-doc').value         = d.doc;
        $f('fac-guia').value        = d.guia;
        $f('fac-emision').value     = d.emision;
        $f('fac-vencimiento').value = d.vencimiento;
        $f('fac-importe').value     = d.importe;
        $f('fac-tc').value          = d.tc;
        $f('fac-cliente').value     = d.cliente;
        $f('fac-estado').value      = d.estado;

        // El detalle guardado se vuelve a cargar como filas editables.
        productosFac = (JSON.parse(d.lista || '[]') || []).map((p) => ({
            nombre:   p.nombre   || '',
            codigo:   p.codigo   || '',
            cantidad: p.cantidad || '',
            factor:   p.factor   ?? null,
            pres:     p.pres     || '',
            galones:  p.galones  || 0,
        }));
        pintarProductos();

        actualizarEquivalencia();
    });
});

// El equivalente en soles se muestra bajo el tipo de cambio.
function actualizarEquivalencia() {
    const soles = (parseFloat($f('fac-importe').value) || 0) * (parseFloat($f('fac-tc').value) || 0);
    $f('fac-equivalencia').textContent = 'S/ ' + soles.toFixed(2);
}

$f('fac-importe').addEventListener('input', actualizarEquivalencia);
$f('fac-tc').addEventListener('input', actualizarEquivalencia);

// ── Productos de la factura ──────────────────────────────────────────────
// Cada fila busca en el catálogo; si el código está en la matriz de galonaje
// se pide la cantidad y el galonaje sale solo, y si no, se escribe a mano.
const CATALOGO_PROD = @json($catalogo);
const FACTORES = @json($factores);

let productosFac = [];

function escapar(texto) {
    return String(texto).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
}

function pintarProductos() {
    const lista = $f('lista-prods');

    if (productosFac.length === 0) {
        lista.innerHTML = '<div class="mprod-vacio">Sin productos. Haz clic en "+ Agregar producto".</div>';
        calcularGalones();
        return;
    }

    lista.innerHTML = productosFac.map((p, i) => {
        const conFactor = p.factor !== null && p.factor !== undefined && p.factor !== '';
        const visible = (p.nombre || '') + (p.codigo ? '  [' + p.codigo + ']' : '');

        const derecha = conFactor
            ? '<div class="mprod-cant-grupo">' +
                  '<input type="number" class="mprod-cant-input" data-campo="cantidad" data-idx="' + i + '" ' +
                  'value="' + (p.cantidad || '') + '" min="0" step="0.01" placeholder="Cant."></div>' +
              '<span class="mprod-pres">' + escapar(p.pres || '') + '</span>' +
              '<span class="mprod-igual">=</span>' +
              '<span class="mprod-gl">' + ((parseFloat(p.cantidad) || 0) * p.factor).toFixed(2) + ' GL</span>'
            : '<input type="number" class="mprod-gal-input" data-campo="galones" data-idx="' + i + '" ' +
              'value="' + (p.galones || '') + '" min="0" step="0.01" placeholder="0.00">' +
              '<span class="mprod-unidad">GL</span>';

        return '<div class="mprod-fila">' +
            '<div class="mprod-buscador">' +
                '<input type="text" class="fac-control" data-campo="nombre" data-idx="' + i + '" ' +
                'value="' + escapar(visible) + '" autocomplete="off" placeholder="Buscar producto...">' +
                '<div class="prod-ac-list" data-lista="' + i + '"></div>' +
            '</div>' + derecha +
            '<button type="button" class="mprod-borrar" data-borrar="' + i + '" title="Eliminar">×</button>' +
        '</div>';
    }).join('');

    enlazarFilas();
    calcularGalones();
}

function enlazarFilas() {
    const lista = $f('lista-prods');

    lista.querySelectorAll('[data-campo]').forEach((campo) => {
        const p = productosFac[Number(campo.dataset.idx)];

        if (campo.dataset.campo === 'nombre') {
            campo.addEventListener('input', () => {
                // Al reescribir el nombre se pierde el código y su factor.
                const teniaFactor = p.factor !== null && p.factor !== undefined && p.factor !== '';
                p.nombre = campo.value.replace(/\s*\[.*?\]\s*$/, '').trim() || campo.value;
                p.codigo = '';
                p.factor = null;
                p.pres = '';

                if (teniaFactor) { pintarProductos(); return; }

                filtrarCatalogo(Number(campo.dataset.idx), campo.value);
                calcularGalones();
            });

            campo.addEventListener('focus', () => filtrarCatalogo(Number(campo.dataset.idx), campo.value));
            campo.addEventListener('blur', () => {
                setTimeout(() => lista.querySelector('[data-lista="' + campo.dataset.idx + '"]')?.classList.remove('abierto'), 200);
            });
        } else {
            campo.addEventListener('input', () => {
                p[campo.dataset.campo] = parseFloat(campo.value) || 0;

                if (campo.dataset.campo === 'cantidad' && p.factor) {
                    p.galones = p.cantidad * p.factor;
                    campo.closest('.mprod-fila').querySelector('.mprod-gl').textContent = p.galones.toFixed(2) + ' GL';
                }

                calcularGalones();
            });
        }
    });

    lista.querySelectorAll('[data-borrar]').forEach((boton) => {
        boton.addEventListener('click', () => {
            productosFac.splice(Number(boton.dataset.borrar), 1);
            pintarProductos();
        });
    });
}

function filtrarCatalogo(idx, termino) {
    const caja = $f('lista-prods').querySelector('[data-lista="' + idx + '"]');

    if (!caja) { return; }

    const q = (termino || '').replace(/\s*\[.*?\]\s*$/, '').trim().toLowerCase();
    const encontrados = (q === ''
        ? CATALOGO_PROD
        : CATALOGO_PROD.filter((p) =>
            p.n.toLowerCase().includes(q) || (p.cod && p.cod.toLowerCase().includes(q)))
    ).slice(0, 50);

    if (encontrados.length === 0) {
        caja.innerHTML = '<div class="prod-ac-empty">Sin coincidencias.</div>';
        caja.classList.add('abierto');
        return;
    }

    caja.innerHTML = encontrados.map((p, i) => {
        const meta = (p.cod ? '<span class="ac-cod">' + escapar(p.cod) + '</span>' : '') +
                     (p.c ? '<span class="ac-cat">' + escapar(p.c) + '</span>' : '');

        return '<div class="prod-ac-item" data-elegir="' + i + '">' +
            '<span class="ac-nombre">' + escapar(p.n) + '</span>' +
            (meta ? '<div>' + meta + '</div>' : '') + '</div>';
    }).join('');

    caja.querySelectorAll('[data-elegir]').forEach((item) => {
        item.addEventListener('mousedown', () => elegirDelCatalogo(idx, encontrados[Number(item.dataset.elegir)]));
    });

    caja.classList.add('abierto');
}

function elegirDelCatalogo(idx, elegido) {
    const p = productosFac[idx];
    const datos = elegido.cod ? FACTORES[elegido.cod] : null;

    p.nombre = elegido.n;
    p.codigo = elegido.cod || '';
    p.factor = datos ? datos.f : null;
    p.pres = datos ? datos.p : '';

    if (datos) { p.galones = (parseFloat(p.cantidad) || 0) * datos.f; }

    pintarProductos();

    // Con factor conocido, lo siguiente que se escribe es la cantidad.
    if (datos) {
        setTimeout(() => {
            const cant = $f('lista-prods').querySelectorAll('.mprod-cant-input')[0];
            const fila = $f('lista-prods').children[idx]?.querySelector('.mprod-cant-input') || cant;
            if (fila) { fila.focus(); fila.select(); }
        }, 30);
    }
}

function calcularGalones() {
    const total = productosFac.reduce((suma, p) => {
        const conFactor = p.factor !== null && p.factor !== undefined && p.factor !== '';

        return suma + (conFactor ? (parseFloat(p.cantidad) || 0) * p.factor : parseFloat(p.galones) || 0);
    }, 0);

    $f('mprodTotal').classList.toggle('visible', productosFac.length > 0);
    $f('mprodTotalVal').textContent = total.toFixed(2);

    // Estos dos campos ocultos son los que se guardan.
    $f('fac-galones').value = total.toFixed(2);
    $f('fac-producto').value = productosFac.map((p) => p.nombre).filter(Boolean).join(' / ');
}

$f('btnAgregarProd').addEventListener('click', () => {
    productosFac.push({ nombre: '', codigo: '', cantidad: '', factor: null, pres: '', galones: '' });
    pintarProductos();

    const campos = $f('lista-prods').querySelectorAll('[data-campo="nombre"]');
    campos[campos.length - 1]?.focus();
});

// El detalle viaja serializado en el campo oculto al enviar.
formFac.addEventListener('submit', () => {
    $f('fac-lista').value = JSON.stringify(productosFac.filter((p) => p.nombre).map((p) => ({
        codigo:   p.codigo,
        nombre:   p.nombre,
        cantidad: parseFloat(p.cantidad) || 0,
        factor:   p.factor ?? 0,
        pres:     p.pres,
        galones:  p.factor ? (parseFloat(p.cantidad) || 0) * p.factor : parseFloat(p.galones) || 0,
    })));
});

pintarProductos();

// ── Análisis del PDF con IA ──────────────────────────────────────────────
const zonaPdf = $f('pdfZona');

['dragover', 'dragleave', 'drop'].forEach((evento) => {
    zonaPdf.addEventListener(evento, (e) => {
        e.preventDefault();
        zonaPdf.classList.toggle('encima', evento === 'dragover');

        if (evento === 'drop' && e.dataTransfer.files.length) {
            analizarPdf(e.dataTransfer.files[0]);
        }
    });
});

$f('pdfArchivo').addEventListener('change', (e) => {
    if (e.target.files.length) { analizarPdf(e.target.files[0]); }
});

async function analizarPdf(archivo) {
    const estado = $f('pdfEstado');
    const resumen = $f('pdfResumen');

    estado.classList.add('visible');
    resumen.classList.remove('visible');
    $f('pdfEstadoTexto').textContent = 'Analizando ' + archivo.name + '…';

    const datos = new FormData();
    datos.append('pdf_file', archivo);

    try {
        const respuesta = await fetch(URL_ANALIZAR, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF_FAC, 'Accept': 'application/json' },
            body: datos,
        });

        const r = await respuesta.json();

        if (!r.ok) { throw new Error(r.error || 'No se pudo leer el documento'); }

        // Los datos extraídos se vuelcan al formulario para poder revisarlos.
        const volcar = (id, valor) => {
            if (valor === undefined || valor === null || valor === '') { return; }

            $f(id).value = valor;
            $f(id).classList.add('ia-lleno');
        };

        volcar('fac-numero', r.numero);
        volcar('fac-doc', r.doc);
        volcar('fac-guia', r.guia_remision);
        volcar('fac-emision', r.emision);
        volcar('fac-vencimiento', r.vencimiento);
        volcar('fac-importe', r.importe);
        volcar('fac-tc', r.tc);
        volcar('fac-cliente', r.cliente);

        // Cada ítem entra como una fila editable de la lista de productos.
        const items = r.items || [];

        productosFac = items.map((i) => ({
            codigo:   i.codigo || '',
            nombre:   i.nombre_matriz || i.nombre || i.descripcion || '',
            cantidad: i.cantidad || 0,
            factor:   i.factor_galones || null,
            pres:     i.presentacion || '',
            galones:  (i.cantidad || 0) * (i.factor_galones || 0),
        }));
        pintarProductos();

        estado.classList.remove('visible');
        $f('pdfAviso').style.display = 'flex';

        resumen.innerHTML =
            '<div class="pdf-resumen-item"><span class="pdf-resumen-label">Factura</span>' +
            '<span class="pdf-resumen-val">' + (r.numero || '—') + '</span></div>' +
            '<div class="pdf-resumen-item"><span class="pdf-resumen-label">Importe</span>' +
            '<span class="pdf-resumen-val">$ ' + Number(r.importe || 0).toFixed(2) + '</span></div>' +
            '<div class="pdf-resumen-item"><span class="pdf-resumen-label">Ítems</span>' +
            '<span class="pdf-resumen-val">' + items.length + '</span></div>' +
            '<div class="pdf-resumen-item"><span class="pdf-resumen-label">Galones</span>' +
            '<span class="pdf-resumen-val destacado">' + Number(r.galones_total || 0).toFixed(2) + ' GL</span></div>';
        resumen.classList.add('visible');

        actualizarEquivalencia();

        // Con los datos ya cargados, se pasa a la pestaña manual para revisarlos.
        activarPestana('manual');
    } catch (e) {
        estado.classList.remove('visible');
        window.alert('No se pudo analizar el PDF: ' + e.message);
    }
}
})();
</script>
@endpush
