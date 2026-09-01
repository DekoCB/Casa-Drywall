@extends('layouts.admin')

@section('title', 'Clientes Destacados')
@section('crumb', 'Cartera de clientes')

@section('content')

<x-page-header titulo="Clientes Destacados" subtitulo="Ranking por facturación acumulada">
    <x-slot:acciones>
        <a href="{{ route('admin.clientes.index') }}" class="btn btn-secondary btn-sm">
            <span class="btn-text">← Clientes</span>
        </a>
    </x-slot:acciones>
</x-page-header>

<div class="content-card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th><th>Cliente</th><th>Compras</th>
                    <th>Galones</th><th>Última compra</th><th>Total facturado</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($destacados as $i => $cliente)
                <tr>
                    <td><strong>{{ $i + 1 }}</strong></td>
                    <td>{{ $cliente->cliente_nombre }}</td>
                    <td>{{ number_format($cliente->compras) }}</td>
                    <td>{{ number_format($cliente->galones, 2) }} GL</td>
                    <td>{{ $cliente->ultima_compra ? \Carbon\Carbon::parse($cliente->ultima_compra)->format('d/m/Y') : '—' }}</td>
                    <td><strong>S/ {{ number_format($cliente->total_facturado, 2) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;padding:40px;color:#666;">Sin ventas registradas todavía</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
