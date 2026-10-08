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
            <label for="cliente_ruc">RUC / DNI <span>*</span></label>
            <input type="text" id="cliente_ruc" name="cliente_ruc" required maxlength="20"
                   value="{{ old('cliente_ruc', $guia?->cliente_ruc ?? $venta?->cliente_ruc) }}">
            <small style="display:block;margin-top:4px;font-size:11px;color:var(--ink-3);">
                SUNAT exige identificar al destinatario — sin documento no se puede registrar la guía.
            </small>
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
            <label for="cod_traslado">Motivo <span>*</span></label>
            <select id="cod_traslado" name="cod_traslado" required>
                @foreach ($motivos as $codigo => $nombre)
                    <option value="{{ $codigo }}" @selected(old('cod_traslado', $guia?->cod_traslado) === $codigo)>{{ $codigo }} — {{ $nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label for="mod_traslado">Modalidad de traslado <span>*</span></label>
            <select id="mod_traslado" name="mod_traslado" required>
                <option value="">— Selecciona —</option>
                @foreach ($modalidades as $codigo => $nombre)
                    <option value="{{ $codigo }}" @selected(old('mod_traslado', $guia?->mod_traslado) === $codigo)>{{ $nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label for="peso_total">Peso total (kg) <span>*</span></label>
            <input type="number" id="peso_total" name="peso_total" required step="0.001" min="0.001"
                   value="{{ old('peso_total', $guia?->peso_total) }}">
        </div>
        <div class="form-group">
            <label for="bultos">Bultos <span>*</span></label>
            <input type="number" id="bultos" name="bultos" required min="1"
                   value="{{ old('bultos', $guia?->bultos ?? 1) }}">
        </div>
    </div>

    <div class="form-grid">
        <div class="form-group">
            <label for="punto_partida">Punto de partida <span>*</span></label>
            <input type="text" id="punto_partida" name="punto_partida" required maxlength="255"
                   value="{{ old('punto_partida', $guia?->punto_partida ?? config('rentaltech.empresa.direccion')) }}">
        </div>
        <div class="form-group">
            <label for="partida_ubigeo">Ubigeo de partida <span>*</span></label>
            <input type="text" id="partida_ubigeo" name="partida_ubigeo" required maxlength="6" pattern="\d{6}"
                   value="{{ old('partida_ubigeo', $guia?->partida_ubigeo ?? $ubigeoPropio ?? '') }}">
            <small style="display:block;margin-top:4px;font-size:11px;color:var(--ink-3);">Código SUNAT de 6 dígitos, no la dirección.</small>
        </div>
        <div class="form-group">
            <label for="punto_llegada">Punto de llegada <span>*</span></label>
            <input type="text" id="punto_llegada" name="punto_llegada" required maxlength="255"
                   value="{{ old('punto_llegada', $guia?->punto_llegada ?? $venta?->destino_entrega) }}">
        </div>
        <div class="form-group">
            <label for="llegada_ubigeo">Ubigeo de llegada <span>*</span></label>
            <input type="text" id="llegada_ubigeo" name="llegada_ubigeo" required maxlength="6" pattern="\d{6}"
                   value="{{ old('llegada_ubigeo', $guia?->llegada_ubigeo) }}">
            <small style="display:block;margin-top:4px;font-size:11px;color:var(--ink-3);">Código SUNAT de 6 dígitos, no la dirección.</small>
        </div>
    </div>
</div>

<div class="content-card" style="margin-top:25px;">
    <h3 style="margin-bottom:20px;font-size:18px;">Transportista</h3>
    <p class="nv-hint" id="transporteHint" style="margin:0 0 14px;">Elige la modalidad de traslado arriba para ver los datos que corresponden.</p>

    {{-- Transporte público (mod_traslado = 01): datos de la empresa transportista. --}}
    <div class="form-grid" id="grupoTransportePublico" style="display:none;">
        <div class="form-group">
            <label for="empresa_transporte">Empresa de transporte <span>*</span></label>
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
            <label for="transportista_ruc">RUC del transportista <span>*</span></label>
            <input type="text" id="transportista_ruc" name="transportista_ruc" maxlength="20"
                   value="{{ old('transportista_ruc', $guia?->transportista_ruc) }}">
        </div>
    </div>

    {{-- Transporte privado (mod_traslado = 02): vehículo y conductor propios. --}}
    <div class="form-grid" id="grupoTransportePrivado" style="display:none;">
        <div class="form-group">
            <label for="placa_vehiculo">Placa del vehículo <span>*</span></label>
            <input type="text" id="placa_vehiculo" name="placa_vehiculo" maxlength="20"
                   value="{{ old('placa_vehiculo', $guia?->placa_vehiculo) }}">
        </div>
        <div class="form-group">
            <label for="conductor_nombre">Conductor <span>*</span></label>
            <input type="text" id="conductor_nombre" name="conductor_nombre" maxlength="200"
                   value="{{ old('conductor_nombre', $guia?->conductor_nombre) }}">
        </div>
        <div class="form-group">
            <label for="conductor_dni">DNI del conductor <span>*</span></label>
            <input type="text" id="conductor_dni" name="conductor_dni" maxlength="15"
                   value="{{ old('conductor_dni', $guia?->conductor_dni) }}">
        </div>
        <div class="form-group">
            <label for="licencia_conductor">Licencia <span>*</span></label>
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

// ── Modalidad de traslado: público muestra datos del transportista,
// privado muestra vehículo/conductor propios — mismo patrón que la
// tarjeta de Bancarización en Factura (style.display directo, no el
// atributo `hidden`, para que no quede neutralizado por el estilo en línea).
const modTraslado = document.getElementById('mod_traslado');
const grupoPublico = document.getElementById('grupoTransportePublico');
const grupoPrivado = document.getElementById('grupoTransportePrivado');
const transporteHint = document.getElementById('transporteHint');

function actualizarModalidadTraslado() {
    const esPublico = modTraslado.value === '01';
    const esPrivado = modTraslado.value === '02';

    grupoPublico.style.display = esPublico ? 'grid' : 'none';
    grupoPrivado.style.display = esPrivado ? 'grid' : 'none';
    transporteHint.style.display = (esPublico || esPrivado) ? 'none' : 'block';

    document.getElementById('transportista_ruc').required = esPublico;
    document.getElementById('placa_vehiculo').required = esPrivado;
    document.getElementById('conductor_nombre').required = esPrivado;
    document.getElementById('conductor_dni').required = esPrivado;
    document.getElementById('licencia_conductor').required = esPrivado;
}

modTraslado.addEventListener('change', actualizarModalidadTraslado);
actualizarModalidadTraslado();

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
