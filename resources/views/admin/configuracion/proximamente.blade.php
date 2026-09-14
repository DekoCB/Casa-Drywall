@extends('layouts.admin')

@section('title', $titulo)
@section('crumb', 'Configuración')

@push('styles')
    @vite(['resources/css/modules/configuracion.css'])
@endpush

@section('content')

<x-page-header :titulo="$titulo" :subtitulo="$desc">
    <x-slot:acciones>
        <a href="{{ route('admin.configuracion.index') }}" class="btn btn-secondary btn-sm"><span class="btn-text">← Configuración</span></a>
    </x-slot:acciones>
</x-page-header>

<div class="content-card" style="text-align:center;padding:60px 20px;">
    <div style="width:56px;height:56px;border-radius:14px;background:var(--surface-2);color:var(--ink-3);display:flex;align-items:center;justify-content:center;margin:0 auto 18px;">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
    </div>
    <div style="font-size:15px;font-weight:700;color:var(--ink);margin-bottom:6px;">Próximamente</div>
    <p style="font-size:13px;color:var(--ink-3);max-width:380px;margin:0 auto;">
        Todavía no está definido qué va a poder configurarse aquí — dinos qué debe incluir "{{ $titulo }}" y lo armamos.
    </p>
</div>

@endsection
