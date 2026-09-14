@extends('layouts.admin')

@section('title', 'Pagos y Bancos')
@section('crumb', 'Configuración')

@push('styles')
    @vite(['resources/css/modules/reportes.css'])
@endpush

@section('content')

<x-page-header titulo="Pagos y Bancos" subtitulo="Cuentas bancarias para que te paguen tus clientes">
    <x-slot:acciones>
        <a href="{{ route('admin.configuracion.datos-empresa') }}" class="btn btn-secondary btn-sm"><span class="btn-text">← Datos de la Empresa</span></a>
        <button type="button" class="btn btn-primary btn-sm" data-modal="modalCuenta"><span class="btn-text">＋ Agregar cuenta</span></button>
    </x-slot:acciones>
</x-page-header>

<div class="content-card">
    <p style="color:var(--ink-3);font-size:13px;margin-bottom:16px;">
        Estas cuentas aparecen al pie de las Cotizaciones y comprobantes de venta, para que el cliente pueda depositar directamente.
    </p>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr><th>Banco</th><th>Moneda</th><th>Titular</th><th>Cuenta</th><th>CCI</th><th>Acciones</th></tr>
            </thead>
            <tbody>
            @forelse ($cuentas as $cuenta)
                <tr>
                    <td>
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:6px;font-size:9px;font-weight:700;color:#fff;background:{{ $cuenta->color }};margin-right:8px;">{{ $cuenta->abrev }}</span>
                        {{ $cuenta->banco }}
                    </td>
                    <td>{{ $cuenta->moneda }}</td>
                    <td>{{ $cuenta->titular }}</td>
                    <td>{{ $cuenta->cuenta }}</td>
                    <td>{{ $cuenta->cci }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.configuracion.pagos.destroy', $cuenta) }}"
                              onsubmit="return confirm('¿Eliminar la cuenta de {{ $cuenta->banco }}?');" style="display:inline;">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-secondary btn-sm">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--ink-3);">No hay cuentas bancarias configuradas.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<x-modal id="modalCuenta" titulo="Nueva cuenta bancaria">
    <form method="POST" action="{{ route('admin.configuracion.pagos.store') }}">
        @csrf
        <div class="form-grid">
            <div class="form-group">
                <label for="cbBanco">Banco <span>*</span></label>
                <input type="text" name="banco" id="cbBanco" maxlength="60" required placeholder="Ej: BBVA">
            </div>
            <div class="form-group">
                <label for="cbAbrev">Sigla (para el círculo de color) <span>*</span></label>
                <input type="text" name="abrev" id="cbAbrev" maxlength="10" required placeholder="Ej: BBVA">
            </div>
            <div class="form-group">
                <label for="cbMoneda">Moneda <span>*</span></label>
                <select name="moneda" id="cbMoneda" required>
                    <option value="S/ Soles">S/ Soles</option>
                    <option value="US$ Dólares">US$ Dólares</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label for="cbTitular">Titular <span>*</span></label>
            <input type="text" name="titular" id="cbTitular" maxlength="150" required placeholder="Ej: BBVA - CASA DRYWALL E.I.R.L.">
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label for="cbCuenta">N° de cuenta <span>*</span></label>
                <input type="text" name="cuenta" id="cbCuenta" maxlength="60" required>
            </div>
            <div class="form-group">
                <label for="cbCci">CCI <span>*</span></label>
                <input type="text" name="cci" id="cbCci" maxlength="60" required>
            </div>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label for="cbColor">Color del círculo</label>
                <input type="color" name="color" id="cbColor" value="#0d47a1" style="height:40px;padding:4px;">
            </div>
            <div class="form-group">
                <label for="cbBg">Color de fondo de la tarjeta</label>
                <input type="color" name="bg" id="cbBg" value="#e8f0fe" style="height:40px;padding:4px;">
            </div>
        </div>
        <div class="header-btns" style="justify-content:flex-end;margin-top:16px;">
            <button type="button" class="btn btn-secondary" data-cerrar="modalCuenta">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-modal>

@endsection
