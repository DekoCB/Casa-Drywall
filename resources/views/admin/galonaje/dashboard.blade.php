@extends('layouts.admin')

@section('title', 'Dashboard Galonaje')
@section('crumb', 'Vista general')

@push('styles')
    @vite('resources/css/modules/galonaje.css')
@endpush

@section('content')
<div class="gal-wrapper">

@php
    $mesesEs = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    // Series de doce posiciones para el gráfico de metas contra lo real.
    $metasPorMes = [];
    $realPorMes  = [];

    foreach (range(1, 12) as $m) {
        $metasPorMes[] = round((float) ($metas[$m] ?? 0), 2);
        $delMes = $porMes->firstWhere('mes', sprintf('%04d-%02d', $anioMetas, $m));
        $realPorMes[] = round((float) ($delMes['galones'] ?? 0), 2);
    }
@endphp

<div class="gal-hero">
    <div>
        <h2>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>
            </svg>
            Dashboard Galonaje
        </h2>
        <p>Análisis detallado de consumo de galones por mes, producto y cliente</p>
    </div>
    <div class="gal-hero-acciones">
        <a href="{{ route('admin.index') }}" class="gbtn gbtn-oscuro">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
            Volver al Dashboard
        </a>
        <a href="{{ route('admin.facturas.index') }}" class="gbtn gbtn-oscuro">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
            </svg>
            Ver Facturas
        </a>
    </div>
</div>

{{-- ── Filtros ─────────────────────────────────────────────────────── --}}
<form method="GET" action="{{ route('admin.galonaje.dashboard') }}" class="gal-filtros">
    <div class="gal-filtros-fila">
        <div class="gal-campo">
            <label for="anio">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Año
            </label>
            <select id="anio" name="anio">
                <option value="">Todos los años</option>
                @foreach ($anios as $a)
                    <option value="{{ $a }}" @selected($filtros['anio'] == $a)>{{ $a }}</option>
                @endforeach
            </select>
        </div>
        <div class="gal-campo">
            <label for="mes">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Mes
            </label>
            <select id="mes" name="mes">
                <option value="">Todos los meses</option>
                @for ($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" @selected($filtros['mes'] !== '' && (int) $filtros['mes'] === $m)>{{ $mesesEs[$m] }}</option>
                @endfor
            </select>
        </div>
        <div class="gal-campo">
            <label for="trimestre">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="6" y1="20" x2="6" y2="14"/><line x1="12" y1="20" x2="12" y2="8"/><line x1="18" y1="20" x2="18" y2="4"/></svg>
                Trimestre
            </label>
            <select id="trimestre" name="trimestre">
                <option value="">Todos</option>
                @foreach (['1º (Ene–Mar)', '2º (Abr–Jun)', '3º (Jul–Sep)', '4º (Oct–Dic)'] as $i => $etiqueta)
                    <option value="{{ $i + 1 }}" @selected($filtros['trimestre'] == $i + 1)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>
        <div class="gal-campo">
            <label for="bimestre">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="6" y1="20" x2="6" y2="14"/><line x1="12" y1="20" x2="12" y2="8"/><line x1="18" y1="20" x2="18" y2="4"/></svg>
                Bimestre
            </label>
            <select id="bimestre" name="bimestre">
                <option value="">Todos</option>
                @foreach (['1º (Ene–Feb)', '2º (Mar–Abr)', '3º (May–Jun)', '4º (Jul–Ago)', '5º (Sep–Oct)', '6º (Nov–Dic)'] as $i => $etiqueta)
                    <option value="{{ $i + 1 }}" @selected($filtros['bimestre'] == $i + 1)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>
        <div class="gal-campo">
            <label for="desde">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/></svg>
                Desde
            </label>
            <input type="date" id="desde" name="desde" value="{{ $filtros['desde'] }}">
        </div>
        <div class="gal-campo">
            <label for="hasta">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/></svg>
                Hasta
            </label>
            <input type="date" id="hasta" name="hasta" value="{{ $filtros['hasta'] }}">
        </div>
    </div>

    <div class="gal-filtros-fila">
        <div class="gal-campo gal-campo-ancho">
            <label for="producto">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M12 2.5 20.5 7v10L12 21.5 3.5 17V7z"/></svg>
                Producto
            </label>
            <select id="producto" name="producto">
                <option value="">Todos los productos</option>
                @foreach ($productos as $p)
                    <option value="{{ $p }}" @selected($filtros['producto'] === $p)>{{ $p }}</option>
                @endforeach
            </select>
        </div>
        <div class="gal-campo">
            <label for="estado">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                Estado
            </label>
            <select id="estado" name="estado">
                <option value="">Todas</option>
                <option value="activa" @selected($filtros['estado'] === 'activa')>Solo activas</option>
                <option value="cancelada" @selected($filtros['estado'] === 'cancelada')>Solo canceladas</option>
            </select>
        </div>
        <div class="gal-campo">
            <label>&nbsp;</label>
            <div style="display:flex;gap:10px;">
                <button type="submit" class="gbtn gbtn-verde">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    Filtrar
                </button>
                @if (array_filter($filtros) !== [])
                    <a href="{{ route('admin.galonaje.dashboard') }}" class="gbtn gbtn-linea">Limpiar</a>
                @endif
            </div>
        </div>
    </div>
</form>

{{-- ── Tarjetas ────────────────────────────────────────────────────── --}}
<div class="gal-tarjetas">
    <div class="gal-tarjeta">
        <div class="gal-tarjeta-ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>
            </svg>
        </div>
        <div class="gal-tarjeta-val">{{ number_format($totalGalones, 2) }} <small>GL</small></div>
        <div class="gal-tarjeta-lbl">Total galones</div>
        <div class="gal-tarjeta-sub">{{ $nFacturas }} facturas con galones registrados</div>
    </div>

    <div class="gal-tarjeta">
        <div class="gal-tarjeta-ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
        </div>
        <div class="gal-tarjeta-val-txt">{{ $mejorMes['etiqueta'] ?? '—' }}</div>
        <div class="gal-tarjeta-lbl">Mejor mes</div>
        <div class="gal-tarjeta-sub">
            @if ($mejorMes)
                {{ number_format($mejorMes['galones'], 2) }} GL — {{ $mejorMes['facturas'] }} facturas
            @else
                Sin datos
            @endif
        </div>
    </div>

    <div class="gal-tarjeta">
        <div class="gal-tarjeta-ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round">
                <path d="M12 2.5 20.5 7v10L12 21.5 3.5 17V7z"/>
            </svg>
        </div>
        <div class="gal-tarjeta-val-txt">{{ Str::limit($productoTop['nombre'] ?? '—', 28) }}</div>
        <div class="gal-tarjeta-lbl">Producto estrella</div>
        <div class="gal-tarjeta-sub">
            {{ $productoTop ? number_format($productoTop['galones'], 2).' GL acumulados' : 'Sin datos' }}
        </div>
    </div>

    <div class="gal-tarjeta">
        <div class="gal-tarjeta-ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <line x1="6" y1="20" x2="6" y2="14"/><line x1="12" y1="20" x2="12" y2="8"/><line x1="18" y1="20" x2="18" y2="4"/>
            </svg>
        </div>
        <div class="gal-tarjeta-val">{{ number_format($promedioMes, 2) }} <small>GL</small></div>
        <div class="gal-tarjeta-lbl">Promedio mensual</div>
        <div class="gal-tarjeta-sub">{{ $nMeses }} {{ $nMeses === 1 ? 'mes registrado' : 'meses registrados' }}</div>
    </div>
</div>

{{-- ── Metas vs real ───────────────────────────────────────────────── --}}
<div class="gal-panel">
    <div class="gal-panel-head">
        <h3>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
            </svg>
            Metas vs real — {{ $anioMetas }}
        </h3>
        <button type="button" class="gbtn gbtn-linea gbtn-sm" id="btnMetas">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                <path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/>
            </svg>
            Editar Metas
        </button>
    </div>

    @php $hayMetas = collect($metas)->filter(fn ($v) => $v > 0)->isNotEmpty(); @endphp

    <div id="panelMetasVacio" @class(['gal-vacio']) @style(['display:none' => $hayMetas])>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
        </svg>
        <p>No hay metas configuradas para {{ $anioMetas }}.</p>
        <button type="button" class="gbtn gbtn-linea" id="btnConfigurarMetas">Configurar metas</button>
    </div>

    <div id="panelMetasGrafico" class="gal-lienzo" @style(['display:none' => ! $hayMetas])>
        <canvas id="graficoMetas"></canvas>
    </div>

    <form method="POST" action="{{ route('admin.galonaje.metas.anio') }}" id="formMetas" style="display:none;">
        @csrf
        <input type="hidden" name="anio_meta" value="{{ $anioMetas }}">

        <div class="gal-metas-grid">
            @for ($m = 1; $m <= 12; $m++)
                <div class="gal-meta-mes">
                    <label for="meta{{ $m }}">{{ $mesesEs[$m] }}</label>
                    <input type="number" id="meta{{ $m }}" name="meta[{{ $m }}]" step="0.01" min="0"
                           value="{{ $metas[$m] ?? 0 }}" placeholder="0">
                </div>
            @endfor
        </div>

        <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button type="button" class="gbtn gbtn-linea gbtn-sm" id="btnCancelarMetas">Cancelar</button>
            <button type="submit" class="gbtn gbtn-verde gbtn-sm">Guardar metas de {{ $anioMetas }}</button>
        </div>
    </form>
</div>

{{-- ── Galones por mes + top productos ─────────────────────────────── --}}
<div class="gal-dupla">
    <div class="gal-panel">
        <div class="gal-panel-head">
            <h3>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <line x1="6" y1="20" x2="6" y2="14"/><line x1="12" y1="20" x2="12" y2="8"/><line x1="18" y1="20" x2="18" y2="4"/>
                </svg>
                Galones por mes
            </h3>
        </div>
        <div class="gal-lienzo"><canvas id="graficoMeses"></canvas></div>
    </div>

    <div class="gal-panel">
        <div class="gal-panel-head">
            <h3>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 2v10l7 4"/></svg>
                Top productos
            </h3>
        </div>
        <div class="gal-lienzo"><canvas id="graficoProductos"></canvas></div>
    </div>
</div>

{{-- ── Ranking por producto ────────────────────────────────────────── --}}
<div class="gal-panel">
    <div class="gal-panel-head">
        <h3>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round">
                <path d="M12 2.5 20.5 7v10L12 21.5 3.5 17V7z"/>
            </svg>
            Ranking por producto
            <span class="gal-conteo">{{ $porProducto->count() }} productos</span>
        </h3>
        @if ($porProducto->count() > 10)
            <button type="button" class="gbtn gbtn-linea gbtn-sm" id="btnRanking"
                    data-total="{{ $porProducto->count() }}">Ver todos los productos ({{ $porProducto->count() }})</button>
        @endif
    </div>

    @php $maxGal = $porProducto->max('galones') ?: 1; @endphp

    <div class="gal-ranking">
        @foreach ($porProducto as $i => $fila)
            <div class="gal-rank-fila" @class(['gal-rank-extra']) @style(['display:none' => $i >= 10])>
                <span class="gal-rank-pos">
                    @if ($i === 0) <span class="gal-rank-medalla">🥇</span>
                    @elseif ($i === 1) <span class="gal-rank-medalla">🥈</span>
                    @elseif ($i === 2) <span class="gal-rank-medalla">🥉</span>
                    @else {{ $i + 1 }}.
                    @endif
                </span>
                <span class="gal-rank-nom" title="{{ $fila['nombre'] }}">{{ $fila['nombre'] }}</span>
                <span class="gal-rank-barra"><i style="width:{{ round($fila['galones'] / $maxGal * 100) }}%"></i></span>
                <span class="gal-rank-val">{{ number_format($fila['galones'], 2) }} GL</span>
            </div>
        @endforeach
    </div>
</div>

{{-- ── Top facturas ────────────────────────────────────────────────── --}}
<div class="gal-panel">
    <div class="gal-panel-head">
        <h3>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
            </svg>
            Top 20 facturas por galones
        </h3>
    </div>

    <div class="gal-scroll">
        <table class="gal-tabla">
            <thead>
                <tr>
                    <th class="gal-tabla-num">#</th>
                    <th>Factura</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Producto</th>
                    <th class="gal-alinea-der">Importe USD</th>
                    <th class="gal-alinea-der">Galones</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($topFacturas as $i => $factura)
                <tr>
                    <td class="gal-tabla-num">
                        @if ($i === 0) 🥇 @elseif ($i === 1) 🥈 @elseif ($i === 2) 🥉 @else {{ $i + 1 }} @endif
                    </td>
                    <td>
                        <a href="{{ route('admin.facturas.show', $factura) }}" class="gal-factura">{{ $factura->numero }}</a>
                    </td>
                    <td>{{ $factura->emision?->format('d/m/Y') ?: '—' }}</td>
                    <td>{{ trim((string) $factura->cliente) ?: '—' }}</td>
                    <td class="gal-prod" title="{{ $factura->producto }}">{{ trim((string) $factura->producto) ?: '—' }}</td>
                    <td class="gal-importe gal-alinea-der">$ {{ number_format($factura->importe, 2) }}</td>
                    <td class="gal-alinea-der"><span class="gal-pill">{{ number_format($factura->galones, 2) }} GL</span></td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center;padding:40px;color:#999;">Sin facturas con galones para estos filtros</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

</div>{{-- /gal-wrapper --}}
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const VERDE = '#3d9b8c';
const REJA  = 'rgba(20,22,27,.07)';
// Un solo tono en distintos pasos: la rosca ordena por magnitud, no por identidad.
const PASOS = ['#3d9b8c', '#1a5c52', '#6fbfb1', '#0f3d35', '#a8dbd2', '#2e7a6e', '#c9e8e2', '#134a41', '#8ccfc3', '#256b60'];

const meses     = @json($porMes->pluck('etiqueta'));
const galMes    = @json($porMes->pluck('galones'));
const productos = @json($porProducto->take(10));
const metas     = @json($metasPorMes);
const realMes   = @json($realPorMes);
const mesesCortos = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

const gl = (v) => Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' GL';

Chart.defaults.font.family = 'Geist, system-ui, sans-serif';
Chart.defaults.color = '#6B6F78';

// ── Galones por mes ──────────────────────────────────────────────────────
new Chart(document.getElementById('graficoMeses'), {
    type: 'bar',
    data: {
        labels: meses,
        datasets: [{ label: 'Galones', data: galMes, backgroundColor: VERDE, borderRadius: 4, borderSkipped: 'bottom' }],
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        scales: {
            x: { grid: { display: false } },
            y: { beginAtZero: true, grid: { color: REJA, drawTicks: false }, border: { display: false },
                 ticks: { callback: (v) => v.toLocaleString('en-US') } },
        },
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: (c) => gl(c.raw) } },
        },
    },
});

// ── Top productos ────────────────────────────────────────────────────────
new Chart(document.getElementById('graficoProductos'), {
    type: 'doughnut',
    data: {
        labels: productos.map((p) => p.nombre),
        datasets: [{
            data: productos.map((p) => p.galones),
            backgroundColor: PASOS,
            borderColor: '#fff',
            borderWidth: 2,
        }],
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        cutout: '58%',
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    boxWidth: 11, boxHeight: 11, padding: 9, font: { size: 11 },
                    generateLabels: (grafico) => grafico.data.labels.map((etiqueta, i) => ({
                        text: (etiqueta.length > 26 ? etiqueta.slice(0, 26) + '…' : etiqueta)
                            + ' — ' + grafico.data.datasets[0].data[i].toFixed(1) + ' GL',
                        fillStyle: PASOS[i % PASOS.length],
                        strokeStyle: PASOS[i % PASOS.length],
                        index: i,
                    })),
                },
            },
            tooltip: { callbacks: { label: (c) => c.label + ': ' + gl(c.raw) } },
        },
    },
});

// ── Metas vs real ────────────────────────────────────────────────────────
const lienzoMetas = document.getElementById('graficoMetas');
let graficoMetas = null;

function dibujarMetas() {
    if (graficoMetas) return;

    graficoMetas = new Chart(lienzoMetas, {
        type: 'bar',
        data: {
            labels: mesesCortos,
            datasets: [
                { label: 'Real', data: realMes, backgroundColor: VERDE, borderRadius: 4, borderSkipped: 'bottom' },
                { label: 'Meta', data: metas, type: 'line', borderColor: '#1c1917', borderWidth: 2,
                  borderDash: [6, 4], pointRadius: 4, pointBackgroundColor: '#1c1917', fill: false, tension: .2 },
            ],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, grid: { color: REJA, drawTicks: false }, border: { display: false },
                     ticks: { callback: (v) => v.toLocaleString('en-US') } },
            },
            plugins: {
                legend: { position: 'top', labels: { boxWidth: 12, boxHeight: 12, font: { size: 12 } } },
                tooltip: { callbacks: { label: (c) => c.dataset.label + ': ' + gl(c.raw) } },
            },
        },
    });
}

if (document.getElementById('panelMetasGrafico').style.display !== 'none') dibujarMetas();

// ── Formulario de metas ──────────────────────────────────────────────────
const formMetas   = document.getElementById('formMetas');
const panelVacio  = document.getElementById('panelMetasVacio');
const panelGrafico = document.getElementById('panelMetasGrafico');

function alternarMetas(mostrar) {
    formMetas.style.display = mostrar ? '' : 'none';
    panelVacio.style.display = mostrar || metas.some((v) => v > 0) ? 'none' : '';
    panelGrafico.style.display = !mostrar && metas.some((v) => v > 0) ? '' : 'none';

    if (panelGrafico.style.display === '') dibujarMetas();
}

document.getElementById('btnMetas').addEventListener('click', () => alternarMetas(formMetas.style.display === 'none'));
document.getElementById('btnConfigurarMetas')?.addEventListener('click', () => alternarMetas(true));
document.getElementById('btnCancelarMetas').addEventListener('click', () => alternarMetas(false));

// ── Ranking: mostrar todo ────────────────────────────────────────────────
document.getElementById('btnRanking')?.addEventListener('click', function () {
    const ocultas = document.querySelectorAll('.gal-rank-fila[style*="display"]');
    const mostrando = this.dataset.mostrando === '1';

    document.querySelectorAll('.gal-rank-fila').forEach((fila, i) => {
        fila.style.display = (mostrando && i >= 10) ? 'none' : '';
    });

    this.dataset.mostrando = mostrando ? '0' : '1';
    this.textContent = mostrando
        ? `Ver todos los productos (${this.dataset.total})`
        : 'Ver solo el top 10';
});
</script>
@endpush
