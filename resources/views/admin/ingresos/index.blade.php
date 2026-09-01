@extends('layouts.admin')

@section('title', 'Ingresos')
@section('crumb', 'Movimientos de caja')

@section('content')

<x-page-header titulo="Ingresos" subtitulo="Ingresos registrados fuera del módulo de ventas">
    <x-slot:acciones>
        <button type="button" class="btn btn-primary" data-modal="modalIngreso">
            <span class="btn-icon">＋</span><span class="btn-text">Nuevo Ingreso</span>
        </button>
    </x-slot:acciones>
</x-page-header>

<div class="stats-grid">
    <x-stat-card :valor="'S/ ' . number_format($totalMes, 2)" etiqueta="Total del mes" />
    <x-stat-card :valor="number_format($ingresos->total())" etiqueta="Movimientos" />
    <x-stat-card :valor="number_format($porTipo->count())" etiqueta="Tipos registrados" />
</div>

<div class="content-card">
    <div class="lista-header">
        <h3>Ingresos del periodo</h3>

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
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                    <th>Método</th>
                    <th>Monto</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($ingresos as $ingreso)
                <tr>
                    <td>{{ $ingreso->fecha?->format("d/m/Y") }}</td>
                    <td>{{ $ingreso->tipo ?: "—" }}</td>
                    <td>{{ $ingreso->descripcion ?: "—" }}</td>
                    <td>{{ ucfirst($ingreso->metodo_pago ?? "—") }}</td>
                    <td><strong>S/ {{ number_format($ingreso->monto, 2) }}</strong></td>
                    <td style="white-space:nowrap;">
                        <button type="button" class="btn btn-dark btn-sm"
                                data-modal="modalIngreso"
                                data-campo-registro_id="{{ $ingreso->id }}"
                                data-campo-fecha="{{ $ingreso->fecha instanceof \DateTimeInterface ? $ingreso->fecha->format('Y-m-d') : $ingreso->fecha }}"
                                data-campo-tipo="{{ $ingreso->tipo }}"
                                data-campo-monto="{{ $ingreso->monto }}"
                                data-campo-metodo_pago="{{ $ingreso->metodo_pago }}"
                                data-campo-descripcion="{{ $ingreso->descripcion }}"
                        >
                            Editar
                        </button>
                        <form method="POST" action="{{ route('admin.ingresos.destroy', $ingreso) }}"
                              style="display:inline;" data-confirmar="¿Eliminar este ingreso?">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;padding:40px;color:#666;">Sin registros</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $ingresos->links() }}
</div>

<x-modal id="modalIngreso" titulo="Nuevo Ingreso">
    <form method="POST" action="{{ route('admin.ingresos.store') }}" id="formIngreso">
        @csrf
        <input type="hidden" name="registro_id" value="">

        <div class="form-grid">
            <div class="form-group">
                <label for="fecha">Fecha <span>*</span></label>
                <input type="date" id="fecha" name="fecha" required >
            </div>
            <div class="form-group">
                <label for="tipo">Tipo</label>
                <input type="text" id="tipo" name="tipo" maxlength="50">
            </div>
            <div class="form-group">
                <label for="monto">Monto <span>*</span></label>
                <input type="number" id="monto" name="monto" required step="0.01" min="0">
            </div>
            <div class="form-group">
                <label for="metodo_pago">Método de pago</label>
                <select id="metodo_pago" name="metodo_pago">
                    <option value="efectivo">Efectivo</option>
                    <option value="transferencia">Transferencia</option>
                    <option value="deposito">Depósito</option>
                    <option value="yape">Yape / Plin</option>
                    <option value="otro">Otro</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="descripcion">Descripción</label>
            <textarea id="descripcion" name="descripcion" rows="2"></textarea>
        </div>


        <div class="header-btns" style="justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalIngreso">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-modal>
@endsection

@push('scripts')
<script>
// El mismo modal sirve para alta y edición: si trae ID, se envía como PUT.
document.getElementById('formIngreso').addEventListener('submit', function () {
    const id = this.querySelector('[name="registro_id"]').value;
    if (!id) return;

    this.action = '{{ url('admin/ingresos') }}/' + id;
    if (!this.querySelector('[name="_method"]')) {
        const metodo = document.createElement('input');
        metodo.type = 'hidden';
        metodo.name = '_method';
        metodo.value = 'PUT';
        this.appendChild(metodo);
    }
});
</script>
@endpush
