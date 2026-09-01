@php
    /** @var \App\Models\OrdenCompra|null $orden */
    $orden = $orden ?? null;
    $productos = $orden?->productos ?? [];
    $merchOrden = $orden?->merch ?? [];
    $catalogoMerch = $catalogoMerch ?? collect();

    // Las líneas viejas guardan `precio`; las del formulario nuevo, `precio_unit_usd`.
    $precioLinea = fn (array $p) => (float) ($p['precio_unit_usd'] ?? $p['precio'] ?? 0);
    $totalMerch = collect($merchOrden)->sum(fn ($l) => ($l['cantidad'] ?? 0) * ($l['costo_unit'] ?? 0));
@endphp

<div class="oc-hoja">

    {{-- ══ Membrete ══ --}}
    <div class="ocd-membrete">
        <div>
            <span class="ocd-tipo">Editando orden de compra</span>
            <div class="ocd-emisor-nombre" id="ocr-proveedor">{{ $orden?->proveedor ?: 'Sin proveedor' }}</div>
            <div class="ocd-emisor-dato">RUC <span id="ocr-ruc">{{ $orden?->ruc ?: '—' }}</span></div>
        </div>

        <div class="ocd-membrete-der">
            <div class="ocd-numero" id="ocr-numero">{{ $orden?->numero_orden ?: '—' }}</div>
            <div class="ocd-fecha" id="ocr-fecha">—</div>
        </div>

        <div class="ocd-membrete-pie">
            <div class="ocd-pie-dato">Para el cliente <strong id="ocr-cliente">{{ $orden?->cliente_ref ?: 'Sin asignar' }}</strong></div>
            <div class="ocd-pie-dato">Estado <strong id="ocr-estado">{{ $orden?->estado ?: 'Pendiente' }}</strong></div>
        </div>
    </div>

    <div class="ocd-cuerpo">

        {{-- ══ 1. Proveedor ══ --}}
        <div class="ocd-seccion">
            <span class="ocd-num">1</span>
            <div>
                <div class="ocd-tit">Proveedor</div>
                <div class="ocd-sub">A quién se le compra; elígelo del listado o escríbelo</div>
            </div>
        </div>

        <div class="oc-form-grid">
            <div class="oc-campo oc-form-full">
                <label class="oc-label" for="proveedor_select">Buscar en proveedores registrados</label>
                <select class="oc-input" id="proveedor_select">
                    <option value="">— Escribir manualmente —</option>
                    @foreach ($proveedores as $prov)
                        <option value="{{ $prov->id }}"
                                data-razon="{{ $prov->razon_social }}"
                                data-ruc="{{ $prov->ruc }}"
                                data-telefono="{{ $prov->telefono }}"
                                data-correo="{{ $prov->email }}"
                                data-direccion="{{ $prov->direccion }}"
                                data-distrito="{{ $prov->distrito }}"
                                data-provincia="{{ $prov->provincia }}"
                                data-departamento="{{ $prov->departamento }}"
                                data-condicion="{{ $prov->condiciones_pago }}">
                            {{ $prov->razon_social }} — {{ $prov->ruc }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="oc-campo">
                <label class="oc-label" for="proveedor">Razón social <span>*</span></label>
                <input type="text" class="oc-input" id="proveedor" name="proveedor" required maxlength="255"
                       value="{{ old('proveedor', $orden?->proveedor) }}">
            </div>
            <div class="oc-campo">
                <label class="oc-label" for="ruc">RUC</label>
                <input type="text" class="oc-input mono" id="ruc" name="ruc" maxlength="20" value="{{ old('ruc', $orden?->ruc) }}">
            </div>
            <div class="oc-campo">
                <label class="oc-label" for="telefono">Teléfono</label>
                <input type="text" class="oc-input" id="telefono" name="telefono" maxlength="50" value="{{ old('telefono', $orden?->telefono) }}">
            </div>
            <div class="oc-campo">
                <label class="oc-label" for="correo">Correo</label>
                <input type="email" class="oc-input" id="correo" name="correo" maxlength="150" value="{{ old('correo', $orden?->correo) }}">
            </div>
            <div class="oc-campo">
                <label class="oc-label" for="distrito">Distrito</label>
                <input type="text" class="oc-input" id="distrito" name="distrito" maxlength="100" value="{{ old('distrito', $orden?->distrito) }}">
            </div>
            <div class="oc-campo">
                <label class="oc-label" for="provincia">Provincia</label>
                <input type="text" class="oc-input" id="provincia" name="provincia" maxlength="100" value="{{ old('provincia', $orden?->provincia) }}">
            </div>
            <div class="oc-campo">
                <label class="oc-label" for="departamento">Departamento</label>
                <input type="text" class="oc-input" id="departamento" name="departamento" maxlength="100" value="{{ old('departamento', $orden?->departamento) }}">
            </div>
            <div class="oc-campo oc-form-full">
                <label class="oc-label" for="direccion">Dirección</label>
                <textarea class="oc-input" id="direccion" name="direccion" rows="2">{{ old('direccion', $orden?->direccion) }}</textarea>
            </div>
        </div>

        {{-- ══ 2. Datos de la orden ══ --}}
        <div class="ocd-seccion">
            <span class="ocd-num">2</span>
            <div>
                <div class="ocd-tit">Datos de la orden</div>
                <div class="ocd-sub">Número, fecha, estado y documentos de la compra</div>
            </div>
        </div>

        <div class="oc-form-grid">
            <div class="oc-campo">
                <label class="oc-label" for="numero_orden">N° de orden</label>
                <input type="text" class="oc-input mono" id="numero_orden" name="numero_orden" maxlength="50"
                       value="{{ old('numero_orden', $orden?->numero_orden) }}" placeholder="Se genera automáticamente">
            </div>
            <div class="oc-campo">
                <label class="oc-label" for="fecha">Fecha <span>*</span></label>
                <input type="date" class="oc-input" id="fecha" name="fecha" required
                       value="{{ old('fecha', $orden?->fecha?->format('Y-m-d') ?? now()->toDateString()) }}">
            </div>
            <div class="oc-campo">
                <label class="oc-label" for="estado">Estado <span>*</span></label>
                <select class="oc-input" id="estado" name="estado" required>
                    @foreach ($estados as $est)
                        <option value="{{ $est }}" @selected(old('estado', $orden?->estado) === $est)>{{ $est }}</option>
                    @endforeach
                </select>
            </div>
            <div class="oc-campo">
                <label class="oc-label" for="cliente_ref">Cliente de referencia</label>
                <input type="text" class="oc-input" id="cliente_ref" name="cliente_ref" maxlength="200"
                       value="{{ old('cliente_ref', $orden?->cliente_ref) }}">
            </div>
            <div class="oc-campo">
                <label class="oc-label" for="nro_factura">N° de factura</label>
                <input type="text" class="oc-input" id="nro_factura" name="nro_factura" maxlength="100"
                       value="{{ old('nro_factura', $orden?->nro_factura) }}">
            </div>
            <div class="oc-campo">
                <label class="oc-label" for="nro_guia">N° de guía</label>
                <input type="text" class="oc-input" id="nro_guia" name="nro_guia" maxlength="100"
                       value="{{ old('nro_guia', $orden?->nro_guia) }}">
            </div>
            <div class="oc-campo">
                <label class="oc-label" for="ref_fecha">Fecha de referencia</label>
                <input type="text" class="oc-input mono" id="ref_fecha" name="ref_fecha" maxlength="20"
                       value="{{ old('ref_fecha', $orden?->ref_fecha) }}">
            </div>
            <div class="oc-campo">
                <label class="oc-label" for="empresa_transporte">Empresa de transporte</label>
                <select class="oc-input" id="empresa_transporte" name="empresa_transporte">
                    <option value="">—</option>
                    @foreach ($empresas as $emp)
                        <option value="{{ $emp->nombre }}" @selected(old('empresa_transporte', $orden?->empresa_transporte) === $emp->nombre)>
                            {{ $emp->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="oc-campo">
                <label class="oc-label" for="peso">Peso</label>
                <input type="text" class="oc-input mono" id="peso" name="peso" maxlength="30" value="{{ old('peso', $orden?->peso) }}">
            </div>
            <div class="oc-campo">
                <label class="oc-label" for="bultos">Bultos</label>
                <input type="number" class="oc-input mono" id="bultos" name="bultos" min="0" value="{{ old('bultos', $orden?->bultos) }}">
            </div>
            <div class="oc-campo">
                <label class="oc-label" for="vendedor">Vendedor</label>
                <input type="text" class="oc-input" id="vendedor" name="vendedor" maxlength="100"
                       value="{{ old('vendedor', $orden?->vendedor) }}">
            </div>
            <div class="oc-campo">
                <label class="oc-label" for="cod_vendedor">Código de vendedor</label>
                <input type="text" class="oc-input mono" id="cod_vendedor" name="cod_vendedor" maxlength="20"
                       value="{{ old('cod_vendedor', $orden?->cod_vendedor) }}">
            </div>
        </div>

        {{-- ══ 3. Productos ══ --}}
        <div class="ocd-seccion">
            <span class="ocd-num">3</span>
            <div>
                <div class="ocd-tit">Productos</div>
                <div class="ocd-sub">Las líneas de la orden y su precio unitario</div>
            </div>
            <span class="ocd-etiqueta">Dólares</span>
        </div>

        <div style="margin-bottom:12px;">
            <button type="button" class="ocm-btn oscuro" id="btnAgregarItem">＋ Agregar línea</button>
        </div>

        <div style="overflow-x:auto;">
            <table class="ocm-tabla">
                <thead>
                    <tr>
                        <th style="width:16%;">Código</th>
                        <th style="width:38%;">Descripción</th>
                        <th style="width:12%;">Cantidad</th>
                        <th style="width:16%;">Precio USD</th>
                        <th style="width:14%;" class="num">Subtotal</th>
                        <th style="width:4%;"></th>
                    </tr>
                </thead>
                <tbody id="itemsBody">
                @foreach ($productos as $i => $prod)
                    <tr class="fila-item">
                        <td><input type="text" class="oc-input mono" name="productos[{{ $i }}][codigo]" value="{{ $prod['codigo'] ?? '' }}"></td>
                        <td><input type="text" class="oc-input" name="productos[{{ $i }}][descripcion]" value="{{ $prod['descripcion'] ?? ($prod['nombre'] ?? '') }}"></td>
                        <td><input type="number" class="oc-input mono item-cantidad" name="productos[{{ $i }}][cantidad]" value="{{ $prod['cantidad'] ?? 1 }}" min="1"></td>
                        <td><input type="number" class="oc-input mono item-precio" name="productos[{{ $i }}][precio]" value="{{ $precioLinea($prod) }}" step="0.0001" min="0"></td>
                        <td class="num item-subtotal">$ {{ number_format(($prod['cantidad'] ?? 0) * $precioLinea($prod), 2) }}</td>
                        <td><button type="button" class="ocm-btn peligro btn-quitar">✕</button></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        {{-- ══ 4. Merch ══ --}}
        <div class="ocd-seccion">
            <span class="ocd-num opcional">4</span>
            <div>
                <div class="ocd-tit">Merch para clientes</div>
                <div class="ocd-sub">Entra al stock de Merch y genera el egreso de promoción</div>
            </div>
            <span class="ocd-etiqueta">Soles</span>
        </div>

        @if ($catalogoMerch->isEmpty())
            <p style="color:var(--ocm-suave);font-size:13px;">
                No hay artículos en el catálogo de merch.
                <a href="{{ route('admin.merch.index') }}" target="_blank">Créalos primero</a>.
            </p>
        @else
            <div style="margin-bottom:12px;">
                <button type="button" class="ocm-btn oscuro" id="btnAgregarMerch">＋ Agregar merch</button>
            </div>

            <div style="overflow-x:auto;">
                <table class="ocm-tabla">
                    <thead>
                        <tr>
                            <th style="width:46%;">Artículo</th>
                            <th style="width:16%;">Cantidad</th>
                            <th style="width:18%;">Costo unit. S/</th>
                            <th style="width:16%;" class="num">Subtotal</th>
                            <th style="width:4%;"></th>
                        </tr>
                    </thead>
                    <tbody id="merchBody">
                    @foreach ($merchOrden as $i => $linea)
                        <tr class="fila-merch">
                            <td>
                                <select class="oc-input merch-articulo" name="merch[{{ $i }}][merch_id]">
                                    @foreach ($catalogoMerch as $articulo)
                                        <option value="{{ $articulo->id }}"
                                                data-precio="{{ $articulo->precio }}"
                                                @selected((int) ($linea['merch_id'] ?? 0) === $articulo->id)>{{ $articulo->nombre }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="number" class="oc-input mono merch-cantidad" name="merch[{{ $i }}][cantidad]" value="{{ $linea['cantidad'] ?? 1 }}" min="1" step="1"></td>
                            <td><input type="number" class="oc-input mono merch-costo" name="merch[{{ $i }}][costo_unit]" value="{{ $linea['costo_unit'] ?? 0 }}" min="0" step="0.01"></td>
                            <td class="num merch-subtotal">S/ {{ number_format(($linea['cantidad'] ?? 0) * ($linea['costo_unit'] ?? 0), 2) }}</td>
                            <td><button type="button" class="ocm-btn peligro btn-quitar-merch">✕</button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- ══ 5. Costos y notas ══ --}}
        <div class="ocd-seccion">
            <span class="ocd-num">5</span>
            <div>
                <div class="ocd-tit">Costos y observaciones</div>
                <div class="ocd-sub">Tipo de cambio, precio de venta y notas de la orden</div>
            </div>
        </div>

        <div class="oc-form-grid">
            <div class="oc-campo">
                <label class="oc-label" for="tc">Tipo de cambio (S/$)</label>
                <input type="number" class="oc-input mono" id="tc" name="tc" step="0.0001" min="0"
                       value="{{ old('tc', $orden?->tc ?? config('rentaltech.tipo_cambio')) }}">
            </div>
            <div class="oc-campo">
                <label class="oc-label" for="condicion_pago">Condición de pago</label>
                <input type="text" class="oc-input" id="condicion_pago" name="condicion_pago" maxlength="100"
                       value="{{ old('condicion_pago', $orden?->condicion_pago) }}">
            </div>
            <div class="oc-campo">
                <label class="oc-label" for="precio_venta">Precio de venta referencial (S/)</label>
                <input type="number" class="oc-input mono" id="precio_venta" name="precio_venta" step="0.01" min="0"
                       value="{{ old('precio_venta', $orden?->precio_venta) }}">
            </div>
            <div class="oc-campo">
                <label class="oc-label" for="gasto_unit">Gasto unitario (S/)</label>
                <input type="number" class="oc-input mono" id="gasto_unit" name="gasto_unit" step="0.01" min="0"
                       value="{{ old('gasto_unit', $orden?->gasto_unit) }}">
            </div>
            <div class="oc-campo oc-form-full">
                <label class="oc-label" for="observaciones">Observaciones</label>
                <textarea class="oc-input" id="observaciones" name="observaciones" rows="3">{{ old('observaciones', $orden?->observaciones) }}</textarea>
            </div>
        </div>

        {{-- ══ Cierre ══ --}}
        <div class="ocd-cierre">
            <div>
                <div class="ocd-lleva-tit">Esta orden lleva</div>

                <div class="ocd-lleva-item {{ $productos ? 'lleno' : '' }}" id="ocr-item-productos">
                    <span class="ocd-lleva-punto"></span>
                    <span class="ocd-lleva-nombre">Productos</span>
                    <span class="ocd-lleva-det">
                        <span class="ocd-lleva-monto" id="ocr-prod-total">$ 0.00</span>
                        <span class="ocd-lleva-sub"><span id="ocr-prod-lineas">0</span> línea(s)</span>
                    </span>
                </div>

                <div class="ocd-lleva-item merch {{ $merchOrden ? 'lleno' : '' }}" id="ocr-item-merch">
                    <span class="ocd-lleva-punto"></span>
                    <span class="ocd-lleva-nombre">Merch</span>
                    <span class="ocd-lleva-det">
                        <span class="ocd-lleva-monto" id="ocr-merch-total">S/ 0.00</span>
                        <span class="ocd-lleva-sub"><span id="ocr-merch-lineas">0</span> artículo(s)</span>
                    </span>
                </div>
            </div>

            <div class="ocd-totales">
                <input type="hidden" name="total_usd" id="total_usd" value="{{ old('total_usd', $orden?->total_usd ?? 0) }}">
                <input type="hidden" name="total_soles" id="total_soles" value="{{ old('total_soles', $orden?->total_soles ?? 0) }}">

                <div class="ocd-tfila"><span>Total en dólares</span><strong id="ocr-total-usd">$ 0.00</strong></div>
                <div class="ocd-tfila"><span>Tipo de cambio</span><strong id="ocr-tc">—</strong></div>
                <div class="ocd-tgran">
                    <span>Total en soles</span>
                    <b id="ocr-total-soles">S/ 0.00</b>
                </div>
                <div class="ocd-nota oculta" id="ocr-nota-merch"></div>
            </div>
        </div>

    </div>{{-- /ocd-cuerpo --}}

    {{-- ══ Barra fija ══ --}}
    <div class="ocd-barra">
        <div class="ocd-barra-info">
            <div class="ocd-barra-lbl">Total de la orden</div>
            <div class="ocd-barra-total"><span id="ocr-barra-total">S/ 0.00</span><span class="ocd-barra-usd" id="ocr-barra-usd">$ 0.00</span></div>
            <div class="ocd-barra-det" id="ocr-barra-det">—</div>
        </div>

        <div class="ocd-barra-acciones">
            <a href="{{ route('admin.ordenes-compra.index') }}" class="ocd-cancelar">Cancelar</a>
            <button type="submit" class="ocd-guardar">Guardar cambios</button>
        </div>
    </div>

</div>

@push('scripts')
<script>
const $oc = (id) => document.getElementById(id);

// ── Líneas de producto ───────────────────────────────────────────────────
const cuerpo = $oc('itemsBody');
let indice = {{ count($productos) }};

function filaHtml(i) {
    return `<tr class="fila-item">
        <td><input type="text" class="oc-input mono" name="productos[${i}][codigo]"></td>
        <td><input type="text" class="oc-input" name="productos[${i}][descripcion]"></td>
        <td><input type="number" class="oc-input mono item-cantidad" name="productos[${i}][cantidad]" value="1" min="1"></td>
        <td><input type="number" class="oc-input mono item-precio" name="productos[${i}][precio]" value="0" step="0.0001" min="0"></td>
        <td class="num item-subtotal">$ 0.00</td>
        <td><button type="button" class="ocm-btn peligro btn-quitar">✕</button></td>
    </tr>`;
}

$oc('btnAgregarItem').addEventListener('click', () => {
    cuerpo.insertAdjacentHTML('beforeend', filaHtml(indice++));
    recalcular();
});

cuerpo.addEventListener('click', (e) => {
    if (e.target.classList.contains('btn-quitar')) {
        e.target.closest('tr').remove();
        recalcular();
    }
});

cuerpo.addEventListener('input', recalcular);
$oc('tc').addEventListener('input', recalcular);

// ── Líneas de merch ──────────────────────────────────────────────────────
const merchCuerpo = $oc('merchBody');
let indiceMerch = {{ count($merchOrden) }};

const OPCIONES_MERCH = `@foreach ($catalogoMerch as $articulo)<option value="{{ $articulo->id }}" data-precio="{{ $articulo->precio }}">{{ $articulo->nombre }}</option>@endforeach`;

function filaMerchHtml(i) {
    return `<tr class="fila-merch">
        <td><select class="oc-input merch-articulo" name="merch[${i}][merch_id]">${OPCIONES_MERCH}</select></td>
        <td><input type="number" class="oc-input mono merch-cantidad" name="merch[${i}][cantidad]" value="1" min="1" step="1"></td>
        <td><input type="number" class="oc-input mono merch-costo" name="merch[${i}][costo_unit]" value="0" min="0" step="0.01"></td>
        <td class="num merch-subtotal">S/ 0.00</td>
        <td><button type="button" class="ocm-btn peligro btn-quitar-merch">✕</button></td>
    </tr>`;
}

$oc('btnAgregarMerch')?.addEventListener('click', () => {
    merchCuerpo.insertAdjacentHTML('beforeend', filaMerchHtml(indiceMerch++));

    // La fila nueva arranca con el precio de catálogo del artículo elegido.
    const fila = merchCuerpo.lastElementChild;
    const select = fila.querySelector('.merch-articulo');
    fila.querySelector('.merch-costo').value = parseFloat(select.selectedOptions[0]?.dataset.precio || 0).toFixed(2);
    recalcular();
});

merchCuerpo?.addEventListener('click', (e) => {
    if (e.target.classList.contains('btn-quitar-merch')) {
        e.target.closest('tr').remove();
        recalcular();
    }
});

merchCuerpo?.addEventListener('change', (e) => {
    if (e.target.classList.contains('merch-articulo')) {
        const precio = e.target.selectedOptions[0]?.dataset.precio;
        e.target.closest('tr').querySelector('.merch-costo').value = parseFloat(precio || 0).toFixed(2);
    }
    recalcular();
});

merchCuerpo?.addEventListener('input', recalcular);

// ── Totales y membrete ───────────────────────────────────────────────────
function recalcular() {
    let totalUsd = 0;
    let lineas = 0;

    cuerpo.querySelectorAll('.fila-item').forEach((fila) => {
        const cantidad = parseFloat(fila.querySelector('.item-cantidad')?.value || 0);
        const precio   = parseFloat(fila.querySelector('.item-precio')?.value || 0);
        const linea    = cantidad * precio;

        totalUsd += linea;
        lineas++;
        fila.querySelector('.item-subtotal').textContent = '$ ' + linea.toFixed(2);
    });

    let totalMerch = 0;
    let lineasMerch = 0;

    merchCuerpo?.querySelectorAll('.fila-merch').forEach((fila) => {
        const cantidad = parseFloat(fila.querySelector('.merch-cantidad')?.value || 0);
        const costo    = parseFloat(fila.querySelector('.merch-costo')?.value || 0);
        const linea    = cantidad * costo;

        totalMerch += linea;
        lineasMerch++;
        fila.querySelector('.merch-subtotal').textContent = 'S/ ' + linea.toFixed(2);
    });

    const tc = parseFloat($oc('tc').value || 0);
    const totalSoles = totalUsd * tc;

    $oc('total_usd').value   = totalUsd.toFixed(2);
    $oc('total_soles').value = totalSoles.toFixed(2);

    $oc('ocr-prod-total').textContent  = '$ ' + totalUsd.toFixed(2);
    $oc('ocr-prod-lineas').textContent = lineas;
    $oc('ocr-item-productos').classList.toggle('lleno', lineas > 0);

    $oc('ocr-merch-total').textContent  = 'S/ ' + totalMerch.toFixed(2);
    $oc('ocr-merch-lineas').textContent = lineasMerch;
    $oc('ocr-item-merch').classList.toggle('lleno', lineasMerch > 0);

    $oc('ocr-total-usd').textContent   = '$ ' + totalUsd.toFixed(2);
    $oc('ocr-tc').textContent          = tc.toFixed(4);
    $oc('ocr-total-soles').textContent = 'S/ ' + totalSoles.toFixed(2);

    const nota = $oc('ocr-nota-merch');
    nota.classList.toggle('oculta', totalMerch <= 0);
    nota.textContent = 'Más S/ ' + totalMerch.toFixed(2) + ' de merch, que se registra aparte como egreso de promoción.';

    const barraSoles = totalSoles + totalMerch;
    $oc('ocr-barra-total').textContent = 'S/ ' + barraSoles.toFixed(2);
    // El merch se paga en soles: para verlo en dólares se devuelve con el mismo tipo de cambio.
    $oc('ocr-barra-usd').textContent = tc > 0 ? '$ ' + (barraSoles / tc).toFixed(2) : '$ —';
    $oc('ocr-barra-det').textContent = lineas + ' producto(s)' + (lineasMerch ? ' · ' + lineasMerch + ' merch' : '');
}

// El membrete refleja lo que se está escribiendo.
function pintarMembrete() {
    $oc('ocr-proveedor').textContent = $oc('proveedor').value.trim() || 'Sin proveedor';
    $oc('ocr-ruc').textContent       = $oc('ruc').value.trim() || '—';
    $oc('ocr-numero').textContent    = $oc('numero_orden').value.trim() || '—';
    $oc('ocr-cliente').textContent   = $oc('cliente_ref').value.trim() || 'Sin asignar';
    $oc('ocr-estado').textContent    = $oc('estado').value;

    const fecha = $oc('fecha').value;
    $oc('ocr-fecha').textContent = fecha
        ? new Date(fecha + 'T00:00:00').toLocaleDateString('es-PE', { day: '2-digit', month: 'long', year: 'numeric' })
        : '—';
}

['proveedor', 'ruc', 'numero_orden', 'cliente_ref', 'fecha', 'estado'].forEach((campo) => {
    $oc(campo).addEventListener('input', pintarMembrete);
    $oc(campo).addEventListener('change', pintarMembrete);
});

// ── Autocompletado del proveedor ─────────────────────────────────────────
$oc('proveedor_select')?.addEventListener('change', function () {
    const opcion = this.selectedOptions[0];
    if (! opcion?.dataset.razon) { return; }

    $oc('proveedor').value      = opcion.dataset.razon || '';
    $oc('ruc').value            = opcion.dataset.ruc || '';
    $oc('telefono').value       = opcion.dataset.telefono || '';
    $oc('correo').value         = opcion.dataset.correo || '';
    $oc('direccion').value      = opcion.dataset.direccion || '';
    $oc('distrito').value       = opcion.dataset.distrito || '';
    $oc('provincia').value      = opcion.dataset.provincia || '';
    $oc('departamento').value   = opcion.dataset.departamento || '';
    $oc('condicion_pago').value = opcion.dataset.condicion || '';

    pintarMembrete();
});

recalcular();
pintarMembrete();
</script>
@endpush
