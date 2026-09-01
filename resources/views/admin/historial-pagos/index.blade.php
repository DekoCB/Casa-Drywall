@extends('layouts.admin')

@section('title', 'Historial de Pagos')
@section('crumb', 'Vista general')

@push('styles')
    @vite('resources/css/modules/historial-pagos.css')
@endpush

@section('content')
<div class="hp-wrapper">

@php
    $mesesEs = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    // Un color por medio de pago, reconocido por el texto de la observación.
    $claseMetodo = function (string $metodo): array {
        $m = mb_strtolower($metodo);

        return match (true) {
            str_contains($m, 'yape')          => ['hp-met-yape', '📱'],
            str_contains($m, 'plin')          => ['hp-met-plin', '📱'],
            str_contains($m, 'efectivo')      => ['hp-met-efvo', '💵'],
            str_contains($m, 'bcp')           => ['hp-met-bcp', '🏦'],
            str_contains($m, 'interbank')     => ['hp-met-ibk', '🏦'],
            str_contains($m, 'bbva')          => ['hp-met-bbva', '🏦'],
            str_contains($m, 'transferencia') => ['hp-met-transf', '💳'],
            default                           => ['hp-met-otro', '💰'],
        };
    };
@endphp

<div class="hp-hero">
    <div>
        <h2>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            Historial de Pagos
        </h2>
        <p>Pagos registrados por cliente, agrupados por mes · {{ number_format($nPagos) }} registro(s)</p>
    </div>
    <div class="hp-hero-meta">
        <span>{{ now()->format('d/m/Y H:i') }}</span>
        <b>{{ number_format($nClientes) }} cliente(s)</b>
    </div>
</div>

{{-- ── Filtros ─────────────────────────────────────────────────────── --}}
<form method="GET" action="{{ route('admin.historial-pagos.index') }}" class="hp-filtros">
    <div class="hp-campo">
        <label for="cliente">🔍 Buscar cliente</label>
        <input type="search" id="cliente" name="cliente" value="{{ $cliente }}" placeholder="Nombre del cliente…">
    </div>
    <div class="hp-campo">
        <label for="anio">Año</label>
        <select id="anio" name="anio">
            <option value="0" @selected($anioSel === 0)>Todos los años</option>
            @foreach ($anios as $a)
                <option value="{{ $a }}" @selected($anioSel === $a)>{{ $a }}</option>
            @endforeach
        </select>
    </div>
    <div class="hp-campo">
        <label for="mes">Mes</label>
        <select id="mes" name="mes">
            <option value="0" @selected($mesSel === 0)>Todos los meses</option>
            @for ($m = 1; $m <= 12; $m++)
                <option value="{{ $m }}" @selected($mesSel === $m)>{{ $mesesEs[$m] }}</option>
            @endfor
        </select>
    </div>
    <div class="hp-campo" style="display:flex;gap:10px;">
        <button type="submit" class="hbtn hbtn-azul">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            Filtrar
        </button>
        @if ($cliente !== '' || $mesSel > 0)
            <a href="{{ route('admin.historial-pagos.index') }}" class="hbtn hbtn-claro">Limpiar</a>
        @endif
    </div>
</form>

{{-- ── Un bloque por cliente ───────────────────────────────────────── --}}
@forelse ($agrupado as $nombre => $porMes)
    @php $totalPagos = $porMes->flatten(1)->count(); @endphp

    <div class="hp-cliente" data-cliente>
        <button type="button" class="hp-cliente-head" data-toggle="cliente">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
            </svg>
            <span class="hp-cliente-nom">{{ $nombre }}</span>
            <span class="hp-chip">{{ $totalPagos }} pago(s)</span>
            <span class="hp-chip">{{ $porMes->count() }} mes(es)</span>
            <svg class="hp-flecha" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 12 15 18 9"/>
            </svg>
        </button>

        <div class="hp-cliente-body">
            @foreach ($porMes->sortKeysDesc() as $periodo => $pagos)
                <div class="hp-mes" data-mes>
                    <button type="button" class="hp-mes-head" data-toggle="mes">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        <span class="hp-mes-nom">{{ $pagos->first()['etiquetaMes'] }}</span>
                        <span class="hp-mes-chip">{{ $pagos->count() }} pago(s)</span>
                        <svg class="hp-flecha" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>

                    <div class="hp-tabla-wrap">
                        <table class="hp-tabla">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>📅 Fecha</th>
                                    <th>📄 Comprobante</th>
                                    <th>💳 Método / Observación</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach ($pagos as $i => $pago)
                                @php [$clase, $icono] = $claseMetodo($pago['metodo']); @endphp
                                <tr>
                                    <td class="hp-num">{{ $i + 1 }}</td>
                                    <td class="hp-fecha">{{ \Illuminate\Support\Carbon::parse($pago['fecha'])->format('d/m/Y') }}</td>
                                    <td>
                                        <a href="{{ route('admin.cobranzas.index', ['q' => $pago['documento']]) }}"
                                           class="hp-doc">{{ $pago['documento'] }}</a>
                                    </td>
                                    <td>
                                        <span class="hp-metodo {{ $clase }}">
                                            {{ $icono }}
                                            {{ $pago['monto'] !== null
                                                ? 'S/ '.number_format($pago['monto'], 2).' — '.$pago['metodo']
                                                : $pago['metodo'] }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($pago['estado'] === 'pagada')
                                            <span class="hp-estado hp-est-pagada">✅ Pagada</span>
                                        @else
                                            <span class="hp-estado hp-est-parcial">⏳ Parcial</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@empty
    <div class="hp-vacio">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
            <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
        </svg>
        <p>No hay pagos registrados con estos filtros.</p>
    </div>
@endforelse

</div>{{-- /hp-wrapper --}}
@endsection

@push('scripts')
<script>
// Plegar y desplegar cada cliente y cada mes.
document.addEventListener('click', (e) => {
    const boton = e.target.closest('[data-toggle]');
    if (!boton) return;

    const contenedor = boton.dataset.toggle === 'cliente'
        ? boton.closest('[data-cliente]')
        : boton.closest('[data-mes]');

    contenedor?.classList.toggle('is-cerrado');
});
</script>
@endpush
