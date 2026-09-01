@php
    $numero   = $venta->n_seri && $venta->n_comp ? $venta->n_seri.' - '.$venta->n_comp : $venta->numero_venta;
    $simbolo  = $venta->moneda === 'USD' ? 'US$' : 'S/';
    $etiqueta = strtoupper($tipos[$venta->tipcomp]['nombre'] ?? 'Comprobante');
    // «01 — Factura» viene con el código delante; en la cabecera va sólo el nombre.
    $etiqueta = trim(preg_replace('/^\d+\s*—\s*/u', '', $etiqueta));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $numero }} — {{ config('rentaltech.empresa.razon_social') }}</title>
    <style>
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10px; color:#000; background:#EFEEE8; padding:24px 14px; }
        .hoja { width: 940px; max-width: 100%; margin:0 auto; background:#fff; padding:26px 30px; box-shadow:0 8px 30px rgba(0,0,0,.10); }

        .barra { width: 940px; max-width:100%; margin:0 auto 12px; display:flex; gap:10px; align-items:center; }
        .barra .ok {
            flex:1; padding:9px 14px; border:1px solid #b7e0d6; border-radius:6px;
            background:#e8f5f3; color:#1f6b5e; font-size:12px; font-weight:bold;
        }
        .barra button, .barra a {
            padding:8px 16px; border:1px solid #DCDAD2; border-radius:6px; background:#fff;
            font-family:inherit; font-size:12px; cursor:pointer; text-decoration:none; color:#14161B; white-space:nowrap;
        }
        .barra .primario { border-color:#3d9b8c; background:#3d9b8c; color:#fff; }

        .estado-sunat {
            display:inline-flex; align-items:center; gap:6px;
            padding:8px 14px; border-radius:6px; font-size:11px; font-weight:bold;
            letter-spacing:.03em; text-transform:uppercase; white-space:nowrap;
        }
        .estado-sunat.pendiente  { background:#f1f0eb; color:#6b6560; }
        .estado-sunat.registrado { background:#e8f0fb; color:#2563eb; }
        .estado-sunat.aceptado   { background:#e8f5f3; color:#1f6b5e; }
        .estado-sunat.rechazado, .estado-sunat.error { background:#fbeaea; color:#a12b2b; }
        .barra .enviar { border-color:#2563eb; background:#2563eb; color:#fff; }
        .nota-sunat {
            width:940px; max-width:100%; margin:0 auto 12px; padding:9px 14px;
            border:1px solid #f0c9c9; border-radius:6px; background:#fbeaea;
            color:#a12b2b; font-size:11.5px;
        }

        /* ── Cabecera ── */
        .cab { display:table; width:100%; margin-bottom:14px; }
        .cab-izq, .cab-der { display:table-cell; vertical-align:top; }
        .cab-izq { width:62%; }
        .cab-der { width:38%; padding-left:16px; }
        .cab-logo { text-align:left; }
        .cab-logo img { max-height:64px; max-width:250px; object-fit:contain; }
        .cab-emp { text-align:center; margin-top:6px; }
        .cab-emp b { font-size:12px; }
        .cab-emp p { font-size:9px; line-height:1.45; margin-top:2px; }

        .caja-doc { border:1px solid #000; padding:12px 10px; text-align:center; }
        .caja-doc .ruc { font-size:11px; font-weight:bold; }
        .caja-doc .tipo { font-size:13px; font-weight:bold; margin:14px 0; }
        .caja-doc .num { font-size:12px; font-weight:bold; }

        /* ── Grilla de datos ── */
        .datos { width:100%; border-collapse:collapse; margin-bottom:10px; }
        .datos td { border:1px solid #000; padding:5px 7px; font-size:9.5px; vertical-align:top; }
        .datos .k { font-weight:bold; white-space:nowrap; width:78px; }
        .datos .k2 { font-weight:bold; white-space:nowrap; width:92px; }

        /* ── Ítems ── */
        .items { width:100%; border-collapse:collapse; margin-bottom:10px; }
        .items th { border:1px solid #000; background:#e9e9e9; padding:6px 7px; font-size:9.5px; text-align:left; }
        .items td { border-left:1px solid #000; border-right:1px solid #000; padding:6px 7px; font-size:9.5px; }
        .items tbody tr:last-child td { border-bottom:1px solid #000; }
        .r { text-align:right; }
        .c { text-align:center; }

        .son { font-size:9.5px; margin:10px 0 12px; }
        .son .lbl { font-weight:bold; margin-right:8px; }

        /* ── Bloque inferior: cuotas + totales ── */
        .inferior { display:table; width:100%; }
        .inf-izq, .inf-der { display:table-cell; vertical-align:top; }
        .inf-izq { width:52%; padding-right:16px; }
        .inf-der { width:48%; }

        .cuotas { width:100%; border-collapse:collapse; }
        .cuotas th { padding:3px 6px; font-size:9px; text-align:left; border-bottom:1px solid #000; white-space:nowrap; }
        .cuotas td { padding:4px 6px; font-size:9.5px; }

        .totales { width:100%; border-collapse:collapse; }
        .totales td { border:1px solid #000; padding:5px 8px; font-size:9.5px; }
        .totales .lbl { font-weight:bold; }
        .totales .val { text-align:right; white-space:nowrap; }
        .totales .cur { width:34px; font-weight:bold; }
        .totales tr.final td { font-weight:bold; font-size:11px; }

        /* ── Pie ── */
        .pie { display:table; width:100%; margin-top:22px; }
        .pie-izq, .pie-der { display:table-cell; vertical-align:top; }
        .pie-izq { width:60%; padding-right:16px; }
        .pie-der { width:40%; }

        .legal { border:1px solid #000; padding:7px 9px; font-size:8px; font-weight:bold; line-height:1.5; }
        .detraccion { border:1px solid #000; width:230px; margin-left:auto; }
        .detraccion td { border:none; padding:5px 9px; font-size:9.5px; }
        .detraccion .tit { font-weight:bold; text-decoration:underline; }
        .detraccion .val { text-align:right; }

        .aviso { margin-top:16px; font-size:8.5px; color:#555; line-height:1.5; }

        @media print {
            body { background:#fff; padding:0; }
            .hoja { box-shadow:none; padding:0; width:auto; }
            .barra, .nota-sunat { display:none; }
        }
    </style>
</head>
<body>

<div class="barra">
    @if (session('mensaje'))
        <div class="ok">✅ {{ session('mensaje') }}</div>
    @endif
    @if (session('error'))
        <div class="ok" style="background:#fbeaea;border-color:#f0c9c9;color:#a12b2b;">⚠️ {{ session('error') }}</div>
    @endif

    @if (in_array($venta->tipcomp, ['01', '03']))
        @php
            $estadosSunat = [
                'pendiente'  => 'Pendiente de registro',
                'registrado' => 'Registrado, falta enviar',
                'aceptado'   => 'Aceptado por SUNAT',
                'rechazado'  => 'Rechazado por SUNAT',
                'error'      => 'Error al enviar',
            ];
            $estado = $venta->estado_factura ?: 'pendiente';
        @endphp
        <span class="estado-sunat {{ $estado }}">SUNAT: {{ $estadosSunat[$estado] ?? $estado }}</span>

        @if ($estado === 'registrado' || $estado === 'rechazado' || $estado === 'error')
            <form method="POST" action="{{ route('admin.ventas.enviar-sunat', $venta) }}" style="display:contents;">
                @csrf
                <button type="submit" class="enviar">Enviar a SUNAT</button>
            </form>
        @endif

        @if ($estado === 'aceptado')
            <a href="{{ route('admin.ventas.pdf-sunat', $venta) }}" target="_blank" class="primario">Descargar PDF oficial</a>
        @endif
    @endif

    <a href="{{ route('admin.ventas.index') }}">← Ventas</a>
    <a href="{{ route('admin.ventas.factura.create') }}">＋ Nueva venta</a>
    <button type="button" onclick="window.print()">Imprimir / Guardar PDF</button>
</div>

@if (in_array($venta->tipcomp, ['01', '03']) && in_array($venta->estado_factura, ['rechazado', 'error']) && $venta->nota_contadora)
    <div class="nota-sunat"><b>Motivo:</b> {{ $venta->nota_contadora }}</div>
@endif

<div class="hoja">

    {{-- ══ Cabecera ══ --}}
    <div class="cab">
        <div class="cab-izq">
            <div class="cab-logo">
                <img src="{{ asset('img/logo.png') }}" alt="{{ config('rentaltech.empresa.razon_social') }}">
            </div>
            <div class="cab-emp">
                <b>{{ config('rentaltech.empresa.razon_social') }}</b>
                @if (config('rentaltech.empresa.direccion') || config('rentaltech.empresa.telefono'))
                    <p>
                        {{ config('rentaltech.empresa.direccion') }}
                        @if (config('rentaltech.empresa.telefono')) Telf: {{ config('rentaltech.empresa.telefono') }} @endif
                    </p>
                @endif
                @if (config('rentaltech.empresa.email'))
                    <p>{{ config('rentaltech.empresa.email') }}</p>
                @endif
            </div>
        </div>
        <div class="cab-der">
            <div class="caja-doc">
                <div class="ruc">R.U.C. N° {{ config('rentaltech.empresa.ruc') ?: '—' }}</div>
                <div class="tipo">{{ $etiqueta }}</div>
                <div class="num">N° {{ $numero }}</div>
            </div>
        </div>
    </div>

    {{-- ══ Datos del cliente y condiciones ══ --}}
    <table class="datos">
        <tr>
            <td class="k">Señores:</td>
            <td>{{ $venta->cliente_nombre ?: $venta->razonsocial }}</td>
            <td class="k2">Fecha:</td>
            <td>{{ $venta->fecha?->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="k">RUC:</td>
            <td>{{ $venta->cliente_ruc ?: $venta->n_ruc ?: '—' }}</td>
            <td class="k2">Forma de Pago:</td>
            <td>{{ $venta->condicion_pago ?: 'Contado' }}</td>
        </tr>
        <tr>
            <td class="k">Dirección:</td>
            <td>{{ $venta->cliente_direccion ?: '—' }}</td>
            <td class="k2">Dias Cred:</td>
            <td>{{ $diasCredito !== null ? $diasCredito : '—' }}</td>
        </tr>
        <tr>
            <td class="k">Vendedor:</td>
            <td>{{ $venta->vendedor ?: '—' }}</td>
            <td class="k2">Fecha de vcto:</td>
            <td>{{ $venta->fecha_vencimiento?->format('d/m/Y') ?: '—' }}</td>
        </tr>
        <tr>
            <td class="k">N° de Venta:</td>
            <td>{{ $venta->numero_venta ?: '—' }}</td>
            <td class="k2">N° de Guia:</td>
            <td>{{ $venta->guias->pluck('numero_guia')->filter()->implode(', ') ?: '—' }}</td>
        </tr>
    </table>

    {{-- ══ Detalle ══ --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width:16%;">Codigo</th>
                <th>Descripción</th>
                <th class="c" style="width:8%;">Unidad</th>
                <th class="r" style="width:9%;">Cantidad</th>
                <th class="r" style="width:12%;">Valor Unitario</th>
                <th class="r" style="width:9%;">Dsctos</th>
                <th class="r" style="width:13%;">Valor de Venta Total</th>
            </tr>
        </thead>
        <tbody>
        @forelse ($venta->detalles as $detalle)
            <tr>
                <td>{{ $detalle->prod_codigo ?: '—' }}</td>
                <td>{{ $detalle->prod_nombre }}</td>
                <td class="c">{{ $detalle->producto?->presentacion ?: '—' }}</td>
                <td class="r">{{ number_format($detalle->cantidad, 2) }}</td>
                <td class="r">{{ number_format($detalle->precio_unitario, 2) }}</td>
                <td class="r">0.00</td>
                <td class="r">{{ number_format($detalle->subtotal, 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6">{{ $venta->observaciones ?: 'Venta registrada por monto único, sin detalle de productos.' }}</td>
                <td class="r">{{ number_format($venta->baseimp + $venta->exonerado + $venta->inafecto, 2) }}</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    {{-- ══ Monto en letras ══ --}}
    <div class="son">
        <span class="lbl">SON:</span>{{ $montoLetras }}
    </div>

    {{-- ══ Cuotas + totales ══ --}}
    <div class="inferior">
        <div class="inf-izq">
            <table class="cuotas">
                <thead>
                    <tr>
                        <th>N° CUOTA</th>
                        <th class="r">MONTO CUOTA</th>
                        <th class="c">FECHA VCTO</th>
                        <th>OBSERVACION</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="c">1</td>
                        <td class="r">{{ number_format($venta->total, 2) }}</td>
                        <td class="c">{{ $venta->fecha_vencimiento?->format('d/m/Y') ?: '—' }}</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="inf-der">
            <table class="totales">
                <tr>
                    <td class="lbl">Total Ope. Gravadas</td>
                    <td class="cur"></td>
                    <td class="val">{{ number_format($venta->baseimp, 2) }}</td>
                </tr>
                <tr>
                    <td class="lbl">Total Ope. Inafectas</td>
                    <td class="cur"></td>
                    <td class="val">{{ number_format($venta->inafecto, 2) }}</td>
                </tr>
                <tr>
                    <td class="lbl">Total Ope. Exoneradas</td>
                    <td class="cur"></td>
                    <td class="val">{{ number_format($venta->exonerado, 2) }}</td>
                </tr>
                <tr>
                    <td class="lbl">Total Descuentos</td>
                    <td class="cur"></td>
                    <td class="val">0.00</td>
                </tr>
                <tr>
                    <td class="lbl">Total IGV</td>
                    <td class="cur"></td>
                    <td class="val">{{ number_format($venta->igv, 2) }}</td>
                </tr>
                <tr class="final">
                    <td class="lbl">TOTAL A PAGAR</td>
                    <td class="cur">{{ $simbolo }}</td>
                    <td class="val">{{ number_format($venta->total, 2) }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- ══ Pie: condiciones y detracción ══ --}}
    <div class="pie">
        <div class="pie-izq">
            <div class="legal">
                1.- SI EL PRESENTE COMPROBANTE NO ES CANCELADO A SU VENCIMIENTO GENERARÁ INTERESES
                MORATORIOS Y COMPENSATORIOS.<br>
                2.- SÍRVASE GIRAR EL CHEQUE NO NEGOCIABLE A NOMBRE DE {{ strtoupper(config('rentaltech.empresa.razon_social')) }}.<br>
                3.- EL PAGO SE REALIZARÁ EN NUESTRAS OFICINAS O BANCOS, EN NINGÚN CASO A PERSONAL NO
                AUTORIZADO.
            </div>
        </div>
        <div class="pie-der">
            <table class="detraccion">
                <tr><td class="tit" colspan="2">Detracción</td></tr>
                <tr><td>Porcentaje:</td><td class="val">0 %</td></tr>
                <tr><td>Imponible:</td><td class="val">0.00</td></tr>
            </table>
        </div>
    </div>

    <div class="aviso">
        Documento interno de {{ config('rentaltech.empresa.razon_social') }} emitido el
        {{ now()->translatedFormat('d \d\e F \d\e Y, H:i') }}.
        @if ($venta->estado_factura === 'aceptado')
            Vista previa interna — el comprobante electrónico oficial validado ante SUNAT
            se descarga con el botón "Descargar PDF oficial".
        @else
            No constituye comprobante de pago electrónico validado ante SUNAT.
        @endif
    </div>

</div>

</body>
</html>
