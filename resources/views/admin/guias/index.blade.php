@extends('layouts.admin')

@section('title', 'Guías de Remisión')
@section('crumb', 'Logística')

@section('content')
<x-page-header titulo="Guías de Remisión" subtitulo="Documentos de traslado de mercadería">
    <x-slot:acciones>
        <a href="{{ route('admin.guias.create') }}" class="btn btn-primary">
            <span class="btn-icon">＋</span><span class="btn-text">Nueva Guía</span>
        </a>
    </x-slot:acciones>
</x-page-header>

<div class="stats-grid">
    <x-stat-card :valor="number_format($totalGuias)" etiqueta="Guías emitidas" />
    <x-stat-card :valor="number_format($delMes)" etiqueta="Emitidas este mes" />
</div>

<div class="content-card">
    <div class="lista-header">
        <h3>Lista de Guías</h3>

        <form method="GET" class="filtros">
            <x-buscador :valor="$busqueda" placeholder="Buscar por número, destinatario, venta o placa…" />
            <select class="filtro-select" id="estado" name="estado">
                <option value="">Todos los estados</option>
                <option value="emitida" @selected($estadoSel === 'emitida')>Emitida</option>
                <option value="anulada" @selected($estadoSel === 'anulada')>Anulada</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm"><span class="btn-text">Filtrar</span></button>
            @if ($busqueda !== '')
                <a href="{{ route('admin.guias.index') }}" class="btn btn-secondary btn-sm">
                    <span class="btn-text">Limpiar</span>
                </a>
            @endif
        </form>
    </div>

    @if ($busqueda !== '')
        <p class="resultado-busqueda">
            {{ $guias->total() }} resultado(s) para <strong>"{{ $busqueda }}"</strong>
        </p>
    @endif

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>N° Guía</th><th>Fecha</th><th>Destinatario</th>
                    <th>Traslado</th><th>Transportista</th><th>Estado</th><th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($guias as $guia)
                <tr @style(['opacity:.55' => $guia->estado === 'anulada'])>
                    <td>
                        <strong style="font-family:monospace;">{{ $guia->numero_guia }}</strong>
                        <div style="font-size:12px;color:#666;">{{ $guia->numero_venta }}</div>
                    </td>
                    <td>{{ $guia->fecha?->format('d/m/Y') }}</td>
                    <td>
                        {{ $guia->cliente_nombre }}
                        <div style="font-size:12px;color:#666;">{{ $guia->cliente_ruc }}</div>
                    </td>
                    <td>
                        {{ $guia->motivo_traslado }}
                        <div style="font-size:12px;color:#666;">{{ Str::limit($guia->punto_llegada, 34) }}</div>
                    </td>
                    <td>
                        {{ $guia->empresa_transporte ?: '—' }}
                        <div style="font-size:12px;color:#666;">{{ $guia->placa_vehiculo }}</div>
                    </td>
                    <td>{{ ucfirst($guia->estado) }}</td>
                    <td style="white-space:nowrap;">
                        <a href="{{ route('admin.guias.show', $guia) }}" class="btn btn-secondary btn-sm">Ver</a>
                        <a href="{{ route('admin.guias.excel', $guia) }}" class="btn btn-secondary btn-sm">Excel</a>
                        <a href="{{ route('admin.guias.edit', $guia) }}" class="btn btn-dark btn-sm">Editar</a>
                        @if ($guia->estado !== 'anulada')
                            <form method="POST" action="{{ route('admin.guias.destroy', $guia) }}"
                                  style="display:inline;" data-confirmar="¿Anular la guía {{ $guia->numero_guia }}?">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">Anular</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center;padding:40px;color:#666;">Sin guías registradas</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $guias->links() }}
</div>
@endsection
