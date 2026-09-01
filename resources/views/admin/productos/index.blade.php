@extends('layouts.admin')

@section('title', 'Productos')
@section('crumb', 'Vista general')

@push('styles')
    @vite('resources/css/modules/productos.css')
@endpush

@section('content')
<div class="prod-wrapper">

<x-prod-tabs activa="productos" />

@php
    $almacenActual = $almacenes->firstWhere('id', $almacenSel);
    $sinPeriodo    = $periodo['mes'] === '' && $periodo['desde'] === '' && $periodo['hasta'] === '';
    $hayFiltros    = $busqueda !== '' || $categoriaSel || $marcaSel;
@endphp

<div class="prod-hero prod-hero-verde">
    <div class="prod-hero-texto">
        <h2>Catálogo KENDALL</h2>
        <p>Productos Kendall, P66 y Royal Purple</p>
    </div>
    <div class="prod-hero-acciones">
        <button type="button" class="pbtn pbtn-verde" data-modal="modalProducto">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" width="15" height="15">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Nuevo Producto
        </button>
    </div>
</div>

{{-- ── Selector de almacén ─────────────────────────────────────────── --}}
<div class="prod-almacenes">
    @foreach ($almacenes as $almacen)
        @php
            $datos = $resumenAlmacen[$almacen->id] ?? ['unidades' => 0, 'productos' => 0];
        @endphp
        <a href="{{ route('admin.productos.index', array_filter(request()->except('page', 'almacen')) + ['almacen' => $almacen->id]) }}"
           class="prod-alm @if($almacen->id === $almacenSel) is-activo @endif">
            <span class="prod-alm-ico">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9.5 12 3l9 6.5V21H3z"/><path d="M9 21v-7h6v7"/>
                </svg>
            </span>
            <span class="prod-alm-txt">
                <span class="prod-alm-nom">{{ $almacen->nombre }}</span>
                <span class="prod-alm-uds">{{ number_format($datos['unidades']) }} unidades</span>
            </span>
            @if (($bajosAlmacen[$almacen->id] ?? 0) > 0)
                <span class="prod-alm-aviso">⚠ {{ $bajosAlmacen[$almacen->id] }} bajo stock</span>
            @endif
        </a>
    @endforeach
</div>

<div class="prod-banner">
    <span class="prod-banner-ico">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 9.5 12 3l9 6.5V21H3z"/><path d="M9 21v-7h6v7"/>
        </svg>
    </span>
    <span class="prod-banner-item">
        <span>Mostrando stock de</span>
        <b>{{ $almacenActual->nombre ?? 'Sin almacén' }}</b>
    </span>
    @foreach ($almacenes->where('id', '!=', $almacenSel) as $otro)
        <span class="prod-banner-item">
            <span>{{ $otro->nombre }}:</span>
            <b>{{ number_format($resumenAlmacen[$otro->id]['unidades'] ?? 0) }} uds</b>
        </span>
    @endforeach
    <span class="prod-banner-item prod-banner-fin">
        <span>{{ $hayFiltros ? 'Stock filtrado aquí' : 'Stock total aquí' }}</span>
        <b>{{ number_format($stockAqui) }} uds</b>
    </span>
</div>

{{-- ── Indicadores ─────────────────────────────────────────────────── --}}
<div class="prod-stats">
    <div class="prod-stat">
        <span class="prod-stat-ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2 3 7v10l9 5 9-5V7z"/><path d="m3 7 9 5 9-5"/><path d="M12 22V12"/>
            </svg>
        </span>
        <span class="prod-stat-txt">
            <span class="prod-stat-val">{{ number_format($totalProductos) }}</span>
            <span class="prod-stat-lbl">Total productos</span>
        </span>
    </div>
    <div class="prod-stat">
        <span class="prod-stat-ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="13"/><line x1="12" y1="16" x2="12" y2="16"/>
            </svg>
        </span>
        <span class="prod-stat-txt">
            <span class="prod-stat-val">{{ number_format($stockBajo) }}</span>
            <span class="prod-stat-lbl">Stock bajo <span class="prod-stat-sub">· {{ $almacenActual->nombre ?? '—' }}</span></span>
        </span>
    </div>
    <div class="prod-stat">
        <span class="prod-stat-ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="2" x2="12" y2="22"/><path d="M17 6.5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
            </svg>
        </span>
        <span class="prod-stat-txt">
            <span class="prod-stat-val">S/ {{ number_format($valorInventario) }}</span>
            <span class="prod-stat-lbl">Valor inventario <span class="prod-stat-sub">· {{ $almacenActual->nombre ?? '—' }}</span></span>
        </span>
    </div>
</div>

{{-- ── Filtros del catálogo ────────────────────────────────────────── --}}
<form method="GET" action="{{ route('admin.productos.index') }}" class="prod-panel">
    <input type="hidden" name="almacen" value="{{ $almacenSel }}">
    @foreach (['vmes', 'vdesde', 'vhasta'] as $oculto)
        @if ($periodo[$oculto === 'vmes' ? 'mes' : ($oculto === 'vdesde' ? 'desde' : 'hasta')] !== '')
            <input type="hidden" name="{{ $oculto }}" value="{{ $periodo[$oculto === 'vmes' ? 'mes' : ($oculto === 'vdesde' ? 'desde' : 'hasta')] }}">
        @endif
    @endforeach

    <div class="prod-filtros">
        <div class="prod-campo">
            <label for="categoria">Filtrar por categoría</label>
            <select id="categoria" name="categoria" onchange="this.form.submit()">
                <option value="">📦 Todas las categorías</option>
                @foreach ($categorias as $cat)
                    <option value="{{ $cat->id }}" @selected($categoriaSel == $cat->id)>{{ $cat->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="prod-campo">
            <label for="marca">Filtrar por marca</label>
            <select id="marca" name="marca" onchange="this.form.submit()">
                <option value="">Todas las marcas</option>
                @foreach ($marcas as $m)
                    <option value="{{ $m->id }}" @selected($marcaSel == $m->id)>{{ $m->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="prod-campo">
            <label for="q">Buscar producto</label>
            <input type="search" id="q" name="q" value="{{ $busqueda }}" placeholder="Nombre o código…">
        </div>
    </div>
</form>

{{-- ── Ventas por periodo ──────────────────────────────────────────── --}}
<form method="GET" action="{{ route('admin.productos.index') }}" class="prod-periodo">
    <input type="hidden" name="almacen" value="{{ $almacenSel }}">
    @if ($busqueda !== '')  <input type="hidden" name="q" value="{{ $busqueda }}">           @endif
    @if ($categoriaSel)     <input type="hidden" name="categoria" value="{{ $categoriaSel }}"> @endif
    @if ($marcaSel)         <input type="hidden" name="marca" value="{{ $marcaSel }}">         @endif

    <span class="prod-periodo-tit">📊 Ventas por periodo:</span>

    <a href="{{ route('admin.productos.index', array_filter(request()->except('page', 'vmes', 'vdesde', 'vhasta'))) }}"
       class="prod-chip-todos @if($sinPeriodo) is-activo @endif">Todos</a>

    <span class="prod-periodo-lbl">Mes:</span>
    <input type="month" name="vmes" value="{{ $periodo['mes'] }}" onchange="this.form.submit()">

    <span class="prod-periodo-lbl">Desde</span>
    <input type="date" name="vdesde" value="{{ $periodo['desde'] }}" onchange="this.form.submit()">

    <span class="prod-periodo-lbl">Hasta</span>
    <input type="date" name="vhasta" value="{{ $periodo['hasta'] }}" onchange="this.form.submit()">

    <button type="submit" class="pbtn pbtn-claro pbtn-sm">Aplicar</button>
</form>

{{-- ── Inventario ──────────────────────────────────────────────────── --}}
<div class="prod-tabla-card">
    <div class="prod-tabla-head">
        <h3>Inventario — {{ $almacenActual->nombre ?? 'Sin almacén' }}</h3>
        <span>
            {{ number_format($productos->count()) }} producto(s)
            @unless ($sinPeriodo)
                · ventas del periodo seleccionado
            @endunless
        </span>
    </div>

    <div class="prod-scroll">
        <table class="prod-tabla">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Marca</th>
                    <th>Presentación</th>
                    <th>Viscosidad</th>
                    <th>Stock por almacén</th>
                    <th>Galonaje stock</th>
                    <th>Vendidos</th>
                    <th>Precio venta</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($productos as $producto)
                @php
                    $porAlmacen = $producto->stockPorAlmacen->keyBy('almacen_id');
                    $codigo     = trim((string) $producto->codigo);
                    $matriz     = $factores[$codigo] ?? null;
                    $galones    = $matriz ? $producto->stock_almacen * (float) ($matriz['f'] ?? 0) : null;
                    $venta      = $vendidos[$codigo !== '' ? $codigo : '#'.$producto->id] ?? null;

                    // Todo lo que el modal necesita para precargarse, en un solo atributo.
                    $datosModal = [
                        'id'               => $producto->id,
                        'codigo'           => $producto->codigo,
                        'nombre'           => $producto->nombre,
                        'categoria_id'     => $producto->categoria_id,
                        'marca_id'         => $producto->marca_id,
                        'presentacion'     => $producto->presentacion,
                        'viscosidad'       => $producto->viscosidad,
                        'precio_compra'    => $producto->precio_compra,
                        'precio_venta'     => $producto->precio_venta,
                        'precio_alquiler'  => $producto->precio_alquiler,
                        'stock_minimo'     => $producto->stock_minimo,
                        'peso'             => $producto->peso,
                        'descripcion'      => $producto->descripcion,
                        'especificaciones' => $producto->especificaciones,
                        'factor_gl'        => (float) ($matriz['f'] ?? 0),
                        'stocks'           => $almacenes->mapWithKeys(
                            fn ($a) => [$a->id => (int) ($porAlmacen[$a->id]->stock ?? 0)]
                        ),
                    ];
                @endphp
                <tr>
                    <td class="prod-cod">{{ $producto->codigo ?: '—' }}</td>
                    <td class="prod-nom">{{ $producto->nombre }}</td>
                    <td>{{ $producto->marca?->nombre ?: '—' }}</td>
                    <td>{{ $producto->presentacion ?: '—' }}</td>
                    <td>{{ $producto->viscosidad ?: '—' }}</td>
                    <td>
                        <span class="prod-chips">
                            @foreach ($almacenes as $almacen)
                                @php $n = (int) ($porAlmacen[$almacen->id]->stock ?? 0); @endphp
                                <span @class([
                                    'chip-alm',
                                    'chip-alm-cero' => $n === 0,
                                    'chip-alm-bajo' => $n > 0 && $n <= $producto->stock_minimo,
                                ])>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9.5 12 3l9 6.5V21H3z"/></svg>
                                    {{ $almacen->nombre }}: {{ number_format($n) }}@if($n === 0) ⚠@endif
                                </span>
                            @endforeach
                        </span>
                    </td>
                    <td>
                        @if ($matriz)
                            <span class="prod-gal">{{ number_format($galones, 2) }} GL</span>
                        @else
                            <button type="button" class="prod-agregar"
                                    data-modal="modalGalonaje"
                                    data-campo-codigo="{{ $producto->codigo }}"
                                    data-campo-nombre="{{ $producto->nombre }}">
                                ＋ agregar
                            </button>
                        @endif
                    </td>
                    <td class="prod-vendido">
                        @if ($venta)
                            <b>{{ number_format($venta['unidades']) }} uds</b>
                            <span>{{ number_format($venta['galones'], 2) }} GL</span>
                        @else
                            <span class="prod-mudo">—</span>
                        @endif
                    </td>
                    <td class="prod-precio">S/ {{ number_format($producto->precio_venta, 2) }}</td>
                    <td>
                        <span class="prod-acciones">
                            <button type="button" class="pbtn-ico pbtn-ico-verde" title="Movimiento de stock"
                                    data-modal="modalStock"
                                    data-campo-producto_nombre="{{ $producto->nombre }}"
                                    data-campo-producto_id="{{ $producto->id }}"
                                    data-campo-almacen_id="{{ $almacenSel }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                    <line x1="6" y1="20" x2="6" y2="13"/><line x1="12" y1="20" x2="12" y2="8"/><line x1="18" y1="20" x2="18" y2="4"/>
                                </svg>
                            </button>
                            <button type="button" class="pbtn-ico pbtn-ico-suave" title="Editar producto"
                                    data-modal="modalProducto"
                                    data-producto="{{ json_encode($datosModal) }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>
                                </svg>
                            </button>
                            <form method="POST" action="{{ route('admin.productos.destroy', $producto) }}"
                                  data-confirmar="¿Dar de baja «{{ $producto->nombre }}»?">
                                @csrf @method('DELETE')
                                <button type="submit" class="pbtn-ico pbtn-ico-rojo" title="Dar de baja">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                    </svg>
                                </button>
                            </form>
                        </span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="prod-vacio">Sin productos que coincidan con los filtros</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

</div>

{{-- ── Modales ─────────────────────────────────────────────────────── --}}
<div class="modal-overlay" id="modalProducto">
    <div class="modal-card">
        <div class="modal-header">
            <h3>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round">
                    <path d="M12 2.5 20.5 7v10L12 21.5 3.5 17V7z"/>
                </svg>
                <span id="tituloModalProducto">Nuevo Producto</span>
            </h3>
            <button type="button" class="modal-close" data-cerrar="modalProducto" aria-label="Cerrar">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>

        <div class="modal-body">
            <form method="POST" action="{{ route('admin.productos.store') }}" id="formProducto">
                @csrf
                <input type="hidden" name="registro_id" id="registro_id" value="">

                <div class="form-grid">
                    <div class="form-group">
                        <label for="codigo">Código <span>*</span></label>
                        <input type="text" id="codigo" name="codigo" required maxlength="50" placeholder="1077816">
                    </div>
                    <div class="form-group prod-campo-ancho">
                        <label for="nombre">Nombre del Producto <span>*</span></label>
                        <input type="text" id="nombre" name="nombre" required maxlength="255"
                               placeholder="KENDALL SUPER-D XA (Ti,CK4)">
                    </div>

                    <div class="form-group">
                        <label for="categoria_id">Categoría <span>*</span></label>
                        <select id="categoria_id" name="categoria_id" required>
                            <option value="">Seleccionar categoría</option>
                            @foreach ($categorias as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="marca_id">Marca</label>
                        <select id="marca_id" name="marca_id">
                            <option value="">Sin marca</option>
                            @foreach ($marcas as $m)
                                <option value="{{ $m->id }}">{{ $m->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="presentacion">Presentación</label>
                        <select id="presentacion" name="presentacion">
                            <option value="">Seleccionar presentación…</option>
                            @foreach ($presentaciones as $codigo => $datos)
                                <option value="{{ $codigo }}"
                                        data-gl="{{ $datos['gl'] ?? 0 }}"
                                        data-desc="{{ $datos['descripcion'] ?? '' }}">
                                    {{ $codigo }} — {{ $datos['gl'] ?? 0 }} GL
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="viscosidad">Viscosidad</label>
                        <input type="text" id="viscosidad" name="viscosidad" maxlength="50" placeholder="10W30">
                    </div>
                    <div class="form-group">
                        <label for="precio_compra">Precio Compra <span>*</span></label>
                        <input type="number" id="precio_compra" name="precio_compra" step="0.01" min="0" required placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label for="precio_venta">Precio Venta <span>*</span></label>
                        <input type="number" id="precio_venta" name="precio_venta" step="0.01" min="0" required placeholder="0.00">
                    </div>

                    <div class="form-group">
                        <label for="precio_alquiler">Precio Alquiler</label>
                        <input type="number" id="precio_alquiler" name="precio_alquiler" step="0.01" min="0" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label for="stock_minimo">Stock Mínimo <span>*</span></label>
                        <input type="number" id="stock_minimo" name="stock_minimo" min="0" required placeholder="5">
                    </div>
                    <div class="form-group">
                        <label for="peso">Peso por unidad (kg)</label>
                        <input type="number" id="peso" name="peso" step="0.001" min="0" placeholder="0.00">
                    </div>
                </div>

                {{-- Aparece al elegir presentación; el factor queda editable a mano. --}}
                <div class="prod-galonaje" id="panelGalonaje">
                    <div class="prod-galonaje-tit">Conversión a Galones</div>
                    <div class="prod-galonaje-fila">
                        <div class="prod-galonaje-campo">
                            <span>Factor GL por unidad</span>
                            <div class="prod-galonaje-calc">
                                <b id="galEtiqueta">1 UND =</b>
                                <input type="number" id="galFactor" name="factor_gl" step="0.0001" min="0"
                                       title="Puedes editar el factor manualmente">
                                <b>GL</b>
                            </div>
                            <div class="prod-galonaje-desc" id="galDescripcion"></div>
                        </div>
                        <div class="prod-galonaje-res">
                            <span>Conversión</span>
                            <b id="galFormula">—</b>
                        </div>
                    </div>
                    <p class="prod-galonaje-pie">
                        El valor se autocompleta según la presentación. Puedes editarlo si este producto
                        tiene un factor distinto.
                    </p>
                </div>

                <div class="prod-stock-panel">
                    <div class="prod-stock-tit">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                            <polyline points="9 22 9 12 15 12 15 22"/>
                        </svg>
                        Stock por Almacén
                    </div>
                    <div class="prod-sa-grid">
                        @foreach ($almacenes as $almacen)
                            <div class="prod-sa-item">
                                <div class="prod-sa-label">🏭 {{ $almacen->nombre }}</div>
                                <input type="number" min="0" value="0" placeholder="0"
                                       id="stock_alm_{{ $almacen->id }}"
                                       name="stock[{{ $almacen->id }}]"
                                       data-stock-almacen>
                            </div>
                        @endforeach
                        <div class="prod-sa-total">
                            <span>Total Stock</span>
                            <b id="totalStock">0</b>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="descripcion">Descripción</label>
                    <textarea id="descripcion" name="descripcion" placeholder="Descripción breve…"></textarea>
                </div>
                <div class="form-group">
                    <label for="especificaciones">Especificaciones Técnicas</label>
                    <textarea id="especificaciones" name="especificaciones" placeholder="API CK-4, SAE…"></textarea>
                </div>

                <div class="prod-modal-pie">
                    <button type="submit" class="pbtn pbtn-verde">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        Guardar Producto
                    </button>
                    <button type="button" class="pbtn pbtn-neutro" data-cerrar="modalProducto">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<x-modal id="modalStock" titulo="Movimiento de stock" :oscuro="true">
    <form method="POST" action="" id="formStock">
        @csrf
        <input type="hidden" name="producto_id" value="">

        <div class="form-group">
            <label>Producto</label>
            <input type="text" name="producto_nombre" readonly>
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label for="almacen_id">Almacén <span>*</span></label>
                <select id="almacen_id" name="almacen_id" required>
                    @foreach ($almacenes as $almacen)
                        <option value="{{ $almacen->id }}">{{ $almacen->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="tipo">Tipo <span>*</span></label>
                <select id="tipo" name="tipo" required>
                    <option value="entrada">Entrada</option>
                    <option value="salida">Salida</option>
                    <option value="ajuste">Ajuste (fija el total)</option>
                </select>
            </div>
            <div class="form-group">
                <label for="cantidad">Cantidad <span>*</span></label>
                <input type="number" id="cantidad" name="cantidad" min="1" required>
            </div>
        </div>

        <div class="form-group">
            <label for="motivo">Motivo</label>
            <input type="text" id="motivo" name="motivo" maxlength="255">
        </div>

        <div class="header-btns" style="justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalStock">Cancelar</button>
            <button type="submit" class="btn btn-primary">Registrar movimiento</button>
        </div>
    </form>
</x-modal>

{{-- Alta en la matriz para los productos que aún no tienen factor de galones. --}}
<x-modal id="modalGalonaje" titulo="Registrar galonaje del producto">
    <form id="formGalonaje">
        <div class="form-grid">
            <div class="form-group">
                <label for="gal_codigo">Código <span>*</span></label>
                <input type="text" id="gal_codigo" name="codigo" required maxlength="50">
            </div>
            <div class="form-group">
                <label for="gal_nombre">Nombre <span>*</span></label>
                <input type="text" id="gal_nombre" name="nombre" required maxlength="255">
            </div>
            <div class="form-group">
                <label for="gal_presentacion">Presentación <span>*</span></label>
                <select id="gal_presentacion" name="presentacion" required>
                    <option value="">Elegir…</option>
                    @foreach ($presentaciones as $codigo => $datos)
                        <option value="{{ $codigo }}" data-gl="{{ $datos['gl'] ?? 0 }}">
                            {{ $codigo }} — {{ $datos['descripcion'] ?? '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="gal_linea">Línea <span>*</span></label>
                <select id="gal_linea" name="linea" required>
                    <option value="">Elegir…</option>
                    @foreach ($lineas as $linea)
                        <option value="{{ $linea }}">{{ $linea }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="gal_factor">Galones por unidad <span>*</span></label>
                <input type="number" id="gal_factor" name="factor" step="0.0001" min="0" required>
            </div>
        </div>

        <p class="prod-mudo" style="font-size:13px;margin:4px 0 0;">
            Al elegir una presentación se completa el factor automáticamente; puedes ajustarlo a mano.
        </p>

        <div class="header-btns" style="justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalGalonaje">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar en la matriz</button>
        </div>
    </form>
</x-modal>

</div>{{-- /prod-wrapper --}}
@endsection

@push('scripts')
<script>
const formProducto = document.getElementById('formProducto');
const selPresent   = document.getElementById('presentacion');
const inpFactor    = document.getElementById('galFactor');
const panelGal     = document.getElementById('panelGalonaje');

// ── Stock por almacén: el total se recalcula al teclear ──────────────────
function actualizarTotalStock() {
    let total = 0;
    document.querySelectorAll('[data-stock-almacen]').forEach((campo) => {
        total += parseInt(campo.value, 10) || 0;
    });
    document.getElementById('totalStock').textContent = total;
}

document.querySelectorAll('[data-stock-almacen]').forEach((campo) => {
    campo.addEventListener('input', actualizarTotalStock);
});

// ── Conversión a galones ─────────────────────────────────────────────────
function actualizarFormula() {
    const codigo = selPresent.value || '?';
    const gl     = parseFloat(inpFactor.value) || 0;
    document.getElementById('galFormula').textContent = gl > 0 ? `1 ${codigo} = ${gl} GL` : '—';
}

/** Muestra el panel con el factor de la presentación; `factor` lo sobreescribe. */
function sincronizarGalonaje(factor = null) {
    const opcion = selPresent.selectedOptions[0];
    const codigo = selPresent.value;
    const base   = opcion ? parseFloat(opcion.dataset.gl) || 0 : 0;
    const valor  = factor && factor > 0 ? factor : base;

    if (!codigo || valor <= 0) {
        panelGal.classList.remove('is-visible');
        inpFactor.value = '';
        return;
    }

    inpFactor.value = valor;
    document.getElementById('galEtiqueta').textContent = `1 ${codigo} =`;
    document.getElementById('galDescripcion').textContent = opcion?.dataset.desc || '';
    panelGal.classList.add('is-visible');
    actualizarFormula();
}

selPresent.addEventListener('change', () => sincronizarGalonaje());
inpFactor.addEventListener('input', actualizarFormula);

// ── Apertura del modal ───────────────────────────────────────────────────
document.addEventListener('click', (e) => {
    const boton = e.target.closest('[data-modal="modalProducto"]');
    if (!boton) return;

    formProducto.reset();
    formProducto.querySelector('[name="_method"]')?.remove();

    const datos = boton.dataset.producto ? JSON.parse(boton.dataset.producto) : null;

    document.getElementById('tituloModalProducto').textContent = datos ? 'Editar Producto' : 'Nuevo Producto';
    document.getElementById('registro_id').value = datos?.id ?? '';

    for (const campo of ['codigo', 'nombre', 'categoria_id', 'marca_id', 'presentacion', 'viscosidad',
                         'precio_compra', 'precio_venta', 'precio_alquiler', 'stock_minimo', 'peso',
                         'descripcion', 'especificaciones']) {
        document.getElementById(campo).value = datos?.[campo] ?? '';
    }

    document.querySelectorAll('[data-stock-almacen]').forEach((campo) => {
        const id = campo.id.replace('stock_alm_', '');
        campo.value = datos?.stocks?.[id] ?? 0;
    });

    actualizarTotalStock();
    sincronizarGalonaje(datos?.factor_gl ?? null);
});

@if ($errors->any() && old('nombre') !== null)
// Si la validación falló, se reabre el modal con lo que ya se había escrito.
(function () {
    const previos = @json(old());

    document.getElementById('tituloModalProducto').textContent =
        previos.registro_id ? 'Editar Producto' : 'Nuevo Producto';
    document.getElementById('registro_id').value = previos.registro_id ?? '';

    for (const campo of ['codigo', 'nombre', 'categoria_id', 'marca_id', 'presentacion', 'viscosidad',
                         'precio_compra', 'precio_venta', 'precio_alquiler', 'stock_minimo', 'peso',
                         'descripcion', 'especificaciones']) {
        document.getElementById(campo).value = previos[campo] ?? '';
    }

    document.querySelectorAll('[data-stock-almacen]').forEach((campo) => {
        const id = campo.id.replace('stock_alm_', '');
        campo.value = previos.stock?.[id] ?? 0;
    });

    actualizarTotalStock();
    sincronizarGalonaje(parseFloat(previos.factor_gl) || null);

    document.getElementById('modalProducto').classList.add('active');
    document.body.style.overflow = 'hidden';
})();
@endif

// Al editar, el formulario apunta al registro y viaja como PUT.
formProducto.addEventListener('submit', function () {
    const id = document.getElementById('registro_id').value;
    if (!id) return;

    this.action = '{{ url('admin/productos') }}/' + id;
    if (!this.querySelector('[name="_method"]')) {
        const metodo = document.createElement('input');
        metodo.type = 'hidden';
        metodo.name = '_method';
        metodo.value = 'PUT';
        this.appendChild(metodo);
    }
});

// El endpoint de stock depende del producto seleccionado en la tabla.
document.getElementById('formStock').addEventListener('submit', function () {
    const id = this.querySelector('[name="producto_id"]').value;
    this.action = '{{ url('admin/productos') }}/' + id + '/stock';
});

// La presentación elegida propone el factor de galones de la matriz.
const selPresentacion = document.getElementById('gal_presentacion');
selPresentacion.addEventListener('change', function () {
    const gl = this.selectedOptions[0]?.dataset.gl;
    if (gl) document.getElementById('gal_factor').value = gl;
});

document.getElementById('formGalonaje').addEventListener('submit', async function (e) {
    e.preventDefault();

    const boton = this.querySelector('[type="submit"]');
    boton.disabled = true;

    try {
        const respuesta = await fetch('{{ route('admin.galonaje.productos.store') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify(Object.fromEntries(new FormData(this))),
        });

        if (!respuesta.ok) throw new Error('No se pudo guardar');

        window.location.reload();
    } catch (error) {
        alert('No se pudo registrar el galonaje: ' + error.message);
        boton.disabled = false;
    }
});
</script>
@endpush
