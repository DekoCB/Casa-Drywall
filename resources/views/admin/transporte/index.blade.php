@extends('layouts.admin')

@section('title', 'Transporte')
@section('crumb', 'Logística')

@push('styles')
    @vite(['resources/css/modules/transporte.css'])
@endpush

@section('content')

<x-page-header titulo="Transporte" subtitulo="Cuánto cuesta llevar la carga a cada destino">
    <x-slot:acciones>
        <button type="button" class="btn btn-secondary btn-sm" data-modal="modalEmpresa">
            <span class="btn-icon">＋</span><span class="btn-text">Nueva Empresa</span>
        </button>
        <button type="button" class="btn btn-primary" data-modal="modalTarifa">
            <span class="btn-icon">＋</span><span class="btn-text">Nueva Tarifa</span>
        </button>
    </x-slot:acciones>
</x-page-header>

<div class="tr-wrap">

    <div class="tr-resumen">
        <span class="tr-pill"><b>{{ number_format($totalEmpresas) }}</b> empresas activas</span>
        <span class="tr-pill"><b>{{ number_format($totalDestinos) }}</b> destinos cubiertos</span>
        <span class="tr-pill"><b>{{ number_format($tarifas->count()) }}</b> tarifas registradas</span>
        @if ($tarifaMax > 0)
            <span class="tr-pill">desde <b>S/ {{ number_format($tarifaMin, 2) }}</b> hasta <b>S/ {{ number_format($tarifaMax, 2) }}</b> por balde</span>
        @endif
    </div>

    {{-- ══ La ruta: destinos ordenados por tarifa ══ --}}
    <div class="tr-seccion">
        <h3>Destinos y tarifas</h3>
        <p>de la tarifa más baja a la más alta</p>
        <span class="tr-rule"></span>
    </div>

    @if ($destinos->isEmpty())
        <div class="tr-vacio" style="margin-bottom:30px;">
            Todavía no hay tarifas. Agrega la primera con el botón <strong>Nueva Tarifa</strong>.
        </div>
    @else
    <div class="tr-ruta">
        @foreach ($destinos as $destino)
            @foreach ($destino['tarifas'] as $tarifa)
                @php
                    // Qué tan cara es esta parada dentro de todo el recorrido.
                    $rango = $tarifaMax - $tarifaMin;
                    $nivel = $rango > 0 ? round((((float) $tarifa->precio_baldes - $tarifaMin) / $rango) * 100) : 100;
                @endphp
                <div class="tr-parada">
                    <div class="tr-tarjeta">
                        <div>
                            <div class="tr-destino">{{ $destino['nombre'] }}</div>
                            <span class="tr-empresa-chip">{{ $tarifa->empresa_nombre ?: 'Sin empresa asignada' }}</span>
                            <div class="tr-barra"><i style="width:{{ max($nivel, 6) }}%"></i></div>
                        </div>

                        <div class="tr-precios">
                            <div class="tr-precio">
                                <div class="tr-precio-lbl">Balde</div>
                                <div class="tr-precio-val">S/ {{ number_format($tarifa->precio_baldes, 2) }}</div>
                            </div>
                            <div class="tr-precio">
                                <div class="tr-precio-lbl">Caja</div>
                                <div class="tr-precio-val">S/ {{ number_format($tarifa->precio_cajas, 2) }}</div>
                            </div>
                            <div class="tr-precio cilindro">
                                <div class="tr-precio-lbl">Cilindro</div>
                                <div class="tr-precio-val">S/ {{ number_format($tarifa->precio_cilindros, 2) }}</div>
                            </div>
                        </div>

                        <div class="tr-acciones">
                            <button type="button" class="btn btn-dark btn-sm"
                                    data-modal="modalTarifa"
                                    data-campo-tarifa_id="{{ $tarifa->id }}"
                                    data-campo-empresa_id="{{ $tarifa->empresa_id }}"
                                    data-campo-destino="{{ $tarifa->destino }}"
                                    data-campo-precio_baldes="{{ $tarifa->precio_baldes }}"
                                    data-campo-precio_cajas="{{ $tarifa->precio_cajas }}"
                                    data-campo-precio_cilindros="{{ $tarifa->precio_cilindros }}">
                                Editar
                            </button>
                            <form method="POST" action="{{ route('admin.transporte.tarifas.destroy', $tarifa) }}"
                                  style="display:inline;" data-confirmar="¿Desactivar la tarifa de {{ $tarifa->destino }}?">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">Quitar</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        @endforeach
    </div>
    @endif

    {{-- ══ Empresas ══ --}}
    <div class="tr-seccion">
        <h3>Empresas de transporte</h3>
        <p>quiénes hacen los envíos</p>
        <span class="tr-rule"></span>
    </div>

    <div class="tr-empresas">
        @forelse ($empresas as $empresa)
            @php $nTarifas = $tarifas->where('empresa_id', $empresa->id)->count(); @endphp
            <div class="tr-emp">
                <div class="tr-emp-inicial">{{ Str::upper(Str::substr(trim($empresa->nombre), 0, 1)) }}</div>

                <div class="tr-emp-info">
                    <div class="tr-emp-nombre">{{ $empresa->nombre }}</div>
                    <div class="tr-emp-meta {{ $nTarifas === 0 ? 'sin' : '' }}">
                        {{ $nTarifas === 0 ? 'Aún sin tarifas cargadas' : $nTarifas.' destino'.($nTarifas === 1 ? '' : 's').' con tarifa' }}
                    </div>
                </div>

                <div class="tr-emp-acciones">
                    <button type="button" class="btn btn-dark btn-sm"
                            data-modal="modalEmpresa"
                            data-campo-empresa_id="{{ $empresa->id }}"
                            data-campo-nombre="{{ $empresa->nombre }}">
                        Editar
                    </button>
                    <form method="POST" action="{{ route('admin.transporte.empresas.destroy', $empresa) }}"
                          data-confirmar="¿Desactivar {{ $empresa->nombre }}?">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm" style="width:100%;">Desactivar</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="tr-vacio">Sin empresas registradas.</div>
        @endforelse
    </div>

</div>

<x-modal id="modalEmpresa" titulo="Empresa de transporte">
    <form method="POST" action="{{ route('admin.transporte.empresas.store') }}" id="formEmpresa"
          data-alta-url="{{ route('admin.transporte.empresas.store') }}">
        @csrf
        <input type="hidden" name="empresa_id" value="">

        <div class="form-group">
            <label for="nombre">Nombre de la empresa <span>*</span></label>
            <input type="text" id="nombre" name="nombre" required maxlength="200">
        </div>

        {{-- Una empresa sin destinos no sirve: se ofrece cargar el primero aquí mismo. --}}
        <div id="bloqueTarifaEmpresa">
            <div class="tr-modal-sep">
                <span>Primera tarifa</span>
                <small>opcional · puedes agregar más después</small>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="e_destino">Destino</label>
                    <input type="text" id="e_destino" name="destino" maxlength="100" placeholder="Ej: LA MERCED">
                </div>
                <div class="form-group">
                    <label for="e_precio_baldes">Precio por balde</label>
                    <input type="number" id="e_precio_baldes" name="precio_baldes" step="0.01" min="0" value="0">
                </div>
                <div class="form-group">
                    <label for="e_precio_cajas">Precio por caja</label>
                    <input type="number" id="e_precio_cajas" name="precio_cajas" step="0.01" min="0" value="0">
                </div>
                <div class="form-group">
                    <label for="e_precio_cilindros">Precio por cilindro</label>
                    <input type="number" id="e_precio_cilindros" name="precio_cilindros" step="0.01" min="0" value="0">
                </div>
            </div>
        </div>

        <div class="header-btns" style="justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalEmpresa">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-modal>

<x-modal id="modalTarifa" titulo="Tarifa de transporte" :oscuro="true">
    <form method="POST" action="{{ route('admin.transporte.tarifas.store') }}" id="formTarifa"
          data-alta-url="{{ route('admin.transporte.tarifas.store') }}">
        @csrf
        <input type="hidden" name="tarifa_id" value="">

        <div class="form-grid">
            <div class="form-group">
                <label for="t_empresa_id">Empresa</label>
                <select id="t_empresa_id" name="empresa_id">
                    <option value="">—</option>
                    @foreach ($empresas as $empresa)
                        <option value="{{ $empresa->id }}">{{ $empresa->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="destino">Destino <span>*</span></label>
                <input type="text" id="destino" name="destino" required maxlength="100">
            </div>
            <div class="form-group">
                <label for="precio_baldes">Precio por balde <span>*</span></label>
                <input type="number" id="precio_baldes" name="precio_baldes" step="0.01" min="0" required value="0">
            </div>
            <div class="form-group">
                <label for="precio_cajas">Precio por caja <span>*</span></label>
                <input type="number" id="precio_cajas" name="precio_cajas" step="0.01" min="0" required value="0">
            </div>
            <div class="form-group">
                <label for="precio_cilindros">Precio por cilindro <span>*</span></label>
                <input type="number" id="precio_cilindros" name="precio_cilindros" step="0.01" min="0" required value="0">
            </div>
        </div>

        <div class="header-btns" style="justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalTarifa">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-modal>
@endsection

@push('scripts')
<script>
// Los modales sirven para alta y edición: con ID presente el envío pasa a PUT.
function convertirEnPut(form, campoId, base) {
    const id = form.querySelector('[name="' + campoId + '"]').value;
    if (!id) return;

    form.action = base + '/' + id;
    if (!form.querySelector('[name="_method"]')) {
        const metodo = document.createElement('input');
        metodo.type = 'hidden';
        metodo.name = '_method';
        metodo.value = 'PUT';
        form.appendChild(metodo);
    }
}

document.getElementById('formEmpresa').addEventListener('submit', function () {
    convertirEnPut(this, 'empresa_id', '{{ url('admin/transporte/empresas') }}');
});

// Un alta parte siempre en blanco: si no se abre desde un "Editar", el
// formulario se limpia para no arrastrar el registro anterior.
function prepararModal(evento, selector, formId, campoId) {
    const disparador = evento.target.closest(selector);
    if (! disparador) { return null; }

    const id = disparador.getAttribute('data-campo-' + campoId) || '';
    const form = document.getElementById(formId);

    if (! id) {
        form.reset();
        form.querySelector('[name="' + campoId + '"]').value = '';
        form.querySelector('[name="_method"]')?.remove();
        form.action = form.dataset.altaUrl;
    }

    return id;
}

document.addEventListener('click', (evento) => {
    const id = prepararModal(evento, '[data-modal="modalEmpresa"]', 'formEmpresa', 'empresa_id');
    if (id === null) { return; }

    // La primera tarifa solo se pide al crear; al editar se oculta y se
    // desactiva para que sus campos no viajen en el formulario.
    const bloque = document.getElementById('bloqueTarifaEmpresa');
    const editando = Boolean(id);

    bloque.style.display = editando ? 'none' : '';
    bloque.querySelectorAll('input').forEach((campo) => { campo.disabled = editando; });
});

document.addEventListener('click', (evento) => {
    prepararModal(evento, '[data-modal="modalTarifa"]', 'formTarifa', 'tarifa_id');
});

document.getElementById('formTarifa').addEventListener('submit', function () {
    convertirEnPut(this, 'tarifa_id', '{{ url('admin/transporte/tarifas') }}');
});

</script>
@endpush
