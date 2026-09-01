@extends('layouts.admin')

@section('title', 'Personal')
@section('crumb', 'Recursos humanos')

@section('content')

<x-page-header titulo="Personal" subtitulo="Colaboradores, cargos y accesos al sistema">
    <x-slot:acciones>
        <button type="button" class="btn btn-primary" data-modal="modalEmpleado">
            <span class="btn-icon">＋</span><span class="btn-text">Nuevo Colaborador</span>
        </button>
    </x-slot:acciones>
</x-page-header>

<div class="stats-grid">
    <x-stat-card :valor="number_format($totalActivos)" etiqueta="Colaboradores activos" />
    <x-stat-card :valor="'S/ ' . number_format($totalPlanilla, 2)" etiqueta="Planilla mensual" />
    <x-stat-card :valor="number_format($totalAreas)" etiqueta="Áreas" />
</div>

<div class="content-card">
    <div class="lista-header">
        <h3>Lista de Personal</h3>

        <form method="GET" class="filtros">
            <x-buscador :valor="$busqueda" placeholder="Buscar por nombre, DNI o cargo…" />
            <button type="submit" class="btn btn-primary btn-sm"><span class="btn-text">Filtrar</span></button>
            @if ($busqueda !== '')
                <a href="{{ route('admin.personal.index') }}" class="btn btn-secondary btn-sm">
                    <span class="btn-text">Limpiar</span>
                </a>
            @endif
        </form>
    </div>

    @if ($busqueda !== '')
        <p class="resultado-busqueda">
            {{ $personal->total() }} resultado(s) para <strong>"{{ $busqueda }}"</strong>
        </p>
    @endif

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>DNI</th>
                    <th>Nombre</th>
                    <th>Cargo</th>
                    <th>Ingreso</th>
                    <th>Sueldo</th>
                    <th>Acceso</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($personal as $empleado)
                <tr>
                    <td><strong>{{ $empleado->dni }}</strong></td>
                    <td>{{ $empleado->nombres }} {{ $empleado->apellidos }}</td>
                    <td>{{ $empleado->cargo }}<div style="font-size:12px;color:#666;">{{ $empleado->area }}</div></td>
                    <td>{{ $empleado->fecha_ingreso?->format("d/m/Y") ?: "—" }}</td>
                    <td>S/ {{ number_format($empleado->sueldo, 2) }}<div style="font-size:12px;color:#666;">{{ $empleado->tipo_contrato }}</div></td>
                    <td>{{ $empleado->acceso_username ? $empleado->acceso_username . " (" . $empleado->acceso_rol . ")" : "—" }}</td>
                    <td style="white-space:nowrap;">
                        <button type="button" class="btn btn-dark btn-sm"
                                data-modal="modalEmpleado"
                                data-campo-registro_id="{{ $empleado->id }}"
                                data-campo-dni="{{ $empleado->dni }}"
                                data-campo-nombres="{{ $empleado->nombres }}"
                                data-campo-apellidos="{{ $empleado->apellidos }}"
                                data-campo-cargo="{{ $empleado->cargo }}"
                                data-campo-area="{{ $empleado->area }}"
                                data-campo-telefono="{{ $empleado->telefono }}"
                                data-campo-email="{{ $empleado->email }}"
                                data-campo-fecha_nacimiento="{{ $empleado->fecha_nacimiento instanceof \DateTimeInterface ? $empleado->fecha_nacimiento->format('Y-m-d') : $empleado->fecha_nacimiento }}"
                                data-campo-fecha_ingreso="{{ $empleado->fecha_ingreso instanceof \DateTimeInterface ? $empleado->fecha_ingreso->format('Y-m-d') : $empleado->fecha_ingreso }}"
                                data-campo-sueldo="{{ $empleado->sueldo }}"
                                data-campo-acceso_username="{{ $empleado->acceso_username }}"
                                data-campo-tipo_contrato="{{ $empleado->tipo_contrato }}"
                                data-campo-acceso_rol="{{ $empleado->acceso_rol }}"
                                data-campo-direccion="{{ $empleado->direccion }}"
                        >
                            Editar
                        </button>
                        <form method="POST" action="{{ route('admin.personal.destroy', $empleado) }}"
                              style="display:inline;" data-confirmar="¿Dar de baja a este colaborador?">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center;padding:40px;color:#666;">Sin registros</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $personal->links() }}
</div>

<x-modal id="modalEmpleado" titulo="Nuevo Colaborador">
    <form method="POST" action="{{ route('admin.personal.store') }}" id="formEmpleado">
        @csrf
        <input type="hidden" name="registro_id" value="">

        <div class="form-grid">
            <div class="form-group">
                <label for="dni">DNI <span>*</span></label>
                <input type="text" id="dni" name="dni" required maxlength="15">
            </div>
            <div class="form-group">
                <label for="nombres">Nombres <span>*</span></label>
                <input type="text" id="nombres" name="nombres" required maxlength="150">
            </div>
            <div class="form-group">
                <label for="apellidos">Apellidos <span>*</span></label>
                <input type="text" id="apellidos" name="apellidos" required maxlength="150">
            </div>
            <div class="form-group">
                <label for="cargo">Cargo <span>*</span></label>
                <input type="text" id="cargo" name="cargo" required maxlength="100">
            </div>
            <div class="form-group">
                <label for="area">Área</label>
                <input type="text" id="area" name="area" maxlength="100">
            </div>
            <div class="form-group">
                <label for="telefono">Teléfono</label>
                <input type="text" id="telefono" name="telefono" maxlength="30">
            </div>
            <div class="form-group">
                <label for="email">Correo</label>
                <input type="email" id="email" name="email" maxlength="150">
            </div>
            <div class="form-group">
                <label for="fecha_nacimiento">Fecha de nacimiento</label>
                <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" >
            </div>
            <div class="form-group">
                <label for="fecha_ingreso">Fecha de ingreso</label>
                <input type="date" id="fecha_ingreso" name="fecha_ingreso" >
            </div>
            <div class="form-group">
                <label for="sueldo">Sueldo</label>
                <input type="number" id="sueldo" name="sueldo" step="0.01" min="0">
            </div>
            <div class="form-group">
                <label for="acceso_username">Usuario del sistema</label>
                <input type="text" id="acceso_username" name="acceso_username" maxlength="100">
            </div>
            <div class="form-group">
                <label for="acceso_password">Contraseña de acceso</label>
                <input type="password" id="acceso_password" name="acceso_password" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label for="tipo_contrato">Tipo de contrato <span>*</span></label>
                <select id="tipo_contrato" name="tipo_contrato" required>
                    <option value="Planilla">Planilla</option>
                    <option value="Recibo por honorarios">Recibo por honorarios</option>
                    <option value="Locación de servicios">Locación de servicios</option>
                </select>
            </div>
            <div class="form-group">
                <label for="acceso_rol">Rol de acceso</label>
                <select id="acceso_rol" name="acceso_rol">
                    <option value="">Sin acceso</option>
                    <option value="secretaria">Secretaria</option>
                    <option value="contador">Contador</option>
                    <option value="admin">Administrador</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="direccion">Dirección</label>
            <textarea id="direccion" name="direccion" rows="2"></textarea>
        </div>


        <div class="header-btns" style="justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalEmpleado">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-modal>
@endsection

@push('scripts')
<script>
// El mismo modal sirve para alta y edición: si trae ID, se envía como PUT.
document.getElementById('formEmpleado').addEventListener('submit', function () {
    const id = this.querySelector('[name="registro_id"]').value;
    if (!id) return;

    this.action = '{{ url('admin/personal') }}/' + id;
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
