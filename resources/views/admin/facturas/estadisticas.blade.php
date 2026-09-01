@extends('layouts.admin')

@section('title', 'Estadisticas_facturas')
@section('crumb', 'Vista general')

@push('styles')
    @vite('resources/css/modules/facturas.css')
@endpush

@section('content')
<div class="fac-wrapper">

<div class="est-hero">
    <div>
        <h2>📊 Estadísticas de Facturas</h2>
        <p>GP Maquinarias SAC — análisis detallado de saldo en moneda extranjera</p>
    </div>
    <a href="{{ route('admin.facturas.index') }}" class="est-cerrar">✕ Cerrar</a>
</div>

{{-- ── Resumen ─────────────────────────────────────────────────────── --}}
<div class="est-tarjetas">
    <div class="est-tarjeta">
        <div class="est-tarjeta-lbl">Total USD</div>
        <div class="est-tarjeta-val">$ {{ number_format($totalUsd, 2) }}</div>

        <div class="est-fila"><span>Facturas activas</span><b>{{ number_format($nActivas) }}</b></div>
        <div class="est-fila"><span>Facturas canceladas</span><b>{{ number_format($nCanceladas) }}</b></div>
        <div class="est-fila"><span>Promedio / factura</span><b>$ {{ number_format($promedio, 2) }}</b></div>

        @if ($mesMax)
            <div class="est-fila est-fila-alta">
                <span>Mes más alto</span>
                <b>{{ $mesMax['etiqueta'] }} · $ {{ number_format($mesMax['usd'], 2) }}</b>
            </div>
        @endif
    </div>

    <div class="est-tarjeta">
        <div class="est-tarjeta-lbl">Total Soles</div>
        <div class="est-tarjeta-val">S/ {{ number_format($totalSoles, 2) }}</div>

        <div class="est-fila"><span>Base USD</span><b>$ {{ number_format($totalUsd, 2) }}</b></div>
        <div class="est-fila"><span>T/C promedio</span><b>{{ number_format($tcPromedio, 4) }}</b></div>
        <div class="est-fila"><span>Facturas</span><b>{{ number_format($nActivas) }} activas</b></div>

        <p class="est-formula">
            $ {{ number_format($totalUsd, 2) }} × {{ number_format($tcPromedio, 4) }}
            = S/ {{ number_format($totalSoles, 2) }}
        </p>
    </div>

    <div class="est-tarjeta">
        <div class="est-tarjeta-lbl">Mes más alto</div>
        @if ($mesMax)
            <div class="est-tarjeta-mes">{{ $mesMax['etiqueta'] }}</div>
            <div class="est-tarjeta-sub">$ {{ number_format($mesMax['usd'], 2) }}</div>
        @else
            <div class="est-tarjeta-mes">—</div>
        @endif
    </div>
</div>

{{-- ── USD y Soles por mes ─────────────────────────────────────────── --}}
<div class="est-panel">
    <div class="est-panel-head">
        <h3>📊 USD &amp; Soles por mes</h3>
        <div class="est-leyenda">
            <span><i style="background:#C2333F"></i> USD</span>
            <span><i style="background:#1c1917"></i> Soles</span>
        </div>
    </div>
    <div class="est-lienzo"><canvas id="graficoMeses"></canvas></div>
</div>

{{-- ── Evolución acumulada ─────────────────────────────────────────── --}}
<div class="est-panel">
    <div class="est-panel-head">
        <h3>📈 Evolución acumulada</h3>
        <div class="est-leyenda">
            <span><i style="background:#C2333F"></i> USD</span>
            <span><i style="background:#1c1917"></i> Soles</span>
        </div>
    </div>
    <div class="est-lienzo"><canvas id="graficoEvolucion"></canvas></div>
</div>

{{-- ── Top productos ───────────────────────────────────────────────── --}}
<div class="est-panel">
    <div class="est-panel-head">
        <h3>📦 Top productos por USD</h3>
        <div class="est-leyenda">
            <span><i style="background:#C2333F"></i> USD</span>
            <span><i style="background:#1c1917"></i> Soles</span>
            @if ($porProducto->count() > 10)
                <button type="button" class="est-ver-todos" id="btnVerTodos"
                        data-total="{{ $porProducto->count() }}">Ver todos</button>
            @endif
        </div>
    </div>
    <div class="est-lienzo est-lienzo-alto"><canvas id="graficoProductos"></canvas></div>
</div>

{{-- ── Resumen mensual ─────────────────────────────────────────────── --}}
<div class="est-panel">
    <div class="est-panel-head">
        <h3>📅 Resumen mensual</h3>
    </div>

    <div class="fac-scroll">
        <table class="est-tabla">
            <thead>
                <tr>
                    <th>Mes</th>
                    <th>Facturas</th>
                    <th>Total USD</th>
                    <th>Total Soles</th>
                    <th>Promedio USD</th>
                    <th>% del total</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($porMes->sortByDesc('mes') as $fila)
                <tr>
                    <td><span class="est-mes-pill">{{ $fila['etiqueta'] }}</span></td>
                    <td>{{ $fila['n'] }}</td>
                    <td class="est-usd">$ {{ number_format($fila['usd'], 2) }}</td>
                    <td class="est-soles">S/ {{ number_format($fila['soles'], 2) }}</td>
                    <td class="est-prom">$ {{ number_format($fila['promedio'], 2) }}</td>
                    <td>
                        <span class="est-pct">
                            <span class="est-barra"><i style="width:{{ min(100, $fila['porcentaje']) }}%"></i></span>
                            <b>{{ number_format($fila['porcentaje'], 1) }}%</b>
                        </span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;padding:40px;color:#999;">Sin facturas activas</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

</div>{{-- /fac-wrapper --}}
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const ROJO  = '#C2333F';
const NEGRO = '#1c1917';
const TINTA = '#6B6F78';
const REJA  = 'rgba(20,22,27,.07)';

const meses     = @json($porMes->pluck('etiqueta'));
const usdMes    = @json($porMes->pluck('usd'));
const solesMes  = @json($porMes->pluck('soles'));
const acumUsd   = @json($porMes->pluck('acum_usd'));
const acumSoles = @json($porMes->pluck('acum_soles'));
const productos = @json($porProducto);

const dolares = (v) => '$ ' + Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const soles   = (v) => 'S/ ' + Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

Chart.defaults.font.family = 'Geist, system-ui, sans-serif';
Chart.defaults.color = TINTA;

/** Ejes gemelos: Soles es el mismo eje reexpresado al tipo de cambio. */
const ejes = (maxUsd) => ({
    x: { grid: { display: false }, ticks: { font: { size: 12 } } },
    y: {
        position: 'left',
        beginAtZero: true,
        grid: { color: REJA, drawTicks: false },
        border: { display: false },
        ticks: { font: { size: 11 }, callback: (v) => '$ ' + v.toLocaleString('en-US') },
    },
    y2: {
        position: 'right',
        beginAtZero: true,
        max: maxUsd,
        grid: { display: false },
        border: { display: false },
        ticks: {
            font: { size: 11 },
            callback: (v) => 'S/ ' + Math.round(v * {{ $tcPromedio ?: 1 }}).toLocaleString('en-US'),
        },
    },
});

const leyenda = { legend: { display: false } };

const maxMes = Math.max(0, ...usdMes) * 1.15;
const maxAcum = Math.max(0, ...acumUsd) * 1.05;

// ── USD y Soles por mes ──────────────────────────────────────────────────
new Chart(document.getElementById('graficoMeses'), {
    type: 'bar',
    data: {
        labels: meses,
        datasets: [
            { label: 'USD', data: usdMes, backgroundColor: ROJO, borderRadius: 4, borderSkipped: 'bottom' },
            // Se dibuja contra el eje USD porque S/ es ese mismo importe al T/C.
            { label: 'Soles', data: solesMes.map((v) => v / {{ $tcPromedio ?: 1 }}), backgroundColor: NEGRO,
              borderRadius: 4, borderSkipped: 'bottom',
              solesReales: solesMes },
        ],
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        scales: ejes(maxMes),
        plugins: {
            ...leyenda,
            tooltip: {
                callbacks: {
                    label: (c) => c.datasetIndex === 0
                        ? 'USD: ' + dolares(c.raw)
                        : 'Soles: ' + soles(solesMes[c.dataIndex]),
                },
            },
        },
    },
});

// ── Evolución acumulada ──────────────────────────────────────────────────
new Chart(document.getElementById('graficoEvolucion'), {
    type: 'line',
    data: {
        labels: meses,
        datasets: [
            { label: 'USD', data: acumUsd, borderColor: ROJO, backgroundColor: 'rgba(194,51,63,.06)',
              borderWidth: 2, fill: true, tension: .35, pointRadius: 5,
              pointBackgroundColor: ROJO, pointBorderColor: '#fff', pointBorderWidth: 2 },
            { label: 'Soles', data: acumSoles.map((v) => v / {{ $tcPromedio ?: 1 }}), borderColor: NEGRO,
              backgroundColor: 'rgba(28,25,23,.05)', borderWidth: 2, fill: true, tension: .35, pointRadius: 5,
              pointBackgroundColor: NEGRO, pointBorderColor: '#fff', pointBorderWidth: 2 },
        ],
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        scales: ejes(maxAcum),
        plugins: {
            ...leyenda,
            tooltip: {
                callbacks: {
                    label: (c) => c.datasetIndex === 0
                        ? 'USD acumulado: ' + dolares(c.raw)
                        : 'Soles acumulado: ' + soles(acumSoles[c.dataIndex]),
                },
            },
        },
    },
});

// ── Top productos ────────────────────────────────────────────────────────
const lienzoProd = document.getElementById('graficoProductos');
let graficoProd = null;
let verTodos = false;

function dibujarProductos() {
    const filas = verTodos ? productos : productos.slice(0, 10);
    const solesFila = filas.map((p) => p.soles);

    lienzoProd.parentElement.style.height = Math.max(320, filas.length * 42 + 60) + 'px';

    graficoProd?.destroy();
    graficoProd = new Chart(lienzoProd, {
        type: 'bar',
        data: {
            labels: filas.map((p) => p.producto),
            datasets: [
                { label: 'USD', data: filas.map((p) => p.usd), backgroundColor: ROJO, borderRadius: 4 },
                { label: 'Soles', data: solesFila.map((v) => v / {{ $tcPromedio ?: 1 }}), backgroundColor: NEGRO, borderRadius: 4 },
            ],
        },
        options: {
            indexAxis: 'y',
            responsive: true, maintainAspectRatio: false,
            scales: {
                x: {
                    beginAtZero: true,
                    grid: { color: REJA, drawTicks: false },
                    border: { display: false },
                    ticks: { font: { size: 11 }, callback: (v) => '$ ' + v.toLocaleString('en-US') },
                },
                y: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 11.5 } } },
            },
            plugins: {
                ...leyenda,
                tooltip: {
                    callbacks: {
                        label: (c) => c.datasetIndex === 0
                            ? 'USD: ' + dolares(c.raw)
                            : 'Soles: ' + soles(solesFila[c.dataIndex]),
                    },
                },
            },
        },
    });
}

dibujarProductos();

document.getElementById('btnVerTodos')?.addEventListener('click', function () {
    verTodos = !verTodos;
    this.textContent = verTodos ? 'Ver top 10' : 'Ver todos';
    dibujarProductos();
});
</script>
@endpush
