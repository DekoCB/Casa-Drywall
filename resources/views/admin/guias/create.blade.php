@extends('layouts.admin')

@section('title', 'Nueva Guía de Remisión')
@section('crumb', 'Logística')

@section('content')
<x-page-header titulo="Nueva Guía de Remisión" subtitulo="Emite el documento de traslado de la mercadería">
    <x-slot:acciones>
        <a href="{{ route('admin.guias.index') }}" class="btn btn-secondary btn-sm">
            <span class="btn-text">← Volver</span>
        </a>
    </x-slot:acciones>
</x-page-header>

<form method="POST" action="{{ route('admin.guias.store') }}">
    @csrf
    @include('admin.guias._form', ['productosCatalogo' => $productos])

    <div class="header-btns" style="justify-content:flex-end;margin-top:25px;">
        <a href="{{ route('admin.guias.index') }}" class="btn btn-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">Emitir guía</button>
    </div>
</form>
@endsection
