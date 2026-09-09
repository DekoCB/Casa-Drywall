@extends('layouts.rol')

@section('title', 'Panel de Ventas')
@section('panel', 'Ventas')
@section('crumb', 'Vista general')

@section('menu')
    @php
        $gestionComercial = collect(config('menu.admin')['Gestión Comercial']);
        $ventasItem = $gestionComercial->firstWhere('label', 'Ventas');
        $posItem = $gestionComercial->firstWhere('label', 'POS');
    @endphp

    <div class="sb-section">Principal</div>
    <a href="{{ route('ventas.index') }}" class="mi @if(request()->routeIs('ventas.index')) active @endif">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>
        <span>Inicio</span>
    </a>

    <div class="sb-section">Gestión Comercial</div>
    @include('partials.menu-item', ['item' => $ventasItem])
    @include('partials.menu-item', ['item' => $posItem])
@endsection

@section('content')

<x-page-header titulo="Panel de Ventas"
               subtitulo="Bienvenido, {{ auth()->user()->username }}. Aquí tienes el resumen de tu turno." />

<div class="stats-grid">
    <x-stat-card :valor="number_format($ventasHoy)" etiqueta="Ventas de hoy" />
    <x-stat-card valor="S/ {{ number_format($montoHoy, 2) }}" etiqueta="Vendido hoy" />
    <x-stat-card :valor="$sesion ? $sesion->caja->nombre : 'Cerrada'" etiqueta="Estado de caja" />
</div>

<div class="content-card">
    <h3 style="font-size:18px;margin-bottom:14px;">Módulos habilitados</h3>
    <p style="color:#666;">
        El perfil de Ventas trabaja sobre el Punto de Venta y el módulo de Ventas
        (cotizaciones, notas de venta, boletas, facturas y pedidos).
        No tiene acceso a Clientes, Productos, Inventario ni al resto del panel.
        El administrador concede o retira estos accesos desde el módulo de Personal.
    </p>
</div>
@endsection
