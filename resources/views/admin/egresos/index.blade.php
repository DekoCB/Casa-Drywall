@extends('layouts.admin')

@section('title', 'Egresos')
@section('crumb', 'Finanzas')

@section('content')

@php
    $etiquetasTipo = ['flete' => 'Flete / Transporte', 'gasolina' => 'Gasolina', 'promocion' => 'Promoción', 'operativo' => 'Operativo', 'compra' => 'Compra', 'diversos' => 'Gastos diversos', 'planilla' => 'Planilla', 'otro' => 'Otro'];
@endphp

@if ($tipoSel !== '')
    <div style="margin-bottom:12px;">
        <span class="badge badge-warning">Filtro: {{ $etiquetasTipo[$tipoSel] ?? $tipoSel }}
            <a href="{{ route('admin.egresos.index', ['mes' => $mes, 'anio' => $anio]) }}" style="margin-left:6px;color:inherit;">✕</a>
        </span>
    </div>
@endif

<x-page-header titulo="Egresos" subtitulo="Salidas de dinero: manuales y las generadas por ventas, órdenes y planilla">
    <x-slot:acciones>
        <form method="POST" action="{{ route('admin.egresos.sincronizar') }}" style="display:inline;">
            @csrf
            <input type="hidden" name="anio" value="{{ $anio }}">
            <input type="hidden" name="mes" value="{{ $mes }}">
            <button type="submit" class="btn btn-secondary btn-sm">
                <span class="btn-text">🔄 Sincronizar</span>
            </button>
        </form>
        <button type="button" class="btn btn-primary" data-modal="modalEgreso">
            <span class="btn-icon">＋</span><span class="btn-text">Nuevo Egreso</span>
        </button>
    </x-slot:acciones>
</x-page-header>

<div class="stats-grid">
    <x-stat-card :valor="'S/ ' . number_format($totalMes, 2)" etiqueta="Total del mes" />
    <x-stat-card :valor="'S/ ' . number_format($totalManual, 2)" etiqueta="Egresos manuales" />
    <x-stat-card :valor="'S/ ' . number_format($totalMes - $totalManual, 2)" etiqueta="Egresos automáticos" />
    <x-stat-card :valor="number_format($porTipo->count())" etiqueta="Categorías del mes" />
</div>

<div class="content-card">
    <div class="lista-header">
        <h3>Egresos del periodo</h3>

        <form method="GET" class="filtros">
            <label class="filtro-campo" for="mes">
                <span>Mes</span>
                <select id="mes" name="mes">
                @foreach (range(1, 12) as $m)
                <option value="{{ $m }}" @selected($mes === $m)>
                {{ \Carbon\Carbon::create($anio, $m, 1)->translatedFormat('F') }}
                </option>
                @endforeach
                </select>
            </label>
            <label class="filtro-campo" for="anio">
                <span>Año</span>
                <select id="anio" name="anio">
                @foreach ($anios as $a)
                <option value="{{ $a }}" @selected($anio == $a)>{{ $a }}</option>
                @endforeach
                </select>
            </label>
            <button type="submit" class="btn btn-primary btn-sm"><span class="btn-text">Ver periodo</span></button>
        </form>
    </div>

    @if ($porTipo->isNotEmpty())
        <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:20px;">
            @foreach ($porTipo as $tipo)
                <span style="padding:6px 14px;border-radius:20px;background:#f6f5f1;font-size:13px;">
                    {{ ucfirst($tipo->tipo ?? 'Otros') }}:
                    <strong>S/ {{ number_format($tipo->total, 2) }}</strong>
                    <span style="color:#666;">({{ $tipo->n }})</span>
                </span>
            @endforeach
        </div>
    @endif

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                    <th>Origen</th>
                    <th>Monto</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($egresos as $egreso)
                <tr>
                    <td>{{ $egreso->fecha?->format('d/m/Y') }}</td>
                    <td>
                        {{ ucfirst($egreso->tipo ?? '—') }}
                        <div style="font-size:12px;color:#666;">{{ $egreso->categoria }}</div>
                    </td>
                    <td>{{ $egreso->descripcion ?: '—' }}</td>
                    <td>
                        @if ($egreso->origen === 'manual')
                            <span style="font-size:11px;font-weight:700;padding:3px 9px;border-radius:9px;background:#eef2f6;color:#5b6270;">Manual</span>
                        @else
                            <span style="font-size:11px;font-weight:700;padding:3px 9px;border-radius:9px;background:#ecfdf5;color:#15803d;">
                                🔄 {{ $origenes[$egreso->origen] ?? $egreso->origen }}
                            </span>
                        @endif
                    </td>
                    <td><strong>S/ {{ number_format($egreso->monto, 2) }}</strong></td>
                    <td style="white-space:nowrap;">
                        @if ($egreso->origen === 'manual')
                            <button type="button" class="btn btn-dark btn-sm"
                                    data-modal="modalEgreso"
                                    data-campo-registro_id="{{ $egreso->id }}"
                                    data-campo-fecha="{{ $egreso->fecha?->format('Y-m-d') }}"
                                    data-campo-tipo="{{ $egreso->tipo }}"
                                    data-campo-categoria="{{ $egreso->categoria }}"
                                    data-campo-descripcion="{{ $egreso->descripcion }}"
                                    data-campo-monto="{{ $egreso->monto }}"
                                    data-campo-almacen_id="{{ $egreso->almacen_id }}">
                                Editar
                            </button>
                            <form method="POST" action="{{ route('admin.egresos.destroy', $egreso) }}"
                                  style="display:inline;" data-confirmar="¿Eliminar este egreso?">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                            </form>
                        @else
                            <span style="color:#999;font-size:12px;">Gestionado desde su módulo</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;padding:40px;color:#666;">Sin egresos en el periodo</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $egresos->links() }}
</div>

<x-modal id="modalEgreso" titulo="Datos del egreso">
    <form method="POST" action="{{ route('admin.egresos.store') }}" id="formEgreso">
        @csrf
        <input type="hidden" name="registro_id" value="">

        <div class="form-grid">
            <div class="form-group">
                <label for="fecha">Fecha <span>*</span></label>
                <input type="date" id="fecha" name="fecha" required value="{{ now()->toDateString() }}">
            </div>
            <div class="form-group">
                <label for="tipo">Tipo <span>*</span></label>
                <select id="tipo" name="tipo" required>
                    @foreach ($etiquetasTipo as $valor => $texto)
                        <option value="{{ $valor }}" @selected($tipoSel === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="categoria">Categoría</label>
                <input type="text" id="categoria" name="categoria" maxlength="50">
            </div>
            <div class="form-group">
                <label for="monto">Monto <span>*</span></label>
                <input type="number" id="monto" name="monto" step="0.01" min="0" required>
            </div>
            <div class="form-group">
                <label for="almacen_id">Almacén</label>
                <select id="almacen_id" name="almacen_id">
                    <option value="">—</option>
                    @foreach ($almacenes as $almacen)
                        <option value="{{ $almacen->id }}">{{ $almacen->nombre }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="descripcion">Descripción</label>
            <textarea id="descripcion" name="descripcion" rows="2"></textarea>
        </div>

        <div class="header-btns" style="justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalEgreso">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-modal>
@endsection

@push('scripts')
<script>
document.getElementById('formEgreso').addEventListener('submit', function () {
    const id = this.querySelector('[name="registro_id"]').value;
    if (!id) return;

    this.action = '{{ url('admin/egresos') }}/' + id;
    if (!this.querySelector('[name="_method"]')) {
        const metodo = document.createElement('input');
        metodo.type = 'hidden';
        metodo.name = '_method';
        metodo.value = 'PUT';
        this.appendChild(metodo);
    }
});

@if ($abrirCrear)
    abrirModal('modalEgreso');
@endif
</script>
@endpush
