<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo }}</title>
    <style>
        {{-- dompdf: solo CSS básico, nada de flex/grid. --}}
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color:#111; }
        .cabecera { margin-bottom: 14px; }
        .cabecera h1 { font-size: 16px; margin-bottom: 3px; }
        .cabecera p { font-size: 10px; color:#555; }
        .resumen { width:100%; margin-bottom: 14px; }
        .resumen td {
            border: 1px solid #E2E8F0; padding: 8px 10px; font-size: 10px;
        }
        .resumen .valor { font-size: 13px; font-weight: bold; display:block; margin-top:2px; }
        table.datos { width: 100%; border-collapse: collapse; }
        table.datos th, table.datos td { border: 1px solid #E2E8F0; padding: 5px 7px; font-size: 9px; text-align: left; }
        table.datos th { background: #F3F4F6; font-weight: bold; color: #374151; }
        table.datos tr:nth-child(even) td { background: #FAFAFA; }
        .pie { margin-top: 14px; font-size: 8px; color: #888; }
    </style>
</head>
<body>
    <div class="cabecera">
        <h1>{{ $titulo }}</h1>
        <p>{{ config('rentaltech.empresa.razon_social') }} — Generado el {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    @if (! empty($resumen))
        <table class="resumen">
            <tr>
                @foreach ($resumen as $etiqueta => $valor)
                    <td>{{ $etiqueta }}<span class="valor">{{ $valor }}</span></td>
                @endforeach
            </tr>
        </table>
    @endif

    <table class="datos">
        <thead>
            <tr>
                @foreach ($columnas as $col)
                    <th>{{ $col }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $fila)
                <tr>
                    @foreach ($fila as $valor)
                        <td>{{ $valor }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($columnas) }}">Sin datos para el periodo seleccionado.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="pie">Casa Drywall — Centro de Reportes</div>
</body>
</html>
