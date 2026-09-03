@php
    $numero   = $venta->n_seri && $venta->n_comp ? $venta->n_seri.' - '.$venta->n_comp : $venta->numero_venta;
    $simbolo  = $venta->moneda === 'USD' ? 'US$' : 'S/';
    $monedaTexto = $venta->moneda === 'USD' ? 'Dólares' : 'S/ Soles';
    // mb_strtoupper: strtoupper() no convierte acentos («Crédito» quedaba «CRéDITO»).
    $etiqueta = mb_strtoupper($tipos[$venta->tipcomp]['nombre'] ?? 'Comprobante');
    // «01 — Factura» viene con el código delante; en la cabecera va sólo el nombre.
    $etiqueta = trim(preg_replace('/^\d+\s*—\s*/u', '', $etiqueta));
    // Una Nota de Crédito/Débito solo pasa por el circuito SUNAT si corrige un
    // comprobante real ya aceptado — las filas tipcomp=07 creadas por el
    // importador de cobranzas (contabilidad interna) no tienen venta_origen_id.
    $esComprobanteElectronico = in_array($venta->tipcomp, ['01', '03'], true)
        || ($venta->venta_origen_id !== null && in_array($venta->tipcomp, ['07', '08'], true));
    $saldo = $venta->monto_pendiente !== null ? (float) $venta->monto_pendiente : (float) $venta->total;
    $pagado = (float) ($venta->monto_pagado ?? 0);
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
        .hoja { width: 760px; max-width: 100%; margin:0 auto; background:#fff; padding:26px 30px; box-shadow:0 8px 30px rgba(0,0,0,.10); }

        .barra { width: 760px; max-width:100%; margin:0 auto 12px; display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
        .barra .ok {
            flex-basis:100%; padding:9px 14px; border:1px solid #b7e0d6; border-radius:6px;
            background:#e8f5f3; color:#1f6b5e; font-size:12px; font-weight:bold;
        }
        .barra button, .barra a {
            padding:8px 16px; border:1px solid #DCDAD2; border-radius:6px; background:#fff;
            font-family:inherit; font-size:12px; cursor:pointer; text-decoration:none; color:#14161B; white-space:nowrap;
        }
        .barra .primario { border-color:#3d9b8c; background:#3d9b8c; color:#fff; }
        .barra .imprimir-grupo { display:inline-flex; border:1px solid #DCDAD2; border-radius:6px; overflow:hidden; margin-left:auto; }
        .barra .imprimir-grupo button { border:none; border-radius:0; }
        .barra .imprimir-grupo button + button { border-left:1px solid #DCDAD2; }

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
            width:760px; max-width:100%; margin:0 auto 12px; padding:9px 14px;
            border:1px solid #f0c9c9; border-radius:6px; background:#fbeaea;
            color:#a12b2b; font-size:11.5px;
        }

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

        .son { font-size:9.5px; margin:8px 0 12px; }
        .son .lbl { font-weight:bold; margin-right:8px; }

        /* ── Cuotas + totales ── */
        .inferior { display:table; width:100%; margin-bottom:18px; }
        .inf-izq, .inf-der { display:table-cell; vertical-align:top; }
        .inf-izq { width:52%; padding-right:16px; }
        .inf-der { width:48%; }

        .cuotas { width:100%; border-collapse:collapse; }
        .cuotas th { padding:3px 6px; font-size:9px; text-align:left; border-bottom:1px solid #000; white-space:nowrap; }
        .cuotas td { padding:4px 6px; font-size:9.5px; }

        .totales { width:100%; border:1px solid #000; border-radius:4px; overflow:hidden; border-collapse:collapse; }
        .totales td { padding:5px 10px; font-size:9.5px; border-top:1px solid #eee; }
        .totales tr:first-child td { border-top:none; }
        .totales .lbl { font-weight:bold; }
        .totales .val { text-align:right; white-space:nowrap; }
        .totales tr.final td { font-weight:bold; font-size:13px; background:#f4f3ef; border-top:1px solid #000; }

        /* ── Pie: condiciones y cuentas bancarias ── */
        .legal { border:1px solid #000; padding:7px 9px; font-size:8px; font-weight:bold; line-height:1.5; margin-bottom:18px; }

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
            .barra, .nota-sunat { display:none; }
        }

        /* ── Formato 80mm (ticket térmico): todo apilado en una sola columna ── */
        body.formato-80mm .hoja { width:80mm; padding:6px 8px; font-size:9px; }
        body.formato-80mm .cab, body.formato-80mm .cab-izq, body.formato-80mm .cab-der,
        body.formato-80mm .inferior, body.formato-80mm .inf-izq, body.formato-80mm .inf-der,
        body.formato-80mm .pie { display:block; width:100%; }
        body.formato-80mm .cab-der { padding-left:0; margin-top:8px; }
        body.formato-80mm .cab-logo { text-align:center; }
        body.formato-80mm .cab-logo img { max-height:40px; }
        body.formato-80mm .cab-emp { text-align:center; }
        body.formato-80mm .caja-doc { border-style:dashed; }
        body.formato-80mm .datos .k { width:auto; display:inline-block; }
        body.formato-80mm .datos-fecha { text-align:left; }
        body.formato-80mm .items th, body.formato-80mm .items td { font-size:8px; padding:3px 4px; }
        body.formato-80mm .items th:nth-child(3), body.formato-80mm .items td:nth-child(3) { display:none; } /* UND: se omite, no cabe */
        body.formato-80mm .inf-izq { padding-right:0; margin-bottom:10px; }
        body.formato-80mm .cb-grid { display:block; }
        body.formato-80mm .cb-caja { display:block; width:auto; margin-bottom:8px; }
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

    @if ($esComprobanteElectronico)
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

        @if ($estado === 'aceptado' && in_array($venta->tipcomp, ['01', '03'], true))
            <a href="{{ route('admin.ventas.notas.create', $venta) }}">Nota de Crédito / Débito</a>
        @endif
    @endif

    <a href="{{ route('admin.ventas.index') }}">← Ventas</a>
    <a href="{{ route('admin.ventas.factura.create') }}">＋ Nueva venta</a>

    <div class="imprimir-grupo">
        <button type="button" onclick="imprimirComo('80mm')">Imprimir 80mm</button>
        <button type="button" onclick="imprimirComo('a4')">Imprimir A4</button>
    </div>
</div>

@if ($esComprobanteElectronico && in_array($venta->estado_factura, ['rechazado', 'error']) && $venta->nota_contadora)
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
                <div class="tipo">{{ $etiqueta }}</div>
                <div class="num">N° {{ $numero }}</div>
            </div>
        </div>
    </div>

    {{-- ══ Datos del cliente y condiciones ══ --}}
    <table class="datos">
        <tr>
            <td class="k">Señores:</td>
            <td>{{ $venta->cliente_nombre ?: $venta->razonsocial ?: '—' }}</td>
            <td rowspan="7" class="datos-fecha">
                <span class="k">Fecha de emisión</span>
                <span class="v">{{ $venta->fecha?->format('Y-m-d') }}</span>
            </td>
        </tr>
        <tr>
            <td class="k">RUC / DNI:</td>
            <td>{{ $venta->cliente_ruc ?: $venta->n_ruc ?: '—' }}</td>
        </tr>
        <tr>
            <td class="k">Dirección:</td>
            <td>{{ $venta->cliente_direccion ?: '—' }}</td>
        </tr>
        <tr>
            <td class="k">Forma de pago:</td>
            <td>{{ $venta->condicion_pago ?: 'Contado' }} — MONEDA: {{ $monedaTexto }}</td>
        </tr>
        <tr>
            <td class="k">Vendedor:</td>
            <td>{{ $venta->vendedor ?: $venta->usuario?->username ?: '—' }}</td>
        </tr>
        <tr>
            <td class="k">N° de Venta:</td>
            <td>{{ $venta->numero_venta ?: '—' }} @if ($venta->guias->isNotEmpty()) — N° Guía: {{ $venta->guias->pluck('numero_guia')->filter()->implode(', ') }} @endif</td>
        </tr>
        @if ($venta->ventaOrigen)
            <tr>
                <td class="k">Corresponde a:</td>
                <td>
                    {{ $venta->ventaOrigen->n_seri }}-{{ $venta->ventaOrigen->n_comp }} —
                    {{ $venta->tipcomp === '07' ? \App\Models\Venta::MOTIVOS_CREDITO[$venta->cod_motivo] ?? '—' : \App\Models\Venta::MOTIVOS_DEBITO[$venta->cod_motivo] ?? '—' }}
                </td>
            </tr>
        @else
            <tr><td colspan="2"></td></tr>
        @endif
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
                <td class="r">{{ number_format($detalle->cantidad, 2) }}</td>
                <td class="c">{{ $detalle->producto?->presentacion ?: '' }}</td>
                <td>{{ $detalle->prod_nombre }}</td>
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
            @if ($pagado > 0)
                <div style="margin-top:10px;font-size:9.5px;">
                    <div><b>PAGOS:</b> {{ $simbolo }} {{ number_format($pagado, 2) }}</div>
                    <div><b>SALDO:</b> {{ $simbolo }} {{ number_format($saldo, 2) }}</div>
                </div>
            @endif
        </div>

        <div class="inf-der">
            <table class="totales">
                <tr>
                    <td class="lbl">Total Ope. Gravadas</td>
                    <td class="val">{{ $simbolo }} {{ number_format($venta->baseimp, 2) }}</td>
                </tr>
                <tr>
                    <td class="lbl">Total Ope. Inafectas</td>
                    <td class="val">{{ $simbolo }} {{ number_format($venta->inafecto, 2) }}</td>
                </tr>
                <tr>
                    <td class="lbl">Total Ope. Exoneradas</td>
                    <td class="val">{{ $simbolo }} {{ number_format($venta->exonerado, 2) }}</td>
                </tr>
                <tr>
                    <td class="lbl">Total IGV</td>
                    <td class="val">{{ $simbolo }} {{ number_format($venta->igv, 2) }}</td>
                </tr>
                <tr class="final">
                    <td class="lbl">TOTAL A PAGAR</td>
                    <td class="val">{{ $simbolo }} {{ number_format($venta->total, 2) }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- ══ Condiciones ══ --}}
    <div class="legal">
        1.- SI EL PRESENTE COMPROBANTE NO ES CANCELADO A SU VENCIMIENTO GENERARÁ INTERESES
        MORATORIOS Y COMPENSATORIOS.<br>
        2.- SÍRVASE GIRAR EL CHEQUE NO NEGOCIABLE A NOMBRE DE {{ strtoupper(config('rentaltech.empresa.razon_social')) }}.<br>
        3.- EL PAGO SE REALIZARÁ EN NUESTRAS OFICINAS O BANCOS, EN NINGÚN CASO A PERSONAL NO
        AUTORIZADO.
    </div>

    {{-- ══ Cuentas bancarias ══ --}}
    @if (! empty(config('rentaltech.cuentas_bancarias')))
        <div class="cb-tit">CUENTAS BANCARIAS</div>
        <div class="cb-grid">
            @foreach (config('rentaltech.cuentas_bancarias') as $cuenta)
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

<script>
/**
 * El tamaño de página no se puede condicionar por clase en @page de forma
 * fiable entre navegadores — se inyecta la regla @page correcta justo antes
 * de imprimir y se retira después, en vez de mantener dos @page fijas.
 */
function imprimirComo(formato) {
    document.body.classList.toggle('formato-80mm', formato === '80mm');

    const estilo = document.createElement('style');
    estilo.id = 'estilo-pagina-impresion';
    estilo.textContent = formato === '80mm'
        ? '@page { size: 80mm auto; margin: 2mm; }'
        : '@page { size: A4; margin: 15mm; }';
    document.head.appendChild(estilo);

    window.print();

    setTimeout(() => estilo.remove(), 500);
}
</script>

</body>
</html>
