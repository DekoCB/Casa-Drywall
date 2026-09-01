@extends('layouts.admin')

@section('title', 'Comprobante ' . $venta->n_seri . '-' . $venta->n_comp)
@section('crumb', 'Registro de comprobantes')

@section('content')

<x-page-header titulo="{{ $venta->n_seri }}-{{ $venta->n_comp }}"
               subtitulo="{{ $tipos[$venta->tipcomp]['nombre'] ?? $venta->tipcomp }} · {{ $venta->fecha?->format('d/m/Y') }}">
    <x-slot:acciones>
        <a href="{{ route('admin.ventas.comprobante', $venta) }}" target="_blank" class="btn btn-secondary btn-sm">Ver impreso</a>
        <a href="{{ route('admin.ventas.index') }}" class="btn btn-secondary btn-sm">← Volver</a>
    </x-slot:acciones>
</x-page-header>

<div class="stats-grid">
    <x-stat-card :valor="'S/ ' . number_format($venta->baseimp, 2)" etiqueta="Base imponible" />
    <x-stat-card :valor="'S/ ' . number_format($venta->igv, 2)" etiqueta="IGV" />
    <x-stat-card :valor="'S/ ' . number_format($venta->total, 2)" etiqueta="Total" />
    <x-stat-card :valor="number_format($venta->tipcambio, 3)" etiqueta="Tipo de cambio" />
</div>

<div class="content-card">
    <div class="form-grid">
        <div>
            <h3 style="font-size:14px;text-transform:uppercase;letter-spacing:.1em;color:#666;margin-bottom:10px;">Cliente</h3>
            <p><strong>{{ $venta->razonsocial ?: $venta->cliente_nombre ?: '—' }}</strong></p>
            <p>RUC / DNI: {{ $venta->n_ruc ?: $venta->cliente_ruc ?: '—' }}</p>
            <p>{{ $venta->cliente_direccion }}</p>
        </div>
        <div>
            <h3 style="font-size:14px;text-transform:uppercase;letter-spacing:.1em;color:#666;margin-bottom:10px;">Importes</h3>
            <p>Base imponible: S/ {{ number_format($venta->baseimp, 2) }}</p>
            <p>Exonerado: S/ {{ number_format($venta->exonerado, 2) }}</p>
            <p>Inafecto: S/ {{ number_format($venta->inafecto, 2) }}</p>
            <p>IGV: S/ {{ number_format($venta->igv, 2) }}</p>
            <p><strong>Total: S/ {{ number_format($venta->total, 2) }}</strong></p>
        </div>
    </div>

    @if ($venta->detalles->isNotEmpty())
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr><th>Código</th><th>Producto</th><th>Cantidad</th><th>Galones</th><th>P. unitario</th><th>Subtotal</th></tr>
                </thead>
                <tbody>
                @foreach ($venta->detalles as $detalle)
                    <tr>
                        <td>{{ $detalle->prod_codigo ?: '—' }}</td>
                        <td>{{ $detalle->prod_nombre }}</td>
                        <td>{{ number_format($detalle->cantidad) }}</td>
                        <td>{{ number_format($detalle->galones, 2) }}</td>
                        <td>S/ {{ number_format($detalle->precio_unitario, 2) }}</td>
                        <td><strong>S/ {{ number_format($detalle->subtotal, 2) }}</strong></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($venta->observaciones)
        <div style="margin-top:20px;padding-top:20px;border-top:1px solid #f0f0f0;">
            <strong>Observaciones:</strong> {{ $venta->observaciones }}
        </div>
    @endif
</div>
@endsection
