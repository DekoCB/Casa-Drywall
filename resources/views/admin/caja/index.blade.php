@extends('layouts.admin')

@section('title', 'Cajas')
@section('crumb', 'Finanzas')

@section('content')

<x-page-header titulo="Cajas" subtitulo="Catálogo de cajas físicas y su historial de aperturas/cierres">
    <x-slot:acciones>
        <a href="{{ route('admin.pos.index') }}" class="btn btn-secondary btn-sm">Ir al Punto de Venta</a>
        <button type="button" class="btn btn-primary" data-modal="modalNuevaCaja">
            <span class="btn-icon">＋</span><span class="btn-text">Nueva Caja</span>
        </button>
    </x-slot:acciones>
</x-page-header>

<div class="content-card">
    <h3 style="font-size:15px;margin-bottom:14px;">Catálogo</h3>
    <div class="table-container">
        <table class="table">
            <thead><tr><th>Nombre</th><th>Descripción</th><th>Estado</th></tr></thead>
            <tbody>
            @forelse ($cajas as $caja)
                <tr>
                    <td><strong>{{ $caja->nombre }}</strong></td>
                    <td>{{ $caja->descripcion ?: '—' }}</td>
                    <td>
                        @if ($caja->activo)
                            <span class="badge badge-success">Activa</span>
                        @else
                            <span class="badge badge-neutral">Inactiva</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" style="text-align:center;padding:40px;color:var(--ink-3);">Sin cajas registradas todavía</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="content-card" style="margin-top:18px;">
    <h3 style="font-size:15px;margin-bottom:14px;">Historial de sesiones</h3>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Caja</th><th>Cajero</th><th>Abierta</th><th>Cerrada</th>
                    <th class="num">Inicial</th><th class="num">Esperado</th><th class="num">Contado</th><th class="num">Diferencia</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($sesiones as $sesion)
                <tr>
                    <td>{{ $sesion->caja?->nombre ?? '—' }}</td>
                    <td>{{ $sesion->usuario?->username ?? '—' }}</td>
                    <td>{{ $sesion->abierta_en?->format('d/m/Y H:i') }}</td>
                    <td>{{ $sesion->cerrada_en?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td class="num">S/ {{ number_format((float) $sesion->monto_inicial, 2) }}</td>
                    <td class="num">{{ $sesion->monto_final_esperado !== null ? 'S/ '.number_format((float) $sesion->monto_final_esperado, 2) : '—' }}</td>
                    <td class="num">{{ $sesion->monto_final_contado !== null ? 'S/ '.number_format((float) $sesion->monto_final_contado, 2) : '—' }}</td>
                    <td class="num">
                        @if ($sesion->diferencia !== null)
                            @php $colorDif = (float) $sesion->diferencia == 0 ? 'var(--ink)' : ((float) $sesion->diferencia > 0 ? 'var(--pos)' : 'var(--neg)'); @endphp
                            <span style="color:{{ $colorDif }};">S/ {{ number_format((float) $sesion->diferencia, 2) }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @if ($sesion->estado === 'abierta')
                            <span class="badge badge-success">Abierta</span>
                        @else
                            <span class="badge badge-neutral">Cerrada</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--ink-3);">Sin sesiones registradas todavía</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<x-modal id="modalNuevaCaja" titulo="Nueva caja">
    <form action="{{ route('admin.caja.store') }}" method="POST">
        @csrf
        <div class="form-grid">
            <div class="form-group">
                <label for="nombre">Nombre <span>*</span></label>
                <input type="text" id="nombre" name="nombre" required maxlength="100" placeholder="Caja 01">
            </div>
            <div class="form-group">
                <label for="descripcion">Descripción</label>
                <input type="text" id="descripcion" name="descripcion" maxlength="255">
            </div>
        </div>
        <div class="header-btns" style="justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalNuevaCaja">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-modal>

@endsection
