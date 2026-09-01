@extends('layouts.documento')

@section('title', 'Guía ' . $guia->numero_guia)
@section('tipo-documento', 'Guía de Remisión — Remitente')
@section('numero-documento', $guia->numero_guia)

@section('documento')
<div class="doc-bloques">
    <div class="doc-bloque">
        <h4>Destinatario</h4>
        <p><strong>{{ $guia->cliente_nombre }}</strong></p>
        <p>RUC/DNI: {{ $guia->cliente_ruc ?: '—' }}</p>
        @if ($guia->cliente_direccion) <p>{{ $guia->cliente_direccion }}</p> @endif
        <p>{{ collect([$guia->cliente_distrito, $guia->cliente_provincia, $guia->cliente_departamento])->filter()->implode(', ') }}</p>
    </div>

    <div class="doc-bloque">
        <h4>Traslado</h4>
        <p>Emisión: {{ $guia->fecha?->format('d/m/Y') }}</p>
        <p>Inicio de traslado: {{ $guia->fecha_traslado?->format('d/m/Y') ?: '—' }}</p>
        <p>Motivo: {{ $guia->motivo_traslado }}</p>
        @if ($guia->numero_venta) <p>Documento relacionado: {{ $guia->numero_venta }}</p> @endif
        <p>Peso total: {{ $guia->peso_total ?: '—' }} · Bultos: {{ $guia->bultos ?: '—' }}</p>
    </div>
</div>

<div class="doc-bloques">
    <div class="doc-bloque">
        <h4>Punto de partida</h4>
        <p>{{ $guia->punto_partida }}</p>
    </div>
    <div class="doc-bloque">
        <h4>Punto de llegada</h4>
        <p>{{ $guia->punto_llegada }}</p>
    </div>
</div>

<div class="doc-bloques">
    <div class="doc-bloque">
        <h4>Transportista</h4>
        <p>{{ $guia->empresa_transporte ?: '—' }}</p>
        <p>RUC: {{ $guia->transportista_ruc ?: '—' }}</p>
    </div>
    <div class="doc-bloque">
        <h4>Vehículo y conductor</h4>
        <p>Placa: {{ $guia->placa_vehiculo ?: '—' }}</p>
        <p>Conductor: {{ $guia->conductor_nombre ?: '—' }}</p>
        <p>Licencia: {{ $guia->licencia_conductor ?: '—' }}</p>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th style="width:18%;">Código</th>
            <th>Descripción</th>
            <th class="num" style="width:14%;">Cantidad</th>
            <th class="num" style="width:16%;">Peso</th>
        </tr>
    </thead>
    <tbody>
    @forelse ($guia->productos ?? [] as $producto)
        <tr>
            <td>{{ $producto['codigo'] ?? '—' }}</td>
            <td>{{ $producto['nombre'] ?? ($producto['descripcion'] ?? '—') }}</td>
            <td class="num">{{ number_format($producto['cantidad'] ?? 0) }}</td>
            <td class="num">{{ $producto['peso'] ?? '—' }}</td>
        </tr>
    @empty
        <tr><td colspan="4" style="text-align:center;padding:26px;color:#6B6F78;">Sin bienes registrados</td></tr>
    @endforelse
    </tbody>
</table>
@endsection

@section('pie')
    @if ($guia->observaciones)
        <div style="margin-bottom:6px;"><strong>Observaciones:</strong> {{ $guia->observaciones }}</div>
    @endif
    Estado: {{ ucfirst($guia->estado) }}
@endsection
