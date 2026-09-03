@php
    $numero = trim($venta->n_seri.'-'.$venta->n_comp, '-') ?: $venta->numero_venta;
    $simbolo = $venta->moneda === 'USD' ? 'US$' : 'S/';
    $monedaTexto = $venta->moneda === 'USD' ? 'Dólares' : 'S/ Soles';
    $saldo = $venta->monto_pendiente !== null ? (float) $venta->monto_pendiente : (float) $venta->total;
    $pagado = (float) ($venta->monto_pagado ?? 0);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotización {{ $numero }} — {{ config('rentaltech.empresa.razon_social') }}</title>
    <style>
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10px; color:#000; background:#EFEEE8; padding:24px 14px; }
        .hoja { width: 760px; max-width: 100%; margin:0 auto; background:#fff; padding:26px 30px; box-shadow:0 8px 30px rgba(0,0,0,.10); }

        .barra { width: 760px; max-width:100%; margin:0 auto 12px; display:flex; gap:10px; align-items:center; }
        .barra .ok {
            flex:1; padding:9px 14px; border:1px solid #b7e0d6; border-radius:6px;
            background:#e8f5f3; color:#1f6b5e; font-size:12px; font-weight:bold;
        }
        .barra button, .barra a {
            padding:8px 16px; border:1px solid #DCDAD2; border-radius:6px; background:#fff;
            font-family:inherit; font-size:12px; cursor:pointer; text-decoration:none; color:#14161B; white-space:nowrap;
        }
        .barra .primario { border-color:#3d9b8c; background:#3d9b8c; color:#fff; margin-left:auto; }

        /* ── Cabecera ── */
        .cab { display:table; width:100%; margin-bottom:14px; }
        .cab-izq, .cab-der { display:table-cell; vertical-align:top; }
        .cab-izq { width:60%; }
        .cab-der { width:40%; padding-left:16px; }
        .cab-logo img { max-height:56px; max-width:220px; object-fit:contain; }
        .cab-emp { margin-top:6px; }
        .cab-emp b { font-size:12px; }
        .cab-emp p { font-size:9px; line-height:1.5; margin-top:2px; }

        .caja-doc { border:1px solid #000; border-radius:4px; padding:12px 10px; text-align:center; height:100%; }
        .caja-doc .tipo { font-size:13px; font-weight:bold; letter-spacing:.04em; }
        .caja-doc .num { font-size:13px; font-weight:bold; margin-top:10px; }

        /* ── Datos del cliente ── */
        .datos { width:100%; margin-bottom:12px; }
        .datos td { padding:2px 0; font-size:10px; vertical-align:top; }
        .datos .k { font-weight:bold; white-space:nowrap; width:170px; }
        .datos-fecha { text-align:right; }
        .datos-fecha .k { display:block; font-weight:bold; }
        .datos-fecha .v { font-size:11px; }

        /* ── Ítems ── */
        .items { width:100%; border-collapse:collapse; margin-bottom:6px; }
        .items th { border:1px solid #000; background:#e9e9e9; padding:6px 6px; font-size:9.5px; text-align:left; }
        .items td { border-left:1px solid #000; border-right:1px solid #000; padding:5px 6px; font-size:9.5px; }
        .items tbody tr:last-child td { border-bottom:1px solid #000; }
        .items thead th:first-child, .items tbody td:first-child { border-left:1px solid #000; }
        .r { text-align:right; }
        .c { text-align:center; }

        .total-pagar { text-align:right; font-size:13px; font-weight:bold; padding:8px 2px 16px; }

        /* ── Info adicional / saldo ── */
        .adicional { margin-bottom:18px; }
        .adicional .fila { font-size:10px; padding:2px 0; }
        .adicional .lbl { font-weight:bold; margin-right:6px; }
        .adicional .saldo { font-size:11.5px; font-weight:bold; }

        /* ── Cuentas bancarias ── */
        .cb-tit { text-align:center; font-size:10.5px; font-weight:bold; letter-spacing:.05em; margin-bottom:10px; }
        .cb-grid { display:table; width:100%; border-collapse:separate; border-spacing:10px 0; }
        .cb-caja { display:table-cell; width:50%; border:1px solid #DCDAD2; border-radius:8px; padding:10px 12px; vertical-align:middle; }
        .cb-fila { display:table; width:100%; }
        .cb-icono {
            display:table-cell; width:42px; height:42px; border-radius:6px;
            text-align:center; vertical-align:middle; font-size:8px; font-weight:bold; color:#fff; letter-spacing:.03em;
        }
        .cb-info { display:table-cell; vertical-align:middle; padding-left:10px; font-size:9px; line-height:1.5; }
        .cb-info b { font-size:9.5px; }
        .cb-info .cuenta { font-weight:bold; font-size:10.5px; letter-spacing:.02em; }

        .aviso { margin-top:20px; font-size:8.5px; color:#555; line-height:1.5; text-align:center; }

        @media print {
            body { background:#fff; padding:0; }
            .hoja { box-shadow:none; padding:0; width:auto; }
            .barra { display:none; }
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

    <a href="{{ route('admin.ventas.index') }}">← Ventas</a>
    <a href="{{ route('admin.ventas.factura.create') }}">＋ Nueva venta</a>
    <button type="button" class="primario" onclick="window.print()">Imprimir / Guardar PDF</button>
</div>

<div class="hoja">

    {{-- ══ Cabecera ══ --}}
    <div class="cab">
        <div class="cab-izq">
            <div class="cab-logo">
                <img src="{{ asset('img/logo.png') }}" alt="{{ config('rentaltech.empresa.razon_social') }}">
            </div>
            <div class="cab-emp">
                <b>{{ config('rentaltech.empresa.razon_social') }}</b>
                @if (config('rentaltech.empresa.ruc'))
                    <p>RUC {{ config('rentaltech.empresa.ruc') }}</p>
                @endif
                @if (config('rentaltech.empresa.direccion'))
                    <p>{{ config('rentaltech.empresa.direccion') }}</p>
                @endif
                @if (config('rentaltech.empresa.telefono'))
                    <p>Central telefónica: {{ config('rentaltech.empresa.telefono') }}</p>
                @endif
                @if (config('rentaltech.empresa.email'))
                    <p>Email: {{ config('rentaltech.empresa.email') }}</p>
                @endif
            </div>
        </div>
        <div class="cab-der">
            <div class="caja-doc">
                <div class="tipo">COTIZACIÓN</div>
                <div class="num">{{ $numero }}</div>
            </div>
        </div>
    </div>

    {{-- ══ Datos del cliente y condiciones ══ --}}
    <table class="datos">
        <tr>
            <td class="k">Cliente:</td>
            <td>{{ $venta->cliente_nombre ?: $venta->razonsocial ?: '—' }}</td>
            <td rowspan="6" class="datos-fecha">
                <span class="k">Fecha de emisión</span>
                <span class="v">{{ $venta->fecha?->format('Y-m-d') }}</span>
            </td>
        </tr>
        <tr>
            <td class="k">Doc.trib.no.dom.sin.ruc:</td>
            <td>{{ $venta->cliente_ruc ?: $venta->n_ruc ?: '—' }}</td>
        </tr>
        <tr>
            <td class="k">Dirección:</td>
            <td>{{ $venta->cliente_direccion ?: $venta->cliente_distrito ?: '—' }}</td>
        </tr>
        <tr>
            <td class="k">F.Pago:</td>
            <td>{{ $venta->condicion_pago ?: 'Contado' }}</td>
        </tr>
        <tr>
            <td class="k">MONEDA:</td>
            <td>{{ $monedaTexto }}</td>
        </tr>
        <tr>
            <td class="k">Vendedor:</td>
            <td>{{ $venta->vendedor ?: $venta->usuario?->username ?: '—' }}</td>
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
        @forelse ($venta->detalles as $detalle)
            <tr>
                <td>{{ $detalle->prod_codigo ?: '—' }}</td>
                <td class="r">{{ number_format($detalle->cantidad, 0) }}</td>
                <td class="c">{{ $detalle->producto?->presentacion ?: '' }}</td>
                <td>{{ $detalle->prod_nombre }}</td>
                <td class="r">{{ number_format($detalle->precio_unitario, 2) }}</td>
                <td class="r">0.00</td>
                <td class="r">{{ number_format($detalle->subtotal, 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6">{{ $venta->observaciones ?: 'Cotización registrada por monto único, sin detalle de productos.' }}</td>
                <td class="r">{{ number_format($venta->total, 2) }}</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    <div class="total-pagar">TOTAL A PAGAR: {{ $simbolo }} {{ number_format($venta->total, 2) }}</div>

    {{-- ══ Información adicional y saldo ══ --}}
    <div class="adicional">
        <div class="fila"><span class="lbl">Información adicional:</span>{{ $venta->observaciones ?: '—' }}</div>
        <div class="fila"><span class="lbl">PAGOS:</span>{{ $simbolo }} {{ number_format($pagado, 2) }}</div>
        <div class="fila saldo"><span class="lbl">SALDO:</span>{{ $simbolo }} {{ number_format($saldo, 2) }}</div>
    </div>

    {{-- ══ Cuentas bancarias ══ --}}
    @if (! empty($cuentasBancarias))
        <div class="cb-tit">CUENTAS BANCARIAS</div>
        <div class="cb-grid">
            @foreach ($cuentasBancarias as $cuenta)
                <div class="cb-caja">
                    <div class="cb-fila">
                        <div class="cb-icono" style="background:{{ $cuenta['color'] }};">{{ $cuenta['abrev'] }}</div>
                        <div class="cb-info">
                            <b>{{ $cuenta['banco'] }} {{ $cuenta['moneda'] }}</b><br>
                            {{ $cuenta['titular'] }}<br>
                            <span class="cuenta">{{ $cuenta['cuenta'] }}</span><br>
                            CCI: {{ $cuenta['cci'] }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="aviso">
        Cotización válida por {{ $venta->fecha && $venta->fecha_vencimiento ? $venta->fecha->diffInDays($venta->fecha_vencimiento) : 7 }} días desde la fecha de emisión.
        No constituye comprobante de pago.
    </div>

</div>

</body>
</html>
