@extends('layouts.admin')

@section('title', 'Proveedores')
@section('crumb', 'Directorio de proveedores')

@section('content')

<x-page-header titulo="Proveedores" subtitulo="Proveedores y condiciones comerciales">
    <x-slot:acciones>
        <button type="button" class="btn btn-primary" id="btnNuevoProveedor">
            <span class="btn-icon">＋</span><span class="btn-text">Nuevo Proveedor</span>
        </button>
    </x-slot:acciones>
</x-page-header>

<div class="stats-grid">
    <x-stat-card :valor="number_format($totalActivos)" etiqueta="Proveedores activos" />
    <x-stat-card :valor="number_format($conCredito)" etiqueta="Con línea de crédito" />
    <x-stat-card :valor="number_format($promedioCredito, 0) . ' días'" etiqueta="Crédito promedio" />
</div>

<div class="content-card">
    <div class="lista-header">
        <h3>Lista de Proveedores</h3>

        <form method="GET" class="filtros">
            <x-buscador :valor="$busqueda" placeholder="Buscar por razón social, RUC o contacto…" />
            <button type="submit" class="btn btn-primary btn-sm"><span class="btn-text">Filtrar</span></button>
            @if ($busqueda !== '')
                <a href="{{ route('admin.proveedores.index') }}" class="btn btn-secondary btn-sm">
                    <span class="btn-text">Limpiar</span>
                </a>
            @endif
        </form>
    </div>

    @if ($busqueda !== '')
        <p class="resultado-busqueda">
            {{ $proveedores->total() }} resultado(s) para <strong>"{{ $busqueda }}"</strong>
        </p>
    @endif

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>RUC</th>
                    <th>Razón social</th>
                    <th>Contacto</th>
                    <th>Productos</th>
                    <th>Crédito</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($proveedores as $proveedor)
                <tr>
                    <td><strong>{{ $proveedor->ruc }}</strong></td>
                    <td>{{ $proveedor->razon_social }}</td>
                    <td>{{ $proveedor->contacto ?: "—" }}<div style="font-size:12px;color:#666;">{{ $proveedor->telefono }}</div></td>
                    <td>{{ Str::limit($proveedor->productos_suministra, 40) ?: "—" }}</td>
                    <td>{{ $proveedor->dias_credito }} días<div style="font-size:12px;color:#666;">{{ $proveedor->condiciones_pago }}</div></td>
                    <td style="white-space:nowrap;">
                        <button type="button" class="btn btn-dark btn-sm btn-editar"
                                data-proveedor="{{ $proveedor->id }}"
                                data-ruc="{{ $proveedor->ruc }}"
                                data-razon-social="{{ $proveedor->razon_social }}"
                                data-contacto="{{ $proveedor->contacto }}"
                                data-telefono="{{ $proveedor->telefono }}"
                                data-email="{{ $proveedor->email }}"
                                data-distrito="{{ $proveedor->distrito }}"
                                data-provincia="{{ $proveedor->provincia }}"
                                data-departamento="{{ $proveedor->departamento }}"
                                data-condiciones-pago="{{ $proveedor->condiciones_pago }}"
                                data-dias-credito="{{ $proveedor->dias_credito }}"
                                data-fecha-cumpleanos="{{ $proveedor->fecha_cumpleanos instanceof \DateTimeInterface ? $proveedor->fecha_cumpleanos->format('Y-m-d') : $proveedor->fecha_cumpleanos }}"
                                data-direccion="{{ $proveedor->direccion }}"
                                data-productos-suministra="{{ $proveedor->productos_suministra }}"
                        >
                            Editar
                        </button>
                        <form method="POST" action="{{ route('admin.proveedores.destroy', $proveedor) }}"
                              style="display:inline;" data-confirmar="¿Desactivar a este proveedor?">
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

    {{ $proveedores->links() }}
</div>

{{-- ── Alta / edición ── --}}
<div class="modal-overlay" id="modalProveedor">
    <div class="modal-card">
        <div class="modal-header">
            <h3>
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 7h-9"/><path d="M14 17H5"/>
                    <circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/>
                </svg>
                <span id="tituloModal">Nuevo Proveedor</span>
            </h3>
            <button type="button" class="modal-close" data-cerrar="modalProveedor" aria-label="Cerrar">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>

        <div class="modal-body">
            <form method="POST" action="{{ route('admin.proveedores.store') }}" id="formProveedor">
                @csrf
                <input type="hidden" name="proveedor_id" id="proveedor_id" value="">

                <div class="form-grid">
                    <div class="form-group">
                        <label for="ruc">RUC <span>*</span></label>
                        <div style="display:flex;gap:8px;">
                            <input type="text" id="ruc" name="ruc" required maxlength="11" placeholder="20100047218" style="flex:1;">
                            <button type="button" class="btn btn-secondary" id="btnBuscarRuc" title="Buscar en SUNAT">Buscar</button>
                        </div>
                        <small id="rucEstado" style="display:block;margin-top:4px;color:#666;"></small>
                    </div>

                    <div class="form-group" style="grid-column:1/-1;">
                        <label for="razon_social">Razón Social <span>*</span></label>
                        <input type="text" id="razon_social" name="razon_social" required maxlength="255" placeholder="EMPRESA SAC">
                    </div>

                    <div class="form-group">
                        <label for="contacto">Persona de Contacto</label>
                        <input type="text" id="contacto" name="contacto" maxlength="150" placeholder="Juan Pérez">
                    </div>

                    <div class="form-group">
                        <label for="telefono">Teléfono</label>
                        <input type="text" id="telefono" name="telefono" maxlength="50" placeholder="014116500">
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" maxlength="150" placeholder="ventas@empresa.com">
                    </div>

                    <div class="form-group">
                        <label for="condiciones_pago">Condiciones de Pago <span>*</span></label>
                        <select id="condiciones_pago" name="condiciones_pago" required>
                            <option value="Contado">Contado</option>
                            <option value="Crédito">Crédito</option>
                        </select>
                    </div>

                    {{-- Solo tiene sentido cuando la condición es a crédito. --}}
                    <div class="form-group" id="campo_dias_credito" style="display:none;">
                        <label for="dias_credito">Días de Crédito</label>
                        <input type="number" id="dias_credito" name="dias_credito" min="0" value="30" placeholder="30">
                    </div>
                </div>

                <div class="form-group">
                    <label for="direccion">Dirección</label>
                    <textarea id="direccion" name="direccion" placeholder="Calle / Av. / Jr. del proveedor..." style="height:70px;"></textarea>
                </div>

                <div class="form-grid" style="margin-bottom:0;">
                    <div class="form-group">
                        <label for="distrito">Distrito <span class="lbl-opcional">(opcional)</span></label>
                        <input type="text" id="distrito" name="distrito" maxlength="100" placeholder="Ej: Miraflores">
                    </div>
                    <div class="form-group">
                        <label for="provincia">Provincia <span class="lbl-opcional">(opcional)</span></label>
                        <input type="text" id="provincia" name="provincia" maxlength="100" placeholder="Ej: Lima">
                    </div>
                    <div class="form-group">
                        <label for="departamento">Departamento <span class="lbl-opcional">(opcional)</span></label>
                        <input type="text" id="departamento" name="departamento" maxlength="100" placeholder="Ej: Lima">
                    </div>
                    <div class="form-group">
                        <label for="fecha_cumpleanos">Fecha Cumpleaños <span class="lbl-opcional">(opcional)</span></label>
                        <input type="date" id="fecha_cumpleanos" name="fecha_cumpleanos">
                    </div>
                </div>

                <div class="form-group">
                    <label for="productos_suministra">Productos que Suministra</label>
                    <textarea id="productos_suministra" name="productos_suministra"
                              placeholder="Ej: Aceites, lubricantes, filtros, maquinaria..."></textarea>
                </div>

                <div style="display:flex;gap:15px;margin-top:35px;">
                    <button type="submit" class="btn btn-success" style="flex:1;justify-content:center;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Guardar</span>
                    </button>
                    <button type="button" class="btn btn-secondary" data-cerrar="modalProveedor" style="flex:.5;justify-content:center;">
                        <span>Cancelar</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const URL_PROVEEDORES = '{{ url('admin/proveedores') }}';
const formProveedor   = document.getElementById('formProveedor');
const campoCondicion  = document.getElementById('condiciones_pago');
const campoDias       = document.getElementById('campo_dias_credito');

// Los días de crédito solo aparecen si la condición de pago es a crédito.
function alternarCredito() {
    campoDias.style.display = campoCondicion.value === 'Crédito' ? 'block' : 'none';
}

campoCondicion.addEventListener('change', alternarCredito);

// ── Alta ─────────────────────────────────────────────────────────────────
document.getElementById('btnNuevoProveedor').addEventListener('click', () => {
    formProveedor.reset();
    formProveedor.action = URL_PROVEEDORES;
    formProveedor.querySelector('[name="_method"]')?.remove();

    document.getElementById('tituloModal').textContent = 'Nuevo Proveedor';
    document.getElementById('proveedor_id').value      = '';

    alternarCredito();
    abrirModal('modalProveedor');
});

// ── Edición ──────────────────────────────────────────────────────────────
document.querySelectorAll('.btn-editar').forEach((boton) => {
    boton.addEventListener('click', () => {
        const d = boton.dataset;

        formProveedor.action = URL_PROVEEDORES + '/' + d.proveedor;
        if (!formProveedor.querySelector('[name="_method"]')) {
            const metodo = document.createElement('input');
            metodo.type = 'hidden';
            metodo.name = '_method';
            metodo.value = 'PUT';
            formProveedor.appendChild(metodo);
        }

        document.getElementById('tituloModal').textContent        = 'Editar Proveedor';
        document.getElementById('proveedor_id').value             = d.proveedor;
        document.getElementById('ruc').value                      = d.ruc || '';
        document.getElementById('razon_social').value             = d.razonSocial || '';
        document.getElementById('contacto').value                 = d.contacto || '';
        document.getElementById('telefono').value                 = d.telefono || '';
        document.getElementById('email').value                    = d.email || '';
        document.getElementById('condiciones_pago').value         = d.condicionesPago || 'Contado';
        document.getElementById('dias_credito').value             = d.diasCredito || 0;
        document.getElementById('direccion').value                = d.direccion || '';
        document.getElementById('distrito').value                 = d.distrito || '';
        document.getElementById('provincia').value                = d.provincia || '';
        document.getElementById('departamento').value             = d.departamento || '';
        document.getElementById('fecha_cumpleanos').value         = d.fechaCumpleanos || '';
        document.getElementById('productos_suministra').value     = d.productosSuministra || '';

        alternarCredito();
        abrirModal('modalProveedor');
    });
});

// ── Consulta de RUC (SUNAT) ─────────────────────────────────────────────
const rucEstado = document.getElementById('rucEstado');

document.getElementById('btnBuscarRuc').addEventListener('click', async () => {
    const ruc = document.getElementById('ruc').value.trim();

    if (!/^\d{11}$/.test(ruc)) {
        rucEstado.textContent = 'El RUC debe tener 11 dígitos';
        return;
    }

    rucEstado.textContent = 'Buscando...';

    try {
        const r = await fetch(`{{ url('admin/documentos/buscar/ruc') }}/${ruc}`, { headers: { Accept: 'application/json' } });
        const j = await r.json();

        if (!j.ok) {
            rucEstado.textContent = j.error || 'No se encontró el RUC';
            return;
        }

        document.getElementById('razon_social').value = j.datos.razon_social || '';
        document.getElementById('direccion').value     = j.datos.direccion || '';
        document.getElementById('distrito').value      = j.datos.distrito || '';
        document.getElementById('provincia').value     = j.datos.provincia || '';
        document.getElementById('departamento').value  = j.datos.departamento || '';
        rucEstado.textContent = j.origen === 'local' ? 'Datos de un registro existente' : 'Datos obtenidos de SUNAT';
    } catch (e) {
        rucEstado.textContent = 'Servicio de consulta no disponible';
    }
});
</script>
@endpush