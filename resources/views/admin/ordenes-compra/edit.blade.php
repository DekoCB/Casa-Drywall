@extends('layouts.admin')

@section('title', 'Editar Orden de Compra')
@section('crumb', $orden->numero_orden)

@push('styles')
    @vite(['resources/css/modules/ordenes-compra.css'])
@endpush

@section('content')
<div class="oc-wrapper ocm oc-hoja-wrap">

    <div class="oc-nueva-head">
        <a href="{{ route('admin.ordenes-compra.index') }}" class="btn-volver-oc">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
            Volver
        </a>
        <div>
            <h2>Editar orden {{ $orden->numero_orden }}</h2>
            <p>Los cambios se reflejan en el resumen del pie y en la barra inferior</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.ordenes-compra.update', $orden) }}">
        @csrf @method('PUT')
        @include('admin.ordenes-compra._form')
    </form>
</div>
@endsection
