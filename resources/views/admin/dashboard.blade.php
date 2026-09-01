@extends('layouts.admin')

@section('title', 'Dashboard')

@push('styles')
    @vite(['resources/css/modules/dashboard.css'])
@endpush

@section('content')
@php
    use Illuminate\Support\Str;

    /** Deuda pendiente repartida en tramos: total y máximo, para escalar las barras. */
    $deudaTotal = array_sum($antiguedad);
    $maxTramo = $antiguedad ? max($antiguedad) : 0;
    $maxDeudor = (float) ($topDeudores->max('saldo') ?? 0);

    /** Cuánto de lo facturado en el período ya entró en caja. */
    $cobertura = $facturado > 0 ? ($cobrado / $facturado) * 100 : null;
    $pendientePeriodo = $facturado - $cobrado;

    /** Variación que puede no existir: el histórico no tiene período previo. */
    $delta = function (?float $pct, bool $masEsMejor = true) {
        if ($pct === null) {
            return ['clase' => 'flat', 'texto' => '— sin comparativo'];
        }

        $bueno = $masEsMejor ? $pct >= 0 : $pct <= 0;

        return [
            'clase' => $bueno ? 'pos' : 'neg',
            'texto' => ($pct >= 0 ? '▲ +' : '▼ ') . number_format(abs($pct), 1) . '%',
        ];
    };
@endphp

<div class="dbn">

{{-- ══ HERO ══ --}}
<section class="hero-new">
  <div class="hero-left">
    <div class="hero-eyebrow">Resumen Empresarial · {{ config('rentaltech.empresa.razon_social') }}</div>
    <h2>Facturación y cobranza. <em>{{ $porCobrar > 0 ? 'Hay cartera por cobrar.' : 'Cartera al día.' }}</em></h2>
    <p>{{ $periodos[$periodo] }} · {{ $desde->translatedFormat('d M Y') }} – {{ $hasta->translatedFormat('d M Y') }}</p>

    <div class="per-tabs">
      @foreach ($periodos as $clave => $etiqueta)
        <a href="{{ route('admin.index', ['periodo' => $clave]) }}"
           class="per-tab {{ $periodo === $clave ? 'on' : '' }}">{{ $etiqueta }}</a>
      @endforeach
    </div>

    <div class="hero-stats" style="margin-top:18px">
      <div class="hero-stat">
        <div class="lbl">Facturado</div>
        <div class="val">S/ {{ number_format($facturado, 2) }}</div>
        <div class="sub">{{ number_format($nComprobantes) }} comprobantes</div>
      </div>
      <div class="hero-stat">
        <div class="lbl">Cobrado</div>
        <div class="val pos">S/ {{ number_format($cobrado, 2) }}</div>
        <div class="sub">{{ $cobertura !== null ? number_format($cobertura, 1) . '% de lo facturado' : 'Sin facturación' }}</div>
      </div>
      <div class="hero-stat">
        <div class="lbl">Cartera por cobrar</div>
        <div class="val {{ $porCobrar > 0 ? 'neg' : 'pos' }}">S/ {{ number_format($porCobrar, 2) }}</div>
        <div class="sub">Saldo vivo total</div>
      </div>
    </div>

    <a href="{{ route('admin.cobranzas.index') }}" class="btn-gal" style="margin-top:20px;">
      <span class="gal-ico">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 3v18h18"/><path d="M7 16l4-4 4 4 4-6"/></svg>
      </span>
      <span>
        Ir a Cobranzas
        <span class="gal-sub">S/ {{ number_format($porCobrar, 2) }} pendientes · gestión por cliente y documento</span>
      </span>
      <svg class="gal-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
    </a>
  </div>

  <div class="hero-right">
    <div style="display:flex;align-items:center;justify-content:space-between;">
      <div class="hero-clock"><span class="pulse"></span> EN VIVO · LIMA, PE</div>
      <div class="hero-clock" id="reloj-live"></div>
    </div>
    <div>
      <div class="hero-bigdate">{{ now()->format('d') }}<em>·</em>{{ now()->translatedFormat('M') }}<em>·</em>{{ now()->format('y') }}</div>
      <div class="hero-datesub">{{ now()->translatedFormat('l') }} · Semana {{ now()->format('W') }} · Q{{ ceil(now()->month / 3) }}</div>
    </div>
  </div>
</section>

{{-- ══ ALERTAS ══ --}}
<div class="al-row">
  @if ($vencido90 > 0)
    <span class="al err"><span class="dot"></span> S/ {{ number_format($vencido90, 2) }} vencido +90 días</span>
  @endif
  @if ($criticas->isNotEmpty())
    <span class="al warn"><span class="dot"></span> {{ $criticas->count() }} documentos vencidos por gestionar</span>
  @endif
  @if ($porCobrar > 0)
    <span class="al info"><span class="dot"></span> S/ {{ number_format($porCobrar, 2) }} en cartera</span>
  @else
    <span class="al ok"><span class="dot"></span> Sin saldo pendiente</span>
  @endif
  @if ($stockBajo > 0)
    <span class="al warn"><span class="dot"></span> {{ $stockBajo }} producto{{ $stockBajo != 1 ? 's' : '' }} con stock bajo</span>
  @endif
  <span class="al ok"><span class="dot"></span> Sistema operativo</span>
</div>

{{-- ══ FACTURACIÓN ══ --}}
<div class="sec-head">
  <h3>Facturación y cobranza</h3><div class="rule"></div>
  <div class="meta">Periodo: <span class="mono">{{ $desde->format('d/m/Y') }} – {{ $hasta->format('d/m/Y') }}</span></div>
  <a href="{{ route('admin.ventas.index') }}" class="btn-ghost">Ver ventas <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg></a>
</div>
<div class="kpi-grid">

  @php $d = $delta($facturadoPct); @endphp
  <div class="kpi">
    <div class="kpi-head"><div class="kpi-lbl">Facturado</div>
      <svg class="kpi-ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 17l6-6 4 4 8-9"/></svg>
    </div>
    <div class="kpi-val"><span class="cur">S/</span>{{ number_format($facturado, 2) }}</div>
    <div class="kpi-sub"><span class="delta {{ $d['clase'] }}">{{ $d['texto'] }}</span> vs período previo</div>
    <div class="kpi-spark"><svg viewBox="0 0 200 40" preserveAspectRatio="none"><defs><linearGradient id="g1" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#1F4A86" stop-opacity=".2"/><stop offset="1" stop-color="#1F4A86" stop-opacity="0"/></linearGradient></defs><path d="M0,34 L50,28 L100,20 L150,14 L200,8 L200,40 L0,40Z" fill="url(#g1)"/><path d="M0,34 L50,28 L100,20 L150,14 L200,8" fill="none" stroke="#1F4A86" stroke-width="1.4"/></svg></div>
  </div>

  @php $d = $delta($cobradoPct); @endphp
  <div class="kpi">
    <div class="kpi-head"><div class="kpi-lbl">Cobrado</div>
      <svg class="kpi-ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 6L9 17l-5-5"/></svg>
    </div>
    <div class="kpi-val"><span class="cur">S/</span>{{ number_format($cobrado, 2) }}</div>
    <div class="kpi-sub"><span class="delta {{ $d['clase'] }}">{{ $d['texto'] }}</span> vs período previo</div>
    <div class="kpi-spark"><svg viewBox="0 0 200 40" preserveAspectRatio="none"><defs><linearGradient id="g2" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#11704A" stop-opacity=".2"/><stop offset="1" stop-color="#11704A" stop-opacity="0"/></linearGradient></defs><path d="M0,36 L50,30 L100,24 L150,14 L200,10 L200,40 L0,40Z" fill="url(#g2)"/><path d="M0,36 L50,30 L100,24 L150,14 L200,10" fill="none" stroke="#11704A" stroke-width="1.4"/></svg></div>
  </div>

  @php $d = $delta($comprobantesPct); @endphp
  <div class="kpi">
    <div class="kpi-head"><div class="kpi-lbl">Comprobantes</div>
      <svg class="kpi-ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
    </div>
    <div class="kpi-val">{{ number_format($nComprobantes) }}</div>
    <div class="kpi-sub"><span class="delta {{ $d['clase'] }}">{{ $d['texto'] }}</span> vs período previo</div>
    <div class="kpi-spark"><svg viewBox="0 0 200 40" preserveAspectRatio="none"><g fill="#1F4A86" opacity=".7"><rect x="10" y="30" width="12" height="10" rx="1"/><rect x="40" y="24" width="12" height="16" rx="1"/><rect x="70" y="18" width="12" height="22" rx="1"/><rect x="100" y="22" width="12" height="18" rx="1"/><rect x="130" y="14" width="12" height="26" rx="1"/><rect x="160" y="10" width="12" height="30" rx="1"/><rect x="178" y="6" width="12" height="34" rx="1"/></g></svg></div>
  </div>

  <div class="kpi">
    <div class="kpi-head"><div class="kpi-lbl">Ticket promedio</div>
      <svg class="kpi-ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 9V6a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4z"/></svg>
    </div>
    <div class="kpi-val"><span class="cur">S/</span>{{ number_format($ticket, 2) }}</div>
    <div class="kpi-sub"><span class="delta flat">— por comprobante</span></div>
    <div class="kpi-spark"><svg viewBox="0 0 200 40" preserveAspectRatio="none"><path d="M0,30 L50,26 L100,20 L150,16 L200,10" fill="none" stroke="#3A3D45" stroke-width="1.4"/></svg></div>
  </div>

  <div class="kpi">
    <div class="kpi-head"><div class="kpi-lbl">Vencido +90 días</div>
      <svg class="kpi-ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
    </div>
    <div class="kpi-val" style="color:{{ $vencido90 > 0 ? 'var(--neg)' : 'var(--ink)' }}"><span class="cur">S/</span>{{ number_format($vencido90, 2) }}</div>
    <div class="kpi-sub">
      <span class="delta {{ $vencido90 > 0 ? 'neg' : 'pos' }}">{{ $porCobrar > 0 ? number_format(($vencido90 / $porCobrar) * 100, 1) . '%' : '0%' }}</span>
      de la cartera
    </div>
    <div class="kpi-spark"><svg viewBox="0 0 200 40" preserveAspectRatio="none"><line x1="0" y1="30" x2="200" y2="30" stroke="var(--line)" stroke-width="1" stroke-dasharray="4 4"/></svg></div>
  </div>

  <div class="kpi">
    <div class="kpi-head"><div class="kpi-lbl">Stock bajo</div>
      <svg class="kpi-ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20.59 13.41L13.42 20.58a2 2 0 0 1-2.83 0L2.59 12.58a2 2 0 0 1 0-2.83l7.17-7.17a2 2 0 0 1 2.83 0l7.17 7.17a2 2 0 0 1 0 2.83z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
    </div>
    <div class="kpi-val" style="color:{{ $stockBajo > 0 ? 'var(--warn)' : 'var(--ink)' }}">{{ number_format($stockBajo) }}</div>
    <div class="kpi-sub">
      <span class="delta {{ $stockBajo > 0 ? 'neg' : 'pos' }}">{{ $stockBajo > 0 ? 'Revisar' : 'OK' }}</span>
      producto{{ $stockBajo != 1 ? 's' : '' }} en el mínimo
    </div>
    <a href="{{ route('admin.productos.index') }}" class="kpi-spark" style="display:block;"><svg viewBox="0 0 200 40" preserveAspectRatio="none"><line x1="0" y1="30" x2="200" y2="30" stroke="var(--line)" stroke-width="1" stroke-dasharray="4 4"/></svg></a>
  </div>

</div>

{{-- ══ CARTERA ══ --}}
<div class="sec-head" style="margin-top:28px">
  <h3>Cartera y antigüedad de deuda</h3><div class="rule"></div>
  <div class="meta">Saldo vivo: <span class="mono" style="color:var(--ink)">S/ {{ number_format($porCobrar, 2) }}</span></div>
  <a href="{{ route('admin.cobranzas.index') }}" class="btn-ghost">Ver cobranzas <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg></a>
</div>
<div class="bal-grid">

  <div class="panel">
    <div class="panel-head">
      <div><div class="panel-title">Por cobrar</div><div class="panel-sub">Todas las cobranzas vigentes</div></div>
      <span class="pill brand"><span class="dot"></span> Cartera</span>
    </div>
    <div class="big {{ $porCobrar > 0 ? 'neg' : 'pos' }}"><span class="cur">S/</span>{{ number_format($porCobrar, 2) }}</div>
    <div class="foot">Vencido +90 d <span class="mono" style="color:var(--neg);font-weight:500;margin-left:auto">S/ {{ number_format($vencido90, 2) }}</span></div>
  </div>

  <div class="panel">
    <div class="panel-head">
      <div><div class="panel-title">Cobrado del período</div><div class="panel-sub">Sobre lo facturado</div></div>
      <span class="pill {{ ($cobertura ?? 0) >= 80 ? 'ok' : 'warn' }}"><span class="dot"></span> {{ $cobertura !== null ? number_format($cobertura, 1) . '%' : '—' }}</span>
    </div>
    <div class="big pos"><span class="cur">S/</span>{{ number_format($cobrado, 2) }}</div>
    <div class="foot">
      Falta cobrar <span class="mono" style="color:var(--{{ $pendientePeriodo > 0 ? 'neg' : 'pos' }});font-weight:500;margin-left:auto">S/ {{ number_format(max($pendientePeriodo, 0), 2) }}</span>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head">
      <div><div class="panel-title">Antigüedad</div><div class="panel-sub">Deuda pendiente por tramo</div></div>
    </div>
    <div style="margin-top:12px">
      @foreach ($tramos as $t)
        @php $monto = $antiguedad[$t['clave']]; @endphp
        <div class="egr-item" style="padding:7px 0">
          <div class="egr-info">
            <div class="egr-lbl">{{ $t['etiqueta'] }}</div>
            <div class="egr-bar"><div class="egr-fill" style="width:{{ $maxTramo > 0 ? round(($monto / $maxTramo) * 100) : 0 }}%"></div></div>
          </div>
          <div class="egr-val">S/ {{ number_format($monto, 2) }}</div>
        </div>
      @endforeach
      <div class="brow" style="padding-top:10px;border-top:2px solid var(--line)"><span class="lbl" style="font-weight:600;color:var(--ink)">Total pendiente</span><span class="val mono">S/ {{ number_format($deudaTotal, 2) }}</span></div>
    </div>
  </div>

  <div class="panel chart-panel">
    <div class="panel-head">
      <div><div class="panel-title">Facturado vs cobrado</div><div class="panel-sub">Últimos 12 meses</div></div>
    </div>
    <div class="chart-area"><canvas id="dbChartFC" style="height:200px"></canvas></div>
    <div class="chart-legend">
      <span class="item"><span class="swatch" style="background:var(--info)"></span> Facturado</span>
      <span class="item"><span class="swatch" style="background:var(--pos)"></span> Cobrado</span>
    </div>
  </div>

</div>

{{-- ══ TENDENCIA ══ --}}
<div class="sec-head" style="margin-top:28px">
  <h3>Tendencia</h3><div class="rule"></div>
  <div class="meta">Facturación mensual · últimos 24 meses</div>
</div>
<div class="panel chart-panel">
  <div class="chart-area"><canvas id="dbChartTend" style="height:220px"></canvas></div>
  <div class="chart-legend">
    <span class="item"><span class="swatch" style="background:var(--brand)"></span> Facturado</span>
    <span class="item"><span class="swatch" style="background:var(--pos)"></span> Cobrado</span>
  </div>
</div>

{{-- ══ GESTIÓN DE COBRANZA ══ --}}
<div class="sec-head" style="margin-top:28px">
  <h3>Gestión de cobranza</h3><div class="rule"></div>
  <a href="{{ route('admin.cobranzas.index') }}" class="btn-ghost">Ver cartera completa <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg></a>
</div>
<div class="twocol">

  <div class="tbl-panel">
    <div class="tbl-head">
      <div><div class="panel-title">Top deudores</div><div class="panel-sub">Clientes con mayor saldo pendiente</div></div>
      <a href="{{ route('admin.cobranzas.index') }}" class="btn-ghost">Ver todos <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg></a>
    </div>
    @if ($topDeudores->isEmpty())
      <div style="text-align:center;padding:40px;color:var(--ink-3);font-size:13px;">Sin deuda pendiente registrada</div>
    @else
    <table class="tbl">
      <thead><tr><th>Cliente</th><th>Documentos</th><th>Participación</th><th style="text-align:right">Saldo</th></tr></thead>
      <tbody>
      @foreach ($topDeudores as $td)
        @php $ini = Str::upper(Str::substr(trim($td->cliente_nombre ?: '?'), 0, 1)); @endphp
      <tr>
        <td>
          <div class="who">
            <div class="pic r">{{ $ini }}</div>
            <div>
              <div class="vname" style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $td->cliente_nombre }}">{{ Str::limit($td->cliente_nombre, 34, '…') }}</div>
              <div class="vsub">Pendiente de cobro</div>
            </div>
          </div>
        </td>
        <td>{{ $td->n }} doc{{ $td->n != 1 ? 's' : '' }}</td>
        <td><div class="egr-bar" style="min-width:90px"><div class="egr-fill" style="width:{{ $maxDeudor > 0 ? round(($td->saldo / $maxDeudor) * 100) : 0 }}%"></div></div></td>
        <td style="text-align:right"><span class="amnt" style="font-weight:500;color:var(--neg)">S/ {{ number_format($td->saldo, 2) }}</span></td>
      </tr>
      @endforeach
      </tbody>
    </table>
    @endif
  </div>

  <div class="side-panel">
    <div class="sp-head"><div class="sp-title">Documentos más atrasados</div><div class="sp-sub">Ordenados por días vencidos</div></div>
    @if ($criticas->isEmpty())
      <div style="padding:28px;text-align:center;color:var(--ink-3);font-size:13px;">Sin documentos vencidos</div>
    @else
      @foreach ($criticas as $c)
        <div class="egr-item">
          <span class="pill {{ $c->dias > 90 ? 'brand' : ($c->dias > 30 ? 'warn' : 'info') }}">{{ $c->dias }} d</span>
          <div class="egr-info">
            <div class="egr-lbl" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $c->cliente_nombre }}">{{ Str::limit($c->cliente_nombre ?: '—', 24, '…') }}</div>
            <div class="mono" style="font-size:10.5px;color:var(--ink-3);margin-top:2px">{{ Str::upper($c->tipo ?? '') }} {{ $c->numero }}</div>
          </div>
          <div class="egr-val">S/ {{ number_format($c->monto_pendiente, 2) }}</div>
        </div>
      @endforeach
    @endif
    <div class="sp-head" style="border-top:1px solid var(--line);margin-top:auto;"><div class="sp-title">Resumen del período</div></div>
    <div class="bal-sum">
      <div class="brow"><span class="lbl">Facturado</span><span class="val" style="color:var(--info)">S/ {{ number_format($facturado, 2) }}</span></div>
      <div class="brow"><span class="lbl">Cobrado</span><span class="val" style="color:var(--pos)">S/ {{ number_format($cobrado, 2) }}</span></div>
      <div class="brow"><span class="lbl">Pendiente del período</span><span class="val" style="color:var(--neg)">S/ {{ number_format(max($pendientePeriodo, 0), 2) }}</span></div>
      <div class="brow" style="padding-top:10px;border-top:2px solid var(--line);margin-top:4px;"><span class="lbl" style="font-weight:600;color:var(--ink)">Cartera total</span><span class="val" style="font-size:16px;color:var(--neg)">S/ {{ number_format($porCobrar, 2) }}</span></div>
    </div>
  </div>

</div>

</div>{{-- /dbn --}}
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Reloj en vivo
(function tick(){
  const d = new Date();
  const t = d.getHours().toString().padStart(2,'0')+':'+d.getMinutes().toString().padStart(2,'0')+':'+d.getSeconds().toString().padStart(2,'0');
  const el = document.getElementById('reloj-live');
  if (el) el.textContent = t;
  setTimeout(tick, 1000);
})();

const money = v => 'S/ ' + Number(v).toLocaleString('es-PE', {minimumFractionDigits:2, maximumFractionDigits:2});

// Lee los tokens de color reales (respeta modo claro/oscuro activo).
const estilo = getComputedStyle(document.documentElement);
const tok = (nombre) => estilo.getPropertyValue(nombre).trim();
const colorBrand = tok('--brand'), colorPos = tok('--pos'), colorLine = tok('--line'), colorInk3 = tok('--ink-3');

const ejes = {
  y: { beginAtZero:true, ticks:{font:{size:10}, color:colorInk3, callback: v => 'S/ ' + v.toLocaleString()}, grid:{color:colorLine} },
  x: { grid:{display:false}, ticks:{font:{size:10}, color:colorInk3, maxRotation:0, autoSkip:true} }
};
const tipSoles = { callbacks: { label: ctx => ctx.dataset.label + ': ' + money(ctx.raw) } };

// ── Facturado vs cobrado · 12 meses ──
const fc = @json($facturadoVsCobrado);
new Chart(document.getElementById('dbChartFC'), {
  type: 'bar',
  data: { labels: fc.map(f => f.etiqueta), datasets: [
    { label:'Facturado', data: fc.map(f => f.facturado), backgroundColor:colorBrand, borderRadius:3 },
    { label:'Cobrado',   data: fc.map(f => f.cobrado),   backgroundColor:colorPos, borderRadius:3 }
  ]},
  options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}, tooltip:tipSoles}, scales: ejes }
});

// ── Tendencia mensual · 24 meses ──
const tend = @json($tendencia);
new Chart(document.getElementById('dbChartTend'), {
  type: 'line',
  data: { labels: tend.map(t => t.etiqueta), datasets: [
    { label:'Facturado', data: tend.map(t => t.facturado), borderColor:colorBrand, backgroundColor:colorBrand + '18', borderWidth:1.8, pointRadius:2, fill:true, tension:0.35 },
    { label:'Cobrado',   data: tend.map(t => t.cobrado),   borderColor:colorPos, backgroundColor:colorPos + '18', borderWidth:1.8, pointRadius:2, fill:true, tension:0.35, borderDash:[4,3] }
  ]},
  options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}, tooltip:tipSoles}, scales: ejes }
});
</script>
@endpush
