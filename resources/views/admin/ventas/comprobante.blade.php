@php
    $numero   = $venta->n_seri && $venta->n_comp ? $venta->n_seri.'-'.$venta->n_comp : $venta->numero_venta;
    $simbolo  = $venta->moneda === 'USD' ? 'US$' : 'S/';
    $monedaTexto = $venta->moneda === 'USD' ? 'Dólares' : 'Soles';
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
        .hoja { width: 760px; max-width: 100%; margin:0 auto; background:#fff; padding:30px 34px; box-shadow:0 8px 30px rgba(0,0,0,.10); }

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

        /* ── Cabecera: igual al formato oficial — logo junto al bloque de la
           empresa a la izquierda, caja del tipo de documento a la derecha ── */
        .cab { display:table; width:100%; margin-bottom:16px; }
        .cab-izq, .cab-der { display:table-cell; vertical-align:middle; }
        .cab-izq { width:62%; }
        .cab-der { width:38%; padding-left:16px; }

        .cab-marca { display:table; width:100%; }
        .cab-logo-cel { display:table-cell; width:64px; vertical-align:top; }
        .cab-logo-cel img { width:54px; height:54px; object-fit:contain; }
        .cab-emp-cel { display:table-cell; vertical-align:top; padding-left:12px; text-align:center; }
        .cab-emp b { font-size:12px; }
        .cab-emp p { font-size:8.7px; line-height:1.55; margin-top:1px; }

        .caja-doc { border:1px solid #000; padding:12px 14px; text-align:center; }
        .caja-doc .tipo { font-size:12px; letter-spacing:.03em; }
        .caja-doc .num { font-size:17px; font-weight:bold; margin-top:8px; letter-spacing:.01em; }

        /* ── Datos del cliente ── */
        .datos { width:100%; margin-bottom:14px; }
        .datos td { padding:1.5px 0; font-size:9.5px; vertical-align:top; }
        .datos .k { font-weight:bold; white-space:nowrap; width:160px; }
        .datos-fecha { text-align:right; white-space:nowrap; }
        .datos-fecha .k { font-weight:bold; }

        /* ── Ítems: filas separadas por línea punteada, sin grilla pesada ── */
        .items { width:100%; border-collapse:collapse; margin-bottom:4px; }
        .items th {
            border-bottom:1.5px solid #000; padding:5px 6px; font-size:9px;
            font-weight:bold; text-align:left; white-space:nowrap;
        }
        .items td { padding:5px 6px; font-size:9.5px; border-bottom:1px dotted #999; }
        .items tbody tr:last-child td { border-bottom:1.5px solid #000; }
        .r { text-align:right; }
        .c { text-align:center; }

        .total-pagar { text-align:right; font-size:12.5px; font-weight:bold; padding:9px 6px 18px; }

        /* ── Información adicional / saldo ── */
        .adicional { margin-bottom:20px; }
        .adicional .fila { font-size:9.5px; padding:1.5px 0; }
        .adicional .lbl { font-weight:bold; margin-right:6px; }
        .adicional .saldo { font-size:10.5px; font-weight:bold; }

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
            .barra, .nota-sunat { display:none; }
        }

        /* ── Formato 80mm (ticket térmico): todo apilado en una sola columna ── */
        body.formato-80mm .hoja { width:80mm; padding:6px 8px; font-size:9px; }
        body.formato-80mm .cab, body.formato-80mm .cab-izq, body.formato-80mm .cab-der { display:block; width:100%; }
        body.formato-80mm .cab-der { padding-left:0; margin-top:8px; }
        body.formato-80mm .cab-marca, body.formato-80mm .cab-logo-cel, body.formato-80mm .cab-emp-cel { display:block; width:100%; text-align:center; padding-left:0; }
        body.formato-80mm .cab-logo-cel img { margin:0 auto; }
        body.formato-80mm .datos .k { width:auto; display:inline-block; }
        body.formato-80mm .datos-fecha { text-align:left; }
        body.formato-80mm .items th, body.formato-80mm .items td { font-size:8px; padding:3px 4px; }
        body.formato-80mm .items th:nth-child(3), body.formato-80mm .items td:nth-child(3) { display:none; } /* UND: se omite, no cabe */
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

    @if ($esComprobanteElectronico && config('empresas.activa.sunat_habilitado', true))
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
            <div class="cab-marca">
                <div class="cab-logo-cel">
                    <img src="{{ asset('img/Logo-docs.png') }}" alt="{{ config('rentaltech.empresa.razon_social') }}">
                </div>
                <div class="cab-emp-cel cab-emp">
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
                </div>
            </div>
        </div>
        <div class="cab-der">
            <div class="caja-doc">
                <div class="tipo">{{ $etiqueta }}</div>
                <div class="num">{{ $numero }}</div>
            </div>
        </div>
    </div>

    {{-- ══ Datos del cliente y condiciones ══ --}}
    <table class="datos">
        <tr>
            <td class="k">Cliente:</td>
            <td>{{ $venta->cliente_nombre ?: $venta->razonsocial ?: '—' }}</td>
            <td rowspan="{{ ($venta->ventaOrigen || $venta->guias->isNotEmpty()) ? 7 : 6 }}" class="datos-fecha">
                <span class="k">Fecha de emisión:</span>
                {{ $venta->fecha?->format('Y-m-d') }}
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
            <td class="k">T. Pago:</td>
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
        @if ($venta->ventaOrigen)
            <tr>
                <td class="k">Corresponde a:</td>
                <td>
                    {{ $venta->ventaOrigen->n_seri }}-{{ $venta->ventaOrigen->n_comp }} —
                    {{ $venta->tipcomp === '07' ? \App\Models\Venta::MOTIVOS_CREDITO[$venta->cod_motivo] ?? '—' : \App\Models\Venta::MOTIVOS_DEBITO[$venta->cod_motivo] ?? '—' }}
                </td>
            </tr>
        @elseif ($venta->guias->isNotEmpty())
            <tr>
                <td class="k">N° de Guía:</td>
                <td>{{ $venta->guias->pluck('numero_guia')->filter()->implode(', ') }}</td>
            </tr>
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
                <td class="c">{{ $detalle->producto?->presentacion ?: 'UND' }}</td>
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

    <div class="total-pagar">TOTAL A PAGAR: {{ $simbolo }} {{ number_format($venta->total, 2) }}</div>

    {{-- ══ Información adicional y saldo ══ --}}
    <div class="adicional">
        <div class="fila"><span class="lbl">Información adicional:</span>{{ $venta->observaciones ?: '' }}</div>
        <div class="fila"><span class="lbl">PAGOS:</span>{{ $simbolo }} {{ number_format($pagado, 2) }}</div>
        <div class="fila saldo"><span class="lbl">SALDO:</span>{{ $simbolo }} {{ number_format($saldo, 2) }}</div>
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
