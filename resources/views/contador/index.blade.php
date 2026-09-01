@extends('layouts.rol')

@section('title', 'Panel de Contabilidad')
@section('panel', 'Contabilidad')
@section('crumb', 'Sin módulos')

@section('menu')
    <div class="sb-section">Principal</div>
    <a href="{{ route('contador.index') }}" class="mi active">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>
        Inicio
    </a>
@endsection

@section('content')

<x-page-header titulo="Panel de Contabilidad"
               subtitulo="Bienvenido, {{ auth()->user()->username }}." />

<div class="content-card" style="text-align:center;padding:60px 40px;">
    <div style="font-size:44px;margin-bottom:16px;">📋</div>
    <h3 style="font-size:20px;margin-bottom:10px;">Todavía no tienes módulos asignados</h3>
    <p style="color:#666;max-width:520px;margin:0 auto;">
        El administrador habilita los módulos de este perfil desde Personal.
        Solicítalos para empezar a trabajar.
    </p>
</div>
@endsection
