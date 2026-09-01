@extends('layouts.documento')

@section('title', 'Orden ' . $orden->numero_orden)
@section('tipo-documento', 'Orden de Compra')
@section('numero-documento', $orden->numero_orden)

@section('documento')
@include('partials.flash')

<div class="doc-bloques">
    <div class="doc-bloque">
        <h4>Proveedor</h4>
        <p><strong>{{ $orden->proveedor }}</strong></p>
        <p>RUC: {{ $orden->ruc ?: '—' }}</p>
        <p>{{ $orden->direccion }}</p>
    </div>
    <div class="doc-bloque">
        <h4>Orden</h4>
        <p>Fecha: {{ $orden->fecha?->format('d/m/Y') }}</p>
        <p>Estado: {{ $orden->estado }}</p>
        <p>Condición de pago: {{ $orden->condicion_pago ?: '—' }}</p>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th style="width:16%;">Código</th>
            <th>Descripción</th>
            <th class="num" style="width:12%;">Cant.</th>
            <th class="num" style="width:16%;">Precio USD</th>
            <th class="num" style="width:16%;">Importe USD</th>
        </tr>
    </thead>
    <tbody>
    @forelse ($orden->productos ?? [] as $producto)
        <tr>
            <td>{{ $producto['codigo'] ?? '—' }}</td>
            <td>{{ $producto['descripcion'] ?? ($producto['nombre'] ?? '—') }}</td>
            <td class="num">{{ number_format($producto['cantidad'] ?? 0) }}</td>
            <td class="num">$ {{ number_format($producto['precio'] ?? 0, 2) }}</td>
            <td class="num">$ {{ number_format(($producto['cantidad'] ?? 0) * ($producto['precio'] ?? 0), 2) }}</td>
        </tr>
    @empty
        <tr><td colspan="5" style="text-align:center;padding:26px;color:#6B6F78;">Sin líneas registradas</td></tr>
    @endforelse
    </tbody>
</table>

<div class="totales">
    <div><span>Total USD</span><span>$ {{ number_format($orden->total_usd, 2) }}</span></div>
    <div><span>Tipo de cambio</span><span>{{ number_format($orden->tc, 4) }}</span></div>
    <div class="final"><span>Total S/</span><span>S/ {{ number_format($orden->total_soles, 2) }}</span></div>
</div>

<div style="margin-top:34px;padding-top:22px;border-top:2px solid #9B1E2A;">
    <h4 style="font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:#6B6F78;margin-bottom:14px;">
        Confirmar datos de despacho
    </h4>

    <form method="POST" action="{{ route('orden.publica', $token) }}">
        @csrf

        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px;margin-bottom:16px;">
            <label style="display:block;">
                <span style="display:block;font-size:12px;color:#6B6F78;margin-bottom:5px;">N° de factura</span>
                <input type="text" name="nro_factura" value="{{ $orden->nro_factura }}" maxlength="100"
                       style="width:100%;padding:9px 12px;border:1px solid #DCDAD2;border-radius:7px;font-family:inherit;">
            </label>
            <label style="display:block;">
                <span style="display:block;font-size:12px;color:#6B6F78;margin-bottom:5px;">N° de guía</span>
                <input type="text" name="nro_guia" value="{{ $orden->nro_guia }}" maxlength="100"
                       style="width:100%;padding:9px 12px;border:1px solid #DCDAD2;border-radius:7px;font-family:inherit;">
            </label>
            <label style="display:block;">
                <span style="display:block;font-size:12px;color:#6B6F78;margin-bottom:5px;">Peso</span>
                <input type="text" name="peso" value="{{ $orden->peso }}" maxlength="30"
                       style="width:100%;padding:9px 12px;border:1px solid #DCDAD2;border-radius:7px;font-family:inherit;">
            </label>
            <label style="display:block;">
                <span style="display:block;font-size:12px;color:#6B6F78;margin-bottom:5px;">Bultos</span>
                <input type="number" name="bultos" value="{{ $orden->bultos }}" min="0"
                       style="width:100%;padding:9px 12px;border:1px solid #DCDAD2;border-radius:7px;font-family:inherit;">
            </label>
        </div>

        <label style="display:block;margin-bottom:16px;">
            <span style="display:block;font-size:12px;color:#6B6F78;margin-bottom:5px;">Observaciones</span>
            <textarea name="observaciones" rows="3"
                      style="width:100%;padding:9px 12px;border:1px solid #DCDAD2;border-radius:7px;font-family:inherit;">{{ $orden->observaciones }}</textarea>
        </label>

        <button type="submit"
                style="padding:11px 24px;background:#9B1E2A;color:#fff;border:none;border-radius:8px;font-family:inherit;font-size:14px;font-weight:600;cursor:pointer;">
            Confirmar datos
        </button>
    </form>
</div>
@endsection

@section('pie')
    Este enlace es temporal y de un solo uso por orden. Si expiró, solicite uno nuevo a Rental Tech SAC.
@endsection
