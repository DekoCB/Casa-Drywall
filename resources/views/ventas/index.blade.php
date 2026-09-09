@extends('layouts.rol')

@section('title', 'Panel de Ventas')
@section('panel', 'Ventas')
@section('crumb', 'Vista general')

@section('menu')
    <div class="sb-section">Principal</div>
    <a href="{{ route('ventas.index') }}" class="mi active">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>
        Inicio
    </a>
    <a href="{{ route('admin.pos.index') }}" class="mi">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
        Punto de Venta
    </a>
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
        El perfil de Ventas trabaja únicamente sobre el Punto de Venta.
        No tiene acceso a cotizaciones, boletas ni facturas fuera del POS.
        El administrador concede o retira estos accesos desde el módulo de Personal.
    </p>
</div>
@endsection
