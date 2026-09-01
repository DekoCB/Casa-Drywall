@extends('layouts.admin')

@section('title', 'Cobranzas')
@section('crumb', 'Vista general')

@push('styles')
    @vite('resources/css/modules/cobranzas.css')
@endpush

@section('content')
<div class="cob-wrapper">

{{-- ── Cabecera ────────────────────────────────────────────────────── --}}
<div class="cob-header">
    <div>
        <h2>Cuentas por Cobrar</h2>
        <p>Control de facturas, boletas y cobranzas pendientes</p>
    </div>
    <div class="cob-header-acciones">
        <a href="{{ route('admin.cobranzas.importar') }}" class="cbtn cbtn-claro">Importar Excel</a>

        <button type="button" class="cbtn cbtn-verde" data-modal="modalCobranza" data-nueva="1">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Nueva Cobranza
        </button>
    </div>
</div>

{{-- ── Indicadores ─────────────────────────────────────────────────── --}}
<div class="cob-stats">
    <div class="cob-stat">
        <div class="cob-stat-ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
            </svg>
        </div>
        <div class="cob-stat-val">S/ {{ number_format($totalPendiente, 2) }}</div>
        <div class="cob-stat-lbl">Total pendiente</div>
    </div>
    <div class="cob-stat">
        <div class="cob-stat-ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
        </div>
        <div class="cob-stat-val">{{ number_format($nVencidas) }}</div>
        <div class="cob-stat-lbl">Facturas vencidas</div>
    </div>
    <div class="cob-stat">
        <div class="cob-stat-ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
            </svg>
        </div>
        <div class="cob-stat-val">{{ number_format($nPendientes) }}</div>
        <div class="cob-stat-lbl">Pendientes</div>
    </div>
    <div class="cob-stat">
        <div class="cob-stat-ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
        </div>
        <div class="cob-stat-val">{{ number_format($nPagadas) }}</div>
        <div class="cob-stat-lbl">Pagadas</div>
    </div>
</div>

{{-- ── Filtros ─────────────────────────────────────────────────────── --}}
<form method="GET" action="{{ route('admin.cobranzas.index') }}" class="cob-filtros">
    <div class="cob-filtro-fila">
        <div class="cob-campo">
            <label for="q">Buscar cliente o número</label>
            <input type="search" id="q" name="q" value="{{ $filtros['q'] }}" placeholder="Nombre o N° comprobante…">
        </div>
        <div class="cob-campo">
            <label for="estado">Estado</label>
            <select id="estado" name="estado" onchange="this.form.submit()">
                <option value="">Todos los estados</option>
                <option value="pendiente" @selected($filtros['estado'] === 'pendiente')>Pendiente</option>
                <option value="pagada" @selected($filtros['estado'] === 'pagada')>Pagada</option>
            </select>
        </div>
        <div class="cob-campo">
            <label for="tipo">Tipo</label>
            <select id="tipo" name="tipo" onchange="this.form.submit()">
                <option value="">Todos los tipos</option>
                @foreach ($tipos as $codigo => $etiqueta)
                    <option value="{{ $codigo }}" @selected($filtros['tipo'] === $codigo)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="cob-fechas">
        <span class="cob-fechas-tit">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            Filtrar por fecha:
        </span>

        <span>Mes/Año</span>
        <input type="month" name="mes" value="{{ $filtros['mes'] }}">

        <span class="cob-fechas-sep">|</span>

        <span>Desde</span>
        <input type="date" name="desde" value="{{ $filtros['desde'] }}">

        <span>Hasta</span>
        <input type="date" name="hasta" value="{{ $filtros['hasta'] }}">

        <span class="cob-fechas-fin">
            @if (array_filter($filtros) !== [])
                <a href="{{ route('admin.cobranzas.index') }}" class="cbtn cbtn-claro cbtn-sm">Limpiar</a>
            @endif
            <button type="submit" class="cbtn cbtn-verde cbtn-sm">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                Buscar
            </button>
        </span>
    </div>
</form>

{{-- ── Tarjeta: resumen del cliente buscado ───────────────────────────── --}}
@if ($clienteResumen)
    <div class="cob-cliente-card">
        <div class="cob-cliente-ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
        </div>
        <div class="cob-cliente-dato">
            <span>Cliente</span>
            <b>{{ $clienteResumen['nombre'] }}</b>
        </div>
        <div class="cob-cliente-dato">
            <span>Total que debe</span>
            <b class="cob-cliente-deuda">S/ {{ number_format($clienteResumen['deuda'], 2) }}</b>
        </div>
        <div class="cob-cliente-dato">
            <span>Facturas pendientes</span>
            <b class="cob-cliente-facturas">{{ $clienteResumen['nPendientes'] }} {{ Str::plural('Factura', $clienteResumen['nPendientes']) }}</b>
        </div>
    </div>
@endif

{{-- ── Tabla ───────────────────────────────────────────────────────── --}}
@php $invalidas = $cobranzas->filter(fn ($c) => $c->vencimientoInvalido())->count(); @endphp

<div class="cob-card">
    <div class="cob-card-tit">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
        </svg>
        Cobranzas
        <small>({{ number_format($totalRegistros) }} registros)</small>

        <span class="cob-tit-totales">
            <span>Monto total <b>S/ {{ number_format($totalFiltroMonto, 2) }}</b></span>
            <span>Pagado <b>S/ {{ number_format($totalFiltroPagado, 2) }}</b></span>
            <span>Pendiente <b class="cob-tit-pend">S/ {{ number_format($totalFiltroPendiente, 2) }}</b></span>
        </span>
    </div>

    @if ($invalidas > 0)
        <div class="cob-aviso">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            Se detectaron {{ $invalidas }} cobranza(s) con fecha de vencimiento inválida. Edítalas para corregirlas.
        </div>
    @endif

    <div class="cob-scroll">
        <table class="cob-tabla">
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Número</th>
                    <th>Fecha Emisión</th>
                    <th>Vencimiento</th>
                    <th>Días Vencidos</th>
                    <th>Cliente</th>
                    <th>Monto Total</th>
                    <th>Monto Pagado</th>
                    <th>Monto Pendiente</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($cobranzas as $cobranza)
                @php
                    $mala   = $cobranza->vencimientoInvalido();
                    $dias   = $cobranza->diasVencidos();
                    $pagada = $cobranza->estado === 'pagada';

                    $clase = match (true) {
                        $mala             => 'cob-dias-invalida',
                        $pagada           => 'cob-dias-ok',
                        $dias === null,
                        $dias <= 0        => 'cob-dias-ok',
                        $dias <= 7        => 'cob-dias-pronto',
                        $dias <= 30       => 'cob-dias-tarde',
                        default           => 'cob-dias-muytarde',
                    };

                    $datosModal = [
                        'id'                => $cobranza->id,
                        'tipo'              => $cobranza->tipo,
                        'numero'            => $cobranza->numero,
                        'fecha_emision'     => $cobranza->fecha_emision?->format('Y-m-d'),
                        'fecha_vencimiento' => $cobranza->fecha_vencimiento?->format('Y-m-d'),
                        'cliente_nombre'    => $cobranza->cliente_nombre,
                        'cliente_id'        => $cobranza->cliente_id,
                        'monto_total'       => $cobranza->monto_total,
                        'monto_pagado'      => $cobranza->monto_pagado,
                        'monto_pendiente'   => $cobranza->monto_pendiente,
                        'observaciones'     => $cobranza->observaciones,
                    ];
                @endphp
                <tr @class(['cob-fila-mala' => $mala])>
                    <td>
                        <span class="cob-tipo cob-tipo-{{ strtolower($cobranza->tipo ?: 'ot') }}">{{ $cobranza->tipo }}</span>
                    </td>
                    <td><span class="cob-numero">{{ $cobranza->numero }}</span></td>
                    <td>{{ $cobranza->fecha_emision?->format('d/m/Y') ?: '—' }}</td>
                    <td>
                        @if ($mala)
                            <span style="color:#8a6100;font-weight:700;">Sin fecha válida</span>
                        @else
                            {{ $cobranza->fecha_vencimiento->format('d/m/Y') }}
                        @endif
                    </td>
                    <td>
                        <span class="cob-dias {{ $clase }}">
                            @if ($mala)
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                    <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                                </svg>
                                Fecha inválida
                            @elseif ($pagada)
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                                Cobrada
                            @elseif ($dias <= 0)
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                                    <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                                </svg>
                                {{ abs($dias) }} días
                            @else
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4">
                                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                                </svg>
                                {{ $dias }} días
                            @endif
                        </span>
                    </td>
                    <td class="cob-cliente">{{ $cobranza->cliente_nombre ?: '—' }}</td>
                    <td><span class="cob-monto">S/ {{ number_format($cobranza->monto_total, 2) }}</span></td>
                    <td><span class="cob-monto-pag">S/ {{ number_format($cobranza->monto_pagado, 2) }}</span></td>
                    <td><span class="cob-monto-pend">S/ {{ number_format($cobranza->monto_pendiente, 2) }}</span></td>
                    <td>
                        <span class="cob-estado cob-estado-{{ $cobranza->estado }}">
                            @if ($pagada)
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2"><polyline points="20 6 9 17 4 12"/></svg>
                                Pagada
                            @else
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                Pendiente
                            @endif
                        </span>
                    </td>
                    <td>
                        <span class="cob-acciones">
                            @unless ($pagada)
                                <button type="button" class="cob-ico cob-ico-verde" title="Registrar pago"
                                        data-modal="modalPago" data-cobranza="{{ json_encode($datosModal) }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
                                        <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                    </svg>
                                </button>
                            @endunless

                            @if ((float) $cobranza->monto_pagado > 0)
                                <button type="button" class="cob-ico cob-ico-azul" title="Ver detalle de pagos"
                                        data-modal="modalDetalle" data-cobranza="{{ json_encode($datosModal) }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </button>
                            @endif

                            <button type="button" class="cob-ico cob-ico-suave" title="Editar"
                                    data-modal="modalCobranza" data-cobranza="{{ json_encode($datosModal) }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/>
                                </svg>
                            </button>

                            <form method="POST" action="{{ route('admin.cobranzas.destroy', $cobranza) }}"
                                  data-confirmar="¿Eliminar la cobranza {{ $cobranza->tipo }} {{ $cobranza->numero }}?">
                                @csrf @method('DELETE')
                                <button type="submit" class="cob-ico cob-ico-rojo" title="Eliminar">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="3 6 5 6 21 6"/>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                    </svg>
                                </button>
                            </form>
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="cob-vacio">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                        </svg>
                        No hay cobranzas que coincidan con los filtros
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if ($cobranzas->hasPages())
        <div class="cob-paginacion">{{ $cobranzas->links() }}</div>
    @endif
</div>

{{-- ── Modal: alta y edición ───────────────────────────────────────── --}}
<div class="modal-overlay" id="modalCobranza">
    <div class="modal-card">
        <div class="modal-header">
            <h3>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                </svg>
                <span id="tituloModalCobranza">Nueva Cobranza</span>
            </h3>
            <button type="button" class="modal-close" data-cerrar="modalCobranza" aria-label="Cerrar">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <form method="POST" action="{{ route('admin.cobranzas.store') }}" id="formCobranza">
                @csrf
                <input type="hidden" name="registro_id" id="cob_registro_id" value="">

                <div class="form-group" style="margin-bottom:20px;">
                    <label>Tipo de Comprobante <span>*</span></label>
                    <div class="cob-tipos">
                        @foreach ($tipos as $codigo => $etiqueta)
                            <div class="cob-tipo-opt">
                                <input type="radio" name="tipo" id="tipo{{ $codigo }}" value="{{ $codigo }}"
                                       @checked($loop->first) required>
                                <label for="tipo{{ $codigo }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                        <polyline points="14 2 14 8 20 8"/>
                                        @if ($codigo !== 'FT') <line x1="8" y1="13" x2="16" y2="13"/> @endif
                                        @if ($codigo === 'NC' || $codigo === 'OT') <line x1="8" y1="17" x2="12" y2="17"/> @endif
                                    </svg>
                                    {{ $etiqueta }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="cob_numero">Número <span>*</span></label>
                        <input type="text" id="cob_numero" name="numero" required maxlength="50" placeholder="F01-0000186">
                    </div>
                    <div class="form-group">
                        <label for="cob_fecha_emision">Fecha Emisión <span>*</span></label>
                        <input type="date" id="cob_fecha_emision" name="fecha_emision" required
                               min="2000-01-01" max="2100-12-31" value="{{ now()->toDateString() }}">
                    </div>
                    <div class="form-group">
                        <label for="cob_fecha_vencimiento">Fecha Vencimiento <span>*</span></label>
                        <input type="date" id="cob_fecha_vencimiento" name="fecha_vencimiento" required
                               min="2000-01-01" max="2100-12-31">
                    </div>
                </div>

                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label for="cob_cliente_nombre">Nombre del Cliente <span>*</span></label>
                        <div class="cob-autocomplete">
                            <input type="text" id="cob_cliente_nombre" name="cliente_nombre" required maxlength="255"
                                   placeholder="Nombre o razón social" autocomplete="off">
                            <div class="cob-sugerencias" id="sugerenciasCliente"></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="cob_cliente_id">Cliente en Sistema</label>
                        <select id="cob_cliente_id" name="cliente_id">
                            <option value="">— Seleccionar (opcional) —</option>
                            @foreach ($clientes as $cliente)
                                <option value="{{ $cliente->id }}" data-nombre="{{ $cliente->nombres }}">
                                    {{ $cliente->nombres }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="cob-importes">
                    <div class="cob-importes-tit">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
                            <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                        Importes
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="cob_monto_total">Monto Total <span>*</span></label>
                            <input type="number" id="cob_monto_total" name="monto_total" step="0.01" min="0" required placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="cob_monto_pagado">Monto Pagado</label>
                            <input type="number" id="cob_monto_pagado" name="monto_pagado" step="0.01" min="0" value="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label>Monto Pendiente</label>
                            <div class="cob-pendiente" id="cobPendiente">
                                <span>Pendiente</span>
                                <b id="cobPendienteVal">S/ 0.00</b>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="cob_observaciones">Observaciones</label>
                    <textarea id="cob_observaciones" name="observaciones" placeholder="Condiciones, notas, referencias…"></textarea>
                </div>

                <div class="cob-modal-pie">
                    <button type="submit" class="cbtn cbtn-verde">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        Guardar Cobranza
                    </button>
                    <button type="button" class="cbtn cbtn-oscuro" data-cerrar="modalCobranza">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Modal: registrar pago ───────────────────────────────────────── --}}
<x-modal id="modalPago" titulo="Registrar pago" :oscuro="true">
    <form method="POST" action="" id="formPago">
        @csrf
        <input type="hidden" name="cobranza_id" id="pago_cobranza_id" value="">

        <div class="form-group">
            <label>Documento</label>
            <input type="text" id="pago_documento" readonly>
        </div>
        <div class="form-group">
            <label>Saldo pendiente</label>
            <input type="text" id="pago_pendiente" readonly>
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label for="monto_pago">Monto del pago <span>*</span></label>
                <input type="number" id="monto_pago" name="monto_pago" step="0.01" min="0.01" required>
            </div>
            <div class="form-group">
                <label for="fecha_pago">Fecha del pago <span>*</span></label>
                <input type="date" id="fecha_pago" name="fecha_pago" value="{{ now()->toDateString() }}" required>
            </div>
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label for="pago_medio">Medio de pago <span>*</span></label>
                <select id="pago_medio" required>
                    <option value="Efectivo">Efectivo</option>
                    <option value="Transferencia">Transferencia</option>
                    <option value="Yape">Yape</option>
                    <option value="Plin">Plin</option>
                    <option value="BCP">BCP</option>
                    <option value="Interbank">Interbank</option>
                </select>
            </div>
            <div class="form-group" id="pago_operacion_grupo" style="display:none;">
                <label for="pago_operacion">N° de operación <span>*</span></label>
                <input type="text" id="pago_operacion" maxlength="50" placeholder="N° de operación">
            </div>
        </div>

        <div class="form-group">
            <label for="pago_nota">Observaciones <span class="lbl-opcional">(opcional)</span></label>
            <input type="text" id="pago_nota" maxlength="150" placeholder="Detalle adicional…">
        </div>

        <input type="hidden" id="pago_observaciones" name="observaciones" value="">

        <div class="header-btns" style="justify-content:flex-end;">
            <button type="button" class="cbtn cbtn-claro" data-cerrar="modalPago">Cancelar</button>
            <button type="submit" class="cbtn cbtn-verde">Registrar pago</button>
        </div>
    </form>
</x-modal>

{{-- ── Modal: detalle de pagos ─────────────────────────────────────── --}}
<x-modal id="modalDetalle" titulo="Detalle de pagos">
    <div id="detallePagos"></div>
</x-modal>


{{-- ── Aviso: cobranzas con 90 días o más de atraso ────────────────── --}}
@if ($alertas90->isNotEmpty())
    <div class="modal-overlay" id="modalAlertas90">
        <div class="modal-card">
            <div class="modal-header modal-header-rojo">
                <h3>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    {{ $nAlertas90 }} cobranza(s) con +90 días vencidos
                </h3>
                <button type="button" class="modal-close" data-cerrar="modalAlertas90" aria-label="Cerrar">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <p class="cob-alerta-intro">
                    Estos documentos llevan más de <b>90 días</b> sin pagar.
                </p>

                <div class="cob-alerta-scroll">
                    <table class="cob-alerta-tabla">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Comprobante</th>
                                <th style="text-align:right;">Pendiente</th>
                                <th style="text-align:center;">Días</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($alertas90 as $alerta)
                            <tr>
                                <td class="cob-alerta-cliente">{{ $alerta->cliente_nombre ?: '—' }}</td>
                                <td>
                                    @if (trim((string) $alerta->numero) !== '')
                                        <a href="{{ route('admin.cobranzas.index', ['q' => $alerta->numero]) }}"
                                           class="cob-alerta-doc">{{ $alerta->numero }}</a>
                                    @else
                                        <span class="cob-alerta-doc-vacio">Sin documento</span>
                                    @endif
                                </td>
                                <td class="cob-alerta-monto">S/ {{ number_format($alerta->monto_pendiente, 2) }}</td>
                                <td style="text-align:center;">
                                    <span class="cob-alerta-dias">{{ number_format($alerta->dias) }} días</span>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($nAlertas90 > $alertas90->count())
                    <p class="cob-alerta-pie">
                        Mostrando las {{ $alertas90->count() }} más atrasadas de {{ $nAlertas90 }}.
                    </p>
                @endif

                <button type="button" class="cbtn cbtn-rojo" data-cerrar="modalAlertas90">Entendido, cerrar</button>
            </div>
        </div>
    </div>
@endif

<script id="listaClientes" type="application/json">@json($clientes->map(fn ($c) => ['id' => $c->id, 'n' => $c->nombres]))</script>

</div>{{-- /cob-wrapper --}}
@endsection

@push('scripts')
<script>
const formCobranza = document.getElementById('formCobranza');
const formPago     = document.getElementById('formPago');
const clientes     = JSON.parse(document.getElementById('listaClientes')?.textContent || '[]');

const campoTotal  = document.getElementById('cob_monto_total');
const campoPagado = document.getElementById('cob_monto_pagado');
const campoNombre = document.getElementById('cob_cliente_nombre');
const sugerencias = document.getElementById('sugerenciasCliente');

// ── Pendiente en vivo ────────────────────────────────────────────────────
function calcularPendiente() {
    const total  = parseFloat(campoTotal.value) || 0;
    const pagado = parseFloat(campoPagado.value) || 0;
    const saldo  = total - pagado;

    document.getElementById('cobPendienteVal').textContent = 'S/ ' + saldo.toFixed(2);
    // En rojo cuando lo pagado supera el total: el backend lo rechaza.
    document.getElementById('cobPendiente').classList.toggle('is-negativo', saldo < -0.009);
}

campoTotal.addEventListener('input', calcularPendiente);
campoPagado.addEventListener('input', calcularPendiente);

// ── Alta y edición ───────────────────────────────────────────────────────
document.addEventListener('click', (e) => {
    const boton = e.target.closest('[data-modal="modalCobranza"]');
    if (!boton) return;

    formCobranza.reset();
    formCobranza.querySelector('[name="_method"]')?.remove();
    sugerencias.classList.remove('is-visible');

    const datos = boton.dataset.cobranza ? JSON.parse(boton.dataset.cobranza) : null;

    document.getElementById('tituloModalCobranza').textContent = datos ? 'Editar Cobranza' : 'Nueva Cobranza';
    document.getElementById('cob_registro_id').value = datos?.id ?? '';

    for (const campo of ['numero', 'cliente_id', 'cliente_nombre', 'fecha_emision',
                         'fecha_vencimiento', 'monto_total', 'monto_pagado', 'observaciones']) {
        document.getElementById('cob_' + campo).value = datos?.[campo] ?? '';
    }

    // El tipo es un grupo de radios, no un campo de texto.
    const tipo = datos?.tipo || '{{ array_key_first($tipos) }}';
    const radio = formCobranza.querySelector(`[name="tipo"][value="${tipo}"]`);
    if (radio) radio.checked = true;

    if (!datos) {
        document.getElementById('cob_fecha_emision').value = '{{ now()->toDateString() }}';
        campoPagado.value = 0;
    }

    calcularPendiente();
});

// ── Autocompletado del nombre de cliente ─────────────────────────────────
campoNombre.addEventListener('input', function () {
    const texto = this.value.trim().toLowerCase();

    if (texto.length < 2) {
        sugerencias.classList.remove('is-visible');
        return;
    }

    const encontrados = clientes.filter((c) => c.n.toLowerCase().includes(texto)).slice(0, 8);

    if (encontrados.length === 0) {
        sugerencias.classList.remove('is-visible');
        return;
    }

    sugerencias.innerHTML = encontrados
        .map((c) => `<div class="cob-sugerencia" data-id="${c.id}" data-nombre="${c.n}">${c.n}</div>`)
        .join('');
    sugerencias.classList.add('is-visible');
});

// Elegir una sugerencia rellena el nombre y vincula el cliente.
sugerencias.addEventListener('mousedown', (e) => {
    const opcion = e.target.closest('.cob-sugerencia');
    if (!opcion) return;

    campoNombre.value = opcion.dataset.nombre;
    document.getElementById('cob_cliente_id').value = opcion.dataset.id;
    sugerencias.classList.remove('is-visible');
});

campoNombre.addEventListener('blur', () => sugerencias.classList.remove('is-visible'));

// Elegir un cliente del sistema rellena el nombre si aún está vacío.
document.getElementById('cob_cliente_id').addEventListener('change', function () {
    const nombre = this.selectedOptions[0]?.dataset.nombre;
    if (nombre && campoNombre.value.trim() === '') campoNombre.value = nombre;
});

formCobranza.addEventListener('submit', function () {
    const id = document.getElementById('cob_registro_id').value;
    if (!id) return;

    this.action = '{{ url('admin/cobranzas') }}/' + id;
    if (!this.querySelector('[name="_method"]')) {
        const metodo = document.createElement('input');
        metodo.type = 'hidden';
        metodo.name = '_method';
        metodo.value = 'PUT';
        this.appendChild(metodo);
    }
});

// ── Registrar pago ───────────────────────────────────────────────────────
const MEDIOS_VIRTUALES = ['Transferencia', 'Yape', 'Plin', 'BCP', 'Interbank'];
const pagoMedio         = document.getElementById('pago_medio');
const pagoOperacionGrupo = document.getElementById('pago_operacion_grupo');
const pagoOperacion      = document.getElementById('pago_operacion');
const pagoNota           = document.getElementById('pago_nota');

function actualizarOperacion() {
    const esVirtual = MEDIOS_VIRTUALES.includes(pagoMedio.value);
    pagoOperacionGrupo.style.display = esVirtual ? '' : 'none';
    pagoOperacion.required = esVirtual;
    if (!esVirtual) pagoOperacion.value = '';
}

pagoMedio.addEventListener('change', actualizarOperacion);

document.addEventListener('click', (e) => {
    const boton = e.target.closest('[data-modal="modalPago"]');
    if (!boton) return;

    const datos = JSON.parse(boton.dataset.cobranza);
    const pendiente = parseFloat(datos.monto_pendiente) || 0;

    document.getElementById('pago_cobranza_id').value = datos.id;
    document.getElementById('pago_documento').value   = datos.tipo + ' ' + datos.numero + ' — ' + datos.cliente_nombre;
    document.getElementById('pago_pendiente').value   = 'S/ ' + pendiente.toFixed(2);

    const monto = document.getElementById('monto_pago');
    monto.max   = pendiente;
    monto.value = pendiente.toFixed(2);

    pagoMedio.value = 'Efectivo';
    pagoOperacion.value = '';
    pagoNota.value = '';
    actualizarOperacion();
});

formPago.addEventListener('submit', function () {
    this.action = '{{ url('admin/cobranzas') }}/' + document.getElementById('pago_cobranza_id').value + '/pago';

    const partes = [pagoMedio.value];
    if (pagoOperacion.value.trim() !== '') partes.push('Op. ' + pagoOperacion.value.trim());
    if (pagoNota.value.trim() !== '') partes.push(pagoNota.value.trim());

    document.getElementById('pago_observaciones').value = partes.join(' - ');
});

// ── Aviso de mora: salta una sola vez por sesión del navegador ───────────
const avisoMora = document.getElementById('modalAlertas90');

if (avisoMora && !sessionStorage.getItem('cobranzas_aviso90')) {
    avisoMora.classList.add('active');
    document.body.style.overflow = 'hidden';
}

// Cerrarlo por cualquier vía lo marca como visto.
avisoMora?.addEventListener('click', (e) => {
    if (e.target.closest('[data-cerrar="modalAlertas90"]') || e.target === avisoMora) {
        sessionStorage.setItem('cobranzas_aviso90', '1');
    }
});

// ── Detalle de pagos: se arma leyendo las notas de la cobranza ────────────
document.addEventListener('click', (e) => {
    const boton = e.target.closest('[data-modal="modalDetalle"]');
    if (!boton) return;

    const datos = JSON.parse(boton.dataset.cobranza);
    const notas = (datos.observaciones || '').match(/\[Pago[^\]]*\][^\n\[]*/g) || [];

    const soles = (v) => 'S/ ' + (parseFloat(v) || 0).toFixed(2);

    let html = `<p style="margin:0 0 14px;font-size:14px;">
            <b>${datos.tipo} ${datos.numero}</b> — ${datos.cliente_nombre}<br>
            <span style="color:#6b6f78;font-size:13px;">
                Total ${soles(datos.monto_total)} · Pagado ${soles(datos.monto_pagado)} ·
                Pendiente ${soles(datos.monto_pendiente)}
            </span>
        </p>`;

    if (notas.length) {
        html += '<ul style="margin:0;padding-left:18px;font-size:13.5px;line-height:1.7;">'
            + notas.map((n) => `<li>${n.trim()}</li>`).join('')
            + '</ul>';
    } else {
        html += '<p style="color:#6b6f78;font-size:13.5px;margin:0;">No hay notas de pago registradas para este documento.</p>';
    }

    document.getElementById('detallePagos').innerHTML = html;
});
</script>
@endpush
