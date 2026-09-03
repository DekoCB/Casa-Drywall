@extends('layouts.admin')

@section('title', 'Importar Productos')
@section('crumb', 'Inventario')

@section('content')
<x-page-header titulo="Importar Productos" subtitulo="Carga o actualiza el catálogo desde un archivo Excel">
    <x-slot:acciones>
        <a href="{{ route('admin.productos.index') }}" class="btn btn-secondary btn-sm">
            <span class="btn-text">← Volver</span>
        </a>
    </x-slot:acciones>
</x-page-header>

<div class="content-card">
    <h3 style="font-size:16px;margin-bottom:14px;">Formato esperado</h3>
    <p style="color:#666;margin-bottom:10px;">
        La primera fila debe ser el encabezado. No importa el orden de las columnas — el sistema
        reconoce estos nombres (probando también variantes parecidas):
    </p>

    <div class="table-container" style="margin-bottom:18px;">
        <table class="table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nombre <span style="color:#c0392b;">(obligatoria)</span></th>
                    <th>Costo</th>
                    <th>Precio de venta</th>
                    <th>Stock actual</th>
                    <th>Stock mínimo</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Código, Cod. Interno</td>
                    <td>Nombre, Producto, Descripción</td>
                    <td>Costo, Precio de compra</td>
                    <td>Precio de venta, Precio</td>
                    <td>Stock actual, Stock</td>
                    <td>Stock mínimo, Stock min</td>
                </tr>
            </tbody>
        </table>
    </div>

    <p style="color:#666;margin-bottom:8px;">
        Si el <strong>código</strong> de una fila ya existe en el sistema, se <strong>actualiza</strong>
        su precio y stock — no se duplica. Si no trae código, o el código es nuevo, se crea un producto.
    </p>
    <p style="color:#666;margin-bottom:18px;">
        Los productos nuevos quedan sin categoría ni marca — se pueden asignar después desde
        "Editar" en el listado.
    </p>

    <form method="POST" action="{{ route('admin.productos.importar') }}" enctype="multipart/form-data">
        @csrf

        <div class="form-group" style="margin-bottom:16px;max-width:320px;">
            <label for="almacen_id">Almacén de destino para el stock <span>*</span></label>
            <select id="almacen_id" name="almacen_id" required>
                @foreach ($almacenes as $almacen)
                    <option value="{{ $almacen->id }}">{{ $almacen->nombre }}</option>
                @endforeach
            </select>
        </div>

        <div class="drop-zone">
            <input type="file" name="archivo" accept=".xlsx" required>
        </div>

        <div class="header-btns" style="justify-content:flex-end;">
            <a href="{{ route('admin.productos.index') }}" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Importar</button>
        </div>
    </form>
</div>
@endsection
