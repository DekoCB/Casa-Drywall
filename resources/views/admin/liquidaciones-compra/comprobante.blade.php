@php
    $items = collect($liquidacion->productos ?? []);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liquidación {{ $liquidacion->numero }} — {{ config('rentaltech.empresa.razon_social') }}</title>
    <style>
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color:#000; background:#EFEEE8; padding:24px 14px; }
        .hoja { width: 700px; max-width: 100%; margin:0 auto; background:#fff; padding:30px 34px; box-shadow:0 8px 30px rgba(0,0,0,.10); }

        .barra { width: 700px; max-width:100%; margin:0 auto 12px; display:flex; gap:10px; }
        .barra button, .barra a {
            padding:8px 16px; border:1px solid #DCDAD2; border-radius:6px; background:#fff;
            font-family:inherit; font-size:12px; cursor:pointer; text-decoration:none; color:#14161B;
        }
        .barra .primario { border-color:#3d9b8c; background:#3d9b8c; color:#fff; margin-left:auto; }

        .aviso-interno {
            border:1.5px solid #A8231F; background:#FBE7E6; color:#A8231F; padding:12px 16px;
            border-radius:6px; font-size:11.5px; font-weight:bold; margin-bottom:18px; text-align:center;
        }

        .cab { display:table; width:100%; margin-bottom:18px; }
        .cab-izq, .cab-der { display:table-cell; vertical-align:top; }
        .cab-der { text-align:right; }
        .cab h1 { font-size:16px; }
        .cab p { font-size:10px; color:#555; margin-top:2px; }
        .cab .num { font-size:15px; font-weight:bold; }
        .cab .fecha { font-size:10.5px; color:#555; }

        .datos { width:100%; margin:18px 0; border-collapse:collapse; }
        .datos td { padding:4px 0; font-size:11px; vertical-align:top; }
        .datos .k { font-weight:bold; width:150px; }

        .items { width:100%; border-collapse:collapse; margin:18px 0; }
        .items th { border-bottom:1.5px solid #000; padding:6px; font-size:10px; text-align:left; }
        .items td { padding:6px; font-size:11px; border-bottom:1px dotted #999; }

        .total { text-align:right; font-size:14px; font-weight:bold; padding:10px 6px; }

        .firma { margin-top:60px; display:table; width:100%; }
        .firma-cel { display:table-cell; width:50%; text-align:center; }
        .firma-linea { border-top:1px solid #000; width:200px; margin:0 auto 6px; }

        @media print {
            body { background:#fff; padding:0; }
            .hoja { box-shadow:none; padding:0; width:auto; }
            .barra { display:none; }
        }
    </style>
</head>
<body>

<div class="barra">
    <a href="{{ route('admin.liquidaciones-compra.index') }}">← Volver</a>
    <button type="button" class="primario" onclick="window.print()">🖨 Imprimir</button>
</div>

<div class="hoja">
    <div class="aviso-interno">
        ⚠ DOCUMENTO INTERNO — NO ES UN COMPROBANTE ELECTRÓNICO SUNAT.<br>
        Solo para control contable propio de {{ config('rentaltech.empresa.razon_social') }}.
    </div>

    <div class="cab">
        <div class="cab-izq">
            <h1>{{ config('rentaltech.empresa.razon_social') }}</h1>
            <p>Liquidación de Compra (registro interno)</p>
        </div>
        <div class="cab-der">
            <div class="num">{{ $liquidacion->numero }}</div>
            <div class="fecha">{{ $liquidacion->fecha?->format('d/m/Y') }}</div>
        </div>
    </div>

    <table class="datos">
        <tr><td class="k">Vendedor:</td><td>{{ $liquidacion->vendedor_nombre }}</td></tr>
        <tr><td class="k">Documento (DNI):</td><td>{{ $liquidacion->vendedor_documento ?: '—' }}</td></tr>
        @if ($liquidacion->proveedor)
            <tr><td class="k">Ficha de proveedor:</td><td>{{ $liquidacion->proveedor->razon_social }}</td></tr>
        @endif
        <tr><td class="k">Registrado por:</td><td>{{ $liquidacion->usuario?->username ?? '—' }}</td></tr>
    </table>

    <table class="items">
        <thead><tr><th>#</th><th>Descripción</th></tr></thead>
        <tbody>
        @forelse ($items as $i => $item)
            <tr><td>{{ $i + 1 }}</td><td>{{ $item['descripcion'] ?? '' }}</td></tr>
        @empty
            <tr><td colspan="2">Sin ítems detallados.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="total">Total: S/ {{ number_format((float) $liquidacion->total, 2) }}</div>

    @if ($liquidacion->observaciones)
        <p style="font-size:10.5px;color:#555;margin-top:10px;">Observaciones: {{ $liquidacion->observaciones }}</p>
    @endif

    <div class="firma">
        <div class="firma-cel"><div class="firma-linea"></div>Firma del vendedor</div>
        <div class="firma-cel"><div class="firma-linea"></div>Firma / recibí conforme</div>
    </div>
</div>

</body>
</html>
