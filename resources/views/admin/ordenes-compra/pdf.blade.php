<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Órdenes de Compra</title>
    <style>
        {{-- dompdf: solo CSS básico, nada de flex/grid — igual que admin.ventas.comprobante,
             pero con display:table/table-cell vía <table> real en vez de divs. --}}
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color:#000; }

        .hoja { page-break-after: always; }
        .hoja:last-child { page-break-after: auto; }

        .cab { width:100%; margin-bottom:16px; }
        .cab td { vertical-align:top; }
        .cab-izq { width:62%; }
        .cab-marca { width:100%; }
        .cab-marca td { vertical-align:middle; }
        .cab-logo-cel { width:64px; }
        .cab-logo-cel img { width:54px; height:54px; }
        .cab-emp-cel { padding-left:12px; }
        .cab-emp-cel b { font-size:12px; }
        .cab-emp-cel p { font-size:8.7px; color:#333; line-height:1.55; margin-top:1px; }
        .cab-der { width:38%; padding-left:16px; text-align:right; }
        .caja-doc {
            display:inline-block; border:1px solid #000; padding:12px 16px;
            text-align:center; min-width:170px;
        }
        .caja-doc .tipo { font-size:12px; letter-spacing:.03em; }
        .caja-doc .num { font-size:17px; font-weight:bold; margin-top:8px; letter-spacing:.01em; }

        .datos { width:100%; margin-bottom:14px; }
        .datos td { padding:1.5px 0; font-size:9.5px; vertical-align:top; }
        .datos .k { font-weight:bold; white-space:nowrap; width:130px; }
        .datos-der { text-align:right; white-space:nowrap; }
        .datos-der .k { font-weight:bold; }

        table.items { width:100%; border-collapse:collapse; margin-bottom:4px; }
        table.items th {
            border-bottom:1.5px solid #000; padding:5px 6px; font-size:9px;
            font-weight:bold; text-align:left; white-space:nowrap;
        }
        table.items td { padding:5px 6px; font-size:9.5px; border-bottom:1px dotted #999; }
        table.items tbody tr:last-child td { border-bottom:1.5px solid #000; }
        .r { text-align:right; }
        .c { text-align:center; }

        .seccion-tit { font-size:10px; font-weight:bold; margin:12px 0 4px; }

        .totales { width:100%; margin-top:8px; }
        .totales td { padding:3px 0; font-size:9.5px; }
        .totales .lbl { text-align:right; padding-right:14px; color:#333; }
        .totales .val { text-align:right; width:130px; }
        .totales .gran .lbl, .totales .gran .val { font-size:13px; font-weight:bold; color:#000; padding-top:6px; }

        .obs { margin-top:16px; font-size:9px; line-height:1.5; color:#333; }
        .obs .k { font-weight:bold; display:block; margin-bottom:2px; }

        .pie { margin-top:24px; font-size:8px; color:#777; text-align:center; }
    </style>
</head>
<body>

@foreach ($ordenes as $orden)
    @php
        $productos = $orden->productos ?? [];
        $lineasMerch = $orden->merch ?? [];
        $precioLinea = fn (array $p) => (float) ($p['precio_unit_usd'] ?? $p['precio'] ?? 0);
        $totalMerch = collect($lineasMerch)->sum(fn ($l) => ($l['cantidad'] ?? 0) * ($l['costo_unit'] ?? 0));
        $nombresMerch = \App\Models\Merch::whereIn('id', array_column($lineasMerch, 'merch_id'))->pluck('nombre', 'id');

        // El catálogo Kendall (en dólares) ya no existe: las órdenes nuevas se
        // guardan con tc=1, así que solo las órdenes históricas reales van en USD.
        $esSolesNativo = abs((float) $orden->tc - 1.0) < 0.0001;
        $simbolo = $esSolesNativo ? 'S/' : '$';

        $direccionOrden = trim(implode(', ', array_filter([$orden->direccion, $orden->distrito, $orden->provincia])));

        // Desglose de IGV sobre el total en soles, al mismo % que usa el resto
        // del sistema para comprobantes (config('rentaltech.igv')) — la orden
        // no guarda un monto de IGV propio, se calcula igual que en una Boleta/
        // Nota de Venta a partir del total.
        $igvPct = (float) config('rentaltech.igv', 0.18);
        $totalPagar = (float) $orden->total_soles;
        $opGravadas = round($totalPagar / (1 + $igvPct), 2);
        $igvMonto = round($totalPagar - $opGravadas, 2);
    @endphp

    <div class="hoja">
        {{-- ══ Cabecera: logo + empresa a la izquierda, caja de documento a la derecha ══ --}}
        <table class="cab">
            <tr>
                <td class="cab-izq">
                    <table class="cab-marca">
                        <tr>
                            <td class="cab-logo-cel">
                                <img src="{{ public_path('img/Logo-rec.png') }}" alt="{{ config('rentaltech.empresa.razon_social') }}">
                            </td>
                            <td class="cab-emp-cel">
                                <b>{{ config('rentaltech.empresa.razon_social') }}</b>
                                @if (config('rentaltech.empresa.ruc'))
                                    <p>RUC {{ config('rentaltech.empresa.ruc') }}</p>
                                @endif
                                @if (config('rentaltech.empresa.direccion'))
                                    <p>{{ config('rentaltech.empresa.direccion') }}</p>
                                    <p>D. Comercial: {{ config('rentaltech.empresa.direccion') }}</p>
                                @endif
                                @if (config('rentaltech.empresa.telefono'))
                                    <p>Central telefónica: {{ config('rentaltech.empresa.telefono') }}</p>
                                @endif
                                @if (config('rentaltech.empresa.email'))
                                    <p>Email: {{ config('rentaltech.empresa.email') }}</p>
                                @endif
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="cab-der">
                    <div class="caja-doc">
                        <div class="tipo">ORDEN DE COMPRA</div>
                        <div class="num">{{ $orden->numero_orden }}</div>
                    </div>
                </td>
            </tr>
        </table>

        {{-- ══ Datos: proveedor a la izquierda, fechas a la derecha ══ --}}
        <table class="datos">
            <tr>
                <td class="k">Proveedor:</td>
                <td>{{ $orden->proveedor }}</td>
                <td rowspan="4" class="datos-der">
                    <span class="k">Fecha de emisión:</span>
                    {{ $orden->fecha?->format('Y-m-d') }}
                    <br><br>
                    <span class="k">Fecha de vencimiento:</span>
                    {{ $orden->fecha_vencimiento?->format('Y-m-d') ?: '—' }}
                </td>
            </tr>
            <tr>
                <td class="k">RUC:</td>
                <td>{{ $orden->ruc ?: '—' }}</td>
            </tr>
            <tr>
                <td class="k">Dirección:</td>
                <td>{{ $direccionOrden ?: '—' }}</td>
            </tr>
            <tr>
                <td class="k">Condición de pago:</td>
                <td>{{ $orden->condicion_pago ?: 'Contado' }}</td>
            </tr>
        </table>

        {{-- ══ Detalle ══ --}}
        <table class="items">
            <thead>
                <tr>
                    <th style="width:11%;">COD.</th>
                    <th class="r" style="width:9%;">CANT.</th>
                    <th class="c" style="width:9%;">UND</th>
                    <th>DESCRIPCIÓN</th>
                    <th class="r" style="width:11%;">P.UNIT</th>
                    <th class="r" style="width:9%;">DTO.</th>
                    <th class="r" style="width:12%;">TOTAL</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($productos as $producto)
                @php $precio = $precioLinea($producto); @endphp
                <tr>
                    <td>{{ $producto['codigo'] ?? '—' }}</td>
                    <td class="r">{{ number_format($producto['cantidad'] ?? 0) }}</td>
                    <td class="c">{{ $producto['unidad'] ?? 'UND' }}</td>
                    <td>{{ $producto['descripcion'] ?? ($producto['nombre'] ?? '—') }}</td>
                    <td class="r">{{ number_format($precio, 2) }}</td>
                    <td class="r">0.00</td>
                    <td class="r">{{ number_format(($producto['cantidad'] ?? 0) * $precio, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6">Esta orden no tiene líneas de producto.</td><td class="r">0.00</td></tr>
            @endforelse
            </tbody>
        </table>

        @if ($lineasMerch)
            <div class="seccion-tit">Merch para clientes</div>
            <table class="items">
                <thead>
                    <tr>
                        <th class="c" style="width:8%;">#</th>
                        <th>Artículo</th>
                        <th class="r" style="width:14%;">Cantidad</th>
                        <th class="r" style="width:16%;">Costo unitario</th>
                        <th class="r" style="width:16%;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($lineasMerch as $i => $linea)
                    <tr>
                        <td class="c">{{ $i + 1 }}</td>
                        <td>{{ $nombresMerch[$linea['merch_id'] ?? 0] ?? '— artículo eliminado —' }}</td>
                        <td class="r">{{ number_format($linea['cantidad'] ?? 0) }}</td>
                        <td class="r">S/ {{ number_format($linea['costo_unit'] ?? 0, 2) }}</td>
                        <td class="r">S/ {{ number_format(($linea['cantidad'] ?? 0) * ($linea['costo_unit'] ?? 0), 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif

        {{-- ══ Totales: OP. GRAVADAS + IGV, igual que en Boletas/Notas de Venta ══ --}}
        <table class="totales">
            @unless ($esSolesNativo)
                <tr><td class="lbl">Total en dólares</td><td class="val">$ {{ number_format($orden->total_usd, 2) }}</td></tr>
                <tr><td class="lbl">Tipo de cambio</td><td class="val">{{ number_format($orden->tc, 4) }}</td></tr>
            @endunless
            @if ($totalMerch > 0)
                <tr><td class="lbl">Merch (aparte)</td><td class="val">S/ {{ number_format($totalMerch, 2) }}</td></tr>
            @endif
            <tr><td class="lbl">OP. GRAVADAS</td><td class="val">S/ {{ number_format($opGravadas, 2) }}</td></tr>
            <tr><td class="lbl">IGV</td><td class="val">S/ {{ number_format($igvMonto, 2) }}</td></tr>
            <tr class="gran"><td class="lbl">TOTAL A PAGAR</td><td class="val">S/ {{ number_format($totalPagar, 2) }}</td></tr>
        </table>

        @if ($orden->observaciones)
            <div class="obs">
                <span class="k">Observaciones</span>
                {{ $orden->observaciones }}
            </div>
        @endif

        <div class="pie">{{ config('rentaltech.empresa.razon_social') }} — Orden de compra generada el {{ now()->format('d/m/Y H:i') }}</div>
    </div>
@endforeach

</body>
</html>
