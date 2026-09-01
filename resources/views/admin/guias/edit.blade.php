@extends('layouts.admin')

@section('title', 'Editar Guía')
@section('crumb', $guia->numero_guia)

@section('content')
<x-page-header titulo="Editar {{ $guia->numero_guia }}" subtitulo="Modifica los datos del traslado">
    <x-slot:acciones>
        <a href="{{ route('admin.guias.excel', $guia) }}" class="btn btn-secondary btn-sm">
            <span class="btn-text">Excel</span>
        </a>
        <a href="{{ route('admin.guias.index') }}" class="btn btn-secondary btn-sm">
            <span class="btn-text">← Volver</span>
        </a>
    </x-slot:acciones>
</x-page-header>

<form method="POST" action="{{ route('admin.guias.update', $guia) }}">
    @csrf @method('PUT')
    @include('admin.guias._form', ['productosCatalogo' => $productos])

    <div class="header-btns" style="justify-content:flex-end;margin-top:25px;">
        <a href="{{ route('admin.guias.index') }}" class="btn btn-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">Guardar cambios</button>
    </div>
</form>
@endsection
