@extends('layouts.admin')

@section('title', 'Movimientos de Merch')
@section('crumb', 'Merch · Entradas y salidas')

@section('content')

<x-page-header titulo="Movimientos de Merch"
               subtitulo="Las entradas llegan de las órdenes de compra; las salidas son entregas a clientes">
    <x-slot:acciones>
        <a href="{{ route('admin.merch.index') }}" class="btn btn-secondary">
            <span class="btn-text">← Volver al catálogo</span>
        </a>
    </x-slot:acciones>
</x-page-header>

<div class="content-card">
    <div class="lista-header">
        <h3>Historial</h3>

        <form method="GET" class="filtros">
            <select name="merch">
                <option value="">Todos los artículos</option>
                @foreach ($articulos as $articulo)
                    <option value="{{ $articulo->id }}" @selected($merchSel === $articulo->id)>{{ $articulo->nombre }}</option>
                @endforeach
            </select>

            <select name="tipo">
                <option value="">Entradas y salidas</option>
                <option value="entrada" @selected($tipoSel === 'entrada')>Solo entradas</option>
                <option value="salida" @selected($tipoSel === 'salida')>Solo salidas</option>
            </select>

            @if ($ordenSel > 0)
                <input type="hidden" name="orden" value="{{ $ordenSel }}">
            @endif

            <button type="submit" class="btn btn-primary btn-sm"><span class="btn-text">Filtrar</span></button>

            @if ($merchSel || $tipoSel || $ordenSel)
                <a href="{{ route('admin.merch.movimientos') }}" class="btn btn-secondary btn-sm">
                    <span class="btn-text">Limpiar</span>
                </a>
            @endif
        </form>
    </div>

    @if ($ordenSel > 0)
        <p class="resultado-busqueda">Mostrando solo lo que ingresó por la orden de compra #{{ $ordenSel }}</p>
    @endif

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Artículo</th>
                    <th>Movimiento</th>
                    <th>Cantidad</th>
                    <th>Costo unit.</th>
                    <th>Origen / destino</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($movimientos as $movimiento)
                @php $esEntrada = $movimiento->tipo === 'entrada'; @endphp
                <tr>
                    <td>{{ $movimiento->fecha?->format('d/m/Y') }}</td>
                    <td><strong>{{ $movimiento->merch?->nombre ?? '— eliminado —' }}</strong></td>
                    <td>
                        <span style="color:{{ $esEntrada ? '#11704A' : '#A8231F' }};font-weight:600;">
                            {{ $esEntrada ? 'Entrada' : 'Entrega' }}
                        </span>
                    </td>
                    <td>{{ $esEntrada ? '+' : '−' }}{{ number_format($movimiento->cantidad) }}</td>
                    <td>{{ $esEntrada ? 'S/ '.number_format($movimiento->costo_unit, 2) : '—' }}</td>
                    <td>
                        @if ($esEntrada)
                            @if ($movimiento->orden_compra_id)
                                <a href="{{ route('admin.ordenes-compra.show', $movimiento->orden_compra_id) }}">
                                    Orden {{ $movimiento->numero_orden ?: '#'.$movimiento->orden_compra_id }}
                                </a>
                            @else
                                Compra
                            @endif
                        @else
                            {{ $movimiento->cliente_nombre ?: '— sin cliente —' }}
                        @endif
                        @if ($movimiento->observaciones)
                            <div style="color:#6B6F78;font-size:12px;">{{ $movimiento->observaciones }}</div>
                        @endif
                    </td>
                    <td style="white-space:nowrap;">
                        @if ($esEntrada)
                            <span style="color:#9CA0A8;font-size:12px;">Se corrige en la orden</span>
                        @else
                            <form method="POST" action="{{ route('admin.merch.movimientos.anular', $movimiento) }}"
                                  style="display:inline;" data-confirmar="¿Anular esta entrega y devolver el stock?">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">Anular</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center;padding:40px;color:#666;">Sin movimientos registrados</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $movimientos->links() }}
</div>
@endsection
