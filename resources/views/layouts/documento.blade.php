<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') — Rental Tech SAC</title>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family:'Geist', system-ui, sans-serif;
            font-size:13px; color:#14161B; background:#EFEEE8;
            padding:30px 20px; -webkit-font-smoothing:antialiased;
        }
        .hoja {
            max-width:820px; margin:0 auto; background:#fff;
            padding:44px 48px; border-radius:8px;
            box-shadow:0 8px 30px rgba(0,0,0,.10);
        }
        .doc-head { display:flex; justify-content:space-between; align-items:flex-start; gap:30px; padding-bottom:22px; border-bottom:3px solid #9B1E2A; }
        .doc-marca b { display:block; font-size:26px; letter-spacing:-.02em; }
        .doc-marca span { color:#6B6F78; font-size:12px; }
        .doc-caja { border:2px solid #9B1E2A; border-radius:8px; padding:12px 20px; text-align:center; min-width:230px; }
        .doc-caja .tipo { font-size:12px; letter-spacing:.14em; text-transform:uppercase; color:#9B1E2A; font-weight:600; }
        .doc-caja .numero { font-family:monospace; font-size:19px; font-weight:600; margin-top:4px; }
        .doc-bloques { display:grid; grid-template-columns:1fr 1fr; gap:26px; margin:26px 0; }
        .doc-bloque h4 { font-size:11px; letter-spacing:.14em; text-transform:uppercase; color:#6B6F78; margin-bottom:8px; }
        .doc-bloque p { margin:2px 0; }
        table { width:100%; border-collapse:collapse; margin-top:14px; }
        th { background:#F6F5F1; padding:10px; text-align:left; font-size:11px; letter-spacing:.08em; text-transform:uppercase; border-bottom:2px solid #DCDAD2; }
        td { padding:10px; border-bottom:1px solid #EFEEE8; }
        .num { text-align:right; font-variant-numeric:tabular-nums; }
        .totales { margin-top:22px; margin-left:auto; width:290px; }
        .totales div { display:flex; justify-content:space-between; padding:7px 0; }
        .totales .final { border-top:2px solid #14161B; margin-top:6px; padding-top:12px; font-size:17px; font-weight:600; }
        .doc-pie { margin-top:34px; padding-top:18px; border-top:1px solid #E6E4DD; color:#6B6F78; font-size:11.5px; }
        .acciones { max-width:820px; margin:0 auto 18px; display:flex; gap:10px; justify-content:flex-end; }
        .acciones button, .acciones a {
            padding:9px 18px; border:1px solid #DCDAD2; border-radius:8px; background:#fff;
            font-family:inherit; font-size:13px; cursor:pointer; text-decoration:none; color:#14161B;
        }
        @media print {
            body { background:#fff; padding:0; }
            .hoja { box-shadow:none; border-radius:0; padding:0; max-width:none; }
            .acciones { display:none; }
        }
    </style>
</head>
<body>

<div class="acciones">
    <a href="{{ url()->previous() }}">← Volver</a>
    <button type="button" onclick="window.print()">Imprimir / Guardar PDF</button>
</div>

<div class="hoja">
    <div class="doc-head">
        <div class="doc-marca">
            <b>Rental Tech <span style="color:#9B1E2A;">SAC</span></b>
            <span>{{ config('rentaltech.empresa.razon_social') }}</span>
            @if (config('rentaltech.empresa.ruc'))
                <span>RUC {{ config('rentaltech.empresa.ruc') }}</span>
            @endif
            @if (config('rentaltech.empresa.direccion'))
                <span>{{ config('rentaltech.empresa.direccion') }}</span>
            @endif
        </div>

        <div class="doc-caja">
            <div class="tipo">@yield('tipo-documento')</div>
            <div class="numero">@yield('numero-documento')</div>
        </div>
    </div>

    @yield('documento')

    <div class="doc-pie">
        @yield('pie')
        <div style="margin-top:6px;">Documento generado el {{ now()->translatedFormat('d \d\e F \d\e Y, H:i') }}</div>
    </div>
</div>

</body>
</html>
