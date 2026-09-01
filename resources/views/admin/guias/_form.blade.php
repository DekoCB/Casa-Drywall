@php
    /** @var \App\Models\GuiaRemision|null $guia */
    $guia      = $guia ?? null;
    $productos = $guia?->productos ?? [];
    $venta     = $venta ?? null;
@endphp

<div class="content-card">
    <h3 style="margin-bottom:20px;font-size:18px;">Destinatario</h3>

    @isset($ventas)
    <div class="form-group">
        <label for="venta_select">Generar desde una venta</label>
        <select id="venta_select" name="venta_id">
            <option value="">— Sin venta asociada —</option>
            @foreach ($ventas as $v)
                <option value="{{ $v->id }}"
                        @selected(old('venta_id', $guia?->venta_id ?? $venta?->id) == $v->id)
                        data-numero="{{ $v->numero_venta }}"
                        data-cliente="{{ $v->cliente_nombre }}"
                        data-ruc="{{ $v->cliente_ruc }}"
                        data-direccion="{{ $v->cliente_direccion }}"
                        data-distrito="{{ $v->cliente_distrito }}"
                        data-destino="{{ $v->destino_entrega }}"
                        data-transporte="{{ $v->empresa_transporte }}">
                    {{ $v->numero_venta }} — {{ $v->cliente_nombre }} ({{ $v->fecha?->format('d/m/Y') }})
                </option>
            @endforeach
        </select>
    </div>
    @endisset

    <div class="form-grid">
        <div class="form-group">
            <label for="numero_venta">N° de venta</label>
            <input type="text" id="numero_venta" name="numero_venta" maxlength="40"
                   value="{{ old('numero_venta', $guia?->numero_venta ?? $venta?->numero_venta) }}">
        </div>
        <div class="form-group">
            <label for="cliente_nombre">Destinatario <span>*</span></label>
            <input type="text" id="cliente_nombre" name="cliente_nombre" required maxlength="200"
                   value="{{ old('cliente_nombre', $guia?->cliente_nombre ?? $venta?->cliente_nombre) }}">
        </div>
        <div class="form-group">
            <label for="cliente_ruc">RUC / DNI</label>
            <input type="text" id="cliente_ruc" name="cliente_ruc" maxlength="20"
                   value="{{ old('cliente_ruc', $guia?->cliente_ruc ?? $venta?->cliente_ruc) }}">
        </div>
        <div class="form-group">
            <label for="cliente_distrito">Distrito</label>
            <input type="text" id="cliente_distrito" name="cliente_distrito" maxlength="100"
                   value="{{ old('cliente_distrito', $guia?->cliente_distrito ?? $venta?->cliente_distrito) }}">
        </div>
        <div class="form-group">
            <label for="cliente_provincia">Provincia</label>
            <input type="text" id="cliente_provincia" name="cliente_provincia" maxlength="100"
                   value="{{ old('cliente_provincia', $guia?->cliente_provincia) }}">
        </div>
        <div class="form-group">
            <label for="cliente_departamento">Departamento</label>
            <input type="text" id="cliente_departamento" name="cliente_departamento" maxlength="100"
                   value="{{ old('cliente_departamento', $guia?->cliente_departamento) }}">
        </div>
    </div>

    <div class="form-group">
        <label for="cliente_direccion">Dirección del destinatario</label>
        <input type="text" id="cliente_direccion" name="cliente_direccion" maxlength="255"
               value="{{ old('cliente_direccion', $guia?->cliente_direccion ?? $venta?->cliente_direccion) }}">
    </div>
</div>

<div class="content-card" style="margin-top:25px;">
    <h3 style="margin-bottom:20px;font-size:18px;">Traslado</h3>

    <div class="form-grid">
        <div class="form-group">
            <label for="fecha">Fecha de emisión <span>*</span></label>
            <input type="date" id="fecha" name="fecha" required
                   value="{{ old('fecha', $guia?->fecha?->format('Y-m-d') ?? now()->toDateString()) }}">
        </div>
        <div class="form-group">
            <label for="fecha_traslado">Fecha de traslado</label>
            <input type="date" id="fecha_traslado" name="fecha_traslado"
                   value="{{ old('fecha_traslado', $guia?->fecha_traslado?->format('Y-m-d')) }}">
        </div>
        <div class="form-group">
            <label for="motivo_traslado">Motivo <span>*</span></label>
            <select id="motivo_traslado" name="motivo_traslado" required>
                @foreach ($motivos as $motivo)
                    <option value="{{ $motivo }}" @selected(old('motivo_traslado', $guia?->motivo_traslado) === $motivo)>{{ $motivo }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label for="peso_total">Peso total</label>
            <input type="text" id="peso_total" name="peso_total" maxlength="30"
                   value="{{ old('peso_total', $guia?->peso_total) }}">
        </div>
        <div class="form-group">
            <label for="bultos">Bultos</label>
            <input type="number" id="bultos" name="bultos" min="0"
                   value="{{ old('bultos', $guia?->bultos) }}">
        </div>
    </div>

    <div class="form-grid">
        <div class="form-group">
            <label for="punto_partida">Punto de partida <span>*</span></label>
            <input type="text" id="punto_partida" name="punto_partida" required maxlength="255"
                   value="{{ old('punto_partida', $guia?->punto_partida ?? config('rentaltech.empresa.direccion')) }}">
        </div>
        <div class="form-group">
            <label for="punto_llegada">Punto de llegada <span>*</span></label>
            <input type="text" id="punto_llegada" name="punto_llegada" required maxlength="255"
                   value="{{ old('punto_llegada', $guia?->punto_llegada ?? $venta?->destino_entrega) }}">
        </div>
    </div>
</div>

<div class="content-card" style="margin-top:25px;">
    <h3 style="margin-bottom:20px;font-size:18px;">Transportista</h3>

    <div class="form-grid">
        <div class="form-group">
            <label for="empresa_transporte">Empresa de transporte</label>
            <select id="empresa_transporte" name="empresa_transporte">
                <option value="">—</option>
                @foreach ($empresas as $emp)
                    <option value="{{ $emp->nombre }}"
                            @selected(old('empresa_transporte', $guia?->empresa_transporte ?? $venta?->empresa_transporte) === $emp->nombre)>
                        {{ $emp->nombre }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label for="transportista_ruc">RUC del transportista</label>
            <input type="text" id="transportista_ruc" name="transportista_ruc" maxlength="20"
                   value="{{ old('transportista_ruc', $guia?->transportista_ruc) }}">
        </div>
        <div class="form-group">
            <label for="placa_vehiculo">Placa del vehículo</label>
            <input type="text" id="placa_vehiculo" name="placa_vehiculo" maxlength="20"
                   value="{{ old('placa_vehiculo', $guia?->placa_vehiculo) }}">
        </div>
        <div class="form-group">
            <label for="conductor_nombre">Conductor</label>
            <input type="text" id="conductor_nombre" name="conductor_nombre" maxlength="200"
                   value="{{ old('conductor_nombre', $guia?->conductor_nombre) }}">
        </div>
        <div class="form-group">
            <label for="licencia_conductor">Licencia</label>
            <input type="text" id="licencia_conductor" name="licencia_conductor" maxlength="20"
                   value="{{ old('licencia_conductor', $guia?->licencia_conductor) }}">
        </div>
    </div>

    <div class="form-group">
        <label for="observaciones">Observaciones</label>
        <textarea id="observaciones" name="observaciones" rows="2">{{ old('observaciones', $guia?->observaciones) }}</textarea>
    </div>
</div>

<div class="content-card" style="margin-top:25px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <h3 style="font-size:18px;margin:0;">Bienes a trasladar</h3>
        <button type="button" class="btn btn-dark btn-sm" id="btnAgregarItem">
            <span class="btn-text">＋ Agregar línea</span>
        </button>
    </div>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:18%;">Código</th>
                    <th style="width:44%;">Descripción</th>
                    <th style="width:14%;">Cantidad</th>
                    <th style="width:16%;">Peso</th>
                    <th style="width:8%;"></th>
                </tr>
            </thead>
            <tbody id="itemsBody">
            @foreach ($productos as $i => $prod)
                <tr class="fila-item">
                    <td><input type="text" name="productos[{{ $i }}][codigo]" value="{{ $prod['codigo'] ?? '' }}"></td>
                    <td><input type="text" name="productos[{{ $i }}][nombre]" value="{{ $prod['nombre'] ?? '' }}" list="listaProductos"></td>
                    <td><input type="number" name="productos[{{ $i }}][cantidad]" value="{{ $prod['cantidad'] ?? 1 }}" min="1"></td>
                    <td><input type="text" name="productos[{{ $i }}][peso]" value="{{ $prod['peso'] ?? '' }}"></td>
                    <td><button type="button" class="btn btn-danger btn-sm btn-quitar">✕</button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <datalist id="listaProductos">
        @foreach ($productos ?? [] as $p) @endforeach
        @isset($productos)
            @foreach (($productosCatalogo ?? collect()) as $prod)
                <option value="{{ $prod->nombre }}" data-codigo="{{ $prod->codigo }}" data-peso="{{ $prod->peso }}"></option>
            @endforeach
        @endisset
    </datalist>
</div>

@push('scripts')
<script>
const cuerpo = document.getElementById('itemsBody');
let indice = {{ count($productos) }};

document.getElementById('btnAgregarItem').addEventListener('click', () => {
    cuerpo.insertAdjacentHTML('beforeend', `<tr class="fila-item">
        <td><input type="text" name="productos[${indice}][codigo]"></td>
        <td><input type="text" name="productos[${indice}][nombre]" list="listaProductos"></td>
        <td><input type="number" name="productos[${indice}][cantidad]" value="1" min="1"></td>
        <td><input type="text" name="productos[${indice}][peso]"></td>
        <td><button type="button" class="btn btn-danger btn-sm btn-quitar">✕</button></td>
    </tr>`);
    indice++;
});

cuerpo.addEventListener('click', (e) => {
    if (e.target.classList.contains('btn-quitar')) {
        e.target.closest('tr').remove();
    }
});

// Al elegir una venta se copian los datos del destinatario y el destino.
document.getElementById('venta_select')?.addEventListener('change', function () {
    const opcion = this.selectedOptions[0];
    if (!opcion?.dataset.cliente) return;

    document.getElementById('numero_venta').value       = opcion.dataset.numero || '';
    document.getElementById('cliente_nombre').value     = opcion.dataset.cliente || '';
    document.getElementById('cliente_ruc').value        = opcion.dataset.ruc || '';
    document.getElementById('cliente_direccion').value  = opcion.dataset.direccion || '';
    document.getElementById('cliente_distrito').value   = opcion.dataset.distrito || '';
    document.getElementById('punto_llegada').value      = opcion.dataset.destino || '';

    const transporte = document.getElementById('empresa_transporte');
    if (transporte && opcion.dataset.transporte) transporte.value = opcion.dataset.transporte;
});
</script>
@endpush
