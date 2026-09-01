@extends('layouts.admin')

@section('title', 'Importar Cobranzas')
@section('crumb', 'Cartera por cobrar')

@section('content')
<x-page-header titulo="Importar Cobranzas" subtitulo="Carga masiva de documentos por cobrar desde un archivo Excel">
    <x-slot:acciones>
        <a href="{{ route('admin.cobranzas.index') }}" class="btn btn-secondary btn-sm">
            <span class="btn-text">← Volver</span>
        </a>
    </x-slot:acciones>
</x-page-header>

<div class="content-card">
    <h3 style="font-size:16px;margin-bottom:14px;">Formato esperado</h3>
    <p style="color:#666;margin-bottom:18px;">
        La primera fila se trata como encabezado. Las columnas se leen en este orden:
    </p>

    <div class="table-container" style="margin-bottom:26px;">
        <table class="table">
            <thead>
                <tr><th>A</th><th>B</th><th>C</th><th>D</th><th>E</th><th>F</th><th>G</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>Tipo</td><td>Número</td><td>Emisión</td><td>Vencimiento</td>
                    <td>Cliente</td><td>Monto total</td><td>Monto pagado</td>
                </tr>
            </tbody>
        </table>
    </div>

    <p style="color:#666;margin-bottom:18px;">
        Los documentos cuyo par <strong>tipo + número</strong> ya exista se omiten para evitar duplicados.
    </p>

    <form method="POST" action="{{ route('admin.cobranzas.importar') }}" enctype="multipart/form-data">
        @csrf
        <div class="drop-zone">
            <input type="file" name="archivo" accept=".xlsx" required>
        </div>

        <div class="header-btns" style="justify-content:flex-end;">
            <a href="{{ route('admin.cobranzas.index') }}" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Importar</button>
        </div>
    </form>
</div>
@endsection
