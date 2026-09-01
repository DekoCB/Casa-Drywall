@extends('layouts.admin')

@section('title', 'Factura '.$factura->numero)
@section('crumb', 'Compras & documentos')

@push('styles')
    @vite(['resources/css/modules/facturas.css'])
@endpush

@section('content')

<div class="fac-wrapper">
    <div class="fac-header">
        <div class="fac-header-left">
            <h2>{{ $factura->numero }}</h2>
            <p>
                Emitida el {{ $factura->emision->format('d/m/Y') }} ·
                vence el {{ $factura->vencimiento->format('d/m/Y') }}
                @if ($factura->doc) · doc. {{ $factura->doc }} @endif
            </p>
        </div>
        <div class="fac-header-right">
            @if ($factura->pdf)
                <a href="{{ Storage::url($factura->pdf) }}" target="_blank" class="btn-upload-pdf">📄 Ver PDF</a>
            @endif
            <a href="{{ route('admin.facturas.index') }}" class="btn-add-fac">← Volver</a>
        </div>
    </div>

    <div class="fac-kpis">
        <div class="fac-kpi">
            <div class="fac-kpi-label">Importe (USD)</div>
            <div class="fac-kpi-val">$ {{ number_format($factura->importe, 2) }}</div>
            <div class="fac-kpi-sub">Tipo de cambio {{ number_format($factura->tc, 2) }}</div>
        </div>
        <div class="fac-kpi">
            <div class="fac-kpi-label">Importe (Soles)</div>
            <div class="fac-kpi-val">S/ {{ number_format($factura->importeSoles(), 2) }}</div>
            <div class="fac-kpi-sub">Al tipo de cambio aplicado</div>
        </div>
        <div class="fac-kpi kpi-galones">
            <div class="fac-kpi-label">Galones</div>
            <div class="fac-kpi-val">{{ number_format($factura->galones, 2) }} GL</div>
            <div class="fac-kpi-sub">{{ count($factura->productos_lista ?? []) }} producto(s)</div>
        </div>
        <div class="fac-kpi">
            <div class="fac-kpi-label">Estado</div>
            <div class="fac-kpi-val" style="font-size:20px;">
                <span class="fac-badge badge-{{ $factura->estado() }}">
                    {{ Str::upper(str_replace('_', ' ', $factura->estado())) }}
                </span>
            </div>
            <div class="fac-kpi-sub">
                {{ $factura->diasMora() > 0
                    ? $factura->diasMora().' día(s) de mora'
                    : 'Vence en '.$factura->diasParaVencer().' día(s)' }}
            </div>
        </div>
    </div>

    <div class="fac-card">
        <div class="fac-card-header">
            <strong>{{ $factura->producto ?: 'Detalle de productos' }}</strong>
            @if ($factura->guia_remision)
                <span class="celda-guia">Guía {{ $factura->guia_remision }}</span>
            @endif
        </div>

        <div class="detalle-caja">
            @if (count($factura->productos_lista ?? []) > 0)
                <table class="detalle-tabla">
                    <thead>
                        <tr>
                            <th>Código</th><th>Producto</th>
                            <th class="cen">Cant.</th><th class="cen">Pres.</th>
                            <th class="cen">Factor</th><th class="num">Galones</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($factura->productos_lista as $item)
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
    </div>
</div>
@endsection
