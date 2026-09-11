<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Órdenes de Compra</title>
    <style>
        {{-- dompdf: solo CSS básico, nada de flex/grid. --}}
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color:#14161B; }

        .hoja { page-break-after: always; }
        .hoja:last-child { page-break-after: auto; }

        .cab { width:100%; margin-bottom:14px; }
        .cab td { vertical-align:top; }
        .cab-emp { width:62%; }
        .cab-emp b { font-size:13px; }
        .cab-emp p { font-size:8.7px; color:#555; line-height:1.5; margin-top:2px; }
        .cab-doc { width:38%; text-align:right; }
        .caja-doc {
            display:inline-block; border:1px solid #000; padding:10px 16px;
            text-align:center; min-width:170px;
        }
        .caja-doc .tipo { font-size:11px; letter-spacing:.03em; }
        .caja-doc .num { font-size:16px; font-weight:bold; margin-top:6px; }
        .caja-doc .fecha { font-size:9px; color:#555; margin-top:4px; }

        .estado-pill {
            display:inline-block; margin-top:6px; padding:2px 9px; border-radius:9px;
            font-size:8.5px; font-weight:bold; letter-spacing:.02em;
        }
        .estado-pendiente  { background:#f1f0eb; color:#6b6560; }
        .estado-transito   { background:#e8f0fb; color:#2563eb; }
        .estado-recibido   { background:#e8f5f3; color:#1f6b5e; }
        .estado-cancelado  { background:#fbeaea; color:#a12b2b; }

        .datos { width:100%; border-collapse:collapse; margin-bottom:12px; }
        .datos td {
            border:1px solid #E2E8F0; padding:6px 9px; font-size:9px; vertical-align:top;
        }
        .datos .k { font-weight:bold; color:#555; display:block; font-size:8px; text-transform:uppercase; letter-spacing:.03em; margin-bottom:2px; }

        .seccion-tit {
            font-size:10.5px; font-weight:bold; margin:14px 0 6px;
            padding-bottom:4px; border-bottom:1.5px solid #000;
        }

        table.items { width:100%; border-collapse:collapse; margin-bottom:6px; }
        table.items th {
            background:#F3F4F6; border:1px solid #E2E8F0; padding:5px 7px;
            font-size:8.5px; font-weight:bold; color:#374151; text-align:left;
        }
        table.items td { border:1px solid #E2E8F0; padding:5px 7px; font-size:9px; }
        table.items tr:nth-child(even) td { background:#FAFAFA; }
        .r { text-align:right; }
        .c { text-align:center; }

        .totales { width:100%; margin-top:8px; }
        .totales td { padding:3px 0; font-size:9.5px; }
        .totales .lbl { text-align:right; padding-right:14px; color:#555; }
        .totales .val { text-align:right; width:130px; }
        .totales .gran .lbl, .totales .gran .val { font-size:13px; font-weight:bold; color:#000; padding-top:6px; }

        .obs { margin-top:12px; font-size:9px; line-height:1.5; color:#333; }
        .obs .k { font-weight:bold; display:block; margin-bottom:2px; }

        .pie { margin-top:20px; font-size:8px; color:#888; text-align:center; }
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

        $estado = \Illuminate\Support\Str::lower(trim((string) $orden->estado));
        $claseEstado = match (true) {
            str_contains($estado, 'recib') => 'estado-recibido',
            str_contains($estado, 'tráns') || str_contains($estado, 'trans') => 'estado-transito',
            str_contains($estado, 'cancel') => 'estado-cancelado',
            default => 'estado-pendiente',
        };
    @endphp

    <div class="hoja">
        <table class="cab">
            <tr>
                <td class="cab-emp">
                    <b>{{ config('rentaltech.empresa.razon_social') }}</b>
                    @if (config('rentaltech.empresa.ruc'))
                        <p>RUC {{ config('rentaltech.empresa.ruc') }}</p>
                    @endif
                    @if (config('rentaltech.empresa.direccion'))
                        <p>{{ config('rentaltech.empresa.direccion') }}</p>
                    @endif
                    @if (config('rentaltech.empresa.telefono'))
                        <p>Tel: {{ config('rentaltech.empresa.telefono') }}</p>
                    @endif
                </td>
                <td class="cab-doc">
                    <div class="caja-doc">
                        <div class="tipo">ORDEN DE COMPRA</div>
                        <div class="num">{{ $orden->numero_orden }}</div>
                        <div class="fecha">{{ $orden->fecha?->translatedFormat('d \d\e F \d\e Y') }}</div>
                        <div><span class="estado-pill {{ $claseEstado }}">{{ $orden->estado ?: 'Pendiente' }}</span></div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="datos">
            <tr>
                <td style="width:34%;"><span class="k">Proveedor</span>{{ $orden->proveedor }}</td>
                <td style="width:22%;"><span class="k">RUC</span>{{ $orden->ruc ?: '—' }}</td>
                <td style="width:22%;"><span class="k">Condición de pago</span>{{ $orden->condicion_pago ?: 'Contado' }}</td>
                <td style="width:22%;"><span class="k">Cliente</span>{{ $orden->cliente_ref ?: 'Sin asignar' }}</td>
            </tr>
            <tr>
                <td><span class="k">Factura</span>{{ $orden->nro_factura ?: 'Pendiente' }}</td>
                <td><span class="k">Guía de remisión</span>{{ $orden->nro_guia ?: 'Pendiente' }}</td>
                <td><span class="k">Transporte</span>{{ $orden->empresa_transporte ?: 'Sin asignar' }}</td>
                <td><span class="k">Peso / Bultos</span>{{ $orden->peso ?: '—' }} · {{ $orden->bultos ?: 0 }} bulto(s)</td>
            </tr>
        </table>

        <div class="seccion-tit">Productos</div>

        @if ($productos)
            <table class="items">
                <thead>
                    <tr>
                        <th style="width:5%;">#</th>
                        <th style="width:15%;">Código</th>
                        <th>Descripción</th>
                        <th class="r" style="width:11%;">Cantidad</th>
                        <th class="r" style="width:14%;">P. unitario</th>
                        <th class="r" style="width:14%;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($productos as $i => $producto)
                    @php $precio = $precioLinea($producto); @endphp
                    <tr>
                        <td class="c">{{ $i + 1 }}</td>
                        <td>{{ $producto['codigo'] ?? '—' }}</td>
                        <td>{{ $producto['descripcion'] ?? ($producto['nombre'] ?? '—') }}</td>
                        <td class="r">{{ number_format($producto['cantidad'] ?? 0) }}</td>
                        <td class="r">{{ $simbolo }} {{ number_format($precio, 2) }}</td>
                        <td class="r">{{ $simbolo }} {{ number_format(($producto['cantidad'] ?? 0) * $precio, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <p style="font-size:9px;color:#888;">Esta orden no tiene líneas de producto.</p>
        @endif

        @if ($lineasMerch)
            <div class="seccion-tit">Merch para clientes</div>
            <table class="items">
                <thead>
                    <tr>
                        <th style="width:5%;">#</th>
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

        <table class="totales">
            @unless ($esSolesNativo)
                <tr><td class="lbl">Total en dólares</td><td class="val">$ {{ number_format($orden->total_usd, 2) }}</td></tr>
                <tr><td class="lbl">Tipo de cambio</td><td class="val">{{ number_format($orden->tc, 4) }}</td></tr>
            @endunless
            @if ($totalMerch > 0)
                <tr><td class="lbl">Merch (aparte)</td><td class="val">S/ {{ number_format($totalMerch, 2) }}</td></tr>
            @endif
            <tr class="gran"><td class="lbl">Total en soles</td><td class="val">S/ {{ number_format($orden->total_soles, 2) }}</td></tr>
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
